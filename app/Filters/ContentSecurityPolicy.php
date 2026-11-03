<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Limits damage if escaping is ever missed: no inline or third-party scripts.
 * Off in development only, because the debug toolbar injects its own scripts.
 */
class ContentSecurityPolicy implements FilterInterface
{
    private const POLICY = "default-src 'self'; object-src 'none'; base-uri 'self'; "
        . "form-action 'self'; frame-ancestors 'none'";

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        if (ENVIRONMENT !== 'development') {
            $response->setHeader('Content-Security-Policy', self::POLICY);
        }

        return $response;
    }
}
