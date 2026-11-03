<?php

namespace App\Libraries\Jotts;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Transaction;
use App\Models\CollectionModel;
use App\Models\JottModel;

/**
 * The single path for changing a jott, shared by the web UI and the API so their
 * rules can't drift. Each change and its audit record commit together.
 */
final class JottService
{
    public const MAX_LENGTH = 10000;

    public function __construct(
        private readonly JottModel $jotts = new JottModel(),
        private readonly CollectionModel $collections = new CollectionModel(),
        private readonly MembershipService $memberships = new MembershipService(),
        private readonly HashtagParser $parser = new HashtagParser(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {
    }

    /**
     * @return array{jott: array<string, mixed>, tags: list<array{name: string, created: bool}>}
     */
    public function create(string $text, string $source = 'web'): array
    {
        $text = $this->validated($text);

        return Transaction::run(function () use ($text, $source): array {
            $publicId = bin2hex(random_bytes(12));
            $id       = (int) $this->jotts->insert(['public_id' => $publicId, 'text' => $text, 'source' => $source]);

            $tags   = $this->resolveTags($text);
            $change = $this->memberships->syncAutomatic($id, array_column($tags, 'id'));

            $this->audit->record('jott.created', 'jott', $publicId, [
                'source'      => $source,
                'collections' => $change['added'],
            ]);

            return ['jott' => $this->jotts->find($id), 'tags' => $tags];
        });
    }

    /**
     * @param array<string, mixed> $jott A row already loaded through the tenant scope.
     *
     * @return list<array{name: string, created: bool}>
     */
    public function update(array $jott, string $text): array
    {
        $text = $this->validated($text);

        return Transaction::run(function () use ($jott, $text): array {
            $this->jotts->update($jott['id'], ['text' => $text]);

            $tags   = $this->resolveTags($text);
            $change = $this->memberships->syncAutomatic((int) $jott['id'], array_column($tags, 'id'));

            $this->audit->record('jott.updated', 'jott', $jott['public_id'], [
                'collections_added'   => $change['added'],
                'collections_removed' => $change['removed'],
            ]);

            return $tags;
        });
    }

    /** @param array<string, mixed> $jott A row already loaded through the tenant scope. */
    public function delete(array $jott): void
    {
        Transaction::run(function () use ($jott): void {
            $this->jotts->delete($jott['id']);
            $this->audit->record('jott.deleted', 'jott', $jott['public_id']);
        });
    }

    /**
     * @param array<string, mixed> $jott       A row already loaded through the tenant scope.
     * @param array<string, mixed> $collection A row already loaded through the tenant scope.
     */
    public function addToCollection(array $jott, array $collection): void
    {
        Transaction::run(function () use ($jott, $collection): void {
            $this->memberships->addManual((int) $jott['id'], (int) $collection['id']);
            $this->audit->record('jott.collection_added', 'jott', $jott['public_id'], [
                'collection' => $collection['public_id'],
            ]);
        });
    }

    /**
     * @param array<string, mixed> $jott       A row already loaded through the tenant scope.
     * @param array<string, mixed> $collection A row already loaded through the tenant scope.
     */
    public function removeFromCollection(array $jott, array $collection): void
    {
        Transaction::run(function () use ($jott, $collection): void {
            if ($this->memberships->removeManual((int) $jott['id'], (int) $collection['id'])) {
                $this->audit->record('jott.collection_removed', 'jott', $jott['public_id'], [
                    'collection' => $collection['public_id'],
                ]);
            }
        });
    }

    private function validated(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidJottText('Write something first.');
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            throw new InvalidJottText('A jott can be at most ' . self::MAX_LENGTH . ' characters.');
        }

        return $text;
    }

    /** @return list<array{id: int, name: string, created: bool}> */
    private function resolveTags(string $text): array
    {
        $tags = [];

        foreach ($this->parser->parse($text) as $key => $display) {
            $found  = $this->collections->findOrCreateForTag((string) $key, $display);
            $tags[] = [
                'id'      => (int) $found['collection']['id'],
                'name'    => (string) $found['collection']['name'],
                'created' => $found['created'],
            ];
        }

        return $tags;
    }
}
