<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSalePricingPolicies extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('sales', [
            'item_discount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'discount'],
            'transaction_discount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'item_discount'],
            'transaction_discount_type' => ['type' => 'ENUM', 'constraint' => ['amount', 'percent'], 'default' => 'amount', 'after' => 'transaction_discount'],
            'transaction_discount_value' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'transaction_discount_type'],
            'discount_approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'transaction_discount_value'],
        ]);
        $this->db->query('ALTER TABLE sales ADD CONSTRAINT fk_sales_discount_approved_by FOREIGN KEY (discount_approved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE');
        $this->db->query('UPDATE sales SET transaction_discount = discount WHERE transaction_discount = 0 AND discount > 0');

        $this->forge->addColumn('sale_items', [
            'item_discount_type' => ['type' => 'ENUM', 'constraint' => ['amount', 'percent'], 'default' => 'amount', 'after' => 'line_total'],
            'item_discount_value' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'item_discount_type'],
            'item_discount_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'item_discount_value'],
            'transaction_discount_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'item_discount_amount'],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'transaction_discount_amount'],
            'service_charge_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'after' => 'tax_amount'],
        ]);
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE sales DROP FOREIGN KEY fk_sales_discount_approved_by');
        $this->forge->dropColumn('sales', ['item_discount', 'transaction_discount', 'transaction_discount_type', 'transaction_discount_value', 'discount_approved_by']);
        $this->forge->dropColumn('sale_items', ['item_discount_type', 'item_discount_value', 'item_discount_amount', 'transaction_discount_amount', 'tax_amount', 'service_charge_amount']);
    }
}
