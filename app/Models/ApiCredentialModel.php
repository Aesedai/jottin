<?php

namespace App\Models;

class ApiCredentialModel extends TenantScopedModel
{
    private const PUBLIC_COLUMNS = 'id, public_id, label, token_prefix, last_used_at, revoked_at, created_at';

    protected $table         = 'api_credentials';
    protected $allowedFields = ['public_id', 'label', 'token_prefix', 'token_hash', 'last_used_at', 'revoked_at'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /** @return list<array<string, mixed>> Newest first, and never includes the hash. */
    public function listPublic(): array
    {
        return $this->select(self::PUBLIC_COLUMNS)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(string $publicId): ?array
    {
        return $this->select(self::PUBLIC_COLUMNS)->where('public_id', $publicId)->first();
    }
}
