<?php

namespace Tests\Support;

use App\Libraries\Delivery\TransportResult;
use App\Libraries\Delivery\WebhookTransport;

/**
 * Records what would have been sent, and answers however a test needs.
 */
final class FakeWebhookTransport implements WebhookTransport
{
    /** @var list<array{url: string, target: array<string, mixed>, body: string, headers: array<string, string>}> */
    public array $requests = [];

    public TransportResult $next;

    public function __construct()
    {
        $this->next = new TransportResult(200, null);
    }

    public function post(string $url, array $target, string $body, array $headers): TransportResult
    {
        $this->requests[] = ['url' => $url, 'target' => $target, 'body' => $body, 'headers' => $headers];

        return $this->next;
    }
}
