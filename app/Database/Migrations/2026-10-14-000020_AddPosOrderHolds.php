<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPosOrderHolds extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'order_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'cashier_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'order_type' => ['type' => 'ENUM', 'constraint' => ['dine_in', 'takeaway'], 'default' => 'takeaway'],
            'table_number' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'customer_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'items_json' => ['type' => 'LONGTEXT'],
            'status' => ['type' => 'ENUM', 'constraint' => ['held', 'paid', 'cancelled'], 'default' => 'held'],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'paid_at' => ['type' => 'DATETIME', 'null' => true],
            'cancelled_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_number');
        $this->forge->addUniqueKey('sale_id');
        $this->forge->addKey(['cashier_id', 'status', 'updated_at']);
        $this->forge->addForeignKey('cashier_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('pos_orders', true, $options);

        $this->forge->addColumn('sales', [
            'order_type' => ['type' => 'ENUM', 'constraint' => ['dine_in', 'takeaway'], 'default' => 'takeaway', 'after' => 'cashier_id'],
            'table_number' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'order_type'],
            'customer_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'table_number'],
        ]);
        $this->forge->addColumn('sale_items', [
            'note' => ['type' => 'VARCHAR', 'constraint' => 300, 'null' => true, 'after' => 'variant_name'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('pos_orders', true);
        $this->forge->dropColumn('sale_items', 'note');
        $this->forge->dropColumn('sales', ['order_type', 'table_number', 'customer_name']);
    }
}
