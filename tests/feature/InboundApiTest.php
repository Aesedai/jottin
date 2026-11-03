<?php

namespace Tests\Feature;

use CodeIgniter\Config\Services;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\AuthTestHelpers;

final class InboundApiTest extends CIUnitTestCase
{
    use AuthTestHelpers;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        Services::resetSingle('throttler');
        // The bucket refills over real time, so a slow test run would never empty it.
        service('throttler')->setTestTime(time());
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function createKey(array $account): string
    {
        $body = $this->actingAs($account)->submit('settings/api', ['label' => 'n8n'])->getBody();
        preg_match('/<code>(jott_[0-9a-f]{64})<\/code>/', $body, $match);
        $this->assertArrayHasKey(1, $match, 'The new key should be shown once.');

        return $match[1];
    }

    /** @param array<string, mixed> $payload */
    private function api(?string $key, array $payload): TestResponse
    {
        $this->freshRequest();

        return $this->withSession([])
            ->withHeaders($key === null ? [] : ['Authorization' => "Bearer {$key}"])
            ->withBodyFormat('json')
            ->post('v1/jotts', $payload);
    }

    private function rawApi(string $key, string $body): TestResponse
    {
        $this->freshRequest();

        return $this->withSession([])
            ->withHeaders(['Authorization' => "Bearer {$key}", 'Content-Type' => 'application/json'])
            ->withBody($body)
            ->post('v1/jotts');
    }

    /** @return array<string, mixed> */
    private function json(TestResponse $response): array
    {
        return json_decode((string) $response->getJSON(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testValidKeyCreatesAJott(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $result = $this->api($key, ['text' => 'From the API #inbound']);

        $result->assertStatus(201);
        $body = $this->json($result);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $body['id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['createdAt']);
        $this->assertSame('inbound', $body['collections'][0]['name']);
        $this->assertSame('automatic', $body['collections'][0]['membership']);
        $this->seeInDatabase('jotts', [
            'public_id' => $body['id'],
            'tenant_id' => $ada['tenant_id'],
            'source'    => 'api',
        ]);
    }

    public function testAcceptsWebhookerPayloadAndRepliesWithAMessage(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $result = $this->rawApi($key, json_encode([
            'sentAt' => '2026-09-27T03:46:00.000Z',
            'sender' => 'jordan',
            'text'   => 'Pushed from Webhooker #inbox',
        ], JSON_THROW_ON_ERROR));

        $result->assertStatus(201);
        $this->assertSame('Saved in inbox.', $this->json($result)['message']);
        $this->seeInDatabase('jotts', ['tenant_id' => $ada['tenant_id'], 'text' => 'Pushed from Webhooker #inbox']);
    }

    public function testMessageIsPlainWhenThereAreNoCollections(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $this->assertSame('Saved.', $this->json($this->api($key, ['text' => 'No tags']))['message']);
    }

    public function testExistingTagIsReused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);
        $this->actingAs($ada)->submit('jotts', ['text' => 'From the web #Shared']);

        $body = $this->json($this->api($key, ['text' => 'From the API #shared']));

        $this->assertSame('Shared', $body['collections'][0]['name']);
        $this->seeNumRecords(1, 'collections', ['tenant_id' => $ada['tenant_id']]);
    }

    public function testManualCollectionsCanBeAssigned(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);
        $this->actingAs($ada)->submit('collections', ['name' => 'Inbox']);
        $id = (string) $this->db->query('SELECT public_id FROM collections')->getRow()->public_id;

        $body = $this->json($this->api($key, ['text' => 'No tags here', 'collections' => [$id]]));

        $this->assertSame('manual', $body['collections'][0]['membership']);
        $this->assertSame($id, $body['collections'][0]['id']);
    }

    public function testMissingWrongAndMalformedKeysAreRefused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->createKey($ada);

        foreach ([null, 'jott_wrong', str_repeat('a', 69)] as $key) {
            $result = $this->api($key, ['text' => 'Nope']);

            $result->assertStatus(401);
            $result->assertHeader('WWW-Authenticate', 'Bearer');
        }

        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testRevokedKeyIsRefused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);
        $id  = (string) $this->db->query('SELECT public_id FROM api_credentials')->getRow()->public_id;

        $this->api($key, ['text' => 'Works'])->assertStatus(201);
        $this->actingAs($ada)->submit("settings/api/{$id}/revoke")->assertRedirectTo('/settings/api');

        $this->api($key, ['text' => 'Not any more'])->assertStatus(401);
        $this->seeNumRecords(1, 'jotts', []);
    }

    public function testBodyCannotChooseAnotherTenant(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $grace  = $this->createWorkspace('grace@example.com');
        $keyOfG = $this->createKey($grace);

        $this->api($keyOfG, [
            'text'       => 'Trying to write to Ada',
            'tenant_id'  => $ada['tenant_id'],
            'account_id' => $ada['tenant_id'],
        ])->assertStatus(201);

        $this->seeNumRecords(0, 'jotts', ['tenant_id' => $ada['tenant_id']]);
        $this->seeNumRecords(1, 'jotts', ['tenant_id' => $grace['tenant_id']]);
    }

    public function testAnotherTenantsCollectionIsRefusedAndCreatesNothing(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $grace  = $this->createWorkspace('grace@example.com');
        $keyOfG = $this->createKey($grace);
        $this->actingAs($ada)->submit('collections', ['name' => 'Ada only']);
        $foreign = (string) $this->db->query('SELECT public_id FROM collections')->getRow()->public_id;

        $result = $this->api($keyOfG, ['text' => 'Sneaky', 'collections' => [$foreign]]);

        $result->assertStatus(422);
        $this->assertSame('unknown_collection', $this->json($result)['error']['code']);
        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testMalformedJsonIsRefusedAndCreatesNothing(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $result = $this->rawApi($key, '{"text": ');

        $result->assertStatus(400);
        $this->assertSame('invalid_json', $this->json($result)['error']['code']);
        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testInvalidBodiesGetUsefulErrorsAndCreateNothing(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $cases = [
            [['text' => '   '], 422, 'invalid_text'],
            [['nope' => 'x'], 422, 'invalid_text'],
            [['text' => ['not', 'a', 'string']], 422, 'invalid_text'],
            [['text' => str_repeat('a', 10001)], 422, 'invalid_text'],
            [['text' => 'ok', 'collections' => 'abc'], 422, 'invalid_collections'],
            [['text' => 'ok', 'collections' => ['nope']], 422, 'unknown_collection'],
            [['text' => 'ok', 'collections' => [123]], 422, 'unknown_collection'],
        ];

        foreach ($cases as [$payload, $status, $code]) {
            $result = $this->rawApi($key, json_encode($payload, JSON_THROW_ON_ERROR));
            $result->assertStatus($status);
            $this->assertSame($code, $this->json($result)['error']['code']);
        }

        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testOversizedBodyIsRefused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $this->rawApi($key, json_encode(['text' => str_repeat('a', 70000)], JSON_THROW_ON_ERROR))->assertStatus(413);

        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testKeyIsRateLimited(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        for ($i = 0; $i < 60; $i++) {
            $this->api($key, [])->assertStatus(422);
        }

        $result = $this->api($key, ['text' => 'One too many']);

        $result->assertStatus(429);
        $this->assertSame('rate_limited', $this->json($result)['error']['code']);
        $this->assertNotSame('', $result->response()->getHeaderLine('Retry-After'));
        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testRepeatedFailedKeysAreRateLimited(): void
    {
        $this->createWorkspace('ada@example.com');

        for ($i = 0; $i < 20; $i++) {
            $this->api('jott_wrong', ['text' => 'Guess'])->assertStatus(401);
        }

        $this->api('jott_wrong', ['text' => 'Guess'])->assertStatus(429);
    }

    public function testOnlyAHashOfTheKeyIsStored(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $row = $this->db->query('SELECT * FROM api_credentials')->getRowArray();

        $this->assertSame(hash('sha256', $key), $row['token_hash']);
        $this->assertStringNotContainsString($key, (string) json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function testKeyAndHashAreNeverShownAgain(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);
        $row = $this->db->query('SELECT * FROM api_credentials')->getRowArray();

        $page = $this->actingAs($ada)->get('settings/api');

        $page->assertDontSee($key);
        $page->assertDontSee($row['token_hash']);
        $page->assertSee($row['token_prefix']);
    }

    public function testApiResponsesExposeNoCredentialData(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);

        $body = (string) $this->api($key, ['text' => 'Note #tag'])->getJSON();

        $this->assertStringNotContainsString($key, $body);
        $this->assertStringNotContainsString('tenant', $body);
        $this->assertStringNotContainsString('hash', $body);
    }

    public function testApiChangesAreAuditedAgainstTheKey(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);
        $row = $this->db->query('SELECT * FROM api_credentials')->getRowArray();

        $body = $this->json($this->api($key, ['text' => 'Note']));

        $this->seeInDatabase('audit_log', [
            'action'     => 'jott.created',
            'actor_type' => 'api',
            'actor_id'   => $row['id'],
            'entity_ref' => $body['id'],
        ]);
        $this->seeInDatabase('audit_log', ['action' => 'api_key.created', 'actor_id' => $ada['user_id']]);
        $this->assertStringNotContainsString($key, (string) json_encode(
            $this->db->query('SELECT * FROM audit_log')->getResultArray(),
            JSON_THROW_ON_ERROR
        ));
    }

    public function testLastUsedIsRecorded(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $key = $this->createKey($ada);
        $this->assertNull($this->db->query('SELECT last_used_at FROM api_credentials')->getRow()->last_used_at);

        $this->api($key, ['text' => 'Note']);

        $this->assertNotNull($this->db->query('SELECT last_used_at FROM api_credentials')->getRow()->last_used_at);
    }

    public function testSessionCookieDoesNotAuthenticateTheApi(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $this->actingAs($ada)->withBodyFormat('json')->post('v1/jotts', ['text' => 'Via session'])->assertStatus(401);

        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testMembersCannotManageKeys(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');
        $this->createKey($ada);
        $id = (string) $this->db->query('SELECT public_id FROM api_credentials')->getRow()->public_id;

        $this->actingAs($member)->get('settings/api')->assertStatus(403);
        $this->actingAs($member)->submit('settings/api', ['label' => 'x'])->assertStatus(403);
        $this->actingAs($member)->submit("settings/api/{$id}/revoke")->assertStatus(403);

        $this->seeNumRecords(1, 'api_credentials', []);
        $this->seeInDatabase('api_credentials', ['public_id' => $id, 'revoked_at' => null]);
    }

    public function testAnotherTenantCannotSeeOrRevokeAKey(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $this->createKey($ada);
        $row = $this->db->query('SELECT * FROM api_credentials')->getRowArray();

        $this->actingAs($grace)->get('settings/api')->assertDontSee($row['token_prefix']);

        try {
            $this->actingAs($grace)->submit("settings/api/{$row['public_id']}/revoke");
            $this->fail('Expected a 404 for another tenant\'s key.');
        } catch (PageNotFoundException) {
            $this->seeInDatabase('api_credentials', ['public_id' => $row['public_id'], 'revoked_at' => null]);
        }
    }
}
