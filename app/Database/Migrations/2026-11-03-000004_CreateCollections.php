<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCollections extends Migration
{
    public function up(): void
    {
        // tag_key uses a binary collation so uniqueness means exactly the normalized
        // key. The default collation would treat #cafe and #café as the same tag.
        $this->db->query('CREATE TABLE collections (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            public_id CHAR(24) NOT NULL,
            name VARCHAR(100) NOT NULL,
            tag_key VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY collections_public_id (public_id),
            UNIQUE KEY collections_tag (tenant_id, tag_key),
            UNIQUE KEY collections_tenant_id (tenant_id, id),
            CONSTRAINT collections_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ) ENGINE=InnoDB');
    }

    public function down(): void
    {
        $this->forge->dropTable('collections');
    }
}
