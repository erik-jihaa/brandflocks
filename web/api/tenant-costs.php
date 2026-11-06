<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/tenant-cost-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
ce_require_auth($pdo);

$month = trim((string) ($_GET['month'] ?? ''));
$clientId = trim((string) ($_GET['client_id'] ?? ''));
$postsPerWeek = isset($_GET['posts_per_week']) ? (int) $_GET['posts_per_week'] : null;
$useProjected = filter_var($_GET['projected'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

$report = ce_tenant_cost_report(
    $pdo,
    $clientId !== '' ? $clientId : null,
    $month !== '' ? $month : (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y-m'),
    $postsPerWeek,
    $useProjected,
);

if (isset($report['error'])) {
    ce_json_error((string) $report['error'], 400);
}

ce_json_response($report);
