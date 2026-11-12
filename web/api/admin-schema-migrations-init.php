<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

/** @return list<string> */
function ce_pre_baseline_migration_filenames(): array
{
    $dir = dirname(__DIR__) . '/migrations/archive/pre-baseline-2026-11';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    $names = array_map('basename', $files);
    sort($names);

    return array_values($names);
}

$pdo = ce_db();

$pdo->exec(
    <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
);

$insert = $pdo->prepare(
    'INSERT IGNORE INTO schema_migrations (filename) VALUES (:filename)'
);

$inserted = [];
foreach (ce_pre_baseline_migration_filenames() as $filename) {
    $insert->execute(['filename' => $filename]);
    if ($insert->rowCount() > 0) {
        $inserted[] = $filename;
    }
}

$applied = $pdo->query('SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at ASC')
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];

ce_json_response([
    'ok' => true,
    'inserted' => $inserted,
    'applied' => $applied,
]);
