<?php

declare(strict_types=1);

/**
 * §6 acceptatie — vergelijk distinctiviteit met/zonder hygiënefrase in de post.
 * GET ?article_id=...&phrase=...&client_id=...
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hygiene-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();
$articleId = trim((string) ($_GET['article_id'] ?? $_GET['articleId'] ?? ''));
$phrase = trim((string) ($_GET['phrase'] ?? ''));
$clientId = trim((string) ($_GET['client_id'] ?? $_GET['clientId'] ?? CE_HBA_PILOT_CLIENT_ID));

if ($articleId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $articleId)) {
    ce_json_error('article_id ontbreekt of is ongeldig', 400);
}
if ($phrase === '') {
    ce_json_error('phrase is verplicht', 400);
}

$article = ce_fetch_article_by_id($pdo, $articleId);
if (!$article) {
    ce_json_error('Artikel niet gevonden', 404);
}

require_once __DIR__ . '/client-context.php';
$slotId = (string) ($article['slot_id'] ?? '');
ce_set_active_client_id(ce_client_id_for_slot($pdo, $slotId));

$formatted = ce_format_article_row($article);
$headline = ce_article_headline($article);
$bodyText = trim((string) ($formatted['body'] ?? ''));
$basePost = trim($headline . "\n\n" . $bodyText);
$withPhrase = trim($basePost . "\n\n" . $phrase);

$narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId);
if (!$narrativeRow) {
    ce_json_error('Geen brand narrative', 400);
}

require_once __DIR__ . '/hba-identity-gate.php';
if (ce_hba_active_guardrail($pdo, $clientId) === null) {
    ce_json_error('Guardrail niet actief', 400);
}

$platform = strtolower(trim((string) ($article['platform_final'] ?? $article['platform'] ?? 'linkedin')));
$hygieneContext = ce_hygiene_scoring_context($pdo, trim((string) ($narrativeRow['contextprofiel'] ?? '')));

/** @return array<string, mixed>|null */
function ce_hygiene_acceptance_score(PDO $pdo, array $narrativeRow, string $platform, string $postText, ?array $hygieneContext): ?array
{
    $prompt = ce_hba_build_system_prompt($narrativeRow, $platform, 'article', $postText, $hygieneContext);
    $llm = ce_hba_call_llm($prompt, false);
    if (!$llm['ok'] || !is_array($llm['tool'])) {
        return null;
    }
    $tool = ce_hba_normalize_score_tool($llm['tool']);
    if (($tool['status'] ?? '') !== 'scored') {
        return null;
    }
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    $bc = is_array($scores['brand_contribution'] ?? null) ? $scores['brand_contribution'] : null;
    $dims = is_array($bc['dimensions'] ?? null) ? $bc['dimensions'] : [];

    return [
        'brandContribution' => isset($bc['score']) ? (float) $bc['score'] : null,
        'distinctiviteit' => isset($dims['distinctiviteit']) ? (float) $dims['distinctiviteit'] : null,
        'diagnose' => (string) ($tool['diagnose'] ?? ''),
    ];
}

$baseline = ce_hygiene_acceptance_score($pdo, $narrativeRow, $platform, $basePost, $hygieneContext);
$withHygienePhrase = ce_hygiene_acceptance_score($pdo, $narrativeRow, $platform, $withPhrase, $hygieneContext);

$pass = false;
if ($baseline && $withHygienePhrase) {
    $baseDist = $baseline['distinctiviteit'];
    $phraseDist = $withHygienePhrase['distinctiviteit'];
    if ($baseDist !== null && $phraseDist !== null) {
        $pass = $phraseDist < $baseDist;
    }
}

ce_json_response([
    'ok' => true,
    'articleId' => $articleId,
    'phrase' => $phrase,
    'hygieneListVersion' => is_array($hygieneContext) ? ($hygieneContext['version'] ?? null) : null,
    'baseline' => $baseline,
    'withPhrase' => $withHygienePhrase,
    'pass' => $pass,
    'promptVersion' => CE_HBA_PROMPT_VERSION,
]);
