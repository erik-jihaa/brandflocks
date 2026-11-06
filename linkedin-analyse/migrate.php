<?php

declare(strict_types=1);

/**
 * Eenmalige/idempotente DB-migraties voor productie (curl na deploy).
 * Vereist ?key=… waar key = sha256(LINKEDIN_ANALYSE_APP_PASSWORD) uit deploy-config.
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$key = (string) ($_GET['key'] ?? '');
$expected = (string) li_config('migration_key', '');
if ($expected === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$columnMigrations = [
    'branche_code' => 'ALTER TABLE scans ADD COLUMN branche_code VARCHAR(64) NULL AFTER company_slug',
    'company_description' => 'ALTER TABLE scans ADD COLUMN company_description TEXT NULL AFTER branche_code',
    'contextprofiel' => 'ALTER TABLE scans ADD COLUMN contextprofiel VARCHAR(32) NULL AFTER company_description',
    'report_token' => 'ALTER TABLE scans ADD COLUMN report_token CHAR(32) NULL AFTER error_message',
    'report_opened_at' => 'ALTER TABLE scans ADD COLUMN report_opened_at DATETIME NULL AFTER report_token',
    'metrics_redacted_at' => 'ALTER TABLE scans ADD COLUMN metrics_redacted_at DATETIME NULL AFTER report_opened_at',
];

$tableMigrations = [
    'login_attempts' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS login_attempts (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          ip VARCHAR(45) NOT NULL,
          email VARCHAR(255) NOT NULL,
          attempted_at DATETIME NOT NULL,
          KEY idx_login_attempts_email_time (email, attempted_at),
          KEY idx_login_attempts_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
    'cost_log' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS cost_log (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          scan_id CHAR(36) NULL,
          kind VARCHAR(16) NOT NULL,
          usd DECIMAL(12, 6) NOT NULL,
          created_at DATETIME NOT NULL,
          KEY idx_cost_log_created (created_at),
          KEY idx_cost_log_kind_created (kind, created_at),
          KEY idx_cost_log_scan (scan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL,
];

try {
    $pdo = li_pdo();
    $exitCode = 0;

    foreach ($columnMigrations as $column => $sql) {
        $exists = $pdo->query("SHOW COLUMNS FROM scans LIKE " . $pdo->quote($column))->fetch();
        if ($exists) {
            echo $column . ": already exists\n";
            continue;
        }

        $pdo->exec($sql);
        echo $column . ": added\n";
    }

    foreach ($tableMigrations as $table => $sql) {
        $exists = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetch();
        if ($exists) {
            echo $table . ": already exists\n";
            continue;
        }

        $pdo->exec($sql);
        echo $table . ": created\n";
    }

    exit($exitCode);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
