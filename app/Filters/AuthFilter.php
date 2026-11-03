<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Requires a signed-in user and sets the tenant for the rest of the request.
 * The session's user is looked up inside its tenant, so a session whose user and
 * tenant don't match, or whose user was removed, simply stops working.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session  = session();
        $userId   = (int) $session->get('user_id');
        $tenantId = (int) $session->get('tenant_id');

        if ($userId < 1 || $tenantId < 1) {
            return redirect()->to('/login');
        }

        service('tenantContext')->set($tenantId);

        $user = (new UserModel())->findPublic($userId);

        if ($user === null) {
            $session->destroy();

            return redirect()->to('/login');
        }

        service('currentUser')->set($user);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
