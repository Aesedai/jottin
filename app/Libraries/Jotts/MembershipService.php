<?php

namespace App\Libraries\Jotts;

use App\Models\JottCollectionModel;

/**
 * Applies the rule that hashtags and manual choices are independent reasons for
 * membership. Removing one reason never removes the other.
 */
final class MembershipService
{
    public function __construct(private readonly JottCollectionModel $memberships = new JottCollectionModel())
    {
    }

    /**
     * @return bool Whether the jott newly entered the collection.
     */
    public function addManual(int $jottId, int $collectionId): bool
    {
        $row = $this->find($jottId, $collectionId);

        if ($row === null) {
            $this->memberships->insert(['jott_id' => $jottId, 'collection_id' => $collectionId, 'is_manual' => 1]);

            return true;
        }

        if ((int) $row['is_manual'] === 0) {
            $this->memberships->update($row['id'], ['is_manual' => 1]);
        }

        return false;
    }

    /**
     * Removes only the manual reason. If a hashtag also applies, the jott stays.
     *
     * @return bool Whether there was a manual reason to remove.
     */
    public function removeManual(int $jottId, int $collectionId): bool
    {
        $row = $this->find($jottId, $collectionId);

        if ($row === null || (int) $row['is_manual'] === 0) {
            return false;
        }

        if ((int) $row['is_automatic'] === 1) {
            $this->memberships->update($row['id'], ['is_manual' => 0]);
        } else {
            $this->memberships->delete($row['id']);
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function find(int $jottId, int $collectionId): ?array
    {
        return $this->memberships->where('jott_id', $jottId)->where('collection_id', $collectionId)->first();
    }

    /**
     * Makes automatic membership match exactly the given collections.
     *
     * @param list<int> $collectionIds
     *
     * @return array{entered: list<int>, added: list<int>, removed: list<int>}
     *   entered: newly members at all. added/removed: automatic reason changed.
     */
    public function syncAutomatic(int $jottId, array $collectionIds): array
    {
        $existing = [];

        foreach ($this->memberships->where('jott_id', $jottId)->findAll() as $row) {
            $existing[(int) $row['collection_id']] = $row;
        }

        $result = ['entered' => [], 'added' => [], 'removed' => []];

        foreach ($collectionIds as $collectionId) {
            $row = $existing[$collectionId] ?? null;

            if ($row === null) {
                $this->memberships->insert([
                    'jott_id'       => $jottId,
                    'collection_id' => $collectionId,
                    'is_automatic'  => 1,
                ]);
                $result['entered'][] = $collectionId;
                $result['added'][]   = $collectionId;
            } elseif ((int) $row['is_automatic'] === 0) {
                $this->memberships->update($row['id'], ['is_automatic' => 1]);
                $result['added'][] = $collectionId;
            }
        }

        foreach ($existing as $collectionId => $row) {
            if ((int) $row['is_automatic'] === 1 && ! in_array($collectionId, $collectionIds, true)) {
                if ((int) $row['is_manual'] === 1) {
                    $this->memberships->update($row['id'], ['is_automatic' => 0]);
                } else {
                    $this->memberships->delete($row['id']);
                }

                $result['removed'][] = $collectionId;
            }
        }

        return $result;
    }
}
