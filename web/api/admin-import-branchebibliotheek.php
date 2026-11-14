<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/branche-bibliotheek-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$body = ce_read_json_body();
$dryRun = (bool) ($body['dryRun'] ?? $body['dry_run'] ?? false);
$dirOverride = trim((string) ($body['dir'] ?? ''));

try {
    $dir = ce_branche_bibliotheek_resolve_dir($dirOverride !== '' ? $dirOverride : null);
} catch (Throwable $e) {
    ce_json_error($e->getMessage(), 400);
}

$pdo = ce_db();
$stats = ce_branche_bibliotheek_import($dryRun ? null : $pdo, $dir, $dryRun);

if (($stats['errors'] ?? []) !== []) {
    ce_json_response(['ok' => false, 'stats' => $stats], 422);
}

ce_json_response(['ok' => true, 'stats' => $stats, 'dir' => $dir]);