<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Jotts\InvalidJottText;
use App\Libraries\Jotts\JottService;
use App\Models\CollectionModel;
use App\Models\JottCollectionModel;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * POST /v1/jotts. The tenant comes only from the API key, set by ApiAuthFilter, so
 * any tenant or account field in the body is ignored.
 */
class Jotts extends BaseController
{
    private const MAX_BODY_BYTES = 65536;

    public function create(): ResponseInterface
    {
        if (strlen((string) $this->request->getBody()) > self::MAX_BODY_BYTES) {
            return $this->error(413, 'payload_too_large', 'The request body is too large.');
        }

        try {
            $body = $this->request->getJSON(true);
        } catch (HTTPException) {
            return $this->error(400, 'invalid_json', 'The request body must be valid JSON.');
        }

        if (! is_array($body)) {
            return $this->error(400, 'invalid_json', 'The request body must be a JSON object.');
        }

        if (! is_string($body['text'] ?? null)) {
            return $this->error(422, 'invalid_text', 'text is required and must be a string.');
        }

        $collectionIds = $body['collections'] ?? [];

        if (! is_array($collectionIds) || ! array_is_list($collectionIds)) {
            return $this->error(422, 'invalid_collections', 'collections must be an array of collection IDs.');
        }

        $collections = $this->findCollections($collectionIds);

        if ($collections === null) {
            return $this->error(422, 'unknown_collection', 'One or more collection IDs were not found.');
        }

        try {
            $result = (new JottService())->create($body['text'], 'api', $collections);
        } catch (InvalidJottText $e) {
            return $this->error(422, 'invalid_text', $e->getMessage());
        }

        return $this->response->setStatusCode(201)->setJSON($this->present($result['jott']));
    }

    /**
     * Each ID loads through the tenant scope, so another tenant's collection is
     * refused exactly like one that doesn't exist.
     *
     * @param list<mixed> $ids
     *
     * @return list<array<string, mixed>>|null Null if any ID isn't found.
     */
    private function findCollections(array $ids): ?array
    {
        $model       = new CollectionModel();
        $collections = [];

        foreach (array_unique($ids, SORT_REGULAR) as $id) {
            $collection = is_string($id) ? $model->findByPublicId($id) : null;

            if ($collection === null) {
                return null;
            }

            $collections[] = $collection;
        }

        return $collections;
    }

    /**
     * @param array<string, mixed> $jott
     *
     * @return array<string, mixed>
     */
    private function present(array $jott): array
    {
        $id          = (int) $jott['id'];
        $memberships = (new JottCollectionModel())->forJotts([$id])[$id] ?? [];
        $names       = array_column($memberships, 'name');

        return [
            'id'          => $jott['public_id'],
            'createdAt'   => str_replace(' ', 'T', (string) $jott['created_at']) . 'Z',
            'source'      => $jott['source'],
            // Short and human-readable, for tools that show the reply, such as Webhooker.
            'message'     => $names === [] ? 'Saved.' : 'Saved in ' . implode(', ', $names) . '.',
            'collections' => array_map(
                static fn (array $membership): array => [
                    'id'         => $membership['public_id'],
                    'name'       => $membership['name'],
                    'membership' => match (true) {
                        (int) $membership['is_automatic'] === 1 && (int) $membership['is_manual'] === 1 => 'both',
                        (int) $membership['is_automatic'] === 1 => 'automatic',
                        default => 'manual',
                    },
                ],
                $memberships
            ),
        ];
    }

    private function error(int $status, string $code, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => ['code' => $code, 'message' => $message]]);
    }
}
