<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOpnameSuggestions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'inventory_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'reason' => ['type' => 'ENUM', 'constraint' => ['low_stock', 'activity', 'stale']],
            'detail' => ['type' => 'VARCHAR', 'constraint' => 300],
            'status' => ['type' => 'ENUM', 'constraint' => ['open', 'dismissed', 'started'], 'default' => 'open'],
            'stock_opname_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'resolved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'resolved_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['inventory_item_id', 'status']);
        $this->forge->addForeignKey('inventory_item_id', 'inventory_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('stock_opname_id', 'stock_opnames', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('opname_suggestions');
    }

    public function down(): void
    {
        $this->forge->dropTable('opname_suggestions', true);
    }
}
