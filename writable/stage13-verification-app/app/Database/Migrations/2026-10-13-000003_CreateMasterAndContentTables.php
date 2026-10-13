<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMasterAndContentTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 140],
            'contact_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'address' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_active', 'name']);
        $this->forge->createTable('suppliers', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 140],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 80],
            'base_unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'quantity_on_hand' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'average_cost' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'minimum_stock' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey(['is_active', 'name']);
        $this->forge->createTable('inventory_items', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'purchase_unit' => ['type' => 'VARCHAR', 'constraint' => 30],
            'factor_to_base' => ['type' => 'DECIMAL', 'constraint' => '18,6'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['inventory_item_id', 'purchase_unit']);
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('unit_conversions', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_variant_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['product_variant_id', 'version']);
        $this->forge->addKey(['product_variant_id', 'is_active']);
        $this->forge->addForeignKey('product_variant_id', 'product_variants', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('recipes', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'recipe_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['recipe_id', 'inventory_item_id']);
        $this->forge->addForeignKey('recipe_id', 'recipes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('recipe_items', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'alt_text' => ['type' => 'VARCHAR', 'constraint' => 180],
            'sort_order' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'sort_order']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('product_images', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 140],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'alt_text' => ['type' => 'VARCHAR', 'constraint' => 180],
            'link_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'sort_order' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'starts_at' => ['type' => 'DATETIME', 'null' => true],
            'ends_at' => ['type' => 'DATETIME', 'null' => true],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['is_active', 'sort_order', 'starts_at', 'ends_at']);
        $this->forge->createTable('banners', true, $options);

        $this->forge->addField([
            'setting_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('setting_key', true);
        $this->forge->createTable('settings', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('settings', true);
        $this->forge->dropTable('banners', true);
        $this->forge->dropTable('product_images', true);
        $this->forge->dropTable('recipe_items', true);
        $this->forge->dropTable('recipes', true);
        $this->forge->dropTable('unit_conversions', true);
        $this->forge->dropTable('inventory_items', true);
        $this->forge->dropTable('suppliers', true);
    }
}
