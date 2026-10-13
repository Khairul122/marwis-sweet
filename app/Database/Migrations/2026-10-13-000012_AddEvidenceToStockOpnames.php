<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEvidenceToStockOpnames extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('stock_opnames', [
            'evidence_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'notes'],
            'evidence_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'evidence_path'],
            'evidence_mime' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'evidence_name'],
            'evidence_uploaded_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'evidence_mime'],
            'evidence_uploaded_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'evidence_uploaded_by'],
        ]);
        $this->db->query('ALTER TABLE stock_opnames ADD INDEX stock_opnames_evidence_uploader_index (evidence_uploaded_by), ADD CONSTRAINT stock_opnames_evidence_uploader_fk FOREIGN KEY (evidence_uploaded_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE stock_opnames DROP FOREIGN KEY stock_opnames_evidence_uploader_fk, DROP INDEX stock_opnames_evidence_uploader_index');
        $this->forge->dropColumn('stock_opnames', ['evidence_path', 'evidence_name', 'evidence_mime', 'evidence_uploaded_by', 'evidence_uploaded_at']);
    }
}
