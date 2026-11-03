<?php

namespace App\Libraries\Auth;

use App\Libraries\Tenancy\TenantContext;
use App\Libraries\Transaction;
use App\Models\TenantModel;
use App\Models\UserLoginLookup;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

final class AccountService
{
    private const MYSQL_DUPLICATE_KEY = 1062;

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantModel $tenants = new TenantModel(),
        private readonly UserModel $users = new UserModel(),
        private readonly UserLoginLookup $lookup = new UserLoginLookup(),
    ) {
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Creates a tenant and its owner together, so neither can exist alone.
     *
     * @return array{tenant_id: int, user_id: int}
     */
    public function signUp(string $workspace, string $name, string $email, string $password): array
    {
        $email = self::normalizeEmail($email);

        if ($this->lookup->findByEmail($email) !== null) {
            throw new EmailTakenException();
        }

        try {
            return Transaction::run(function () use ($workspace, $name, $email, $password): array {
                $tenantId = (int) $this->tenants->insert(['name' => $workspace]);
                $userId   = $this->context->runAs(
                    $tenantId,
                    fn (): int => $this->createUser($name, $email, $password, UserModel::ROLE_OWNER)
                );

                return ['tenant_id' => $tenantId, 'user_id' => $userId];
            });
        } catch (DatabaseException $e) {
            // Two sign-ups can pass the check above at once; the unique key settles it.
            throw $e->getCode() === self::MYSQL_DUPLICATE_KEY ? new EmailTakenException() : $e;
        }
    }

    /**
     * Adds a member to the current tenant.
     *
     * @return string The generated password, which is only available here.
     */
    public function addMember(string $name, string $email): string
    {
        $email = self::normalizeEmail($email);

        if ($this->lookup->findByEmail($email) !== null) {
            throw new EmailTakenException();
        }

        $password = bin2hex(random_bytes(12));

        try {
            $this->createUser($name, $email, $password, UserModel::ROLE_MEMBER);
        } catch (DatabaseException $e) {
            throw $e->getCode() === self::MYSQL_DUPLICATE_KEY ? new EmailTakenException() : $e;
        }

        return $password;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $hash = $this->users->passwordHash($userId);

        if ($hash === null || ! password_verify($currentPassword, $hash)) {
            return false;
        }

        return $this->users->update($userId, ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]);
    }

    private function createUser(string $name, string $email, string $password, string $role): int
    {
        return (int) $this->users->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
        ]);
    }
}
