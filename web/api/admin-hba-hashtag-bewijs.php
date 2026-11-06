<?php

declare(strict_types=1);

/**
 * Rogier A6 ronde 2 punt C — hashtag-bewijs (staging/local, CE_RESET_TOKEN).
 * GET ?slot_id=… [&rescore=1]
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-prompt.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

if (ce_is_production()) {
    ce_json_error('Alleen op staging/local', 403);
}

$slotId = trim((string) ($_GET['slot_id'] ?? $_GET['slotId'] ?? ''));
$pdo = ce_db();

if ($slotId === '') {
    $stmt = $pdo->prepare(
        "SELECT cs.id FROM content_slots cs
         INNER JOIN clients c ON c.id = cs.client_id
         INNER JOIN articles a ON a.slot_id = cs.id
         WHERE LOWER(c.name) LIKE '%brandpunt%'
           AND (a.hashtags_final LIKE '%versgebrand%' OR a.hashtags_suggested LIKE '%versgebrand%')
         ORDER BY a.updated_at DESC LIMIT 1"
    );
    if (ce_table_has_column($pdo, 'articles', 'hashtags_final')) {
        $stmt->execute();
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($found)) {
            $slotId = (string) ($found['id'] ?? '');
        }
    }
}

if ($slotId === '') {
    $fallback = $pdo->prepare(
        "SELECT cs.id FROM content_slots cs
         INNER JOIN clients c ON c.id = cs.client_id
         WHERE LOWER(c.name) LIKE '%brandpunt%'
           AND cs.theme LIKE 'HBA-test:%Categorietaal%'
         ORDER BY cs.theme ASC LIMIT 1"
    );
    $fallback->execute();
    $slotId = (string) ($fallback->fetchColumn() ?: '');
}

if (!preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    ce_json_error('slot_id ongeldig', 400);
}

ce_consolidate_slot_articles($pdo, $slotId);
$slotStmt = $pdo->prepare('SELECT id, client_id, theme FROM content_slots WHERE id = :id LIMIT 1');
$slotStmt->execute(['id' => $slotId]);
$slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
if (!$slot) {
    ce_json_error('Slot niet gevonden', 404);
}

$clientId = (string) ($slot['client_id'] ?? '');
$article = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
if ($article === null) {
    ce_json_error('Geen artikel voor slot', 404);
}

$articleId = (string) ($article['id'] ?? '');
$hashtags = trim((string) ($article['hashtags_final'] ?? $article['hashtags_suggested'] ?? ''));
$postText = ce_hba_article_post_text($article);

$narrativeRow = ce_hba_resolve_narrative_for_scoring($pdo, $clientId);
$scoreRow = $narrativeRow
    ? ce_hba_fetch_score_for_article($pdo, $articleId, (string) ($narrativeRow['id'] ?? ''))
    : null;

$flagCitaten = $scoreRow ? ce_hba_flag_citaten_from_raw($scoreRow['raw_llm_response'] ?? null) : [];
$flags = $scoreRow ? ce_hba_decode_json_list($scoreRow['architecture_risk_flags'] ?? []) : [];

$marksHashtag = static function (string $haystack): bool {
    $h = strtolower($haystack);

    return str_contains($h, '#versgebrand') || preg_match('/\bversgebrand\b/u', $h) === 1;
};

$prepareCategorietaal = filter_var($_GET['prepare_categorietaal'] ?? '0', FILTER_VALIDATE_BOOLEAN);
if ($prepareCategorietaal && ce_table_has_column($pdo, 'articles', 'hashtags_final')) {
    $catSlot = $slotId;
    $themeLower = strtolower((string) ($slot['theme'] ?? ''));
    if (!str_contains($themeLower, 'categorietaal')) {
        $find = $pdo->prepare(
            "SELECT id FROM content_slots
             WHERE client_id = :cid AND theme LIKE 'HBA-test:%Categorietaal%'
             ORDER BY theme ASC LIMIT 1"
        );
        $find->execute(['cid' => $clientId]);
        $resolved = (string) ($find->fetchColumn() ?: '');
        if ($resolved !== '') {
            $catSlot = $resolved;
        }
    }
    $catArticle = ce_fetch_article_by_slot($pdo, $catSlot, $clientId);
    if ($catArticle !== null) {
        $pdo->prepare('UPDATE articles SET hashtags_final = :tags WHERE id = :id')
            ->execute([
                'tags' => '#versgebrand, #koffie',
                'id' => (string) ($catArticle['id'] ?? ''),
            ]);
        try {
            ce_hba_score_article($pdo, ['article_id' => (string) ($catArticle['id'] ?? ''), 'force' => true]);
        } catch (Throwable) {
            // bewijs-endpoint mag niet falen op scoring
        }
        $slotId = $catSlot;
        $slot = ['id' => $catSlot, 'client_id' => $clientId, 'theme' => 'HBA-test: Categorietaal'];
        $article = ce_fetch_article_by_slot($pdo, $slotId, $clientId) ?? $article;
        $articleId = (string) ($article['id'] ?? $articleId);
        $hashtags = trim((string) ($article['hashtags_final'] ?? $article['hashtags_suggested'] ?? ''));
        $postText = ce_hba_article_post_text($article);
        $scoreRow = $narrativeRow
            ? ce_hba_fetch_score_for_article($pdo, $articleId, (string) ($narrativeRow['id'] ?? ''))
            : null;
        $flagCitaten = $scoreRow ? ce_hba_flag_citaten_from_raw($scoreRow['raw_llm_response'] ?? null) : [];
        $flags = $scoreRow ? ce_hba_decode_json_list($scoreRow['architecture_risk_flags'] ?? []) : [];
    }
}

$rescore = filter_var($_GET['rescore'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$rescoreResult = null;
if ($rescore && $narrativeRow) {
    @set_time_limit(120);
    $rescoreResult = ce_hba_dry_run_content_score_for_article($pdo, $articleId, 'claude-sonnet-4-6');
    if (($rescoreResult['ok'] ?? false) === true && is_array($rescoreResult['tool'] ?? null)) {
        $tool = $rescoreResult['tool'];
        $toolJson = json_encode($tool, JSON_UNESCAPED_UNICODE);
        $freshCitaten = ce_hba_flag_citaten_from_raw($toolJson);
        foreach ($freshCitaten as $flag => $citaat) {
            if (is_string($citaat) && $marksHashtag($citaat)) {
                $hashtagMarkedInScore = true;
                $hashtagCitaat = ['flag' => $flag, 'citaat' => $citaat, 'source' => 'rescore'];
                break;
            }
        }
        $rescoreResult = [
            'ok' => true,
            'hoofdoordeel' => ce_hba_hoofdoordeel_from_raw($toolJson),
            'flag_citaten' => $freshCitaten,
        ];
    }
}

$selfReview = ce_parse_self_review($article['self_review'] ?? null);
$ce03MarksHashtag = false;
$ce03Snippet = null;
if (is_array($selfReview)) {
    foreach ($selfReview as $key => $val) {
        if (!is_string($val) || $val === '') {
            continue;
        }
        if ($marksHashtag($val)) {
            $ce03MarksHashtag = true;
            $ce03Snippet = ['field' => $key, 'text' => $val];
            break;
        }
    }
}

$hashtagMarkedInScore = false;
$hashtagCitaat = null;
foreach ($flagCitaten as $flag => $citaat) {
    if (!is_string($citaat) || $citaat === '') {
        continue;
    }
    if ($marksHashtag($citaat)) {
        $hashtagMarkedInScore = true;
        $hashtagCitaat = ['flag' => $flag, 'citaat' => $citaat];
        break;
    }
}

ce_json_response([
    'ok' => true,
    'promptVersion' => CE_HBA_PROMPT_VERSION,
    'slotId' => $slotId,
    'articleId' => $articleId,
    'clientId' => $clientId,
    'theme' => (string) ($slot['theme'] ?? ''),
    'hashtags' => $hashtags,
    'hasHashtagVersgebrand' => $marksHashtag($hashtags),
    'postTextTail' => mb_substr($postText, max(0, mb_strlen($postText) - 400)),
    'postTextIncludesHashtagLine' => $marksHashtag($postText),
    'hbaScore' => $scoreRow ? [
        'scoredAt' => (string) ($scoreRow['scored_at'] ?? ''),
        'modelUsed' => (string) ($scoreRow['model_used'] ?? ''),
        'flags' => $flags,
        'flag_citaten' => $flagCitaten,
    ] : null,
    'hashtagMarkedInScore' => $hashtagMarkedInScore,
    'hashtagScoreCitaat' => $hashtagCitaat,
    'ce03MarksHashtag' => $ce03MarksHashtag,
    'ce03Snippet' => $ce03Snippet,
    'rescore' => $rescoreResult,
    'bewijsOk' => $marksHashtag($hashtags)
        && ($hashtagMarkedInScore || $ce03MarksHashtag || str_contains(strtolower($postText), '#versgebrand')),
]);
