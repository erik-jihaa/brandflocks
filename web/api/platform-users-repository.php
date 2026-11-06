<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';

function ce_platform_user_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function ce_ensure_platform_users_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_exists($pdo, 'platform_users')) {
        return;
    }

    $migration = dirname(__DIR__, 2) . '/scripts/migrations/add_platform_users_table.sql';
    if (is_readable($migration)) {
        try {
            $pdo->exec((string) file_get_contents($migration));
        } catch (Throwable) {
            // Lazy fallback below.
        }
    }

    if (ce_table_exists($pdo, 'platform_users')) {
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS platform_users (
              id CHAR(36) NOT NULL PRIMARY KEY,
              email VARCHAR(255) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL DEFAULT '',
              role VARCHAR(20) NOT NULL DEFAULT 'support',
              active TINYINT(1) NOT NULL DEFAULT 1,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uniq_platform_users_email (email),
              KEY idx_platform_users_active (active)
            ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL
        );
    } catch (Throwable) {
        // Geen CREATE-rechten.
    }
}

/** @return array<string, mixed>|null */
function ce_platform_user_row_to_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'role' => (string) ($row['role'] ?? 'support'),
        'active' => !empty($row['active']),
    ];
}

/** @return array<string, mixed>|null */
function ce_platform_user_by_id(PDO $pdo, string $userId): ?array
{
    ce_ensure_platform_users_schema($pdo);
    if (!ce_table_exists($pdo, 'platform_users')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM platform_users WHERE id = :id AND active = 1 LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    return $row ? ce_platform_user_row_to_dto($row) : null;
}

function ce_resolve_platform_user_id(): ?string
{
    ce_session_start();
    $id = trim((string) ($_SESSION['ce_platform_user_id'] ?? ''));

    return $id !== '' ? $id : null;
}

function ce_platform_auth_is_authenticated(): bool
{
    ce_session_start();

    return !empty($_SESSION['ce_platform_authenticated']) && ce_resolve_platform_user_id() !== null;
}

/** @return array<string, mixed>|null */
function ce_platform_current_user(PDO $pdo): ?array
{
    $userId = ce_resolve_platform_user_id();
    if ($userId === null) {
        return null;
    }

    return ce_platform_user_by_id($pdo, $userId);
}

function ce_establish_platform_session(string $userId): void
{
    ce_session_start();
    session_regenerate_id(true);
    $_SESSION['ce_platform_user_id'] = $userId;
    $_SESSION['ce_platform_authenticated'] = true;
}

function ce_clear_platform_session(): void
{
    ce_session_start();
    unset($_SESSION['ce_platform_user_id'], $_SESSION['ce_platform_authenticated']);
}

function ce_platform_password_matches(PDO $pdo, array $row, string $password): bool
{
    $hash = trim((string) ($row['password_hash'] ?? ''));
    if ($hash !== '' && password_verify($password, $hash)) {
        return true;
    }

    require_once __DIR__ . '/account-repository.php';

    $email = strtolower(trim((string) ($row['email'] ?? '')));
    if ($email !== '' && ce_table_exists($pdo, 'clients')) {
        $stmt = $pdo->prepare(
            'SELECT * FROM clients WHERE LOWER(TRIM(contact_email)) = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $client = $stmt->fetch();
        if ($client && ce_client_password_matches($client, $password)) {
            return true;
        }
    }

    return false;
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_platform_auth_login(PDO $pdo, array $body): array
{
    ce_ensure_platform_users_schema($pdo);
    if (!ce_table_exists($pdo, 'platform_users')) {
        throw new RuntimeException('Platform-login is nog niet ingericht');
    }

    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    if ($email === '' || $password === '') {
        throw new InvalidArgumentException('E-mail en wachtwoord zijn verplicht');
    }

    require_once __DIR__ . '/login-rate-limit.php';
    ce_login_rate_limit_assert($email);

    $stmt = $pdo->prepare(
        'SELECT * FROM platform_users WHERE LOWER(TRIM(email)) = :email AND active = 1 LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();
    if (!$row || !ce_platform_password_matches($pdo, $row, $password)) {
        ce_login_attempts_register($email);
        throw new InvalidArgumentException('Onjuist e-mailadres of wachtwoord');
    }

    ce_establish_platform_session((string) $row['id']);
    ce_login_attempts_reset($email);

    return [
        'ok' => true,
        'user' => ce_platform_user_row_to_dto($row),
    ];
}

/** @return array<string, mixed> */
function ce_platform_auth_session(PDO $pdo): array
{
    ce_session_start();
    $user = ce_platform_current_user($pdo);

    return [
        'ok' => true,
        'authenticated' => $user !== null,
        'user' => $user,
    ];
}

/** @return array<string, mixed> */
function ce_platform_auth_logout(): array
{
    ce_clear_platform_session();

    return ['ok' => true];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_create_platform_user_admin(PDO $pdo, array $body): array
{
    ce_ensure_platform_users_schema($pdo);
    if (!ce_table_exists($pdo, 'platform_users')) {
        throw new RuntimeException('platform_users-tabel kon niet worden aangemaakt');
    }

    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $name = trim((string) ($body['name'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $role = strtolower(trim((string) ($body['role'] ?? 'admin')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Geldig e-mailadres is verplicht');
    }
    if ($name === '') {
        $name = strstr($email, '@', true) ?: $email;
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Wachtwoord moet minimaal 8 tekens zijn');
    }
    if (!in_array($role, ['admin', 'support'], true)) {
        throw new InvalidArgumentException('role moet admin of support zijn');
    }

    $dup = $pdo->prepare('SELECT id FROM platform_users WHERE LOWER(email) = :email LIMIT 1');
    $dup->execute(['email' => $email]);
    if ($dup->fetch()) {
        throw new InvalidArgumentException('E-mailadres bestaat al');
    }

    $id = ce_platform_user_uuid();
    $pdo->prepare(
        'INSERT INTO platform_users (id, email, password_hash, name, role, active)
         VALUES (:id, :email, :password_hash, :name, :role, 1)'
    )->execute([
        'id' => $id,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'role' => $role,
    ]);

    $row = ce_platform_user_by_id($pdo, $id);
    if ($row === null) {
        throw new RuntimeException('Platform-user aanmaken mislukt');
    }

    return ['ok' => true, 'user' => $row];
}
