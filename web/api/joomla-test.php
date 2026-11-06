<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/publish-settings-repository.php';
require_once __DIR__ . '/joomla-publish-repository.php';
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
$config = ce_joomla_config_for_client($pdo, $clientId);

if (array_key_exists('joomlaSiteUrl', $body)) {
    $config['site_url'] = ce_normalize_joomla_site_url(trim((string) $body['joomlaSiteUrl']));
}
if (array_key_exists('joomlaCategoryId', $body)) {
    $config['category_id'] = max(0, (int) $body['joomlaCategoryId']);
}
$draftToken = trim((string) ($body['joomlaApiToken'] ?? ''));
if ($draftToken !== '') {
    $config['api_token'] = $draftToken;
}

$test = ce_joomla_test_connection($config);
$tenantConfigured = ce_client_joomla_configured($pdo, $clientId);

ce_json_response([
    'ok' => true,
    'connected' => $test['connected'] && $test['categoryOk'],
    'authError' => $test['authError'],
    'categoryOk' => $test['categoryOk'],
    'categoryTitle' => $test['categoryTitle'],
    'siteUrl' => $config['site_url'],
    'categoryId' => $config['category_id'] > 0 ? $config['category_id'] : null,
    'hasApiToken' => $config['api_token'] !== '',
    'tenantJoomlaConfigured' => $tenantConfigured,
]);
