<?php

namespace Tests\Feature;

use App\Libraries\Tenancy\TenantContext;
use App\Libraries\Tenancy\TenantScopedBuilder;
use App\Libraries\Tenancy\TenantScopeException;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\RawSql;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Models\MisconfiguredNoteModel;
use Tests\Support\Models\ScopedNoteModel;

/**
 * Proves one tenant cannot read or change another's rows through the base model.
 * Tenant 1 is "ours" throughout; tenant 2 is the other customer.
 */
final class TenantIsolationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private const OURS   = 1;
    private const THEIRS = 2;

    protected $migrate   = false;
    protected $refresh   = false;
    protected $namespace = null;

    private TenantContext $context;
    private ScopedNoteModel $notes;
    private int $ourNote;
    private int $theirNote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropFixtures();
        $this->db->query('CREATE TABLE scoped_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            body VARCHAR(100) NOT NULL,
            UNIQUE KEY tenant_note (tenant_id, id)
        ) ENGINE=InnoDB');

        $this->db->query("INSERT INTO scoped_notes (tenant_id, body) VALUES (?, 'ours')", [self::OURS]);
        $this->ourNote = $this->db->insertID();
        $this->db->query("INSERT INTO scoped_notes (tenant_id, body) VALUES (?, 'theirs')", [self::THEIRS]);
        $this->theirNote = $this->db->insertID();

        $this->db->query('CREATE TABLE scoped_note_tags (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            note_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB');

        // The second row is a stray link: another tenant's tag that points at our note.
        $this->db->query(
            "INSERT INTO scoped_note_tags (tenant_id, note_id, label) VALUES (?, ?, 'our tag'), (?, ?, 'their tag')",
            [self::OURS, $this->ourNote, self::THEIRS, $this->ourNote]
        );

        $this->context = service('tenantContext');
        $this->context->set(self::OURS);
        $this->notes = new ScopedNoteModel();
    }

    protected function tearDown(): void
    {
        $this->dropFixtures();

        parent::tearDown();
    }

    private function dropFixtures(): void
    {
        $this->db->query('DROP TABLE IF EXISTS scoped_note_tags');
        $this->db->query('DROP TABLE IF EXISTS scoped_note_links');
        $this->db->query('DROP TABLE IF EXISTS scoped_notes');
    }

    /** @return array<string, mixed>|null */
    private function rawRow(int $id): ?array
    {
        return $this->db->query('SELECT * FROM scoped_notes WHERE id = ?', [$id])->getRowArray();
    }

    public function testFindAllReturnsOnlyOurRows(): void
    {
        $this->assertSame(['ours'], array_column($this->notes->findAll(), 'body'));
    }

    public function testFindByForeignIdReturnsNothing(): void
    {
        $this->assertNull($this->notes->find($this->theirNote));
        $this->assertSame([], $this->notes->find([$this->theirNote]));
    }

    public function testOrWhereCannotEscapeTheScope(): void
    {
        $rows = $this->notes->where('id', $this->ourNote)->orWhere('id', $this->theirNote)->findAll();

        $this->assertSame([$this->ourNote], array_map('intval', array_column($rows, 'id')));
    }

    public function testRawWhereCannotEscapeTheScope(): void
    {
        $rows = $this->notes->where('1 = 1 OR 1 = 1', null, false)->findAll();

        $this->assertSame(['ours'], array_column($rows, 'body'));
    }

    public function testResetQueryCannotRemoveTheScope(): void
    {
        $this->notes->builder()->resetQuery();

        $this->assertSame(['ours'], array_column($this->notes->findAll(), 'body'));
    }

    public function testDirectBuilderUseIsScoped(): void
    {
        $rows = $this->notes->builder()->get()->getResultArray();

        $this->assertSame(['ours'], array_column($rows, 'body'));
    }

    public function testCountsIncludeOnlyOurRows(): void
    {
        $this->assertSame(1, $this->notes->countAllResults());
    }

    public function testFirstAndColumnsIncludeOnlyOurRows(): void
    {
        $this->assertSame('ours', $this->notes->orderBy('id', 'DESC')->first()['body']);
        $this->assertSame(['ours'], $this->notes->findColumn('body'));
    }

    public function testScopeHoldsAcrossConsecutiveQueries(): void
    {
        $this->notes->findAll();

        $this->assertSame(['ours'], array_column($this->notes->findAll(), 'body'));
    }

    public function testUpdateByForeignIdChangesNothing(): void
    {
        $this->notes->update($this->theirNote, ['body' => 'hacked']);

        $this->assertSame('theirs', $this->rawRow($this->theirNote)['body']);
    }

    public function testUpdateWithOrWhereCannotReachForeignRows(): void
    {
        $this->notes->where('id', $this->ourNote)->orWhere('id', $this->theirNote)->set('body', 'changed')->update();

        $this->assertSame('changed', $this->rawRow($this->ourNote)['body']);
        $this->assertSame('theirs', $this->rawRow($this->theirNote)['body']);
    }

    public function testDeleteByForeignIdRemovesNothing(): void
    {
        $this->notes->delete($this->theirNote);

        $this->assertNotNull($this->rawRow($this->theirNote));
    }

    public function testDeleteWithOrWhereCannotReachForeignRows(): void
    {
        $this->notes->where('id', $this->ourNote)->orWhere('id', $this->theirNote)->delete();

        $this->assertNull($this->rawRow($this->ourNote));
        $this->assertNotNull($this->rawRow($this->theirNote));
    }

    public function testDeleteWithoutConditionsIsRefused(): void
    {
        $this->expectException(DatabaseException::class);

        $this->notes->builder()->delete();
    }

    public function testInsertIsStampedWithTheCurrentTenant(): void
    {
        $id = $this->notes->insert(['body' => 'new']);

        $this->assertSame(self::OURS, (int) $this->rawRow((int) $id)['tenant_id']);
    }

    public function testMassAssignedTenantIdIsIgnored(): void
    {
        $id = $this->notes->insert(['body' => 'new', 'tenant_id' => self::THEIRS]);

        $this->assertSame(self::OURS, (int) $this->rawRow((int) $id)['tenant_id']);
    }

    public function testBuilderRefusesInsertWithTenantId(): void
    {
        $this->expectException(TenantScopeException::class);

        $this->notes->builder()->insert(['body' => 'new', 'tenant_id' => self::THEIRS]);
    }

    public function testBuilderRefusesMovingARowToAnotherTenant(): void
    {
        try {
            $this->notes->builder()->where('id', $this->ourNote)->update(['tenant_id' => self::THEIRS]);
            $this->fail('Expected the update to be refused.');
        } catch (TenantScopeException) {
            $this->assertSame(self::OURS, (int) $this->rawRow($this->ourNote)['tenant_id']);
        }
    }

    public function testModelCannotAllowTenantIdMassAssignment(): void
    {
        $this->expectException(TenantScopeException::class);

        new MisconfiguredNoteModel();
    }

    public function testFromCannotPointAtAnotherTableWithoutOverwrite(): void
    {
        $this->expectException(TenantScopeException::class);

        $this->notes->builder()->from('tenants');
    }

    public function testModelCannotQueryAnotherTable(): void
    {
        $this->expectException(TenantScopeException::class);

        $this->notes->builder('tenants');
    }

    /** @return array<string, array{callable(BaseBuilder): mixed}> */
    public static function unscopableOperations(): array
    {
        return [
            'countAll'     => [static fn (BaseBuilder $b) => $b->countAll()],
            'from'         => [static fn (BaseBuilder $b) => $b->from('tenants', true)],
            'fromSubquery' => [static fn (BaseBuilder $b) => $b->fromSubquery($b, 'x')],
            'union'        => [static fn (BaseBuilder $b) => $b->union($b)],
            'unionAll'     => [static fn (BaseBuilder $b) => $b->unionAll($b)],
            'replace'      => [static fn (BaseBuilder $b) => $b->replace(['body' => 'x'])],
            'upsert'       => [static fn (BaseBuilder $b) => $b->upsert(['body' => 'x'])],
            'upsertBatch'  => [static fn (BaseBuilder $b) => $b->upsertBatch([['body' => 'x']])],
            'insertBatch'  => [static fn (BaseBuilder $b) => $b->insertBatch([['body' => 'x']])],
            'updateBatch'  => [static fn (BaseBuilder $b) => $b->updateBatch([['id' => 1, 'body' => 'x']], 'id')],
            'deleteBatch'  => [static fn (BaseBuilder $b) => $b->deleteBatch([['id' => 1]], ['id'])],
            'emptyTable'   => [static fn (BaseBuilder $b) => $b->emptyTable()],
            'truncate'     => [static fn (BaseBuilder $b) => $b->truncate()],
        ];
    }

    /** @param callable(BaseBuilder): mixed $operation */
    #[DataProvider('unscopableOperations')]
    public function testOperationsThatCannotBeScopedAreRefused(callable $operation): void
    {
        $this->expectException(TenantScopeException::class);

        $operation($this->notes->builder());
    }

    /** @return array<string, array{string, string}> */
    public static function safeJoins(): array
    {
        return [
            'joined table first' => [
                'scoped_note_tags',
                'scoped_note_tags.tenant_id = scoped_notes.tenant_id AND scoped_note_tags.note_id = scoped_notes.id',
            ],
            'own table first' => [
                'scoped_note_tags',
                'scoped_notes.tenant_id = scoped_note_tags.tenant_id AND scoped_notes.id = scoped_note_tags.note_id',
            ],
            'alias' => [
                'scoped_note_tags t',
                't.tenant_id = scoped_notes.tenant_id AND t.note_id = scoped_notes.id',
            ],
            'alias with AS' => [
                'scoped_note_tags AS t',
                't.tenant_id=scoped_notes.tenant_id AND t.note_id=scoped_notes.id',
            ],
        ];
    }

    #[DataProvider('safeJoins')]
    public function testJoinOnTheTenantReturnsOnlyOurRows(string $table, string $condition): void
    {
        $rows = $this->notes->builder()->select('label')->join($table, $condition)->get()->getResultArray();

        $this->assertSame(['our tag'], array_column($rows, 'label'));
    }

    /** @return array<string, array{string, mixed}> */
    public static function unsafeJoins(): array
    {
        return [
            'ID only' => ['scoped_note_tags', 'scoped_note_tags.note_id = scoped_notes.id'],
            'OR around the tenant' => [
                'scoped_note_tags',
                'scoped_note_tags.tenant_id = scoped_notes.tenant_id OR scoped_note_tags.note_id = scoped_notes.id',
            ],
            'lowercase or' => [
                'scoped_note_tags',
                'scoped_note_tags.tenant_id = scoped_notes.tenant_id or scoped_note_tags.note_id = scoped_notes.id',
            ],
            'pipes as OR' => [
                'scoped_note_tags',
                'scoped_note_tags.tenant_id = scoped_notes.tenant_id || scoped_note_tags.note_id = scoped_notes.id',
            ],
            'tenant compared to itself' => [
                'scoped_note_tags',
                'scoped_note_tags.tenant_id = scoped_note_tags.tenant_id',
            ],
            'fixed tenant value' => ['scoped_note_tags', 'scoped_note_tags.tenant_id = 2'],
            'a different table' => [
                'scoped_note_tags',
                'other.scoped_note_tags.tenant_id = scoped_notes.tenant_id',
            ],
            'similar column name' => [
                'scoped_note_tags',
                'scoped_note_tags.tenant_id = scoped_notes.tenant_id_old',
            ],
            'USING' => ['scoped_note_tags', 'tenant_id'],
            'raw SQL' => ['scoped_note_tags', new RawSql('scoped_note_tags.tenant_id = scoped_notes.tenant_id')],
            'subquery as table' => [
                '(SELECT * FROM scoped_note_tags) t',
                't.tenant_id = scoped_notes.tenant_id',
            ],
        ];
    }

    #[DataProvider('unsafeJoins')]
    public function testJoinThatCouldLeaveTheTenantIsRefused(string $table, mixed $condition): void
    {
        $this->expectException(TenantScopeException::class);

        $this->notes->builder()->join($table, $condition);
    }

    public function testJoinThroughAModelIsAlsoRefused(): void
    {
        $this->expectException(TenantScopeException::class);

        $this->notes->join('scoped_note_tags', 'scoped_note_tags.note_id = scoped_notes.id');
    }

    public function testQueriesFailClosedWithoutATenant(): void
    {
        $notes = new TenantScopedBuilder('scoped_notes', $this->db, new TenantContext());

        $this->expectException(TenantScopeException::class);

        $notes->get();
    }

    public function testInsertsFailClosedWithoutATenant(): void
    {
        $notes = new TenantScopedBuilder('scoped_notes', $this->db, new TenantContext());

        $this->expectException(TenantScopeException::class);

        $notes->insert(['body' => 'orphan']);
    }

    public function testRunAsSwitchesTenantForTheTaskOnly(): void
    {
        $seen = $this->context->runAs(self::THEIRS, fn () => array_column($this->notes->findAll(), 'body'));

        $this->assertSame(['theirs'], $seen);
        $this->assertSame(['ours'], array_column($this->notes->findAll(), 'body'));
    }

    public function testCompositeForeignKeyRejectsCrossTenantLinks(): void
    {
        $this->db->query('CREATE TABLE scoped_note_links (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            note_id BIGINT UNSIGNED NOT NULL,
            CONSTRAINT note_same_tenant FOREIGN KEY (tenant_id, note_id) REFERENCES scoped_notes (tenant_id, id)
        ) ENGINE=InnoDB');

        $link = 'INSERT INTO scoped_note_links (tenant_id, note_id) VALUES (?, ?)';
        $this->db->query($link, [self::OURS, $this->ourNote]);

        $this->expectException(DatabaseException::class);

        $this->db->query($link, [self::OURS, $this->theirNote]);
    }
}
