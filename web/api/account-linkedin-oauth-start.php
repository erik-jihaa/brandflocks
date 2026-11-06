<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';

$pdo = ce_db();

if (!ce_linkedin_oauth_configured()) {
    ce_json_response([
        'configured' => false,
        'note' => 'LinkedIn OAuth ontbreekt — stel LINKEDIN_CLIENT_ID en LINKEDIN_CLIENT_SECRET in (.env).',
    ]);
}

$clientId = ce_resolve_client_id($pdo, (string) ($_GET['client_id'] ?? ''));
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 400);
}

$start = ce_linkedin_oauth_start($pdo, $clientId);
if ($start === null) {
    ce_json_error('LinkedIn OAuth start mislukt', 503);
}

ce_json_response([
    'configured' => true,
    'authUrl' => $start['authUrl'],
]);
