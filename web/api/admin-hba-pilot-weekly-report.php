<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/hba-pilot-weekly-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (ce_is_production()) {
    ce_json_error('Alleen op staging/local', 403);
}

$week = isset($_GET['week']) ? trim((string) $_GET['week']) : null;
$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'json';

$payload = ce_hba_pilot_weekly_report_payload(ce_db(), $week);

if ($format === 'md' || $format === 'markdown') {
    header('Content-Type: text/markdown; charset=utf-8');
    echo ce_hba_pilot_weekly_report_markdown($payload);
    exit;
}

ce_json_response($payload);
