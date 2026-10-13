<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGoodsReceiptApproval extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE goods_receipts
            MODIFY status ENUM('draft', 'submitted', 'approved', 'rejected', 'posted', 'cancelled') NOT NULL DEFAULT 'draft',
            ADD COLUMN submitted_by BIGINT UNSIGNED NULL AFTER created_by,
            ADD COLUMN submitted_at DATETIME NULL AFTER submitted_by,
            ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER submitted_at,
            ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by,
            ADD COLUMN review_notes VARCHAR(500) NULL AFTER reviewed_at,
            ADD INDEX goods_receipts_approval_index (status, submitted_at),
            ADD CONSTRAINT goods_receipts_submitted_by_fk FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD CONSTRAINT goods_receipts_reviewed_by_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE");
    }

    public function down(): void
    {
        $this->db->query("UPDATE goods_receipts SET status = 'draft' WHERE status IN ('submitted', 'approved', 'rejected')");
        $this->db->query("ALTER TABLE goods_receipts
            DROP FOREIGN KEY goods_receipts_submitted_by_fk,
            DROP FOREIGN KEY goods_receipts_reviewed_by_fk,
            DROP INDEX goods_receipts_approval_index,
            DROP COLUMN submitted_by,
            DROP COLUMN submitted_at,
            DROP COLUMN reviewed_by,
            DROP COLUMN reviewed_at,
            DROP COLUMN review_notes,
            MODIFY status ENUM('draft', 'posted', 'cancelled') NOT NULL DEFAULT 'draft'");
    }
}
