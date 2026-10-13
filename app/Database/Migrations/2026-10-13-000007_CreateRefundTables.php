<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRefundTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'refund_number' => ['type' => 'VARCHAR', 'constraint' => 50],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'shift_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['submitted', 'posted', 'rejected'], 'default' => 'submitted'],
            'method' => ['type' => 'ENUM', 'constraint' => ['cash', 'qris', 'bank_transfer', 'debit', 'credit']],
            'subtotal_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'service_charge_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 500],
            'reference_number' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'requested_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'rejected_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'rejection_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'submitted_at' => ['type' => 'DATETIME'],
            'posted_at' => ['type' => 'DATETIME', 'null' => true],
            'rejected_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('refund_number');
        $this->forge->addKey(['sale_id', 'status']);
        $this->forge->addKey(['shift_id', 'status', 'method']);
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('shift_id', 'shifts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('requested_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('rejected_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('refunds', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'refund_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'sale_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'gross_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'discount_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'tax_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'service_charge_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'refund_amount' => ['type' => 'DECIMAL', 'constraint' => '15,2'],
            'restock_decision' => ['type' => 'ENUM', 'constraint' => ['not_returned', 'consumed', 'restock'], 'default' => 'not_returned'],
            'cogs_reversed' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['refund_id', 'sale_item_id']);
        $this->forge->addKey('sale_item_id');
        $this->forge->addForeignKey('refund_id', 'refunds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sale_item_id', 'sale_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('refund_items', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('refund_items', true);
        $this->forge->dropTable('refunds', true);
    }
}
