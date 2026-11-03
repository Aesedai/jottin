<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Usage: role:owner. Runs after AuthFilter, and refuses if no user was resolved.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = service('currentUser');

        foreach ($arguments ?? [] as $role) {
            if ($user->hasRole($role)) {
                return null;
            }
        }

        return service('response')->setStatusCode(403)->setBody(view('errors/forbidden'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
