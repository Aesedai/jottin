<?php

namespace App\Controllers;

use App\Libraries\Api\ApiCredentialService;
use App\Models\ApiCredentialModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Owner-only, enforced by the role:owner filter on these routes.
 */
class ApiKeys extends BaseController
{
    public function index(): string
    {
        return view('api/index', ['credentials' => (new ApiCredentialModel())->listPublic()]);
    }

    public function create(): ResponseInterface
    {
        $credentials = new ApiCredentialModel();

        if (! $this->validate(['label' => 'required|max_length[100]'])) {
            return $this->response->setStatusCode(422)
                ->setBody(view('api/index', ['credentials' => $credentials->listPublic()]));
        }

        $token = (new ApiCredentialService())->create(trim((string) $this->request->getPost('label')));

        // Rendered directly, not flashed, so the key is never stored in the session.
        return $this->response->setStatusCode(201)->setBody(view('api/index', [
            'credentials' => $credentials->listPublic(),
            'newToken'    => $token,
        ]));
    }

    public function revoke(string $publicId): RedirectResponse
    {
        $credential = (new ApiCredentialModel())->findByPublicId($publicId)
            ?? throw PageNotFoundException::forPageNotFound();

        if ($credential['revoked_at'] === null) {
            (new ApiCredentialService())->revoke($credential);
        }

        return redirect()->to('/settings/api')->with('notice', 'API key revoked.');
    }
}
