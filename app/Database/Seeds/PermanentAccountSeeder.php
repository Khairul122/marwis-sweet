<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use InvalidArgumentException;

class PermanentAccountSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccount('owner', true);
        $this->seedAccount('cashier', false);
    }

    private function seedAccount(string $role, bool $required): void
    {
        $prefix = 'SEED_' . strtoupper($role) . '_';
        $values = [
            'name' => trim((string) env($prefix . 'NAME', '')),
            'username' => trim((string) env($prefix . 'USERNAME', '')),
            'email' => trim((string) env($prefix . 'EMAIL', '')),
            'password' => (string) env($prefix . 'PASSWORD', ''),
        ];

        $provided = array_filter($values, static fn (string $value): bool => $value !== '');
        if ($provided === [] && !$required) {
            return;
        }

        if ($values['name'] === '' || $values['username'] === '' || strlen($values['password']) < 12) {
            throw new InvalidArgumentException($prefix . 'NAME dan ' . $prefix . 'USERNAME wajib diisi; password minimal 12 karakter.');
        }

        if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException($prefix . 'EMAIL tidak valid.');
        }

        $users = $this->db->table('users');
        $existing = $users->where('username', $values['username'])->get()->getRowArray();
        $emailOwner = $values['email'] === '' ? null : $users->where('email', $values['email'])->get()->getRowArray();

        if ($emailOwner !== null && (int) $emailOwner['id'] !== (int) ($existing['id'] ?? 0)) {
            throw new InvalidArgumentException('Email untuk akun ' . $role . ' sudah digunakan akun lain.');
        }

        $now = date('Y-m-d H:i:s');
        $payload = [
            'name' => $values['name'],
            'username' => $values['username'],
            'email' => $values['email'] === '' ? null : $values['email'],
            'password_hash' => password_hash($values['password'], PASSWORD_DEFAULT),
            'role' => $role,
            'status' => 'active',
            'updated_at' => $now,
        ];

        if ($existing === null) {
            $payload['created_at'] = $now;
            $users->insert($payload);
            return;
        }

        $users->where('id', $existing['id'])->update($payload);
    }
}
