<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sessions live in MySQL so they survive restarts and work across containers.
 */
class CreateSessions extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE TABLE ci_sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            data BLOB NOT NULL,
            KEY ci_sessions_timestamp (timestamp)
        ) ENGINE=InnoDB');
    }

    public function down(): void
    {
        $this->forge->dropTable('ci_sessions');
    }
}
