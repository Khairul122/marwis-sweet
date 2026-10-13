<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSalesTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'shift_number' => ['type' => 'VARCHAR', 'constraint' => 40],
            'cashier_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'opened_at' => ['type' => 'DATETIME'],
            'opening_cash' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'status' => ['type' => 'ENUM', 'constraint' => ['open', 'closed'], 'default' => 'open'],
            'closed_at' => ['type' => 'DATETIME', 'null' => true],
            'expected_cash' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'actual_cash' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'cash_difference' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'difference_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('shift_number');
        $this->forge->addKey(['cashier_id', 'status']);
        $this->forge->addForeignKey('cashier_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('shifts', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'shift_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'movement_type' => ['type' => 'ENUM', 'constraint' => ['in', 'out']],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['shift_id', 'created_at']);
        $this->forge->addForeignKey('shift_id', 'shifts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('cash_movements', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sale_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'shift_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'cashier_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['paid', 'partially_refunded', 'refunded', 'cancelled'], 'default' => 'paid'],
            'subtotal' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'discount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'service_charge' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'total_cogs' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'paid_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sale_number');
        $this->forge->addUniqueKey('idempotency_key');
        $this->forge->addKey(['paid_at', 'status']);
        $this->forge->addForeignKey('shift_id', 'shifts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('cashier_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('sales', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'product_variant_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'product_name' => ['type' => 'VARCHAR', 'constraint' => 140],
            'variant_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 80],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'unit_price' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'line_total' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'unit_cogs' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'default' => 0],
            'line_cogs' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'recipe_snapshot' => ['type' => 'LONGTEXT', 'null' => true],
            'topping_snapshot' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['sale_id', 'product_variant_id']);
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('product_variant_id', 'product_variants', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('sale_items', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'method' => ['type' => 'ENUM', 'constraint' => ['cash', 'qris', 'bank_transfer', 'debit', 'credit']],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'cash_received' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'change_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['sale_id', 'method']);
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('payments', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('payments', true);
        $this->forge->dropTable('sale_items', true);
        $this->forge->dropTable('sales', true);
        $this->forge->dropTable('cash_movements', true);
        $this->forge->dropTable('shifts', true);
    }
}
