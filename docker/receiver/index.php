<?php

/**
 * A controllable webhook receiver for demos and tests. Never used in production.
 * /ok  200   /fail  500   /slow  waits 10 seconds   /redirect  302 to /ok
 * GET /last returns the most recent delivery that was received.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$log  = sys_get_temp_dir() . '/jottin-receiver-' . ($_SERVER['SERVER_PORT'] ?? '80') . '.json';

if ($path === '/last') {
    header('Content-Type: application/json');
    echo is_file($log) ? file_get_contents($log) : '{}';

    return;
}

$record = [
    'path'      => $path,
    'body'      => file_get_contents('php://input'),
    'signature' => $_SERVER['HTTP_X_JOTTIN_SIGNATURE'] ?? null,
    'timestamp' => $_SERVER['HTTP_X_JOTTIN_TIMESTAMP'] ?? null,
    'count'     => (is_file($log) ? (json_decode((string) file_get_contents($log), true)['count'] ?? 0) : 0) + 1,
];
file_put_contents($log, json_encode($record));
error_log('Received ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . $path);

match ($path) {
    '/fail'     => http_response_code(500),
    '/slow'     => sleep(10),
    '/redirect' => header('Location: /ok', true, 302),
    default     => http_response_code(200),
};
