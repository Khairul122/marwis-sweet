<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (session('user_id')) {
            return redirect()->route('panel');
        }

        return view('auth/login', ['title' => 'Masuk']);
    }

    public function attempt(): \CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'identity' => 'required|min_length[3]|max_length[190]',
            'password' => 'required|min_length[8]|max_length[255]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $identity = trim((string) $this->request->getPost('identity'));
        $attemptKey = 'login_attempts_' . hash('sha256', strtolower($identity) . '|' . $this->request->getIPAddress());
        $attempts = (int) (cache()->get($attemptKey) ?? 0);
        if ($attempts >= 5) {
            return redirect()->route('login')->with('error', 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
        }

        $user = (new UserModel())->groupStart()
            ->where('username', $identity)
            ->orWhere('email', $identity)
            ->groupEnd()
            ->first();

        if (!$user || $user['status'] !== 'active' || !password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
            cache()->save($attemptKey, $attempts + 1, 900);
            return redirect()->route('login')->withInput()->with('error', 'Username/email atau password tidak sesuai.');
        }

        cache()->delete($attemptKey);
        $intended = session()->get('login_intended');
        session()->remove('login_intended');
        session()->regenerate(true);
        session()->set(['user_id' => $user['id']]);
        (new UserModel())->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        $destination = is_string($intended) && preg_match('#^panel(/[A-Za-z0-9/_.\-]*)?(\?[^\r\n]*)?$#D', $intended)
            ? $intended : 'panel';

        return redirect()->to(site_url($destination))->with('success', 'Selamat datang, ' . $user['name'] . '.');
    }

    public function logout(): \CodeIgniter\HTTP\RedirectResponse
    {
        session()->destroy();
        return redirect()->route('login')->with('success', 'Anda telah keluar.');
    }
}
