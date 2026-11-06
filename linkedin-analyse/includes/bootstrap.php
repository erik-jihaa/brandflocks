<?php

declare(strict_types=1);

// Tweede laag: geen directe web-aanroep van includes (naast .htaccess).
if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

session_set_cookie_params([
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

/** @var array<string, mixed> $LI_CONFIG */
$LI_CONFIG = [];

$configPath = __DIR__ . '/config.local.php';
if (is_readable($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) {
        $LI_CONFIG = $loaded;
    }
}

function li_config(string $key, mixed $default = null): mixed
{
    global $LI_CONFIG;

    return $LI_CONFIG[$key] ?? $default;
}

function li_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) li_config('db_host', '127.0.0.1');
    $name = (string) li_config('db_name', 'linkedin_analyse');
    $user = (string) li_config('db_user', '');
    $pass = (string) li_config('db_pass', '');

    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function li_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function li_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function li_csrf_token(): string
{
    $existing = $_SESSION['li_csrf'] ?? null;
    if (is_string($existing) && $existing !== '') {
        return $existing;
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['li_csrf'] = $token;

    return $token;
}

function li_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . li_h(li_csrf_token()) . '">';
}

function li_csrf_verify(): bool
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    $expected = (string) ($_SESSION['li_csrf'] ?? '');

    return $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

/** Browser-tab favicon — iGiS rood (#C17156), zelfde cirkel als CE favicons. */
function li_head_favicon(): void
{
    echo '  <link rel="icon" type="image/svg+xml" href="assets/favicon-red.svg">' . "\n";
}

/** Platform-logo op beige — canonical: SVG/iGiSNext_Blue-main.svg (zelfde als ce.igisnext.com). */
function li_platform_logo_path(): string
{
    return 'assets/iGiSNext_Blue-main.svg';
}

function li_brand_logo(string $extraClass = ''): void
{
    $class = trim('platform-logo ' . $extraClass);
    echo '      <img src="' . li_h(li_platform_logo_path()) . '" alt="iGiSNext" class="' . li_h($class) . '">' . "\n";
}

function li_is_logged_in(): bool
{
    $users = li_app_users();
    if ($users === []) {
        return false;
    }

    return !empty($_SESSION['li_auth_email']);
}

function li_require_auth(): void
{
    if (li_is_logged_in()) {
        return;
    }

    header('Location: index.php?login=1');
    exit;
}

function li_attempt_login(string $email, string $password): bool
{
    $users = li_app_users();
    if ($users === []) {
        return false;
    }

    $email = strtolower(trim($email));
    $hash = $users[$email] ?? null;
    if (!is_string($hash) || $hash === '') {
        return false;
    }

    // Wachtwoord niet trimmen — wat de gebruiker typt, is het wachtwoord (briefing B4).
    if ($password !== '' && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['li_auth_email'] = $email;

        return true;
    }

    return false;
}

function li_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly'],
        );
    }
    session_destroy();
}

function li_handle_logout_request(): void
{
    if (!isset($_GET['logout']) || (string) $_GET['logout'] !== '1') {
        return;
    }

    li_logout();
    header('Location: index.php?login=1');
    exit;
}

function li_base_url(): string
{
    $configured = rtrim((string) li_config('base_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host;
}

require_once __DIR__ . '/branche-library.php';
require_once __DIR__ . '/context-profiles.php';
require_once __DIR__ . '/pulldown.php';
require_once __DIR__ . '/../api/apify-client.php';
require_once __DIR__ . '/../api/auth-repository.php';
require_once __DIR__ . '/../api/analyzer.php';
require_once __DIR__ . '/../api/narrative-analyzer.php';
require_once __DIR__ . '/../api/report-renderer.php';
require_once __DIR__ . '/../api/scan-repository.php';

li_handle_logout_request();
