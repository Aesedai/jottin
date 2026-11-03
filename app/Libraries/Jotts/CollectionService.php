<?php

namespace App\Libraries\Jotts;

use App\Libraries\Audit\AuditLogger;
use App\Libraries\Transaction;
use App\Models\CollectionModel;

final class CollectionService
{
    private const MAX_NAME_LENGTH = 100;

    public function __construct(
        private readonly CollectionModel $collections = new CollectionModel(),
        private readonly AuditLogger $audit = new AuditLogger(),
    ) {
    }

    /**
     * A manual collection has no tag key, so no hashtag ever files into it.
     *
     * @return array<string, mixed>
     */
    public function createManual(string $name): array
    {
        $name = $this->validated($name);

        return Transaction::run(function () use ($name): array {
            $publicId = bin2hex(random_bytes(12));
            $id       = $this->collections->insert(['public_id' => $publicId, 'name' => $name]);
            $this->audit->record('collection.created', 'collection', $publicId);

            return $this->collections->find($id);
        });
    }

    /**
     * Changes the display name only. The tag key stays, so #oldname keeps filing
     * into this same collection.
     *
     * @param array<string, mixed> $collection A row already loaded through the tenant scope.
     */
    public function rename(array $collection, string $name): void
    {
        $name = $this->validated($name);

        Transaction::run(function () use ($collection, $name): void {
            $this->collections->update($collection['id'], ['name' => $name]);
            $this->audit->record('collection.renamed', 'collection', $collection['public_id']);
        });
    }

    private function validated(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidCollectionName('Enter a name.');
        }

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new InvalidCollectionName('A name can be at most ' . self::MAX_NAME_LENGTH . ' characters.');
        }

        return $name;
    }
}
