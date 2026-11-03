<?php

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTestHelpers;

final class CollectionsTest extends CIUnitTestCase
{
    use AuthTestHelpers;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';

    /** @param array{tenant_id: int, user_id: int} $account */
    private function jott(array $account, string $text): string
    {
        $this->actingAs($account)->submit('jotts', ['text' => $text]);

        return $this->lastPublicId('jotts', $account);
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function manualCollection(array $account, string $name): string
    {
        $this->actingAs($account)->submit('collections', ['name' => $name]);

        return $this->lastPublicId('collections', $account);
    }

    /** @param array{tenant_id: int, user_id: int} $account */
    private function lastPublicId(string $table, array $account): string
    {
        return (string) $this->db->query(
            "SELECT public_id FROM {$table} WHERE tenant_id = ? ORDER BY id DESC LIMIT 1",
            [$account['tenant_id']]
        )->getRow()->public_id;
    }

    private function collectionIdForTag(string $tag): string
    {
        return (string) $this->db->query('SELECT public_id FROM collections WHERE tag_key = ?', [$tag])
            ->getRow()->public_id;
    }

    /** @return array<string, mixed>|null */
    private function membership(string $jott, string $collection): ?array
    {
        return $this->db->query(
            'SELECT m.is_automatic, m.is_manual FROM jott_collections m
             JOIN jotts j ON j.id = m.jott_id JOIN collections c ON c.id = m.collection_id
             WHERE j.public_id = ? AND c.public_id = ?',
            [$jott, $collection]
        )->getRowArray();
    }

    private function assertReasons(string $jott, string $collection, bool $automatic, bool $manual): void
    {
        $row = $this->membership($jott, $collection);

        $this->assertNotNull($row);
        $this->assertSame($automatic, (int) $row['is_automatic'] === 1);
        $this->assertSame($manual, (int) $row['is_manual'] === 1);
    }

    public function testCreateAndViewAnEmptyManualCollection(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $id = $this->manualCollection($ada, 'Read later');

        $result = $this->actingAs($ada)->get("collections/{$id}");
        $result->assertStatus(200);
        $result->assertSee('Read later');
        $result->assertSee('No jotts in this collection yet.');
        $this->seeInDatabase('collections', ['public_id' => $id, 'tag_key' => null]);
    }

    public function testBlankCollectionNameIsRefused(): void
    {
        $ada = $this->createWorkspace('ada@example.com');

        $this->actingAs($ada)->submit('collections', ['name' => '   '])->assertStatus(422);

        $this->seeNumRecords(0, 'collections', []);
    }

    public function testCollectionViewShowsOnlyItsMembers(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->jott($ada, 'In the first #alpha');
        $this->jott($ada, 'In the second #beta');

        $result = $this->actingAs($ada)->get('collections/' . $this->collectionIdForTag('alpha'));

        $result->assertSee('In the first');
        $result->assertDontSee('In the second');
    }

    public function testNavigationListsCollectionsWithCounts(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->jott($ada, 'One #alpha');
        $this->jott($ada, 'Two #alpha');
        $this->manualCollection($ada, 'Empty one');

        $body = $this->actingAs($ada)->get('collections')->getBody();

        $this->assertMatchesRegularExpression('/alpha.*?2 jotts/s', $body);
        $this->assertMatchesRegularExpression('/Empty one.*?0 jotts/s', $body);
    }

    public function testRenameKeepsTheTagSoTheHashtagStillFilesThere(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->jott($ada, 'First #interview');
        $id = $this->collectionIdForTag('interview');

        $this->actingAs($ada)
            ->submit("collections/{$id}/settings", ['name' => 'Job search', 'tag_key' => 'hacked'])
            ->assertRedirectTo("/collections/{$id}/settings");

        $this->seeInDatabase('collections', [
            'public_id' => $id,
            'name'      => 'Job search',
            'tag_key'   => 'interview',
        ]);

        $this->jott($ada, 'Second #interview');

        $this->seeNumRecords(1, 'collections', ['tenant_id' => $ada['tenant_id']]);
        $this->actingAs($ada)->get("collections/{$id}")->assertSee('Second');
    }

    public function testManualAddAndRemove(): void
    {
        $ada        = $this->createWorkspace('ada@example.com');
        $jott       = $this->jott($ada, 'Plain note');
        $collection = $this->manualCollection($ada, 'Read later');

        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['collection' => $collection])
            ->assertRedirectTo("/jotts/{$jott}");

        $this->assertReasons($jott, $collection, automatic: false, manual: true);
        $this->actingAs($ada)->get("jotts/{$jott}")->assertSee('Added manually');

        $this->actingAs($ada)->submit("jotts/{$jott}/collections/{$collection}/remove");

        $this->assertNull($this->membership($jott, $collection));
    }

    public function testNewCollectionCanBeCreatedFromAJott(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $jott = $this->jott($ada, 'Plain note');

        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['new_collection' => 'Fresh']);

        $id = $this->lastPublicId('collections', $ada);
        $this->seeInDatabase('collections', ['public_id' => $id, 'name' => 'Fresh', 'tag_key' => null]);
        $this->assertNotNull($this->membership($jott, $id));
    }

    public function testManualAndHashtagReasonsAreIndependentWhenRemovingTheHashtag(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $jott = $this->jott($ada, 'Note #both');
        $id   = $this->collectionIdForTag('both');
        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['collection' => $id]);

        $this->actingAs($ada)->submit("jotts/{$jott}", ['text' => 'Note']);

        $this->assertReasons($jott, $id, automatic: false, manual: true);
    }

    public function testManualAndHashtagReasonsAreIndependentWhenRemovingManual(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $jott = $this->jott($ada, 'Note #both');
        $id   = $this->collectionIdForTag('both');
        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['collection' => $id]);

        $this->actingAs($ada)->submit("jotts/{$jott}/collections/{$id}/remove");

        $this->assertReasons($jott, $id, automatic: true, manual: false);
    }

    public function testHashtagMembershipCannotBeRemovedThroughTheManualRoute(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $jott = $this->jott($ada, 'Note #tagged');
        $id   = $this->collectionIdForTag('tagged');

        $this->actingAs($ada)->submit("jotts/{$jott}/collections/{$id}/remove");

        $this->assertReasons($jott, $id, automatic: true, manual: false);
        $this->dontSeeInDatabase('audit_log', ['action' => 'jott.collection_removed']);
    }

    public function testEditorDistinguishesHashtagFromManualMembership(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $jott = $this->jott($ada, 'Note #tagged');
        $id   = $this->manualCollection($ada, 'By hand');
        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['collection' => $id]);

        $body = $this->actingAs($ada)->get("jotts/{$jott}")->getBody();

        [$tags, $manual] = explode('Added manually', substr($body, (int) strpos($body, 'From hashtags')), 2);

        $this->assertStringContainsString('#tagged', $tags);
        $this->assertStringNotContainsString('By hand', $tags);
        $this->assertStringContainsString('By hand', $manual);
    }

    public function testMembershipChangesAreAudited(): void
    {
        $ada  = $this->createWorkspace('ada@example.com');
        $jott = $this->jott($ada, 'Note');
        $id   = $this->manualCollection($ada, 'Secret project name');
        $this->actingAs($ada)->submit("jotts/{$jott}/collections", ['collection' => $id]);
        $this->actingAs($ada)->submit("jotts/{$jott}/collections/{$id}/remove");

        $this->seeInDatabase('audit_log', ['action' => 'collection.created', 'entity_ref' => $id]);
        $this->seeInDatabase('audit_log', ['action' => 'jott.collection_added', 'entity_ref' => $jott]);
        $this->seeInDatabase('audit_log', ['action' => 'jott.collection_removed', 'entity_ref' => $jott]);

        $rows = json_encode($this->db->query('SELECT * FROM audit_log')->getResultArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Secret project name', $rows);
    }

    public function testAnotherTenantsCollectionsAreInvisibleAndUnreachable(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $this->jott($ada, 'Ada note #adatag');
        $id = $this->collectionIdForTag('adatag');

        $this->actingAs($grace)->get('collections')->assertDontSee('adatag');
        $this->actingAs($grace)->get('dashboard')->assertDontSee('adatag');

        $attempts = [
            fn () => $this->actingAs($grace)->get("collections/{$id}"),
            fn () => $this->actingAs($grace)->get("collections/{$id}/settings"),
            fn () => $this->actingAs($grace)->submit("collections/{$id}/settings", ['name' => 'hacked']),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Expected a 404 for another tenant\'s collection.');
            } catch (PageNotFoundException) {
                $this->seeInDatabase('collections', ['public_id' => $id, 'name' => 'adatag']);
            }
        }
    }

    public function testCannotFileAJottIntoAnotherTenantsCollection(): void
    {
        $ada     = $this->createWorkspace('ada@example.com');
        $grace   = $this->createWorkspace('grace@example.com');
        $foreign = $this->manualCollection($ada, 'Ada only');
        $mine    = $this->jott($grace, 'Grace note');

        try {
            $this->actingAs($grace)->submit("jotts/{$mine}/collections", ['collection' => $foreign]);
            $this->fail('Expected a 404 for another tenant\'s collection.');
        } catch (PageNotFoundException) {
            $this->assertNull($this->membership($mine, $foreign));
        }
    }

    public function testCannotTouchAnotherTenantsJottThroughCollections(): void
    {
        $ada     = $this->createWorkspace('ada@example.com');
        $grace   = $this->createWorkspace('grace@example.com');
        $foreign = $this->jott($ada, 'Ada note');
        $mine    = $this->manualCollection($grace, 'Grace only');

        try {
            $this->actingAs($grace)->submit("jotts/{$foreign}/collections", ['collection' => $mine]);
            $this->fail('Expected a 404 for another tenant\'s jott.');
        } catch (PageNotFoundException) {
            $this->assertNull($this->membership($foreign, $mine));
        }
    }
}
