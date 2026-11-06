<?php

declare(strict_types=1);

require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/schema-helper.php';

function ce_auth_is_authenticated(): bool
{
    ce_session_start();
    require_once __DIR__ . '/session-repository.php';
    ce_enforce_tenant_session_idle();

    return !empty($_SESSION['ce_authenticated']);
}

function ce_auth_is_disabled(): bool
{
    ce_load_env();
    $flag = strtolower(trim((string) (getenv('CE_AUTH_DISABLED') ?: '')));
    if (in_array($flag, ['1', 'true', 'yes'], true)) {
        return true;
    }

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config) && !empty($config['auth_disabled'])) {
            return true;
        }
    }

    return false;
}

function ce_auth_is_required(PDO $pdo): bool
{
    if (ce_auth_is_disabled()) {
        return false;
    }

    if (!ce_table_exists($pdo, 'clients')) {
        return false;
    }

    if (!ce_table_has_column($pdo, 'clients', 'password_hash')) {
        return false;
    }

    $count = (int) $pdo->query(
        "SELECT COUNT(*) FROM clients WHERE password_hash IS NOT NULL AND TRIM(password_hash) <> ''"
    )->fetchColumn();

    return $count > 0;
}

function ce_require_auth(PDO $pdo): void
{
    if (!ce_auth_is_required($pdo)) {
        return;
    }

    if (!ce_auth_is_authenticated()) {
        ce_json_error('Login vereist', 401);
    }

    require_once __DIR__ . '/session-repository.php';
    ce_touch_tenant_session_activity();
}

/** @return list<string> */
function ce_public_api_paths(): array
{
    return [
        '/health',
        '/auth/login',
        '/auth/logout',
        '/auth/session',
        '/auth/forgot-password',
        '/auth/reset-password',
    ];
}

function ce_api_path_is_public(string $path): bool
{
    if (in_array($path, ce_public_api_paths(), true)) {
        return true;
    }

    if ($path === '/sources/analytics/oauth/callback') {
        return true;
    }

    if ($path === '/sources/calendar/oauth/callback') {
        return true;
    }

    if ($path === '/account/linkedin/oauth/callback') {
        return true;
    }

    return (bool) preg_match('#^/review/#', $path);
}
