<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/client-context.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);

try {
    ce_json_response(ce_fetch_pending_research_queue($pdo, $clientId));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
