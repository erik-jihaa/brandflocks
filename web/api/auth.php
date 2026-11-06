<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/password-reset-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$action = preg_match('#/auth/(login|logout|session|forgot-password|reset-password)#', $path, $m) ? $m[1] : '';

$pdo = ce_db();

if ($action === 'login' && $method === 'POST') {
    try {
        ce_json_response(ce_auth_login($pdo, ce_read_json_body()));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 401);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

if ($action === 'logout' && $method === 'POST') {
    ce_json_response(ce_auth_logout());
}

if ($action === 'session' && $method === 'GET') {
    ce_json_response(ce_auth_session($pdo));
}

if ($action === 'forgot-password' && $method === 'POST') {
    $body = ce_read_json_body();
    $email = trim((string) ($body['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ce_json_error('Vul een geldig e-mailadres in', 400);
    }
    ce_json_response(ce_request_password_reset($pdo, $email));
}

if ($action === 'reset-password' && $method === 'GET') {
    $token = trim((string) ($_GET['token'] ?? ''));
    $result = ce_validate_password_reset_token($pdo, $token);
    if (!$result['ok']) {
        ce_json_error($result['error'] ?? 'Ongeldige link', 400);
    }
    ce_json_response([
        'ok' => true,
        'company' => $result['company'] ?? '',
        'clientId' => $result['clientId'] ?? '',
    ]);
}

if ($action === 'reset-password' && $method === 'POST') {
    $body = ce_read_json_body();
    $token = trim((string) ($body['token'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $passwordRetype = (string) ($body['passwordRetype'] ?? $body['password_retype'] ?? '');
    $valid = ce_validate_password_reset_token($pdo, $token);
    if (!$valid['ok']) {
        ce_json_error($valid['error'] ?? 'Ongeldige link', 400);
    }

    $clientId = trim((string) ($valid['clientId'] ?? ''));
    $result = ce_complete_password_reset($pdo, $token, $password, $passwordRetype);
    if (!$result['ok']) {
        ce_json_error($result['error'] ?? 'Reset mislukt', 400);
    }

    if ($clientId !== '') {
        require_once __DIR__ . '/session-repository.php';
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $row = $stmt->fetch() ?: null;
        ce_establish_auth_session(
            $clientId,
            true,
            strtolower(trim((string) ($row['contact_email'] ?? ''))),
        );
        ce_json_response([
            'ok' => true,
            'authenticated' => true,
            'account' => $row ? ce_account_dto($row) : null,
        ]);
    }

    ce_json_response(['ok' => true]);
}

ce_json_error('Not found', 404);
