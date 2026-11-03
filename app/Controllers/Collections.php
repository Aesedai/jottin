<?php

namespace App\Controllers;

use App\Libraries\Jotts\CollectionService;
use App\Libraries\Jotts\FeedPage;
use App\Libraries\Jotts\InvalidCollectionName;
use App\Models\CollectionModel;
use App\Models\CollectionWebhookModel;
use App\Models\DeliveryAttemptModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Collections extends BaseController
{
    public function index(): string
    {
        return view('collections/index', ['collections' => (new CollectionModel())->withCounts()]);
    }

    public function create(): RedirectResponse|ResponseInterface
    {
        try {
            $collection = (new CollectionService())->createManual((string) $this->request->getPost('name'));
        } catch (InvalidCollectionName $e) {
            return $this->response->setStatusCode(422)->setBody(view('collections/index', [
                'collections' => (new CollectionModel())->withCounts(),
                'nameError'   => $e->getMessage(),
            ]));
        }

        return redirect()->to('/collections/' . $collection['public_id'])->with('notice', 'Collection created.');
    }

    public function show(string $publicId): string
    {
        return view('dashboard/index', FeedPage::data($this->collectionOr404($publicId)));
    }

    public function settings(string $publicId): string
    {
        return view('collections/settings', $this->settingsData($this->collectionOr404($publicId)));
    }

    public function rename(string $publicId): RedirectResponse|ResponseInterface
    {
        $collection = $this->collectionOr404($publicId);

        try {
            (new CollectionService())->rename($collection, (string) $this->request->getPost('name'));
        } catch (InvalidCollectionName $e) {
            return $this->response->setStatusCode(422)->setBody(view(
                'collections/settings',
                $this->settingsData($collection) + ['nameError' => $e->getMessage()]
            ));
        }

        return redirect()->to("/collections/{$publicId}/settings")->with('notice', 'Collection renamed.');
    }

    /**
     * @param array<string, mixed> $collection
     *
     * @return array<string, mixed>
     */
    private function settingsData(array $collection): array
    {
        return [
            'collection' => $collection,
            'webhook'    => (new CollectionWebhookModel())->forCollection((int) $collection['id']),
            'history'    => (new DeliveryAttemptModel())->historyFor((int) $collection['id']),
        ];
    }

    /**
     * Goes through the tenant scope, so another tenant's collection looks exactly
     * like one that doesn't exist.
     *
     * @return array<string, mixed>
     */
    private function collectionOr404(string $publicId): array
    {
        return (new CollectionModel())->findByPublicId($publicId) ?? throw PageNotFoundException::forPageNotFound();
    }
}
