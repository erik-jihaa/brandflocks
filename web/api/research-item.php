<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$itemId = (string) ($_GET['research_id'] ?? '');
if ($itemId === '') {
    ce_json_error('research_id ontbreekt', 400);
}

$pdo = ce_db();

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    $feedback = (string) ($body['feedback'] ?? '');
    if ($feedback === '') {
        ce_json_error('feedback is verplicht', 400);
    }
    try {
        ce_json_response(ce_patch_research_feedback($pdo, $itemId, $feedback));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
