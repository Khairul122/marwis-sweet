<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDirectUnitCogsToSaleItems extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sale_items', [
            'direct_unit_cogs' => [
                'type' => 'DECIMAL',
                'constraint' => '15,4',
                'default' => 0,
                'after' => 'unit_cogs',
            ],
        ]);
        $this->db->query("UPDATE sale_items si JOIN (SELECT source_id AS sale_id, inventory_item_id, MAX(unit_cost) AS unit_cost FROM stock_movements WHERE source_type = 'sale' AND movement_type = 'sale' GROUP BY source_id, inventory_item_id) sm ON sm.sale_id = si.sale_id AND sm.inventory_item_id = si.direct_inventory_item_id SET si.direct_unit_cogs = sm.unit_cost WHERE si.direct_inventory_item_id IS NOT NULL");
    }

    public function down(): void
    {
        $this->forge->dropColumn('sale_items', 'direct_unit_cogs');
    }
}
