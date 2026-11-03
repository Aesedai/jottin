<?php

namespace App\Controllers;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Auth\AccountService;
use App\Libraries\Auth\EmailTakenException;
use App\Libraries\Transaction;
use App\Models\UserLoginLookup;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Owner-only, enforced by the role:owner filter on these routes.
 */
class Members extends BaseController
{
    public function index(): string
    {
        return view('members/index', ['members' => (new UserModel())->listPublic()]);
    }

    public function create(): ResponseInterface
    {
        $users = new UserModel();

        $rules = ['name' => 'required|max_length[100]', 'email' => 'required|valid_email|max_length[254]'];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)
                ->setBody(view('members/index', ['members' => $users->listPublic()]));
        }

        $email = (string) $this->request->getPost('email');

        try {
            $password = Transaction::run(function () use ($email): string {
                $password = (new AccountService(service('tenantContext')))->addMember(
                    trim((string) $this->request->getPost('name')),
                    $email
                );
                $id = (new UserLoginLookup())->idForEmail($email);
                (new AuditLogger())->record('member.added', 'user', (string) $id);

                return $password;
            });
        } catch (EmailTakenException) {
            return $this->response->setStatusCode(422)->setBody(view('members/index', [
                'members' => $users->listPublic(),
                'error'   => 'That email already has an account.',
            ]));
        }

        // Rendered directly, not flashed, so the password is never stored in the session.
        return $this->response->setStatusCode(201)->setBody(view('members/index', [
            'members' => $users->listPublic(),
            'created' => ['email' => $email, 'password' => $password],
        ]));
    }

    public function remove(int $id): RedirectResponse
    {
        $users  = new UserModel();
        $member = $users->findPublic($id);

        // Another tenant's user is indistinguishable from a user that doesn't exist.
        if ($member === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($member['role'] === UserModel::ROLE_OWNER) {
            return redirect()->to('/members')->with('error', 'The workspace owner cannot be removed.');
        }

        Transaction::run(static function () use ($users, $id): void {
            $users->delete($id);
            (new AuditLogger())->record('member.removed', 'user', (string) $id);
        });

        return redirect()->to('/members')->with('notice', 'Member removed.');
    }
}
