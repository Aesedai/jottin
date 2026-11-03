<?php

namespace App\Controllers;

use App\Libraries\Jotts\InvalidJottText;
use App\Libraries\Jotts\JottService;
use App\Models\JottCollectionModel;
use App\Models\JottModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class Jotts extends BaseController
{
    public function create(): RedirectResponse|ResponseInterface
    {
        $text = (string) $this->request->getPost('text');

        try {
            $result = (new JottService())->create($text);
        } catch (InvalidJottText $e) {
            $jotts = (new JottModel())->feed();

            return $this->response->setStatusCode(422)->setBody(view('dashboard/index', [
                'jotts'       => $jotts,
                'memberships' => (new JottCollectionModel())->forJotts(array_map('intval', array_column($jotts, 'id'))),
                'textError'   => $e->getMessage(),
                'draft'       => $text,
            ]));
        }

        return redirect()->to('/dashboard')->with('notice', $this->savedMessage($result['tags']));
    }

    public function show(string $publicId): string
    {
        $jott = $this->jottOr404($publicId);

        return view('jotts/show', $this->detailData($jott));
    }

    public function update(string $publicId): RedirectResponse|ResponseInterface
    {
        $jott = $this->jottOr404($publicId);
        $text = (string) $this->request->getPost('text');

        try {
            $tags = (new JottService())->update($jott, $text);
        } catch (InvalidJottText $e) {
            return $this->response->setStatusCode(422)->setBody(view(
                'jotts/show',
                $this->detailData($jott) + ['textError' => $e->getMessage(), 'draft' => $text]
            ));
        }

        return redirect()->to("/jotts/{$publicId}")->with('notice', $this->savedMessage($tags));
    }

    public function confirmDelete(string $publicId): string
    {
        return view('jotts/delete', ['jott' => $this->jottOr404($publicId)]);
    }

    public function delete(string $publicId): RedirectResponse
    {
        (new JottService())->delete($this->jottOr404($publicId));

        return redirect()->to('/dashboard')->with('notice', 'Jott deleted.');
    }

    /**
     * Goes through the tenant scope, so another tenant's jott looks exactly like one
     * that doesn't exist.
     *
     * @return array<string, mixed>
     */
    private function jottOr404(string $publicId): array
    {
        return (new JottModel())->findByPublicId($publicId) ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * @param array<string, mixed> $jott
     *
     * @return array<string, mixed>
     */
    private function detailData(array $jott): array
    {
        $id = (int) $jott['id'];

        return ['jott' => $jott, 'memberships' => (new JottCollectionModel())->forJotts([$id])[$id] ?? []];
    }

    /** @param list<array{name: string, created: bool}> $tags */
    private function savedMessage(array $tags): string
    {
        if ($tags === []) {
            return 'Saved.';
        }

        $names = array_map(
            static fn (array $tag): string => '#' . $tag['name'] . ($tag['created'] ? ' (new)' : ''),
            $tags
        );

        return 'Saved in ' . implode(', ', $names) . '.';
    }
}
