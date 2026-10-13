<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInventoryValueAndDirectLineCost extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('inventory_items', [
            'inventory_value' => [
                'type' => 'DECIMAL',
                'constraint' => '18,2',
                'default' => 0,
                'after' => 'average_cost',
            ],
        ]);
        $this->db->query('UPDATE inventory_items SET inventory_value = ROUND(quantity_on_hand * average_cost, 2)');

        $this->forge->addColumn('sale_items', [
            'direct_line_cogs' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'default' => 0,
                'after' => 'direct_unit_cogs',
            ],
        ]);
        $this->db->query('UPDATE sale_items SET direct_line_cogs = ROUND(direct_unit_cogs * quantity, 2) WHERE direct_inventory_item_id IS NOT NULL');
    }

    public function down(): void
    {
        $this->forge->dropColumn('sale_items', 'direct_line_cogs');
        $this->forge->dropColumn('inventory_items', 'inventory_value');
    }
}
