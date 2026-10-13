<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryOperationTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 80],
            'supplier_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'document_date' => ['type' => 'DATE'],
            'payment_source' => ['type' => 'ENUM', 'constraint' => ['cash', 'bank', 'payable'], 'default' => 'cash'],
            'status' => ['type' => 'ENUM', 'constraint' => ['draft', 'posted', 'cancelled'], 'default' => 'draft'],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'posted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'posted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('reference_number');
        $this->forge->addKey(['status', 'document_date']);
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('posted_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('goods_receipts', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'goods_receipt_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'purchase_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'purchase_unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'factor_to_base' => ['type' => 'DECIMAL', 'constraint' => '18,6'],
            'base_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'purchase_unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4'],
            'base_unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4'],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['goods_receipt_id', 'inventory_item_id']);
        $this->forge->addForeignKey('goods_receipt_id', 'goods_receipts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('goods_receipt_items', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'unit_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4'],
            'total_cost' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'movement_type' => ['type' => 'ENUM', 'constraint' => ['opening', 'receipt', 'sale', 'return', 'damage', 'opname', 'correction']],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'source_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'posted_at' => ['type' => 'DATETIME'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['source_type', 'source_id', 'inventory_item_id', 'movement_type'], 'stock_movement_source_unique');
        $this->forge->addKey(['inventory_item_id', 'posted_at']);
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('stock_movements', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('stock_movements', true);
        $this->forge->dropTable('goods_receipt_items', true);
        $this->forge->dropTable('goods_receipts', true);
    }
}
