<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/platform-users-repository.php';
require_once __DIR__ . '/review-repository.php';
require_once __DIR__ . '/mail-helper.php';

function ce_ensure_platform_password_reset_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_exists($pdo, 'platform_password_reset_tokens')) {
        return;
    }

    $migration = dirname(__DIR__, 2) . '/scripts/migrations/add_platform_password_reset_tokens.sql';
    if (is_readable($migration)) {
        try {
            $pdo->exec((string) file_get_contents($migration));
        } catch (Throwable) {
            // Lazy fallback below.
        }
    }

    if (ce_table_exists($pdo, 'platform_password_reset_tokens')) {
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS platform_password_reset_tokens (
              id CHAR(36) NOT NULL PRIMARY KEY,
              platform_user_id CHAR(36) NOT NULL,
              token_hash CHAR(64) NOT NULL,
              email VARCHAR(255) NOT NULL,
              expires_at DATETIME NOT NULL,
              used_at DATETIME NULL DEFAULT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_platform_reset_token_hash (token_hash),
              KEY idx_platform_reset_user (platform_user_id),
              KEY idx_platform_reset_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
    } catch (Throwable) {
        // Geen CREATE-rechten.
    }
}

function ce_platform_password_reset_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function ce_platform_password_reset_page_url(string $token): string
{
    return ce_public_base_url() . '/platform/reset-password/' . rawurlencode($token);
}

function ce_create_platform_password_reset_token(PDO $pdo, string $platformUserId, string $email): string
{
    ce_ensure_platform_password_reset_schema($pdo);

    $token = bin2hex(random_bytes(32));
    $hash = ce_platform_password_reset_token_hash($token);
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE platform_password_reset_tokens SET used_at = NOW()
         WHERE platform_user_id = :user_id AND used_at IS NULL'
    )->execute(['user_id' => $platformUserId]);

    $pdo->prepare(
        'INSERT INTO platform_password_reset_tokens (id, platform_user_id, token_hash, email, expires_at)
         VALUES (:id, :user_id, :token_hash, :email, :expires_at)'
    )->execute([
        'id' => ce_platform_user_uuid(),
        'user_id' => $platformUserId,
        'token_hash' => $hash,
        'email' => strtolower(trim($email)),
        'expires_at' => $expiresAt,
    ]);

    return $token;
}

/** @return array{ok: true, message: string} */
function ce_request_platform_password_reset(PDO $pdo, string $email): array
{
    ce_ensure_platform_users_schema($pdo);
    $message = 'Als dit e-mailadres bij een platform-beheerder hoort, ontvang je binnen enkele minuten een mail met een link om een nieuw wachtwoord in te stellen.';

    $email = strtolower(trim($email));
    if ($email === '' || !ce_table_exists($pdo, 'platform_users')) {
        return ['ok' => true, 'message' => $message];
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email FROM platform_users
         WHERE LOWER(TRIM(email)) = :email AND active = 1 LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['ok' => true, 'message' => $message];
    }

    $userId = (string) ($user['id'] ?? '');
    $name = trim((string) ($user['name'] ?? 'Platform-beheerder'));
    if ($userId === '') {
        return ['ok' => true, 'message' => $message];
    }

    $token = ce_create_platform_password_reset_token($pdo, $userId, $email);
    $url = ce_platform_password_reset_page_url($token);

    $textBody = implode("\n", [
        'Hallo,',
        '',
        'Je hebt een nieuw wachtwoord aangevraagd voor het Content Engine platform-dashboard.',
        '',
        'Open deze link om een nieuw wachtwoord in te stellen (geldig 1 uur):',
        $url,
        '',
        'Als je dit niet hebt aangevraagd, kun je deze mail negeren.',
        '',
        '— Content Engine',
    ]);

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $htmlBody = '<p>Hallo,</p>'
        . '<p>Je hebt een nieuw wachtwoord aangevraagd voor het platform-dashboard'
        . ($safeName !== '' ? ' (' . $safeName . ')' : '')
        . '.</p>'
        . '<p><a href="' . $safeUrl . '">Nieuw wachtwoord instellen</a> (geldig 1 uur)</p>'
        . '<p>Als je dit niet hebt aangevraagd, kun je deze mail negeren.</p>';

    $mail = ce_send_mail($email, 'Content Engine — platform wachtwoord resetten', $textBody, $htmlBody);
    if (!$mail['ok']) {
        error_log('CE platform password reset mail failed: ' . ($mail['error'] ?? 'unknown'));
    }

    return ['ok' => true, 'message' => $message];
}

/** @return array{ok: bool, error?: string, platformUserId?: string, name?: string} */
function ce_validate_platform_password_reset_token(PDO $pdo, string $token): array
{
    ce_ensure_platform_password_reset_schema($pdo);

    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return ['ok' => false, 'error' => 'Ongeldige of verlopen link'];
    }

    $hash = ce_platform_password_reset_token_hash($token);
    $stmt = $pdo->prepare(
        'SELECT t.platform_user_id, t.expires_at, t.used_at, u.name
         FROM platform_password_reset_tokens t
         LEFT JOIN platform_users u ON u.id = t.platform_user_id
         WHERE t.token_hash = :hash
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
        'platformUserId' => (string) ($row['platform_user_id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
    ];
}

/** @return array{ok: bool, error?: string} */
function ce_complete_platform_password_reset(PDO $pdo, string $token, string $password, string $passwordRetype): array
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

    $valid = ce_validate_platform_password_reset_token($pdo, $token);
    if (!$valid['ok']) {
        return ['ok' => false, 'error' => $valid['error'] ?? 'Ongeldige link'];
    }

    $userId = (string) ($valid['platformUserId'] ?? '');
    if ($userId === '' || !ce_table_exists($pdo, 'platform_users')) {
        return ['ok' => false, 'error' => 'Account niet gevonden'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE platform_users SET password_hash = :hash WHERE id = :id')
        ->execute(['hash' => $hash, 'id' => $userId]);

    $tokenHash = ce_platform_password_reset_token_hash($token);
    $pdo->prepare(
        'UPDATE platform_password_reset_tokens SET used_at = NOW() WHERE token_hash = :hash'
    )->execute(['hash' => $tokenHash]);

    return ['ok' => true, 'platformUserId' => $userId];
}
