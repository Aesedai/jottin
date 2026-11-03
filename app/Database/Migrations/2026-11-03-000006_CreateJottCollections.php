<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per jott and collection, with each reason for membership kept separately.
 * Composite foreign keys make a membership across two tenants impossible in MySQL.
 */
class CreateJottCollections extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE jott_collections (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            jott_id BIGINT UNSIGNED NOT NULL,
            collection_id BIGINT UNSIGNED NOT NULL,
            is_automatic TINYINT(1) NOT NULL DEFAULT 0,
            is_manual TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY jott_collection (jott_id, collection_id),
            CONSTRAINT jott_collections_jott FOREIGN KEY (tenant_id, jott_id)
                REFERENCES jotts (tenant_id, id) ON DELETE CASCADE,
            CONSTRAINT jott_collections_collection FOREIGN KEY (tenant_id, collection_id)
                REFERENCES collections (tenant_id, id) ON DELETE CASCADE,
            CONSTRAINT jott_collections_has_reason CHECK (is_automatic = 1 OR is_manual = 1)
        ) ENGINE=InnoDB');
    }

    public function down(): void
    {
        $this->forge->dropTable('jott_collections');
    }
}
