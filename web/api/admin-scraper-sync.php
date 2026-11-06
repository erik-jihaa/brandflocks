<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/scrape-repository.php';
require_once __DIR__ . '/theme-repository.php';
require_once __DIR__ . '/client-context.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    ce_json_error('Method not allowed', 405);
}

$pdo = ce_db();

// Ops-endpoint over tenants heen: alleen met de service-token, niet met een tenantsessie.
if (!ce_reset_token_valid()) {
    ce_json_error('Reset-token vereist', 403);
}

ce_ensure_sources_schema($pdo);
ce_ensure_research_items_schema($pdo);

$clientId = ce_resolve_client_id($pdo, $_GET['client_id'] ?? null);
$sourceId = trim((string) ($_GET['source_id'] ?? ''));
$slotId = trim((string) ($_GET['slot_id'] ?? ''));
$themeId = trim((string) ($_GET['theme_id'] ?? ''));

$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
    $sourceId = trim((string) ($body['sourceId'] ?? $body['source_id'] ?? $sourceId));
    $slotId = trim((string) ($body['slotId'] ?? $body['slot_id'] ?? $slotId));
    $themeId = trim((string) ($body['themeId'] ?? $body['theme_id'] ?? $themeId));

    // Ops: LinkedIn-profiel / budget bijwerken vóór sync (reset-token only).
    if (
        ce_reset_token_valid()
        && $sourceId !== ''
        && (
            array_key_exists('scraperLinkedinProfile', $body)
            || array_key_exists('maxUnitsPerRun', $body)
        )
    ) {
        $patch = [];
        if (array_key_exists('scraperLinkedinProfile', $body)) {
            $patch['scraperLinkedinProfile'] = trim((string) $body['scraperLinkedinProfile']);
        }
        if (array_key_exists('maxUnitsPerRun', $body)) {
            $patch['maxUnitsPerRun'] = $body['maxUnitsPerRun'] === null
                ? null
                : (int) $body['maxUnitsPerRun'];
        }
        if ($patch !== [] && ce_update_source($pdo, $sourceId, $patch) === null) {
            ce_json_error('Source not found', 404);
        }
    }
}

$sql = "SELECT id, title, category, scraper_linkedin_profile, scraper_actor_or_task_id,
               scraper_api_token_ref, max_units_per_run
        FROM sources
        WHERE category IN ('scraper', 'linkedin')";
$params = [];

if ($sourceId !== '') {
    $sql .= ' AND id = :source_id';
    $params['source_id'] = $sourceId;
} elseif ($clientId !== null) {
    $sql .= ' AND client_id = :client_id';
    $params['client_id'] = $clientId;
}

$sql .= ' ORDER BY category ASC, title ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$results = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    $id = (string) ($row['id'] ?? '');
    $entry = [
        'sourceId' => $id,
        'title' => $row['title'] ?? '',
        'category' => $row['category'] ?? '',
        'linkedinProfile' => $row['scraper_linkedin_profile'] ?? null,
        'actorId' => $row['scraper_actor_or_task_id'] ?? null,
        'tokenConfigured' => trim((string) ($row['scraper_api_token_ref'] ?? '')) !== '',
        'maxUnitsPerRun' => $row['max_units_per_run'],
    ];

    if (ce_table_exists($pdo, 'scrape_runs')) {
        $runStmt = $pdo->prepare(
            'SELECT status, items_count, message, created_at
             FROM scrape_runs WHERE source_id = :id
             ORDER BY created_at DESC LIMIT 1'
        );
        $runStmt->execute(['id' => $id]);
        $entry['lastRun'] = $runStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $entry['probe'] = ce_probe_scraper_source($pdo, $id);

    if ($method === 'POST' && ($body['runScraper'] ?? true)) {
        $entry['scraperRun'] = ce_run_scraper_source(
            $pdo,
            $id,
            $themeId !== '' ? $themeId : null,
            $slotId !== '' ? $slotId : null,
            trim((string) ($body['searchLabel'] ?? '')) ?: null,
        );
    }

    $results[] = $entry;
}

$response = [
    'ok' => true,
    'results' => $results,
];

if ($method === 'POST' && ($body['triggerResearch'] ?? false)) {
    if ($slotId === '' && $clientId !== null) {
        $slotStmt = $pdo->prepare(
            "SELECT s.id
             FROM content_slots s
             WHERE s.client_id = :client_id
               AND s.status IN ('open', 'planned', 'themed', 'research_done')
             ORDER BY s.created_at DESC
             LIMIT 1"
        );
        $slotStmt->execute(['client_id' => $clientId]);
        $slotId = trim((string) ($slotStmt->fetchColumn() ?: ''));
    }

    if ($slotId === '' && $themeId === '') {
        $response['ce02'] = ['ok' => false, 'error' => 'Geen slot_id of theme_id — geef slotId mee of zet triggerResearch zonder slotId alleen als er open slots zijn.'];
    } else {
        $response['ce02'] = ce_trigger_ce02_research(
            $themeId !== '' ? $themeId : null,
            $slotId !== '' ? $slotId : null,
        );
        $response['researchSlotId'] = $slotId !== '' ? $slotId : null;
    }
}

if ($method === 'GET' && $clientId !== null && ce_table_exists($pdo, 'research_items')) {
    $riStmt = $pdo->prepare(
        "SELECT ri.source_type, COUNT(*) AS cnt
         FROM research_items ri
         LEFT JOIN content_slots s ON s.id = ri.slot_id
         LEFT JOIN themes t ON t.id = ri.theme_id
         WHERE COALESCE(s.client_id, t.client_id) = :client_id
         GROUP BY ri.source_type"
    );
    $riStmt->execute(['client_id' => $clientId]);
    $response['researchBySourceType'] = $riStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $urlContains = trim((string) ($_GET['url_contains'] ?? ''));
    if ($urlContains !== '') {
        $like = '%' . $urlContains . '%';
        if (ce_table_exists($pdo, 'sources')) {
            $srcStmt = $pdo->prepare(
                "SELECT id, title, url, category, source_kind, url_status, last_checked_at
                 FROM sources
                 WHERE client_id = :client_id
                   AND (url LIKE :q OR title LIKE :q)
                 ORDER BY last_checked_at DESC
                 LIMIT 10"
            );
            $srcStmt->execute(['client_id' => $clientId, 'q' => $like]);
            $response['sourcesMatching'] = $srcStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $detailStmt = $pdo->prepare(
            "SELECT ri.id, ri.title, ri.url, ri.snippet, ri.source_type, ri.source_id, ri.theme_id, ri.slot_id,
                    ri.feedback, ri.item_rationale, ri.created_at,
                    s.title AS source_title, s.url AS source_url, s.source_kind
             FROM research_items ri
             LEFT JOIN sources s ON s.id = ri.source_id
             LEFT JOIN content_slots cs ON cs.id = ri.slot_id
             LEFT JOIN themes th ON th.id = ri.theme_id
             WHERE COALESCE(cs.client_id, th.client_id, s.client_id) = :client_id
               AND (ri.url LIKE :q OR ri.title LIKE :q OR s.url LIKE :q OR ri.snippet LIKE :q)
             ORDER BY ri.created_at DESC
             LIMIT 20"
        );
        $detailStmt->execute(['client_id' => $clientId, 'q' => $like]);
        $response['researchMatching'] = $detailStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (ce_table_exists($pdo, 'themes')) {
            $themeStmt = $pdo->prepare(
                "SELECT id, title, status, cycle_week
                 FROM themes
                 WHERE client_id = :client_id
                   AND COALESCE(status, 'proposed') NOT IN ('deleted', 'archived')
                 ORDER BY cycle_week DESC, created_at DESC
                 LIMIT 5"
            );
            $themeStmt->execute(['client_id' => $clientId]);
            $response['recentThemes'] = $themeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
}

if ($method === 'GET' && $themeId !== '' && ce_table_exists($pdo, 'research_items')) {
    $themeResearchStmt = $pdo->prepare(
        "SELECT ri.id, ri.title, ri.url, ri.snippet, ri.source_type, ri.source_id, ri.theme_id, ri.slot_id,
                ri.feedback, ri.item_rationale, ri.created_at,
                s.title AS source_title, s.url AS source_url, s.source_kind
         FROM research_items ri
         LEFT JOIN sources s ON s.id = ri.source_id
         WHERE ri.theme_id = :theme_id
         ORDER BY ri.created_at ASC"
    );
    $themeResearchStmt->execute(['theme_id' => $themeId]);
    $response['researchForTheme'] = $themeResearchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

require_once __DIR__ . '/hba-baseline-repository.php';
$response['hbaBaseline'] = ce_hba_baseline_run_pending($pdo, null, CE_HBA_BASELINE_MAX_POSTS);

ce_json_response($response);
