<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDeliveryTables extends Migration
{
    public function up(): void
    {
        // The URL and secret are encrypted because URLs often carry tokens.
        // endpoint_host is kept readable for the UI and history.
        $this->db->query("CREATE TABLE collection_webhooks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            collection_id BIGINT UNSIGNED NOT NULL,
            mode ENUM('disabled', 'manual', 'automatic') NOT NULL DEFAULT 'disabled',
            endpoint_encrypted TEXT NOT NULL,
            endpoint_host VARCHAR(255) NOT NULL,
            secret_encrypted TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY one_webhook_per_collection (collection_id),
            CONSTRAINT collection_webhooks_collection FOREIGN KEY (tenant_id, collection_id)
                REFERENCES collections (tenant_id, id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // Per jott and collection: a jott in two collections has two independent states.
        // No row means the jott hasn't been sent to this collection.
        $this->db->query("CREATE TABLE delivery_states (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            jott_id BIGINT UNSIGNED NOT NULL,
            collection_id BIGINT UNSIGNED NOT NULL,
            state ENUM('ready', 'queued', 'sending', 'succeeded', 'failed', 'rearmed') NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY one_state (jott_id, collection_id),
            CONSTRAINT delivery_states_jott FOREIGN KEY (tenant_id, jott_id)
                REFERENCES jotts (tenant_id, id) ON DELETE CASCADE,
            CONSTRAINT delivery_states_collection FOREIGN KEY (tenant_id, collection_id)
                REFERENCES collections (tenant_id, id) ON DELETE CASCADE
        ) ENGINE=InnoDB");

        // Both the history and the durable queue: a 'queued' row commits with the jott,
        // so an automatic delivery survives a restart. jott_id has no foreign key, so
        // history outlives a deleted jott, and no note text is ever copied here.
        $this->db->query("CREATE TABLE delivery_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            public_id CHAR(24) NOT NULL,
            jott_id BIGINT UNSIGNED NOT NULL,
            jott_ref CHAR(24) NOT NULL,
            collection_id BIGINT UNSIGNED NOT NULL,
            trigger_type ENUM('manual', 'automatic') NOT NULL,
            status ENUM('queued', 'sending', 'succeeded', 'failed') NOT NULL,
            endpoint_host VARCHAR(255) NULL,
            http_status SMALLINT UNSIGNED NULL,
            error_summary VARCHAR(255) NULL,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY delivery_attempts_public_id (public_id),
            KEY delivery_attempts_queue (status, id),
            KEY delivery_attempts_history (tenant_id, collection_id, id),
            CONSTRAINT delivery_attempts_collection FOREIGN KEY (tenant_id, collection_id)
                REFERENCES collections (tenant_id, id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
    }

    public function down(): void
    {
        $this->forge->dropTable('delivery_attempts');
        $this->forge->dropTable('delivery_states');
        $this->forge->dropTable('collection_webhooks');
    }
}
