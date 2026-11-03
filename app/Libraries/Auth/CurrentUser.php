<?php

namespace App\Libraries\Auth;

/**
 * The user behind this request, loaded fresh by AuthFilter. Roles are read from
 * the database on every request, so removing someone takes effect immediately.
 */
final class CurrentUser
{
    /** @var array<string, mixed>|null */
    private ?array $user = null;

    /** @param array<string, mixed> $user */
    public function set(array $user): void
    {
        $this->user = $user;
    }

    public function isSignedIn(): bool
    {
        return $this->user !== null;
    }

    public function id(): int
    {
        return (int) ($this->user['id'] ?? 0);
    }

    public function name(): string
    {
        return (string) ($this->user['name'] ?? '');
    }

    public function email(): string
    {
        return (string) ($this->user['email'] ?? '');
    }

    public function hasRole(string $role): bool
    {
        return ($this->user['role'] ?? null) === $role;
    }
}
