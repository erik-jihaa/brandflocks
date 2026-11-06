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

function li_ensure_auth_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS app_users (
          email VARCHAR(255) NOT NULL PRIMARY KEY,
          password_hash VARCHAR(255) NOT NULL,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
          id CHAR(36) NOT NULL PRIMARY KEY,
          email VARCHAR(255) NOT NULL,
          token_hash CHAR(64) NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          KEY idx_token_hash (token_hash),
          KEY idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS login_attempts (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          ip VARCHAR(45) NOT NULL,
          email VARCHAR(255) NOT NULL,
          attempted_at DATETIME NOT NULL,
          KEY idx_login_attempts_email_time (email, attempted_at),
          KEY idx_login_attempts_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL
    );
}

/** Seed ontbrekende gebruikers uit config — overschrijft bestaande DB-wachtwoorden niet. */
function li_sync_app_users_from_config(PDO $pdo): void
{
    li_ensure_auth_schema($pdo);

    $configUsers = li_config('app_users', []);
    if (!is_array($configUsers)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO app_users (email, password_hash) VALUES (:email, :hash)'
    );

    foreach ($configUsers as $email => $hash) {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !is_string($hash) || $hash === '') {
            continue;
        }
        $stmt->execute(['email' => $email, 'hash' => $hash]);
    }
}

/** @return array<string, string> email => password_hash */
function li_app_users_from_db(PDO $pdo): array
{
    li_ensure_auth_schema($pdo);
    li_sync_app_users_from_config($pdo);

    $rows = $pdo->query('SELECT email, password_hash FROM app_users')->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return [];
    }

    $users = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        $hash = (string) ($row['password_hash'] ?? '');
        if ($email !== '' && $hash !== '') {
            $users[$email] = $hash;
        }
    }

    return $users;
}

/** @return array<string, string> */
function li_app_users(): array
{
    try {
        $pdo = li_pdo();
        $fromDb = li_app_users_from_db($pdo);
        if ($fromDb !== []) {
            return $fromDb;
        }
    } catch (Throwable) {
        // Database niet bereikbaar — val terug op config.
    }

    $users = li_config('app_users', []);
    if (is_array($users) && $users !== []) {
        return $users;
    }

    $hash = (string) li_config('app_password_hash', '');
    if ($hash === '') {
        return [];
    }

    $email = strtolower(trim((string) li_config('app_default_email', 'erik@igisnext.com')));

    return [$email => $hash];
}

function li_user_exists(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    return isset(li_app_users()[$email]);
}

function li_update_user_password(PDO $pdo, string $email, string $passwordHash): void
{
    li_ensure_auth_schema($pdo);
    $email = strtolower(trim($email));

    $pdo->prepare(
        'INSERT INTO app_users (email, password_hash) VALUES (:email, :hash)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    )->execute([
        'email' => $email,
        'hash' => $passwordHash,
    ]);
}

function li_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function li_login_is_blocked(PDO $pdo, string $email, string $ip): bool
{
    li_ensure_auth_schema($pdo);
    $email = strtolower(trim($email));
    $ip = substr(trim($ip), 0, 45);

    $stmt = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN email = :email THEN 1 ELSE 0 END) AS email_count,
            SUM(CASE WHEN ip = :ip THEN 1 ELSE 0 END) AS ip_count
         FROM login_attempts
         WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
           AND (email = :email2 OR ip = :ip2)'
    );
    $stmt->execute([
        'email' => $email,
        'ip' => $ip,
        'email2' => $email,
        'ip2' => $ip,
    ]);
    $row = $stmt->fetch();
    $emailCount = is_array($row) ? (int) ($row['email_count'] ?? 0) : 0;
    $ipCount = is_array($row) ? (int) ($row['ip_count'] ?? 0) : 0;

    return $emailCount >= 5 || $ipCount >= 5;
}

function li_login_record_failure(PDO $pdo, string $email, string $ip): void
{
    li_ensure_auth_schema($pdo);
    $email = strtolower(trim($email));
    $ip = substr(trim($ip), 0, 45);

    $pdo->prepare(
        'INSERT INTO login_attempts (ip, email, attempted_at) VALUES (:ip, :email, NOW())'
    )->execute([
        'ip' => $ip,
        'email' => $email !== '' ? $email : '(empty)',
    ]);
}

function li_login_clear_failures(PDO $pdo, string $email): void
{
    li_ensure_auth_schema($pdo);
    $email = strtolower(trim($email));
    if ($email === '') {
        return;
    }

    $pdo->prepare('DELETE FROM login_attempts WHERE email = :email')->execute(['email' => $email]);
}
