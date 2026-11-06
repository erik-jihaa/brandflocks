<?php

declare(strict_types=1);

/**
 * HBA testprotocol §2 — contentscore A/B (Sonnet 5 vs Opus 5), zonder DB-persistentie.
 * Alleen via CE_RESET_TOKEN. Batchgewijs aanroepen (timeout).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

const CE_HBA_AB_SONNET = 'claude-sonnet-5';
const CE_HBA_AB_OPUS = 'claude-opus-5';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = ce_db();

/** @return array{id: string, name: string}|null */
function ce_hba_ab_resolve_client(PDO $pdo, string $clientId, string $clientSearch): ?array
{
    if ($clientId !== '' && preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        $stmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? '')] : null;
    }

    $search = trim($clientSearch);
    if ($search === '') {
        return null;
    }

    if (ce_table_has_column($pdo, 'clients', 'company')) {
        $stmt = $pdo->prepare(
            "SELECT id, name FROM clients
             WHERE LOWER(name) LIKE :q OR LOWER(COALESCE(company, '')) LIKE :q
             ORDER BY name ASC LIMIT 1"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, name FROM clients
             WHERE LOWER(name) LIKE :q
             ORDER BY name ASC LIMIT 1"
        );
    }
    $stmt->execute(['q' => '%' . strtolower($search) . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? ['id' => (string) $row['id'], 'name' => (string) ($row['name'] ?? '')] : null;
}

if ($method === 'GET') {
    $client = ce_hba_ab_resolve_client(
        $pdo,
        trim((string) ($_GET['client_id'] ?? '')),
        trim((string) ($_GET['client_search'] ?? $_GET['client'] ?? 'doornvogel')),
    );
    if ($client === null) {
        ce_json_error('Client niet gevonden — geef client_id of client_search', 404);
    }

    $total = ce_hba_count_content_score_test_articles($pdo, $client['id']);
    $sample = ce_hba_fetch_content_score_test_articles($pdo, $client['id'], 5, 0);

    ce_json_response([
        'ok' => true,
        'client' => $client,
        'models' => ['sonnet' => CE_HBA_AB_SONNET, 'opus' => CE_HBA_AB_OPUS],
        'totalCandidates' => $total,
        'sample' => $sample,
        'note' => $total < 50
            ? 'Minder dan 50 posts beschikbaar — test draait over alle kandidaten.'
            : 'Max 50 posts (offset/limit in POST).',
    ]);
}

if ($method !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

@set_time_limit(600);

$body = ce_read_json_body();
$client = ce_hba_ab_resolve_client(
    $pdo,
    trim((string) ($body['client_id'] ?? $body['clientId'] ?? '')),
    trim((string) ($body['client_search'] ?? $body['client'] ?? 'doornvogel')),
);
if ($client === null) {
    ce_json_error('Client niet gevonden — geef client_id of client_search', 404);
}

$offset = max(0, (int) ($body['offset'] ?? 0));
$limit = max(1, min(5, (int) ($body['limit'] ?? 3)));
$maxTotal = max(1, min(50, (int) ($body['max_total'] ?? $body['maxTotal'] ?? 50)));

if ($offset >= $maxTotal) {
    ce_json_response([
        'ok' => true,
        'client' => $client,
        'offset' => $offset,
        'limit' => $limit,
        'results' => [],
        'hasMore' => false,
        'totalCandidates' => ce_hba_count_content_score_test_articles($pdo, $client['id']),
    ]);
}

$remaining = $maxTotal - $offset;
$fetchLimit = min($limit, $remaining);
$articles = ce_hba_fetch_content_score_test_articles($pdo, $client['id'], $fetchLimit, $offset);
$totalCandidates = ce_hba_count_content_score_test_articles($pdo, $client['id']);

$results = [];
foreach ($articles as $article) {
    $articleId = $article['articleId'];
    $entry = [
        'articleId' => $articleId,
        'headline' => $article['headline'],
        'status' => $article['status'],
        'publishedAt' => $article['publishedAt'],
        'sonnet' => null,
        'opus' => null,
    ];

    foreach ([CE_HBA_AB_SONNET => 'sonnet', CE_HBA_AB_OPUS => 'opus'] as $model => $key) {
        $run = ce_hba_dry_run_content_score_for_article($pdo, $articleId, $model);
        if (!$run['ok']) {
            $entry[$key] = ['ok' => false, 'error' => $run['error'] ?? 'mislukt', 'model' => $model];
            continue;
        }
        $entry[$key] = array_merge(
            ['ok' => true, 'model' => $run['model'] ?? $model],
            ce_hba_content_score_tool_summary(is_array($run['tool']) ? $run['tool'] : []),
        );
    }

    $results[] = $entry;
}

$nextOffset = $offset + count($articles);
$hasMore = $nextOffset < min($maxTotal, $totalCandidates) && count($articles) > 0;

ce_json_response([
    'ok' => true,
    'client' => $client,
    'models' => ['sonnet' => CE_HBA_AB_SONNET, 'opus' => CE_HBA_AB_OPUS],
    'offset' => $offset,
    'limit' => $fetchLimit,
    'maxTotal' => $maxTotal,
    'totalCandidates' => $totalCandidates,
    'processed' => count($results),
    'nextOffset' => $hasMore ? $nextOffset : null,
    'hasMore' => $hasMore,
    'results' => $results,
]);
