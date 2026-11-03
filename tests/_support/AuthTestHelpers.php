<?php

namespace Tests\Support;

use App\Libraries\Auth\AccountService;
use App\Models\UserLoginLookup;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\TestResponse;

trait AuthTestHelpers
{
    protected string $password = 'correct-horse-battery';

    /** @return array{tenant_id: int, user_id: int} */
    protected function createWorkspace(string $email, string $workspace = 'Workspace'): array
    {
        return (new AccountService(service('tenantContext')))->signUp($workspace, 'Owner', $email, $this->password);
    }

    /** @return array{tenant_id: int, user_id: int} */
    protected function createMember(int $tenantId, string $email): array
    {
        $context = service('tenantContext');
        $context->runAs($tenantId, static fn () => (new AccountService($context))->addMember('Member', $email));

        $row = (new UserLoginLookup())->findByEmail($email);

        return ['tenant_id' => (int) $row['tenant_id'], 'user_id' => (int) $row['id']];
    }

    /**
     * Acts as this user on the next request. Each simulated request needs fresh
     * services, as a real one would get.
     *
     * @param array{tenant_id: int, user_id: int} $account
     */
    protected function actingAs(array $account): static
    {
        $this->freshRequest();

        return $this->withSession($account + [$this->tokenName() => $this->token()]);
    }

    protected function freshRequest(): void
    {
        Services::resetSingle('tenantContext');
        Services::resetSingle('currentUser');
        // It reads the session's token once when created, as each real request does.
        Services::resetSingle('security');
    }

    protected function tokenName(): string
    {
        return config('Security')->tokenName;
    }

    protected function token(): string
    {
        return 'test-csrf-token';
    }

    /** @param array<string, mixed> $params */
    protected function submit(string $uri, array $params = []): TestResponse
    {
        return $this->post($uri, $params + [$this->tokenName() => $this->token()]);
    }
}
