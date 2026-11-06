<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth-repository.php';
require_once __DIR__ . '/mail-helper.php';

function li_password_reset_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function li_password_reset_page_url(string $token): string
{
    return rtrim(li_base_url(), '/') . '/reset-password.php?token=' . rawurlencode($token);
}

function li_create_password_reset_token(PDO $pdo, string $email): string
{
    li_ensure_auth_schema($pdo);

    $email = strtolower(trim($email));
    $token = bin2hex(random_bytes(32));
    $hash = li_password_reset_token_hash($token);
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE password_reset_tokens SET used_at = NOW()
         WHERE email = :email AND used_at IS NULL'
    )->execute(['email' => $email]);

    $pdo->prepare(
        'INSERT INTO password_reset_tokens (id, email, token_hash, expires_at)
         VALUES (:id, :email, :token_hash, :expires_at)'
    )->execute([
        'id' => li_uuid(),
        'email' => $email,
        'token_hash' => $hash,
        'expires_at' => $expiresAt,
    ]);

    return $token;
}

/** @return array{ok: true, message: string} */
function li_request_password_reset(PDO $pdo, string $email): array
{
    $message = 'Als dit e-mailadres bij een account hoort, ontvang je binnen enkele minuten een mail met een link om een nieuw wachtwoord in te stellen.';

    $email = strtolower(trim($email));
    if ($email === '' || !li_user_exists($email)) {
        return ['ok' => true, 'message' => $message];
    }

    $token = li_create_password_reset_token($pdo, $email);
    $url = li_password_reset_page_url($token);

    $textBody = implode("\n", [
        'Hallo,',
        '',
        'Je hebt een nieuw wachtwoord aangevraagd voor de LinkedIn Scan.',
        '',
        'Open deze link om een nieuw wachtwoord in te stellen (geldig 1 uur):',
        $url,
        '',
        'Als je dit niet hebt aangevraagd, kun je deze mail negeren.',
        '',
        '— LinkedIn Scan / ContentFlow',
    ]);

    $urlEsc = li_h($url);
    $htmlBody = '<p>Hallo,</p>'
        . '<p>Je hebt een nieuw wachtwoord aangevraagd voor de LinkedIn Scan.</p>'
        . '<p><a href="' . $urlEsc . '">Nieuw wachtwoord instellen</a> (geldig 1 uur)</p>'
        . '<p>Als je dit niet hebt aangevraagd, kun je deze mail negeren.</p>';

    $mail = li_send_mail($email, 'LinkedIn Scan — wachtwoord resetten', $textBody, $htmlBody);
    if (!$mail['ok']) {
        error_log('LinkedIn Scan password reset mail failed: ' . ($mail['error'] ?? 'unknown'));
    }

    return ['ok' => true, 'message' => $message];
}

/** @return array{ok: bool, error?: string, email?: string} */
function li_validate_password_reset_token(PDO $pdo, string $token): array
{
    li_ensure_auth_schema($pdo);

    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return ['ok' => false, 'error' => 'Ongeldige of verlopen link'];
    }

    $hash = li_password_reset_token_hash($token);
    $stmt = $pdo->prepare(
        'SELECT email, expires_at, used_at
         FROM password_reset_tokens
         WHERE token_hash = :hash
         LIMIT 1'
    );
    $stmt->execute(['hash' => $hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Ongeldige of verlopen link'];
    }

    if (!empty($row['used_at'])) {
        return ['ok' => false, 'error' => 'Deze link is al gebruikt'];
    }

    $expires = strtotime((string) ($row['expires_at'] ?? ''));
    if ($expires === false || $expires < time()) {
        return ['ok' => false, 'error' => 'Deze link is verlopen — vraag een nieuwe aan'];
    }

    return [
        'ok' => true,
        'email' => (string) ($row['email'] ?? ''),
    ];
}

/** @return array{ok: bool, error?: string} */
function li_complete_password_reset(PDO $pdo, string $token, string $password, string $passwordRetype): array
{
    if ($password === '' || $passwordRetype === '') {
        return ['ok' => false, 'error' => 'Wachtwoord is verplicht'];
    }
    if ($password !== $passwordRetype) {
        return ['ok' => false, 'error' => 'Wachtwoorden komen niet overeen'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Wachtwoord moet minimaal 8 tekens zijn'];
    }

    $valid = li_validate_password_reset_token($pdo, $token);
    if (!$valid['ok']) {
        return ['ok' => false, 'error' => $valid['error'] ?? 'Ongeldige link'];
    }

    $email = strtolower(trim((string) ($valid['email'] ?? '')));
    if ($email === '' || !li_user_exists($email)) {
        return ['ok' => false, 'error' => 'Account niet gevonden'];
    }

    li_update_user_password($pdo, $email, password_hash($password, PASSWORD_DEFAULT));

    $tokenHash = li_password_reset_token_hash($token);
    $pdo->prepare(
        'UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = :hash'
    )->execute(['hash' => $tokenHash]);

    return ['ok' => true];
}
