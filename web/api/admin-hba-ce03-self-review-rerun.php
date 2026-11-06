<?php

declare(strict_types=1);

/**
 * Hermet stap D (self_review) op bestaand artikel — geen CE-03 rewrite.
 * POST { "article_id": "...", "persist": false, "apply_stap3a_text_fix": true }
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ce03-self-review-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$body = ce_read_json_body();
$articleId = trim((string) ($body['article_id'] ?? $body['articleId'] ?? ''));
if ($articleId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $articleId)) {
    ce_json_error('article_id ontbreekt of is ongeldig', 400);
}

$persist = filter_var($body['persist'] ?? false, FILTER_VALIDATE_BOOLEAN);
$applyFix = filter_var($body['apply_stap3a_text_fix'] ?? $body['applyStap3aTextFix'] ?? true, FILTER_VALIDATE_BOOLEAN);

$pdo = ce_db();
$result = ce_ce03_rerun_step_d_for_article($pdo, $articleId, $applyFix);
if (!$result['ok']) {
    ce_json_error((string) ($result['error'] ?? 'Stap D hermeting mislukt'), 502);
}

if ($persist) {
    $raw = is_array($result['selfReviewRaw'] ?? null) ? $result['selfReviewRaw'] : [];
    $content = (string) ($result['contentAi'] ?? '');
    ce_ce03_persist_self_review_and_content($pdo, $articleId, $content, $raw);
}

ce_json_response([
    'ok' => true,
    'articleId' => $articleId,
    'persisted' => $persist,
    'contentAi' => $result['contentAi'] ?? null,
    'selfReview' => $result['selfReviewParsed'] ?? null,
    'selfReviewRaw' => $result['selfReviewRaw'] ?? null,
]);
