<?php

declare(strict_types=1);

const CE_LOGIN_MAX_ATTEMPTS = 10;
const CE_LOGIN_LOCKOUT_SECONDS = 900;

function ce_login_rate_limit_storage_dir(): string
{
    return dirname(__DIR__) . '/uploads/login-attempts';
}

/** Sleutel per e-mailadres en per IP, zodat geen van beide vrij kan rammen. */
function ce_login_rate_limit_keys(string $email): array
{
    $keys = [];

    $email = strtolower(trim($email));
    if ($email !== '') {
        $keys[] = 'email-' . sha1($email);
    }

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip !== '') {
        $keys[] = 'ip-' . sha1($ip);
    }

    return $keys;
}

function ce_login_rate_limit_path(string $key): string
{
    return ce_login_rate_limit_storage_dir() . '/' . $key . '.json';
}

/** @return array{attempts: int, first: int} */
function ce_login_attempts_load(string $key): array
{
    $path = ce_login_rate_limit_path($key);
    if (!is_readable($path)) {
        return ['attempts' => 0, 'first' => 0];
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return ['attempts' => 0, 'first' => 0];
    }

    $first = max(0, (int) ($data['first'] ?? 0));
    if ($first > 0 && (time() - $first) > CE_LOGIN_LOCKOUT_SECONDS) {
        return ['attempts' => 0, 'first' => 0];
    }

    return ['attempts' => max(0, (int) ($data['attempts'] ?? 0)), 'first' => $first];
}

/** Seconden tot de poort weer open gaat; 0 als er geen blokkade is. */
function ce_login_retry_after(string $email): int
{
    $wait = 0;
    foreach (ce_login_rate_limit_keys($email) as $key) {
        $state = ce_login_attempts_load($key);
        if ($state['attempts'] < CE_LOGIN_MAX_ATTEMPTS) {
            continue;
        }
        $wait = max($wait, CE_LOGIN_LOCKOUT_SECONDS - (time() - $state['first']));
    }

    return max(0, $wait);
}

function ce_login_attempts_register(string $email): void
{
    $dir = ce_login_rate_limit_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    foreach (ce_login_rate_limit_keys($email) as $key) {
        $state = ce_login_attempts_load($key);
        $state['attempts']++;
        if ($state['first'] === 0) {
            $state['first'] = time();
        }
        file_put_contents(ce_login_rate_limit_path($key), json_encode($state), LOCK_EX);
    }
}

function ce_login_attempts_reset(string $email): void
{
    foreach (ce_login_rate_limit_keys($email) as $key) {
        $path = ce_login_rate_limit_path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** Gooit een 429 als er te veel mislukte pogingen zijn. */
function ce_login_rate_limit_assert(string $email): void
{
    $wait = ce_login_retry_after($email);
    if ($wait <= 0) {
        return;
    }

    $minutes = max(1, (int) ceil($wait / 60));
    header('Retry-After: ' . $wait);
    ce_json_error(
        'Te veel mislukte inlogpogingen. Probeer het over ' . $minutes . ' minuten opnieuw.',
        429,
    );
}
