<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/event-log-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

// POST = retentie opruimen (bedoeld voor een dagelijkse cron).
if ($method === 'POST') {
    ce_json_response([
        'ok' => true,
        'environment' => ce_env(),
        'pruned' => ce_prune_event_log($pdo),
    ]);
}

$events = ce_fetch_events($pdo, [
    'severity' => (string) ($_GET['severity'] ?? ''),
    'category' => (string) ($_GET['category'] ?? ''),
    'clientId' => (string) ($_GET['client_id'] ?? ''),
    'days' => (int) ($_GET['days'] ?? 7),
    'limit' => (int) ($_GET['limit'] ?? 100),
]);

$counts = [];
foreach ($events as $event) {
    $key = (string) ($event['severity'] ?? 'info');
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

ce_json_response([
    'ok' => true,
    'environment' => ce_env(),
    'count' => count($events),
    'countsBySeverity' => $counts,
    'events' => $events,
]);
