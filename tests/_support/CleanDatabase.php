<?php

namespace Tests\Support;

use Config\Services;
use RuntimeException;

/**
 * Builds the schema once per test run and empties it before each test. Rebuilding
 * it for every test took minutes. TRUNCATE also works on the audit log, because it
 * doesn't fire the triggers that protect that table in normal use.
 */
trait CleanDatabase
{
    protected function setUpCleanDatabase(): void
    {
        $this->loadDependencies();

        $database = $this->db->getDatabase();

        if (ENVIRONMENT !== 'testing' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to clear database '{$database}' outside the test environment.");
        }

        if (! SchemaState::$built) {
            $this->dropAllTables();
            Services::migrations()->setNamespace('App')->latest();
            SchemaState::$built = true;
        }

        $this->emptyAllTables();
    }

    /** @return list<string> */
    private function allTables(): array
    {
        $rows = $this->db->query(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$this->db->getDatabase()]
        )->getResultArray();

        return array_column($rows, 'name');
    }

    private function dropAllTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->allTables() as $table) {
            $this->db->query("DROP TABLE `{$table}`");
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function emptyAllTables(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->allTables() as $table) {
            if ($table !== 'migrations' && $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1")->getRow() !== null) {
                $this->db->query("TRUNCATE TABLE `{$table}`");
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}
