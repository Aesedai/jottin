<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The one unscoped read of users: at sign-in the tenant isn't known yet, because
 * it is derived from this row. Lookup by exact email only, and it is read-only.
 */
class UserLoginLookup extends Model
{
    protected $table         = 'users';
    protected $allowedFields = [];
    protected $returnType    = 'array';

    /** @return array{id: string, tenant_id: string, password_hash: string}|null */
    public function findByEmail(string $normalizedEmail): ?array
    {
        return $this->select('id, tenant_id, password_hash')->where('email', $normalizedEmail)->first();
    }
}
