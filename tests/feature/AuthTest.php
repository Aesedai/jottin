<?php

namespace Tests\Feature;

use App\Models\UserLoginLookup;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTestHelpers;
use Tests\Support\CleanDatabase;

final class AuthTest extends CIUnitTestCase
{
    use AuthTestHelpers;
    use DatabaseTestTrait;
    use CleanDatabase;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttler is shared and would otherwise carry counts between tests.
        Services::resetSingle('throttler');
    }

    /** @return array<string, string> */
    private function signupFields(string $email = 'ada@example.com'): array
    {
        return [
            'workspace' => 'Analytical Engines',
            'name'      => 'Ada',
            'email'     => $email,
            'password'  => $this->password,
        ];
    }

    private function asGuest(): static
    {
        $this->freshRequest();

        return $this->withSession([$this->tokenName() => $this->token()]);
    }

    public function testSignupCreatesATenantAndOwnerAndSignsIn(): void
    {
        $result = $this->asGuest()->submit('signup', $this->signupFields());

        $result->assertRedirectTo('/dashboard');
        $this->seeInDatabase('tenants', ['name' => 'Analytical Engines']);
        $this->seeInDatabase('users', ['email' => 'ada@example.com', 'role' => 'owner']);
        $this->assertGreaterThan(0, session('tenant_id'));
    }

    public function testPasswordIsStoredAsAHash(): void
    {
        $this->createWorkspace('ada@example.com');

        $hash = (new UserLoginLookup())->findByEmail('ada@example.com')['password_hash'];

        $this->assertNotSame($this->password, $hash);
        $this->assertTrue(password_verify($this->password, $hash));
    }

    public function testDuplicateEmailIsRefusedRegardlessOfCase(): void
    {
        $this->createWorkspace('ada@example.com');

        $result = $this->asGuest()->submit('signup', $this->signupFields('ADA@Example.com'));

        $result->assertStatus(422);
        $result->assertSee('already has an account');
        $this->seeNumRecords(1, 'tenants', []);
    }

    public function testWeakPasswordCreatesNothing(): void
    {
        $result = $this->asGuest()->submit('signup', ['password' => 'short'] + $this->signupFields());

        $result->assertStatus(422);
        $this->seeNumRecords(0, 'tenants', []);
    }

    public function testPasswordOver72BytesIsRefusedEvenIfUnder72Characters(): void
    {
        $result = $this->asGuest()->submit('signup', ['password' => str_repeat('é', 40)] + $this->signupFields());

        $result->assertStatus(422);
        $this->seeNumRecords(0, 'tenants', []);
    }

    public function testSignupCannotChooseATenantOrRole(): void
    {
        $existing = $this->createWorkspace('first@example.com');

        $forged = ['tenant_id' => $existing['tenant_id'], 'role' => 'member'];
        $this->asGuest()->submit('signup', $this->signupFields() + $forged);

        $row = (new UserLoginLookup())->findByEmail('ada@example.com');
        $this->assertNotSame($existing['tenant_id'], (int) $row['tenant_id']);
        $this->seeInDatabase('users', ['email' => 'ada@example.com', 'role' => 'owner']);
    }

    public function testCorrectPasswordSignsIn(): void
    {
        $account = $this->createWorkspace('ada@example.com');

        $result = $this->asGuest()->submit('login', ['email' => 'ADA@example.com', 'password' => $this->password]);

        $result->assertRedirectTo('/dashboard');
        $this->assertSame($account['user_id'], session('user_id'));
    }

    public function testFailedSignInsLookTheSameForKnownAndUnknownEmails(): void
    {
        $this->createWorkspace('ada@example.com');

        $wrong = 'wrong-password-here';

        $wrongPassword = $this->asGuest()->submit('login', ['email' => 'ada@example.com', 'password' => $wrong]);
        $unknownEmail  = $this->asGuest()->submit('login', ['email' => 'nobody@example.com', 'password' => $wrong]);

        $wrongPassword->assertStatus(401);
        $unknownEmail->assertStatus(401);
        $wrongPassword->assertSee('Incorrect email or password.');
        $unknownEmail->assertSee('Incorrect email or password.');
        $this->assertNull(session('user_id'));
    }

    public function testRepeatedFailuresAreThrottled(): void
    {
        $this->createWorkspace('ada@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->asGuest()
                ->submit('login', ['email' => 'ada@example.com', 'password' => 'wrong-password-here'])
                ->assertStatus(401);
        }

        $result = $this->asGuest()->submit('login', ['email' => 'ada@example.com', 'password' => $this->password]);

        $result->assertStatus(429);
        $this->assertNull(session('user_id'));
    }

    public function testProtectedPagesRedirectGuestsToSignIn(): void
    {
        foreach (['dashboard', 'account/password', 'members'] as $page) {
            $this->freshRequest();
            $this->get($page)->assertRedirectTo('/login');
        }
    }

    public function testSignedInUserReachesTheDashboard(): void
    {
        $account = $this->createWorkspace('ada@example.com');

        $this->actingAs($account)->get('dashboard')->assertStatus(200);
    }

    /**
     * Session::destroy() is a no-op in the testing environment, so this can only
     * cover the route. Real sign-out is checked over HTTP against real sessions.
     */
    public function testSignOutRedirectsHome(): void
    {
        $account = $this->createWorkspace('ada@example.com');

        $this->actingAs($account)->submit('logout')->assertRedirectTo('/');
    }

    public function testSignOutRequiresPost(): void
    {
        $this->assertFalse(in_array('logout', array_keys(service('routes')->getRoutes('GET')), true));
    }

    public function testSessionPairingAUserWithAnotherTenantIsRejected(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');

        $forged = ['user_id' => $ada['user_id'], 'tenant_id' => $grace['tenant_id']];

        $this->actingAs($forged)->get('dashboard')->assertRedirectTo('/login');
    }

    public function testPostWithoutCsrfTokenIsRefused(): void
    {
        $account = $this->createWorkspace('ada@example.com');
        $this->actingAs($account);

        $this->expectException(SecurityException::class);

        $this->post('logout');
    }

    public function testPostWithWrongCsrfTokenIsRefused(): void
    {
        $this->asGuest();

        $this->expectException(SecurityException::class);

        $this->post('login', [$this->tokenName() => 'not-the-token', 'email' => 'a@example.com', 'password' => 'x']);
    }

    public function testChangingPasswordRequiresTheCurrentOne(): void
    {
        $account = $this->createWorkspace('ada@example.com');

        $result = $this->actingAs($account)->submit('account/password', [
            'current_password' => 'not-my-password',
            'new_password'     => 'a-brand-new-password',
        ]);

        $result->assertStatus(422);
        $hash = (new UserLoginLookup())->findByEmail('ada@example.com')['password_hash'];
        $this->assertTrue(password_verify($this->password, $hash));
    }

    public function testChangingPasswordWorks(): void
    {
        $account = $this->createWorkspace('ada@example.com');

        $this->actingAs($account)->submit('account/password', [
            'current_password' => $this->password,
            'new_password'     => 'a-brand-new-password',
        ])->assertRedirectTo('/dashboard');

        $hash = (new UserLoginLookup())->findByEmail('ada@example.com')['password_hash'];
        $this->assertTrue(password_verify('a-brand-new-password', $hash));
    }

    public function testUserInputIsEscaped(): void
    {
        $account = $this->createWorkspace('ada@example.com');
        $this->db->query('UPDATE users SET name = ? WHERE id = ?', ['<script>alert(1)</script>', $account['user_id']]);

        $result = $this->actingAs($account)->get('members');

        $result->assertDontSee('<script>alert(1)</script>');
        $result->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    public function testPagesGetTheirOwnTitleAndLayoutClass(): void
    {
        $this->get('login')->assertSee('<title>Sign in</title>');
        $this->get('signup')->assertSee('<title>Sign up</title>');
        $this->get('/')->assertSee('<main class="landing">');
    }

    public function testSecurityHeadersAreSent(): void
    {
        $result = $this->get('/');

        $result->assertHeader('X-Content-Type-Options', 'nosniff');
        $result->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $policy = $result->response()->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }
}
