<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/client-context.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$body = ce_read_json_body();
$password = (string) ($body['password'] ?? '');
$clientId = trim((string) ($body['clientId'] ?? ''));
$copyFromClientId = trim((string) ($body['copyFromClientId'] ?? ''));
$issueResetLink = !empty($body['issueResetLink']);

$pdo = ce_db();
ce_ensure_account_columns($pdo);

$clientId = ce_resolve_client_id($pdo, $clientId);
if ($clientId === null) {
    ce_json_error('Geen client gevonden', 404);
}

if ($issueResetLink && $password === '' && $copyFromClientId === '') {
    require_once __DIR__ . '/password-reset-repository.php';
    $stmt = $pdo->prepare('SELECT contact_email, name FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch() ?: [];
    $email = strtolower(trim((string) ($row['contact_email'] ?? '')));
    if ($email === '') {
        ce_json_error('Tenant heeft geen contact-e-mail', 400);
    }
    $token = ce_create_password_reset_token($pdo, $clientId, $email);
    ce_json_response([
        'ok' => true,
        'clientId' => $clientId,
        'company' => (string) ($row['name'] ?? ''),
        'contactEmail' => $email,
        'resetUrl' => ce_password_reset_page_url($token),
        'expiresIn' => '1 hour',
    ]);
}

if ($copyFromClientId !== '') {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $copyFromClientId)) {
        ce_json_error('Invalid copyFromClientId', 400);
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $copyFromClientId]);
    $source = $stmt->fetch() ?: null;
    $hash = trim((string) ($source['password_hash'] ?? ''));
    if ($hash === '') {
        ce_json_error('Bron-tenant heeft geen wachtwoord', 404);
    }
} else {
    if (strlen($password) < 8) {
        ce_json_error('Wachtwoord moet minimaal 8 tekens zijn', 400);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
}
$pdo->prepare('UPDATE clients SET password_hash = :hash WHERE id = :id')
    ->execute(['hash' => $hash, 'id' => $clientId]);

$stmt = $pdo->prepare('SELECT contact_email, name FROM clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $clientId]);
$row = $stmt->fetch() ?: [];

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'company' => (string) ($row['name'] ?? ''),
    'contactEmail' => (string) ($row['contact_email'] ?? ''),
]);
