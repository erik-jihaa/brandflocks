<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $categoryUi = trim((string) ($_GET['category'] ?? ''));
    if ($categoryUi === '' || strcasecmp($categoryUi, 'all') === 0) {
        require_once __DIR__ . '/source-folders-repository.php';
        ce_json_response([
            'sources' => ce_fetch_all_sources($pdo),
            'folders' => ce_fetch_source_folders($pdo),
        ]);
    }
    $category = ce_ui_category_to_db($categoryUi);
    ce_json_response(['sources' => ce_fetch_sources($pdo, $category)]);
}

if ($method === 'POST') {
    $body = ce_read_json_body();
    $categoryUi = trim((string) ($body['category'] ?? 'URL/RSS'));
    $category = ce_ui_category_to_db($categoryUi);

    try {
        $source = ce_create_source($pdo, $category, $body);
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 503);
    }

    ce_json_response($source, 201);
}

ce_json_error('Method not allowed', 405);
