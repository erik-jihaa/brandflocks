<?php

declare(strict_types=1);

require_once __DIR__ . '/platform-users-repository.php';

function ce_require_platform_auth(PDO $pdo, string $minRole = 'support'): void
{
    // Platform-dashboard nooit open via CE_AUTH_DISABLED.
    if (!ce_platform_auth_is_authenticated()) {
        ce_json_error('Platform-login vereist', 401);
    }

    $user = ce_platform_current_user($pdo);
    if ($user === null) {
        ce_json_error('Platform-login vereist', 401);
    }

    $role = (string) ($user['role'] ?? 'support');
    if ($minRole === 'admin' && $role !== 'admin') {
        ce_json_error('Admin-rechten vereist', 403);
    }
}

function ce_platform_role_allows(string $actualRole, string $requiredRole): bool
{
    if ($requiredRole === 'support') {
        return in_array($actualRole, ['admin', 'support'], true);
    }

    return $actualRole === 'admin';
}
