<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/analytics-oauth-helper.php';

$sourceId = trim((string) ($_GET['source_id'] ?? ''));
if (!preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_error('Invalid source_id', 400);
}

$pdo = ce_db();

if (!ce_analytics_oauth_configured()) {
    ce_json_response([
        'configured' => false,
        'note' => 'Google OAuth ontbreekt — stel GOOGLE_CLIENT_ID en GOOGLE_CLIENT_SECRET in (.env).',
    ]);
}

$start = ce_analytics_oauth_start($pdo, $sourceId, trim((string) ($_GET['return_to'] ?? 'sources')));
if ($start === null) {
    ce_json_error('OAuth start mislukt', 503);
}

ce_json_response([
    'configured' => true,
    'authUrl' => $start['authUrl'],
]);
