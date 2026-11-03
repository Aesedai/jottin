<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The unscoped read behind API authentication: the tenant is derived from this
 * row, so it can't be known beforehand. Exact hash match, active keys, read-only.
 */
class ApiCredentialLookup extends Model
{
    protected $table         = 'api_credentials';
    protected $allowedFields = [];
    protected $returnType    = 'array';

    /** @return array<string, mixed>|null */
    public function findActiveByHash(string $tokenHash): ?array
    {
        return $this->select('id, tenant_id, public_id, token_prefix, last_used_at')
            ->where('token_hash', $tokenHash)
            ->where('revoked_at', null)
            ->first();
    }
}
