<?php

namespace App\Models;

class UserModel extends TenantScopedModel
{
    public const ROLE_OWNER  = 'owner';
    public const ROLE_MEMBER = 'member';

    /** Everything safe to hold in memory or show; deliberately excludes password_hash. */
    private const PUBLIC_COLUMNS = 'id, tenant_id, name, email, role, created_at';

    protected $table         = 'users';
    protected $allowedFields = ['name', 'email', 'password_hash', 'role'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /** @return array<string, mixed>|null */
    public function findPublic(int $id): ?array
    {
        return $this->select(self::PUBLIC_COLUMNS)->where('id', $id)->first();
    }

    /** @return list<array<string, mixed>> */
    public function listPublic(): array
    {
        return $this->select(self::PUBLIC_COLUMNS)->orderBy('created_at', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    public function passwordHash(int $id): ?string
    {
        $row = $this->select('password_hash')->where('id', $id)->first();

        return $row['password_hash'] ?? null;
    }
}
