<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class RefundService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function submit(int $saleId, array $payload, int $userId): int
    {
        $method = (string) ($payload['method'] ?? '');
        $reason = trim((string) ($payload['reason'] ?? ''));
        $reference = trim((string) ($payload['reference_number'] ?? ''));
        $quantities = (array) ($payload['quantities'] ?? []);
        $restock = (array) ($payload['restock'] ?? []);
        if (!in_array($method, ['cash', 'qris', 'bank_transfer', 'debit', 'credit'], true)) {
            throw new RuntimeException('Metode pengembalian dana tidak valid.');
        }
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Alasan refund harus berisi 5 sampai 500 karakter.');
        }
        if ($method !== 'cash' && $reference === '') {
            throw new RuntimeException('Nomor referensi wajib untuk pengembalian non-tunai.');
        }

        try {
            $this->database->transException(true)->transStart();
            $sale = $this->database->query('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId])->getRowArray();
            if (!$sale || !in_array($sale['status'], ['paid', 'partially_refunded'], true)) {
                throw new RuntimeException('Transaksi tidak tersedia untuk refund.');
            }
            $saleItems = $this->database->query('SELECT * FROM sale_items WHERE sale_id = ? FOR UPDATE', [$saleId])->getResultArray();
            $selected = [];
            $grossTotal = 0.0;
            foreach ($saleItems as $item) {
                $quantity = (float) ($quantities[$item['id']] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }
                if (abs($quantity - round($quantity)) > 0.0001) {
                    throw new RuntimeException('Jumlah refund harus berupa unit utuh.');
                }
                $reserved = (float) ($this->database->table('refund_items')
                    ->selectSum('refund_items.quantity', 'total')
                    ->join('refunds', 'refunds.id = refund_items.refund_id')
                    ->where('refund_items.sale_item_id', $item['id'])
                    ->whereIn('refunds.status', ['submitted', 'posted'])
                    ->get()->getRow('total') ?? 0);
                if ($quantity - 0.0001 > (float) $item['quantity'] - $reserved) {
                    throw new RuntimeException('Jumlah refund melebihi sisa unit untuk ' . $item['product_name'] . '.');
                }
                $gross = round((float) $item['unit_price'] * $quantity, 2);
                $grossTotal += $gross;
                $wantsRestock = isset($restock[$item['id']]) && (string) $restock[$item['id']] === '1';
                if ($wantsRestock && !$item['direct_inventory_item_id']) {
                    throw new RuntimeException('Restock hanya tersedia untuk barang siap jual yang dipetakan ke persediaan.');
                }
                $selected[] = ['item' => $item, 'quantity' => $quantity, 'gross' => $gross, 'restock' => $wantsRestock];
            }
            if ($selected === []) {
                throw new RuntimeException('Pilih sedikitnya satu item untuk direfund.');
            }

            $saleSubtotal = (float) $sale['subtotal'];
            if ($saleSubtotal <= 0) {
                throw new RuntimeException('Nilai transaksi asal tidak valid.');
            }
            $discount = round((float) $sale['discount'] * ($grossTotal / $saleSubtotal), 2);
            $tax = round((float) $sale['tax'] * ($grossTotal / $saleSubtotal), 2);
            $service = round((float) $sale['service_charge'] * ($grossTotal / $saleSubtotal), 2);
            $amount = round($grossTotal - $discount + $tax + $service, 2);
            $alreadyReserved = (float) ($this->database->table('refunds')->selectSum('amount', 'total')->where('sale_id', $saleId)->whereIn('status', ['submitted', 'posted'])->get()->getRow('total') ?? 0);
            $remainingValue = round((float) $sale['total'] - $alreadyReserved, 2);
            if ($amount > $remainingValue + 0.01) {
                throw new RuntimeException('Nilai refund melebihi sisa nilai transaksi.');
            }
            if ($this->isFullRemainingSelection($saleItems, $selected, $saleId)) {
                $amount = $remainingValue;
                $discount = round($grossTotal + $tax + $service - $amount, 2);
            }

            $now = date('Y-m-d H:i:s');
            $this->database->table('refunds')->insert([
                'refund_number' => 'RFD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'sale_id' => $saleId,
                'status' => 'submitted',
                'method' => $method,
                'subtotal_amount' => $grossTotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'service_charge_amount' => $service,
                'amount' => $amount,
                'reason' => $reason,
                'reference_number' => $reference ?: null,
                'requested_by' => $userId,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $refundId = (int) $this->database->insertID();
            foreach ($selected as $line) {
                $share = $line['gross'] / $grossTotal;
                $lineDiscount = round($discount * $share, 2);
                $lineTax = round($tax * $share, 2);
                $lineService = round($service * $share, 2);
                $this->database->table('refund_items')->insert([
                    'refund_id' => $refundId,
                    'sale_item_id' => $line['item']['id'],
                    'quantity' => $line['quantity'],
                    'gross_amount' => $line['gross'],
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'service_charge_amount' => $lineService,
                    'refund_amount' => round($line['gross'] - $lineDiscount + $lineTax + $lineService, 2),
                    'restock_decision' => $line['restock'] ? 'restock' : ($line['item']['recipe_snapshot'] ? 'consumed' : 'not_returned'),
                    'cogs_reversed' => 0,
                    'created_at' => $now,
                ]);
            }
            $this->database->transComplete();
            return $refundId;
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function approve(int $refundId, int $ownerId): int
    {
        try {
            $this->database->transException(true)->transStart();
            $refund = $this->database->query('SELECT * FROM refunds WHERE id = ? FOR UPDATE', [$refundId])->getRowArray();
            if (!$refund || $refund['status'] !== 'submitted') {
                throw new RuntimeException('Pengajuan refund sudah diproses atau tidak ditemukan.');
            }
            $sale = $this->database->query('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$refund['sale_id']])->getRowArray();
            if (!$sale || !in_array($sale['status'], ['paid', 'partially_refunded'], true)) {
                throw new RuntimeException('Transaksi asal tidak dapat direfund.');
            }

            $shiftId = null;
            if ($refund['method'] === 'cash') {
                $shift = $this->database->query('SELECT id FROM shifts WHERE cashier_id = ? AND status = ? FOR UPDATE', [$refund['requested_by'], 'open'])->getRowArray();
                if (!$shift) {
                    throw new RuntimeException('Kasir pemohon harus memiliki shift aktif untuk refund tunai.');
                }
                $shiftId = (int) $shift['id'];
            }
            $postedTotal = (float) ($this->database->table('refunds')->selectSum('amount', 'total')->where('sale_id', $sale['id'])->where('status', 'posted')->get()->getRow('total') ?? 0);
            if ($postedTotal + (float) $refund['amount'] > (float) $sale['total'] + 0.01) {
                throw new RuntimeException('Nilai refund kumulatif melebihi nilai transaksi asal.');
            }

            $now = date('Y-m-d H:i:s');
            $restockRequirements = [];
            $refundItems = $this->database->table('refund_items')
                ->select('refund_items.*, sale_items.direct_inventory_item_id, sale_items.unit_cogs, sale_items.product_name')
                ->join('sale_items', 'sale_items.id = refund_items.sale_item_id')
                ->where('refund_items.refund_id', $refundId)->get()->getResultArray();
            foreach ($refundItems as $refundItem) {
                if ($refundItem['restock_decision'] !== 'restock') {
                    continue;
                }
                $inventoryItemId = (int) ($refundItem['direct_inventory_item_id'] ?? 0);
                if (!$inventoryItemId) {
                    throw new RuntimeException('Item refund tidak memiliki pemetaan persediaan untuk restock.');
                }
                $quantity = (float) $refundItem['quantity'];
                $cogs = round((float) $refundItem['unit_cogs'] * $quantity, 2);
                if (!isset($restockRequirements[$inventoryItemId])) {
                    $restockRequirements[$inventoryItemId] = ['quantity' => 0.0, 'cogs' => 0.0, 'names' => []];
                }
                $restockRequirements[$inventoryItemId]['quantity'] += $quantity;
                $restockRequirements[$inventoryItemId]['cogs'] += $cogs;
                $restockRequirements[$inventoryItemId]['names'][] = $refundItem['product_name'];
                $this->database->table('refund_items')->where('id', $refundItem['id'])->update(['cogs_reversed' => $cogs]);
            }
            $restockItemIds = array_keys($restockRequirements);
            sort($restockItemIds);
            $lockedRestockItems = [];
            foreach ($restockItemIds as $inventoryItemId) {
                $lockedRestockItems[$inventoryItemId] = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$inventoryItemId])->getRowArray();
            }
            (new InventoryLockService($this->database))->assertUnlocked($restockItemIds);
            foreach ($restockRequirements as $inventoryItemId => $restockLine) {
                $inventoryItem = $lockedRestockItems[$inventoryItemId];
                if (!$inventoryItem) {
                    throw new RuntimeException('Item persediaan untuk restock tidak ditemukan.');
                }
                $unitCost = $restockLine['quantity'] > 0 ? $restockLine['cogs'] / $restockLine['quantity'] : 0;
                $newQuantity = (float) $inventoryItem['quantity_on_hand'] + $restockLine['quantity'];
                $newAverageCost = $newQuantity > 0
                    ? (((float) $inventoryItem['quantity_on_hand'] * (float) $inventoryItem['average_cost']) + $restockLine['cogs']) / $newQuantity
                    : 0;
                $this->database->table('inventory_items')->where('id', $inventoryItemId)->update([
                    'quantity_on_hand' => $newQuantity,
                    'average_cost' => $newAverageCost,
                    'updated_at' => $now,
                ]);
                $this->database->table('stock_movements')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'quantity' => $restockLine['quantity'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $restockLine['cogs'],
                    'movement_type' => 'return',
                    'source_type' => 'refund',
                    'source_id' => $refundId,
                    'notes' => $refund['refund_number'] . ' · ' . implode(', ', array_unique($restockLine['names'])),
                    'posted_at' => $now,
                    'created_by' => $ownerId,
                    'created_at' => $now,
                ]);
            }
            $refundJournalLines = [];
            $netProductRefund = round((float) $refund['subtotal_amount'] - (float) $refund['discount_amount'], 2);
            if ($netProductRefund > 0) { $refundJournalLines[] = ['account_key' => 'sales_returns', 'debit' => $netProductRefund, 'credit' => 0]; }
            if ((float) $refund['service_charge_amount'] > 0) { $refundJournalLines[] = ['account_key' => 'service_revenue', 'debit' => (float) $refund['service_charge_amount'], 'credit' => 0]; }
            if ((float) $refund['tax_amount'] > 0) { $refundJournalLines[] = ['account_key' => 'tax_payable', 'debit' => (float) $refund['tax_amount'], 'credit' => 0]; }
            $refundJournalLines[] = ['account_key' => AccountingService::paymentAccountKey($refund['method']), 'debit' => 0, 'credit' => (float) $refund['amount']];
            $restoredCogs = array_sum(array_map(static fn (array $line): float => (float) $line['cogs'], $restockRequirements));
            if ($restoredCogs > 0) {
                $refundJournalLines[] = ['account_key' => 'inventory', 'debit' => $restoredCogs, 'credit' => 0];
                $refundJournalLines[] = ['account_key' => 'cogs', 'debit' => 0, 'credit' => $restoredCogs];
            }
            (new AccountingService($this->database))->post('refund', $refundId, date('Y-m-d', strtotime($now)), 'Refund ' . $refund['refund_number'] . ' dari ' . $sale['sale_number'], $refundJournalLines, $ownerId);
            $this->database->table('refunds')->where('id', $refundId)->update([
                'status' => 'posted', 'shift_id' => $shiftId, 'approved_by' => $ownerId,
                'posted_at' => $now, 'updated_at' => $now,
            ]);
            $newRefundedTotal = $postedTotal + (float) $refund['amount'];
            $status = $newRefundedTotal >= (float) $sale['total'] - 0.01 ? 'refunded' : 'partially_refunded';
            $this->database->table('sales')->where('id', $sale['id'])->update(['status' => $status, 'updated_at' => $now]);
            if ($status === 'refunded') {
                $this->database->table('review_tokens')->where('sale_id', $sale['id'])->where('disabled_at', null)->update(['disabled_at' => $now, 'disabled_reason' => 'Transaksi direfund penuh']);
                $reviewIds = array_column($this->database->table('product_reviews')->select('product_reviews.id')->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')->where('review_tokens.sale_id', $sale['id'])->get()->getResultArray(), 'id');
                if ($reviewIds !== []) { $this->database->table('product_reviews')->whereIn('id', $reviewIds)->update(['sale_fully_refunded' => true, 'updated_at' => $now]); }
            }
            $this->database->transComplete();
            return (int) $sale['id'];
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function reject(int $refundId, string $reason, int $ownerId): int
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Alasan penolakan harus berisi 5 sampai 500 karakter.');
        }
        try {
            $this->database->transException(true)->transStart();
            $refund = $this->database->query('SELECT * FROM refunds WHERE id = ? FOR UPDATE', [$refundId])->getRowArray();
            if (!$refund || $refund['status'] !== 'submitted') {
                throw new RuntimeException('Pengajuan refund sudah diproses atau tidak ditemukan.');
            }
            $now = date('Y-m-d H:i:s');
            $this->database->table('refunds')->where('id', $refundId)->update([
                'status' => 'rejected', 'rejected_by' => $ownerId, 'rejection_reason' => $reason,
                'rejected_at' => $now, 'updated_at' => $now,
            ]);
            $this->database->transComplete();
            return (int) $refund['sale_id'];
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    private function isFullRemainingSelection(array $saleItems, array $selected, int $saleId): bool
    {
        $selectedByItem = [];
        foreach ($selected as $line) {
            $selectedByItem[$line['item']['id']] = $line['quantity'];
        }
        foreach ($saleItems as $item) {
            $reserved = (float) ($this->database->table('refund_items')->selectSum('refund_items.quantity', 'total')
                ->join('refunds', 'refunds.id = refund_items.refund_id')
                ->where('refund_items.sale_item_id', $item['id'])
                ->whereIn('refunds.status', ['submitted', 'posted'])
                ->get()->getRow('total') ?? 0);
            $remaining = (float) $item['quantity'] - $reserved;
            if (abs(($selectedByItem[$item['id']] ?? 0) - $remaining) > 0.0001) {
                return false;
            }
        }
        return true;
    }
}
