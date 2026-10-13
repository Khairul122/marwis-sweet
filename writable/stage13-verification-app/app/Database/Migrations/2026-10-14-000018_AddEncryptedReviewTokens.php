<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEncryptedReviewTokens extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('review_tokens', [
            'encrypted_token' => ['type' => 'TEXT', 'null' => true, 'after' => 'token_hash'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('review_tokens', 'encrypted_token');
    }
}
