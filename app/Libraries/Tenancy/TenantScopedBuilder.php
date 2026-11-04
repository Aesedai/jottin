<?php

namespace App\Libraries\Tenancy;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\MySQLi\Builder;

/**
 * Query builder that cannot produce SQL outside the current tenant.
 *
 * The scope is added when SQL is compiled, not as an ordinary where() call:
 *  - Caller conditions are wrapped in parentheses, so orWhere() cannot escape:
 *    WHERE t.tenant_id = 1 AND (id = 1 OR id = 2)
 *  - resetQuery() and the builder's own resets cannot remove it.
 *
 * Operations that do not pass through that compile step throw instead.
 */
final class TenantScopedBuilder extends Builder
{
    private const COLUMN = 'tenant_id';

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(
        private readonly string $scopedTable,
        ConnectionInterface $db,
        private readonly TenantContext $context,
        ?array $options = null,
    ) {
        parent::__construct($scopedTable, $db, $options);
    }

    protected function compileWhereHaving(string $qbKey): string
    {
        $sql = parent::compileWhereHaving($qbKey);

        if ($qbKey !== 'QBWhere') {
            return $sql;
        }

        $scope = $this->db->escapeIdentifiers($this->scopedTable) . '.'
            . $this->db->escapeIdentifiers(self::COLUMN) . ' = ' . $this->context->id();

        if ($sql === '') {
            return "\nWHERE {$scope}";
        }

        return "\nWHERE {$scope} AND (" . substr($sql, strlen("\nWHERE ")) . ')';
    }

    public function insert($set = null, ?bool $escape = null)
    {
        if ($set !== null) {
            $this->set($set, '', $escape);
        }

        $this->assertTenantColumnNotSet();
        $this->set(self::COLUMN, $this->context->id());

        return parent::insert(null, $escape);
    }

    public function update($set = null, $where = null, ?int $limit = null): bool
    {
        if ($set !== null) {
            $this->set($set);
        }

        $this->assertTenantColumnNotSet();

        return parent::update(null, $where, $limit);
    }

    /**
     * The framework calls this to restore the table after each query, so the
     * model's own table stays allowed. Any other table is refused.
     */
    public function from($from, bool $overwrite = false): self
    {
        if (! is_string($from) || trim($from, $this->db->escapeChar) !== $this->scopedTable) {
            $this->refuse('from');
        }

        parent::from($from, $overwrite);

        return $this;
    }

    /**
     * The scope only covers this builder's own table, so a joined table has to be held
     * to the same tenant in the join itself. This catches mistakes in application code.
     * It reads the condition's text, so it is not a SQL parser.
     *
     * @param mixed $cond
     *
     * @return $this
     */
    public function join(string $table, $cond, string $type = '', ?bool $escape = null)
    {
        $this->assertJoinKeepsTenant($table, $cond);

        return parent::join($table, $cond, $type, $escape);
    }

    public function fromSubquery(BaseBuilder $from, string $alias): never
    {
        $this->refuse('fromSubquery');
    }

    public function union($union): never
    {
        $this->refuse('union');
    }

    public function unionAll($union): never
    {
        $this->refuse('unionAll');
    }

    public function countAll(bool $reset = true): never
    {
        $this->refuse('countAll');
    }

    public function replace(?array $set = null): never
    {
        $this->refuse('replace');
    }

    public function upsert($set = null, ?bool $escape = null): never
    {
        $this->refuse('upsert');
    }

    public function upsertBatch($set = null, ?bool $escape = null, int $batchSize = 100): never
    {
        $this->refuse('upsertBatch');
    }

    public function insertBatch($set = null, ?bool $escape = null, int $batchSize = 100): never
    {
        $this->refuse('insertBatch');
    }

    public function updateBatch($set = null, $constraints = null, int $batchSize = 100): never
    {
        $this->refuse('updateBatch');
    }

    public function deleteBatch($set = null, $constraints = null, int $batchSize = 100): never
    {
        $this->refuse('deleteBatch');
    }

    public function emptyTable(): never
    {
        $this->refuse('emptyTable');
    }

    public function truncate(): never
    {
        $this->refuse('truncate');
    }

    private function assertTenantColumnNotSet(): void
    {
        foreach (array_keys($this->QBSet) as $column) {
            if (trim((string) $column, $this->db->escapeChar) === self::COLUMN) {
                throw new TenantScopeException('tenant_id is assigned by the tenant scope and cannot be set.');
            }
        }
    }

    private function assertJoinKeepsTenant(string $table, mixed $cond): void
    {
        $matched = preg_match('/^(\w+)(?:\s+(?:AS\s+)?(\w+))?$/i', trim($table), $parts);

        if ($matched !== 1 || ! is_string($cond)) {
            throw new TenantScopeException('A join must name a table and use a plain text condition.');
        }

        // An OR could match rows on its other side, whatever the tenant condition says.
        if (preg_match('/\b(?:X?OR)\b|\|\|/i', $cond) === 1) {
            throw new TenantScopeException('A join condition cannot use OR.');
        }

        $joined = preg_quote($parts[2] ?? $parts[1], '/');
        $own    = preg_quote($this->scopedTable, '/');

        $sameTenant = "/(?<![\\w.`])(?:{$joined}\\.tenant_id\\s*=\\s*{$own}\\.tenant_id"
            . "|{$own}\\.tenant_id\\s*=\\s*{$joined}\\.tenant_id)(?![\\w.])/i";

        if (preg_match($sameTenant, $cond) !== 1) {
            throw new TenantScopeException(
                "A join must match {$parts[1]}.tenant_id to {$this->scopedTable}.tenant_id."
            );
        }
    }

    private function refuse(string $operation): never
    {
        throw new TenantScopeException("{$operation}() cannot be tenant-scoped and is not allowed here.");
    }
}
