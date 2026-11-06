<?php

declare(strict_types=1);

/** @var array<string, array<string, true>> */
$GLOBALS['ce_table_columns'] = [];

/** @return array<string, true> */
function ce_table_columns(PDO $pdo, string $table): array
{
    if (isset($GLOBALS['ce_table_columns'][$table])) {
        return $GLOBALS['ce_table_columns'][$table];
    }

    $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($table));
    if ($safe === '') {
        return [];
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM `$safe`");
    $columns = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = (string) ($row['Field'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }

    $GLOBALS['ce_table_columns'][$table] = $columns;

    return $columns;
}

function ce_table_has_column(PDO $pdo, string $table, string $column): bool
{
    if (!ce_table_exists($pdo, $table)) {
        return false;
    }

    $columns = ce_table_columns($pdo, $table);

    return isset($columns[$column]);
}

function ce_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($table));
    if ($safe === '') {
        return false;
    }
    if (isset($cache[$safe])) {
        return $cache[$safe];
    }

    $stmt = $pdo->query("SHOW TABLES LIKE '$safe'");
    $cache[$safe] = (bool) $stmt->fetch();

    return $cache[$safe];
}

/** @return list<string> */
function ce_column_enum_values(PDO $pdo, string $table, string $column): array
{
    static $cache = [];
    $key = "$table.$column";
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if (!ce_table_has_column($pdo, $table, $column)) {
        return $cache[$key] = [];
    }

    $safeTable = preg_replace('/[^a-z0-9_]/', '', strtolower($table));
    $safeColumn = preg_replace('/[^a-z0-9_]/', '', strtolower($column));
    $stmt = $pdo->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    $row = $stmt->fetch();
    $type = (string) ($row['Type'] ?? '');

    if (!preg_match("/^enum\\((.+)\\)$/i", $type, $matches)) {
        return $cache[$key] = [];
    }

    $values = str_getcsv($matches[1], ',', "'");
    $cache[$key] = array_values(array_filter(array_map('strval', $values)));

    return $cache[$key];
}

function ce_pick_status_value(PDO $pdo, string $table, array $preferred): string
{
    $allowed = ce_column_enum_values($pdo, $table, 'status');
    if ($allowed === []) {
        return $preferred[0];
    }

    foreach ($preferred as $value) {
        if (in_array($value, $allowed, true)) {
            return $value;
        }
    }

    return $allowed[0];
}

function ce_ui_status_candidates(string $uiStatus): array
{
    return match ($uiStatus) {
        'pending' => ['pending', 'pending_review', 'submitted', 'review', 'proposed'],
        'feedback' => ['feedback_received', 'feedback', 'needs_feedback', 'revision'],
        'approved' => ['approved'],
        'published' => ['published'],
        'draft' => ['draft', 'written'],
        default => [$uiStatus],
    };
}

function ce_db_status_for_ui(PDO $pdo, string $table, string $uiStatus): string
{
    return ce_pick_status_value($pdo, $table, ce_ui_status_candidates($uiStatus));
}

function ce_submit_status(PDO $pdo, string $table): string
{
    return ce_db_status_for_ui($pdo, $table, 'pending');
}

function ce_feedback_status(PDO $pdo, string $table): string
{
    return ce_db_status_for_ui($pdo, $table, 'feedback');
}

/** Voeg pipeline-statussen toe aan content_slots.status ENUM indien nodig. */
function ce_ensure_slot_pipeline_statuses(PDO $pdo): void
{
    static $checked = false;
    if ($checked || !ce_table_exists($pdo, 'content_slots')) {
        return;
    }
    $checked = true;

    ce_ensure_status_enum_values($pdo, 'content_slots', [
        'themed',
        'research_done',
        'written',
        'draft',
        'pending',
        'pending_review',
        'proposed',
        'submitted',
        'review',
        'feedback',
        'feedback_received',
        'approved',
        'published',
        'archived',
    ]);
}

function ce_alter_status_enum(PDO $pdo, string $table, array $needed): void
{
    ce_alter_column_enum($pdo, $table, 'status', $needed);
}

/** Breid een ENUM-kolom uit met ontbrekende waarden (no-op als kolom geen ENUM is). */
function ce_alter_column_enum(PDO $pdo, string $table, string $column, array $needed): void
{
    if (!ce_table_exists($pdo, $table)) {
        return;
    }

    $allowed = ce_column_enum_values($pdo, $table, $column);
    if ($allowed === []) {
        return;
    }

    $missing = array_values(array_filter($needed, static fn (string $value): bool => !in_array($value, $allowed, true)));
    if ($missing === []) {
        return;
    }

    $merged = array_values(array_unique(array_merge($allowed, $missing)));
    $enumSql = implode("','", array_map(static fn (string $v): string => str_replace("'", "\\'", $v), $merged));

    $safeTable = preg_replace('/[^a-z0-9_]/', '', strtolower($table));
    $safeColumn = preg_replace('/[^a-z0-9_]/', '', strtolower($column));
    $stmt = $pdo->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $null = stripos((string) ($row['Null'] ?? ''), 'YES') !== false ? 'NULL' : 'NOT NULL';
    $default = $row['Default'] ?? null;
    $defaultSql = $default === null ? '' : " DEFAULT '" . str_replace("'", "\\'", (string) $default) . "'";

    $pdo->exec(
        "ALTER TABLE `$safeTable` MODIFY COLUMN `$safeColumn` ENUM('$enumSql') $null$defaultSql"
    );

    unset($GLOBALS['ce_table_columns'][$table]);
}

function ce_ensure_status_enum_values(PDO $pdo, string $table, array $needed): void
{
    static $done = [];
    $cacheKey = $table . ':' . implode(',', $needed);
    if (isset($done[$cacheKey])) {
        return;
    }
    $done[$cacheKey] = true;

    ce_alter_status_enum($pdo, $table, $needed);
}

function ce_ensure_article_statuses(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    ce_ensure_status_enum_values($pdo, 'articles', [
        'draft',
        'written',
        'pending',
        'pending_review',
        'proposed',
        'submitted',
        'review',
        'feedback',
        'feedback_received',
        'approved',
        'published',
        'archived',
    ]);
}

/** Correlated subquery: nieuwste artikel per slot (voorkomt dubbele agenda-rijen). */
function ce_sql_latest_article_join(string $slotAlias = 's'): string
{
    $slotAlias = preg_replace('/[^a-z_]/', '', strtolower($slotAlias)) ?: 's';

    return <<<SQL
LEFT JOIN articles a ON a.id = (
    SELECT a2.id
    FROM articles a2
    WHERE a2.slot_id = {$slotAlias}.id
    ORDER BY a2.id DESC
    LIMIT 1
)
SQL;
}
