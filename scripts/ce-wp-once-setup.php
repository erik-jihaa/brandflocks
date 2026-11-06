<?php
/**
 * Eenmalig: API-user + Application Password voor Content Engine → igisnext.com
 * Verwijder dit bestand direct na gebruik.
 */
declare(strict_types=1);

$expectedKey = '__CE_WP_SETUP_KEY__';
$providedKey = (string) ($_GET['key'] ?? '');
if ($expectedKey === '' || $expectedKey === '__CE_WP_SETUP_KEY__' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

define('WP_USE_THEMES', false);
$wpLoad = __DIR__ . '/wp-load.php';
if (!is_file($wpLoad)) {
    $wpLoad = dirname(__DIR__, 3) . '/wp-load.php';
}
if (!is_file($wpLoad)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'wp-load.php not found']);
    exit;
}
require $wpLoad;

if (!class_exists('WP_Application_Passwords')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'WP_Application_Passwords unavailable']);
    exit;
}

$apiUser = 'content-engine';
$apiEmail = 'content-engine@igisnext.com';
$api = get_user_by('login', $apiUser);

if (!$api) {
    $userId = wp_insert_user([
        'user_login' => $apiUser,
        'user_pass' => wp_generate_password(32, true, true),
        'user_email' => $apiEmail,
        'display_name' => 'Content Engine',
        'role' => 'editor',
    ]);
    if (is_wp_error($userId)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $userId->get_error_message()]);
        exit;
    }
    $api = get_user_by('id', (int) $userId);
}

if (!$api) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'API user missing']);
    exit;
}

WP_Application_Passwords::delete_all_application_passwords($api->ID);
$app = WP_Application_Passwords::create_new_application_password($api->ID, ['name' => 'Content Engine']);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'siteUrl' => home_url('/'),
    'apiUser' => $apiUser,
    'applicationPassword' => $app[0] ?? '',
    'wpAdmin' => admin_url(),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
