<?php

namespace App\Libraries\Delivery;

/**
 * Stops a webhook being used to reach internal systems (SSRF). A host passes only if
 * every address it resolves to is public, because an attacker controls their own DNS.
 */
final class WebhookUrlValidator
{
    private const BLOCKED_SUFFIXES = ['.localhost', '.local', '.internal', '.intranet', '.lan', '.home', '.corp'];

    /** Ranges that aren't reachable public addresses, including cloud metadata (169.254/16). */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
        '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001:db8::/32', 'fc00::/7',
        'fe80::/10', 'ff00::/8',
    ];

    public function __construct(
        private readonly DnsResolver $resolver,
        private readonly bool $allowPrivateTargets = false,
    ) {
    }

    public static function fromEnvironment(): self
    {
        // A local-demo switch that only ever applies in development, so a test run or a
        // staging environment that inherits the variable keeps the protection.
        $allow = ENVIRONMENT === 'development' && getenv('JOTTIN_ALLOW_PRIVATE_WEBHOOKS') === 'true';

        return new self(service('dnsResolver'), $allow);
    }

    /**
     * Checks a URL's form and resolves its host.
     *
     * @return array{host: string, port: int, address: string}
     *   The address the request must connect to, so DNS can't change between this
     *   check and the connection.
     */
    public function resolve(string $url): array
    {
        $host = $this->checkedHost($url);
        $port = parse_url($url, PHP_URL_PORT);
        $port = is_int($port) ? $port : (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);

        $literal   = trim($host, '[]');
        $addresses = filter_var($literal, FILTER_VALIDATE_IP) !== false ? [$literal] : $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new BlockedTarget('That host could not be found.');
        }

        foreach ($addresses as $address) {
            if (! $this->allowPrivateTargets && $this->isBlocked($address)) {
                throw new BlockedTarget('That address is not allowed: it points to a private or reserved network.');
            }
        }

        return ['host' => $host, 'port' => $port, 'address' => $addresses[0]];
    }

    private function checkedHost(string $url): string
    {
        if (mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new BlockedTarget('Enter a valid URL, including http:// or https://.');
        }

        $parts = parse_url($url);

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new BlockedTarget('Only http and https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new BlockedTarget('A URL can\'t include a username or password. Use the signing secret instead.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            throw new BlockedTarget('Enter a valid URL, including http:// or https://.');
        }

        if ($this->allowPrivateTargets) {
            return $host;
        }

        if ($host === 'localhost' || $this->hasBlockedSuffix($host)) {
            throw new BlockedTarget('That address is not allowed: it points to a private or reserved network.');
        }

        // Forms such as 2130706433 and 0x7f.0.0.1 are how 127.0.0.1 gets disguised.
        if (preg_match('/^(0x[0-9a-f.]+|[0-9.]+)$/', $host) === 1 && filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new BlockedTarget('That address is not allowed: it points to a private or reserved network.');
        }

        return $host;
    }

    private function hasBlockedSuffix(string $host): bool
    {
        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isBlocked(string $address): bool
    {
        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->inRange($address, $range)) {
                return true;
            }
        }

        return false;
    }

    private function inRange(string $address, string $range): bool
    {
        [$network, $prefix] = explode('/', $range);

        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);

        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $wholeBytes = (int) ((int) $prefix / 8);
        $extraBits  = (int) $prefix % 8;

        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($extraBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $extraBits) & 0xFF;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }
}
