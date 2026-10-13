<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReviewTables extends Migration
{
    public function up(): void
    {
        $options = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sale_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'disabled_at' => ['type' => 'DATETIME', 'null' => true],
            'disabled_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey('token_hash'); $this->forge->addKey(['sale_id', 'expires_at']);
        $this->forge->addForeignKey('sale_id', 'sales', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('review_tokens', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'review_token_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'sale_item_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'display_name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'rating' => ['type' => 'TINYINT', 'unsigned' => true],
            'comment' => ['type' => 'TEXT'],
            'status' => ['type' => 'ENUM', 'constraint' => ['pending', 'approved', 'rejected', 'hidden'], 'default' => 'pending'],
            'moderation_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'moderated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'moderated_at' => ['type' => 'DATETIME', 'null' => true],
            'submitted_ip_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'sale_fully_refunded' => ['type' => 'BOOLEAN', 'default' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey(['review_token_id', 'sale_item_id']); $this->forge->addKey(['product_id', 'status', 'created_at']);
        $this->forge->addForeignKey('review_token_id', 'review_tokens', 'id', 'RESTRICT', 'CASCADE'); $this->forge->addForeignKey('sale_item_id', 'sale_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE'); $this->forge->addForeignKey('moderated_by', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('product_reviews', true, $options);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_review_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true); $this->forge->addKey('product_review_id');
        $this->forge->addForeignKey('product_review_id', 'product_reviews', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('review_images', true, $options);
    }

    public function down(): void
    {
        $this->forge->dropTable('review_images', true); $this->forge->dropTable('product_reviews', true); $this->forge->dropTable('review_tokens', true);
    }
}
