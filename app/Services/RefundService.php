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
            $grossTotalCents = 0;
            foreach ($saleItems as $item) {
                $quantityMilli = Money::quantityMilli($quantities[$item['id']] ?? 0);
                if ($quantityMilli <= 0) {
                    continue;
                }
                if ($quantityMilli % 1000 !== 0) {
                    throw new RuntimeException('Jumlah refund harus berupa unit utuh.');
                }
                $reservedMilli = Money::quantityMilli($this->database->table('refund_items')
                    ->selectSum('refund_items.quantity', 'total')
                    ->join('refunds', 'refunds.id = refund_items.refund_id')
                    ->where('refund_items.sale_item_id', $item['id'])
                    ->whereIn('refunds.status', ['submitted', 'posted'])
                    ->get()->getRow('total') ?? 0);
                if ($quantityMilli > Money::quantityMilli($item['quantity']) - $reservedMilli) {
                    throw new RuntimeException('Jumlah refund melebihi sisa unit untuk ' . $item['product_name'] . '.');
                }
                $quantity = intdiv($quantityMilli, 1000);
                $grossCents = Money::cents($item['unit_price']) * $quantity;
                $grossTotalCents += $grossCents;
                $wantsRestock = isset($restock[$item['id']]) && (string) $restock[$item['id']] === '1';
                if ($wantsRestock && !$item['direct_inventory_item_id']) {
                    throw new RuntimeException('Restock hanya tersedia untuk barang siap jual yang dipetakan ke persediaan.');
                }
                $selected[] = ['item' => $item, 'quantity' => $quantity, 'gross_cents' => $grossCents, 'restock' => $wantsRestock];
            }
            if ($selected === [] || $grossTotalCents <= 0) {
                throw new RuntimeException('Pilih sedikitnya satu item untuk direfund.');
            }

            $saleSubtotalCents = Money::cents($sale['subtotal']);
            if ($saleSubtotalCents <= 0 || $grossTotalCents > $saleSubtotalCents) {
                throw new RuntimeException('Nilai transaksi asal tidak valid.');
            }
            $discountCents = Money::proportionalCents(Money::cents($sale['discount']), $grossTotalCents, $saleSubtotalCents);
            $taxCents = Money::proportionalCents(Money::cents($sale['tax']), $grossTotalCents, $saleSubtotalCents);
            $serviceCents = Money::proportionalCents(Money::cents($sale['service_charge']), $grossTotalCents, $saleSubtotalCents);
            $amountCents = $grossTotalCents - $discountCents + $taxCents + $serviceCents;
            $alreadyReservedCents = Money::cents($this->database->table('refunds')->selectSum('amount', 'total')->where('sale_id', $saleId)->whereIn('status', ['submitted', 'posted'])->get()->getRow('total') ?? 0);
            $remainingValueCents = Money::cents($sale['total']) - $alreadyReservedCents;
            if ($remainingValueCents < 0) {
                throw new RuntimeException('Nilai refund sebelumnya melebihi nilai transaksi asal.');
            }
            if ($this->isFullRemainingSelection($saleItems, $selected)) {
                $amountCents = $remainingValueCents;
                $discountCents = $grossTotalCents + $taxCents + $serviceCents - $amountCents;
                if ($discountCents < 0 || $discountCents > $grossTotalCents) {
                    throw new RuntimeException('Sisa nilai refund tidak sesuai dengan item yang belum direfund.');
                }
            } elseif ($amountCents > $remainingValueCents) {
                throw new RuntimeException('Nilai refund melebihi sisa nilai transaksi.');
            }

            $now = date('Y-m-d H:i:s');
            $this->database->table('refunds')->insert([
                'refund_number' => 'RFD-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'sale_id' => $saleId,
                'status' => 'submitted',
                'method' => $method,
                'subtotal_amount' => Money::decimal($grossTotalCents),
                'discount_amount' => Money::decimal($discountCents),
                'tax_amount' => Money::decimal($taxCents),
                'service_charge_amount' => Money::decimal($serviceCents),
                'amount' => Money::decimal($amountCents),
                'reason' => $reason,
                'reference_number' => $reference ?: null,
                'requested_by' => $userId,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $refundId = (int) $this->database->insertID();
            $weights = array_column($selected, 'gross_cents');
            $lineDiscounts = Money::allocateCents($discountCents, $weights);
            $lineTaxes = Money::allocateCents($taxCents, $weights);
            $lineServices = Money::allocateCents($serviceCents, $weights);
            foreach ($selected as $index => $line) {
                $lineDiscountCents = $lineDiscounts[$index];
                $lineTaxCents = $lineTaxes[$index];
                $lineServiceCents = $lineServices[$index];
                $lineRefundCents = $line['gross_cents'] - $lineDiscountCents + $lineTaxCents + $lineServiceCents;
                $this->database->table('refund_items')->insert([
                    'refund_id' => $refundId,
                    'sale_item_id' => $line['item']['id'],
                    'quantity' => $line['quantity'],
                    'gross_amount' => Money::decimal($line['gross_cents']),
                    'discount_amount' => Money::decimal($lineDiscountCents),
                    'tax_amount' => Money::decimal($lineTaxCents),
                    'service_charge_amount' => Money::decimal($lineServiceCents),
                    'refund_amount' => Money::decimal($lineRefundCents),
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
            $postedTotalCents = Money::cents($this->database->table('refunds')->selectSum('amount', 'total')->where('sale_id', $sale['id'])->where('status', 'posted')->get()->getRow('total') ?? 0);
            $refundAmountCents = Money::cents($refund['amount']);
            if ($postedTotalCents + $refundAmountCents > Money::cents($sale['total'])) {
                throw new RuntimeException('Nilai refund kumulatif melebihi nilai transaksi asal.');
            }

            $now = date('Y-m-d H:i:s');
            $restockRequirements = [];
            $refundItems = $this->database->table('refund_items')
                ->select('refund_items.*, sale_items.direct_inventory_item_id, sale_items.direct_line_cogs, sale_items.quantity AS sold_quantity, sale_items.product_name')
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
                $quantityMilli = Money::quantityMilli($refundItem['quantity']);
                $soldQuantityMilli = Money::quantityMilli($refundItem['sold_quantity']);
                $alreadyRestocked = $this->database->query(
                    'SELECT COALESCE(SUM(ri.quantity), 0) AS quantity, COALESCE(SUM(ri.cogs_reversed), 0) AS cogs FROM refund_items ri JOIN refunds r ON r.id = ri.refund_id WHERE ri.sale_item_id = ? AND ri.restock_decision = ? AND r.status = ?',
                    [$refundItem['sale_item_id'], 'restock', 'posted'],
                )->getRowArray();
                $alreadyRestockedMilli = Money::quantityMilli($alreadyRestocked['quantity']);
                $lineCogsCents = Money::cents($refundItem['direct_line_cogs']);
                $alreadyRestockedCents = Money::cents($alreadyRestocked['cogs']);
                if ($alreadyRestockedMilli > $soldQuantityMilli || $alreadyRestockedCents < 0 || $alreadyRestockedCents > $lineCogsCents) {
                    throw new RuntimeException('Riwayat restock melebihi biaya atau jumlah barang yang terjual.');
                }
                $restockedAfterMilli = $alreadyRestockedMilli + $quantityMilli;
                $cogsTargetAfterCents = $this->restockCogsTarget($lineCogsCents, $soldQuantityMilli, $restockedAfterMilli);
                $cogsCents = $restockedAfterMilli === $soldQuantityMilli
                    ? $lineCogsCents - $alreadyRestockedCents
                    : max(0, $cogsTargetAfterCents - $alreadyRestockedCents);
                if (!isset($restockRequirements[$inventoryItemId])) {
                    $restockRequirements[$inventoryItemId] = ['quantity_milli' => 0, 'cogs_cents' => 0, 'names' => []];
                }
                $restockRequirements[$inventoryItemId]['quantity_milli'] += $quantityMilli;
                $restockRequirements[$inventoryItemId]['cogs_cents'] += $cogsCents;
                $restockRequirements[$inventoryItemId]['names'][] = $refundItem['product_name'];
                $this->database->table('refund_items')->where('id', $refundItem['id'])->update(['cogs_reversed' => Money::decimal($cogsCents)]);
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
                $oldQuantityMilli = Money::quantityMilli($inventoryItem['quantity_on_hand']);
                $newQuantityMilli = $oldQuantityMilli + $restockLine['quantity_milli'];
                $newValueCents = Money::cents($inventoryItem['inventory_value']) + $restockLine['cogs_cents'];
                $newAverageCost = Money::averageUnitCost($newValueCents, $newQuantityMilli);
                $unitCost = Money::averageUnitCost($restockLine['cogs_cents'], $restockLine['quantity_milli']);
                $this->database->table('inventory_items')->where('id', $inventoryItemId)->update([
                    'quantity_on_hand' => Money::quantity($newQuantityMilli),
                    'average_cost' => $newAverageCost,
                    'inventory_value' => Money::decimal($newValueCents),
                    'updated_at' => $now,
                ]);
                $this->database->table('stock_movements')->insert([
                    'inventory_item_id' => $inventoryItemId,
                    'quantity' => Money::quantity($restockLine['quantity_milli']),
                    'unit_cost' => $unitCost,
                    'total_cost' => Money::decimal($restockLine['cogs_cents']),
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
            $netProductRefundCents = Money::cents($refund['subtotal_amount']) - Money::cents($refund['discount_amount']);
            $taxRefundCents = Money::cents($refund['tax_amount']);
            $serviceRefundCents = Money::cents($refund['service_charge_amount']);
            if ($netProductRefundCents > 0) { $refundJournalLines[] = ['account_key' => 'sales_returns', 'debit' => Money::decimal($netProductRefundCents), 'credit' => 0]; }
            if ($serviceRefundCents > 0) { $refundJournalLines[] = ['account_key' => 'service_revenue', 'debit' => Money::decimal($serviceRefundCents), 'credit' => 0]; }
            if ($taxRefundCents > 0) { $refundJournalLines[] = ['account_key' => 'tax_payable', 'debit' => Money::decimal($taxRefundCents), 'credit' => 0]; }
            $refundJournalLines[] = ['account_key' => AccountingService::paymentAccountKey($refund['method']), 'debit' => 0, 'credit' => Money::decimal($refundAmountCents)];
            $restoredCogsCents = array_sum(array_map(static fn (array $line): int => $line['cogs_cents'], $restockRequirements));
            if ($restoredCogsCents > 0) {
                $refundJournalLines[] = ['account_key' => 'inventory', 'debit' => Money::decimal($restoredCogsCents), 'credit' => 0];
                $refundJournalLines[] = ['account_key' => 'cogs', 'debit' => 0, 'credit' => Money::decimal($restoredCogsCents)];
            }
            (new AccountingService($this->database))->post('refund', $refundId, date('Y-m-d', strtotime($now)), 'Refund ' . $refund['refund_number'] . ' dari ' . $sale['sale_number'], $refundJournalLines, $ownerId);
            $this->database->table('refunds')->where('id', $refundId)->update([
                'status' => 'posted', 'shift_id' => $shiftId, 'approved_by' => $ownerId,
                'posted_at' => $now, 'updated_at' => $now,
            ]);
            $newRefundedTotalCents = $postedTotalCents + $refundAmountCents;
            $status = $newRefundedTotalCents >= Money::cents($sale['total']) ? 'refunded' : 'partially_refunded';
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

    private function isFullRemainingSelection(array $saleItems, array $selected): bool
    {
        $selectedByItem = [];
        foreach ($selected as $line) {
            $selectedByItem[$line['item']['id']] = Money::quantityMilli($line['quantity']);
        }
        foreach ($saleItems as $item) {
            $reservedMilli = Money::quantityMilli($this->database->table('refund_items')->selectSum('refund_items.quantity', 'total')
                ->join('refunds', 'refunds.id = refund_items.refund_id')
                ->where('refund_items.sale_item_id', $item['id'])
                ->whereIn('refunds.status', ['submitted', 'posted'])
                ->get()->getRow('total') ?? 0);
            $remainingMilli = Money::quantityMilli($item['quantity']) - $reservedMilli;
            if (($selectedByItem[$item['id']] ?? 0) !== $remainingMilli) {
                return false;
            }
        }
        return true;
    }

    private function restockCogsTarget(int $lineCogsCents, int $soldQuantityMilli, int $restockedQuantityMilli): int
    {
        if ($lineCogsCents < 0 || $soldQuantityMilli <= 0 || $soldQuantityMilli % 1000 !== 0
            || $restockedQuantityMilli < 0 || $restockedQuantityMilli > $soldQuantityMilli || $restockedQuantityMilli % 1000 !== 0) {
            throw new RuntimeException('Jumlah atau nilai restock tidak valid.');
        }

        $soldUnits = intdiv($soldQuantityMilli, 1000);
        $restockedUnits = intdiv($restockedQuantityMilli, 1000);
        $centsPerUnit = intdiv($lineCogsCents, $soldUnits);
        $residualCents = $lineCogsCents % $soldUnits;

        return ($centsPerUnit * $restockedUnits) + min($restockedUnits, $residualCents);
    }
}
