<?php

namespace App\Libraries\Auth;

use App\Libraries\Tenancy\TenantContext;
use App\Models\UserLoginLookup;
use App\Models\UserModel;

final class Authenticator
{
    /**
     * Verified when the email is unknown, so response time doesn't reveal which
     * emails have accounts. Its plaintext was random and discarded.
     */
    private const DUMMY_HASH = '$2y$10$OjePWr91Ru4Ww2nhdHRISOARo/M68iX0ARJXLDstJssNuG47QFUpu';

    public function __construct(
        private readonly TenantContext $context,
        private readonly UserLoginLookup $lookup = new UserLoginLookup(),
        private readonly UserModel $users = new UserModel(),
    ) {
    }

    /**
     * @return array{tenant_id: int, user_id: int}|null Null for any failure, with no hint why.
     */
    public function attempt(string $email, string $password): ?array
    {
        $row = $this->lookup->findByEmail(AccountService::normalizeEmail($email));

        if (! password_verify($password, $row['password_hash'] ?? self::DUMMY_HASH) || $row === null) {
            return null;
        }

        $tenantId = (int) $row['tenant_id'];
        $userId   = (int) $row['id'];

        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $this->context->runAs($tenantId, fn () => $this->users->update($userId, [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]));
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }
}
