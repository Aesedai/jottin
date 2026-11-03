<?php

namespace Tests\Unit;

use Config\App;
use PHPUnit\Framework\TestCase;

final class TrustedProxyConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('JOTTIN_TRUSTED_PROXY');
        unset($_ENV['JOTTIN_TRUSTED_PROXY'], $_SERVER['JOTTIN_TRUSTED_PROXY']);
    }

    public function testNoProxyIsTrustedByDefault(): void
    {
        $this->assertSame([], (new App())->proxyIPs);
    }

    public function testOnlyTheConfiguredRangeIsTrusted(): void
    {
        putenv('JOTTIN_TRUSTED_PROXY=172.30.0.0/24');

        $this->assertSame(['172.30.0.0/24' => 'X-Forwarded-For'], (new App())->proxyIPs);
    }
}
