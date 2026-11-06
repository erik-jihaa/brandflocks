<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/platform-users-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$body = ce_read_json_body();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ce_json_error('Geldig e-mailadres is verplicht', 400);
}

if (strlen($password) < 8) {
    ce_json_error('Wachtwoord moet minimaal 8 tekens zijn', 400);
}

$pdo = ce_db();
ce_ensure_platform_users_schema($pdo);

$stmt = $pdo->prepare(
    'SELECT id, email, name, role FROM platform_users WHERE LOWER(TRIM(email)) = :email AND active = 1 LIMIT 1'
);
$stmt->execute(['email' => $email]);
$row = $stmt->fetch();
if (!$row) {
    ce_json_error('Platform-beheerder niet gevonden', 404);
}

$pdo->prepare('UPDATE platform_users SET password_hash = :hash WHERE id = :id')
    ->execute([
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => (string) $row['id'],
    ]);

ce_json_response([
    'ok' => true,
    'email' => (string) $row['email'],
    'name' => (string) $row['name'],
    'role' => (string) $row['role'],
]);
