<?php

namespace App\Controllers;

use App\Libraries\Auth\AccountService;
use App\Libraries\Auth\Authenticator;
use App\Libraries\Auth\EmailTakenException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Failed forms re-render instead of redirecting. redirect()->withInput() would
 * store the submitted password in the session row.
 */
class Auth extends BaseController
{
    private const SIGNUP_RULES = [
        'workspace' => 'required|max_length[120]',
        'name'      => 'required|max_length[100]',
        'email'     => 'required|valid_email|max_length[254]',
        // bcrypt only uses the first 72 bytes, so longer input is refused rather than cut silently.
        'password'  => 'required|min_length[12]|fitsBcrypt',
    ];

    public function loginForm(): RedirectResponse|string
    {
        return $this->isSignedIn() ? redirect()->to('/dashboard') : view('auth/login');
    }

    public function login(): RedirectResponse|ResponseInterface
    {
        if (! $this->validate(['email' => 'required|valid_email', 'password' => 'required'])) {
            return $this->render('auth/login', 422);
        }

        $email = (string) $this->request->getPost('email');
        $ip    = $this->request->getIPAddress();

        if (
            ! service('throttler')->check('login-' . md5(AccountService::normalizeEmail($email) . '|' . $ip), 5, MINUTE)
            || ! service('throttler')->check('login-ip-' . md5($ip), 30, MINUTE)
        ) {
            return $this->render('auth/login', 429, ['error' => 'Too many attempts. Wait a minute and try again.']);
        }

        $account = (new Authenticator(service('tenantContext')))->attempt(
            $email,
            (string) $this->request->getPost('password')
        );

        if ($account === null) {
            return $this->render('auth/login', 401, ['error' => 'Incorrect email or password.']);
        }

        return $this->startSession($account);
    }

    public function signupForm(): RedirectResponse|string
    {
        return $this->isSignedIn() ? redirect()->to('/dashboard') : view('auth/signup');
    }

    public function signup(): RedirectResponse|ResponseInterface
    {
        if (! service('throttler')->check('signup-ip-' . md5($this->request->getIPAddress()), 10, HOUR)) {
            return $this->render('auth/signup', 429, ['error' => 'Too many sign-ups. Try again later.']);
        }

        if (! $this->validate(self::SIGNUP_RULES)) {
            return $this->render('auth/signup', 422);
        }

        try {
            $account = (new AccountService(service('tenantContext')))->signUp(
                trim((string) $this->request->getPost('workspace')),
                trim((string) $this->request->getPost('name')),
                (string) $this->request->getPost('email'),
                (string) $this->request->getPost('password')
            );
        } catch (EmailTakenException) {
            return $this->render('auth/signup', 422, ['error' => 'That email already has an account.']);
        }

        return $this->startSession($account);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('/');
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function startSession(array $account): RedirectResponse
    {
        // A new ID and CSRF token at sign-in defeat session fixation.
        session()->regenerate(true);
        session()->set($account);
        service('security')->generateHash();

        return redirect()->to('/dashboard');
    }

    private function isSignedIn(): bool
    {
        return session()->has('user_id');
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, int $status, array $data = []): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setBody(view($view, $data));
    }
}
