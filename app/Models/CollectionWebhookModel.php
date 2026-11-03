<?php

namespace App\Models;

class CollectionWebhookModel extends TenantScopedModel
{
    public const MODE_DISABLED  = 'disabled';
    public const MODE_MANUAL    = 'manual';
    public const MODE_AUTOMATIC = 'automatic';

    protected $table         = 'collection_webhooks';
    protected $allowedFields = ['collection_id', 'mode', 'endpoint_encrypted', 'endpoint_host', 'secret_encrypted'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /** @return array<string, mixed>|null */
    public function forCollection(int $collectionId): ?array
    {
        return $this->where('collection_id', $collectionId)->first();
    }
}
