<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only: triggers refuse UPDATE and DELETE, whichever code path tries.
 * Holds who did what to which record, never the note's text.
 */
class CreateAuditLog extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            actor_type ENUM('user', 'api', 'system') NOT NULL,
            actor_id BIGINT UNSIGNED NULL,
            actor_label VARCHAR(254) NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(30) NOT NULL,
            entity_ref VARCHAR(40) NOT NULL,
            detail JSON NULL,
            created_at DATETIME NOT NULL,
            KEY audit_log_recent (tenant_id, created_at, id),
            CONSTRAINT audit_log_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ) ENGINE=InnoDB");

        foreach (['UPDATE', 'DELETE'] as $operation) {
            $trigger = 'audit_log_no_' . strtolower($operation);
            $this->db->query("CREATE TRIGGER {$trigger} BEFORE {$operation} ON audit_log FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'");
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_log');
    }
}
