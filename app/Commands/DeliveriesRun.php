<?php

namespace App\Commands;

use App\Libraries\Delivery\DeliveryRunner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sends queued automatic deliveries. Runs once by default, which suits a scheduled
 * job. --loop keeps polling, and exits after a while so the container restarts it
 * with a fresh process and database connection.
 */
class DeliveriesRun extends BaseCommand
{
    private const LOOP_ITERATIONS    = 1800;
    private const LOOP_SLEEP_SECONDS = 2;

    protected $group       = 'Jottin';
    protected $name        = 'deliveries:run';
    protected $description = 'Sends queued automatic webhook deliveries.';
    protected $usage       = 'deliveries:run [--loop]';

    /** @param array<int|string, string|null> $params */
    public function run(array $params): int
    {
        $iterations = CLI::getOption('loop') !== null || array_key_exists('loop', $params) ? self::LOOP_ITERATIONS : 1;

        for ($i = 0; $i < $iterations; $i++) {
            $processed = (new DeliveryRunner())->runOnce();

            if ($processed > 0) {
                CLI::write("Processed {$processed} deliveries.");
            }

            if ($iterations > 1) {
                sleep(self::LOOP_SLEEP_SECONDS);
            }
        }

        return EXIT_SUCCESS;
    }
}
