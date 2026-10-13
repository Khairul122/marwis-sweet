<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\SalesReportService;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $user = session('user');
        $isOwner = ($user['role'] ?? '') === 'owner';
        $today = date('Y-m-d');
        $salesReport = (new SalesReportService($database))->build(['period_type' => 'daily', 'date' => $today], $isOwner);
        $lowStock = $database->table('inventory_items')->select('name, sku, base_unit, quantity_on_hand, minimum_stock')->where('is_active', true)->where('quantity_on_hand <= minimum_stock', null, false)->orderBy('quantity_on_hand')->limit(8)->get()->getResultArray();
        $activeShift = null;
        if (!$isOwner) {
            $activeShift = $database->table('shifts')->where('cashier_id', (int) session('user_id'))->where('status', 'open')->get()->getRowArray();
        }
        $ownerMetrics = null;
        if ($isOwner) {
            $expenseToday = (float) ($database->table('expenses')->selectSum('amount', 'total')->where('status', 'paid')->where('posting_date', $today)->get()->getRow('total') ?? 0);
            $cashRows = $database->query("SELECT a.name, a.system_key, COALESCE(SUM(CASE WHEN je.status = 'posted' THEN jl.debit - jl.credit ELSE 0 END), 0) AS balance FROM accounts a LEFT JOIN journal_lines jl ON jl.account_id = a.id LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE a.system_key IN ('main_cash','cash_drawer','bank') GROUP BY a.id, a.name, a.system_key ORDER BY a.code")->getResultArray();
            $ownerMetrics = ['expenseToday' => $expenseToday, 'cashBalances' => $cashRows, 'cashTotal' => array_sum(array_map(static fn (array $row): float => (float) $row['balance'], $cashRows))];
        }
        $dateFormatter = new \IntlDateFormatter(
            'id_ID',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            'Asia/Jakarta',
            \IntlDateFormatter::GREGORIAN,
            'EEEE, d MMMM y',
        );

        return view('panel/dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
            'todayLabel' => $dateFormatter->format(new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'))),
            'salesReport' => $salesReport,
            'lowStock' => $lowStock,
            'activeShift' => $activeShift,
            'ownerMetrics' => $ownerMetrics,
        ]);
    }
}
