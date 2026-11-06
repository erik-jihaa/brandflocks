<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/auth-guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

// Wist de pijplijn van alle tenants: alleen met de service-token, nooit met een tenantsessie.
if (!ce_reset_token_valid()) {
    ce_json_error('Reset-token vereist', 403);
}

ce_require_destructive_confirmation('reset-content');

$counts = ce_reset_pipeline_content($pdo);

ce_json_response([
    'ok' => true,
    'deleted' => $counts,
]);
