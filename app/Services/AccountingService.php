<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class AccountingService
{
    private array $accountCache = [];

    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function post(string $sourceType, int $sourceId, string $entryDate, string $description, array $lines, int $userId, ?int $reversalOfId = null): int
    {
        $sourceKey = $sourceType . ':' . $sourceId;
        $existing = $this->database->table('journal_entries')->where('source_key', $sourceKey)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $entryDate);
        if (!$date || $date->format('Y-m-d') !== $entryDate) {
            throw new RuntimeException('Tanggal jurnal tidak valid.');
        }
        $closedPeriod = $this->database->table('accounting_periods')->where('status', 'closed')->where('period_start <=', $entryDate)->where('period_end >=', $entryDate)->get()->getRowArray();
        if ($closedPeriod) {
            throw new RuntimeException('Periode akuntansi untuk tanggal tersebut sudah ditutup.');
        }
        if (mb_strlen($description) < 3 || mb_strlen($description) > 500) {
            throw new RuntimeException('Deskripsi jurnal tidak valid.');
        }

        $normalized = [];
        $debitCents = 0;
        $creditCents = 0;
        foreach ($lines as $line) {
            $debit = $this->toCents($line['debit'] ?? 0);
            $credit = $this->toCents($line['credit'] ?? 0);
            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw new RuntimeException('Setiap baris jurnal harus memiliki debit atau kredit.');
            }
            $accountId = isset($line['account_id']) ? (int) $line['account_id'] : $this->accountId((string) ($line['account_key'] ?? ''));
            if (!$accountId || !$this->database->table('accounts')->where('id', $accountId)->where('is_active', true)->get()->getRowArray()) {
                throw new RuntimeException('Akun jurnal tidak tersedia.');
            }
            $debitCents += $debit;
            $creditCents += $credit;
            $normalized[] = ['account_id' => $accountId, 'debit' => $debit, 'credit' => $credit, 'memo' => trim((string) ($line['memo'] ?? '')) ?: null];
        }
        if ($normalized === [] || $debitCents !== $creditCents) {
            throw new RuntimeException('Total debit dan kredit jurnal harus seimbang.');
        }
        $now = date('Y-m-d H:i:s');
        $this->database->table('journal_entries')->insert([
            'journal_number' => 'JRN-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2))),
            'entry_date' => $entryDate, 'description' => $description, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'source_key' => $sourceKey, 'status' => 'posted', 'reversal_of_id' => $reversalOfId, 'created_by' => $userId,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $journalId = (int) $this->database->insertID();
        foreach ($normalized as $line) {
            $this->database->table('journal_lines')->insert([
                'journal_entry_id' => $journalId, 'account_id' => $line['account_id'],
                'debit' => $this->fromCents($line['debit']), 'credit' => $this->fromCents($line['credit']),
                'memo' => $line['memo'], 'created_at' => $now,
            ]);
        }
        return $journalId;
    }

    public function accountId(string $systemKey): int
    {
        if (!isset($this->accountCache[$systemKey])) {
            $id = $this->database->table('accounts')->where('system_key', $systemKey)->where('is_active', true)->get()->getRow('id');
            if (!$id) {
                throw new RuntimeException('Akun sistem ' . $systemKey . ' belum tersedia.');
            }
            $this->accountCache[$systemKey] = (int) $id;
        }
        return $this->accountCache[$systemKey];
    }

    public static function paymentAccountKey(string $method): string
    {
        return $method === 'cash' || $method === 'cash_drawer' ? 'cash_drawer' : ($method === 'main_cash' ? 'main_cash' : 'bank');
    }

    private function toCents(mixed $amount): int
    {
        $cents = Money::cents($amount);
        if ($cents < 0) {
            throw new RuntimeException('Nominal jurnal tidak valid.');
        }
        return $cents;
    }

    private function fromCents(int $cents): string
    {
        return Money::decimal($cents);
    }
}
