<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/drive-oauth-helper.php';

$config = ce_drive_oauth_config();
if ($config === null) {
    ce_json_response([
        'configured' => false,
        'note' => 'Google OAuth ontbreekt — stel GOOGLE_CLIENT_ID en GOOGLE_CLIENT_SECRET in (.env).',
    ]);
}

$sourceId = trim((string) ($_GET['source_id'] ?? ''));
if ($sourceId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $sourceId)) {
    ce_json_response([
        'configured' => true,
        'redirectUri' => $config['redirectUri'],
    ]);
}

$pdo = ce_db();
$status = ce_drive_oauth_status($pdo, $sourceId);
ce_json_response([
    'configured' => (bool) ($status['configured'] ?? false),
    'connected' => (bool) ($status['connected'] ?? false),
    'email' => $status['email'] ?? null,
    'redirectUri' => $config['redirectUri'],
]);
