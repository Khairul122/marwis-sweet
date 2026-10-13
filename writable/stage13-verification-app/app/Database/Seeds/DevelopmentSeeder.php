<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $hasPermanentOwner = trim((string) env('SEED_OWNER_NAME', '')) !== ''
            && trim((string) env('SEED_OWNER_USERNAME', '')) !== ''
            && strlen((string) env('SEED_OWNER_PASSWORD', '')) >= 12;

        $this->call($hasPermanentOwner ? PermanentAccountSeeder::class : DevelopmentAccountSeeder::class);
        $this->call(TemporaryDataSeeder::class);
    }
}
