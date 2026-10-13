<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateToppingTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 80],
            'additional_price' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey(['is_active', 'name']);
        $this->forge->createTable('toppings', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'topping_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['topping_id', 'inventory_item_id']);
        $this->forge->addForeignKey('topping_id', 'toppings', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('topping_recipe_items', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('topping_recipe_items', true);
        $this->forge->dropTable('toppings', true);
    }
}
