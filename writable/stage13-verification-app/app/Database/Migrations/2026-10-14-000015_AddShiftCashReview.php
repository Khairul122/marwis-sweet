<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShiftCashReview extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('shifts', [
            'cash_review_status' => [
                'type' => 'ENUM',
                'constraint' => ['not_required', 'pending', 'approved', 'rejected'],
                'default' => 'not_required',
                'after' => 'difference_reason',
            ],
            'cash_reviewed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'cash_review_status'],
            'cash_reviewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'cash_reviewed_by'],
            'cash_review_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'cash_reviewed_at'],
        ]);
        $this->db->query('ALTER TABLE shifts ADD INDEX shifts_cash_review_status_index (cash_review_status), ADD CONSTRAINT shifts_cash_reviewed_by_fk FOREIGN KEY (cash_reviewed_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        $this->db->query("UPDATE shifts SET cash_review_status = 'pending' WHERE status = 'closed' AND cash_difference IS NOT NULL AND ABS(cash_difference) >= 0.01");
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE shifts DROP FOREIGN KEY shifts_cash_reviewed_by_fk, DROP INDEX shifts_cash_review_status_index');
        $this->forge->dropColumn('shifts', ['cash_review_status', 'cash_reviewed_by', 'cash_reviewed_at', 'cash_review_reason']);
    }
}
