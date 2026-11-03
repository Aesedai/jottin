<?php

namespace App\Libraries\Tenancy;

/**
 * The tenant the current request or job acts for. It is set once from a trusted
 * source (the session or an API credential), never from request input.
 */
final class TenantContext
{
    private ?int $tenantId = null;

    public function set(int $tenantId): void
    {
        if ($tenantId < 1) {
            throw new TenantScopeException('A tenant ID must be a positive integer.');
        }

        if ($this->tenantId !== null && $this->tenantId !== $tenantId) {
            throw new TenantScopeException('The tenant cannot change during a request.');
        }

        $this->tenantId = $tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Fails closed: with no tenant there is no query, rather than an unscoped one.
     */
    public function id(): int
    {
        return $this->tenantId ?? throw new TenantScopeException('No tenant has been resolved.');
    }

    /**
     * Runs a system task, such as a delivery job, for one tenant. The previous
     * tenant is restored even if the task throws.
     *
     * @template T
     *
     * @param callable(): T $task
     *
     * @return T
     */
    public function runAs(int $tenantId, callable $task): mixed
    {
        if ($tenantId < 1) {
            throw new TenantScopeException('A tenant ID must be a positive integer.');
        }

        $previous       = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $task();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
