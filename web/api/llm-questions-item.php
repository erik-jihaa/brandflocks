<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/llm-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$questionId = trim((string) ($_GET['question_id'] ?? ''));
if ($questionId === '') {
    ce_json_error('question_id ontbreekt', 400);
}

$pdo = ce_db();

if ($method === 'PATCH') {
    $body = ce_read_json_body();
    try {
        ce_json_response(ce_patch_llm_question($pdo, $questionId, $body));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

if ($method === 'DELETE') {
    try {
        ce_json_response(ce_delete_llm_question($pdo, $questionId));
    } catch (InvalidArgumentException $e) {
        ce_json_error($e->getMessage(), 400);
    } catch (RuntimeException $e) {
        ce_json_error($e->getMessage(), 404);
    }
}

ce_json_error('Method not allowed', 405);
