<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\AccountingService;
use CodeIgniter\HTTP\RedirectResponse;

class ShiftController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $userId = (int) session('user_id');
        $activeShift = $database->table('shifts')->where('cashier_id', $userId)->where('status', 'open')->get()->getRowArray();
        return view('panel/shifts/index', [
            'title' => 'Shift kasir',
            'user' => session('user'),
            'activeShift' => $activeShift,
            'cashMovements' => $activeShift ? $database->table('cash_movements')->where('shift_id', $activeShift['id'])->orderBy('id', 'DESC')->get()->getResultArray() : [],
            'recentShifts' => $database->table('shifts')->select('shifts.*, users.name AS cashier_name')->join('users', 'users.id = shifts.cashier_id')->orderBy('shifts.id', 'DESC')->limit(20)->get()->getResultArray(),
        ]);
    }

    public function open(): RedirectResponse
    {
        if (!$this->validate(['opening_cash' => 'required|decimal|greater_than_equal_to[0]'])) {
            return redirect()->route('panel.shifts')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database = db_connect();
        $userId = (int) session('user_id');
        try {
            $database->transException(true)->transStart();
            $database->query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if ($database->table('shifts')->where('cashier_id', $userId)->where('status', 'open')->countAllResults() > 0) {
                throw new \RuntimeException('Anda masih memiliki shift aktif.');
            }
            $now = date('Y-m-d H:i:s');
            $openingCash = (float) $this->request->getPost('opening_cash');
            $shiftNumber = 'S-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $database->table('shifts')->insert([
                'shift_number' => $shiftNumber, 'cashier_id' => $userId, 'opened_at' => $now,
                'opening_cash' => $openingCash, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $shiftId = (int) $database->insertID();
            if ($openingCash > 0) {
                (new AccountingService($database))->post('shift_opening', $shiftId, date('Y-m-d', strtotime($now)), 'Modal awal ' . $shiftNumber, [
                    ['account_key' => 'cash_drawer', 'debit' => $openingCash, 'credit' => 0],
                    ['account_key' => 'main_cash', 'debit' => 0, 'credit' => $openingCash],
                ], $userId);
            }
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.shifts')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.shifts')->with('success', 'Shift dibuka. POS sudah dapat digunakan.');
    }

    public function cashMovement(): RedirectResponse
    {
        $rules = ['movement_type' => 'required|in_list[in,out]', 'amount' => 'required|decimal|greater_than[0]', 'reason' => 'required|min_length[3]|max_length[255]'];
        if (!$this->validate($rules)) {
            return redirect()->route('panel.shifts')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database = db_connect();
        $shift = $database->table('shifts')->where('cashier_id', (int) session('user_id'))->where('status', 'open')->get()->getRowArray();
        if (!$shift) {
            return redirect()->route('panel.shifts')->with('error', 'Buka shift sebelum mencatat pergerakan kas.');
        }
        $database->table('cash_movements')->insert([
            'shift_id' => $shift['id'], 'movement_type' => (string) $this->request->getPost('movement_type'), 'amount' => (string) $this->request->getPost('amount'),
            'reason' => trim((string) $this->request->getPost('reason')), 'created_by' => (int) session('user_id'), 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->route('panel.shifts')->with('success', 'Pergerakan kas berhasil dicatat.');
    }

    public function close(): RedirectResponse
    {
        if (!$this->validate(['actual_cash' => 'required|decimal|greater_than_equal_to[0]', 'difference_reason' => 'permit_empty|max_length[500]'])) {
            return redirect()->route('panel.shifts')->with('error', implode(' ', $this->validator->getErrors()));
        }
        $database = db_connect();
        $userId = (int) session('user_id');
        $shift = $database->table('shifts')->where('cashier_id', $userId)->where('status', 'open')->get()->getRowArray();
        if (!$shift) {
            return redirect()->route('panel.shifts')->with('error', 'Shift aktif tidak ditemukan.');
        }
        $cashSales = (float) ($database->table('payments')->selectSum('payments.amount', 'total')->join('sales', 'sales.id = payments.sale_id')->where('sales.shift_id', $shift['id'])->where('payments.method', 'cash')->get()->getRow('total') ?? 0);
        $cashRefunds = (float) ($database->table('refunds')->selectSum('amount', 'total')->where('shift_id', $shift['id'])->where('status', 'posted')->where('method', 'cash')->get()->getRow('total') ?? 0);
        $drawerExpenses = (float) ($database->table('expenses')->selectSum('amount', 'total')->where('shift_id', $shift['id'])->where('status', 'paid')->where('payment_source', 'cash_drawer')->get()->getRow('total') ?? 0);
        $cashIn = (float) ($database->table('cash_movements')->selectSum('amount', 'total')->where('shift_id', $shift['id'])->where('movement_type', 'in')->get()->getRow('total') ?? 0);
        $cashOut = (float) ($database->table('cash_movements')->selectSum('amount', 'total')->where('shift_id', $shift['id'])->where('movement_type', 'out')->get()->getRow('total') ?? 0);
        $expected = (float) $shift['opening_cash'] + $cashSales - $cashRefunds - $drawerExpenses + $cashIn - $cashOut;
        $actual = (float) $this->request->getPost('actual_cash');
        $difference = $actual - $expected;
        $reason = trim((string) $this->request->getPost('difference_reason'));
        if (abs($difference) >= 0.01 && mb_strlen($reason) < 3) {
            return redirect()->route('panel.shifts')->with('error', 'Jelaskan alasan selisih kas sebelum menutup shift.');
        }
        $database->table('shifts')->where('id', $shift['id'])->update([
            'status' => 'closed', 'closed_at' => date('Y-m-d H:i:s'), 'expected_cash' => $expected, 'actual_cash' => $actual,
            'cash_difference' => $difference, 'difference_reason' => $reason ?: null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->route('panel.shifts')->with('success', 'Shift ditutup dan selisih kas tersimpan.');
    }
}
