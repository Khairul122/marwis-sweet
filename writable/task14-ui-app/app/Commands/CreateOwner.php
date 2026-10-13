<?php

namespace App\Commands;

use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CreateOwner extends BaseCommand
{
    protected $group = 'Setup';
    protected $name = 'owner:create';
    protected $description = 'Creates the first owner account during controlled installation.';

    public function run(array $params)
    {
        $users = new UserModel();
        if ($users->where('role', 'owner')->countAllResults() > 0) {
            CLI::error('Owner sudah tersedia. Gunakan panel untuk mengelola akun berikutnya.');
            return EXIT_ERROR;
        }

        $name = trim((string) env('BOOTSTRAP_OWNER_NAME', ''));
        $username = trim((string) env('BOOTSTRAP_OWNER_USERNAME', ''));
        $email = trim((string) env('BOOTSTRAP_OWNER_EMAIL', ''));
        $password = (string) env('BOOTSTRAP_OWNER_PASSWORD', '');

        if ($name === '' || $username === '' || strlen($password) < 12 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            CLI::error('Input tidak valid. Nama dan username wajib, password minimal 12 karakter, email harus valid.');
            return EXIT_ERROR;
        }

        if ($users->where('username', $username)->first() || ($email !== '' && $users->where('email', $email)->first())) {
            CLI::error('Username atau email sudah digunakan.');
            return EXIT_ERROR;
        }

        $users->insert([
            'name' => $name,
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'owner',
            'status' => 'active',
        ]);

        $environmentFile = ROOTPATH . '.env';
        if (is_file($environmentFile)) {
            $contents = file_get_contents($environmentFile);
            if ($contents === false) {
                CLI::error('Akun dibuat, tetapi .env tidak dapat dibaca. Hapus empat baris BOOTSTRAP_OWNER_* secara manual.');
                return EXIT_ERROR;
            }
            $contents = preg_replace('/^BOOTSTRAP_OWNER_(?:NAME|USERNAME|EMAIL|PASSWORD)\s*=.*(?:\R|$)/m', '', $contents);
            if (file_put_contents($environmentFile, $contents, LOCK_EX) === false) {
                CLI::error('Akun dibuat, tetapi kredensial bootstrap gagal dibersihkan dari .env. Hapus empat baris BOOTSTRAP_OWNER_* secara manual.');
                return EXIT_ERROR;
            }
        }

        CLI::write('Akun owner berhasil dibuat.', 'green');
        return EXIT_SUCCESS;
    }
}
