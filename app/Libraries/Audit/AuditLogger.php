<?php

namespace App\Libraries\Audit;

use App\Models\AuditLogModel;

/**
 * Writes inside the caller's transaction, so a change and its audit record commit
 * or fail together. Pass identifiers in $detail, never user content.
 */
class AuditLogger
{
    public function __construct(private readonly AuditLogModel $log = new AuditLogModel())
    {
    }

    /** @param array<string, mixed> $detail */
    public function record(string $action, string $entityType, string $entityRef, array $detail = []): void
    {
        $this->log->insert($this->actor() + [
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_ref'  => $entityRef,
            'detail'      => $detail === [] ? null : json_encode($detail, JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return array{actor_type: string, actor_id: int|null, actor_label: string} */
    private function actor(): array
    {
        $user   = service('currentUser');
        $client = service('apiClient');

        if ($user->isSignedIn()) {
            return ['actor_type' => 'user', 'actor_id' => $user->id(), 'actor_label' => $user->email()];
        }

        if ($client->isAuthenticated()) {
            return ['actor_type' => 'api', 'actor_id' => $client->id(), 'actor_label' => $client->label()];
        }

        return ['actor_type' => 'system', 'actor_id' => null, 'actor_label' => 'system'];
    }
}
