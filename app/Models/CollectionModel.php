<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;

class CollectionModel extends TenantScopedModel
{
    private const MYSQL_DUPLICATE_KEY = 1062;

    protected $table         = 'collections';
    protected $allowedFields = ['public_id', 'name', 'tag_key'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /**
     * Finds this tenant's collection for a tag, creating it the first time.
     *
     * @return array{collection: array<string, mixed>, created: bool}
     */
    public function findOrCreateForTag(string $tagKey, string $displayName): array
    {
        $existing = $this->where('tag_key', $tagKey)->first();

        if ($existing !== null) {
            return ['collection' => $existing, 'created' => false];
        }

        try {
            $id = $this->insert([
                'public_id' => bin2hex(random_bytes(12)),
                'name'      => $displayName,
                'tag_key'   => $tagKey,
            ]);
        } catch (DatabaseException $e) {
            if ($e->getCode() !== self::MYSQL_DUPLICATE_KEY) {
                throw $e;
            }

            // Another request created it first; the unique key means there is only one.
            return ['collection' => $this->where('tag_key', $tagKey)->first(), 'created' => false];
        }

        return ['collection' => $this->find($id), 'created' => true];
    }
}
