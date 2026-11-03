<?php

namespace App\Models;

use App\Libraries\Tenancy\TenantScopeException;

/**
 * Append-only here as well as in the database, so misuse fails with a clear error.
 */
class AuditLogModel extends TenantScopedModel
{
    private const RECENT_LIMIT = 200;

    protected $table         = 'audit_log';
    protected $allowedFields = [
        'actor_type', 'actor_id', 'actor_label', 'action', 'entity_type', 'entity_ref', 'detail',
    ];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $returnType    = 'array';

    /** @return list<array<string, mixed>> Newest first. */
    public function recent(): array
    {
        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll(self::RECENT_LIMIT);
    }

    public function update($id = null, $row = null): bool
    {
        throw new TenantScopeException('The audit log is append-only.');
    }

    public function delete($id = null, bool $purge = false)
    {
        throw new TenantScopeException('The audit log is append-only.');
    }
}
