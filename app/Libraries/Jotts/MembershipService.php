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
