<?php

namespace App\Libraries\Jotts;

use App\Models\CollectionModel;
use App\Models\JottCollectionModel;
use App\Models\JottModel;

/**
 * Everything the feed view needs, shared by the dashboard, a selected collection
 * and a composer that re-renders after a validation error.
 */
final class FeedPage
{
    /**
     * @param array<string, mixed>|null $collection Limit the feed to this collection.
     *
     * @return array<string, mixed>
     */
    public static function data(?array $collection = null): array
    {
        $jottModel = new JottModel();
        $jotts     = $collection === null ? $jottModel->feed() : $jottModel->inCollection((int) $collection['id']);

        return [
            'jotts'       => $jotts,
            'collection'  => $collection,
            'collections' => (new CollectionModel())->withCounts(),
            'memberships' => (new JottCollectionModel())->forJotts(array_map('intval', array_column($jotts, 'id'))),
        ];
    }
}
