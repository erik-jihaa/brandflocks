<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$themeId = (string) ($_GET['theme_id'] ?? '');
if ($themeId === '') {
    ce_json_error('theme_id ontbreekt', 400);
}

$pdo = ce_db();

try {
    ce_json_response(ce_submit_theme($pdo, $themeId));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
