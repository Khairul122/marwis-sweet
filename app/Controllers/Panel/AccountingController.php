<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\AccountingService;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class AccountingController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $from = $this->validDate((string) $this->request->getGet('from')) ?: date('Y-m-01');
        $to = $this->validDate((string) $this->request->getGet('to')) ?: date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $accountId = filter_var($this->request->getGet('account'), FILTER_VALIDATE_INT) ?: null;
        $accounts = $database->table('accounts')->orderBy('code')->get()->getResultArray();
        $entries = $database->table('journal_entries')->select('journal_entries.*, users.name AS creator_name')->join('users', 'users.id = journal_entries.created_by')->where('entry_date >=', $from)->where('entry_date <=', $to)->orderBy('entry_date', 'DESC')->orderBy('id', 'DESC')->limit(100)->get()->getResultArray();
        $linesByEntry = [];
        $entryIds = array_column($entries, 'id');
        if ($entryIds !== []) {
            foreach ($database->table('journal_lines')->select('journal_lines.*, accounts.code, accounts.name AS account_name')->join('accounts', 'accounts.id = journal_lines.account_id')->whereIn('journal_entry_id', $entryIds)->orderBy('journal_lines.id')->get()->getResultArray() as $line) {
                $linesByEntry[$line['journal_entry_id']][] = $line;
            }
        }
        $trialBalance = $database->query('SELECT a.id, a.code, a.name, a.account_type, a.normal_balance, COALESCE(SUM(CASE WHEN je.status = \'posted\' AND je.entry_date BETWEEN ? AND ? THEN jl.debit ELSE 0 END), 0) AS debit, COALESCE(SUM(CASE WHEN je.status = \'posted\' AND je.entry_date BETWEEN ? AND ? THEN jl.credit ELSE 0 END), 0) AS credit FROM accounts a LEFT JOIN journal_lines jl ON jl.account_id = a.id LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id GROUP BY a.id, a.code, a.name, a.account_type, a.normal_balance ORDER BY a.code', [$from, $to, $from, $to])->getResultArray();
        $cumulativeBalance = $database->query('SELECT a.id, a.code, a.name, a.account_type, a.normal_balance, COALESCE(SUM(CASE WHEN je.status = \'posted\' AND je.entry_date <= ? THEN jl.debit ELSE 0 END), 0) AS debit, COALESCE(SUM(CASE WHEN je.status = \'posted\' AND je.entry_date <= ? THEN jl.credit ELSE 0 END), 0) AS credit FROM accounts a LEFT JOIN journal_lines jl ON jl.account_id = a.id LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id GROUP BY a.id, a.code, a.name, a.account_type, a.normal_balance ORDER BY a.code', [$to, $to])->getResultArray();
        $ledger = [];
        $ledgerOpening = 0.0;
        if ($accountId) {
            $selectedAccount = array_values(array_filter($accounts, static fn (array $account): bool => (int) $account['id'] === (int) $accountId))[0] ?? null;
            if (!$selectedAccount) {
                $accountId = null;
            } else {
                $opening = $database->query('SELECT COALESCE(SUM(jl.debit), 0) AS debit, COALESCE(SUM(jl.credit), 0) AS credit FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE jl.account_id = ? AND je.status = \'posted\' AND je.entry_date < ?', [$accountId, $from])->getRowArray();
                $ledgerOpening = $selectedAccount['normal_balance'] === 'debit' ? (float) $opening['debit'] - (float) $opening['credit'] : (float) $opening['credit'] - (float) $opening['debit'];
            }
        }
        if ($accountId) {
            $ledger = $database->table('journal_lines')->select('journal_lines.*, journal_entries.entry_date, journal_entries.journal_number, journal_entries.description')->join('journal_entries', 'journal_entries.id = journal_lines.journal_entry_id')->where('journal_lines.account_id', $accountId)->where('journal_entries.status', 'posted')->where('journal_entries.entry_date >=', $from)->where('journal_entries.entry_date <=', $to)->orderBy('journal_entries.entry_date')->orderBy('journal_lines.id')->get()->getResultArray();
        }

        $profitLoss = ['revenue' => 0.0, 'contra_revenue' => 0.0, 'expense' => 0.0, 'net_profit' => 0.0];
        foreach ($trialBalance as $row) {
            if ($row['account_type'] === 'revenue') { $profitLoss['revenue'] += (float) $row['credit'] - (float) $row['debit']; }
            elseif ($row['account_type'] === 'contra_revenue') { $profitLoss['contra_revenue'] += (float) $row['debit'] - (float) $row['credit']; }
            elseif ($row['account_type'] === 'expense') { $profitLoss['expense'] += (float) $row['debit'] - (float) $row['credit']; }
        }
        $profitLoss['net_profit'] = $profitLoss['revenue'] - $profitLoss['contra_revenue'] - $profitLoss['expense'];
        $balanceSheet = ['assets' => [], 'liabilities' => [], 'equity' => [], 'asset_total' => 0.0, 'liability_total' => 0.0, 'equity_total' => 0.0, 'current_profit' => 0.0];
        foreach ($cumulativeBalance as $row) {
            $balance = $row['normal_balance'] === 'debit' ? (float) $row['debit'] - (float) $row['credit'] : (float) $row['credit'] - (float) $row['debit'];
            if ($row['account_type'] === 'asset') { $balanceSheet['assets'][] = $row + ['balance' => $balance]; $balanceSheet['asset_total'] += $balance; }
            elseif ($row['account_type'] === 'liability') { $balanceSheet['liabilities'][] = $row + ['balance' => $balance]; $balanceSheet['liability_total'] += $balance; }
            elseif ($row['account_type'] === 'equity') {
                $equityBalance = $row['normal_balance'] === 'credit' ? $balance : -$balance;
                $balanceSheet['equity'][] = $row + ['balance' => $equityBalance];
                $balanceSheet['equity_total'] += $equityBalance;
            }
            elseif ($row['account_type'] === 'revenue') { $balanceSheet['current_profit'] += (float) $row['credit'] - (float) $row['debit']; }
            elseif ($row['account_type'] === 'contra_revenue' || $row['account_type'] === 'expense') { $balanceSheet['current_profit'] -= (float) $row['debit'] - (float) $row['credit']; }
        }
        $cashReport = [];
        foreach (array_filter($accounts, static fn (array $account): bool => in_array($account['system_key'], ['main_cash', 'cash_drawer', 'bank'], true)) as $cashAccount) {
            $opening = $database->query('SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS balance FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE jl.account_id = ? AND je.status = \'posted\' AND je.entry_date < ?', [$cashAccount['id'], $from])->getRow('balance');
            $movement = $database->query('SELECT COALESCE(SUM(jl.debit), 0) AS incoming, COALESCE(SUM(jl.credit), 0) AS outgoing FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE jl.account_id = ? AND je.status = \'posted\' AND je.entry_date BETWEEN ? AND ?', [$cashAccount['id'], $from, $to])->getRowArray();
            $cashReport[] = $cashAccount + ['opening' => (float) $opening, 'incoming' => (float) $movement['incoming'], 'outgoing' => (float) $movement['outgoing'], 'ending' => (float) $opening + (float) $movement['incoming'] - (float) $movement['outgoing']];
        }
        return view('panel/accounting/index', compact('accounts', 'entries', 'linesByEntry', 'trialBalance', 'ledger', 'ledgerOpening', 'profitLoss', 'balanceSheet', 'cashReport', 'accountId', 'from', 'to') + ['title' => 'Pembukuan', 'user' => session('user'), 'selectedAccountId' => $accountId, 'periods' => $database->table('accounting_periods')->orderBy('period_start', 'DESC')->get()->getResultArray()]);
    }

    public function manualJournal(): RedirectResponse
    {
        $database = db_connect();
        $date = $this->validDate((string) $this->request->getPost('entry_date'));
        $debitId = filter_var($this->request->getPost('debit_account_id'), FILTER_VALIDATE_INT);
        $creditId = filter_var($this->request->getPost('credit_account_id'), FILTER_VALIDATE_INT);
        $amount = $this->request->getPost('amount');
        $description = trim((string) $this->request->getPost('description'));
        $debit = $debitId ? $database->table('accounts')->where('id', $debitId)->where('allow_manual', true)->where('is_active', true)->get()->getRowArray() : null;
        $credit = $creditId ? $database->table('accounts')->where('id', $creditId)->where('allow_manual', true)->where('is_active', true)->get()->getRowArray() : null;
        if (!$date || !$debit || !$credit || $debitId === $creditId || !is_numeric($amount) || (float) $amount <= 0 || mb_strlen($description) < 5 || mb_strlen($description) > 500) {
            return redirect()->route('panel.accounting')->with('error', 'Data jurnal manual tidak valid atau akun tidak mengizinkan posting manual.');
        }
        try {
            $database->transStart();
            (new AccountingService($database))->post('manual_journal', random_int(1, PHP_INT_MAX), $date, $description, [['account_id' => $debitId, 'debit' => $amount, 'credit' => 0], ['account_id' => $creditId, 'debit' => 0, 'credit' => $amount]], (int) session('user_id'));
            $database->transComplete();
            if (!$database->transStatus()) { throw new RuntimeException('Jurnal manual gagal disimpan.'); }
        } catch (\Throwable $exception) { return redirect()->route('panel.accounting')->with('error', $exception->getMessage()); }
        return redirect()->route('panel.accounting')->with('success', 'Jurnal manual berhasil diposting.');
    }

    public function openingBalance(): RedirectResponse
    {
        $database = db_connect();
        $date = $this->validDate((string) $this->request->getPost('entry_date'));
        $debitId = filter_var($this->request->getPost('debit_account_id'), FILTER_VALIDATE_INT);
        $creditId = filter_var($this->request->getPost('credit_account_id'), FILTER_VALIDATE_INT);
        $amount = $this->request->getPost('amount');
        $description = trim((string) $this->request->getPost('description'));
        $debit = $debitId ? $database->table('accounts')->where('id', $debitId)->where('account_type', 'asset')->get()->getRowArray() : null;
        $credit = $creditId ? $database->table('accounts')->where('id', $creditId)->whereIn('account_type', ['liability', 'equity'])->get()->getRowArray() : null;
        if (!$date || !$debit || !$credit || !is_numeric($amount) || (float) $amount <= 0 || mb_strlen($description) < 5 || mb_strlen($description) > 500) {
            return redirect()->route('panel.accounting')->with('error', 'Data saldo awal tidak valid.');
        }
        if ($debit['system_key'] === 'inventory') {
            $inventoryValue = (float) ($database->table('inventory_items')->select('SUM(quantity_on_hand * average_cost) AS total', false)->get()->getRow('total') ?? 0);
            if (abs($inventoryValue - (float) $amount) > 0.01) { return redirect()->route('panel.accounting')->with('error', 'Saldo awal persediaan harus sama dengan nilai kartu stok: Rp' . number_format($inventoryValue, 2, ',', '.')); }
        }
        try {
            $database->transException(true)->transStart();
            $database->query('SELECT id FROM accounts WHERE id = ? FOR UPDATE', [$debitId]);
            $existingOpening = $database->table('journal_entries')
                ->select('journal_number')
                ->where('source_key', 'opening_balance:' . $debitId)
                ->get()->getRowArray();
            if (!$existingOpening) {
                $existingOpening = $database->table('journal_entries')
                    ->select('journal_entries.journal_number')
                    ->join('journal_lines', 'journal_lines.journal_entry_id = journal_entries.id')
                    ->where('journal_entries.source_type', 'opening_balance')
                    ->where('journal_lines.account_id', $debitId)
                    ->where('journal_lines.debit >', 0)
                    ->get()->getRowArray();
            }
            if ($existingOpening) {
                throw new RuntimeException('Saldo awal untuk akun aset ini sudah pernah diposting (' . $existingOpening['journal_number'] . '). Pengiriman kedua ditolak; koreksi harus dicatat pada jurnal terpisah.');
            }
            (new AccountingService($database))->post('opening_balance', $debitId, $date, $description, [['account_id' => $debitId, 'debit' => $amount, 'credit' => 0], ['account_id' => $creditId, 'debit' => 0, 'credit' => $amount]], (int) session('user_id'));
            $database->transComplete();
            if (!$database->transStatus()) { throw new RuntimeException('Saldo awal gagal disimpan.'); }
        } catch (\Throwable $exception) { return redirect()->route('panel.accounting')->with('error', $exception->getMessage()); }
        return redirect()->route('panel.accounting')->with('success', 'Saldo awal berhasil diposting.');
    }

    public function closePeriod(): RedirectResponse
    {
        $month = (string) $this->request->getPost('month');
        $reason = trim((string) $this->request->getPost('reason'));
        $start = \DateTimeImmutable::createFromFormat('!Y-m', $month);
        if (!$start || $start->format('Y-m') !== $month || mb_strlen($reason) < 5 || mb_strlen($reason) > 500) { return redirect()->route('panel.accounting')->with('error', 'Bulan dan alasan penutupan wajib diisi.'); }
        $periodStart = $start->format('Y-m-01'); $periodEnd = $start->modify('last day of this month')->format('Y-m-d');
        $database = db_connect(); $now = date('Y-m-d H:i:s');
        $existing = $database->table('accounting_periods')->where('period_start', $periodStart)->where('period_end', $periodEnd)->get()->getRowArray();
        if ($existing && $existing['status'] === 'closed') { return redirect()->route('panel.accounting')->with('error', 'Periode tersebut sudah ditutup.'); }
        $data = ['status' => 'closed', 'closed_by' => (int) session('user_id'), 'closed_at' => $now, 'close_reason' => $reason, 'updated_at' => $now];
        if ($existing) { $database->table('accounting_periods')->where('id', $existing['id'])->update($data); } else { $database->table('accounting_periods')->insert($data + ['period_start' => $periodStart, 'period_end' => $periodEnd, 'created_at' => $now]); }
        return redirect()->route('panel.accounting')->with('success', 'Periode ' . $month . ' ditutup. Posting baru pada bulan tersebut akan ditolak.');
    }

    public function reopenPeriod(int $periodId): RedirectResponse
    {
        $reason = trim((string) $this->request->getPost('reason'));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) { return redirect()->route('panel.accounting')->with('error', 'Alasan pembukaan kembali harus berisi 5 sampai 500 karakter.'); }
        $database = db_connect(); $period = $database->table('accounting_periods')->where('id', $periodId)->where('status', 'closed')->get()->getRowArray();
        if (!$period) { return redirect()->route('panel.accounting')->with('error', 'Periode tertutup tidak ditemukan.'); }
        $database->table('accounting_periods')->where('id', $periodId)->update(['status' => 'open', 'reopened_by' => (int) session('user_id'), 'reopened_at' => date('Y-m-d H:i:s'), 'reopen_reason' => $reason, 'updated_at' => date('Y-m-d H:i:s')]);
        return redirect()->route('panel.accounting')->with('success', 'Periode dibuka kembali dan alasannya tersimpan.');
    }

    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
