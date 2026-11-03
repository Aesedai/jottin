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
        $user = service('currentUser');

        $this->log->insert([
            'actor_type'  => $user->isSignedIn() ? 'user' : 'system',
            'actor_id'    => $user->isSignedIn() ? $user->id() : null,
            'actor_label' => $user->isSignedIn() ? $user->email() : 'system',
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_ref'  => $entityRef,
            'detail'      => $detail === [] ? null : json_encode($detail, JSON_THROW_ON_ERROR),
        ]);
    }
}
