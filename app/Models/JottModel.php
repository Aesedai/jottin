<?php

namespace App\Models;

class JottModel extends TenantScopedModel
{
    private const FEED_LIMIT = 100;

    protected $table         = 'jotts';
    protected $allowedFields = ['public_id', 'text', 'source'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /** @return array<string, mixed>|null */
    public function findByPublicId(string $publicId): ?array
    {
        return $this->where('public_id', $publicId)->first();
    }

    /** @return list<array<string, mixed>> Newest first. */
    public function inCollection(int $collectionId): array
    {
        return $this->select('jotts.*')
            ->join(
                'jott_collections',
                'jott_collections.tenant_id = jotts.tenant_id AND jott_collections.jott_id = jotts.id'
            )
            ->where('jott_collections.collection_id', $collectionId)
            ->orderBy('jotts.created_at', 'DESC')
            ->orderBy('jotts.id', 'DESC')
            ->findAll(self::FEED_LIMIT);
    }

    /** @return list<array<string, mixed>> Newest first. */
    public function feed(): array
    {
        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll(self::FEED_LIMIT);
    }
}
