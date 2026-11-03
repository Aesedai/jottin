<?php

namespace App\Controllers;

use App\Libraries\Delivery\BlockedTarget;
use App\Libraries\Delivery\DeliveryService;
use App\Libraries\Delivery\WebhookConfigService;
use App\Models\CollectionModel;
use App\Models\CollectionWebhookModel;
use App\Models\DeliveryAttemptModel;
use App\Models\JottCollectionModel;
use App\Models\JottModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Deliveries extends BaseController
{
    /** Sends run one after another inside the request, so this bounds how long it can take. */
    private const MAX_SELECTED = 10;

    /**
     * Owner-only, enforced by the role:owner filter on this route.
     */
    public function configure(string $publicId): RedirectResponse|ResponseInterface
    {
        $collection = $this->collectionOr404($publicId);
        $mode       = (string) $this->request->getPost('mode');

        if (! in_array($mode, ['disabled', 'manual', 'automatic'], true)) {
            return $this->settingsPage($collection, ['webhookError' => 'Choose a delivery mode.'], 422);
        }

        try {
            $secret = (new WebhookConfigService())->save($collection, (string) $this->request->getPost('url'), $mode);
        } catch (BlockedTarget $e) {
            return $this->settingsPage($collection, ['webhookError' => $e->getMessage()], 422);
        }

        if ($secret !== null) {
            // Rendered directly, not flashed, so the secret is never stored in the session.
            return $this->settingsPage($collection, ['newSecret' => $secret], 201);
        }

        return redirect()->to("/collections/{$publicId}/settings")->with('notice', 'Webhook saved.');
    }

    /**
     * A first POST shows the confirmation. Only one with confirm=1 sends.
     */
    public function send(string $publicId): RedirectResponse|string
    {
        $collection = $this->collectionOr404($publicId);
        $webhook    = (new CollectionWebhookModel())->forCollection((int) $collection['id']);
        $back       = "/collections/{$publicId}";

        if ($webhook === null || $webhook['mode'] !== CollectionWebhookModel::MODE_MANUAL) {
            return redirect()->to($back)->with('error', 'This collection is not set to Manual delivery.');
        }

        $selected = array_values(array_unique(array_filter((array) $this->request->getPost('jotts'), 'is_string')));

        if ($selected === []) {
            return redirect()->to($back)->with('error', 'Select at least one jott to send.');
        }

        if (count($selected) > self::MAX_SELECTED) {
            return redirect()->to($back)->with('error', 'Select at most ' . self::MAX_SELECTED . ' jotts at a time.');
        }

        $jotts = $this->selectedMembers($selected, (int) $collection['id']);

        if ($this->request->getPost('confirm') !== '1') {
            return view('deliveries/confirm', [
                'collection' => $collection,
                'webhook'    => $webhook,
                'jotts'      => $jotts,
            ]);
        }

        $result = (new DeliveryService())->sendManually($collection, $jotts);

        return redirect()->to($back)->with(
            $result['failed'] > 0 ? 'error' : 'notice',
            "Delivered: {$result['succeeded']}. Failed: {$result['failed']}. Not sent: {$result['skipped']}."
        );
    }

    public function rearm(string $publicId, string $jottId): RedirectResponse
    {
        $collection = $this->collectionOr404($publicId);
        $jott       = (new JottModel())->findByPublicId($jottId) ?? throw PageNotFoundException::forPageNotFound();

        $rearmed = (new DeliveryService())->rearm($jott, (int) $collection['id']);

        return redirect()->to("/collections/{$publicId}")->with(
            $rearmed ? 'notice' : 'error',
            $rearmed ? 'Re-armed. This jott can be sent again.' : 'That jott doesn\'t need re-arming.'
        );
    }

    /**
     * Only jotts that are actually in this collection, loaded through the tenant scope.
     *
     * @param list<string> $publicIds
     *
     * @return list<array<string, mixed>>
     */
    private function selectedMembers(array $publicIds, int $collectionId): array
    {
        $jottModel   = new JottModel();
        $memberships = new JottCollectionModel();
        $jotts       = [];

        foreach ($publicIds as $publicId) {
            $jott = $jottModel->findByPublicId($publicId);

            if ($jott !== null && $memberships->isMember((int) $jott['id'], $collectionId)) {
                $jotts[] = $jott;
            }
        }

        return $jotts;
    }

    /**
     * @param array<string, mixed> $collection
     * @param array<string, mixed> $extra
     */
    private function settingsPage(array $collection, array $extra, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setBody(view('collections/settings', [
            'collection' => $collection,
            'webhook'    => (new CollectionWebhookModel())->forCollection((int) $collection['id']),
            'history'    => (new DeliveryAttemptModel())->historyFor((int) $collection['id']),
        ] + $extra));
    }

    /** @return array<string, mixed> */
    private function collectionOr404(string $publicId): array
    {
        return (new CollectionModel())->findByPublicId($publicId) ?? throw PageNotFoundException::forPageNotFound();
    }
}
