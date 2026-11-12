<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$config = ce_db_config();

if ($config['host'] === '' || $config['name'] === '' || $config['user'] === '') {
    ce_json_response([
        'ok' => false,
        'environment' => ce_env(),
        'branding' => ce_product_branding(),
        'db' => 'not_configured',
    ], 503);
}

try {
    $pdo = ce_db();
    $pdo->query('SELECT 1');

    $migration = null;
    try {
        $migration = $pdo->query(
            'SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at DESC, filename DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable) {
        // schema_migrations ontbreekt nog — geen reden om health te laten falen.
    }

    ce_json_response([
        'ok' => true,
        'environment' => ce_env(),
        'branding' => ce_product_branding(),
        'db' => 'connected',
        'imap' => function_exists('imap_open'),
        'lastMigration' => $migration,
    ]);
} catch (Throwable $e) {
    ce_json_response([
        'ok' => false,
        'environment' => ce_env(),
        'branding' => ce_product_branding(),
        'db' => 'error',
        'message' => $e->getMessage(),
    ], 503);
}
