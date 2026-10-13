<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockOpnameTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'opname_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'scope' => ['type' => 'ENUM', 'constraint' => ['all', 'partial'], 'default' => 'all'],
            'status' => ['type' => 'ENUM', 'constraint' => ['counting', 'submitted', 'rejected', 'approved', 'cancelled'], 'default' => 'counting'],
            'cutoff_at' => ['type' => 'DATETIME'],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'review_notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'cancellation_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'submitted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'cancelled_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('opname_number');
        $this->forge->addKey(['status', 'cutoff_at']);
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('submitted_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('cancelled_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('stock_opnames', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'stock_opname_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'system_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'physical_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => true],
            'difference_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => true],
            'unit_cost_snapshot' => ['type' => 'DECIMAL', 'constraint' => '18,4'],
            'adjustment_unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['stock_opname_id', 'inventory_item_id']);
        $this->forge->addKey(['inventory_item_id', 'stock_opname_id']);
        $this->forge->addForeignKey('stock_opname_id', 'stock_opnames', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('stock_opname_items', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('stock_opname_items', true);
        $this->forge->dropTable('stock_opnames', true);
    }
}
