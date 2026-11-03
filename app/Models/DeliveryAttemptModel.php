<?php

namespace App\Models;

class DeliveryAttemptModel extends TenantScopedModel
{
    private const HISTORY_LIMIT = 50;

    protected $table         = 'delivery_attempts';
    protected $allowedFields = [
        'public_id', 'jott_id', 'jott_ref', 'collection_id', 'trigger_type', 'status',
        'endpoint_host', 'http_status', 'error_summary', 'started_at', 'finished_at',
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /** @return list<array<string, mixed>> Newest first. */
    public function historyFor(int $collectionId): array
    {
        return $this->where('collection_id', $collectionId)->orderBy('id', 'DESC')->findAll(self::HISTORY_LIMIT);
    }
}
