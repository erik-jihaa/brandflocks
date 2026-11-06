<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-baseline-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$clientId = trim((string) ($_GET['client_id'] ?? $_GET['clientId'] ?? ''));
$run = ($_GET['run'] ?? '') === '1';
$scheduleOnly = ($_GET['schedule'] ?? '') === '1';
$maxPosts = max(1, min(CE_HBA_BASELINE_MAX_POSTS, (int) ($_GET['limit'] ?? CE_HBA_BASELINE_MAX_POSTS)));

if ($scheduleOnly && $clientId !== '') {
    ce_hba_baseline_schedule($pdo, $clientId);
    ce_json_response([
        'ok' => true,
        'scheduled' => true,
        'state' => ce_hba_baseline_client_state($pdo, $clientId),
    ]);
}

if ($run) {
    ce_json_response(ce_hba_baseline_run_pending($pdo, $clientId !== '' ? $clientId : null, $maxPosts));
}

$pending = ce_hba_baseline_pending_client_ids($pdo);
$states = [];
foreach ($pending as $id) {
    $states[$id] = ce_hba_baseline_client_state($pdo, $id);
}

ce_json_response([
    'ok' => true,
    'pendingClients' => $pending,
    'states' => $states,
    'hint' => 'Run: GET /api/admin/hba-baseline?run=1&client_id=...',
]);
