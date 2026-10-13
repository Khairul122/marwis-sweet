<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDirectInventoryMapping extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE product_variants ADD direct_inventory_item_id BIGINT UNSIGNED NULL AFTER stock_mode, ADD INDEX product_variant_direct_item_index (direct_inventory_item_id), ADD CONSTRAINT product_variants_direct_item_fk FOREIGN KEY (direct_inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        $this->db->query('ALTER TABLE sale_items ADD direct_inventory_item_id BIGINT UNSIGNED NULL AFTER product_variant_id, ADD INDEX sale_item_direct_item_index (direct_inventory_item_id), ADD CONSTRAINT sale_items_direct_item_fk FOREIGN KEY (direct_inventory_item_id) REFERENCES inventory_items(id) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE sale_items DROP FOREIGN KEY sale_items_direct_item_fk, DROP INDEX sale_item_direct_item_index, DROP COLUMN direct_inventory_item_id');
        $this->db->query('ALTER TABLE product_variants DROP FOREIGN KEY product_variants_direct_item_fk, DROP INDEX product_variant_direct_item_index, DROP COLUMN direct_inventory_item_id');
    }
}
