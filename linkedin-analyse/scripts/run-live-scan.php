#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Live Apify-scan zonder database (lokaal testen / eerste rapport).
 *
 *   php linkedin-analyse/scripts/run-live-scan.php growthelements
 *   php linkedin-analyse/scripts/run-live-scan.php "https://www.linkedin.com/company/growthelements/"
 *   php linkedin-analyse/scripts/run-live-scan.php "https://www.linkedin.com/school/codamcollege/"
 *
 * Leest APIFY_API_TOKEN uit .env.production / .env in de repo-root.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$slugArg = $argv[1] ?? 'growthelements';

require_once dirname(__DIR__) . '/api/apify-client.php';
require_once dirname(__DIR__) . '/api/analyzer.php';
require_once dirname(__DIR__) . '/api/report-renderer.php';

/** @var array<string, string> $LI_CLI_ENV */
$LI_CLI_ENV = [];

if (!function_exists('li_config')) {
    function li_config(string $key, mixed $default = null): mixed
    {
        global $LI_CLI_ENV;
        $map = [
            'report_logo_url' => rtrim($LI_CLI_ENV['LINKEDIN_ANALYSE_BASE_URL'] ?? 'https://linkedin-analyse.igisnext.com', '/') . '/assets/iGiSNext_Blue-main.svg',
            'base_url' => $LI_CLI_ENV['LINKEDIN_ANALYSE_BASE_URL'] ?? 'https://linkedin-analyse.igisnext.com',
            'school_actor_id' => $LI_CLI_ENV['LINKEDIN_ANALYSE_SCHOOL_ACTOR_ID'] ?? '',
        ];

        return $map[$key] ?? $default;
    }
}

if (!function_exists('li_base_url')) {
    function li_base_url(): string
    {
        return (string) li_config('base_url', 'https://linkedin-analyse.igisnext.com');
    }
}

/** @return array<string, string> */
function li_cli_load_env(string $root): array
{
    foreach (["$root/.env.production", "$root/.env"] as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && $v[0] === $v[-1] && $v[0] === '"') {
                $v = substr($v, 1, -1);
            }
            $out[trim($k)] = $v;
        }

        return $out;
    }

    return [];
}

$LI_CLI_ENV = li_cli_load_env($root);
$token = trim((string) ($LI_CLI_ENV['APIFY_API_TOKEN'] ?? ''));
if ($token === '') {
    fwrite(STDERR, "APIFY_API_TOKEN niet gevonden in .env.production / .env\n");
    exit(1);
}

$page = li_linkedin_parse_page_url($slugArg);
if ($page !== null) {
    $slug = $page['slug'];
    $pageType = $page['type'];
} else {
    $slug = ltrim($slugArg, '@/');
    $pageType = 'company';
}

if ($slug === '') {
    fwrite(STDERR, "Geen pagina-slug.\n");
    exit(1);
}

$maxPosts = (int) ($LI_CLI_ENV['LINKEDIN_ANALYSE_MAX_POSTS'] ?? 30);
$timeout = (int) ($LI_CLI_ENV['LINKEDIN_ANALYSE_APIFY_TIMEOUT_SEC'] ?? 120);

echo "Apify scan ({$pageType}): {$slug} (max {$maxPosts} posts, timeout {$timeout}s)...\n";

$apify = li_apify_run_page_scan($token, $pageType, $slug, $maxPosts, $timeout);
if (!$apify['ok']) {
    fwrite(STDERR, 'Scan mislukt: ' . ($apify['message'] ?? 'onbekend') . "\n");
    exit(1);
}

$rawItems = $pageType === 'school'
    ? li_normalize_school_apify_items($apify['items'], $slug)
    : $apify['items'];

$rawCount = count($rawItems);
echo "Ruwe rijen: {$rawCount}\n";
if ($apify['cost_usd'] !== null) {
    echo 'Kosten: $' . number_format((float) $apify['cost_usd'], 4) . "\n";
}

$analysis = li_analyze_posts($rawItems, $slug);
$m = $analysis['metrics'];

echo "Unieke posts: {$m['posts_count']}\n";
echo 'Periode: ' . li_format_period($m['period_start'] ?? null, $m['period_end'] ?? null) . "\n";
echo "Ritme: {$m['posts_per_week']} posts/week\n";
echo 'Stiltes: ' . implode(', ', $m['silences_days'] ?? []) . " dagen\n";
echo "Gem. reacties: {$m['avg_reactions']} (mediaan {$m['median_reactions']})\n";
echo "Zonder comments: {$m['posts_without_comments']} / {$m['posts_count']}\n";
echo "Volgers: " . ($m['followers'] ?? '—') . "\n";

$outDir = dirname(__DIR__) . '/output';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$rawPath = $outDir . '/' . $slug . '-raw.json';
$reportPath = $outDir . '/' . $slug . '-report.html';

file_put_contents($rawPath, json_encode($rawItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$reportInner = li_render_full_report_html($m, li_format_dutch_date(date('Y-m-d')));
file_put_contents($reportPath, $reportInner);

echo "\nOpgeslagen:\n  {$rawPath}\n  {$reportPath}\n";
