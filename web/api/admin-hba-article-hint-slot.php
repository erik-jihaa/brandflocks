<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/hba-v2-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$body = $method === 'POST' ? ce_read_json_body() : [];
$slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? $_GET['slot_id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('slot_id verplicht (UUID)', 400);
}

$pdo = ce_db();
ce_consolidate_slot_articles($pdo, $slotId);

$slotStmt = $pdo->prepare('SELECT id, client_id FROM content_slots WHERE id = :id LIMIT 1');
$slotStmt->execute(['id' => $slotId]);
$slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
if (!$slot) {
    ce_json_error('Slot niet gevonden', 404);
}

$clientId = (string) ($slot['client_id'] ?? '');
$article = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
if ($article === null) {
    ce_json_error('Geen artikel voor slot', 404);
}

$articleId = (string) ($article['id'] ?? '');
$content = trim((string) ($article['content_ai'] ?? ''));
if ($content === '') {
    ce_json_error('Artikel heeft geen content_ai — CE-03 nog niet klaar', 409);
}

ce_set_active_client_id($clientId);
$force = filter_var($body['force'] ?? $_GET['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
$result = ce_hba_v2_run_article_hint($pdo, $clientId, $articleId, ['force' => $force]);

ce_json_response([
    'ok' => ($result['ok'] ?? false) === true,
    'slotId' => $slotId,
    'clientId' => $clientId,
    'articleId' => $articleId,
    'hint' => $result['hint'] ?? null,
    'code' => $result['code'] ?? null,
    'error' => $result['error'] ?? null,
    'architectureFootnote' => $result['architectureFootnote'] ?? null,
]);
