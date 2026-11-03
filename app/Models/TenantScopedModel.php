<?php

namespace App\Models;

use App\Libraries\Tenancy\TenantScopedBuilder;
use App\Libraries\Tenancy\TenantScopeException;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Model;
use CodeIgniter\Validation\ValidationInterface;

/**
 * Base class for every model that stores tenant-owned rows. Each query the model
 * runs goes through TenantScopedBuilder, so a feature cannot forget the scope.
 *
 * Joined tables are not scoped automatically. Join on a composite foreign key
 * that includes tenant_id, so the database guarantees both rows share a tenant.
 */
abstract class TenantScopedModel extends Model
{
    public function __construct(?ConnectionInterface $db = null, ?ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);

        if (in_array('tenant_id', $this->allowedFields, true)) {
            throw new TenantScopeException(static::class . ' must not list tenant_id in allowedFields.');
        }
    }

    /**
     * @return TenantScopedBuilder
     */
    final public function builder(?string $table = null): BaseBuilder
    {
        if ((string) $table !== '' && $table !== $this->table) {
            throw new TenantScopeException('A tenant-scoped model can only query its own table.');
        }

        if (! $this->builder instanceof TenantScopedBuilder) {
            $this->builder = new TenantScopedBuilder($this->table, $this->db, service('tenantContext'));
        }

        return $this->builder;
    }
}
