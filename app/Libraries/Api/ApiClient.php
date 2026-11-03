<?php

namespace App\Libraries\Api;

/**
 * The API credential behind this request, set by ApiAuthFilter.
 */
final class ApiClient
{
    /** @var array<string, mixed>|null */
    private ?array $credential = null;

    /** @param array<string, mixed> $credential */
    public function set(array $credential): void
    {
        $this->credential = $credential;
    }

    public function isAuthenticated(): bool
    {
        return $this->credential !== null;
    }

    public function id(): int
    {
        return (int) ($this->credential['id'] ?? 0);
    }

    public function label(): string
    {
        return 'API key ' . ($this->credential['token_prefix'] ?? '');
    }
}
