<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/hba-testmerken-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (ce_is_production()) {
    ce_json_error('Alleen op staging/local', 403);
}

try {
    $body = ce_read_json_body();
    $password = trim((string) ($body['password'] ?? ''));
    $options = [
        'password' => $password !== '' ? $password : null,
        'contactEmail' => trim((string) ($body['contactEmail'] ?? '')),
        'reuseExisting' => filter_var($body['reuseExisting'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ];
    ce_json_response(ce_provision_hba_testmerk_client(ce_db(), $body, array_filter($options)));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 500);
}
