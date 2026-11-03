<?php

namespace Tests\Feature;

use App\Libraries\Delivery\DeliveryRunner;
use App\Libraries\Delivery\TransportResult;
use App\Libraries\Delivery\WebhookConfigService;
use CodeIgniter\Config\Services;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\AuthTestHelpers;
use Tests\Support\CleanDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\Support\FakeWebhookTransport;

final class DeliveryTest extends CIUnitTestCase
{
    use AuthTestHelpers;
    use DatabaseTestTrait;
    use CleanDatabase;
    use FeatureTestTrait;

    private const URL = 'https://hooks.example.com/in?token=super-secret-token';

    protected $migrate = false;
    protected $refresh = false;

    private FakeWebhookTransport $transport;
    private FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeWebhookTransport();
        $this->dns       = new FakeDnsResolver();
        Services::injectMock('webhookTransport', $this->transport);
        Services::injectMock('dnsResolver', $this->dns);
    }

    /**
     * Each test account starts with a jott tagged #hook, which creates the collection.
     *
     * @param array{tenant_id: int, user_id: int} $account
     */
    private function collectionFor(array $account, string $tag = 'hook'): string
    {
        $this->actingAs($account)->submit('jotts', ['text' => "Seed #{$tag}"]);

        return (string) $this->db->query(
            'SELECT public_id FROM collections WHERE tenant_id = ? AND tag_key = ?',
            [$account['tenant_id'], $tag]
        )->getRow()->public_id;
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function configure(array $account, string $collection, string $mode, string $url = self::URL): TestResponse
    {
        return $this->actingAs($account)->submit("collections/{$collection}/webhook", ['url' => $url, 'mode' => $mode]);
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function jott(array $account, string $text): string
    {
        $this->actingAs($account)->submit('jotts', ['text' => $text]);

        return (string) $this->db->query(
            'SELECT public_id FROM jotts WHERE tenant_id = ? ORDER BY id DESC LIMIT 1',
            [$account['tenant_id']]
        )->getRow()->public_id;
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function send(array $account, string $collection, string ...$jotts): TestResponse
    {
        return $this->actingAs($account)->submit(
            "collections/{$collection}/send",
            ['jotts' => $jotts, 'confirm' => '1']
        );
    }

    private function stateOf(string $jott): ?string
    {
        $row = $this->db->query(
            'SELECT s.state FROM delivery_states s JOIN jotts j ON j.id = s.jott_id WHERE j.public_id = ?',
            [$jott]
        )->getRow();

        return $row?->state;
    }

    private function runWorker(): int
    {
        $this->freshRequest();

        return (new DeliveryRunner())->runOnce();
    }

    // ----- Configuration

    public function testOwnerConfiguresAWebhookAndSeesTheSecretOnce(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);

        $result = $this->configure($ada, $id, 'manual');

        $result->assertStatus(201);
        $this->assertMatchesRegularExpression('/<code>[0-9a-f]{64}<\/code>/', $result->getBody());
        $this->actingAs($ada)->get("collections/{$id}/settings")->assertDontSeeElement('.alert-ok');
    }

    public function testSecondSaveReplacesTheOnlyWebhook(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');

        $this->configure($ada, $id, 'automatic', 'https://other.example.com/new')
            ->assertRedirectTo("/collections/{$id}/settings");

        $this->seeNumRecords(1, 'collection_webhooks', ['tenant_id' => $ada['tenant_id']]);
        $this->seeInDatabase('collection_webhooks', ['endpoint_host' => 'other.example.com', 'mode' => 'automatic']);
    }

    public function testBlankUrlKeepsTheExistingOne(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');

        $this->configure($ada, $id, 'disabled', '');

        $this->seeInDatabase('collection_webhooks', ['endpoint_host' => 'hooks.example.com', 'mode' => 'disabled']);
    }

    public function testUnsafeTargetsAreRefusedAndNothingIsSaved(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);

        foreach (['not a url', 'http://localhost/x', 'http://192.168.1.37/x', 'http://169.254.169.254/x'] as $url) {
            $this->configure($ada, $id, 'manual', $url)->assertStatus(422);
        }

        $this->seeNumRecords(0, 'collection_webhooks', []);
    }

    public function testInvalidModeIsRefused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);

        $this->configure($ada, $id, 'sometimes')->assertStatus(422);

        $this->seeNumRecords(0, 'collection_webhooks', []);
    }

    public function testUrlAndSecretAreEncryptedAtRestAndNeverRendered(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');

        $row = $this->db->query('SELECT * FROM collection_webhooks')->getRowArray();

        $this->assertStringNotContainsString('super-secret-token', (string) json_encode($row, JSON_THROW_ON_ERROR));
        $this->assertSame(self::URL, WebhookConfigService::decrypt($row['endpoint_encrypted']));

        $page = $this->actingAs($ada)->get("collections/{$id}/settings");
        $page->assertDontSee('super-secret-token');
        $page->assertDontSee(WebhookConfigService::decrypt($row['secret_encrypted']));
        $page->assertSee('hooks.example.com');
    }

    public function testMemberCannotConfigureButCanSeeTheMode(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');
        $id     = $this->collectionFor($ada);

        $this->configure($member, $id, 'manual')->assertStatus(403);

        $this->seeNumRecords(0, 'collection_webhooks', []);
        $this->actingAs($member)->get("collections/{$id}/settings")->assertSee('Only the workspace owner');
    }

    public function testAnotherTenantCannotViewOrChangeAWebhook(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $id    = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');

        $attempts = [
            fn () => $this->actingAs($grace)->get("collections/{$id}/settings"),
            fn () => $this->configure($grace, $id, 'automatic', 'https://attacker.example.com/'),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Expected a 404 for another tenant\'s collection.');
            } catch (PageNotFoundException) {
                $this->seeInDatabase('collection_webhooks', [
                    'endpoint_host' => 'hooks.example.com',
                    'mode'          => 'manual',
                ]);
            }
        }
    }

    // ----- Manual delivery and re-arm

    public function testManualModeSendsNothingUntilAskedTo(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');

        $this->jott($ada, 'New note #hook');
        $this->runWorker();

        $this->assertSame([], $this->transport->requests);
    }

    public function testFirstPostOnlyAsksForConfirmation(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');

        $result = $this->actingAs($ada)->submit("collections/{$id}/send", ['jotts' => [$jott]]);

        $result->assertSee('Send 1 jott?');
        $result->assertSee('hooks.example.com');
        $this->assertSame([], $this->transport->requests);
        $this->seeNumRecords(0, 'delivery_attempts', []);
    }

    public function testManualSendDeliversAndRecordsHistory(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');

        $this->send($ada, $id, $jott)->assertRedirectTo("/collections/{$id}");

        $this->assertCount(1, $this->transport->requests);
        $this->seeInDatabase('delivery_attempts', [
            'jott_ref'     => $jott,
            'trigger_type' => 'manual',
            'status'       => 'succeeded',
            'http_status'  => 200,
        ]);
        $this->assertSame('succeeded', $this->stateOf($jott));

        $history = $this->actingAs($ada)->get("collections/{$id}/settings");
        $history->assertSee('Delivered');
        $history->assertSee('Manual');
        $history->assertSee($jott);
        $history->assertSee('HTTP 200');
    }

    public function testPayloadMatchesTheEnvelopeAndIsSigned(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $response = $this->configure($ada, $id, 'manual');
        preg_match('/<code>([0-9a-f]{64})<\/code>/', $response->getBody(), $match);
        $jott = $this->jott($ada, 'Say héllo #hook');

        $this->send($ada, $id, $jott);

        $request = $this->transport->requests[0];
        $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('jott.delivery', $payload['event']);
        $this->assertSame($jott, $payload['jott']['id']);
        $this->assertSame('Say héllo #hook', $payload['jott']['text']);
        $this->assertSame($id, $payload['collection']['id']);
        $this->assertSame($payload['deliveryId'], $request['headers']['X-Jottin-Delivery']);
        $this->assertSame(self::URL, $request['url']);

        $signed = $request['headers']['X-Jottin-Timestamp'] . '.' . $request['body'];
        $expected = 'sha256=' . hash_hmac('sha256', $signed, $match[1]);
        $this->assertSame($expected, $request['headers']['X-Jottin-Signature']);
    }

    public function testSentJottCannotBeSentAgainUntilReArmed(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');

        $this->send($ada, $id, $jott);
        $this->send($ada, $id, $jott);

        $this->assertCount(1, $this->transport->requests);
        $this->seeNumRecords(1, 'delivery_attempts', []);

        $this->actingAs($ada)->get("collections/{$id}")->assertDontSee("select-{$jott}");

        $this->actingAs($ada)->submit("collections/{$id}/jotts/{$jott}/rearm")->assertRedirectTo("/collections/{$id}");
        $this->assertSame('rearmed', $this->stateOf($jott));
        $this->actingAs($ada)->get("collections/{$id}")->assertSee("select-{$jott}");

        $this->send($ada, $id, $jott);

        $this->assertCount(2, $this->transport->requests);
        $this->seeNumRecords(2, 'delivery_attempts', []);
    }

    public function testReArmIsAuditedAndOnlyAppliesToSentJotts(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');

        $this->actingAs($ada)->submit("collections/{$id}/jotts/{$jott}/rearm");
        $this->dontSeeInDatabase('audit_log', ['action' => 'jott.rearmed']);

        $this->send($ada, $id, $jott);
        $this->actingAs($ada)->submit("collections/{$id}/jotts/{$jott}/rearm");
        $this->seeInDatabase('audit_log', ['action' => 'jott.rearmed', 'entity_ref' => $jott]);
    }

    public function testFailedSendIsReportedAsFailed(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');
        $this->transport->next = new TransportResult(500, 'The endpoint returned HTTP 500.');

        $this->send($ada, $id, $jott);

        $this->seeInDatabase('delivery_attempts', [
            'jott_ref'      => $jott,
            'status'        => 'failed',
            'http_status'   => 500,
            'error_summary' => 'The endpoint returned HTTP 500.',
        ]);
        $this->assertSame('failed', $this->stateOf($jott));

        $page = $this->actingAs($ada)->get("collections/{$id}");
        $page->assertSee('Failed');
        $page->assertDontSee('Delivered');
    }

    public function testFailedJottNeedsReArmingToo(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');
        $this->transport->next = new TransportResult(null, 'The endpoint did not respond in time.');

        $this->send($ada, $id, $jott);
        $this->send($ada, $id, $jott);

        $this->assertCount(1, $this->transport->requests);
    }

    public function testStatesAreIndependentPerCollection(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $one = $this->collectionFor($ada, 'one');
        $two = $this->collectionFor($ada, 'two');
        $this->configure($ada, $one, 'manual');
        $this->configure($ada, $two, 'manual', 'https://two.example.com/in');
        $jott = $this->jott($ada, 'Both #one #two');

        $this->send($ada, $one, $jott);

        $this->actingAs($ada)->get("collections/{$two}")->assertSee("select-{$jott}");
        $this->actingAs($ada)->get("collections/{$one}")->assertDontSee("select-{$jott}");
    }

    public function testMemberCanSendAndReArm(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');
        $id     = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');

        $this->send($member, $id, $jott);
        $this->actingAs($member)->submit("collections/{$id}/jotts/{$jott}/rearm");

        $this->assertCount(1, $this->transport->requests);
        $this->assertSame('rearmed', $this->stateOf($jott));
    }

    public function testJottOutsideTheCollectionIsNotSent(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $id    = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $other = $this->jott($ada, 'Not in that collection');

        $this->send($ada, $id, $other);

        $this->assertSame([], $this->transport->requests);
    }

    public function testDisabledAndAutomaticCollectionsRefuseManualSend(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $jott = $this->jott($ada, 'New note #hook');

        foreach (['disabled', 'automatic'] as $mode) {
            $this->configure($ada, $id, $mode);
            $this->send($ada, $id, $jott);
        }

        $this->assertSame([], $this->transport->requests);
    }

    public function testNotConfiguredCollectionExplainsItself(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);

        $this->actingAs($ada)->get("collections/{$id}")->assertSee('No webhook is configured');
    }

    public function testSelectionIsCappedPerRequest(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $ids = array_map(static fn (int $i): string => str_pad((string) $i, 24, '0'), range(1, 11));

        $this->send($ada, $id, ...$ids);

        $this->assertSame([], $this->transport->requests);
    }

    public function testAnotherTenantCannotSendOrReArm(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $id    = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott  = $this->jott($ada, 'Ada note #hook');
        $this->send($ada, $id, $jott);

        $attempts = [
            fn () => $this->send($grace, $id, $jott),
            fn () => $this->actingAs($grace)->submit("collections/{$id}/jotts/{$jott}/rearm"),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Expected a 404 for another tenant\'s collection.');
            } catch (PageNotFoundException) {
                $this->assertCount(1, $this->transport->requests);
                $this->assertSame('succeeded', $this->stateOf($jott));
            }
        }
    }

    public function testCannotSendAnotherTenantsJottThroughYourOwnWebhook(): void
    {
        $ada     = $this->createWorkspace('ada@example.com');
        $grace   = $this->createWorkspace('grace@example.com');
        $foreign = $this->jott($ada, 'Ada private note');
        $id      = $this->collectionFor($grace);
        $this->configure($grace, $id, 'manual', 'https://attacker.example.com/in');

        $this->send($grace, $id, $foreign);

        $this->assertSame([], $this->transport->requests);
    }

    public function testDnsChangingToAPrivateAddressBlocksTheSend(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');

        $this->dns->records['hooks.example.com'] = ['169.254.169.254'];
        $this->send($ada, $id, $jott);

        $this->assertSame([], $this->transport->requests);
        $this->assertSame('failed', $this->stateOf($jott));
        $this->seeInDatabase('delivery_attempts', ['jott_ref' => $jott, 'status' => 'failed', 'http_status' => null]);
    }

    public function testRequestsConnectToTheAddressThatWasChecked(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'New note #hook');
        $this->dns->records['hooks.example.com'] = ['93.184.216.99'];

        $this->send($ada, $id, $jott);

        $this->assertSame('93.184.216.99', $this->transport->requests[0]['target']['address']);
    }

    public function testHistoryNeverContainsSecretsOrNoteText(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'Confidential diagnosis #hook');
        $this->send($ada, $id, $jott);

        foreach (['delivery_attempts', 'audit_log'] as $table) {
            $rows = json_encode($this->db->query("SELECT * FROM {$table}")->getResultArray(), JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString('super-secret-token', $rows);
            $this->assertStringNotContainsString('diagnosis', $rows);
        }
    }

    public function testHistorySurvivesDeletingTheJott(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'manual');
        $jott = $this->jott($ada, 'Goodbye #hook');
        $this->send($ada, $id, $jott);

        $this->actingAs($ada)->submit("jotts/{$jott}/delete");

        $this->seeInDatabase('delivery_attempts', ['jott_ref' => $jott]);
    }

    // ----- Automatic delivery

    public function testNewWebJottQueuesAndDeliversOnce(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');

        $jott = $this->jott($ada, 'New note #hook');

        // Nothing is sent inside the web request.
        $this->assertSame([], $this->transport->requests);
        $this->seeInDatabase('delivery_attempts', [
            'jott_ref'     => $jott,
            'status'       => 'queued',
            'trigger_type' => 'automatic',
        ]);

        $this->assertSame(1, $this->runWorker());
        $this->assertCount(1, $this->transport->requests);
        $this->seeInDatabase('delivery_attempts', ['jott_ref' => $jott, 'status' => 'succeeded', 'http_status' => 200]);

        // Running again finds nothing to do.
        $this->assertSame(0, $this->runWorker());
        $this->assertCount(1, $this->transport->requests);
    }

    public function testNewApiJottDeliversOnce(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $body = $this->actingAs($ada)->submit('settings/api', ['label' => 'x'])->getBody();
        preg_match('/<code>(jott_[0-9a-f]{64})<\/code>/', $body, $match);

        $this->freshRequest();
        $response = $this->withSession([])
            ->withHeaders(['Authorization' => "Bearer {$match[1]}"])
            ->withBodyFormat('json')
            ->post('v1/jotts', ['text' => 'From the API #hook']);
        $response->assertStatus(201);

        $this->runWorker();

        $this->assertCount(1, $this->transport->requests);
        $this->assertStringContainsString('From the API', $this->transport->requests[0]['body']);
    }

    public function testDisabledModeQueuesNothing(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->collectionFor($ada);
        $this->configure($ada, $id, 'disabled');

        $this->jott($ada, 'New note #hook');
        $this->runWorker();

        $this->seeNumRecords(0, 'delivery_attempts', []);
        $this->assertSame([], $this->transport->requests);
    }

    public function testEditingAMemberJottDoesNotDeliverAgain(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $jott = $this->jott($ada, 'New note #hook');
        $this->runWorker();

        $this->actingAs($ada)->submit("jotts/{$jott}", ['text' => 'Edited note #hook']);
        $this->runWorker();

        $this->assertCount(1, $this->transport->requests);
    }

    public function testEditingATagInOrAddingManuallyDoesNotDeliver(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $jott = $this->jott($ada, 'No tag yet');

        $this->actingAs($ada)->submit("jotts/{$jott}", ['text' => 'Now tagged #hook']);
        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['collection' => $id]);
        $this->runWorker();

        $this->seeNumRecords(0, 'delivery_attempts', []);
    }

    public function testAutomaticFailureIsRecorded(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $jott = $this->jott($ada, 'New note #hook');
        $this->transport->next = new TransportResult(null, 'The endpoint did not respond in time.');

        $this->runWorker();

        $this->seeInDatabase('delivery_attempts', [
            'jott_ref'      => $jott,
            'trigger_type'  => 'automatic',
            'status'        => 'failed',
            'error_summary' => 'The endpoint did not respond in time.',
        ]);
        $this->actingAs($ada)->get("collections/{$id}/settings")->assertSee('Automatic');
    }

    public function testTurningOffAWebhookStopsQueuedDeliveries(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $jott = $this->jott($ada, 'New note #hook');

        $this->configure($ada, $id, 'disabled', '');
        $this->runWorker();

        $this->assertSame([], $this->transport->requests);
        $this->seeInDatabase('delivery_attempts', ['jott_ref' => $jott, 'status' => 'failed']);
    }

    public function testDeletingAQueuedJottStopsItsDelivery(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $jott = $this->jott($ada, 'Delete me #hook');

        $this->actingAs($ada)->submit("jotts/{$jott}/delete");
        $this->runWorker();

        $this->assertSame([], $this->transport->requests);
        $this->seeInDatabase('delivery_attempts', ['jott_ref' => $jott, 'status' => 'failed']);
    }

    public function testWorkerKeepsEachTenantsDataAndWebhookTogether(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $this->configure($ada, $this->collectionFor($ada), 'automatic', 'https://ada.example.com/in');
        $this->configure($grace, $this->collectionFor($grace), 'automatic', 'https://grace.example.com/in');

        $this->jott($ada, 'Ada secret #hook');
        $this->jott($grace, 'Grace secret #hook');
        $this->runWorker();

        $this->assertCount(2, $this->transport->requests);

        foreach ($this->transport->requests as $request) {
            if (str_contains($request['url'], 'ada.example.com')) {
                $this->assertStringContainsString('Ada secret', $request['body']);
                $this->assertStringNotContainsString('Grace', $request['body']);
            } else {
                $this->assertStringContainsString('Grace secret', $request['body']);
                $this->assertStringNotContainsString('Ada', $request['body']);
            }
        }

        $this->assertFalse(service('tenantContext')->hasTenant(), 'The worker must leave no tenant behind.');
    }

    public function testAbandonedSendIsReportedFailedAndNotRetried(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $id   = $this->collectionFor($ada);
        $this->configure($ada, $id, 'automatic');
        $jott = $this->jott($ada, 'New note #hook');
        $this->db->query(
            "UPDATE delivery_attempts SET status = 'sending', started_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
        );
        $this->db->query("UPDATE delivery_states SET state = 'sending'");

        $this->runWorker();

        $this->assertSame([], $this->transport->requests);
        $this->seeInDatabase('delivery_attempts', ['jott_ref' => $jott, 'status' => 'failed']);
        $this->assertSame('failed', $this->stateOf($jott));
    }
}
