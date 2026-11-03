<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJotts extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE jotts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            public_id CHAR(24) NOT NULL,
            text TEXT NOT NULL,
            source ENUM('web', 'api') NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY jotts_public_id (public_id),
            UNIQUE KEY jotts_tenant_id (tenant_id, id),
            KEY jotts_feed (tenant_id, created_at, id),
            CONSTRAINT jotts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ) ENGINE=InnoDB");
    }

    public function down(): void
    {
        $this->forge->dropTable('jotts');
    }
}
