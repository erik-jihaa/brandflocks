<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/event-log-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
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

$migrationsDir = dirname(__DIR__, 2) . '/scripts/migrations';
if (!is_dir($migrationsDir)) {
    $migrationsDir = dirname(__DIR__) . '/migrations';
}

/** @return list<string> */
function ce_migration_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.sql') ?: [];
    $names = array_map('basename', $files);
    sort($names);

    return array_values($names);
}

/** @return list<string> */
function ce_applied_migrations(PDO $pdo): array
{
    $rows = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];

    return array_map('strval', $rows);
}

/** Voer één migratie-statement uit; SELECT-resultsets worden volledig gelezen. */
function ce_migration_execute(PDO $pdo, string $statement): void
{
    if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $statement)) {
        $stmt = $pdo->query($statement);
        if ($stmt !== false) {
            $stmt->fetchAll();
            $stmt->closeCursor();
        }

        return;
    }

    $pdo->exec($statement);
}

/** MySQL DDL is niet transactioneel — splits op en stop bij de eerste fout. @return list<string> */
function ce_split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

    return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
}

$applied = ce_applied_migrations($pdo);
$available = ce_migration_files($migrationsDir);
$pending = array_values(array_diff($available, $applied));

if ($method === 'GET') {
    ce_json_response([
        'ok' => true,
        'environment' => ce_env(),
        'migrationsDir' => $migrationsDir,
        'applied' => $applied,
        'available' => $available,
        'pending' => $pending,
    ]);
}

$body = ce_read_json_body();
$requested = trim((string) ($body['filename'] ?? ''));
$runAll = !empty($body['all']);

if ($requested === '' && !$runAll) {
    ce_json_error('Geef "filename" mee, of "all": true voor alle openstaande migraties.', 400);
}

$queue = $runAll ? $pending : [$requested];

foreach ($queue as $filename) {
    if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $filename)) {
        ce_json_error('Ongeldige bestandsnaam: ' . $filename, 400);
    }
    if (!in_array($filename, $available, true)) {
        ce_json_error('Migratie niet gevonden op de server: ' . $filename, 404);
    }
}

$results = [];
$insert = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename) VALUES (:filename)');

foreach ($queue as $filename) {
    if (in_array($filename, $applied, true)) {
        $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'al toegepast'];
        continue;
    }

    $sql = (string) @file_get_contents($migrationsDir . '/' . $filename);
    if (trim($sql) === '') {
        $results[] = ['filename' => $filename, 'status' => 'skipped', 'reason' => 'leeg bestand'];
        continue;
    }

    $statements = ce_split_sql_statements($sql);
    $executed = 0;
    $error = null;

    foreach ($statements as $statement) {
        try {
            ce_migration_execute($pdo, $statement);
            $executed++;
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            // Idempotent ADD COLUMN: kolom bestaat al (andere migratie of auto-heal).
            if (str_contains($msg, 'Duplicate column name') || str_contains($msg, '1060')) {
                $executed++;
                continue;
            }
            $error = $msg;
            break;
        }
    }

    if ($error !== null) {
        ce_log_event($pdo, 'error', 'schema', 'migration_failed', $error, [
            'filename' => $filename,
            'statementsExecuted' => $executed,
        ]);
        $results[] = [
            'filename' => $filename,
            'status' => 'failed',
            'statementsExecuted' => $executed,
            'error' => $error,
        ];
        break;
    }

    $insert->execute(['filename' => $filename]);
    ce_log_event($pdo, 'info', 'schema', 'migration_applied', null, [
        'filename' => $filename,
        'statements' => $executed,
    ]);
    $results[] = ['filename' => $filename, 'status' => 'applied', 'statements' => $executed];
}

$failed = array_filter($results, static fn (array $r): bool => ($r['status'] ?? '') === 'failed');

ce_json_response([
    'ok' => $failed === [],
    'environment' => ce_env(),
    'results' => $results,
    'applied' => ce_applied_migrations($pdo),
], $failed === [] ? 200 : 500);
