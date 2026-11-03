<?php

namespace App\Models;

class JottCollectionModel extends TenantScopedModel
{
    protected $table         = 'jott_collections';
    protected $allowedFields = ['jott_id', 'collection_id', 'is_automatic', 'is_manual'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function isMember(int $jottId, int $collectionId): bool
    {
        return $this->where('jott_id', $jottId)->where('collection_id', $collectionId)->countAllResults() > 0;
    }

    /**
     * @param list<int> $jottIds
     *
     * @return array<int, list<array<string, mixed>>> Memberships with collection names, by jott ID.
     */
    public function forJotts(array $jottIds): array
    {
        if ($jottIds === []) {
            return [];
        }

        $rows = $this->select('jott_collections.jott_id, jott_collections.is_automatic, jott_collections.is_manual')
            ->select('collections.public_id, collections.name, collections.tag_key')
            // Joined on the composite key, so both rows must belong to the same tenant.
            ->join(
                'collections',
                'collections.tenant_id = jott_collections.tenant_id'
                . ' AND collections.id = jott_collections.collection_id'
            )
            ->whereIn('jott_collections.jott_id', $jottIds)
            ->orderBy('collections.name', 'ASC')
            ->findAll();

        $byJott = [];

        foreach ($rows as $row) {
            $byJott[(int) $row['jott_id']][] = $row;
        }

        return $byJott;
    }
}
