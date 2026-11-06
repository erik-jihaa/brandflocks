<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/platform-users-repository.php';
require_once __DIR__ . '/platform-password-reset-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$action = preg_match('#/platform/auth/(login|logout|session|forgot-password|reset-password)#', $path, $m) ? $m[1] : '';

$pdo = ce_db();

if ($action === 'login' && $method === 'POST') {
    try {
        ce_json_response(ce_platform_auth_login($pdo, ce_read_json_body()));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 401);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 503);
    }
}

if ($action === 'logout' && $method === 'POST') {
    ce_json_response(ce_platform_auth_logout());
}

if ($action === 'session' && $method === 'GET') {
    ce_json_response(ce_platform_auth_session($pdo));
}

if ($action === 'forgot-password' && $method === 'POST') {
    $body = ce_read_json_body();
    $email = trim((string) ($body['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ce_json_error('Vul een geldig e-mailadres in', 400);
    }
    ce_json_response(ce_request_platform_password_reset($pdo, $email));
}

if ($action === 'reset-password' && $method === 'GET') {
    $token = trim((string) ($_GET['token'] ?? ''));
    $result = ce_validate_platform_password_reset_token($pdo, $token);
    if (!$result['ok']) {
        ce_json_error($result['error'] ?? 'Ongeldige link', 400);
    }
    ce_json_response([
        'ok' => true,
        'name' => $result['name'] ?? '',
        'platformUserId' => $result['platformUserId'] ?? '',
    ]);
}

if ($action === 'reset-password' && $method === 'POST') {
    $body = ce_read_json_body();
    $token = trim((string) ($body['token'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $passwordRetype = (string) ($body['passwordRetype'] ?? $body['password_retype'] ?? '');
    $valid = ce_validate_platform_password_reset_token($pdo, $token);
    if (!$valid['ok']) {
        ce_json_error($valid['error'] ?? 'Ongeldige link', 400);
    }

    $result = ce_complete_platform_password_reset($pdo, $token, $password, $passwordRetype);
    if (!$result['ok']) {
        ce_json_error($result['error'] ?? 'Reset mislukt', 400);
    }

    $userId = trim((string) ($result['platformUserId'] ?? $valid['platformUserId'] ?? ''));
    if ($userId !== '') {
        ce_establish_platform_session($userId);
        $user = ce_platform_user_by_id($pdo, $userId);
        ce_json_response([
            'ok' => true,
            'authenticated' => true,
            'user' => $user,
        ]);
    }

    ce_json_response(['ok' => true]);
}

ce_json_error('Not found', 404);
