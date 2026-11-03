<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * The worker's cross-tenant view of queued work, and the only such view: it returns
 * just which tenant and attempt to run next. All real work happens afterwards,
 * inside runAs() for that tenant, through scoped models.
 */
class DeliveryQueue extends Model
{
    protected $table         = 'delivery_attempts';
    protected $allowedFields = [];
    protected $returnType    = 'array';

    /**
     * Claims the oldest queued attempt. SKIP LOCKED lets several workers run without
     * ever claiming the same row, so one queued attempt means one request.
     *
     * @return array{id: int, tenant_id: int}|null
     */
    public function claimNext(): ?array
    {
        $this->db->transBegin();

        $row = $this->db->query(
            "SELECT id, tenant_id FROM delivery_attempts
             WHERE status = 'queued' ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED"
        )->getRowArray();

        if ($row === null) {
            $this->db->transCommit();

            return null;
        }

        $this->db->query(
            "UPDATE delivery_attempts SET status = 'sending', started_at = ? WHERE id = ?",
            [Time::now()->toDateTimeString(), $row['id']]
        );
        $this->db->transCommit();

        return ['id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id']];
    }

    /**
     * A worker that died mid-send can't say what happened. Report that honestly as
     * failed rather than retrying, which could deliver the same jott twice.
     *
     * @return int How many attempts were closed.
     */
    public function failAbandoned(int $olderThanSeconds): int
    {
        $cutoff = Time::now()->subSeconds($olderThanSeconds)->toDateTimeString();
        $now    = Time::now()->toDateTimeString();

        $this->db->query(
            "UPDATE delivery_states s
             JOIN delivery_attempts a ON a.tenant_id = s.tenant_id AND a.jott_id = s.jott_id
                AND a.collection_id = s.collection_id
             SET s.state = 'failed', s.updated_at = ?
             WHERE a.status = 'sending' AND a.started_at < ? AND s.state = 'sending'",
            [$now, $cutoff]
        );
        $this->db->query(
            "UPDATE delivery_attempts
             SET status = 'failed', finished_at = ?, error_summary = 'Interrupted before a result was recorded.'
             WHERE status = 'sending' AND started_at < ?",
            [$now, $cutoff]
        );

        return $this->db->affectedRows();
    }
}
