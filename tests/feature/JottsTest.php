<?php

namespace Tests\Feature;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Jotts\JottService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;
use Tests\Support\AuthTestHelpers;

final class JottsTest extends CIUnitTestCase
{
    use AuthTestHelpers;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';

    /** @param array{tenant_id: int, user_id: int} $account */
    private function jottAs(array $account, string $text): string
    {
        $this->actingAs($account)->submit('jotts', ['text' => $text])->assertRedirectTo('/dashboard');

        return (string) $this->db->query(
            'SELECT public_id FROM jotts WHERE tenant_id = ? ORDER BY id DESC LIMIT 1',
            [$account['tenant_id']]
        )->getRow()->public_id;
    }

    /**
     * @param array{tenant_id: int, user_id: int} $account
     *
     * @return list<string> Tag keys the jott currently belongs to.
     */
    private function tagsOf(array $account, string $publicId): array
    {
        $rows = $this->db->query(
            'SELECT c.tag_key FROM jott_collections m
             JOIN collections c ON c.id = m.collection_id
             JOIN jotts j ON j.id = m.jott_id
             WHERE j.public_id = ? AND j.tenant_id = ? ORDER BY c.tag_key',
            [$publicId, $account['tenant_id']]
        )->getResultArray();

        return array_column($rows, 'tag_key');
    }

    public function testSavingAJottWithTwoTagsFilesItInBoth(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $id = $this->jottAs($ada, 'Prepare examples for #Interview and #SaaS');

        $this->assertSame(['interview', 'saas'], $this->tagsOf($ada, $id));
        $this->seeInDatabase('collections', ['tenant_id' => $ada['tenant_id'], 'name' => 'Interview']);
    }

    public function testExistingTagIsReusedWithoutADuplicate(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $this->jottAs($ada, 'First #interview');
        $this->jottAs($ada, 'Second #Interview');

        $this->seeNumRecords(1, 'collections', ['tenant_id' => $ada['tenant_id']]);
    }

    public function testRepeatedTagGivesOneMembership(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $this->jottAs($ada, '#interview #interview #INTERVIEW');

        $this->seeNumRecords(1, 'jott_collections', ['tenant_id' => $ada['tenant_id']]);
    }

    public function testAccentedAndPlainTagsAreSeparateCollections(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $this->jottAs($ada, '#cafe and #café');

        $this->seeNumRecords(2, 'collections', ['tenant_id' => $ada['tenant_id']]);
    }

    public function testSameTagInTwoTenantsIsTwoCollections(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');

        $this->jottAs($ada, '#shared');
        $this->jottAs($grace, '#shared');

        $this->seeNumRecords(2, 'collections', ['tag_key' => 'shared']);
    }

    public function testEditingOutATagRemovesOnlyThatMembership(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->jottAs($ada, 'Prepare #interview and #saas');

        $this->actingAs($ada)->submit("jotts/{$id}", ['text' => 'Prepare #interview']);

        $this->assertSame(['interview'], $this->tagsOf($ada, $id));
        // The collection itself stays, even when empty.
        $this->seeInDatabase('collections', ['tenant_id' => $ada['tenant_id'], 'tag_key' => 'saas']);
    }

    public function testManualMembershipSurvivesRemovingTheHashtag(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->jottAs($ada, 'Prepare #interview');
        $this->db->query('UPDATE jott_collections SET is_manual = 1 WHERE tenant_id = ?', [$ada['tenant_id']]);

        $this->actingAs($ada)->submit("jotts/{$id}", ['text' => 'Prepare']);

        $this->assertSame(['interview'], $this->tagsOf($ada, $id));
        $this->seeInDatabase('jott_collections', [
            'tenant_id'    => $ada['tenant_id'],
            'is_automatic' => 0,
            'is_manual'    => 1,
        ]);
    }

    public function testBlankTextIsRefusedAndCreatesNothing(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $result = $this->actingAs($ada)->submit('jotts', ['text' => "   \n  "]);

        $result->assertStatus(422);
        $result->assertSee('Write something first.');
        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testFeedIsNewestFirstAndEscaped(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->jottAs($ada, 'older note');
        $this->jottAs($ada, '<script>alert(1)</script> newer note');

        $result = $this->actingAs($ada)->get('dashboard');
        $body   = $result->getBody();

        $this->assertLessThan(strpos($body, 'older note'), strpos($body, 'newer note'));
        $result->assertDontSee('<script>alert(1)</script>');
    }

    public function testDetailShowsTimesAndSource(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->jottAs($ada, 'Note #tag');

        $result = $this->actingAs($ada)->get("jotts/{$id}");

        $result->assertSee('From hashtags');
        $result->assertSee('Last changed');
        $result->assertSee('Web');
    }

    public function testDeleteRequiresConfirmationPageThenRemovesTheJott(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->jottAs($ada, 'Goodbye #tag');

        $this->actingAs($ada)->get("jotts/{$id}/delete")->assertSee('Delete this jott?');
        $this->seeInDatabase('jotts', ['public_id' => $id]);

        $this->actingAs($ada)->submit("jotts/{$id}/delete")->assertRedirectTo('/dashboard');

        $this->dontSeeInDatabase('jotts', ['public_id' => $id]);
        $this->seeNumRecords(0, 'jott_collections', ['tenant_id' => $ada['tenant_id']]);
    }

    public function testAnotherTenantCannotSeeAJottInTheirFeed(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $this->jottAs($ada, 'Ada private note #secret');

        $result = $this->actingAs($grace)->get('dashboard');

        $result->assertDontSee('Ada private note');
        $result->assertDontSee('secret');
    }

    public function testAnotherTenantCannotOpenEditOrDeleteAJott(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $id    = $this->jottAs($ada, 'Ada private note');

        $attempts = [
            fn () => $this->actingAs($grace)->get("jotts/{$id}"),
            fn () => $this->actingAs($grace)->submit("jotts/{$id}", ['text' => 'hacked']),
            fn () => $this->actingAs($grace)->get("jotts/{$id}/delete"),
            fn () => $this->actingAs($grace)->submit("jotts/{$id}/delete"),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Expected a 404 for another tenant\'s jott.');
            } catch (PageNotFoundException) {
                $this->seeInDatabase('jotts', ['public_id' => $id, 'text' => 'Ada private note']);
            }
        }
    }

    public function testRequestCannotChooseTheTenantOrSource(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');

        $forged = ['tenant_id' => $grace['tenant_id'], 'source' => 'api'];
        $this->actingAs($ada)->submit('jotts', ['text' => 'Mine'] + $forged);

        $this->seeInDatabase('jotts', ['tenant_id' => $ada['tenant_id'], 'text' => 'Mine', 'source' => 'web']);
        $this->seeNumRecords(0, 'jotts', ['tenant_id' => $grace['tenant_id']]);
    }

    public function testGuestCannotCreateAJott(): void
    {
        $this->freshRequest();
        $this->withSession([$this->tokenName() => $this->token()])
            ->submit('jotts', ['text' => 'anonymous'])
            ->assertRedirectTo('/login');

        $this->seeNumRecords(0, 'jotts', []);
    }

    public function testChangeRollsBackIfItsAuditRecordFails(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        service('tenantContext')->set($ada['tenant_id']);

        $failingAudit = new class () extends AuditLogger {
            public function record(string $action, string $entityType, string $entityRef, array $detail = []): void
            {
                throw new RuntimeException('audit unavailable');
            }
        };

        try {
            (new JottService(audit: $failingAudit))->create('Note #tag');
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException) {
            $this->seeNumRecords(0, 'jotts', []);
            $this->seeNumRecords(0, 'collections', []);
        }
    }
}
