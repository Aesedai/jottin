<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Throwable;

/**
 * Liveness + database check for containers and uptime monitors.
 * Deliberately reports nothing beyond up/down: no versions, hosts or error text.
 */
class Health extends BaseController
{
    public function index(): ResponseInterface
    {
        try {
            Database::connect()->query('SELECT 1');
        } catch (Throwable $e) {
            log_message('error', 'Health check failed: {message}', ['message' => $e->getMessage()]);

            return $this->response->setStatusCode(503)->setJSON(['status' => 'unavailable']);
        }

        return $this->response->setJSON(['status' => 'ok']);
    }
}
