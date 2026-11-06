<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/hba-repository.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$articleId = trim((string) ($body['article_id'] ?? $body['articleId'] ?? ''));

$systemCall = ce_reset_token_valid();
if ($articleId === '' && $systemCall) {
    $slotId = trim((string) ($body['slot_id'] ?? $body['slotId'] ?? ''));
    if ($slotId !== '') {
        require_once __DIR__ . '/article-repository.php';
        $row = ce_fetch_primary_article_row_for_slot($pdo, $slotId);
        if ($row) {
            $articleId = (string) ($row['id'] ?? '');
        }
    }
}

if (!$systemCall) {
    ce_require_auth($pdo);
    if ($articleId === '') {
        ce_json_error('article_id is verplicht', 400);
    }
    $article = ce_fetch_article_by_id($pdo, $articleId);
    if (!$article) {
        ce_json_error('Artikel niet gevonden', 404);
    }
    $slotId = (string) ($article['slot_id'] ?? '');
    if (!ce_slot_belongs_to_client($pdo, $slotId)) {
        ce_json_error('Forbidden', 403);
    }
}

if ($articleId !== '') {
    $body['article_id'] = $articleId;
}

try {
    ce_json_response(ce_hba_score_article($pdo, $body));
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
