<?php

namespace App\Models;

class DeliveryStateModel extends TenantScopedModel
{
    public const SELECTABLE = ['ready', 'rearmed'];
    public const SENT       = ['succeeded', 'failed'];

    protected $table         = 'delivery_states';
    protected $allowedFields = ['jott_id', 'collection_id', 'state'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    /**
     * @param list<int> $jottIds
     *
     * @return array<int, string> State by jott ID. A missing entry means never sent.
     */
    public function forCollection(int $collectionId, array $jottIds): array
    {
        if ($jottIds === []) {
            return [];
        }

        $rows = $this->where('collection_id', $collectionId)->whereIn('jott_id', $jottIds)->findAll();

        return array_column($rows, 'state', 'jott_id');
    }

    public function stateOf(int $jottId, int $collectionId): ?string
    {
        $row = $this->where('jott_id', $jottId)->where('collection_id', $collectionId)->first();

        return $row['state'] ?? null;
    }

    public function setState(int $jottId, int $collectionId, string $state): void
    {
        $row = $this->where('jott_id', $jottId)->where('collection_id', $collectionId)->first();

        if ($row === null) {
            $this->insert(['jott_id' => $jottId, 'collection_id' => $collectionId, 'state' => $state]);

            return;
        }

        $this->update($row['id'], ['state' => $state]);
    }

    /**
     * Atomically moves a jott from one of the given states to a new one. If two
     * requests race, only one sees true, which is what prevents a double send.
     *
     * @param list<string> $from
     */
    public function transition(int $jottId, int $collectionId, array $from, string $to): bool
    {
        $this->builder()
            ->where('jott_id', $jottId)
            ->where('collection_id', $collectionId)
            ->whereIn('state', $from)
            ->update(['state' => $to]);

        return $this->db->affectedRows() === 1;
    }
}
