<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/linkedin-oauth-helper.php';

$pdo = ce_db();

if (!ce_linkedin_oauth_configured()) {
    ce_json_response([
        'configured' => false,
        'connected' => false,
        'note' => 'LinkedIn OAuth ontbreekt — stel LINKEDIN_CLIENT_ID en LINKEDIN_CLIENT_SECRET in (.env).',
    ]);
}

$clientId = ce_resolve_client_id($pdo, (string) ($_GET['client_id'] ?? ''));
$status = ce_linkedin_oauth_status_for_client($pdo, $clientId);

ce_json_response([
    'configured' => true,
    'connected' => $status['connected'],
    'memberName' => $status['memberName'],
    'organizationSocialGranted' => $status['organizationSocialGranted'],
    'oauthScopes' => $status['oauthScopes'],
]);
