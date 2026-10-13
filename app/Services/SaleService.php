<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class SaleService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function finalize(array $payload, int $userId): int
    {
        $key = strtolower(trim((string) ($payload['idempotency_key'] ?? '')));
        if (!preg_match('/^[a-f0-9]{32,64}$/', $key)) {
            throw new RuntimeException('Kunci transaksi tidak valid. Muat ulang POS dan coba lagi.');
        }
        $existing = $this->database->table('sales')->where('idempotency_key', $key)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }
        $cart = $payload['cart'] ?? [];
        $payments = $payload['payments'] ?? [];
        if (!is_array($cart) || $cart === [] || !is_array($payments) || $payments === []) {
            throw new RuntimeException('Keranjang dan pembayaran wajib diisi.');
        }

        try {
            $this->database->transException(true)->transStart();
            $shift = $this->database->query('SELECT * FROM shifts WHERE cashier_id = ? AND status = ? FOR UPDATE', [$userId, 'open'])->getRowArray();
            if (!$shift) {
                throw new RuntimeException('Buka shift sebelum menerima pembayaran.');
            }
            $lines = [];
            $requirements = [];
            $subtotal = 0.0;
            foreach ($cart as $entry) {
                $variantId = filter_var($entry['variant_id'] ?? null, FILTER_VALIDATE_INT);
                $quantity = (int) ($entry['quantity'] ?? 0);
                if (!$variantId || $quantity < 1 || $quantity > 99) {
                    throw new RuntimeException('Isi keranjang tidak valid.');
                }
                $variant = $this->database->table('product_variants')
                    ->select('product_variants.*, products.id AS product_id, products.name AS product_name, products.is_active AS product_active')
                    ->join('products', 'products.id = product_variants.product_id')->where('product_variants.id', $variantId)->get()->getRowArray();
                if (!$variant || !$variant['is_active'] || !$variant['product_active']) {
                    throw new RuntimeException('Salah satu produk sudah tidak tersedia.');
                }
                $toppingIds = array_values(array_unique(array_filter(array_map('intval', (array) ($entry['topping_ids'] ?? [])))));
                $toppings = [];
                $toppingPrice = 0.0;
                foreach ($toppingIds as $toppingId) {
                    $topping = $this->database->table('toppings')->where('id', $toppingId)->where('is_active', true)->get()->getRowArray();
                    if (!$topping) {
                        throw new RuntimeException('Salah satu topping sudah tidak tersedia.');
                    }
                    $toppingPrice += (float) $topping['additional_price'];
                    $toppingMaterials = $this->database->table('topping_recipe_items')->where('topping_id', $toppingId)->get()->getResultArray();
                    foreach ($toppingMaterials as $material) {
                        $required = (float) $material['quantity'] * $quantity;
                        $requirements[$material['inventory_item_id']] = ($requirements[$material['inventory_item_id']] ?? 0) + $required;
                    }
                    $toppings[] = ['id' => (int) $topping['id'], 'name' => $topping['name'], 'price' => (float) $topping['additional_price'], 'materials' => $toppingMaterials];
                }
                $recipe = [];
                if ($variant['stock_mode'] === 'recipe') {
                    $recipeRow = $this->database->table('recipes')->where('product_variant_id', $variantId)->where('is_active', true)->get()->getRowArray();
                    if (!$recipeRow) {
                        throw new RuntimeException('Resep untuk ' . $variant['product_name'] . ' belum tersedia.');
                    }
                    $recipe = $this->database->table('recipe_items')->where('recipe_id', $recipeRow['id'])->get()->getResultArray();
                    if ($recipe === []) {
                        throw new RuntimeException('Resep untuk ' . $variant['product_name'] . ' masih kosong.');
                    }
                    foreach ($recipe as $material) {
                        $required = (float) $material['quantity'] * $quantity;
                        $requirements[$material['inventory_item_id']] = ($requirements[$material['inventory_item_id']] ?? 0) + $required;
                    }
                } elseif ($variant['stock_mode'] === 'direct') {
                    $directItemId = (int) ($variant['direct_inventory_item_id'] ?? 0);
                    if (!$directItemId) {
                        throw new RuntimeException('Produk stok langsung belum dipetakan ke item persediaan.');
                    }
                    $requirements[$directItemId] = ($requirements[$directItemId] ?? 0) + $quantity;
                }
                $unitPrice = (float) $variant['price'] + $toppingPrice;
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;
                $lines[] = ['variant' => $variant, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => $lineTotal, 'recipe' => $recipe, 'toppings' => $toppings, 'direct_item_id' => $variant['stock_mode'] === 'direct' ? (int) $variant['direct_inventory_item_id'] : null];
            }

            $lockedItems = [];
            $requirementIds = array_keys($requirements);
            sort($requirementIds);
            foreach ($requirementIds as $itemId) {
                $required = $requirements[$itemId];
                $item = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId])->getRowArray();
                if (!$item || (float) $item['quantity_on_hand'] + 0.0001 < $required) {
                    throw new RuntimeException('Stok ' . ($item['name'] ?? 'bahan') . ' tidak mencukupi.');
                }
                $lockedItems[$itemId] = $item;
            }
            (new InventoryLockService($this->database))->assertUnlocked($requirementIds);

            $discount = max(0, (float) ($payload['discount'] ?? 0));
            if ($discount > $subtotal) {
                throw new RuntimeException('Diskon tidak boleh melebihi subtotal.');
            }
            $total = $subtotal - $discount;
            $paymentTotal = 0.0;
            $normalizedPayments = [];
            foreach ($payments as $payment) {
                $method = (string) ($payment['method'] ?? '');
                $amount = (float) ($payment['amount'] ?? 0);
                if (!in_array($method, ['cash', 'qris', 'bank_transfer', 'debit', 'credit'], true) || $amount <= 0) {
                    throw new RuntimeException('Rincian pembayaran tidak valid.');
                }
                $received = $method === 'cash' ? (float) ($payment['cash_received'] ?? $amount) : null;
                if ($method === 'cash' && $received < $amount) {
                    throw new RuntimeException('Uang tunai diterima kurang dari alokasi tunai.');
                }
                $reference = trim((string) ($payment['reference_number'] ?? ''));
                if ($method !== 'cash' && $reference === '') {
                    throw new RuntimeException('Nomor referensi wajib untuk pembayaran non-tunai.');
                }
                $paymentTotal += $amount;
                $normalizedPayments[] = ['method' => $method, 'amount' => $amount, 'received' => $received, 'change' => $method === 'cash' ? $received - $amount : 0, 'reference' => $reference ?: null];
            }
            if (abs($paymentTotal - $total) > 0.01) {
                throw new RuntimeException('Total alokasi pembayaran harus sama dengan total transaksi.');
            }

            $now = date('Y-m-d H:i:s');
            $saleNumber = 'TRX-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $this->database->table('sales')->insert([
                'sale_number' => $saleNumber, 'idempotency_key' => $key, 'shift_id' => $shift['id'], 'cashier_id' => $userId, 'status' => 'paid',
                'subtotal' => $subtotal, 'discount' => $discount, 'tax' => 0, 'service_charge' => 0, 'total' => $total, 'total_cogs' => 0,
                'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $saleId = (int) $this->database->insertID();
            $totalCogs = 0.0;
            foreach ($lines as $line) {
                $lineCogs = 0.0;
                foreach ($line['recipe'] as $material) {
                    $lineCogs += (float) $material['quantity'] * (float) $lockedItems[$material['inventory_item_id']]['average_cost'] * $line['quantity'];
                }
                foreach ($line['toppings'] as $topping) {
                    foreach ($topping['materials'] as $material) {
                        $lineCogs += (float) $material['quantity'] * (float) $lockedItems[$material['inventory_item_id']]['average_cost'] * $line['quantity'];
                    }
                }
                if ($line['direct_item_id']) {
                    $lineCogs += (float) $lockedItems[$line['direct_item_id']]['average_cost'] * $line['quantity'];
                }
                $totalCogs += $lineCogs;
                $variant = $line['variant'];
                $this->database->table('sale_items')->insert([
                    'sale_id' => $saleId, 'product_id' => $variant['product_id'], 'product_variant_id' => $variant['id'], 'direct_inventory_item_id' => $line['direct_item_id'], 'product_name' => $variant['product_name'],
                    'variant_name' => $variant['name'], 'sku' => $variant['sku'], 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'line_total' => $line['line_total'],
                    'unit_cogs' => $lineCogs / $line['quantity'], 'line_cogs' => $lineCogs,
                    'recipe_snapshot' => $line['recipe'] ? json_encode($line['recipe'], JSON_UNESCAPED_UNICODE) : null,
                    'topping_snapshot' => $line['toppings'] ? json_encode($line['toppings'], JSON_UNESCAPED_UNICODE) : null, 'created_at' => $now,
                ]);
            }
            $this->database->table('sales')->where('id', $saleId)->update(['total_cogs' => $totalCogs]);
            foreach ($normalizedPayments as $payment) {
                $this->database->table('payments')->insert(['sale_id' => $saleId, 'method' => $payment['method'], 'amount' => $payment['amount'], 'cash_received' => $payment['received'], 'change_amount' => $payment['change'], 'reference_number' => $payment['reference'], 'created_at' => $now]);
            }
            foreach ($requirements as $itemId => $required) {
                $item = $lockedItems[$itemId];
                $unitCost = (float) $item['average_cost'];
                $this->database->table('inventory_items')->where('id', $itemId)->update(['quantity_on_hand' => (float) $item['quantity_on_hand'] - $required, 'updated_at' => $now]);
                $this->database->table('stock_movements')->insert(['inventory_item_id' => $itemId, 'quantity' => -$required, 'unit_cost' => $unitCost, 'total_cost' => -($required * $unitCost), 'movement_type' => 'sale', 'source_type' => 'sale', 'source_id' => $saleId, 'notes' => $saleNumber, 'posted_at' => $now, 'created_by' => $userId, 'created_at' => $now]);
            }
            $journalLines = [];
            foreach ($normalizedPayments as $payment) {
                $journalLines[] = ['account_key' => AccountingService::paymentAccountKey($payment['method']), 'debit' => $payment['amount'], 'credit' => 0, 'memo' => 'Pembayaran ' . $payment['method']];
            }
            $netProductRevenue = round($subtotal - $discount, 2);
            if ($netProductRevenue > 0) { $journalLines[] = ['account_key' => 'sales_revenue', 'debit' => 0, 'credit' => $netProductRevenue]; }
            if ($totalCogs > 0) {
                $journalLines[] = ['account_key' => 'cogs', 'debit' => $totalCogs, 'credit' => 0];
                $journalLines[] = ['account_key' => 'inventory', 'debit' => 0, 'credit' => $totalCogs];
            }
            (new AccountingService($this->database))->post('sale', $saleId, date('Y-m-d', strtotime($now)), 'Penjualan ' . $saleNumber, $journalLines, $userId);
            $this->database->transComplete();
            return $saleId;
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            $existing = $this->database->table('sales')->where('idempotency_key', $key)->get()->getRowArray();
            if ($existing) {
                return (int) $existing['id'];
            }
            throw $exception;
        }
    }
}
