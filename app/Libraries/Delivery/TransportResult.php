<?php

namespace App\Libraries\Delivery;

/**
 * The outcome of one request. The error is safe to show and store: it never includes
 * the URL or the response body.
 */
final class TransportResult
{
    public function __construct(
        public readonly ?int $httpStatus,
        public readonly ?string $error,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->error === null
            && $this->httpStatus !== null
            && $this->httpStatus >= 200
            && $this->httpStatus < 300;
    }
}
