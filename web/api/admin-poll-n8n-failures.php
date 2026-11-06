<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/n8n-poll-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$result = ce_poll_n8n_failed_executions($pdo);

ce_json_response([
    'ok' => $result['ok'],
    'environment' => ce_env(),
    'error' => $result['error'] ?? null,
    'fetched' => $result['fetched'],
    'logged' => $result['logged'],
    'skipped' => $result['skipped'],
]);
