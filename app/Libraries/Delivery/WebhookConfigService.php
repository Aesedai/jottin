<?php

namespace App\Libraries\Delivery;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Transaction;
use App\Models\CollectionWebhookModel;

/**
 * A collection has at most one webhook, so saving replaces any existing one.
 */
final class WebhookConfigService
{
    public function __construct(
        private readonly CollectionWebhookModel $webhooks = new CollectionWebhookModel(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {
    }

    /**
     * @param array<string, mixed> $collection A row already loaded through the tenant scope.
     * @param string               $url        Blank keeps the current URL.
     *
     * @return string|null The signing secret if one was just created. It's only available here.
     *
     * @throws BlockedTarget
     */
    public function save(array $collection, string $url, string $mode): ?string
    {
        $collectionId = (int) $collection['id'];
        $existing     = $this->webhooks->forCollection($collectionId);
        $url          = trim($url);

        if ($url === '' && $existing === null) {
            throw new BlockedTarget('Enter the URL to send jotts to.');
        }

        $row = ['mode' => $mode];

        if ($url !== '') {
            $target                    = WebhookUrlValidator::fromEnvironment()->resolve($url);
            $row['endpoint_encrypted'] = self::encrypt($url);
            $row['endpoint_host']      = $target['host'];
        }

        $secret = $existing === null ? bin2hex(random_bytes(32)) : null;

        Transaction::run(function () use ($existing, $row, $secret, $collectionId, $collection): void {
            if ($existing === null) {
                $this->webhooks->insert($row + [
                    'collection_id'    => $collectionId,
                    'secret_encrypted' => self::encrypt((string) $secret),
                ]);
            } else {
                $this->webhooks->update($existing['id'], $row);
            }

            $this->audit->record('webhook.configured', 'collection', $collection['public_id'], [
                'mode' => $row['mode'],
                'host' => $row['endpoint_host'] ?? $existing['endpoint_host'],
            ]);
        });

        return $secret;
    }

    public static function encrypt(string $value): string
    {
        return base64_encode(service('encrypter')->encrypt($value));
    }

    public static function decrypt(string $value): string
    {
        return service('encrypter')->decrypt(base64_decode($value, true));
    }
}
