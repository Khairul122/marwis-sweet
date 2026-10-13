<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use LogicException;

class DevelopmentAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new LogicException('DevelopmentAccountSeeder tidak boleh dijalankan di lingkungan production.');
        }

        $accounts = [
            [
                'name' => 'Owner Demo',
                'username' => 'owner',
                'email' => 'owner.demo@localhost.test',
                'password' => 'DemoOwner!2026',
                'role' => 'owner',
            ],
            [
                'name' => 'Kasir Demo',
                'username' => 'kasir',
                'email' => 'kasir.demo@localhost.test',
                'password' => 'DemoKasir!2026',
                'role' => 'cashier',
            ],
        ];

        $users = $this->db->table('users');
        $now = date('Y-m-d H:i:s');
        foreach ($accounts as $account) {
            $existing = $users->where('username', $account['username'])->get()->getRowArray();
            $payload = [
                'name' => $account['name'],
                'email' => $account['email'],
                'password_hash' => password_hash($account['password'], PASSWORD_DEFAULT),
                'role' => $account['role'],
                'status' => 'active',
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $payload['username'] = $account['username'];
                $payload['created_at'] = $now;
                $users->insert($payload);
                continue;
            }

            $users->where('id', $existing['id'])->update($payload);
        }
    }
}
