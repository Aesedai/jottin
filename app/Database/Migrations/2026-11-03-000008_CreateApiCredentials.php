<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Only a SHA-256 hash of each key is stored. Keys are long and random, so the slow
 * hashing that protects human passwords isn't needed, and lookup must be by hash.
 */
class CreateApiCredentials extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE api_credentials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            public_id CHAR(24) NOT NULL,
            label VARCHAR(100) NOT NULL,
            token_prefix CHAR(13) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            last_used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY api_credentials_public_id (public_id),
            UNIQUE KEY api_credentials_hash (token_hash),
            UNIQUE KEY api_credentials_tenant_id (tenant_id, id),
            CONSTRAINT api_credentials_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ) ENGINE=InnoDB');
    }

    public function down(): void
    {
        $this->forge->dropTable('api_credentials');
    }
}
