<?php

namespace App\Filters;

use App\Libraries\Api\ApiCredentialService;
use App\Models\ApiCredentialLookup;
use App\Models\ApiCredentialModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;

/**
 * Authenticates an API key and sets the tenant from it. Nothing in the request can
 * choose the tenant, and every failure gets the same answer.
 */
class ApiAuthFilter implements FilterInterface
{
    private const REQUESTS_PER_MINUTE        = 60;
    private const FAILED_ATTEMPTS_PER_MINUTE = 20;
    private const LAST_USED_RESOLUTION_SECS  = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        assert($request instanceof IncomingRequest);

        $token      = $this->bearerToken($request);
        $credential = $token === null
            ? null
            : (new ApiCredentialLookup())->findActiveByHash(ApiCredentialService::hash($token));

        if ($credential === null) {
            $ipKey = 'api-fail-' . md5($request->getIPAddress());

            if (! service('throttler')->check($ipKey, self::FAILED_ATTEMPTS_PER_MINUTE, MINUTE)) {
                return $this->rateLimited();
            }

            return service('response')
                ->setStatusCode(401)
                ->setHeader('WWW-Authenticate', 'Bearer')
                ->setJSON(['error' => ['code' => 'unauthorized', 'message' => 'A valid API key is required.']]);
        }

        if (! service('throttler')->check('api-key-' . $credential['public_id'], self::REQUESTS_PER_MINUTE, MINUTE)) {
            return $this->rateLimited();
        }

        service('tenantContext')->set((int) $credential['tenant_id']);
        service('apiClient')->set($credential);
        $this->recordUse($credential);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    private function bearerToken(IncomingRequest $request): ?string
    {
        $matched = preg_match('/^Bearer\s+(\S+)$/i', $request->getHeaderLine('Authorization'), $matches);

        return $matched === 1 ? $matches[1] : null;
    }

    /** @param array<string, mixed> $credential */
    private function recordUse(array $credential): void
    {
        $lastUsed = $credential['last_used_at'] === null ? 0 : strtotime((string) $credential['last_used_at'] . ' UTC');

        // Coarse on purpose, so a busy client doesn't turn every request into a write.
        if (Time::now()->getTimestamp() - (int) $lastUsed >= self::LAST_USED_RESOLUTION_SECS) {
            (new ApiCredentialModel())->update($credential['id'], ['last_used_at' => Time::now()->toDateTimeString()]);
        }
    }

    private function rateLimited(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) max(1, service('throttler')->getTokenTime()))
            ->setJSON(['error' => ['code' => 'rate_limited', 'message' => 'Too many requests. Try again shortly.']]);
    }
}
