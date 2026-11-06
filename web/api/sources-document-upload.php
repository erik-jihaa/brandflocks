<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/source-document-repository.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/auth-guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$sourceId = $_GET['source_id'] ?? '';
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

$pdo = ce_db();

// Deze route draait vóór de centrale inlogcontrole in index.php.
ce_require_auth($pdo);

$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 400);
}

$stmt = $pdo->prepare('SELECT id, category, client_id FROM sources WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $sourceId]);
$row = $stmt->fetch();
if (!$row || (string) ($row['client_id'] ?? '') !== $clientId) {
    ce_json_error('Bron niet gevonden', 404);
}
if ((string) ($row['category'] ?? '') !== 'document') {
    ce_json_error('Geen document-bron', 400);
}

if (!isset($_FILES['document']) || !is_array($_FILES['document'])) {
    ce_json_error('Document ontbreekt', 400);
}

$result = ce_source_document_store_upload($pdo, $sourceId, $clientId, $_FILES['document']);
if (!($result['ok'] ?? false)) {
    ce_json_error((string) ($result['message'] ?? 'Upload mislukt'), 400);
}

$source = ce_fetch_source($pdo, $sourceId);
ce_json_response([
    'ok' => true,
    'message' => $result['message'] ?? null,
    'itemCount' => $result['itemCount'] ?? 0,
    'source' => $source,
]);
