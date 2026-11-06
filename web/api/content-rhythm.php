<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-rhythm-repository.php';
require_once __DIR__ . '/hba-v2-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 400);
}

if ($method === 'GET') {
    $ctx = function_exists('ce_hba_v2_architecture_context')
        ? ce_hba_v2_architecture_context($pdo, $clientId)
        : ['archetype' => null, 'branche_code' => null];
    $rhythm = ce_content_rhythm_ensure_proposed(
        $pdo,
        $clientId,
        $ctx['archetype'] ?? null,
        $ctx['branche_code'] ?? null,
    );
    ce_content_rhythm_sync_posts_from_client($pdo, $clientId);
    $rhythm = ce_content_rhythm_active($pdo, $clientId) ?? $rhythm;
    ce_json_response(['ok' => true, 'rhythm' => $rhythm]);
}

if ($method === 'PATCH' || $method === 'POST') {
    $rhythm = ce_content_rhythm_save($pdo, $clientId, ce_read_json_body());
    ce_json_response(['ok' => true, 'rhythm' => $rhythm]);
}

ce_json_error('Method not allowed', 405);
