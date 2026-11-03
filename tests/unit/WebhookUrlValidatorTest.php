<?php

namespace Tests\Unit;

use App\Libraries\Delivery\BlockedTarget;
use App\Libraries\Delivery\WebhookUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDnsResolver;

final class WebhookUrlValidatorTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function blockedUrls(): array
    {
        return [
            'localhost'              => ['http://localhost/hook'],
            'localhost subdomain'    => ['https://foo.localhost/hook'],
            'dot local'              => ['https://printer.local/hook'],
            'dot internal'           => ['https://metadata.google.internal/computeMetadata/v1/'],
            'loopback'               => ['http://127.0.0.1/hook'],
            'loopback elsewhere'     => ['http://127.8.8.8/hook'],
            'zero network'           => ['http://0.0.0.0/hook'],
            'private 10'             => ['http://10.1.2.3/hook'],
            'private 172'            => ['http://172.16.0.1/hook'],
            'private 172 upper'      => ['http://172.31.255.255/hook'],
            'private 192'            => ['http://192.168.1.50/hook'],
            'cloud metadata'         => ['http://169.254.169.254/latest/meta-data/'],
            'carrier-grade NAT'      => ['http://100.64.0.1/hook'],
            'benchmark range'        => ['http://198.18.0.1/hook'],
            'multicast'              => ['http://224.0.0.1/hook'],
            'decimal IP'             => ['http://2130706433/hook'],
            'hex IP'                 => ['http://0x7f.0.0.1/hook'],
            'short IP'               => ['http://127.1/hook'],
            'IPv6 loopback'          => ['http://[::1]/hook'],
            'IPv6 mapped IPv4'       => ['http://[::ffff:127.0.0.1]/hook'],
            'IPv6 unique local'      => ['http://[fd00::1]/hook'],
            'IPv6 link-local'        => ['http://[fe80::1]/hook'],
            'file scheme'            => ['file:///etc/passwd'],
            'ftp scheme'             => ['ftp://example.com/hook'],
            'gopher scheme'          => ['gopher://example.com/hook'],
            'credentials in URL'     => ['https://user:pass@example.com/hook'],
            'not a URL'              => ['not a url'],
            'empty'                  => [''],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function testBlocksUnsafeTargets(string $url): void
    {
        $this->expectException(BlockedTarget::class);

        (new WebhookUrlValidator(new FakeDnsResolver()))->resolve($url);
    }

    public function testBlocksAHostnameResolvingToAPrivateAddress(): void
    {
        $resolver = new FakeDnsResolver(['sneaky.example.com' => ['10.0.0.5']]);

        $this->expectException(BlockedTarget::class);

        (new WebhookUrlValidator($resolver))->resolve('https://sneaky.example.com/hook');
    }

    public function testBlocksAHostIfAnyOfItsAddressesIsPrivate(): void
    {
        $resolver = new FakeDnsResolver(['mixed.example.com' => ['93.184.216.34', '127.0.0.1']]);

        $this->expectException(BlockedTarget::class);

        (new WebhookUrlValidator($resolver))->resolve('https://mixed.example.com/hook');
    }

    public function testBlocksAHostThatDoesNotResolve(): void
    {
        $resolver = new FakeDnsResolver(['gone.example.com' => []]);

        $this->expectException(BlockedTarget::class);

        (new WebhookUrlValidator($resolver))->resolve('https://gone.example.com/hook');
    }

    public function testAcceptsAPublicHostAndPinsTheCheckedAddress(): void
    {
        $resolver = new FakeDnsResolver(['hooks.example.com' => ['93.184.216.34']]);

        $target = (new WebhookUrlValidator($resolver))->resolve('https://hooks.example.com:8443/in?token=abc');

        $this->assertSame(['host' => 'hooks.example.com', 'port' => 8443, 'address' => '93.184.216.34'], $target);
    }

    public function testDefaultPortsFollowTheScheme(): void
    {
        $validator = new WebhookUrlValidator(new FakeDnsResolver());

        $this->assertSame(443, $validator->resolve('https://example.com/hook')['port']);
        $this->assertSame(80, $validator->resolve('http://example.com/hook')['port']);
    }

    public function testAcceptsAPublicIpLiteral(): void
    {
        $target = (new WebhookUrlValidator(new FakeDnsResolver()))->resolve('https://93.184.216.34/hook');

        $this->assertSame('93.184.216.34', $target['address']);
    }
}
