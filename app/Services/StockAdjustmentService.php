<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class StockAdjustmentService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function create(array $payload, int $userId): int
    {
        $type = (string) ($payload['adjustment_type'] ?? '');
        $date = (string) ($payload['document_date'] ?? '');
        $reason = trim((string) ($payload['reason'] ?? ''));
        $dateValue = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!in_array($type, ['opening', 'damage', 'correction'], true)
            || !$dateValue || $dateValue->format('Y-m-d') !== $date
            || mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Jenis, tanggal, dan alasan penyesuaian harus diisi dengan benar.');
        }

        $offsetAccountId = null;
        if ($type === 'opening') {
            $offsetAccountId = filter_var($payload['offset_account_id'] ?? null, FILTER_VALIDATE_INT);
            $offset = $offsetAccountId
                ? $this->database->table('accounts')->where('id', $offsetAccountId)->where('is_active', true)->whereIn('account_type', ['liability', 'equity'])->get()->getRowArray()
                : null;
            if (!$offset) {
                throw new RuntimeException('Pilih akun lawan berupa kewajiban atau ekuitas untuk saldo awal.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->database->transException(true)->transStart();
        try {
            $this->database->table('stock_adjustments')->insert([
                'reference_number' => $this->referenceNumber('ADJ'),
                'adjustment_type' => $type,
                'status' => 'draft',
                'document_date' => $date,
                'offset_account_id' => $offsetAccountId,
                'reason' => $reason,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adjustmentId = (int) $this->database->insertID();
            (new AuditLogService($this->database))->record($userId, 'inventory.adjustment.created', 'stock_adjustment', $adjustmentId, $reason, [], [
                'reference_number' => $this->referenceNumberForAudit($adjustmentId),
                'adjustment_type' => $type,
                'status' => 'draft',
                'document_date' => $date,
                'offset_account_id' => $offsetAccountId,
            ]);
            $this->database->transComplete();
            return $adjustmentId;
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function saveLine(int $adjustmentId, array $payload, int $userId, string $role): void
    {
        $itemId = filter_var($payload['item_id'] ?? null, FILTER_VALIDATE_INT);
        $quantityInput = $payload['quantity_delta'] ?? null;
        $costInput = $payload['unit_cost'] ?? '0';
        if (!$itemId || !is_string($quantityInput) || !is_string($costInput)) {
            throw new RuntimeException('Bahan, jumlah, atau biaya tidak valid.');
        }

        try {
            $quantityMilli = Money::quantityMilli($quantityInput);
            $unitCostScaled = Money::unitCostTenThousand($costInput);
        } catch (\InvalidArgumentException) {
            throw new RuntimeException('Jumlah dan biaya harus berupa angka yang valid.');
        }
        if ($quantityMilli === 0 || $unitCostScaled < 0) {
            throw new RuntimeException('Jumlah harus bukan nol dan biaya tidak boleh negatif.');
        }

        try {
            $this->database->transException(true)->transStart();
            $adjustment = $this->editableAdjustment($adjustmentId, $userId, $role);
            $item = $this->database->table('inventory_items')->where('id', $itemId)->where('is_active', true)->get()->getRowArray();
            if (!$item) {
                throw new RuntimeException('Bahan aktif tidak ditemukan.');
            }
            if ($adjustment['adjustment_type'] === 'opening' && $quantityMilli < 0) {
                throw new RuntimeException('Saldo awal hanya dapat menambah jumlah stok.');
            }
            if ($adjustment['adjustment_type'] === 'damage') {
                if ($quantityMilli < 0) {
                    throw new RuntimeException('Masukkan jumlah kerusakan sebagai angka positif.');
                }
                $quantityMilli = -$quantityMilli;
                $unitCostScaled = 0;
            } elseif ($quantityMilli < 0) {
                $unitCostScaled = 0;
            }

            $now = date('Y-m-d H:i:s');
            $line = [
                'stock_adjustment_id' => $adjustmentId,
                'inventory_item_id' => $itemId,
                'base_unit' => $item['base_unit'],
                'quantity_delta' => Money::quantity($quantityMilli),
                'unit_cost' => Money::unitCost($unitCostScaled),
                'value_delta' => '0.00',
                'expected_quantity' => null,
                'expected_inventory_value' => null,
                'updated_at' => $now,
            ];
            $existing = $this->database->table('stock_adjustment_items')
                ->where('stock_adjustment_id', $adjustmentId)->where('inventory_item_id', $itemId)->get()->getRowArray();
            if ($existing) {
                $this->database->table('stock_adjustment_items')->where('id', $existing['id'])->update($line);
            } else {
                $line['created_at'] = $now;
                $this->database->table('stock_adjustment_items')->insert($line);
            }
            (new AuditLogService($this->database))->record($userId, 'inventory.adjustment.line_saved', 'stock_adjustment', $adjustmentId, $adjustment['reason'], $existing ? [
                'item_id' => $itemId,
                'quantity_delta' => $existing['quantity_delta'],
                'unit_cost' => $existing['unit_cost'],
            ] : [], [
                'item_id' => $itemId,
                'quantity_delta' => Money::quantity($quantityMilli),
                'unit_cost' => Money::unitCost($unitCostScaled),
            ]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function submit(int $adjustmentId, int $userId, string $role): void
    {
        try {
            $this->database->transException(true)->transStart();
            $adjustment = $this->editableAdjustment($adjustmentId, $userId, $role);
            $lines = $this->database->table('stock_adjustment_items')->where('stock_adjustment_id', $adjustmentId)->orderBy('inventory_item_id')->get()->getResultArray();
            if ($lines === []) {
                throw new RuntimeException('Tambahkan minimal satu bahan sebelum mengajukan penyesuaian.');
            }

            $itemIds = array_column($lines, 'inventory_item_id');
            (new InventoryLockService($this->database))->assertUnlocked($itemIds);
            $now = date('Y-m-d H:i:s');
            foreach ($lines as $line) {
                $item = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$line['inventory_item_id']])->getRowArray();
                if (!$item || !(bool) $item['is_active'] || $item['base_unit'] !== $line['base_unit']) {
                    throw new RuntimeException('Salah satu bahan sudah tidak aktif.');
                }

                $deltaMilli = Money::quantityMilli($line['quantity_delta']);
                $onHandMilli = Money::quantityMilli($item['quantity_on_hand']);
                $inventoryValueCents = Money::cents($item['inventory_value']);
                if ($adjustment['adjustment_type'] === 'opening') {
                    if ($deltaMilli <= 0 || $onHandMilli !== 0 || $inventoryValueCents !== 0
                        || Money::unitCostTenThousand($line['unit_cost']) <= 0
                        || $this->database->table('stock_movements')->where('inventory_item_id', $item['id'])->where('movement_type', 'opening')->countAllResults() > 0) {
                        throw new RuntimeException('Saldo awal hanya boleh dicatat sekali untuk bahan yang belum memiliki stok dan harus mencantumkan biaya.');
                    }
                } elseif ($deltaMilli < 0 && abs($deltaMilli) > $onHandMilli) {
                    throw new RuntimeException('Jumlah pengurangan melebihi stok yang tersedia untuk ' . $item['name'] . '.');
                }

                $unitCostScaled = $deltaMilli > 0
                    ? Money::unitCostTenThousand($line['unit_cost'])
                    : Money::unitCostTenThousand($item['average_cost']);
                if ($deltaMilli > 0 && $unitCostScaled <= 0) {
                    throw new RuntimeException('Jumlah tambahan harus memiliki biaya per satuan di atas nol.');
                }
                if ($deltaMilli < 0) {
                    $absoluteValueCents = Money::proportionalCents($inventoryValueCents, abs($deltaMilli), $onHandMilli);
                } else {
                    $absoluteValueCents = Money::centsForQuantity(abs($deltaMilli), $unitCostScaled);
                }
                $signedValueCents = $deltaMilli < 0 ? -$absoluteValueCents : $absoluteValueCents;
                $this->database->table('stock_adjustment_items')->where('id', $line['id'])->update([
                    'unit_cost' => Money::unitCost($unitCostScaled),
                    'value_delta' => Money::decimal($signedValueCents),
                    'expected_quantity' => Money::quantity($onHandMilli),
                    'expected_inventory_value' => Money::decimal($inventoryValueCents),
                    'updated_at' => $now,
                ]);
            }

            $this->database->table('stock_adjustments')->where('id', $adjustmentId)->update([
                'status' => 'submitted', 'submitted_by' => $userId, 'submitted_at' => $now,
                'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null, 'updated_at' => $now,
            ]);
            (new AuditLogService($this->database))->record($userId, 'inventory.adjustment.submitted', 'stock_adjustment', $adjustmentId, $adjustment['reason'], [
                'status' => $adjustment['status'],
            ], ['status' => 'submitted', 'line_count' => count($lines)]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function review(int $adjustmentId, bool $approve, string $reviewNotes, int $ownerId, string $role): void
    {
        if ($role !== 'owner') {
            throw new RuntimeException('Hanya owner yang dapat meninjau penyesuaian stok.');
        }
        $reviewNotes = trim($reviewNotes);
        if (!$approve && (mb_strlen($reviewNotes) < 5 || mb_strlen($reviewNotes) > 500)) {
            throw new RuntimeException('Catatan perbaikan harus berisi 5 sampai 500 karakter.');
        }

        try {
            $this->database->transException(true)->transStart();
            $adjustment = $this->database->query('SELECT * FROM stock_adjustments WHERE id = ? FOR UPDATE', [$adjustmentId])->getRowArray();
            if (!$adjustment || $adjustment['status'] !== 'submitted') {
                throw new RuntimeException('Penyesuaian tidak sedang menunggu keputusan owner.');
            }
            $now = date('Y-m-d H:i:s');
            $this->database->table('stock_adjustments')->where('id', $adjustmentId)->update([
                'status' => $approve ? 'approved' : 'rejected',
                'reviewed_by' => $ownerId,
                'reviewed_at' => $now,
                'review_notes' => $approve ? null : $reviewNotes,
                'updated_at' => $now,
            ]);
            (new AuditLogService($this->database))->record($ownerId, $approve ? 'inventory.adjustment.approved' : 'inventory.adjustment.rejected', 'stock_adjustment', $adjustmentId, $approve ? $adjustment['reason'] : $reviewNotes, [
                'status' => $adjustment['status'],
            ], ['status' => $approve ? 'approved' : 'rejected', 'review_notes' => $approve ? null : $reviewNotes]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function post(int $adjustmentId, int $userId, string $role): void
    {
        try {
            $this->database->transException(true)->transStart();
            $this->postLocked($adjustmentId, $userId, $role);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function reverse(int $adjustmentId, string $reason, int $ownerId, string $role): int
    {
        $reason = trim($reason);
        if ($role !== 'owner') {
            throw new RuntimeException('Hanya owner yang dapat membalik penyesuaian ter-posting.');
        }
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Alasan pembalik harus berisi 5 sampai 500 karakter.');
        }

        try {
            $this->database->transException(true)->transStart();
            $original = $this->database->query('SELECT * FROM stock_adjustments WHERE id = ? FOR UPDATE', [$adjustmentId])->getRowArray();
            if (!$original || $original['status'] !== 'posted' || $original['adjustment_type'] === 'reversal') {
                throw new RuntimeException('Hanya penyesuaian yang sudah diposting dapat dibalik.');
            }
            if ($this->database->table('stock_adjustments')->where('reversal_of_id', $adjustmentId)->countAllResults() > 0) {
                throw new RuntimeException('Penyesuaian ini sudah memiliki dokumen pembalik.');
            }

            $originalLines = $this->database->table('stock_adjustment_items')->where('stock_adjustment_id', $adjustmentId)->orderBy('inventory_item_id')->get()->getResultArray();
            if ($originalLines === []) {
                throw new RuntimeException('Dokumen asal tidak memiliki detail stok.');
            }
            $itemIds = array_column($originalLines, 'inventory_item_id');
            (new InventoryLockService($this->database))->assertUnlocked($itemIds);
            $now = date('Y-m-d H:i:s');
            $this->database->table('stock_adjustments')->insert([
                'reference_number' => $this->referenceNumber('REV'),
                'adjustment_type' => 'reversal',
                'status' => 'approved',
                'document_date' => date('Y-m-d'),
                'reversal_of_id' => $adjustmentId,
                'reason' => 'Pembalik ' . $original['reference_number'] . ': ' . $reason,
                'created_by' => $ownerId,
                'submitted_by' => $ownerId,
                'reviewed_by' => $ownerId,
                'submitted_at' => $now,
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $reversalId = (int) $this->database->insertID();

            foreach ($originalLines as $line) {
                $item = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$line['inventory_item_id']])->getRowArray();
                if (!$item) {
                    throw new RuntimeException('Bahan pada dokumen asal tidak ditemukan.');
                }
                $this->database->table('stock_adjustment_items')->insert([
                    'stock_adjustment_id' => $reversalId,
                    'inventory_item_id' => $item['id'],
                    'base_unit' => $line['base_unit'],
                    'quantity_delta' => Money::quantity(-Money::quantityMilli($line['quantity_delta'])),
                    'unit_cost' => $line['unit_cost'],
                    'value_delta' => Money::decimal(-Money::cents($line['value_delta'])),
                    'expected_quantity' => $item['quantity_on_hand'],
                    'expected_inventory_value' => $item['inventory_value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->postLocked($reversalId, $ownerId, $role);
            $this->database->transComplete();
            return $reversalId;
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    private function postLocked(int $adjustmentId, int $userId, string $role): void
    {
        $adjustment = $this->database->query('SELECT * FROM stock_adjustments WHERE id = ? FOR UPDATE', [$adjustmentId])->getRowArray();
        if (!$adjustment || $adjustment['status'] !== 'approved'
            || ($role !== 'owner' && (int) $adjustment['created_by'] !== $userId)) {
            throw new RuntimeException('Penyesuaian belum disetujui owner atau tidak dapat diposting oleh akun ini.');
        }
        $lines = $this->database->table('stock_adjustment_items')->where('stock_adjustment_id', $adjustmentId)->orderBy('inventory_item_id')->get()->getResultArray();
        if ($lines === []) {
            throw new RuntimeException('Dokumen tidak memiliki detail stok.');
        }
        (new InventoryLockService($this->database))->assertUnlocked(array_column($lines, 'inventory_item_id'));

        $now = date('Y-m-d H:i:s');
        $positiveValueCents = 0;
        $negativeValueCents = 0;
        foreach ($lines as $line) {
            $item = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$line['inventory_item_id']])->getRowArray();
            if (!$item
                || $item['base_unit'] !== $line['base_unit']
                || $line['expected_quantity'] === null
                || $line['expected_inventory_value'] === null
                || Money::quantityMilli($item['quantity_on_hand']) !== Money::quantityMilli($line['expected_quantity'])
                || Money::cents($item['inventory_value']) !== Money::cents($line['expected_inventory_value'])) {
                throw new RuntimeException('Saldo stok berubah setelah pengajuan. Dokumen perlu diperiksa dan diajukan ulang.');
            }

            $deltaMilli = Money::quantityMilli($line['quantity_delta']);
            $valueDeltaCents = Money::cents($line['value_delta']);
            $oldQuantityMilli = Money::quantityMilli($item['quantity_on_hand']);
            $oldValueCents = Money::cents($item['inventory_value']);
            $newQuantityMilli = $oldQuantityMilli + $deltaMilli;
            $newValueCents = $oldValueCents + $valueDeltaCents;
            if ($newQuantityMilli < 0 || $newValueCents < 0 || ($newQuantityMilli === 0 && $newValueCents !== 0)) {
                throw new RuntimeException('Penyesuaian akan menghasilkan jumlah atau nilai stok yang tidak valid.');
            }

            $this->database->table('inventory_items')->where('id', $item['id'])->update([
                'quantity_on_hand' => Money::quantity($newQuantityMilli),
                'average_cost' => $newQuantityMilli > 0 ? Money::averageUnitCost($newValueCents, $newQuantityMilli) : '0.0000',
                'inventory_value' => Money::decimal($newValueCents),
                'updated_at' => $now,
            ]);
            $movementType = match ($adjustment['adjustment_type']) {
                'opening' => 'opening',
                'damage' => 'damage',
                default => 'correction',
            };
            $sourceType = $adjustment['adjustment_type'] === 'reversal' ? 'stock_adjustment_reversal' : 'stock_adjustment';
            $this->database->table('stock_movements')->insert([
                'inventory_item_id' => $item['id'],
                'quantity' => Money::quantity($deltaMilli),
                'unit_cost' => $line['unit_cost'],
                'total_cost' => Money::decimal($valueDeltaCents),
                'movement_type' => $movementType,
                'source_type' => $sourceType,
                'source_id' => $adjustmentId,
                'notes' => mb_substr($adjustment['reference_number'] . ': ' . $adjustment['reason'], 0, 500),
                'posted_at' => $now,
                'created_by' => $userId,
                'created_at' => $now,
            ]);
            if ($valueDeltaCents > 0) {
                $positiveValueCents += $valueDeltaCents;
            } elseif ($valueDeltaCents < 0) {
                $negativeValueCents += abs($valueDeltaCents);
            }
        }

        $journalLines = $this->journalLines($adjustment, $adjustmentId, $positiveValueCents, $negativeValueCents);
        if ($journalLines !== []) {
            $reversalJournalId = null;
            if ($adjustment['adjustment_type'] === 'reversal') {
                $reversalJournalId = $this->database->table('journal_entries')
                    ->where('source_key', 'stock_adjustment:' . $adjustment['reversal_of_id'])->get()->getRow('id');
            }
            (new AccountingService($this->database))->post(
                $adjustment['adjustment_type'] === 'reversal' ? 'stock_adjustment_reversal' : 'stock_adjustment',
                $adjustmentId,
                $adjustment['document_date'],
                'Penyesuaian stok ' . $adjustment['reference_number'] . ': ' . $adjustment['reason'],
                $journalLines,
                $userId,
                $reversalJournalId ? (int) $reversalJournalId : null,
            );
        }

        $this->database->table('stock_adjustments')->where('id', $adjustmentId)->where('status', 'approved')->update([
            'status' => 'posted', 'posted_by' => $userId, 'posted_at' => $now, 'updated_at' => $now,
        ]);
        (new AuditLogService($this->database))->record($userId,
            $adjustment['adjustment_type'] === 'reversal' ? 'inventory.adjustment.reversed' : 'inventory.adjustment.posted',
            'stock_adjustment', $adjustmentId, $adjustment['reason'], ['status' => 'approved'], [
                'status' => 'posted',
                'adjustment_type' => $adjustment['adjustment_type'],
                'reversal_of_id' => $adjustment['reversal_of_id'] ? (int) $adjustment['reversal_of_id'] : null,
                'items' => array_map(static fn (array $line): array => [
                    'inventory_item_id' => (int) $line['inventory_item_id'],
                    'quantity_delta' => $line['quantity_delta'],
                    'value_delta' => $line['value_delta'],
                ], $lines),
            ]);
    }

    private function journalLines(array $adjustment, int $adjustmentId, int $positiveValueCents, int $negativeValueCents): array
    {
        $lines = [];
        if ($adjustment['adjustment_type'] === 'reversal') {
            $originalJournal = $this->database->table('journal_entries')
                ->where('source_key', 'stock_adjustment:' . $adjustment['reversal_of_id'])->get()->getRowArray();
            if (!$originalJournal) {
                return [];
            }
            foreach ($this->database->table('journal_lines')->where('journal_entry_id', $originalJournal['id'])->get()->getResultArray() as $line) {
                $lines[] = ['account_id' => (int) $line['account_id'], 'debit' => $line['credit'], 'credit' => $line['debit']];
            }
            return $lines;
        }

        $inventoryKey = 'inventory';
        if ($positiveValueCents > 0) {
            $amount = Money::decimal($positiveValueCents);
            $lines[] = ['account_key' => $inventoryKey, 'debit' => $amount, 'credit' => 0];
            if ($adjustment['adjustment_type'] === 'opening') {
                $lines[] = ['account_id' => (int) $adjustment['offset_account_id'], 'debit' => 0, 'credit' => $amount];
            } else {
                $lines[] = ['account_key' => 'inventory_adjustment_income', 'debit' => 0, 'credit' => $amount];
            }
        }
        if ($negativeValueCents > 0) {
            $amount = Money::decimal($negativeValueCents);
            $lines[] = ['account_key' => 'inventory_variance', 'debit' => $amount, 'credit' => 0];
            $lines[] = ['account_key' => $inventoryKey, 'debit' => 0, 'credit' => $amount];
        }

        return $lines;
    }

    private function editableAdjustment(int $adjustmentId, int $userId, string $role): array
    {
        $adjustment = $this->database->query('SELECT * FROM stock_adjustments WHERE id = ? FOR UPDATE', [$adjustmentId])->getRowArray();
        if (!$adjustment || !in_array($adjustment['status'], ['draft', 'rejected'], true)
            || ($role !== 'owner' && (int) $adjustment['created_by'] !== $userId)) {
            throw new RuntimeException('Draft penyesuaian tidak ditemukan atau tidak dapat diubah.');
        }
        return $adjustment;
    }

    private function referenceNumber(string $prefix): string
    {
        return $prefix . '-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function referenceNumberForAudit(int $adjustmentId): string
    {
        return (string) $this->database->table('stock_adjustments')->where('id', $adjustmentId)->get()->getRow('reference_number');
    }
}
