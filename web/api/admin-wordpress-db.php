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

/** @return list<string> */
function ce_wpce_table_names(PDO $pdo): array
{
    $all = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $names = array_values(array_filter(array_map('strval', $all), static fn (string $t): bool => str_starts_with($t, 'wpce_')));

    sort($names);

    return $names;
}

function ce_sql_ident(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

function ce_database_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
    $stmt->execute([$name]);

    return (bool) $stmt->fetchColumn();
}

/** @return list<string> */
function ce_tables_in_database(PDO $pdo, string $database): array
{
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME'
    );
    $stmt->execute([$database]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function ce_wp_db_target_from_env(): ?string
{
    $target = trim(ce_config_value('WP_DB_NAME', 'wp_db_name'));

    return $target !== '' ? $target : null;
}

/** @return array{host:string,name:string,user:string,pass:string,port:string}|null */
function ce_wp_db_config(): ?array
{
    $name = trim(ce_config_value('WP_DB_NAME', 'wp_db_name'));
    $user = trim(ce_config_value('WP_DB_USER', 'wp_db_user'));
    $pass = ce_config_value('WP_DB_PASS', 'wp_db_pass');
    if ($name === '' || $user === '') {
        return null;
    }

    $ceDb = ce_db_config();

    return [
        'host' => (string) ($ceDb['host'] ?: 'localhost'),
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'port' => (string) ($ceDb['port'] ?: '3306'),
    ];
}

function ce_wp_db_pdo(): ?PDO
{
    $config = ce_wp_db_config();
    if ($config === null) {
        return null;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['name']
    );

    try {
        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable) {
        return null;
    }
}

/** @return array{connectOk: bool, tableCount: int, error: ?string} */
function ce_wp_target_database_state(): array
{
    $wpPdo = ce_wp_db_pdo();
    if ($wpPdo === null) {
        return ['connectOk' => false, 'tableCount' => 0, 'error' => 'WP_DB_NAME/WP_DB_USER niet geconfigureerd'];
    }

    try {
        $tables = $wpPdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return ['connectOk' => true, 'tableCount' => count($tables), 'error' => null];
    } catch (Throwable $e) {
        return ['connectOk' => false, 'tableCount' => 0, 'error' => $e->getMessage()];
    }
}

function ce_ce_user_can_access_database(PDO $pdo, string $name): bool
{
    return ce_database_exists($pdo, $name);
}

function ce_wp_db_status(PDO $pdo): array
{
    $sourceDb = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
    $wpceTables = ce_wpce_table_names($pdo);
    $targetDb = ce_wp_db_target_from_env();
    $wpConfig = ce_wp_db_config();
    $targetState = ce_wp_target_database_state();
    $targetExists = $targetState['connectOk'];
    $targetTables = [];
    if ($targetExists) {
        $wpPdo = ce_wp_db_pdo();
        if ($wpPdo !== null) {
            $targetTables = ce_tables_in_database($wpPdo, (string) $targetDb);
        }
    }

    $tableInfo = [];
    foreach ($wpceTables as $table) {
        $count = (int) ($pdo->query('SELECT COUNT(*) FROM ' . ce_sql_ident($table))->fetchColumn() ?: 0);
        $tableInfo[] = ['name' => $table, 'rows' => $count];
    }

    $ceDb = ce_db_config();

    return [
        'sourceDatabase' => $sourceDb,
        'ceDbUser' => (string) ($ceDb['user'] ?? ''),
        'wpceTableCount' => count($wpceTables),
        'wpceTables' => $tableInfo,
        'separated' => count($wpceTables) === 0,
        'targetDatabase' => $targetDb,
        'targetDatabaseExists' => $targetExists,
        'targetDatabaseConnectError' => $targetState['error'],
        'targetTableCount' => count($targetTables),
        'targetHasWpce' => count(array_filter($targetTables, static fn (string $t): bool => str_starts_with($t, 'wpce_'))) > 0,
        'wpDbUserConfigured' => trim((string) ($wpConfig['user'] ?? '')) !== '',
        'wpDbUser' => (string) ($wpConfig['user'] ?? ''),
        'ceUserCanAccessTarget' => $targetDb !== null && ce_ce_user_can_access_database($pdo, $targetDb),
    ];
}

if ($method === 'GET') {
    ce_json_response([
        'ok' => true,
        'environment' => ce_env(),
        'status' => ce_wp_db_status($pdo),
        'nextStep' => count(ce_wpce_table_names($pdo)) === 0
            ? 'already_separated'
            : (ce_wp_db_target_from_env() === null
                ? 'set_WP_DB_NAME_in_env_and_create_database_in_cpanel'
                : 'POST dryRun then migrate'),
    ]);
}

$body = ce_read_json_body();
$dryRun = array_key_exists('dryRun', $body) ? (bool) $body['dryRun'] : true;
$targetDb = trim((string) ($body['targetDatabase'] ?? ce_wp_db_target_from_env() ?? ''));

if ($targetDb === '') {
    ce_json_error('Geef targetDatabase mee of zet WP_DB_NAME in ce.local.php / .env.', 400);
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $targetDb)) {
    ce_json_error('Ongeldige targetDatabase.', 400);
}

$sourceDb = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
if ($sourceDb === '') {
    ce_json_error('Kon brondatabase niet bepalen.', 500);
}

if ($sourceDb === $targetDb) {
    ce_json_error('Doeldatabase mag niet gelijk zijn aan de CE-database.', 400);
}

$wpceTables = ce_wpce_table_names($pdo);
if ($wpceTables === []) {
    ce_json_response([
        'ok' => true,
        'message' => 'Geen wpce_*-tabellen meer in de CE-database — scheiding lijkt al gedaan.',
        'status' => ce_wp_db_status($pdo),
    ]);
}

if (!ce_wp_target_database_state()['connectOk']) {
    $wpConfig = ce_wp_db_config();
    $ceDbUser = (string) (ce_db_config()['user'] ?? '');
    $hint = 'Controleer WP_DB_NAME, WP_DB_USER en WP_DB_PASS (WP-user, niet CE-app-user '
        . $ceDbUser . ').';
    if ($wpConfig !== null) {
        $hint = 'Kon niet verbinden met ' . $wpConfig['name'] . ' als ' . $wpConfig['user']
            . '. Controleer WP_DB_USER/WP_DB_PASS en ALL PRIVILEGES op die database.';
    }
    ce_json_error(
        'Doeldatabase niet bereikbaar: ' . $targetDb . '. ' . $hint,
        400
    );
}

$existingTarget = [];
$wpPdo = ce_wp_db_pdo();
if ($wpPdo !== null) {
    $existingTarget = ce_tables_in_database($wpPdo, $targetDb);
}
if ($existingTarget !== []) {
    ce_json_error(
        'Doeldatabase is niet leeg (' . count($existingTarget) . ' tabellen). Gebruik een lege database.',
        400
    );
}

if (!$dryRun) {
    ce_require_destructive_confirmation('wordpress-db-migrate');

    if (!ce_ce_user_can_access_database($pdo, $targetDb)) {
        $ceDbUser = (string) (ce_db_config()['user'] ?? '');
        ce_json_error(
            'CE-app-user ' . $ceDbUser . ' heeft geen toegang tot ' . $targetDb
            . '. Voeg die user in cPanel toe aan de WP-database (ALL PRIVILEGES) voor RENAME TABLE.',
            400
        );
    }
}

$plan = [];
foreach ($wpceTables as $table) {
    $plan[] = sprintf(
        'RENAME TABLE %s.%s TO %s.%s',
        ce_sql_ident($sourceDb),
        ce_sql_ident($table),
        ce_sql_ident($targetDb),
        ce_sql_ident($table)
    );
}

if ($dryRun) {
    ce_json_response([
        'ok' => true,
        'dryRun' => true,
        'sourceDatabase' => $sourceDb,
        'targetDatabase' => $targetDb,
        'tables' => $wpceTables,
        'statements' => $plan,
        'ceUserCanAccessTarget' => ce_ce_user_can_access_database($pdo, $targetDb),
        'hint' => 'Daarna blog/wp-config.php aanpassen (WP_DB_*). CE-code blijft ongewijzigd.',
    ]);
}

$results = [];
try {
    foreach ($wpceTables as $table) {
        $sql = sprintf(
            'RENAME TABLE %s.%s TO %s.%s',
            ce_sql_ident($sourceDb),
            ce_sql_ident($table),
            ce_sql_ident($targetDb),
            ce_sql_ident($table)
        );
        $pdo->exec($sql);
        $results[] = ['table' => $table, 'status' => 'moved'];
    }
} catch (Throwable $e) {
    ce_log_event($pdo, 'error', 'schema', 'wordpress_db_migrate_failed', $e->getMessage(), [
        'sourceDatabase' => $sourceDb,
        'targetDatabase' => $targetDb,
        'moved' => count($results),
    ]);
    ce_json_error('Migratie gestopt: ' . $e->getMessage(), 500);
}

ce_log_event($pdo, 'info', 'schema', 'wordpress_db_migrated', null, [
    'sourceDatabase' => $sourceDb,
    'targetDatabase' => $targetDb,
    'tables' => count($results),
]);

ce_json_response([
    'ok' => true,
    'dryRun' => false,
    'sourceDatabase' => $sourceDb,
    'targetDatabase' => $targetDb,
    'results' => $results,
    'status' => ce_wp_db_status($pdo),
    'nextStep' => 'update blog/wp-config.php met WP_DB_NAME/USER/PASS en test https://ce.igisnext.com/blog/',
]);
