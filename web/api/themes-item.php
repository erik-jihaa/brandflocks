<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$themeId = (string) ($_GET['theme_id'] ?? '');
if ($themeId === '') {
    ce_json_error('theme_id ontbreekt', 400);
}

$pdo = ce_db();

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_patch_theme($pdo, $themeId, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
