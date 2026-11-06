<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

/**
 * Volledige SQL-dump (schema + data) via PDO — mysqldump is niet beschikbaar en
 * DB_HOST=localhost betekent dat de database niet van buitenaf bereikbaar is.
 * Streamt regel voor regel, zodat de dump nooit volledig in geheugen staat.
 */

@set_time_limit(600);
@ini_set('memory_limit', '256M');

$pdo = ce_db();

$includeWordPress = filter_var($_GET['include_wp'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$schemaOnly = filter_var($_GET['schema_only'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$chunkSize = 500;

/**
 * Alleen het schema, niet de rijen. Sessies zijn vluchtig en `ce_sessions.data` is een
 * blob met mogelijk ongeldige UTF-8 — die hoort niet in een tekstuele dump.
 */
$skipData = ['ce_sessions', 'password_reset_tokens'];

$allTables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: []);
$tables = array_values(array_filter($allTables, static function (string $table) use ($includeWordPress): bool {
    if ($table === '') {
        return false;
    }

    return $includeWordPress || !str_starts_with($table, 'wpce_');
}));

/** Kolom voor stabiele paginering: primaire sleutel, anders geen ORDER BY. */
function ce_dump_order_column(PDO $pdo, string $table): ?string
{
    $rows = $pdo->query('SHOW KEYS FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = \'PRIMARY\'')
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (count($rows) !== 1) {
        return null;
    }

    $column = (string) ($rows[0]['Column_name'] ?? '');

    return $column !== '' ? $column : null;
}

function ce_dump_quote(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return $pdo->quote((string) $value);
}

http_response_code(200);
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="ce-' . ce_env() . '-' . gmdate('Ymd-His') . '.sql"');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/session-repository.php';
ce_close_session_if_active();

while (ob_get_level() > 0) {
    ob_end_flush();
}

$emit = static function (string $line): void {
    echo $line . "\n";
    flush();
};

$emit('-- Content Engine — volledige dump (schema + data)');
$emit('-- Omgeving:  ' . ce_env());
$emit('-- Gegenereerd: ' . gmdate('Y-m-d H:i:s') . ' UTC');
$emit('-- Bron: GET /api/admin/db-dump — herstel: ./scripts/restore-db.sh <bestand>');
$emit('-- WordPress-tabellen: ' . ($includeWordPress ? 'inbegrepen' : 'overgeslagen (wpce_*)'));
$emit('');
$emit('SET NAMES utf8mb4;');
$emit('SET FOREIGN_KEY_CHECKS=0;');
$emit('SET SQL_MODE=\'NO_AUTO_VALUE_ON_ZERO\';');
$emit('');

$rowTotals = [];

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';

    $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_ASSOC) ?: [];
    $createSql = $create['Create Table'] ?? $create['Create View'] ?? null;
    if (!is_string($createSql) || $createSql === '') {
        continue;
    }

    $emit('-- ----------------------------------------------------------');
    $emit('-- Tabel: ' . $table);
    $emit('-- ----------------------------------------------------------');
    $emit('DROP TABLE IF EXISTS ' . $quoted . ';');
    $emit($createSql . ';');
    $emit('');

    if ($schemaOnly || isset($create['Create View'])) {
        continue;
    }

    if (in_array($table, $skipData, true)) {
        $emit('-- Rijen overgeslagen (vluchtige data)');
        $emit('');
        continue;
    }

    $orderColumn = ce_dump_order_column($pdo, $table);
    $offset = 0;
    $written = 0;

    while (true) {
        $sql = 'SELECT * FROM ' . $quoted;
        if ($orderColumn !== null) {
            $sql .= ' ORDER BY `' . str_replace('`', '``', $orderColumn) . '`';
        }
        $sql .= ' LIMIT ' . $chunkSize . ' OFFSET ' . $offset;

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            break;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(
            static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`',
            $columns,
        ));

        foreach ($rows as $row) {
            $values = array_map(static fn (string $c): string => ce_dump_quote($pdo, $row[$c] ?? null), $columns);
            $emit('INSERT INTO ' . $quoted . ' (' . $columnList . ') VALUES (' . implode(', ', $values) . ');');
            $written++;
        }

        if (count($rows) < $chunkSize) {
            break;
        }

        // Zonder primaire sleutel is pagineren niet betrouwbaar — dan één ronde en stoppen.
        if ($orderColumn === null) {
            break;
        }

        $offset += $chunkSize;
    }

    $rowTotals[$table] = $written;
    $emit('');
}

$emit('SET FOREIGN_KEY_CHECKS=1;');
$emit('');
$emit('-- Samenvatting: ' . count($tables) . ' tabellen, ' . array_sum($rowTotals) . ' rijen');
foreach ($rowTotals as $table => $count) {
    $emit('--   ' . $table . ': ' . $count);
}
exit;
