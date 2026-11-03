<?php

namespace Tests\Feature;

use App\Libraries\Tenancy\TenantScopeException;
use App\Models\AuditLogModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthTestHelpers;

final class AuditTest extends CIUnitTestCase
{
    use AuthTestHelpers;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';

    /** @param array{tenant_id: int, user_id: int} $account */
    private function createJott(array $account, string $text): string
    {
        $this->actingAs($account)->submit('jotts', ['text' => $text]);

        return (string) $this->db->query(
            'SELECT public_id FROM jotts WHERE tenant_id = ? ORDER BY id DESC LIMIT 1',
            [$account['tenant_id']]
        )->getRow()->public_id;
    }

    public function testCreateEditAndDeleteRecordWhoDidWhat(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');
        $id     = $this->createJott($member, 'Original');

        $this->actingAs($ada)->submit("jotts/{$id}", ['text' => 'Edited']);
        $this->actingAs($member)->submit("jotts/{$id}/delete");

        foreach (
            [
                ['jott.created', 'mem@example.com', $member],
                ['jott.updated', 'ada@example.com', $ada],
                ['jott.deleted', 'mem@example.com', $member],
            ] as [$action, $label, $actor]
        ) {
            $this->seeInDatabase('audit_log', [
                'tenant_id'   => $ada['tenant_id'],
                'action'      => $action,
                'actor_type'  => 'user',
                'actor_id'    => $actor['user_id'],
                'actor_label' => $label,
                'entity_ref'  => $id,
            ]);
        }
    }

    public function testAuditRecordsNeverContainNoteText(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $id  = $this->createJott($ada, 'Confidential diagnosis #privatetag');
        $this->actingAs($ada)->submit("jotts/{$id}", ['text' => 'Different confidential words']);

        $rows = json_encode($this->db->query('SELECT * FROM audit_log')->getResultArray(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('diagnosis', $rows);
        $this->assertStringNotContainsString('privatetag', $rows);
        $this->assertStringNotContainsString('confidential', strtolower($rows));
    }

    public function testMemberChangesAreAudited(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->actingAs($ada)->submit('members', ['name' => 'Mem', 'email' => 'mem@example.com']);
        $this->seeInDatabase('audit_log', ['action' => 'member.added', 'actor_id' => $ada['user_id']]);

        $id = (int) $this->db->query("SELECT id FROM users WHERE email = 'mem@example.com'")->getRow()->id;
        $this->actingAs($ada)->submit("members/{$id}/remove");

        $this->seeInDatabase('audit_log', ['action' => 'member.removed', 'entity_ref' => (string) $id]);
    }

    public function testDatabaseRefusesEditingAuditRecords(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->createJott($ada, 'Note');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('append-only');

        $this->db->query("UPDATE audit_log SET actor_label = 'someone-else'");
    }

    public function testDatabaseRefusesDeletingAuditRecords(): void
    {
        $ada = $this->createWorkspace('ada@example.com');
        $this->createJott($ada, 'Note');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('append-only');

        $this->db->query('DELETE FROM audit_log');
    }

    public function testModelRefusesEditingOrDeleting(): void
    {
        $log = new AuditLogModel();

        foreach ([static fn () => $log->update(1, ['action' => 'x']), static fn () => $log->delete(1)] as $change) {
            try {
                $change();
                $this->fail('Expected the audit model to refuse.');
            } catch (TenantScopeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testOwnerSeesOnlyTheirTenantsLog(): void
    {
        $ada   = $this->createWorkspace('ada@example.com');
        $grace = $this->createWorkspace('grace@example.com');
        $this->createJott($ada, 'Ada note');
        $this->createJott($grace, 'Grace note');

        $result = $this->actingAs($grace)->get('audit');

        $result->assertSee('grace@example.com');
        $result->assertDontSee('ada@example.com');
    }

    public function testMemberCannotViewTheLog(): void
    {
        $ada    = $this->createWorkspace('ada@example.com');
        $member = $this->createMember($ada['tenant_id'], 'mem@example.com');

        $this->actingAs($member)->get('audit')->assertStatus(403);
    }
}
