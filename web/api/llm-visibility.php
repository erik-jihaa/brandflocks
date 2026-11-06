<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/llm-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method === 'GET') {
    $kind = ce_llm_question_kind((string) ($_GET['kind'] ?? 'geo'));
    ce_json_response(ce_fetch_llm_visibility_report($pdo, null, $kind));
}

ce_json_error('Method not allowed', 405);
