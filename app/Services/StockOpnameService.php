<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class StockOpnameService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function create(array $payload, int $userId): int
    {
        $scope = (string) ($payload['scope'] ?? 'all');
        $notes = trim((string) ($payload['notes'] ?? ''));
        if (!in_array($scope, ['all', 'partial'], true) || mb_strlen($notes) > 500) {
            throw new RuntimeException('Data sesi opname tidak valid.');
        }
        $itemIds = $scope === 'all'
            ? array_column($this->database->table('inventory_items')->select('id')->where('is_active', true)->orderBy('id')->get()->getResultArray(), 'id')
            : array_values(array_unique(array_filter(array_map('intval', (array) ($payload['item_ids'] ?? [])))));
        if ($itemIds === []) {
            throw new RuntimeException('Pilih minimal satu item untuk dihitung.');
        }
        sort($itemIds);

        try {
            $this->database->transException(true)->transStart();
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $items = $this->database->query('SELECT * FROM inventory_items WHERE id IN (' . $placeholders . ') AND is_active = 1 ORDER BY id FOR UPDATE', $itemIds)->getResultArray();
            if (count($items) !== count($itemIds)) {
                throw new RuntimeException('Salah satu item persediaan tidak tersedia.');
            }
            (new InventoryLockService($this->database))->assertUnlocked($itemIds);
            $now = date('Y-m-d H:i:s');
            $this->database->table('stock_opnames')->insert([
                'opname_number' => 'OPN-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
                'scope' => $scope,
                'status' => 'counting',
                'cutoff_at' => $now,
                'notes' => $notes ?: null,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $opnameId = (int) $this->database->insertID();
            foreach ($items as $item) {
                $this->database->table('stock_opname_items')->insert([
                    'stock_opname_id' => $opnameId,
                    'inventory_item_id' => $item['id'],
                    'system_quantity' => $item['quantity_on_hand'],
                    'unit_cost_snapshot' => $item['average_cost'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->database->transComplete();
            return $opnameId;
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function saveCounts(int $opnameId, array $physicalQuantities, array $notes, int $userId, string $role): void
    {
        try {
            $this->database->transException(true)->transStart();
            $opname = $this->lockEditableOpname($opnameId, $userId, $role);
            $items = $this->database->table('stock_opname_items')->where('stock_opname_id', $opnameId)->get()->getResultArray();
            foreach ($items as $item) {
                $rawQuantity = $physicalQuantities[$item['id']] ?? null;
                if ($rawQuantity === null || $rawQuantity === '' || !is_numeric($rawQuantity) || (float) $rawQuantity < 0) {
                    throw new RuntimeException('Isi jumlah fisik seluruh item dengan angka nol atau lebih.');
                }
                $itemNotes = trim((string) ($notes[$item['id']] ?? ''));
                if (mb_strlen($itemNotes) > 300) {
                    throw new RuntimeException('Catatan item maksimal 300 karakter.');
                }
                $physical = round((float) $rawQuantity, 3);
                $this->database->table('stock_opname_items')->where('id', $item['id'])->update([
                    'physical_quantity' => $physical,
                    'difference_quantity' => round($physical - (float) $item['system_quantity'], 3),
                    'notes' => $itemNotes ?: null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($opname['status'] === 'rejected') {
                $this->database->table('stock_opnames')->where('id', $opnameId)->update(['status' => 'counting', 'updated_at' => date('Y-m-d H:i:s')]);
            }
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function submit(int $opnameId, int $userId, string $role): void
    {
        try {
            $this->database->transException(true)->transStart();
            $this->lockEditableOpname($opnameId, $userId, $role);
            $missing = $this->database->table('stock_opname_items')->where('stock_opname_id', $opnameId)->where('physical_quantity', null)->countAllResults();
            if ($missing > 0) {
                throw new RuntimeException('Simpan hasil hitung seluruh item sebelum mengirim opname.');
            }
            $now = date('Y-m-d H:i:s');
            $this->database->table('stock_opnames')->where('id', $opnameId)->update([
                'status' => 'submitted', 'submitted_by' => $userId, 'submitted_at' => $now, 'updated_at' => $now,
            ]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function attachEvidence(int $opnameId, mixed $file, int $userId, string $role): void
    {
        $opname = $this->database->table('stock_opnames')->where('id', $opnameId)->get()->getRowArray();
        if (!$opname || !in_array($opname['status'], ['counting', 'rejected'], true) || ($role !== 'owner' && (int) $opname['created_by'] !== $userId)) {
            throw new RuntimeException('Bukti opname tidak dapat diubah.');
        }
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Pilih berkas bukti terlebih dahulu.');
        }
        $evidence = (new EvidenceUploadService())->store($file, 'opnames');
        $oldPath = $opname['evidence_path'];
        try {
            $this->database->table('stock_opnames')->where('id', $opnameId)->update([
                'evidence_path' => $evidence['path'], 'evidence_name' => $evidence['name'], 'evidence_mime' => $evidence['mime'],
                'evidence_uploaded_by' => $userId, 'evidence_uploaded_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            @unlink(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $evidence['path']));
            throw $exception;
        }
        if ($oldPath) {
            @unlink(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $oldPath));
        }
    }

    public function reject(int $opnameId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Catatan koreksi harus berisi 5 sampai 500 karakter.');
        }
        try {
            $this->database->transException(true)->transStart();
            $opname = $this->database->query('SELECT * FROM stock_opnames WHERE id = ? FOR UPDATE', [$opnameId])->getRowArray();
            if (!$opname || $opname['status'] !== 'submitted') {
                throw new RuntimeException('Opname tidak ditemukan atau sudah diproses.');
            }
            $this->database->table('stock_opnames')->where('id', $opnameId)->update(['status' => 'rejected', 'review_notes' => $reason, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function cancel(int $opnameId, string $reason, int $userId, string $role): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Alasan pembatalan harus berisi 5 sampai 500 karakter.');
        }
        try {
            $this->database->transException(true)->transStart();
            $opname = $this->database->query('SELECT * FROM stock_opnames WHERE id = ? FOR UPDATE', [$opnameId])->getRowArray();
            if (!$opname || !in_array($opname['status'], ['counting', 'submitted', 'rejected'], true) || ($role !== 'owner' && (int) $opname['created_by'] !== $userId)) {
                throw new RuntimeException('Sesi opname tidak dapat dibatalkan.');
            }
            $now = date('Y-m-d H:i:s');
            $this->database->table('stock_opnames')->where('id', $opnameId)->update([
                'status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_by' => $userId,
                'cancelled_at' => $now, 'updated_at' => $now,
            ]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    public function approve(int $opnameId, array $costOverrides, int $ownerId): void
    {
        try {
            $this->database->transException(true)->transStart();
            $opname = $this->database->query('SELECT * FROM stock_opnames WHERE id = ? FOR UPDATE', [$opnameId])->getRowArray();
            if (!$opname || $opname['status'] !== 'submitted') {
                throw new RuntimeException('Opname tidak ditemukan atau sudah diproses.');
            }
            $items = $this->database->table('stock_opname_items')->where('stock_opname_id', $opnameId)->orderBy('inventory_item_id')->get()->getResultArray();
            $now = date('Y-m-d H:i:s');
            $shortageValue = 0.0;
            $surplusValue = 0.0;
            foreach ($items as $line) {
                $item = $this->database->query('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$line['inventory_item_id']])->getRowArray();
                if (!$item || abs((float) $item['quantity_on_hand'] - (float) $line['system_quantity']) > 0.0001) {
                    throw new RuntimeException('Saldo stok berubah setelah cut-off. Batalkan sesi dan buat opname baru.');
                }
                $difference = (float) $line['difference_quantity'];
                if (abs($difference) < 0.0001) {
                    continue;
                }
                $unitCost = (float) $line['unit_cost_snapshot'];
                $override = $costOverrides[$line['id']] ?? null;
                if ($difference > 0 && $override !== null && $override !== '') {
                    if (!is_numeric($override) || (float) $override < 0) {
                        throw new RuntimeException('Biaya penyesuaian harus berupa angka nol atau lebih.');
                    }
                    $unitCost = (float) $override;
                }
                if ($difference > 0 && $unitCost <= 0) {
                    throw new RuntimeException('Isi biaya valid untuk item kelebihan stok yang belum memiliki biaya rata-rata.');
                }
                $newQuantity = (float) $line['physical_quantity'];
                $newAverage = (float) $item['average_cost'];
                if ($difference > 0 && $newQuantity > 0) {
                    $newAverage = (((float) $item['quantity_on_hand'] * (float) $item['average_cost']) + ($difference * $unitCost)) / $newQuantity;
                }
                $this->database->table('inventory_items')->where('id', $item['id'])->update([
                    'quantity_on_hand' => $newQuantity, 'average_cost' => $newAverage, 'updated_at' => $now,
                ]);
                $this->database->table('stock_opname_items')->where('id', $line['id'])->update(['adjustment_unit_cost' => $unitCost, 'updated_at' => $now]);
                $this->database->table('stock_movements')->insert([
                    'inventory_item_id' => $item['id'], 'quantity' => $difference, 'unit_cost' => $unitCost,
                    'total_cost' => round($difference * $unitCost, 2), 'movement_type' => 'opname',
                    'source_type' => 'stock_opname', 'source_id' => $opnameId, 'notes' => $opname['opname_number'],
                    'posted_at' => $now, 'created_by' => $ownerId, 'created_at' => $now,
                ]);
                $adjustmentValue = round(abs($difference * $unitCost), 2);
                if ($difference < 0) { $shortageValue += $adjustmentValue; } else { $surplusValue += $adjustmentValue; }
            }
            $journalLines = [];
            if ($shortageValue > 0) {
                $journalLines[] = ['account_key' => 'inventory_variance', 'debit' => $shortageValue, 'credit' => 0];
                $journalLines[] = ['account_key' => 'inventory', 'debit' => 0, 'credit' => $shortageValue];
            }
            if ($surplusValue > 0) {
                $journalLines[] = ['account_key' => 'inventory', 'debit' => $surplusValue, 'credit' => 0];
                $journalLines[] = ['account_key' => 'inventory_adjustment_income', 'debit' => 0, 'credit' => $surplusValue];
            }
            if ($journalLines !== []) {
                (new AccountingService($this->database))->post('stock_opname', $opnameId, date('Y-m-d', strtotime($now)), 'Penyesuaian stok ' . $opname['opname_number'], $journalLines, $ownerId);
            }
            $this->database->table('stock_opnames')->where('id', $opnameId)->update([
                'status' => 'approved', 'approved_by' => $ownerId, 'approved_at' => $now, 'updated_at' => $now,
            ]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }

    private function lockEditableOpname(int $opnameId, int $userId, string $role): array
    {
        $opname = $this->database->query('SELECT * FROM stock_opnames WHERE id = ? FOR UPDATE', [$opnameId])->getRowArray();
        if (!$opname || !in_array($opname['status'], ['counting', 'rejected'], true) || ($role !== 'owner' && (int) $opname['created_by'] !== $userId)) {
            throw new RuntimeException('Sesi opname tidak dapat diubah.');
        }
        return $opname;
    }
}
