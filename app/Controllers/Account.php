<?php

namespace App\Controllers;

use App\Libraries\Auth\AccountService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Account extends BaseController
{
    public function passwordForm(): string
    {
        return view('account/password');
    }

    public function changePassword(): RedirectResponse|ResponseInterface
    {
        $rules = [
            'current_password' => 'required',
            'new_password'     => 'required|min_length[12]|fitsBcrypt',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setBody(view('account/password'));
        }

        $changed = (new AccountService(service('tenantContext')))->changePassword(
            service('currentUser')->id(),
            (string) $this->request->getPost('current_password'),
            (string) $this->request->getPost('new_password')
        );

        if (! $changed) {
            return $this->response->setStatusCode(422)
                ->setBody(view('account/password', ['error' => 'Your current password is incorrect.']));
        }

        return redirect()->to('/dashboard')->with('notice', 'Password changed.');
    }
}
