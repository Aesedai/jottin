<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenants extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('tenants', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('tenants');
    }
}
