<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Models\UserModel;

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

        (new UserModel())->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'cashier',
            'status' => 'active',
        ]);

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
        (new UserModel())->update($cashier['id'], [
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
        ]);

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
        (new UserModel())->update($cashier['id'], ['status' => $newStatus]);

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
            return redirect()->route('panel.users')->with('error', 'Password baru harus berisi 12 sampai 255 karakter.');
        }

        (new UserModel())->update($cashier['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);

        return redirect()->route('panel.users')->with('success', 'Password akun kasir berhasil direset.');
    }
}
