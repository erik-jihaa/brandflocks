<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$updated = ce_fix_pipeline_slot_statuses(ce_db());

ce_json_response([
    'ok' => true,
    'slots_updated' => $updated,
]);
