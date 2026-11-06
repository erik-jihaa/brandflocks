<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/report-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$reportId = (string) ($_GET['report_id'] ?? '');
if ($reportId === '') {
    ce_json_error('report_id ontbreekt', 400);
}

$pdo = ce_db();

try {
    ce_json_response(ce_fetch_report($pdo, $reportId));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
