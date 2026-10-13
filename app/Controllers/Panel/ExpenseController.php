<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\ExpenseService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class ExpenseController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $builder = $database->table('expenses')
            ->select('expenses.*, expense_categories.name AS category_name, users.name AS creator_name, shifts.shift_number')
            ->join('expense_categories', 'expense_categories.id = expenses.expense_category_id')
            ->join('users', 'users.id = expenses.created_by')
            ->join('shifts', 'shifts.id = expenses.shift_id', 'left');
        if ((session('user')['role'] ?? '') !== 'owner') {
            $builder->where('expenses.created_by', (int) session('user_id'));
        }
        $status = (string) $this->request->getGet('status');
        $categoryId = filter_var($this->request->getGet('category'), FILTER_VALIDATE_INT);
        $source = (string) $this->request->getGet('source');
        $dateFrom = (string) $this->request->getGet('from');
        $dateTo = (string) $this->request->getGet('to');
        if (in_array($status, ['draft', 'submitted', 'approved', 'paid', 'rejected', 'cancelled'], true)) { $builder->where('expenses.status', $status); }
        if ($categoryId) { $builder->where('expenses.expense_category_id', $categoryId); }
        if (in_array($source, ['cash_drawer', 'main_cash', 'bank'], true)) { $builder->where('expenses.payment_source', $source); }
        if ($dateFrom !== '') { $builder->where('expenses.document_date >=', $dateFrom); }
        if ($dateTo !== '') { $builder->where('expenses.document_date <=', $dateTo); }
        return view('panel/expenses/index', [
            'title' => 'Pengeluaran', 'user' => session('user'),
            'categories' => $database->table('expense_categories')->orderBy('is_active', 'DESC')->orderBy('name')->get()->getResultArray(),
            'expenses' => $builder->orderBy('expenses.id', 'DESC')->limit(100)->get()->getResultArray(),
            'filters' => compact('status', 'source', 'dateFrom', 'dateTo') + ['category' => $categoryId ?: ''],
        ]);
    }

    public function create(): RedirectResponse
    {
        try {
            (new ExpenseService())->create($this->expensePayload(), (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.expenses')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.expenses')->with('success', 'Draft pengeluaran berhasil dibuat.');
    }

    public function update(int $expenseId): RedirectResponse
    {
        try {
            (new ExpenseService())->update($expenseId, $this->expensePayload(), (int) session('user_id'));
        } catch (\Throwable $exception) {
            return redirect()->route('panel.expenses')->with('error', $exception->getMessage());
        }
        return redirect()->route('panel.expenses')->with('success', 'Draft pengeluaran diperbarui.');
    }

    public function submit(int $expenseId): RedirectResponse
    {
        return $this->runAction(fn () => (new ExpenseService())->submit($expenseId, (int) session('user_id'), (string) (session('user')['role'] ?? '')), 'Pengeluaran dikirim untuk persetujuan owner.');
    }

    public function approve(int $expenseId): RedirectResponse
    {
        return $this->runAction(fn () => (new ExpenseService())->approve($expenseId, (int) session('user_id')), 'Pengeluaran disetujui dan siap dibayar.');
    }

    public function pay(int $expenseId): RedirectResponse
    {
        return $this->runAction(fn () => (new ExpenseService())->pay($expenseId, (int) session('user_id')), 'Pembayaran pengeluaran berhasil dibukukan.');
    }

    public function reject(int $expenseId): RedirectResponse
    {
        return $this->runAction(fn () => (new ExpenseService())->reject($expenseId, (string) $this->request->getPost('review_notes')), 'Pengeluaran dikembalikan untuk diperbaiki.');
    }

    public function cancel(int $expenseId): RedirectResponse
    {
        return $this->runAction(fn () => (new ExpenseService())->cancel($expenseId, (string) $this->request->getPost('cancellation_reason'), (int) session('user_id'), (string) (session('user')['role'] ?? '')), 'Pengeluaran dibatalkan.');
    }

    public function createCategory(): RedirectResponse
    {
        $name = trim((string) $this->request->getPost('name'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return redirect()->route('panel.expenses')->with('error', 'Nama kategori harus berisi 2 sampai 100 karakter.');
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-')) ?: 'kategori';
        $slug = $base;
        $suffix = 2;
        $database = db_connect();
        $builder = $database->table('expense_categories');
        while ($builder->where('slug', $slug)->countAllResults() > 0) { $slug = $base . '-' . $suffix++; }
        $now = date('Y-m-d H:i:s');
        $lastCode = (int) ($database->table('accounts')->selectMax('code', 'code')->where('account_type', 'expense')->get()->getRow('code') ?? 6000);
        $accountCode = (string) (max(6000, $lastCode) + 10);
        $database->transStart();
        $database->table('accounts')->insert(['code' => $accountCode, 'name' => 'Beban ' . $name, 'account_type' => 'expense', 'normal_balance' => 'debit', 'system_key' => 'expense_' . str_replace('-', '_', $slug), 'allow_manual' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $accountId = (int) $database->insertID();
        $builder->insert(['name' => $name, 'slug' => $slug, 'account_id' => $accountId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $database->transComplete();
        if (!$database->transStatus()) {
            return redirect()->route('panel.expenses')->with('error', 'Kategori dan akun bebannya belum berhasil dibuat.');
        }
        return redirect()->route('panel.expenses')->with('success', 'Kategori pengeluaran ditambahkan.');
    }

    public function toggleCategory(): RedirectResponse
    {
        $categoryId = filter_var($this->request->getPost('category_id'), FILTER_VALIDATE_INT);
        $builder = db_connect()->table('expense_categories');
        $category = $categoryId ? $builder->where('id', $categoryId)->get()->getRowArray() : null;
        if (!$category) {
            return redirect()->route('panel.expenses')->with('error', 'Kategori tidak ditemukan.');
        }
        $builder->where('id', $categoryId)->update(['is_active' => !$category['is_active'], 'updated_at' => date('Y-m-d H:i:s')]);
        return redirect()->route('panel.expenses')->with('success', $category['is_active'] ? 'Kategori dinonaktifkan.' : 'Kategori diaktifkan.');
    }

    public function evidence(int $expenseId): ResponseInterface
    {
        $expense = db_connect()->table('expenses')->where('id', $expenseId)->get()->getRowArray();
        if (!$expense || !$expense['evidence_path'] || ((session('user')['role'] ?? '') !== 'owner' && (int) $expense['created_by'] !== (int) session('user_id'))) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bukti tidak ditemukan.');
        }
        $base = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'expenses');
        $path = realpath(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $expense['evidence_path']));
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Bukti tidak ditemukan.');
        }
        return $this->response->download($path, null)->setFileName($expense['evidence_name'] ?: basename($path));
    }

    private function expensePayload(): array
    {
        return [
            'document_date' => $this->request->getPost('document_date'),
            'expense_category_id' => $this->request->getPost('expense_category_id'),
            'description' => $this->request->getPost('description'),
            'amount' => $this->request->getPost('amount'),
            'payment_source' => $this->request->getPost('payment_source'),
            'payee' => $this->request->getPost('payee'),
            'evidence' => $this->request->getFile('evidence'),
        ];
    }

    private function runAction(callable $action, string $success): RedirectResponse
    {
        try { $action(); } catch (\Throwable $exception) { return redirect()->route('panel.expenses')->with('error', $exception->getMessage()); }
        return redirect()->route('panel.expenses')->with('success', $success);
    }
}
