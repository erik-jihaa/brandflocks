<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/fastlane-repository.php';
require_once __DIR__ . '/tone-styles.php';
require_once __DIR__ . '/ce03-meta-repository.php';
require_once __DIR__ . '/research-quality.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-identity-gate.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$clientId = trim((string) ($_GET['client_id'] ?? '016384af-b26e-4415-aa9c-abc46b804798'));
$slotId = trim((string) ($_GET['slot_id'] ?? ''));
$platform = strtolower(trim((string) ($_GET['platform'] ?? 'blog')));
$trigger = ($_GET['trigger'] ?? '1') !== '0';

if (!in_array($platform, ['linkedin', 'blog', 'instagram', 'x', 'facebook'], true)) {
    ce_json_error('platform moet linkedin, blog, instagram, x of facebook zijn', 400);
}

/** @return array<string, mixed>|null */
function ce_ce03_phase2_pick_slot(PDO $pdo, string $clientId): ?array
{
    require_once __DIR__ . '/research-quality.php';

    $stmt = $pdo->prepare(
        "SELECT cs.id AS slot_id, cs.theme, COUNT(ri.id) AS item_count
         FROM content_slots cs
         INNER JOIN research_items ri ON ri.slot_id = cs.id
         WHERE cs.client_id = :client_id
           AND COALESCE(cs.status, '') NOT IN ('archived', 'archive', 'deleted')
         GROUP BY cs.id, cs.theme
         HAVING item_count > 0
         ORDER BY item_count DESC, cs.created_at DESC"
    );
    $stmt->execute(['client_id' => $clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $slotId = (string) ($row['slot_id'] ?? '');
        if ($slotId === '') {
            continue;
        }
        $ready = ce_research_slot_ready_for_write($pdo, $slotId);
        if (($ready['ok'] ?? false) === true) {
            return $row;
        }
    }

    return null;
}

if ($slotId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    $picked = ce_ce03_phase2_pick_slot($pdo, $clientId);
    if ($picked === null) {
        ce_json_error('Geen slot met research gevonden voor client', 404);
    }
    $slotId = (string) ($picked['slot_id'] ?? '');
}

$slotStmt = $pdo->prepare('SELECT id, client_id, theme, status FROM content_slots WHERE id = :id LIMIT 1');
$slotStmt->execute(['id' => $slotId]);
$slotRow = $slotStmt->fetch();
if (!$slotRow || (string) ($slotRow['client_id'] ?? '') !== $clientId) {
    ce_json_error('Slot niet gevonden voor tenant', 404);
}

$researchReady = ce_research_slot_ready_for_write($pdo, $slotId);
if (($researchReady['ok'] ?? false) !== true) {
    ce_json_error((string) ($researchReady['message'] ?? 'Research onvoldoende voor CE-03'), 409);
}

ce_set_active_client_id($clientId);

try {
    ce_ensure_article_stub_for_slot($pdo, $slotId);
} catch (Throwable $e) {
    ce_json_error($e->getMessage(), 409);
}

$article = ce_fetch_article_by_slot($pdo, $slotId, $clientId);
if ($article === null) {
    ce_json_error('Artikel stub ontbreekt', 500);
}

$articleId = (string) ($article['id'] ?? '');
$contentBefore = trim((string) ($article['content_ai'] ?? ''));
$contentHashBefore = $contentBefore !== '' ? md5($contentBefore) : null;

if (ce_table_has_column($pdo, 'articles', 'platform_final')) {
    $pdo->prepare(
        'UPDATE articles SET platform_final = :p, platform_suggested = :p, status = :status WHERE id = :id'
    )->execute([
        'p' => $platform,
        'status' => 'draft',
        'id' => $articleId,
    ]);
}

$meta = ce_ce03_webhook_meta_from_slot($pdo, $slotId);
$metaPreview = [
    'platform_intended' => $meta['platform_intended'] ?? null,
    'platform_label' => $meta['platform_label'] ?? null,
    'platform_form_guidelines' => isset($meta['platform_form_guidelines'])
        ? mb_substr((string) $meta['platform_form_guidelines'], 0, 120) . '…'
        : null,
    'grenzen_block_chars' => isset($meta['grenzen_block']) ? strlen((string) $meta['grenzen_block']) : 0,
    'grenzen_has_block' => trim((string) ($meta['grenzen_block'] ?? '')) !== '',
];

$triggerResult = null;
if ($trigger) {
    $triggerResult = ce_trigger_ce03_write($slotId, true, $meta);
}

ce_json_response([
    'ok' => true,
    'clientId' => $clientId,
    'slotId' => $slotId,
    'theme' => (string) ($slotRow['theme'] ?? ''),
    'platformRequested' => $platform,
    'articleId' => $articleId,
    'contentHashBefore' => $contentHashBefore,
    'contentWordCountBefore' => $contentBefore !== '' ? str_word_count($contentBefore) : 0,
    'metaPreview' => $metaPreview,
    'grenzenBlock' => $meta['grenzen_block'] ?? '',
    'grenzenDebug' => [
        'guardrailActive' => ce_hba_active_guardrail($pdo, $clientId) !== null,
        'grenzenUsable' => ($narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId))
            ? ce_ce03_grenzen_fields_usable($narrativeRow)
            : false,
    ],
    'trigger' => $triggerResult,
    'poll' => [
        'endpoint' => '/api/admin/debug-article?slot_id=' . rawurlencode($slotId) . '&full=1',
        'hint' => 'Poll tot contentHash verandert (CE-03 duurt 1–3 min)',
    ],
]);
