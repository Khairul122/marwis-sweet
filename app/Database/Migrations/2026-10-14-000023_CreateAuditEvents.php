<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'actor_user_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'object_type' => ['type' => 'VARCHAR', 'constraint' => 80],
            'object_id' => ['type' => 'VARCHAR', 'constraint' => 120],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'before_values' => ['type' => 'JSON', 'null' => true],
            'after_values' => ['type' => 'JSON', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['created_at', 'id']);
        $this->forge->addKey(['object_type', 'object_id', 'created_at']);
        $this->forge->addKey(['actor_user_id', 'created_at']);
        $this->forge->addKey(['event_type', 'created_at']);
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('audit_events', true, ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        $this->db->query("CREATE TRIGGER audit_events_block_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit events are immutable'");
        $this->db->query("CREATE TRIGGER audit_events_block_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit events are immutable'");
    }

    public function down(): void
    {
        $this->db->query('DROP TRIGGER IF EXISTS audit_events_block_update');
        $this->db->query('DROP TRIGGER IF EXISTS audit_events_block_delete');
        $this->forge->dropTable('audit_events', true);
    }
}
