<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuditLogService;

class UsersController extends BaseController
{
    public function index(): string
    {
        return view('panel/users/index', [
            'title' => 'Akun kasir',
            'user' => session('user'),
            'cashiers' => (new UserModel())->where('role', 'cashier')->orderBy('name')->findAll(),
        ]);
    }

    public function create(): \CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[120]',
            'username' => 'required|alpha_numeric_punct|min_length[3]|max_length[80]|is_unique[users.username]',
            'email' => 'permit_empty|valid_email|max_length[190]|is_unique[users.email]',
            'password' => 'required|min_length[12]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'cashier',
            'status' => 'active',
        ];
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            $model = new UserModel();
            $model->insert($data);
            $cashierId = (int) $database->insertID();
            (new AuditLogService($database))->record((int) session('user_id'), 'account.cashier.created', 'user', $cashierId, 'Akun kasir dibuat.', [], [
                'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'role' => 'cashier', 'status' => 'active',
            ]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->back()->withInput()->with('error', 'Akun kasir belum dapat dibuat.');
        }

        return redirect()->route('panel.users')->with('success', 'Akun kasir berhasil dibuat.');
    }

    public function update(): \CodeIgniter\HTTP\RedirectResponse
    {
        $userId = filter_var($this->request->getPost('user_id'), FILTER_VALIDATE_INT);
        $cashier = $userId ? (new UserModel())->where('role', 'cashier')->find($userId) : null;
        if (!$cashier) {
            return redirect()->route('panel.users')->with('error', 'Akun kasir tidak ditemukan.');
        }
        $rules = [
            'name' => 'required|min_length[2]|max_length[120]',
            'username' => 'required|alpha_numeric_punct|min_length[3]|max_length[80]|is_unique[users.username,id,' . $cashier['id'] . ']',
            'email' => 'permit_empty|valid_email|max_length[190]|is_unique[users.email,id,' . $cashier['id'] . ']',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
        ];
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            (new UserModel())->update($cashier['id'], $data);
            (new AuditLogService($database))->record((int) session('user_id'), 'account.cashier.updated', 'user', $cashier['id'], 'Identitas akun kasir diperbarui.', [
                'name' => $cashier['name'], 'username' => $cashier['username'], 'email' => $cashier['email'],
            ], $data);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.users')->with('error', 'Akun kasir belum dapat diperbarui.');
        }

        return redirect()->route('panel.users')->with('success', 'Akun kasir berhasil diperbarui.');
    }

    public function status(): \CodeIgniter\HTTP\RedirectResponse
    {
        $userId = filter_var($this->request->getPost('user_id'), FILTER_VALIDATE_INT);
        $cashier = $userId ? (new UserModel())->where('role', 'cashier')->find($userId) : null;
        if (!$cashier) {
            return redirect()->route('panel.users')->with('error', 'Akun kasir tidak ditemukan.');
        }

        $newStatus = $cashier['status'] === 'active' ? 'inactive' : 'active';
        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            (new UserModel())->update($cashier['id'], ['status' => $newStatus]);
            (new AuditLogService($database))->record((int) session('user_id'), 'account.cashier.status_changed', 'user', $cashier['id'], null, ['status' => $cashier['status']], ['status' => $newStatus]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.users')->with('error', 'Status akun kasir belum dapat diubah.');
        }

        return redirect()->route('panel.users')->with('success', $newStatus === 'active' ? 'Akun kasir diaktifkan.' : 'Akun kasir dinonaktifkan.');
    }

    public function resetPassword(): \CodeIgniter\HTTP\RedirectResponse
    {
        $userId = filter_var($this->request->getPost('user_id'), FILTER_VALIDATE_INT);
        $cashier = $userId ? (new UserModel())->where('role', 'cashier')->find($userId) : null;
        if (!$cashier) {
            return redirect()->route('panel.users')->with('error', 'Akun kasir tidak ditemukan.');
        }

        $password = (string) $this->request->getPost('password');
        if (strlen($password) < 12 || strlen($password) > 255) {
            return redirect()->back()->with('error', 'Password baru harus berisi 12 sampai 255 karakter.');
        }

        $database = db_connect();
        try {
            $database->transException(true)->transStart();
            (new UserModel())->update($cashier['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            (new AuditLogService($database))->record((int) session('user_id'), 'account.cashier.password_reset', 'user', $cashier['id'], 'Kata sandi direset oleh owner.', ['password_changed' => false], ['password_changed' => true]);
            $database->transComplete();
        } catch (\Throwable $exception) {
            $database->transRollback();
            return redirect()->route('panel.users')->with('error', 'Password kasir belum dapat direset.');
        }

        return redirect()->route('panel.users')->with('success', 'Password akun kasir berhasil direset.');
    }
}
