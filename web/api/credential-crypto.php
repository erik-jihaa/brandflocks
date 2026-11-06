<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ce_credential_key(): string
{
    ce_load_env();
    $raw = trim((string) (getenv('CE_CREDENTIAL_KEY') ?: ''));
    if ($raw === '') {
        $local = dirname(__DIR__) . '/includes/ce.local.php';
        if (is_readable($local)) {
            $config = require $local;
            if (is_array($config)) {
                $raw = trim((string) ($config['credential_key'] ?? ''));
            }
        }
    }
    if ($raw === '') {
        $raw = trim((string) (getenv('DB_PASS') ?: ''));
    }
    if ($raw === '') {
        throw new RuntimeException('CE_CREDENTIAL_KEY ontbreekt — stel een sleutel in .env');
    }

    return hash('sha256', $raw, true);
}

function ce_credential_is_encrypted(string $value): bool
{
    return str_starts_with(trim($value), 'enc:v1:');
}

function ce_credential_encrypt(string $plaintext): string
{
    $plaintext = trim($plaintext);
    if ($plaintext === '') {
        return '';
    }

    $key = ce_credential_key();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Credential encrypt mislukt');
    }

    return 'enc:v1:' . base64_encode($iv . $tag . $cipher);
}

function ce_credential_decrypt(string $stored): string
{
    $plain = ce_credential_try_decrypt($stored);
    if ($plain === null) {
        if ($stored !== '' && ce_credential_is_encrypted(trim($stored))) {
            throw new RuntimeException('Credential decrypt mislukt');
        }

        return trim($stored);
    }

    return $plain;
}

/**
 * Ontsleutel zonder exception — voor read-paden (home, integraties).
 * Retourneert null bij lege/ongeldige input of verkeerde sleutel.
 */
function ce_credential_try_decrypt(string $stored): ?string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }
    if (!ce_credential_is_encrypted($stored)) {
        return $stored;
    }

    $raw = base64_decode(substr($stored, 7), true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }

    try {
        $key = ce_credential_key();
    } catch (RuntimeException) {
        return null;
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        return null;
    }

    return $plain;
}

function ce_credential_prepare_for_storage(?string $incoming, ?string $existing): string
{
    $incoming = trim((string) ($incoming ?? ''));
    $existing = trim((string) ($existing ?? ''));

    if ($incoming === '') {
        return $existing;
    }

    if (ce_credential_is_encrypted($incoming)) {
        return $incoming;
    }

    return ce_credential_encrypt($incoming);
}

function ce_credential_resolve_plaintext(?string $incoming, ?string $stored): string
{
    $incoming = trim((string) ($incoming ?? ''));
    if ($incoming !== '') {
        return $incoming;
    }

    $stored = trim((string) ($stored ?? ''));
    if ($stored === '') {
        return '';
    }

    $plain = ce_credential_try_decrypt($stored);

    return $plain ?? '';
}
