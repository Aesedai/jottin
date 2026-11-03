<?php

namespace App\Libraries\Delivery;

/**
 * Sends one POST with strict limits: pinned to the validated address, no redirects,
 * short timeouts, and the response body discarded.
 */
class CurlTransport implements WebhookTransport
{
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const TOTAL_TIMEOUT_SECONDS   = 5;

    public function post(string $url, array $target, string $body, array $headers): TransportResult
    {
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $body,
            CURLOPT_HTTPHEADER      => $headerLines,
            CURLOPT_CONNECTTIMEOUT  => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT         => self::TOTAL_TIMEOUT_SECONDS,
            CURLOPT_NOSIGNAL        => true,
            // A redirect could point anywhere, including somewhere internal.
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Connect to the address that was checked, even if DNS has changed since.
            CURLOPT_RESOLVE         => [
                sprintf('%s:%d:%s', trim($target['host'], '[]'), $target['port'], $target['address']),
            ],
            // Discard the response: it isn't stored and its size shouldn't matter.
            CURLOPT_WRITEFUNCTION   => static fn ($curl, string $chunk): int => strlen($chunk),
            CURLOPT_HEADERFUNCTION  => static fn ($curl, string $line): int => strlen($line),
        ]);

        $completed = curl_exec($handle);
        $errno     = curl_errno($handle);
        $status    = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        curl_close($handle);

        if ($completed === false || $errno !== 0) {
            return new TransportResult($status > 0 ? $status : null, $this->summarize($errno));
        }

        if ($status >= 300 && $status < 400) {
            return new TransportResult($status, 'The endpoint returned a redirect, which is not followed.');
        }

        if ($status < 200 || $status >= 300) {
            return new TransportResult($status, "The endpoint returned HTTP {$status}.");
        }

        return new TransportResult($status, null);
    }

    private function summarize(int $errno): string
    {
        return match ($errno) {
            CURLE_OPERATION_TIMEDOUT => 'The endpoint did not respond in time.',
            CURLE_COULDNT_CONNECT    => 'Could not connect to the endpoint.',
            CURLE_COULDNT_RESOLVE_HOST => 'The endpoint host could not be found.',
            CURLE_SSL_CONNECT_ERROR, CURLE_SSL_PEER_CERTIFICATE => 'The endpoint\'s secure connection failed.',
            default => 'The request could not be completed.',
        };
    }
}
