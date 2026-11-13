<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/campagne-preview-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$slotId = trim((string) ($_GET['slot_id'] ?? $_GET['slotId'] ?? ''));
$campaignId = trim((string) ($_GET['campaign_id'] ?? $_GET['campaignId'] ?? ''));
$titleLike = trim((string) ($_GET['title_like'] ?? ''));

if ($method === 'POST') {
    $body = ce_read_json_body();
    if ($slotId === '') {
        $slotId = trim((string) ($body['slotId'] ?? $body['slot_id'] ?? ''));
    }
    if ($campaignId === '') {
        $campaignId = trim((string) ($body['campaignId'] ?? $body['campaign_id'] ?? ''));
    }
    if ($titleLike === '') {
        $titleLike = trim((string) ($body['titleLike'] ?? $body['title_like'] ?? ''));
    }
}

if ($slotId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    $row = ce_fetch_fastlane_slot_row($pdo, $slotId);
    if ($row === null) {
        ce_json_error('Slot niet gevonden', 404);
    }
    $before = [
        'theme' => trim((string) ($row['theme'] ?? '')),
        'campaignId' => trim((string) ($row['campaign_id'] ?? '')),
        'platform' => trim((string) ($row['campaign_platform'] ?? '')),
        'resolvedTitle' => ce_campagne_resolve_slot_title($pdo, $row),
    ];
    if ($method === 'POST') {
        ce_campagne_repair_slot_campaign_columns($pdo, $slotId);
        if ($campaignId === '') {
            $campaignId = trim((string) ($before['campaignId'] ?? ''));
        }
        if ($campaignId === '') {
            $enriched = ce_campagne_enrich_slot_row($row);
            $campaignId = trim((string) ($enriched['campaign_id'] ?? ''));
        }
        if ($campaignId !== '') {
            ce_campagne_repair_campaign_slots($pdo, $campaignId);
        }
    }
    $afterRow = ce_fetch_fastlane_slot_row($pdo, $slotId) ?? $row;
    $after = [
        'theme' => trim((string) ($afterRow['theme'] ?? '')),
        'campaignId' => trim((string) ($afterRow['campaign_id'] ?? '')),
        'platform' => trim((string) ($afterRow['campaign_platform'] ?? '')),
        'resolvedTitle' => ce_campagne_resolve_slot_title($pdo, $afterRow),
    ];
    ce_json_response([
        'ok' => true,
        'slotId' => $slotId,
        'repaired' => $method === 'POST',
        'before' => $before,
        'after' => $after,
        'campaignId' => $campaignId !== '' ? $campaignId : null,
    ]);
}

if ($campaignId === '' && $titleLike !== '' && ce_table_exists($pdo, 'campaigns')) {
    $stmt = $pdo->prepare('SELECT id, title, preview_status FROM campaigns WHERE title LIKE :q ORDER BY created_at DESC LIMIT 20');
    $stmt->execute(['q' => '%' . $titleLike . '%']);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($matches === []) {
        ce_json_error('Geen campagne voor title_like', 404);
    }
    $campaignId = trim((string) ($matches[0]['id'] ?? ''));
    if ($method === 'GET') {
        ce_json_response(['ok' => true, 'campaignMatches' => $matches, 'campaignId' => $campaignId]);
    }
}

if ($campaignId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $campaignId)) {
    ce_json_error('campaign_id of slot_id vereist', 400);
}

$slotsStmt = $pdo->prepare(
    'SELECT id, theme, campaign_id, campaign_platform, campaign_fase, campaign_slot_index, publish_date
     FROM content_slots WHERE campaign_id = :cid ORDER BY publish_date ASC, campaign_slot_index ASC'
);
$slotsStmt->execute(['cid' => $campaignId]);
$slotsBefore = $slotsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($method === 'POST') {
    ce_campagne_repair_campaign_slots($pdo, $campaignId);
}

$slotsStmt->execute(['cid' => $campaignId]);
$slotsAfter = $slotsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

ce_json_response([
    'ok' => true,
    'repaired' => $method === 'POST',
    'campaignId' => $campaignId,
    'slotCount' => count($slotsAfter),
    'slotsBefore' => array_map(static function (array $row) use ($pdo): array {
        return [
            'slotId' => (string) ($row['id'] ?? ''),
            'theme' => (string) ($row['theme'] ?? ''),
            'platform' => (string) ($row['campaign_platform'] ?? ''),
            'resolvedTitle' => ce_campagne_resolve_slot_title($pdo, $row),
        ];
    }, $slotsBefore),
    'slotsAfter' => array_map(static function (array $row) use ($pdo): array {
        return [
            'slotId' => (string) ($row['id'] ?? ''),
            'theme' => (string) ($row['theme'] ?? ''),
            'platform' => (string) ($row['campaign_platform'] ?? ''),
            'resolvedTitle' => ce_campagne_resolve_slot_title($pdo, $row),
        ];
    }, $slotsAfter),
]);
