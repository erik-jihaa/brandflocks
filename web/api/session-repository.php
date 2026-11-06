<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

function ce_ensure_sessions_table(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_exists($pdo, 'ce_sessions')) {
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS ce_sessions (
              id VARCHAR(128) NOT NULL PRIMARY KEY,
              data BLOB NOT NULL,
              expires_at INT UNSIGNED NOT NULL,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_ce_sessions_expires (expires_at)
            )
            SQL
        );
    } catch (Throwable) {
        // Geen CREATE-rechten — fallback naar PHP file sessions.
    }
}

final class CeDatabaseSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        if ($id === '') {
            return '';
        }

        $stmt = $this->pdo->prepare(
            'SELECT data FROM ce_sessions WHERE id = :id AND expires_at >= :now LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'now' => time()]);
        $row = $stmt->fetch();
        if (!$row) {
            return '';
        }

        $data = $row['data'] ?? '';
        if (!is_string($data)) {
            return '';
        }

        return $data;
    }

    public function write(string $id, string $data): bool
    {
        if ($id === '') {
            return false;
        }

        $lifetime = (int) ini_get('session.gc_maxlifetime');
        if ($lifetime <= 0) {
            $lifetime = 60 * 60 * 24 * 14;
        }

        $expires = time() + $lifetime;
        $stmt = $this->pdo->prepare(
            <<<'SQL'
INSERT INTO ce_sessions (id, data, expires_at)
VALUES (:id, :data, :expires)
ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)
SQL
        );

        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'expires' => $expires,
        ]);
    }

    public function destroy(string $id): bool
    {
        if ($id === '') {
            return true;
        }

        $this->pdo->prepare('DELETE FROM ce_sessions WHERE id = :id')->execute(['id' => $id]);

        return true;
    }

    public function gc(int $maxlifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM ce_sessions WHERE expires_at < :cutoff');
        $stmt->execute(['cutoff' => time() - max(0, $maxlifetime)]);

        return $stmt->rowCount();
    }
}

function ce_register_db_session_handler(PDO $pdo): void
{
    static $registered = false;
    if ($registered) {
        return;
    }

    ce_ensure_sessions_table($pdo);
    if (!ce_table_exists($pdo, 'ce_sessions')) {
        return;
    }

    session_set_save_handler(new CeDatabaseSessionHandler($pdo), true);
    $registered = true;
}

function ce_tenant_session_idle_seconds(): int
{
    return 60 * 60;
}

/** Tenant-sessie verlopen na inactiviteit — platform-sessies uitgezonderd. */
function ce_enforce_tenant_session_idle(): void
{
    ce_session_start();

    if (empty($_SESSION['ce_authenticated'])) {
        return;
    }

    $last = (int) ($_SESSION['ce_last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > ce_tenant_session_idle_seconds()) {
        unset(
            $_SESSION['ce_authenticated'],
            $_SESSION['ce_client_id'],
            $_SESSION['ce_last_activity'],
            $_SESSION['ce_login_email'],
        );
    }
}

function ce_touch_tenant_session_activity(): void
{
    ce_session_start();

    if (!empty($_SESSION['ce_authenticated'])) {
        $_SESSION['ce_last_activity'] = time();
    }
}

function ce_establish_auth_session(
    string $clientId,
    bool $trackActivity = true,
    string $loginEmail = '',
): void {
    ce_session_start();
    session_regenerate_id(true);
    ce_set_active_client_id($clientId);
    $_SESSION['ce_authenticated'] = true;
    $_SESSION['ce_last_activity'] = time();

    // Het ingelogde e-mailadres bepaalt naar welke tenants gewisseld mag worden.
    $loginEmail = strtolower(trim($loginEmail));
    if ($loginEmail !== '') {
        $_SESSION['ce_login_email'] = $loginEmail;
    } else {
        unset($_SESSION['ce_login_email']);
    }

    // Meekijk-logins tellen niet mee als tenant-activiteit in Platform.
    if (!$trackActivity) {
        return;
    }

    require_once __DIR__ . '/tenant-activity-repository.php';
    ce_track_tenant_activity(null, $clientId, 'logins');
}

function ce_close_session_if_active(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
