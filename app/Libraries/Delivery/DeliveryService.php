<?php

namespace App\Libraries\Delivery;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Transaction;
use App\Models\CollectionModel;
use App\Models\CollectionWebhookModel;
use App\Models\DeliveryAttemptModel;
use App\Models\DeliveryStateModel;
use App\Models\JottModel;
use CodeIgniter\I18n\Time;

/**
 * Every delivery follows one path: record the attempt, POST outside any database
 * transaction, then record the result. The history is written before sending, so an
 * attempt can't happen without a record of it.
 */
final class DeliveryService
{
    public function __construct(
        private readonly CollectionWebhookModel $webhooks = new CollectionWebhookModel(),
        private readonly DeliveryAttemptModel $attempts = new DeliveryAttemptModel(),
        private readonly DeliveryStateModel $states = new DeliveryStateModel(),
        private readonly CollectionModel $collections = new CollectionModel(),
        private readonly JottModel $jotts = new JottModel(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {
    }

    /**
     * Queues a new jott for each of its collections set to Automatic. Runs inside the
     * jott's transaction, so the queue entry commits with it, and nothing is sent here.
     *
     * @param list<int> $collectionIds Collections the new jott entered.
     */
    public function enqueueForNewJott(int $jottId, string $jottRef, array $collectionIds): void
    {
        foreach (array_unique($collectionIds) as $collectionId) {
            $webhook = $this->webhooks->forCollection($collectionId);

            if ($webhook === null || $webhook['mode'] !== CollectionWebhookModel::MODE_AUTOMATIC) {
                continue;
            }

            $this->attempts->insert([
                'public_id'     => bin2hex(random_bytes(12)),
                'jott_id'       => $jottId,
                'jott_ref'      => $jottRef,
                'collection_id' => $collectionId,
                'trigger_type'  => 'automatic',
                'status'        => 'queued',
                'endpoint_host' => $webhook['endpoint_host'],
            ]);
            $this->states->setState($jottId, $collectionId, 'queued');
        }
    }

    /**
     * Sends selected jotts one at a time. A jott that isn't selectable is skipped
     * rather than re-sent, which is the point of re-arming.
     *
     * @param array<string, mixed>       $collection Already loaded through the tenant scope.
     * @param list<array<string, mixed>> $jotts      Already loaded through the tenant scope, and members.
     *
     * @return array{succeeded: int, failed: int, skipped: int}
     */
    public function sendManually(array $collection, array $jotts): array
    {
        $collectionId = (int) $collection['id'];
        $summary      = ['succeeded' => 0, 'failed' => 0, 'skipped' => 0];
        $webhook      = $this->webhooks->forCollection($collectionId);

        if ($webhook === null || $webhook['mode'] !== CollectionWebhookModel::MODE_MANUAL) {
            $summary['skipped'] = count($jotts);

            return $summary;
        }

        foreach ($jotts as $jott) {
            $attemptId = $this->claimForManualSend($jott, $collectionId, $webhook);

            if ($attemptId === null) {
                $summary['skipped']++;

                continue;
            }

            $succeeded = $this->deliver($attemptId);
            $summary[$succeeded ? 'succeeded' : 'failed']++;
        }

        $this->audit->record('delivery.sent', 'collection', $collection['public_id'], $summary);

        return $summary;
    }

    /** @param array<string, mixed> $jott A row already loaded through the tenant scope. */
    public function rearm(array $jott, int $collectionId): bool
    {
        return Transaction::run(function () use ($jott, $collectionId): bool {
            $rearmed = $this->states->transition((int) $jott['id'], $collectionId, DeliveryStateModel::SENT, 'rearmed');

            if ($rearmed) {
                $this->audit->record('jott.rearmed', 'jott', $jott['public_id']);
            }

            return $rearmed;
        });
    }

    /**
     * Sends one attempt that has already been claimed. The current mode and URL are
     * read now, so a queued jott can't go to a webhook the user has since turned off.
     */
    public function deliver(int $attemptId): bool
    {
        $attempt = $this->attempts->find($attemptId);

        if ($attempt === null) {
            return false;
        }

        $collectionId = (int) $attempt['collection_id'];
        $webhook      = $this->webhooks->forCollection($collectionId);
        $jott         = $this->jotts->find($attempt['jott_id']);

        if ($jott === null) {
            return $this->finish($attempt, new TransportResult(null, 'The jott was deleted before it could be sent.'));
        }

        if ($webhook === null || $webhook['mode'] !== $this->requiredMode($attempt)) {
            return $this->finish(
                $attempt,
                new TransportResult(null, 'The collection\'s delivery mode changed before this was sent.')
            );
        }

        $url = WebhookConfigService::decrypt($webhook['endpoint_encrypted']);

        try {
            // Checked again at send time, because a host's DNS can change after it's saved.
            $target = WebhookUrlValidator::fromEnvironment()->resolve($url);
        } catch (BlockedTarget $e) {
            return $this->finish($attempt, new TransportResult(null, $e->getMessage()));
        }

        $collection = $this->collections->find($collectionId);
        $body       = json_encode([
            'event'      => 'jott.delivery',
            'deliveryId' => $attempt['public_id'],
            'occurredAt' => $this->iso(Time::now()->toDateTimeString()),
            'collection' => ['id' => $collection['public_id'], 'name' => $collection['name']],
            'jott'       => [
                'id'        => $jott['public_id'],
                'text'      => $jott['text'],
                'createdAt' => $this->iso((string) $jott['created_at']),
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $timestamp = (string) Time::now()->getTimestamp();
        $secret    = WebhookConfigService::decrypt($webhook['secret_encrypted']);

        $result = service('webhookTransport')->post($url, $target, $body, [
            'Content-Type'       => 'application/json',
            'User-Agent'         => 'Jottin-Webhooks/1.0',
            'X-Jottin-Delivery'  => $attempt['public_id'],
            'X-Jottin-Timestamp' => $timestamp,
            'X-Jottin-Signature' => 'sha256=' . hash_hmac('sha256', "{$timestamp}.{$body}", $secret),
        ]);

        return $this->finish($attempt, $result);
    }

    /** @param array<string, mixed> $attempt */
    private function requiredMode(array $attempt): string
    {
        return $attempt['trigger_type'] === 'manual'
            ? CollectionWebhookModel::MODE_MANUAL
            : CollectionWebhookModel::MODE_AUTOMATIC;
    }

    /**
     * @param array<string, mixed> $jott
     * @param array<string, mixed> $webhook
     */
    private function claimForManualSend(array $jott, int $collectionId, array $webhook): ?int
    {
        $jottId = (int) $jott['id'];

        return Transaction::run(function () use ($jott, $jottId, $collectionId, $webhook): ?int {
            if ($this->states->stateOf($jottId, $collectionId) === null) {
                $this->states->setState($jottId, $collectionId, 'ready');
            }

            if (! $this->states->transition($jottId, $collectionId, DeliveryStateModel::SELECTABLE, 'sending')) {
                return null;
            }

            return (int) $this->attempts->insert([
                'public_id'     => bin2hex(random_bytes(12)),
                'jott_id'       => $jottId,
                'jott_ref'      => $jott['public_id'],
                'collection_id' => $collectionId,
                'trigger_type'  => 'manual',
                'status'        => 'sending',
                'endpoint_host' => $webhook['endpoint_host'],
                'started_at'    => Time::now()->toDateTimeString(),
            ]);
        });
    }

    /** @param array<string, mixed> $attempt */
    private function finish(array $attempt, TransportResult $result): bool
    {
        $succeeded = $result->succeeded();

        Transaction::run(function () use ($attempt, $result, $succeeded): void {
            $this->attempts->update($attempt['id'], [
                'status'        => $succeeded ? 'succeeded' : 'failed',
                'http_status'   => $result->httpStatus,
                'error_summary' => $result->error,
                'finished_at'   => Time::now()->toDateTimeString(),
            ]);

            if ($this->jotts->find($attempt['jott_id']) !== null) {
                $this->states->setState(
                    (int) $attempt['jott_id'],
                    (int) $attempt['collection_id'],
                    $succeeded ? 'succeeded' : 'failed'
                );
            }
        });

        return $succeeded;
    }

    private function iso(string $dateTime): string
    {
        return str_replace(' ', 'T', $dateTime) . 'Z';
    }
}
