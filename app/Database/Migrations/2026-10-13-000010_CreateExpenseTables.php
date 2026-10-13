<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExpenseTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 120],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'name']);
        $this->forge->createTable('expense_categories', true, $options);

        $now = date('Y-m-d H:i:s');
        $this->db->table('expense_categories')->insertBatch(array_map(static fn (array $category): array => [
            'name' => $category[0], 'slug' => $category[1], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], [
            ['Sewa', 'sewa'], ['Listrik dan air', 'listrik-air'], ['Gas', 'gas'], ['Transportasi', 'transportasi'],
            ['Kebersihan', 'kebersihan'], ['Pemasaran', 'pemasaran'], ['Lain-lain', 'lain-lain'],
        ]));

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'expense_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'document_date' => ['type' => 'DATE'],
            'posting_date' => ['type' => 'DATE', 'null' => true],
            'expense_category_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'payment_source' => ['type' => 'ENUM', 'constraint' => ['cash_drawer', 'main_cash', 'bank']],
            'shift_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'payee' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'evidence_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'evidence_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'evidence_mime' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['draft', 'submitted', 'approved', 'paid', 'rejected', 'cancelled'], 'default' => 'draft'],
            'review_notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'submitted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'paid_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'cancelled_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('expense_number');
        $this->forge->addKey(['status', 'document_date']);
        $this->forge->addKey(['shift_id', 'status']);
        $this->forge->addForeignKey('expense_category_id', 'expense_categories', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('shift_id', 'shifts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('submitted_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('paid_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('cancelled_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('expenses', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('expenses', true);
        $this->forge->dropTable('expense_categories', true);
    }
}
