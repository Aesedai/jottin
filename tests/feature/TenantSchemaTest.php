<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\CleanDatabase;

/**
 * Checks the migrated schema itself, so a new table can't ship without tenant_id.
 */
final class TenantSchemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CleanDatabase;

    /** Tables that are deliberately not tenant-owned. */
    private const GLOBAL_TABLES = ['ci_sessions', 'migrations', 'tenants'];

    protected $migrate = false;
    protected $refresh = false;

    public function testForeignKeysBetweenTenantTablesIncludeTenantId(): void
    {
        $columns = $this->db->query(
            "SELECT TABLE_NAME AS tbl, CONSTRAINT_NAME AS name, COLUMN_NAME AS col, REFERENCED_TABLE_NAME AS target
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_TABLE_NAME <> 'tenants'",
            [$this->db->getDatabase()]
        )->getResultArray();

        $this->assertNotSame([], $columns);

        $byConstraint = [];

        foreach ($columns as $column) {
            $byConstraint["{$column['tbl']}.{$column['name']}"][] = $column['col'];
        }

        foreach ($byConstraint as $constraint => $names) {
            $this->assertContains('tenant_id', $names, "{$constraint} must be a composite key including tenant_id.");
        }
    }

    public function testEveryOtherTableHasARequiredTenantId(): void
    {
        $tables = $this->db->query(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$this->db->getDatabase()]
        )->getResultArray();
        $names = array_column($tables, 'name');

        $this->assertContains('tenants', $names);

        foreach (array_diff($names, self::GLOBAL_TABLES) as $table) {
            $column = $this->db->query(
                'SELECT IS_NULLABLE AS nullable FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$this->db->getDatabase(), $table, 'tenant_id']
            )->getRowArray();

            $this->assertNotNull($column, "{$table} is missing tenant_id.");
            $this->assertSame('NO', $column['nullable'], "{$table}.tenant_id must be NOT NULL.");
        }
    }
}
