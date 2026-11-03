<?php

namespace Tests\Support;

use App\Libraries\Delivery\DnsResolver;

class FakeDnsResolver extends DnsResolver
{
    /** @param array<string, list<string>> $records */
    public function __construct(public array $records = [])
    {
    }

    public function resolve(string $host): array
    {
        return $this->records[$host] ?? ['93.184.216.34'];
    }
}
