<?php

namespace App\Libraries\Delivery;

use App\Models\DeliveryQueue;

/**
 * Drains the queue across tenants. Each attempt runs inside its own tenant's scope,
 * so a worker can't mix one tenant's jott with another's webhook.
 */
final class DeliveryRunner
{
    private const ABANDONED_AFTER_SECONDS = 300;

    public function __construct(private readonly DeliveryQueue $queue = new DeliveryQueue())
    {
    }

    /**
     * @return int How many attempts were processed.
     */
    public function runOnce(int $limit = 50): int
    {
        $this->queue->failAbandoned(self::ABANDONED_AFTER_SECONDS);

        $processed = 0;

        while ($processed < $limit) {
            $claim = $this->queue->claimNext();

            if ($claim === null) {
                break;
            }

            service('tenantContext')->runAs(
                $claim['tenant_id'],
                static fn () => (new DeliveryService())->deliver($claim['id'])
            );
            $processed++;
        }

        return $processed;
    }
}
