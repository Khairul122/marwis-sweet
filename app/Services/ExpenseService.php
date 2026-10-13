<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class ExpenseService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function create(array $payload, int $userId): int
    {
        $data = $this->normalize($payload, $userId);
        $evidence = null;
        $file = $payload['evidence'] ?? null;
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $evidence = (new EvidenceUploadService())->store($file);
        }
        $now = date('Y-m-d H:i:s');
        try {
            $this->database->transException(true)->transStart();
            $expenseNumber = 'EXP-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $this->database->table('expenses')->insert(array_merge($data, [
                'expense_number' => $expenseNumber,
                'evidence_path' => $evidence['path'] ?? null,
                'evidence_name' => $evidence['name'] ?? null,
                'evidence_mime' => $evidence['mime'] ?? null,
                'status' => 'draft', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]));
            $expenseId = (int) $this->database->insertID();
            (new AuditLogService($this->database))->record($userId, 'expense.created', 'expense', $expenseId, null, [], [
                'expense_number' => $expenseNumber, 'status' => 'draft', 'amount' => $data['amount'],
                'expense_category_id' => $data['expense_category_id'], 'payment_source' => $data['payment_source'],
            ]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            if ($evidence) {
                @unlink(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $evidence['path']));
            }
            throw $exception;
        }
        return $expenseId;
    }

    public function update(int $expenseId, array $payload, int $userId): void
    {
        $expense = $this->database->table('expenses')->where('id', $expenseId)->get()->getRowArray();
        if (!$expense || (int) $expense['created_by'] !== $userId || !in_array($expense['status'], ['draft', 'rejected'], true)) {
            throw new RuntimeException('Pengeluaran tidak dapat diubah.');
        }
        $data = $this->normalize($payload, $userId);
        $file = $payload['evidence'] ?? null;
        $oldPath = null;
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $evidence = (new EvidenceUploadService())->store($file);
            $data = array_merge($data, ['evidence_path' => $evidence['path'], 'evidence_name' => $evidence['name'], 'evidence_mime' => $evidence['mime']]);
            $oldPath = $expense['evidence_path'];
        }
        $data['status'] = 'draft';
        $data['review_notes'] = null;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $evidence = $file && $file->getError() !== UPLOAD_ERR_NO_FILE ? $evidence : null;
        try {
            $this->database->transException(true)->transStart();
            $this->database->table('expenses')->where('id', $expenseId)->update($data);
            (new AuditLogService($this->database))->record($userId, 'expense.updated', 'expense', $expenseId, 'Draft pengeluaran diperbarui.', [
                'status' => $expense['status'], 'amount' => $expense['amount'], 'expense_category_id' => (int) $expense['expense_category_id'], 'payment_source' => $expense['payment_source'],
            ], [
                'status' => 'draft', 'amount' => $data['amount'], 'expense_category_id' => (int) $data['expense_category_id'], 'payment_source' => $data['payment_source'],
            ]);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            if ($evidence) { @unlink(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $evidence['path'])); }
            throw $exception;
        }
        if ($oldPath) {
            @unlink(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $oldPath));
        }
    }

    public function submit(int $expenseId, int $userId, string $role): void
    {
        $this->transition($expenseId, ['draft', 'rejected'], 'submitted', function (array $expense, string $now) use ($userId, $role): array {
            if ($role !== 'owner' && (int) $expense['created_by'] !== $userId) {
                throw new RuntimeException('Pengeluaran ini tidak dapat diajukan oleh akun Anda.');
            }
            return ['submitted_by' => $userId, 'submitted_at' => $now];
        }, $userId);
    }

    public function approve(int $expenseId, int $ownerId): void
    {
        $this->transition($expenseId, ['submitted'], 'approved', static fn (array $expense, string $now): array => ['approved_by' => $ownerId, 'approved_at' => $now], $ownerId);
    }

    public function pay(int $expenseId, int $ownerId): void
    {
        $this->transition($expenseId, ['approved'], 'paid', function (array $expense, string $now) use ($ownerId): array {
            if ($expense['payment_source'] === 'cash_drawer') {
                $shift = $this->database->query('SELECT * FROM shifts WHERE id = ? FOR UPDATE', [$expense['shift_id']])->getRowArray();
                if (!$shift || $shift['status'] !== 'open') {
                    throw new RuntimeException('Shift sumber sudah ditutup. Batalkan dokumen dan buat ulang dengan sumber pembayaran yang masih aktif.');
                }
            }
            $category = $this->database->table('expense_categories')->where('id', $expense['expense_category_id'])->get()->getRowArray();
            if (!$category || !$category['account_id']) {
                throw new RuntimeException('Kategori pengeluaran belum dipetakan ke akun beban.');
            }
            $postingDate = date('Y-m-d');
            (new AccountingService($this->database))->post('expense', (int) $expense['id'], $postingDate, 'Pengeluaran ' . $expense['expense_number'] . ': ' . $expense['description'], [
                ['account_id' => (int) $category['account_id'], 'debit' => (float) $expense['amount'], 'credit' => 0],
                ['account_key' => AccountingService::paymentAccountKey($expense['payment_source']), 'debit' => 0, 'credit' => (float) $expense['amount']],
            ], $ownerId);
            return ['posting_date' => $postingDate, 'paid_by' => $ownerId, 'paid_at' => $now];
        }, $ownerId);
    }

    public function reject(int $expenseId, string $reason, int $ownerId): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Catatan penolakan harus berisi 5 sampai 500 karakter.');
        }
        $this->transition($expenseId, ['submitted'], 'rejected', static fn (): array => ['review_notes' => $reason], $ownerId);
    }

    public function cancel(int $expenseId, string $reason, int $userId, string $role): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new RuntimeException('Alasan pembatalan harus berisi 5 sampai 500 karakter.');
        }
        $this->transition($expenseId, ['draft', 'submitted', 'approved', 'rejected'], 'cancelled', function (array $expense, string $now) use ($reason, $userId, $role): array {
            if ($role !== 'owner' && (int) $expense['created_by'] !== $userId) {
                throw new RuntimeException('Pengeluaran ini tidak dapat dibatalkan oleh akun Anda.');
            }
            return ['cancellation_reason' => $reason, 'cancelled_by' => $userId, 'cancelled_at' => $now];
        }, $userId);
    }

    private function normalize(array $payload, int $userId): array
    {
        $categoryId = filter_var($payload['expense_category_id'] ?? null, FILTER_VALIDATE_INT);
        $documentDate = (string) ($payload['document_date'] ?? '');
        $description = trim((string) ($payload['description'] ?? ''));
        $amount = $payload['amount'] ?? null;
        $source = (string) ($payload['payment_source'] ?? '');
        $payee = trim((string) ($payload['payee'] ?? ''));
        if (!$categoryId || !$this->database->table('expense_categories')->where('id', $categoryId)->where('is_active', true)->get()->getRowArray()) {
            throw new RuntimeException('Kategori pengeluaran tidak valid.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $documentDate);
        if (!$date || $date->format('Y-m-d') !== $documentDate) {
            throw new RuntimeException('Tanggal dokumen tidak valid.');
        }
        if (mb_strlen($description) < 5 || mb_strlen($description) > 500 || !is_numeric($amount) || (float) $amount <= 0) {
            throw new RuntimeException('Isi deskripsi dan nominal pengeluaran dengan benar.');
        }
        if (!in_array($source, ['cash_drawer', 'main_cash', 'bank'], true) || mb_strlen($payee) > 160) {
            throw new RuntimeException('Sumber pembayaran atau penerima tidak valid.');
        }
        $shiftId = null;
        if ($source === 'cash_drawer') {
            $shift = $this->database->table('shifts')->where('cashier_id', $userId)->where('status', 'open')->get()->getRowArray();
            if (!$shift) {
                throw new RuntimeException('Buka shift sebelum memilih sumber laci kasir.');
            }
            $shiftId = (int) $shift['id'];
        }
        return [
            'document_date' => $documentDate, 'expense_category_id' => (int) $categoryId,
            'description' => $description, 'amount' => round((float) $amount, 2),
            'payment_source' => $source, 'shift_id' => $shiftId, 'payee' => $payee ?: null,
        ];
    }

    private function transition(int $expenseId, array $fromStatuses, string $toStatus, callable $extra, int $actorUserId): void
    {
        try {
            $this->database->transException(true)->transStart();
            $expense = $this->database->query('SELECT * FROM expenses WHERE id = ? FOR UPDATE', [$expenseId])->getRowArray();
            if (!$expense || !in_array($expense['status'], $fromStatuses, true)) {
                throw new RuntimeException('Pengeluaran tidak ditemukan atau sudah diproses.');
            }
            $now = date('Y-m-d H:i:s');
            $data = $extra($expense, $now);
            $data['status'] = $toStatus;
            $data['updated_at'] = $now;
            $this->database->table('expenses')->where('id', $expenseId)->update($data);
            $before = [
                'status' => $expense['status'], 'amount' => $expense['amount'],
                'expense_category_id' => (int) $expense['expense_category_id'], 'payment_source' => $expense['payment_source'],
            ];
            $after = $before;
            $after['status'] = $toStatus;
            $reason = $data['review_notes'] ?? $data['cancellation_reason'] ?? null;
            (new AuditLogService($this->database))->record($actorUserId, 'expense.' . $toStatus, 'expense', $expenseId, $reason, $before, $after);
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }
    }
}
