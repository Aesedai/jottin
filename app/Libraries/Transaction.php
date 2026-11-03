<?php

namespace App\Libraries;

use Throwable;

/**
 * Runs a unit of work atomically: it commits on success and rolls back on any
 * exception, whichever code threw it.
 */
final class Transaction
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public static function run(callable $work): mixed
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $result = $work();
            $db->transCommit();

            return $result;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }
}
