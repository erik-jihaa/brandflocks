<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$body = ce_read_json_body();
$week = trim((string) ($body['week'] ?? $body['weekKey'] ?? $_GET['week'] ?? ''));
$forceThin = !empty($body['forceThin']) || !empty($body['force_thin']);

try {
    ce_json_response(ce_generate_themes_for_week($pdo, $week !== '' ? $week : null, $forceThin));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('themes-generate.php: ' . $e->getMessage());
    ce_json_error('Theme-voorstellen genereren mislukt', 500);
}
