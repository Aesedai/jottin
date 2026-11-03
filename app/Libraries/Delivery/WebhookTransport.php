<?php

namespace App\Libraries\Delivery;

interface WebhookTransport
{
    /**
     * @param array{host: string, port: int, address: string} $target Where to connect, already validated.
     * @param array<string, string>                           $headers
     */
    public function post(string $url, array $target, string $body, array $headers): TransportResult;
}
