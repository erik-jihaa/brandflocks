<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-prompt.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/hba-identity-gate.php';

const CE_HBA_DETERMINISM_MAX_SPREAD = 0.5;
const CE_HBA_DETERMINISM_DEFAULT_RUNS = 3;
const CE_HBA_DETERMINISM_DEFAULT_ARTICLES = 10;

/** Request-local: determinismemeting mag geen score-averaging gebruiken. */
function ce_hba_determinism_measurement_active(bool $set = false, ?bool $value = null): bool
{
    static $active = false;
    if ($set && $value !== null) {
        $active = $value;
    }

    return $active;
}

function ce_ensure_hba_determinism_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $migration = dirname(__DIR__) . '/migrations/add_hba_determinism.sql';
    if (!is_readable($migration)) {
        return;
    }

    try {
        foreach (preg_split('/;\s*\n/', (string) file_get_contents($migration)) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        unset($GLOBALS['ce_table_columns']['clients']);
    } catch (Throwable) {
        // Migratie via admin-run-migration indien nodig.
    }
}

/** @return array{displayMode: string, averaging: bool, status: ?string, measuredAt: ?string} */
function ce_hba_determinism_client_settings(PDO $pdo, string $clientId): array
{
    ce_ensure_hba_determinism_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_score_display_mode')) {
        return [
            'displayMode' => 'band',
            'averaging' => false,
            'status' => null,
            'measuredAt' => null,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT hba_score_display_mode, hba_score_averaging, hba_determinism_status, hba_determinism_measured_at
         FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch() ?: [];

    $displayMode = strtolower(trim((string) ($row['hba_score_display_mode'] ?? 'band')));
    if (!in_array($displayMode, ['decimal', 'band'], true)) {
        $displayMode = 'band';
    }

    return [
        'displayMode' => $displayMode,
        'averaging' => !empty($row['hba_score_averaging']),
        'status' => !empty($row['hba_determinism_status']) ? (string) $row['hba_determinism_status'] : null,
        'measuredAt' => !empty($row['hba_determinism_measured_at']) ? (string) $row['hba_determinism_measured_at'] : null,
    ];
}

function ce_hba_client_score_display_mode(PDO $pdo, string $clientId): string
{
    return ce_hba_determinism_client_settings($pdo, $clientId)['displayMode'];
}

function ce_hba_client_score_averaging_enabled(PDO $pdo, string $clientId): bool
{
    return ce_hba_determinism_client_settings($pdo, $clientId)['averaging'];
}

/** @param array<string, mixed> $report */
function ce_hba_determinism_store_result(PDO $pdo, string $clientId, bool $passed, array $report): void
{
    ce_ensure_hba_determinism_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_determinism_status')) {
        return;
    }

    $pdo->prepare(
        'UPDATE clients SET
            hba_determinism_status = :status,
            hba_determinism_measured_at = NOW(),
            hba_determinism_report = :report,
            hba_score_display_mode = :display_mode,
            hba_score_averaging = :averaging
         WHERE id = :id'
    )->execute([
        'status' => $passed ? 'passed' : 'failed',
        'report' => json_encode($report, JSON_UNESCAPED_UNICODE),
        'display_mode' => $passed ? 'decimal' : 'band',
        'averaging' => $passed ? 0 : 1,
        'id' => $clientId,
    ]);
}

/** @return list<array<string, mixed>> */
function ce_hba_determinism_pick_articles(PDO $pdo, string $clientId, int $limit): array
{
    $order = 'a.id DESC';
    if (ce_table_has_column($pdo, 'articles', 'published_at')) {
        $order = 'COALESCE(a.published_at, a.id) DESC';
        if (ce_table_has_column($pdo, 'articles', 'updated_at')) {
            $order = 'COALESCE(a.published_at, a.updated_at, a.id) DESC';
        } elseif (ce_table_has_column($pdo, 'articles', 'created_at')) {
            $order = 'COALESCE(a.published_at, a.created_at, a.id) DESC';
        }
    }

    $stmt = $pdo->prepare(
        "SELECT a.id,
                COALESCE(NULLIF(TRIM(a.headline_human), ''), NULLIF(TRIM(a.headline), '')) AS headline,
                COALESCE(NULLIF(TRIM(a.content_human), ''), NULLIF(TRIM(a.content_ai), '')) AS body,
                a.status,
                LEFT(COALESCE(NULLIF(TRIM(a.content_human), ''), NULLIF(TRIM(a.content_ai), '')), 120) AS excerpt
         FROM articles a
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND COALESCE(cs.origin, '') <> 'baseline'
           AND COALESCE(a.status, '') IN ('published', 'archived', 'approved', 'written', 'review', 'draft')
         HAVING body IS NOT NULL AND TRIM(body) <> ''
         ORDER BY {$order}
         LIMIT " . max(1, min(20, $limit * 3))
    );
    $stmt->bindValue('client_id', $clientId);
    $stmt->execute();

    $picked = [];
    $seenExcerpts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $excerpt = strtolower(trim((string) ($row['excerpt'] ?? '')));
        if ($excerpt !== '' && isset($seenExcerpts[$excerpt])) {
            continue;
        }
        if ($excerpt !== '') {
            $seenExcerpts[$excerpt] = true;
        }
        unset($row['excerpt']);
        $picked[] = $row;
        if (count($picked) >= $limit) {
            break;
        }
    }

    return $picked;
}

/** @param list<float|null> $values */
function ce_hba_determinism_spread(array $values): ?float
{
    $numeric = [];
    foreach ($values as $value) {
        if ($value === null || !is_numeric($value)) {
            continue;
        }
        $numeric[] = (float) $value;
    }
    if (count($numeric) < 2) {
        return null;
    }

    return max($numeric) - min($numeric);
}

/** @return array<string, mixed> */
function ce_hba_determinism_article_runs(
    PDO $pdo,
    string $clientId,
    array $articleRow,
    int $runsPerArticle,
): array {
    $articleId = (string) ($articleRow['id'] ?? '');
    if (function_exists('ce_set_active_client_id')) {
        ce_set_active_client_id($clientId);
    }

    $runs = [];
    $contentrols = [];
    $spreads = [
        'brandContribution' => [],
        'brandConsistency' => [],
        'architectureRisk' => [],
        'bcArchetypeFit' => [],
        'bcDoelFit' => [],
        'bcTegenkrachtFit' => [],
        'bcSpanning' => [],
        'bcMechanismeProof' => [],
        'bcDistinctiviteit' => [],
    ];

    for ($i = 0; $i < $runsPerArticle; $i++) {
        $result = ce_hba_score_article($pdo, [
            'articleId' => $articleId,
            'force' => true,
        ]);
        $score = is_array($result['score'] ?? null) ? $result['score'] : null;
        $dims = is_array($score['brandContributionDimensions'] ?? null)
            ? $score['brandContributionDimensions']
            : [];

        $run = [
            'run' => $i + 1,
            'status' => (string) ($result['status'] ?? ''),
            'contentrol' => $score['contentrol'] ?? null,
            'brandContribution' => $score['brandContribution'] ?? null,
            'brandConsistency' => $score['brandConsistency'] ?? null,
            'architectureRisk' => $score['architectureRisk'] ?? null,
            'promptVersion' => $score['promptVersion'] ?? CE_HBA_PROMPT_VERSION,
            'modelUsed' => $score['modelUsed'] ?? null,
            'temperature' => CE_HBA_CONTENT_SCORE_TEMPERATURE,
        ];
        $runs[] = $run;

        $contentrol = trim((string) ($run['contentrol'] ?? ''));
        if ($contentrol !== '') {
            $contentrols[] = $contentrol;
        }

        $spreads['brandContribution'][] = is_numeric($run['brandContribution'] ?? null) ? (float) $run['brandContribution'] : null;
        $spreads['brandConsistency'][] = is_numeric($run['brandConsistency'] ?? null) ? (float) $run['brandConsistency'] : null;
        $spreads['architectureRisk'][] = is_numeric($run['architectureRisk'] ?? null) ? (float) $run['architectureRisk'] : null;
        $spreads['bcArchetypeFit'][] = is_numeric($dims['archetypeFit'] ?? null) ? (float) $dims['archetypeFit'] : null;
        $spreads['bcDoelFit'][] = is_numeric($dims['doelFit'] ?? null) ? (float) $dims['doelFit'] : null;
        $spreads['bcTegenkrachtFit'][] = is_numeric($dims['tegenkrachtFit'] ?? null) ? (float) $dims['tegenkrachtFit'] : null;
        $spreads['bcSpanning'][] = is_numeric($dims['spanning'] ?? null) ? (float) $dims['spanning'] : null;
        $spreads['bcMechanismeProof'][] = is_numeric($dims['mechanismeProof'] ?? null) ? (float) $dims['mechanismeProof'] : null;
        $spreads['bcDistinctiviteit'][] = is_numeric($dims['distinctiviteit'] ?? null) ? (float) $dims['distinctiviteit'] : null;
    }

    $spreadResult = [];
    foreach ($spreads as $key => $values) {
        $spreadResult[$key] = ce_hba_determinism_spread($values);
    }

    $uniqueContentrols = array_values(array_unique($contentrols));

    return [
        'articleId' => $articleId,
        'headline' => (string) ($articleRow['headline'] ?? ''),
        'status' => (string) ($articleRow['status'] ?? ''),
        'runs' => $runs,
        'contentrolConsistent' => count($uniqueContentrols) <= 1,
        'contentrolValues' => $uniqueContentrols,
        'spread' => $spreadResult,
    ];
}

/** @param list<array<string, mixed>> $articleResults */
function ce_hba_determinism_evaluate(array $articleResults, float $maxSpread): array
{
    $pass = $articleResults !== [];
    $contentrolMismatches = 0;
    $spreadFailures = [];

    foreach ($articleResults as $articleResult) {
        if (empty($articleResult['contentrolConsistent'])) {
            $pass = false;
            $contentrolMismatches++;
        }
        foreach ($articleResult['spread'] ?? [] as $metric => $spreadValue) {
            if ($spreadValue !== null && (float) $spreadValue > $maxSpread) {
                $pass = false;
                $spreadFailures[] = [
                    'articleId' => (string) ($articleResult['articleId'] ?? ''),
                    'metric' => (string) $metric,
                    'spread' => (float) $spreadValue,
                ];
            }
        }
    }

    return [
        'pass' => $pass,
        'contentrolMismatches' => $contentrolMismatches,
        'spreadFailures' => $spreadFailures,
    ];
}

/** @return array<string, mixed>|null */
function ce_hba_determinism_load_partial_report(PDO $pdo, string $clientId): ?array
{
    ce_ensure_hba_determinism_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_determinism_report')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT hba_determinism_report FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

/** @param array<string, mixed> $report */
function ce_hba_determinism_save_partial_report(PDO $pdo, string $clientId, array $report): void
{
    ce_ensure_hba_determinism_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_determinism_report')) {
        return;
    }
    $pdo->prepare(
        'UPDATE clients SET hba_determinism_status = :status, hba_determinism_report = :report WHERE id = :id'
    )->execute([
        'status' => 'running',
        'report' => json_encode($report, JSON_UNESCAPED_UNICODE),
        'id' => $clientId,
    ]);
}

/** @return array<string, mixed> */
function ce_hba_determinism_article_single_run(
    PDO $pdo,
    string $clientId,
    array $articleRow,
    int $runNumber,
): array {
    $articleId = (string) ($articleRow['id'] ?? '');
    if (function_exists('ce_set_active_client_id')) {
        ce_set_active_client_id($clientId);
    }

    ce_hba_determinism_measurement_active(true, true);
    try {
        $result = ce_hba_score_article($pdo, [
            'articleId' => $articleId,
            'force' => true,
        ]);
    } finally {
        ce_hba_determinism_measurement_active(true, false);
    }

    $score = is_array($result['score'] ?? null) ? $result['score'] : null;
    $dims = is_array($score['brandContributionDimensions'] ?? null)
        ? $score['brandContributionDimensions']
        : [];

    return [
        'run' => $runNumber,
        'status' => (string) ($result['status'] ?? ''),
        'contentrol' => $score['contentrol'] ?? null,
        'brandContribution' => $score['brandContribution'] ?? null,
        'brandConsistency' => $score['brandConsistency'] ?? null,
        'architectureRisk' => $score['architectureRisk'] ?? null,
        'promptVersion' => $score['promptVersion'] ?? CE_HBA_PROMPT_VERSION,
        'modelUsed' => $score['modelUsed'] ?? null,
        'temperature' => CE_HBA_CONTENT_SCORE_TEMPERATURE,
        'dimensions' => $dims,
    ];
}

/** @param list<array<string, mixed>> $runs */
function ce_hba_determinism_summarize_runs(array $runs, array $articleRow): array
{
    $contentrols = [];
    $spreads = [
        'brandContribution' => [],
        'brandConsistency' => [],
        'architectureRisk' => [],
        'bcArchetypeFit' => [],
        'bcDoelFit' => [],
        'bcTegenkrachtFit' => [],
        'bcSpanning' => [],
        'bcMechanismeProof' => [],
        'bcDistinctiviteit' => [],
    ];

    foreach ($runs as $run) {
        $contentrol = trim((string) ($run['contentrol'] ?? ''));
        if ($contentrol !== '') {
            $contentrols[] = $contentrol;
        }
        $dims = is_array($run['dimensions'] ?? null) ? $run['dimensions'] : [];
        $spreads['brandContribution'][] = is_numeric($run['brandContribution'] ?? null) ? (float) $run['brandContribution'] : null;
        $spreads['brandConsistency'][] = is_numeric($run['brandConsistency'] ?? null) ? (float) $run['brandConsistency'] : null;
        $spreads['architectureRisk'][] = is_numeric($run['architectureRisk'] ?? null) ? (float) $run['architectureRisk'] : null;
        $spreads['bcArchetypeFit'][] = is_numeric($dims['archetypeFit'] ?? null) ? (float) $dims['archetypeFit'] : null;
        $spreads['bcDoelFit'][] = is_numeric($dims['doelFit'] ?? null) ? (float) $dims['doelFit'] : null;
        $spreads['bcTegenkrachtFit'][] = is_numeric($dims['tegenkrachtFit'] ?? null) ? (float) $dims['tegenkrachtFit'] : null;
        $spreads['bcSpanning'][] = is_numeric($dims['spanning'] ?? null) ? (float) $dims['spanning'] : null;
        $spreads['bcMechanismeProof'][] = is_numeric($dims['mechanismeProof'] ?? null) ? (float) $dims['mechanismeProof'] : null;
        $spreads['bcDistinctiviteit'][] = is_numeric($dims['distinctiviteit'] ?? null) ? (float) $dims['distinctiviteit'] : null;
    }

    $spreadResult = [];
    foreach ($spreads as $key => $values) {
        $spreadResult[$key] = ce_hba_determinism_spread($values);
    }

    $uniqueContentrols = array_values(array_unique($contentrols));
    $publicRuns = array_map(static function (array $run): array {
        unset($run['dimensions']);

        return $run;
    }, $runs);

    return [
        'articleId' => (string) ($articleRow['id'] ?? ''),
        'headline' => (string) ($articleRow['headline'] ?? ''),
        'status' => (string) ($articleRow['status'] ?? ''),
        'runs' => $publicRuns,
        'contentrolConsistent' => count($uniqueContentrols) <= 1,
        'contentrolValues' => $uniqueContentrols,
        'spread' => $spreadResult,
    ];
}

/** @return array<string, mixed> */
function ce_hba_determinism_run_measurement_batch(
    PDO $pdo,
    string $clientId,
    int $offset,
    int $runIndex,
    int $articleLimit = CE_HBA_DETERMINISM_DEFAULT_ARTICLES,
    int $runsPerArticle = CE_HBA_DETERMINISM_DEFAULT_RUNS,
    float $maxSpread = CE_HBA_DETERMINISM_MAX_SPREAD,
): array {
    ce_ensure_hba_determinism_schema($pdo);

    $clientStmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $clientStmt->execute(['id' => $clientId]);
    $client = $clientStmt->fetch();
    if (!$client) {
        throw new RuntimeException('Client niet gevonden');
    }

    $narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId);
    $missing = $narrativeRow ? ce_brand_narrative_missing_fields($narrativeRow) : CE_HBA_NARRATIVE_FIELDS;
    $guardrail = ce_hba_active_guardrail($pdo, $clientId);
    $articles = ce_hba_determinism_pick_articles($pdo, $clientId, $articleLimit);

    if ($missing !== [] || $guardrail === null || $articles === []) {
        throw new RuntimeException('Determinismemeting kan niet starten — check HBA, narrative en guardrail');
    }

    if ($offset < 0 || $offset >= count($articles)) {
        throw new RuntimeException('Offset buiten bereik — max ' . (count($articles) - 1));
    }
    if ($runIndex < 0 || $runIndex >= $runsPerArticle) {
        throw new RuntimeException('Run-index buiten bereik — max ' . ($runsPerArticle - 1));
    }

    $partial = ce_hba_determinism_load_partial_report($pdo, $clientId);
    if (($offset === 0 && $runIndex === 0) || $partial === null || ($partial['batchId'] ?? '') === '') {
        $partial = [
            'batchId' => ce_uuid(),
            'startedAt' => date('c'),
            'promptVersion' => CE_HBA_PROMPT_VERSION,
            'temperature' => CE_HBA_CONTENT_SCORE_TEMPERATURE,
            'config' => [
                'articleLimit' => $articleLimit,
                'runsPerArticle' => $runsPerArticle,
                'maxSpread' => $maxSpread,
            ],
            'articleRuns' => [],
            'results' => [],
        ];
    }

    $articleRow = $articles[$offset];
    $articleId = (string) ($articleRow['id'] ?? '');
    $articleRuns = is_array($partial['articleRuns'] ?? null) ? $partial['articleRuns'] : [];
    if (!isset($articleRuns[$articleId]) || !is_array($articleRuns[$articleId])) {
        $articleRuns[$articleId] = [];
    }

    $articleRuns[$articleId][] = ce_hba_determinism_article_single_run(
        $pdo,
        $clientId,
        $articleRow,
        $runIndex + 1,
    );
    $partial['articleRuns'] = $articleRuns;

    $results = [];
    foreach ($articles as $article) {
        $id = (string) ($article['id'] ?? '');
        $runs = is_array($articleRuns[$id] ?? null) ? $articleRuns[$id] : [];
        if (count($runs) >= $runsPerArticle) {
            $results[] = ce_hba_determinism_summarize_runs($runs, $article);
        }
    }
    $partial['results'] = $results;
    $partial['lastOffset'] = $offset;
    $partial['lastRunIndex'] = $runIndex;
    $partial['articlesPicked'] = count($articles);

    $isLast = $offset >= count($articles) - 1 && $runIndex >= $runsPerArticle - 1;
    $articleResults = $results;
    $evaluation = ce_hba_determinism_evaluate($articleResults, $maxSpread);
    $settings = ce_hba_determinism_client_settings($pdo, $clientId);

    $currentSummary = count($articleRuns[$articleId] ?? []) >= $runsPerArticle
        ? ce_hba_determinism_summarize_runs($articleRuns[$articleId], $articleRow)
        : null;

    if ($isLast) {
        $report = [
            'measuredAt' => date('c'),
            'promptVersion' => CE_HBA_PROMPT_VERSION,
            'temperature' => CE_HBA_CONTENT_SCORE_TEMPERATURE,
            'config' => $partial['config'],
            'evaluation' => $evaluation,
            'articlesTested' => count($articleResults),
            'results' => $articleResults,
            'mitigations' => [
                'temperatureZero' => CE_HBA_CONTENT_SCORE_TEMPERATURE === 0.0,
                'scoreAveragingEnabled' => !$evaluation['pass'],
                'displayMode' => $evaluation['pass'] ? 'decimal' : 'band',
            ],
            'batchMode' => true,
        ];
        ce_hba_determinism_store_result($pdo, $clientId, $evaluation['pass'], $report);
        $settings = ce_hba_determinism_client_settings($pdo, $clientId);
    } else {
        ce_hba_determinism_save_partial_report($pdo, $clientId, $partial);
    }

    return [
        'ok' => true,
        'section' => 'determinism',
        'batch' => true,
        'offset' => $offset,
        'runIndex' => $runIndex,
        'batchComplete' => $isLast,
        'allPass' => $isLast && $evaluation['pass'],
        'checks' => [
            'narrativeComplete' => $missing === [],
            'guardrailActive' => $guardrail !== null,
            'articlesAvailable' => count($articles) >= 1,
            'determinism' => $isLast ? $evaluation['pass'] : null,
        ],
        'client' => [
            'id' => $clientId,
            'name' => (string) ($client['name'] ?? ''),
            'scoreDisplayMode' => $settings['displayMode'],
            'scoreAveraging' => $settings['averaging'],
            'determinismStatus' => $settings['status'],
        ],
        'config' => $partial['config'],
        'evaluation' => $isLast ? $evaluation : null,
        'articlesPicked' => count($articles),
        'articlesDone' => count($articleResults),
        'articleResult' => $currentSummary,
        'runResult' => end($articleRuns[$articleId]) ?: null,
        'results' => $isLast ? $articleResults : null,
    ];
}

/** @return array<string, mixed> */
function ce_hba_determinism_run_measurement(
    PDO $pdo,
    string $clientId,
    int $articleLimit = CE_HBA_DETERMINISM_DEFAULT_ARTICLES,
    int $runsPerArticle = CE_HBA_DETERMINISM_DEFAULT_RUNS,
    float $maxSpread = CE_HBA_DETERMINISM_MAX_SPREAD,
    bool $persist = true,
): array {
    ce_ensure_hba_determinism_schema($pdo);

    $clientStmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $clientStmt->execute(['id' => $clientId]);
    $client = $clientStmt->fetch();
    if (!$client) {
        throw new RuntimeException('Client niet gevonden');
    }

    $narrativeRow = ce_fetch_current_brand_narrative($pdo, $clientId);
    $missing = $narrativeRow ? ce_brand_narrative_missing_fields($narrativeRow) : CE_HBA_NARRATIVE_FIELDS;
    $guardrail = ce_hba_active_guardrail($pdo, $clientId);
    $articles = ce_hba_determinism_pick_articles($pdo, $clientId, $articleLimit);
    $articleResults = [];

    if ($missing === [] && $guardrail !== null && $articles !== []) {
        foreach ($articles as $articleRow) {
            $articleResults[] = ce_hba_determinism_article_runs($pdo, $clientId, $articleRow, $runsPerArticle);
        }
    }

    $evaluation = ce_hba_determinism_evaluate($articleResults, $maxSpread);
    $settings = ce_hba_determinism_client_settings($pdo, $clientId);

    $report = [
        'measuredAt' => date('c'),
        'promptVersion' => CE_HBA_PROMPT_VERSION,
        'temperature' => CE_HBA_CONTENT_SCORE_TEMPERATURE,
        'config' => [
            'articleLimit' => $articleLimit,
            'runsPerArticle' => $runsPerArticle,
            'maxSpread' => $maxSpread,
        ],
        'evaluation' => $evaluation,
        'articlesTested' => count($articleResults),
        'results' => $articleResults,
        'mitigations' => [
            'temperatureZero' => CE_HBA_CONTENT_SCORE_TEMPERATURE === 0.0,
            'scoreAveragingEnabled' => !$evaluation['pass'],
            'displayMode' => $evaluation['pass'] ? 'decimal' : 'band',
        ],
    ];

    if ($persist && $articleResults !== []) {
        ce_hba_determinism_store_result($pdo, $clientId, $evaluation['pass'], $report);
        $settings = ce_hba_determinism_client_settings($pdo, $clientId);
    }

    return [
        'ok' => true,
        'section' => 'determinism',
        'allPass' => $missing === []
            && $guardrail !== null
            && count($articles) >= 1
            && ($articleResults === [] ? false : $evaluation['pass']),
        'checks' => [
            'narrativeComplete' => $missing === [],
            'guardrailActive' => $guardrail !== null,
            'articlesAvailable' => count($articles) >= 1,
            'determinism' => $articleResults === [] ? null : $evaluation['pass'],
        ],
        'client' => [
            'id' => $clientId,
            'name' => (string) ($client['name'] ?? ''),
            'narrativeMissing' => $missing,
            'guardrailActive' => $guardrail !== null,
            'promptVersion' => CE_HBA_PROMPT_VERSION,
            'scoreDisplayMode' => $settings['displayMode'],
            'scoreAveraging' => $settings['averaging'],
            'determinismStatus' => $settings['status'],
        ],
        'config' => $report['config'],
        'evaluation' => $evaluation,
        'articlesPicked' => count($articles),
        'results' => $articleResults,
    ];
}

/** @param array<string, mixed> $a @param array<string, mixed> $b */
function ce_hba_average_normalized_tools(array $a, array $b): array
{
    $merged = $a;
    if (!is_array($merged['scores'] ?? null) || !is_array($b['scores'] ?? null)) {
        return $merged;
    }

    foreach (['brand_contribution', 'brand_consistency', 'architecture_risk'] as $key) {
        $scoreA = is_array($merged['scores'][$key] ?? null) ? ($merged['scores'][$key]['score'] ?? null) : ($merged['scores'][$key] ?? null);
        $scoreB = is_array($b['scores'][$key] ?? null) ? ($b['scores'][$key]['score'] ?? null) : ($b['scores'][$key] ?? null);
        if (!is_numeric($scoreA) || !is_numeric($scoreB)) {
            continue;
        }
        $avg = round(((float) $scoreA + (float) $scoreB) / 2, 1);
        if ($key === 'brand_contribution' && is_array($merged['scores'][$key] ?? null)) {
            $merged['scores'][$key]['score'] = $avg;
            $dims = is_array($merged['scores'][$key]['dimensions'] ?? null) ? $merged['scores'][$key]['dimensions'] : [];
            $dimsB = is_array($b['scores'][$key]['dimensions'] ?? null) ? $b['scores'][$key]['dimensions'] : [];
            foreach ($dims as $dimKey => $dimVal) {
                if (is_numeric($dimVal) && is_numeric($dimsB[$dimKey] ?? null)) {
                    $dims[$dimKey] = round(((float) $dimVal + (float) $dimsB[$dimKey]) / 2, 1);
                }
            }
            $merged['scores'][$key]['dimensions'] = $dims;
        } elseif (is_array($merged['scores'][$key] ?? null)) {
            $merged['scores'][$key]['score'] = $avg;
        } else {
            $merged['scores'][$key] = $avg;
        }
    }

    foreach (['long_term_value', 'short_term_value'] as $key) {
        if (is_numeric($merged['scores'][$key] ?? null) && is_numeric($b['scores'][$key] ?? null)) {
            $merged['scores'][$key] = round(((float) $merged['scores'][$key] + (float) $b['scores'][$key]) / 2, 1);
        }
    }

    if (($merged['contentrol'] ?? null) !== ($b['contentrol'] ?? null)) {
        $merged['contentrol'] = $a['contentrol'] ?? $b['contentrol'] ?? null;
    }

    return $merged;
}

function ce_hba_score_band_label(?float $score): ?string
{
    if ($score === null) {
        return null;
    }
    if ($score <= 4) {
        return 'Laag';
    }
    if ($score < 7) {
        return 'Middel';
    }

    return 'Hoog';
}

function ce_hba_format_score_for_display(?float $score, string $displayMode): ?string
{
    if ($score === null) {
        return null;
    }
    if ($displayMode === 'decimal') {
        return number_format($score, 1, '.', '');
    }

    return ce_hba_score_band_label($score);
}
