<?php

declare(strict_types=1);

function ce_parse_env_value(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $quote = $raw[0];
    if ($quote === '"' || $quote === "'") {
        $len = strlen($raw);
        $out = '';
        for ($i = 1; $i < $len; $i++) {
            if ($raw[$i] === '\\' && $i + 1 < $len) {
                $out .= $raw[++$i];
                continue;
            }
            if ($raw[$i] === $quote) {
                return $out;
            }
            $out .= $raw[$i];
        }
        return $out;
    }

    // Unquoted: alles na spatie+# is comment (dotenv-conventie)
    if (preg_match('/\s+#/', $raw, $match, PREG_OFFSET_CAPTURE)) {
        $raw = substr($raw, 0, $match[0][1]);
    }

    return trim($raw);
}

function ce_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $candidates = [
        dirname(__DIR__, 2) . '/.env',
        dirname(__DIR__) . '/.env',
    ];

    foreach ($candidates as $path) {
        if (!is_readable($path)) {
            continue;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = ce_parse_env_value($value);
            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        break;
    }

    $loaded = true;
}

/** Waarde uit .env, met ce.local.php als fallback (deploy schrijft die). */
function ce_config_value(string $envKey, string $localKey, string $default = ''): string
{
    ce_load_env();

    $value = trim((string) (getenv($envKey) ?: ''));
    if ($value !== '') {
        return $value;
    }

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $value = trim((string) ($config[$localKey] ?? ''));
        }
    }

    return $value !== '' ? $value : $default;
}

/** local | staging | production — bepaalt guards, logtags en de omgevingsbanner. */
function ce_env(): string
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $value = strtolower(ce_config_value('CE_ENV', 'ce_env', 'production'));

    return $env = in_array($value, ['local', 'staging', 'production'], true) ? $value : 'production';
}

function ce_is_production(): bool
{
    return ce_env() === 'production';
}

/** @return array{host:string,name:string,user:string,pass:string,port:string} */
function ce_db_config(): array
{
    $local = dirname(__DIR__) . '/includes/db.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            return [
                'host' => (string) ($config['host'] ?? ''),
                'name' => (string) ($config['name'] ?? ''),
                'user' => (string) ($config['user'] ?? ''),
                'pass' => (string) ($config['pass'] ?? ''),
                'port' => (string) ($config['port'] ?? '3306'),
            ];
        }
    }

    ce_load_env();

    return [
        'host' => getenv('DB_HOST') ?: '',
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
        'port' => getenv('DB_PORT') ?: '3306',
    ];
}

function ce_allowed_origins(): array
{
    ce_load_env();
    $raw = getenv('CE_ALLOWED_ORIGINS');
    if ($raw === false || trim((string) $raw) === '') {
        $local = dirname(__DIR__) . '/includes/ce.local.php';
        if (is_readable($local)) {
            $config = require $local;
            if (is_array($config) && !empty($config['allowed_origins'])) {
                $raw = (string) $config['allowed_origins'];
            }
        }
    }
    if ($raw === false || trim((string) $raw) === '') {
        $raw = 'https://ce.igisnext.com,http://localhost:5173';
    }

    return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
}

function ce_cors_origin(): ?string
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return null;
    }

    return in_array($origin, ce_allowed_origins(), true) ? $origin : null;
}

function ce_json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    $origin = ce_cors_origin();
    if ($origin !== null) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, PATCH, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CE-Reset-Token');

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        ce_json_error('Failed to encode response', 500);
    }

    require_once __DIR__ . '/session-repository.php';
    ce_close_session_if_active();

    echo $json;
    exit;
}

function ce_json_error(string $message, int $status = 400): never
{
    ce_json_response(['error' => $message], $status);
}

function ce_handle_options(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        ce_json_response(['ok' => true]);
    }
}

function ce_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    ce_load_env();

    $config = ce_db_config();
    $host = $config['host'];
    $name = $config['name'];
    $user = $config['user'];
    $pass = $config['pass'];
    $port = $config['port'];

    if ($host === '' || $name === '' || $user === '') {
        ce_json_error('Database not configured. Set DB_HOST, DB_NAME, DB_USER in .env', 503);
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

/** Ruwe request-body, één keer gelezen en daarna hergebruikt. */
function ce_raw_request_body(): string
{
    static $raw = null;
    if ($raw === null) {
        $read = file_get_contents('php://input');
        $raw = $read === false ? '' : $read;
    }

    return $raw;
}

function ce_read_json_body(): array
{
    $raw = ce_raw_request_body();
    if (trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        ce_json_error('Invalid JSON body', 400);
    }

    return $data;
}
