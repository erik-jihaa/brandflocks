<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/article-repository.php';

$slotId = $_GET['slot_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('Invalid slot_id', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$body = ce_read_json_body();
$action = trim((string) ($body['action'] ?? 'search'));

if ($action === 'accept') {
    $result = ce_accept_adhoc_research_item($pdo, $slotId, $body);
    if (($result['ok'] ?? false) !== true) {
        ce_json_error((string) ($result['error'] ?? 'Accepteren mislukt'), 502);
    }

    ce_json_response([
        'ok' => true,
        'item' => $result['item'] ?? null,
    ]);
}

$result = ce_search_claim_evidence($pdo, $slotId, $body);
if (($result['ok'] ?? false) !== true) {
    ce_json_error((string) ($result['error'] ?? 'Zoeken mislukt'), 502);
}

ce_json_response([
    'ok' => true,
    'results' => $result['results'] ?? [],
]);
