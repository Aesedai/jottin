<?php

namespace Tests\Feature;

use App\Models\UserLoginLookup;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTestHelpers;
use Tests\Support\CleanDatabase;

final class MembersTest extends CIUnitTestCase
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

    public function testOwnerSeesOnlyTheirOwnTenantsMembers(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $this->createMember($grace['tenant_id'], 'hopper-team@example.com');

        $result = $this->actingAs($ada)->get('members');

        $result->assertStatus(200);
        $result->assertSee('ada@example.com');
        $result->assertDontSee('grace@example.com');
        $result->assertDontSee('hopper-team@example.com');
    }

    public function testPasswordHashesAreNeverRendered(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $hash = (new UserLoginLookup())->findByEmail('ada@example.com')['password_hash'];

        $this->actingAs($ada)->get('members')->assertDontSee($hash);
    }

    public function testMemberCannotViewOrManageMembers(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');
        $other  = $this->createMember($ada['tenant_id'], 'other@example.com');

        $this->actingAs($member)->get('members')->assertStatus(403);
        $this->actingAs($member)->submit('members', ['name' => 'X', 'email' => 'x@example.com'])->assertStatus(403);
        $this->actingAs($member)->submit("members/{$other['user_id']}/remove")->assertStatus(403);

        $this->dontSeeInDatabase('users', ['email' => 'x@example.com']);
        $this->seeInDatabase('users', ['email' => 'other@example.com']);
    }

    public function testOwnerAddsAMemberWhoCanSignIn(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $result = $this->actingAs($ada)->submit('members', ['name' => 'Mem', 'email' => 'Mem@Example.com']);

        $result->assertStatus(201);
        $row = (new UserLoginLookup())->findByEmail('mem@example.com');
        $this->assertSame($ada['tenant_id'], (int) $row['tenant_id']);
        $this->seeInDatabase('users', ['email' => 'mem@example.com', 'role' => 'member']);

        preg_match('/<code>([0-9a-f]{24})<\/code>/', $result->getBody(), $match);
        $this->assertArrayHasKey(1, $match, 'The generated password should be shown once.');

        $this->freshRequest();
        $this->withSession([$this->tokenName() => $this->token()])
            ->submit('login', ['email' => 'mem@example.com', 'password' => $match[1]])
            ->assertRedirectTo('/dashboard');
    }

    public function testRequestCannotChooseANewMembersTenantOrRole(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');

        $this->actingAs($ada)->submit('members', [
            'name'      => 'Mem',
            'email'     => 'mem@example.com',
            'tenant_id' => $grace['tenant_id'],
            'role'      => 'owner',
        ]);

        $row = (new UserLoginLookup())->findByEmail('mem@example.com');
        $this->assertSame($ada['tenant_id'], (int) $row['tenant_id']);
        $this->seeInDatabase('users', ['email' => 'mem@example.com', 'role' => 'member']);
    }

    public function testEmailAlreadyUsedInAnotherTenantIsRefused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->createWorkspace('grace@example.com');

        $this->actingAs($ada)->submit('members', ['name' => 'G', 'email' => 'grace@example.com'])->assertStatus(422);
    }

    public function testOwnerRemovesAMemberAndTheirSessionStopsWorking(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');

        $this->actingAs($member)->get('dashboard')->assertStatus(200);
        $this->actingAs($ada)->submit("members/{$member['user_id']}/remove")->assertRedirectTo('/members');

        $this->dontSeeInDatabase('users', ['email' => 'mem@example.com']);
        $this->actingAs($member)->get('dashboard')->assertRedirectTo('/login');
    }

    public function testOwnerCannotBeRemoved(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $this->actingAs($ada)->submit("members/{$ada['user_id']}/remove")->assertRedirectTo('/members');

        $this->seeInDatabase('users', ['email' => 'ada@example.com']);
    }

    public function testOwnerCannotRemoveAnotherTenantsMember(): void
    {
        $ada     = $this->createWorkspace('ada@example.com');
        $grace   = $this->createWorkspace('grace@example.com');
        $foreign = $this->createMember($grace['tenant_id'], 'foreign@example.com');

        try {
            $this->actingAs($ada)->submit("members/{$foreign['user_id']}/remove");
            $this->fail('Expected a 404 for another tenant\'s member.');
        } catch (PageNotFoundException) {
            $this->seeInDatabase('users', ['email' => 'foreign@example.com']);
        }
    }
}
