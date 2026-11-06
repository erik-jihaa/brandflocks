<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/research-quality.php';
require_once __DIR__ . '/theme-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
ce_ensure_research_items_schema($pdo);

$clientQuery = trim((string) ($_GET['client'] ?? ''));
$slotId = trim((string) ($_GET['slot_id'] ?? ''));

$clients = [];
if ($slotId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    $slotClientStmt = $pdo->prepare(
        'SELECT c.id, c.name
         FROM content_slots s
         INNER JOIN clients c ON c.id = s.client_id
         WHERE s.id = :slot_id
         LIMIT 1'
    );
    $slotClientStmt->execute(['slot_id' => $slotId]);
    $row = $slotClientStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && ($row['id'] ?? '') !== '') {
        $clients = [$row];
    }
}

if ($clients === []) {
    if ($clientQuery === '') {
        $clientQuery = 'bieze';
    }
    $clientStmt = $pdo->prepare(
        "SELECT id, name FROM clients
         WHERE LOWER(name) LIKE :q
         ORDER BY name ASC LIMIT 5"
    );
    $clientStmt->execute(['q' => '%' . strtolower($clientQuery) . '%']);
    $clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($clients === []) {
        ce_json_error('Geen client gevonden voor: ' . $clientQuery, 404);
    }
}

$clientId = (string) $clients[0]['id'];

if ($slotId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $slotId)) {
    $slotPick = $pdo->prepare(
        'SELECT s.id, s.theme, a.self_review
        FROM content_slots s
        INNER JOIN articles a ON a.slot_id = s.id
        WHERE s.client_id = :client_id
          AND a.self_review IS NOT NULL
          AND a.self_review != \'\'
        ORDER BY a.id DESC
        LIMIT 10'
    );
    $slotPick->execute(['client_id' => $clientId]);
    $candidates = $slotPick->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($candidates as $candidate) {
        $review = strtolower((string) ($candidate['self_review'] ?? ''));
        if (str_contains($review, 'onderbouw') || str_contains($review, 'untitled') || str_contains($review, 'inhoudsloos')) {
            $slotId = (string) $candidate['id'];
            break;
        }
    }
    if ($slotId === '' && $candidates !== []) {
        $slotId = (string) $candidates[0]['id'];
    }
    if ($slotId === '') {
        $fallback = $pdo->prepare(
            'SELECT id FROM content_slots WHERE client_id = :client_id ORDER BY created_at DESC LIMIT 1'
        );
        $fallback->execute(['client_id' => $clientId]);
        $slotId = trim((string) ($fallback->fetchColumn() ?: ''));
    }
}

if ($slotId === '') {
    ce_json_error('Geen slot gevonden voor client', 404);
}

$hasStance = ce_table_has_column($pdo, 'research_items', 'stance');
$hasReliability = ce_table_has_column($pdo, 'research_items', 'reliability');
$hasInvalshoek = ce_table_has_column($pdo, 'research_items', 'invalshoek');
$extraCols = '';
if ($hasStance) {
    $extraCols .= ', ri.stance';
}
if ($hasReliability) {
    $extraCols .= ', ri.reliability';
}
$invalshoekCol = $hasInvalshoek ? ', ri.invalshoek' : '';
$themeInvalshoekCol = $hasInvalshoek ? ', invalshoek' : '';

$itemsStmt = $pdo->prepare(
    "SELECT ri.id, ri.source_type, ri.title, CHAR_LENGTH(ri.snippet) AS snip_len,
            LEFT(ri.snippet, 160) AS snippet_preview, ri.url{$invalshoekCol}, ri.created_at{$extraCols}
     FROM research_items ri
     WHERE ri.slot_id = :slot_id
     ORDER BY ri.created_at ASC"
);
$itemsStmt->execute(['slot_id' => $slotId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($items === []) {
    $themeStmt = $pdo->prepare('SELECT theme_id FROM content_slots WHERE id = :id LIMIT 1');
    $themeStmt->execute(['id' => $slotId]);
    $themeId = trim((string) ($themeStmt->fetchColumn() ?: ''));
    if ($themeId !== '') {
        $themeExtraCols = str_replace('ri.', '', $extraCols);
        $themeItems = $pdo->prepare(
            "SELECT id, source_type, title, CHAR_LENGTH(snippet) AS snip_len, LEFT(snippet, 160) AS snippet_preview, url{$themeInvalshoekCol}, created_at{$themeExtraCols}
             FROM research_items WHERE theme_id = :theme_id ORDER BY created_at ASC"
        );
        $themeItems->execute(['theme_id' => $themeId]);
        $items = $themeItems->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$usableCount = 0;
$rows = [];
foreach ($items as $item) {
    $usable = ce_research_item_row_is_usable([
        'title' => $item['title'] ?? '',
        'snippet' => str_repeat('x', (int) ($item['snip_len'] ?? 0)),
    ]);
    if ($usable) {
        $usableCount++;
    }
    $rows[] = [
        'id' => $item['id'] ?? '',
        'sourceType' => $item['source_type'] ?? '',
        'title' => $item['title'] ?? '',
        'snippetLength' => (int) ($item['snip_len'] ?? 0),
        'snippetPreview' => $item['snippet_preview'] ?? '',
        'url' => $item['url'] ?? '',
        'stance' => $item['stance'] ?? null,
        'reliability' => $item['reliability'] ?? null,
        'invalshoek' => $item['invalshoek'] ?? null,
        'usable' => $usable,
        'createdAt' => $item['created_at'] ?? null,
    ];
}

$articleStmt = $pdo->prepare(
    'SELECT id, LEFT(self_review, 500) AS self_review_preview FROM articles WHERE slot_id = :slot_id ORDER BY id DESC LIMIT 1'
);
$articleStmt->execute(['slot_id' => $slotId]);
$article = $articleStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$fetchTest = null;
try {
    ce_set_active_client_id($clientId);
    $fetchTest = ce_fetch_slot_research($pdo, $slotId);
} catch (Throwable $e) {
    $fetchTest = [
        'ok' => false,
        'error' => $e->getMessage(),
        'class' => $e::class,
    ];
}

ce_json_response([
    'ok' => true,
    'client' => $clients[0],
    'clientsMatched' => $clients,
    'slotId' => $slotId,
    'usableCount' => $usableCount,
    'minRequired' => CE_RESEARCH_MIN_USABLE_ITEMS,
    'minSnippetLength' => CE_RESEARCH_MIN_SNIPPET_LEN,
    'wouldBlockCe03' => $usableCount < CE_RESEARCH_MIN_USABLE_ITEMS,
    'insufficientMessage' => ce_research_insufficient_message(),
    'selfReviewPreview' => $article['self_review_preview'] ?? null,
    'items' => $rows,
    'fetchSlotResearch' => is_array($fetchTest) && isset($fetchTest['ok']) && $fetchTest['ok'] === false
        ? $fetchTest
        : [
            'ok' => true,
            'itemCount' => count(is_array($fetchTest['items'] ?? null) ? $fetchTest['items'] : []),
            'themeGroupCount' => count(is_array($fetchTest['themeGroups'] ?? null) ? $fetchTest['themeGroups'] : []),
            'slotTheme' => (string) ($fetchTest['slotTheme'] ?? ''),
            'phaseKey' => (string) ($fetchTest['phaseKey'] ?? ''),
        ],
]);
