<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/llm-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    ce_json_response(ce_fetch_llm_questions_payload($pdo));
}

if ($method === 'POST') {
    $body = ce_read_json_body();
    try {
        ce_json_response(array_merge(ce_fetch_llm_questions_payload($pdo), ce_create_llm_question($pdo, $body)));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
