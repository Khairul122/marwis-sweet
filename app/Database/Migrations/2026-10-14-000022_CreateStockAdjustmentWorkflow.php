<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockAdjustmentWorkflow extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 80],
            'adjustment_type' => ['type' => 'ENUM', 'constraint' => ['opening', 'damage', 'correction', 'reversal']],
            'status' => ['type' => 'ENUM', 'constraint' => ['draft', 'submitted', 'approved', 'rejected', 'posted', 'cancelled'], 'default' => 'draft'],
            'document_date' => ['type' => 'DATE'],
            'offset_account_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reversal_of_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 500],
            'review_notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'submitted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reviewed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'posted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'posted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('reference_number');
        $this->forge->addUniqueKey('reversal_of_id');
        $this->forge->addKey(['status', 'document_date']);
        $this->forge->addForeignKey('offset_account_id', 'accounts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('reversal_of_id', 'stock_adjustments', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('submitted_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('posted_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('stock_adjustments', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'stock_adjustment_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'base_unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'quantity_delta' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'value_delta' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'expected_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => true],
            'expected_inventory_value' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['stock_adjustment_id', 'inventory_item_id']);
        $this->forge->addKey(['inventory_item_id', 'stock_adjustment_id']);
        $this->forge->addForeignKey('stock_adjustment_id', 'stock_adjustments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('stock_adjustment_items', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('stock_adjustment_items', true);
        $this->forge->dropTable('stock_adjustments', true);
    }
}
