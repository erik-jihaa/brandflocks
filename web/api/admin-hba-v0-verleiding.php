<?php

declare(strict_types=1);

/**
 * V0 verleidingstest: fastlane aanmaken als test-tenant (reset-token).
 * POST JSON: clientId, theme, researchUrls (max 3).
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/fastlane-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('POST verplicht', 405);
}

$pdo = ce_db();
$body = ce_read_json_body();
$clientId = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ''));
$theme = trim((string) ($body['theme'] ?? $body['title'] ?? ''));
$urls = $body['researchUrls'] ?? $body['research_urls'] ?? [];

if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('clientId (UUID) verplicht', 400);
}
if ($theme === '') {
    ce_json_error('theme verplicht', 400);
}
if (!is_array($urls) || count($urls) < 1) {
    ce_json_error('researchUrls: minimaal 1 URL', 400);
}

$check = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
$check->execute(['id' => $clientId]);
$client = $check->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    ce_json_error('Client niet gevonden', 404);
}

ce_set_active_client_id($clientId);

try {
    $result = ce_create_fastlane($pdo, [
        'clientId' => $clientId,
        'title' => $theme,
        'theme' => $theme,
        'researchUrls' => $urls,
        'origin' => 'fastlane',
    ]);
} catch (Throwable $e) {
    ce_json_error($e->getMessage(), 400);
}

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'clientName' => (string) ($client['name'] ?? ''),
    'fastlane' => $result,
]);
