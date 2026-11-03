<?php

namespace App\Libraries\Api;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Transaction;
use App\Models\ApiCredentialModel;
use CodeIgniter\I18n\Time;

final class ApiCredentialService
{
    private const TOKEN_PREFIX_LENGTH = 13;

    public function __construct(
        private readonly ApiCredentialModel $credentials = new ApiCredentialModel(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return string The key. This is the only time it exists in readable form.
     */
    public function create(string $label): string
    {
        $token    = 'jott_' . bin2hex(random_bytes(32));
        $publicId = bin2hex(random_bytes(12));

        Transaction::run(function () use ($token, $publicId, $label): void {
            $this->credentials->insert([
                'public_id'    => $publicId,
                'label'        => $label,
                'token_prefix' => substr($token, 0, self::TOKEN_PREFIX_LENGTH),
                'token_hash'   => self::hash($token),
            ]);
            $this->audit->record('api_key.created', 'api_key', $publicId);
        });

        return $token;
    }

    /** @param array<string, mixed> $credential A row already loaded through the tenant scope. */
    public function revoke(array $credential): void
    {
        Transaction::run(function () use ($credential): void {
            $this->credentials->update($credential['id'], ['revoked_at' => Time::now()->toDateTimeString()]);
            $this->audit->record('api_key.revoked', 'api_key', $credential['public_id']);
        });
    }
}
