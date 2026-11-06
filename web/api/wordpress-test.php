<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/client-context.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_auth_is_authenticated()) {
    ce_json_error('Login vereist', 401);
}

$pdo = ce_db();
$clientId = ce_resolve_client_id($pdo);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

$body = ce_read_json_body();
$config = ce_wordpress_config_for_client($pdo, $clientId, false);

if (array_key_exists('wordpressSiteUrl', $body)) {
    $config['site_url'] = ce_normalize_wordpress_site_url(trim((string) $body['wordpressSiteUrl']));
}
if (array_key_exists('wordpressUser', $body)) {
    $config['user'] = ce_normalize_wordpress_user(trim((string) $body['wordpressUser']));
}
$draftPass = ce_normalize_wordpress_app_password(trim((string) ($body['wordpressAppPassword'] ?? '')));
if ($draftPass !== '') {
    $config['app_password'] = $draftPass;
}

$test = ce_wordpress_test_connection($config);
$tenantConfigured = ce_client_wordpress_configured($pdo, $clientId);

ce_json_response([
    'ok' => true,
    'connected' => $test['connected'],
    'authError' => $test['authError'],
    'wpErrorCode' => $test['wpErrorCode'],
    'authenticatedAs' => $test['authenticatedAs'],
    'siteUrl' => $config['site_url'],
    'user' => $config['user'],
    'hasAppPassword' => $config['app_password'] !== '',
    'tenantWordPressConfigured' => $tenantConfigured,
]);
