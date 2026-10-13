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
        if (count($payments) > 2) {
            throw new RuntimeException('Pembayaran gabungan maksimal menggunakan dua metode.');
        }
        $policy = (new SalesPolicyService($this->database))->current();

        try {
            $this->database->transException(true)->transStart();
            $shift = $this->database->query('SELECT * FROM shifts WHERE cashier_id = ? AND status = ? FOR UPDATE', [$userId, 'open'])->getRowArray();
            if (!$shift) {
                throw new RuntimeException('Buka shift sebelum menerima pembayaran.');
            }
            $lines = [];
            $requirements = [];
            $directUsageByItem = [];
            $subtotalCents = 0;
            $itemDiscountCents = 0;
            $discountNeedsOwnerApproval = false;
            foreach ($cart as $entry) {
                $lineIndex = count($lines);
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
                $rawToppingIds = $entry['topping_ids'] ?? [];
                if (!is_array($rawToppingIds)) {
                    throw new RuntimeException('Pilihan topping tidak valid.');
                }
                $toppingIds = [];
                foreach ($rawToppingIds as $rawToppingId) {
                    if (!is_int($rawToppingId) && !is_string($rawToppingId)) {
                        throw new RuntimeException('Pilihan topping tidak valid.');
                    }
                    $toppingId = filter_var($rawToppingId, FILTER_VALIDATE_INT);
                    if ($toppingId === false || $toppingId < 1) {
                        throw new RuntimeException('Pilihan topping tidak valid.');
                    }
                    $toppingIds[] = $toppingId;
                }
                $toppingIds = array_values(array_unique($toppingIds));
                if ($toppingIds !== [] && $variant['stock_mode'] !== 'recipe') {
                    throw new RuntimeException('Topping hanya tersedia untuk produk dengan resep.');
                }
                $toppings = [];
                $toppingPriceCents = 0;
                foreach ($toppingIds as $toppingId) {
                    $topping = $this->database->table('toppings')->where('id', $toppingId)->where('is_active', true)->get()->getRowArray();
                    if (!$topping) {
                        throw new RuntimeException('Salah satu topping sudah tidak tersedia.');
                    }
                    $toppingPriceCents += Money::cents($topping['additional_price']);
                    $toppingMaterials = $this->database->table('topping_recipe_items')->where('topping_id', $toppingId)->get()->getResultArray();
                    foreach ($toppingMaterials as $material) {
                        $requiredMilli = Money::quantityMilli($material['quantity']) * $quantity;
                        $requirements[$material['inventory_item_id']] = ($requirements[$material['inventory_item_id']] ?? 0) + $requiredMilli;
                    }
                    $toppings[] = ['id' => (int) $topping['id'], 'name' => $topping['name'], 'price' => Money::decimal(Money::cents($topping['additional_price'])), 'materials' => $toppingMaterials];
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
                        $requiredMilli = Money::quantityMilli($material['quantity']) * $quantity;
                        $requirements[$material['inventory_item_id']] = ($requirements[$material['inventory_item_id']] ?? 0) + $requiredMilli;
                    }
                } elseif ($variant['stock_mode'] === 'direct') {
                    $directItemId = (int) ($variant['direct_inventory_item_id'] ?? 0);
                    if (!$directItemId) {
                        throw new RuntimeException('Produk stok langsung belum dipetakan ke item persediaan.');
                    }
                    $directQuantityMilli = $quantity * 1000;
                    $requirements[$directItemId] = ($requirements[$directItemId] ?? 0) + $directQuantityMilli;
                    $directUsageByItem[$directItemId][$lineIndex] = $directQuantityMilli;
                }
                $unitPriceCents = Money::cents($variant['price']) + $toppingPriceCents;
                $lineTotalCents = $unitPriceCents * $quantity;
                $subtotalCents += $lineTotalCents;
                $itemDiscount = SalesPolicyService::calculateDiscount(
                    $lineTotalCents,
                    (string) ($entry['discount_type'] ?? 'amount'),
                    $entry['discount_value'] ?? 0,
                    $policy['item_discount_max_amount_cents'],
                    $policy['item_discount_max_percent_hundredths'],
                );
                $itemDiscountCents += $itemDiscount['amount_cents'];
                $discountNeedsOwnerApproval = $discountNeedsOwnerApproval || $itemDiscount['over_limit'];
                $lines[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitPriceCents,
                    'line_total_cents' => $lineTotalCents,
                    'item_discount_type' => $itemDiscount['type'],
                    'item_discount_value_cents' => $itemDiscount['value_cents'],
                    'item_discount_cents' => $itemDiscount['amount_cents'],
                    'recipe' => $recipe,
                    'toppings' => $toppings,
                    'direct_item_id' => $variant['stock_mode'] === 'direct' ? (int) $variant['direct_inventory_item_id'] : null,
                ];
            }

            $lockedItems = [];
            $requirementIds = array_keys($requirements);
            sort($requirementIds);
            foreach ($requirementIds as $itemId) {
                $requiredMilli = $requirements[$itemId];
                $item = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId])->getRowArray();
                if (!$item || Money::quantityMilli($item['quantity_on_hand']) < $requiredMilli) {
                    throw new RuntimeException('Stok ' . ($item['name'] ?? 'bahan') . ' tidak mencukupi.');
                }
                $lockedItems[$itemId] = $item;
            }
            (new InventoryLockService($this->database))->assertUnlocked($requirementIds);

            $transactionDiscountPayload = $payload['transaction_discount'] ?? [
                'type' => 'amount',
                'value' => $payload['discount'] ?? 0,
            ];
            if (!is_array($transactionDiscountPayload)) {
                throw new RuntimeException('Rincian diskon transaksi tidak valid.');
            }
            $afterItemDiscountCents = $subtotalCents - $itemDiscountCents;
            $transactionDiscount = SalesPolicyService::calculateDiscount(
                $afterItemDiscountCents,
                (string) ($transactionDiscountPayload['type'] ?? 'amount'),
                $transactionDiscountPayload['value'] ?? 0,
                $policy['transaction_discount_max_amount_cents'],
                $policy['transaction_discount_max_percent_hundredths'],
            );
            $transactionDiscountCents = $transactionDiscount['amount_cents'];
            $discountNeedsOwnerApproval = $discountNeedsOwnerApproval || $transactionDiscount['over_limit'];
            $discountCents = $itemDiscountCents + $transactionDiscountCents;
            $serviceBaseCents = $afterItemDiscountCents - $transactionDiscountCents;
            $serviceCents = $policy['service_charge_enabled']
                ? SalesPolicyService::chargeCents($serviceBaseCents, $policy['service_charge_rate_hundredths'])
                : 0;
            $taxBaseCents = $serviceBaseCents + $serviceCents;
            $taxCents = $policy['tax_enabled']
                ? SalesPolicyService::chargeCents($taxBaseCents, $policy['tax_rate_hundredths'])
                : 0;
            $totalCents = $taxBaseCents + $taxCents;
            if ($totalCents <= 0) {
                throw new RuntimeException('Total transaksi harus lebih dari Rp0.');
            }

            $discountApprovedBy = null;
            if ($discountNeedsOwnerApproval) {
                $approval = $payload['discount_approval'] ?? [];
                $identity = is_array($approval) ? trim((string) ($approval['identity'] ?? '')) : '';
                $password = is_array($approval) ? (string) ($approval['password'] ?? '') : '';
                if ($identity === '' || $password === '') {
                    throw new RuntimeException('Masukkan username/email dan kata sandi owner untuk menyetujui diskon ini.');
                }

                $attemptKey = 'pos_discount_approval_' . $userId;
                $attempts = (int) (cache()->get($attemptKey) ?? 0);
                if ($attempts >= 5) {
                    throw new RuntimeException('Terlalu banyak percobaan persetujuan owner. Coba lagi dalam 15 menit.');
                }

                $owner = $this->database->table('users')
                    ->select('id, password_hash')
                    ->where('role', 'owner')->where('status', 'active')
                    ->groupStart()->where('username', $identity)->orWhere('email', $identity)->groupEnd()
                    ->get()->getRowArray();
                if (!$owner || !password_verify($password, $owner['password_hash'])) {
                    cache()->save($attemptKey, $attempts + 1, 900);
                    throw new RuntimeException('Persetujuan owner tidak valid. Periksa kredensial dan coba lagi.');
                }
                cache()->delete($attemptKey);
                $discountApprovedBy = (int) $owner['id'];
            }

            $transactionDiscountAllocations = Money::allocateCents(
                $transactionDiscountCents,
                array_map(static fn (array $line): int => $line['line_total_cents'] - $line['item_discount_cents'], $lines),
            );
            $lineNetCents = [];
            foreach ($lines as $index => &$line) {
                $line['transaction_discount_cents'] = $transactionDiscountAllocations[$index];
                $line['service_charge_cents'] = 0;
                $line['tax_cents'] = 0;
                $lineNetCents[] = $line['line_total_cents'] - $line['item_discount_cents'] - $line['transaction_discount_cents'];
            }
            unset($line);
            $lineServiceCharges = Money::allocateCents($serviceCents, $lineNetCents);
            $lineTaxBases = [];
            foreach ($lineNetCents as $index => $netCents) {
                $lineTaxBases[] = $netCents + $lineServiceCharges[$index];
            }
            $lineTaxes = Money::allocateCents($taxCents, $lineTaxBases);
            foreach ($lines as $index => &$line) {
                $line['service_charge_cents'] = $lineServiceCharges[$index];
                $line['tax_cents'] = $lineTaxes[$index];
            }
            unset($line);

            $paymentTotalCents = 0;
            $normalizedPayments = [];
            $paymentMethods = [];
            foreach ($payments as $payment) {
                if (!is_array($payment)) {
                    throw new RuntimeException('Rincian pembayaran tidak valid.');
                }
                $method = (string) ($payment['method'] ?? '');
                $amountCents = Money::cents($payment['amount'] ?? 0);
                if (!in_array($method, ['cash', 'qris', 'bank_transfer', 'debit', 'credit'], true) || $amountCents <= 0) {
                    throw new RuntimeException('Rincian pembayaran tidak valid.');
                }
                if (in_array($method, $paymentMethods, true)) {
                    throw new RuntimeException('Setiap metode pembayaran hanya boleh dipilih satu kali.');
                }
                $paymentMethods[] = $method;
                $receivedCents = $method === 'cash'
                    ? Money::cents($payment['cash_received'] ?? Money::decimal($amountCents))
                    : null;
                if ($method === 'cash' && $receivedCents < $amountCents) {
                    throw new RuntimeException('Uang tunai diterima kurang dari alokasi tunai.');
                }
                $reference = trim((string) ($payment['reference_number'] ?? ''));
                if ($method !== 'cash' && $reference === '') {
                    throw new RuntimeException('Nomor referensi wajib untuk pembayaran non-tunai.');
                }
                $paymentTotalCents += $amountCents;
                $normalizedPayments[] = [
                    'method' => $method,
                    'amount_cents' => $amountCents,
                    'received_cents' => $receivedCents,
                    'change_cents' => $method === 'cash' ? $receivedCents - $amountCents : 0,
                    'reference' => $reference ?: null,
                ];
            }
            if ($paymentTotalCents !== $totalCents) {
                throw new RuntimeException('Total alokasi pembayaran harus sama dengan total transaksi.');
            }

            $now = date('Y-m-d H:i:s');
            $saleNumber = 'TRX-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $this->database->table('sales')->insert([
                'sale_number' => $saleNumber, 'idempotency_key' => $key, 'shift_id' => $shift['id'], 'cashier_id' => $userId, 'status' => 'paid',
                'subtotal' => Money::decimal($subtotalCents), 'discount' => Money::decimal($discountCents),
                'item_discount' => Money::decimal($itemDiscountCents),
                'transaction_discount' => Money::decimal($transactionDiscountCents),
                'transaction_discount_type' => $transactionDiscount['type'],
                'transaction_discount_value' => Money::decimal($transactionDiscount['value_cents']),
                'discount_approved_by' => $discountApprovedBy,
                'tax' => Money::decimal($taxCents), 'service_charge' => Money::decimal($serviceCents),
                'total' => Money::decimal($totalCents), 'total_cogs' => '0.00',
                'paid_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $saleId = (int) $this->database->insertID();
            $lineCogsProducts = [];
            foreach ($lines as &$line) {
                $cogsProduct = 0;
                foreach ($line['recipe'] as $material) {
                    $quantityMilli = Money::quantityMilli($material['quantity']) * $line['quantity'];
                    $unitCost = Money::unitCostTenThousand($lockedItems[$material['inventory_item_id']]['average_cost']);
                    $cogsProduct += $quantityMilli * $unitCost;
                }
                foreach ($line['toppings'] as $topping) {
                    foreach ($topping['materials'] as $material) {
                        $quantityMilli = Money::quantityMilli($material['quantity']) * $line['quantity'];
                        $unitCost = Money::unitCostTenThousand($lockedItems[$material['inventory_item_id']]['average_cost']);
                        $cogsProduct += $quantityMilli * $unitCost;
                    }
                }
                $directUnitCost = 0;
                if ($line['direct_item_id']) {
                    $directUnitCost = Money::unitCostTenThousand($lockedItems[$line['direct_item_id']]['average_cost']);
                    $cogsProduct += ($line['quantity'] * 1000) * $directUnitCost;
                }
                $line['cogs_product'] = $cogsProduct;
                $line['direct_unit_cogs'] = Money::unitCost($directUnitCost);
                $lineCogsProducts[] = $cogsProduct;
            }
            unset($line);

            $movementCostsCents = [];
            $totalCogsCents = 0;
            foreach ($requirements as $itemId => $requiredMilli) {
                $item = $lockedItems[$itemId];
                $onHandMilli = Money::quantityMilli($item['quantity_on_hand']);
                $inventoryValueCents = Money::cents($item['inventory_value']);
                $unitCost = Money::unitCostTenThousand($item['average_cost']);
                $movementCostCents = $requiredMilli === $onHandMilli
                    ? $inventoryValueCents
                    : min(Money::centsForQuantity($requiredMilli, $unitCost), $inventoryValueCents);
                $movementCostsCents[$itemId] = $movementCostCents;
                $totalCogsCents += $movementCostCents;
            }
            $directLineCogsCents = array_fill(0, count($lines), 0);
            foreach ($directUsageByItem as $itemId => $lineQuantities) {
                $directQuantityMilli = array_sum($lineQuantities);
                $requiredMilli = $requirements[$itemId];
                [$directCostCents] = Money::allocateCents(
                    $movementCostsCents[$itemId],
                    [$directQuantityMilli, $requiredMilli - $directQuantityMilli],
                );
                $lineCosts = Money::allocateCents($directCostCents, array_values($lineQuantities));
                foreach (array_keys($lineQuantities) as $index => $lineIndex) {
                    $directLineCogsCents[$lineIndex] += $lineCosts[$index];
                }
            }
            $lineCogsCents = Money::allocateCents($totalCogsCents, $lineCogsProducts);
            foreach ($lines as $index => $line) {
                $variant = $line['variant'];
                $this->database->table('sale_items')->insert([
                    'sale_id' => $saleId, 'product_id' => $variant['product_id'], 'product_variant_id' => $variant['id'], 'direct_inventory_item_id' => $line['direct_item_id'], 'product_name' => $variant['product_name'],
                    'variant_name' => $variant['name'], 'sku' => $variant['sku'], 'quantity' => $line['quantity'],
                    'unit_price' => Money::decimal($line['unit_price_cents']), 'line_total' => Money::decimal($line['line_total_cents']),
                    'item_discount_type' => $line['item_discount_type'],
                    'item_discount_value' => Money::decimal($line['item_discount_value_cents']),
                    'item_discount_amount' => Money::decimal($line['item_discount_cents']),
                    'transaction_discount_amount' => Money::decimal($line['transaction_discount_cents']),
                    'tax_amount' => Money::decimal($line['tax_cents']),
                    'service_charge_amount' => Money::decimal($line['service_charge_cents']),
                    'unit_cogs' => Money::averageUnitCost($lineCogsCents[$index], $line['quantity'] * 1000),
                    'direct_unit_cogs' => $line['direct_unit_cogs'], 'direct_line_cogs' => Money::decimal($directLineCogsCents[$index]),
                    'line_cogs' => Money::decimal($lineCogsCents[$index]),
                    'recipe_snapshot' => $line['recipe'] ? json_encode($line['recipe'], JSON_UNESCAPED_UNICODE) : null,
                    'topping_snapshot' => $line['toppings'] ? json_encode($line['toppings'], JSON_UNESCAPED_UNICODE) : null, 'created_at' => $now,
                ]);
            }
            $this->database->table('sales')->where('id', $saleId)->update(['total_cogs' => Money::decimal($totalCogsCents)]);
            foreach ($normalizedPayments as $payment) {
                $this->database->table('payments')->insert([
                    'sale_id' => $saleId,
                    'method' => $payment['method'],
                    'amount' => Money::decimal($payment['amount_cents']),
                    'cash_received' => $payment['received_cents'] === null ? null : Money::decimal($payment['received_cents']),
                    'change_amount' => Money::decimal($payment['change_cents']),
                    'reference_number' => $payment['reference'],
                    'created_at' => $now,
                ]);
            }
            foreach ($requirements as $itemId => $required) {
                $item = $lockedItems[$itemId];
                $newQuantityMilli = Money::quantityMilli($item['quantity_on_hand']) - $required;
                $newValueCents = Money::cents($item['inventory_value']) - $movementCostsCents[$itemId];
                $newAverage = $newQuantityMilli > 0 ? Money::averageUnitCost($newValueCents, $newQuantityMilli) : '0.0000';
                $this->database->table('inventory_items')->where('id', $itemId)->update([
                    'quantity_on_hand' => Money::quantity($newQuantityMilli),
                    'average_cost' => $newAverage,
                    'inventory_value' => Money::decimal($newValueCents),
                    'updated_at' => $now,
                ]);
                $this->database->table('stock_movements')->insert([
                    'inventory_item_id' => $itemId,
                    'quantity' => Money::quantity(-$required),
                    'unit_cost' => $item['average_cost'],
                    'total_cost' => Money::decimal(-$movementCostsCents[$itemId]),
                    'movement_type' => 'sale', 'source_type' => 'sale', 'source_id' => $saleId,
                    'notes' => $saleNumber, 'posted_at' => $now, 'created_by' => $userId, 'created_at' => $now,
                ]);
            }
            $journalLines = [];
            foreach ($normalizedPayments as $payment) {
                $journalLines[] = ['account_key' => AccountingService::paymentAccountKey($payment['method']), 'debit' => Money::decimal($payment['amount_cents']), 'credit' => 0, 'memo' => 'Pembayaran ' . $payment['method']];
            }
            $productRevenueCents = $subtotalCents - $discountCents;
            if ($productRevenueCents > 0) { $journalLines[] = ['account_key' => 'sales_revenue', 'debit' => 0, 'credit' => Money::decimal($productRevenueCents)]; }
            if ($serviceCents > 0) { $journalLines[] = ['account_key' => 'service_revenue', 'debit' => 0, 'credit' => Money::decimal($serviceCents)]; }
            if ($taxCents > 0) { $journalLines[] = ['account_key' => 'tax_payable', 'debit' => 0, 'credit' => Money::decimal($taxCents)]; }
            if ($totalCogsCents > 0) {
                $journalLines[] = ['account_key' => 'cogs', 'debit' => Money::decimal($totalCogsCents), 'credit' => 0];
                $journalLines[] = ['account_key' => 'inventory', 'debit' => 0, 'credit' => Money::decimal($totalCogsCents)];
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
