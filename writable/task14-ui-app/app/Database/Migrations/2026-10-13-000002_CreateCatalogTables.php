<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCatalogTables extends Migration
{
    public function up(): void
    {
        $tableOptions = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sort_order' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->createTable('categories', true, $tableOptions);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'category_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 140],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 170],
            'description' => ['type' => 'TEXT', 'null' => true],
            'product_type' => ['type' => 'ENUM', 'constraint' => ['prepared', 'stocked'], 'default' => 'prepared'],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_featured' => ['type' => 'BOOLEAN', 'default' => false],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['category_id', 'is_active']);
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('products', true, $tableOptions);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'sku' => ['type' => 'VARCHAR', 'constraint' => 80],
            'price' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'stock_mode' => ['type' => 'ENUM', 'constraint' => ['recipe', 'direct', 'none'], 'default' => 'recipe'],
            'is_default' => ['type' => 'BOOLEAN', 'default' => true],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey(['product_id', 'is_active']);
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('product_variants', true, $tableOptions);
    }

    public function down(): void
    {
        $this->forge->dropTable('product_variants', true);
        $this->forge->dropTable('products', true);
        $this->forge->dropTable('categories', true);
    }
}
