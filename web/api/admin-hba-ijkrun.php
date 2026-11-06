<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/hba-ijkrun-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (ce_is_production()) {
    ce_json_error('Alleen op staging/local', 403);
}

$execute = filter_var($_GET['execute'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$id = trim((string) ($_GET['id'] ?? ''));

ce_json_response(ce_hba_ijkrun_payload(ce_db(), [
    'execute' => $execute,
    'id' => $id,
]));
