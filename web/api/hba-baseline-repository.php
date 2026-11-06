<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-prompt.php';
require_once __DIR__ . '/hba-identity-gate.php';
require_once __DIR__ . '/linkedin-engagement-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/hba-v2-repository.php';

const CE_HBA_BASELINE_MAX_POSTS = 20;

function ce_ensure_hba_baseline_schema(PDO $pdo): void
{
    ce_ensure_hba_schema($pdo);
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $migration = dirname(__DIR__) . '/migrations/add_hba_baseline.sql';
    if (is_readable($migration)) {
        try {
            foreach (preg_split('/;\s*\n/', (string) file_get_contents($migration)) ?: [] as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }
            unset($GLOBALS['ce_table_columns']['hba_scores'], $GLOBALS['ce_table_columns']['clients']);
        } catch (Throwable) {
            // Migratie draait via admin-run-migration indien nodig.
        }
    }
}

/** Plan nulmeting zodra architectuur + guardrail PASS klaar zijn. */
function ce_hba_baseline_schedule(PDO $pdo, string $clientId): void
{
    ce_ensure_hba_baseline_schema($pdo);
    if (ce_hba_active_guardrail($pdo, $clientId) === null) {
        return;
    }
    if (!ce_hba_v2_architecture_ready($pdo, $clientId)) {
        return;
    }

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative || ce_brand_narrative_missing_fields($narrative) !== []) {
        return;
    }

    $narrativeId = (string) ($narrative['id'] ?? '');
    $promptVersion = CE_HBA_PROMPT_VERSION;
    $state = ce_hba_baseline_client_state($pdo, $clientId);

    if (($state['status'] ?? '') === 'running') {
        return;
    }
    if (
        ($state['status'] ?? '') === 'complete'
        && ($state['narrativeId'] ?? '') === $narrativeId
        && ($state['promptVersion'] ?? '') === $promptVersion
    ) {
        return;
    }
    if (($state['status'] ?? '') === 'pending') {
        return;
    }

    if (!ce_hba_baseline_has_linkedin_source($pdo, $clientId)) {
        ce_hba_baseline_set_status($pdo, $clientId, 'skipped', $narrativeId, $promptVersion, 0);
        return;
    }

    ce_hba_baseline_set_status($pdo, $clientId, 'pending', $narrativeId, $promptVersion, null);
}

/** @return array{status: ?string, narrativeId: ?string, promptVersion: ?string, postCount: int, completedAt: ?string} */
function ce_hba_baseline_client_state(PDO $pdo, string $clientId): array
{
    ce_ensure_hba_baseline_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_baseline_status')) {
        return ['status' => null, 'narrativeId' => null, 'promptVersion' => null, 'postCount' => 0, 'completedAt' => null];
    }

    $stmt = $pdo->prepare(
        'SELECT hba_baseline_status, hba_baseline_narrative_id, hba_baseline_prompt_version,
                hba_baseline_post_count, hba_baseline_completed_at
         FROM clients WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch() ?: [];

    return [
        'status' => !empty($row['hba_baseline_status']) ? (string) $row['hba_baseline_status'] : null,
        'narrativeId' => !empty($row['hba_baseline_narrative_id']) ? (string) $row['hba_baseline_narrative_id'] : null,
        'promptVersion' => !empty($row['hba_baseline_prompt_version']) ? (string) $row['hba_baseline_prompt_version'] : null,
        'postCount' => (int) ($row['hba_baseline_post_count'] ?? 0),
        'completedAt' => !empty($row['hba_baseline_completed_at']) ? (string) $row['hba_baseline_completed_at'] : null,
    ];
}

function ce_hba_baseline_has_linkedin_source(PDO $pdo, string $clientId): bool
{
    if (!ce_table_exists($pdo, 'sources')) {
        return false;
    }
    $stmt = $pdo->prepare(
        "SELECT id FROM sources
         WHERE client_id = :client_id
           AND category IN ('linkedin', 'scraper')
           AND scraper_linkedin_profile IS NOT NULL
           AND TRIM(scraper_linkedin_profile) <> ''
         LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId]);

    return (bool) $stmt->fetch();
}

function ce_hba_baseline_set_status(
    PDO $pdo,
    string $clientId,
    string $status,
    ?string $narrativeId,
    ?string $promptVersion,
    ?int $postCount,
): void {
    ce_ensure_hba_baseline_schema($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'hba_baseline_status')) {
        return;
    }

    $completedAt = in_array($status, ['complete', 'skipped', 'failed'], true) ? date('Y-m-d H:i:s') : null;
    $pdo->prepare(
        'UPDATE clients SET
            hba_baseline_status = :status,
            hba_baseline_narrative_id = :narrative_id,
            hba_baseline_prompt_version = :prompt_version,
            hba_baseline_post_count = :post_count,
            hba_baseline_completed_at = CASE
                WHEN :completed_at IS NOT NULL THEN :completed_at
                ELSE hba_baseline_completed_at
            END
         WHERE id = :id'
    )->execute([
        'status' => $status,
        'narrative_id' => $narrativeId,
        'prompt_version' => $promptVersion,
        'post_count' => $postCount,
        'completed_at' => $completedAt,
        'id' => $clientId,
    ]);
}

/** @return array{ok: bool, clientId: string, status: string, scored: int, skipped: int, errors: list<string>} */
function ce_hba_baseline_run_pending(PDO $pdo, ?string $clientId = null, int $maxPosts = CE_HBA_BASELINE_MAX_POSTS): array
{
    ce_ensure_hba_baseline_schema($pdo);
    $clientIds = $clientId !== null && $clientId !== ''
        ? [$clientId]
        : ce_hba_baseline_pending_client_ids($pdo);

    $summary = ['ok' => true, 'clientId' => $clientId ?? '', 'status' => 'none', 'scored' => 0, 'skipped' => 0, 'errors' => []];
    foreach ($clientIds as $id) {
        $result = ce_hba_baseline_run_for_client($pdo, $id, $maxPosts);
        $summary['clientId'] = $id;
        $summary['status'] = (string) ($result['status'] ?? 'failed');
        $summary['scored'] += (int) ($result['scored'] ?? 0);
        $summary['skipped'] += (int) ($result['skipped'] ?? 0);
        if (!empty($result['errors']) && is_array($result['errors'])) {
            $summary['errors'] = array_merge($summary['errors'], $result['errors']);
        }
        if (($result['status'] ?? '') === 'failed') {
            $summary['ok'] = false;
        }
    }

    return $summary;
}

/** @return list<string> */
function ce_hba_baseline_pending_client_ids(PDO $pdo): array
{
    if (!ce_table_has_column($pdo, 'clients', 'hba_baseline_status')) {
        return [];
    }
    $stmt = $pdo->query(
        "SELECT id FROM clients
         WHERE hba_baseline_status = 'pending'
         ORDER BY hba_architecture_completed_at ASC, id ASC
         LIMIT 5"
    );

    $ids = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            $ids[] = $id;
        }
    }

    return $ids;
}

/** @return array{status: string, scored: int, skipped: int, errors: list<string>} */
function ce_hba_baseline_run_for_client(PDO $pdo, string $clientId, int $maxPosts = CE_HBA_BASELINE_MAX_POSTS): array
{
    ce_ensure_hba_baseline_schema($pdo);
    $errors = [];
    $scored = 0;
    $skipped = 0;

    if (ce_hba_active_guardrail($pdo, $clientId) === null) {
        return ['status' => 'skipped', 'scored' => 0, 'skipped' => 0, 'errors' => ['Geen guardrail PASS']];
    }

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative || ce_brand_narrative_missing_fields($narrative) !== []) {
        return ['status' => 'skipped', 'scored' => 0, 'skipped' => 0, 'errors' => ['Narrative incompleet']];
    }

    $narrativeId = (string) ($narrative['id'] ?? '');
    $promptVersion = CE_HBA_PROMPT_VERSION;
    ce_hba_baseline_set_status($pdo, $clientId, 'running', $narrativeId, $promptVersion, null);

    try {
        if (!ce_hba_baseline_has_linkedin_source($pdo, $clientId)) {
            ce_hba_baseline_set_status($pdo, $clientId, 'skipped', $narrativeId, $promptVersion, 0);
            return ['status' => 'skipped', 'scored' => 0, 'skipped' => 0, 'errors' => ['Geen LinkedIn-bron']];
        }

        ce_linkedin_sync_client_sources($pdo, $clientId);
        $posts = ce_hba_baseline_fetch_posts($pdo, $clientId, $maxPosts);
        if ($posts === []) {
            ce_hba_baseline_set_status($pdo, $clientId, 'complete', $narrativeId, $promptVersion, 0);
            ce_hba_baseline_rescore_app_articles($pdo, $clientId, $narrativeId);
            return ['status' => 'complete', 'scored' => 0, 'skipped' => 0, 'errors' => []];
        }

        foreach ($posts as $post) {
            $text = trim((string) ($post['text_preview'] ?? ''));
            if ($text === '') {
                $skipped++;
                continue;
            }
            if (ce_hba_baseline_score_exists($pdo, (string) $post['id'], $narrativeId, $promptVersion)) {
                $skipped++;
                continue;
            }

            try {
                $articleId = ce_hba_baseline_ensure_article($pdo, $clientId, $post);
                ce_hba_score_baseline_post($pdo, $clientId, $articleId, $post, $narrative, $text);
                $scored++;
            } catch (Throwable $e) {
                $errors[] = ((string) ($post['id'] ?? 'post')) . ': ' . $e->getMessage();
                error_log('HBA baseline score failed for client ' . $clientId . ': ' . $e->getMessage());
            }
        }

        ce_hba_baseline_rescore_app_articles($pdo, $clientId, $narrativeId);
        ce_hba_baseline_set_status($pdo, $clientId, 'complete', $narrativeId, $promptVersion, $scored + $skipped);

        return [
            'status' => $errors !== [] && $scored === 0 ? 'failed' : 'complete',
            'scored' => $scored,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    } catch (Throwable $e) {
        ce_hba_baseline_set_status($pdo, $clientId, 'failed', $narrativeId, $promptVersion, $scored);
        error_log('HBA baseline run failed for client ' . $clientId . ': ' . $e->getMessage());

        return ['status' => 'failed', 'scored' => $scored, 'skipped' => $skipped, 'errors' => array_merge($errors, [$e->getMessage()])];
    }
}

/** @return list<array<string, mixed>> */
function ce_hba_baseline_fetch_posts(PDO $pdo, string $clientId, int $limit): array
{
    if (!ce_table_exists($pdo, 'linkedin_posts')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, text_preview, posted_at, post_url, activity_urn
         FROM linkedin_posts
         WHERE client_id = :client_id
           AND text_preview IS NOT NULL
           AND TRIM(text_preview) <> \'\'
         ORDER BY posted_at DESC, scraped_at DESC
         LIMIT ' . max(1, min(CE_HBA_BASELINE_MAX_POSTS, $limit))
    );
    $stmt->execute(['client_id' => $clientId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ce_hba_baseline_score_exists(
    PDO $pdo,
    string $linkedinPostId,
    string $narrativeId,
    string $promptVersion,
): bool {
    if (!ce_table_exists($pdo, 'hba_scores')) {
        return false;
    }
    $originClause = ce_table_has_column($pdo, 'hba_scores', 'origin')
        ? " AND origin = 'baseline'"
        : '';
    $linkedinClause = ce_table_has_column($pdo, 'hba_scores', 'linkedin_post_id')
        ? ' AND linkedin_post_id = :linkedin_post_id'
        : '';
    $promptClause = ' AND prompt_version = :prompt_version';

    $stmt = $pdo->prepare(
        "SELECT id FROM hba_scores
         WHERE brand_narrative_id = :narrative_id{$originClause}{$linkedinClause}{$promptClause}
         LIMIT 1"
    );
    $params = ['narrative_id' => $narrativeId, 'prompt_version' => $promptVersion];
    if ($linkedinClause !== '') {
        $params['linkedin_post_id'] = $linkedinPostId;
    }
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

/** @param array<string, mixed> $post */
function ce_hba_baseline_ensure_article(PDO $pdo, string $clientId, array $post): string
{
    $linkedinPostId = (string) ($post['id'] ?? '');
    if ($linkedinPostId === '') {
        throw new RuntimeException('LinkedIn-post zonder id');
    }

    if (!empty($post['article_id'])) {
        return (string) $post['article_id'];
    }

    $slotId = ce_hba_baseline_ensure_slot($pdo, $clientId);
    $text = trim((string) ($post['text_preview'] ?? ''));
    $headline = ce_hba_baseline_headline_from_text($text);
    $articleId = ce_uuid();
    $postedAt = trim((string) ($post['posted_at'] ?? ''));

    $cols = ['id', 'slot_id', 'headline', 'content_ai', 'status'];
    $vals = [':id', ':slot_id', ':headline', ':content_ai', ':status'];
    $params = [
        'id' => $articleId,
        'slot_id' => $slotId,
        'headline' => $headline,
        'content_ai' => $text,
        'status' => 'archived',
    ];

    if (ce_table_has_column($pdo, 'articles', 'platform_final')) {
        $cols[] = 'platform_final';
        $vals[] = ':platform_final';
        $params['platform_final'] = 'linkedin';
    }
    if (ce_table_has_column($pdo, 'articles', 'published_at') && $postedAt !== '') {
        $cols[] = 'published_at';
        $vals[] = ':published_at';
        $params['published_at'] = $postedAt;
    }
    if (ce_table_has_column($pdo, 'articles', 'social_post_url') && !empty($post['post_url'])) {
        $cols[] = 'social_post_url';
        $vals[] = ':social_post_url';
        $params['social_post_url'] = (string) $post['post_url'];
    }

    $pdo->prepare(
        'INSERT INTO articles (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
    )->execute($params);

    $pdo->prepare('UPDATE linkedin_posts SET article_id = :article_id WHERE id = :id')
        ->execute(['article_id' => $articleId, 'id' => $linkedinPostId]);

    return $articleId;
}

function ce_hba_baseline_ensure_slot(PDO $pdo, string $clientId): string
{
    if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
        $stmt = $pdo->prepare(
            "SELECT id FROM content_slots
             WHERE client_id = :client_id AND origin = 'baseline'
             LIMIT 1"
        );
        $stmt->execute(['client_id' => $clientId]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (string) ($existing['id'] ?? '');
        }
    }

    $slotId = ce_uuid();
    $cols = ['id', 'client_id', 'theme', 'status'];
    $vals = [':id', ':client_id', ':theme', ':status'];
    $params = [
        'id' => $slotId,
        'client_id' => $clientId,
        'theme' => 'Nulmeting LinkedIn',
        'status' => 'archived',
    ];
    if (ce_table_has_column($pdo, 'content_slots', 'phase_key')) {
        $cols[] = 'phase_key';
        $vals[] = ':phase_key';
        $params['phase_key'] = 'baseline';
    }
    if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
        $cols[] = 'origin';
        $vals[] = ':origin';
        $params['origin'] = 'baseline';
    }
    $pdo->prepare(
        'INSERT INTO content_slots (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
    )->execute($params);

    return $slotId;
}

function ce_hba_baseline_headline_from_text(string $text): string
{
    $line = trim((string) preg_replace('/\s+/u', ' ', strtok($text, "\n") ?: $text));
    if (mb_strlen($line) > 120) {
        $line = mb_substr($line, 0, 117) . '…';
    }

    return $line !== '' ? $line : 'LinkedIn nulmeting';
}

/** @param array<string, mixed> $post @param array<string, mixed> $narrativeRow */
function ce_hba_score_baseline_post(
    PDO $pdo,
    string $clientId,
    string $articleId,
    array $post,
    array $narrativeRow,
    string $postText,
): array {
    $narrativeId = (string) ($narrativeRow['id'] ?? '');
    $headline = ce_hba_baseline_headline_from_text($postText);
    $fullText = trim($headline . "\n\n" . $postText);

    require_once __DIR__ . '/hygiene-repository.php';
    $hygieneContext = ce_hygiene_scoring_context(
        $pdo,
        trim((string) ($narrativeRow['contextprofiel'] ?? '')),
    );
    $prompt = ce_hba_build_system_prompt($narrativeRow, 'linkedin', 'article', $fullText, $hygieneContext, $pdo);

    $llm = ce_hba_call_llm($prompt, false);
    $needsReview = false;
    $forbiddenAdviesHits = [];
    if ($llm['ok'] && $llm['tool'] !== null) {
        $forbiddenCheck = ce_hba_score_output_forbidden_hits($llm['tool']);
        if ($forbiddenCheck['hits'] !== []) {
            $retry = ce_hba_call_llm($prompt, true);
            if ($retry['ok'] && $retry['tool'] !== null) {
                $llm = $retry;
                $forbiddenCheck = ce_hba_score_output_forbidden_hits($llm['tool']);
            }
            if ($forbiddenCheck['hits'] !== []) {
                $needsReview = true;
            }
        }
        $forbiddenAdviesHits = $forbiddenCheck['adviesHits'];
    }

    if (!$llm['ok'] || $llm['tool'] === null) {
        throw new RuntimeException($llm['error'] !== '' ? $llm['error'] : 'HBA baseline scoring mislukt');
    }

    $tool = ce_hba_normalize_score_tool($llm['tool']);
    $finalize = ce_hba_finalize_scored_tool(
        $tool,
        $fullText,
        (string) ($narrativeRow['geloofwaardigheidstest'] ?? ''),
        ce_hba_format_list($narrativeRow['verboden_logica'] ?? []),
        $headline,
    );
    $tool = $finalize['tool'];
    $postFilterForbidden = ce_hba_score_output_forbidden_hits($tool);
    if ($postFilterForbidden['adviesHits'] !== []) {
        $forbiddenAdviesHits = $postFilterForbidden['adviesHits'];
        $needsReview = true;
    }
    $rawForStore = is_array($llm['raw']) ? $llm['raw'] : [];
    if ($finalize['flags_verwijderd'] !== []) {
        $rawForStore['flags_verwijderd'] = $finalize['flags_verwijderd'];
    }
    if (isset($tool['hoofdoordeel'])) {
        $rawForStore['hoofdoordeel'] = $tool['hoofdoordeel'];
    }
    unset($rawForStore['hoofdoordeel_gecorrigeerd']);
    $rawForStore['hoofdoordeel_volgt_uit_flags'] = (bool) ($finalize['hoofdoordeel_volgt_uit_flags'] ?? true);
    $rawForStore['hoofdoordeel_context'] = [
        'foe_on_kern' => (bool) ($finalize['foe_on_kern'] ?? false),
    ];
    if (isset($tool['merkbewering'])) {
        $rawForStore['merkbewering'] = $tool['merkbewering'];
    }
    if (array_key_exists('kern_categorietaal', $tool)) {
        $rawForStore['kern_categorietaal'] = $tool['kern_categorietaal'];
    }
    if ($finalize['verificatie'] !== null) {
        $rawForStore['constraint_breach_verificatie'] = $finalize['verificatie'];
    }
    if (($finalize['brand_contribution_kernbewering_cap'] ?? null) !== null) {
        $rawForStore['brand_contribution_kernbewering_cap'] = $finalize['brand_contribution_kernbewering_cap'];
    }
    if (($finalize['brand_contribution_proof_gap_early_cap'] ?? null) !== null) {
        $rawForStore['brand_contribution_proof_gap_early_cap'] = $finalize['brand_contribution_proof_gap_early_cap'];
    }
    if ($forbiddenAdviesHits !== []) {
        $rawForStore['verbeteradvies_verboden_termen'] = $forbiddenAdviesHits;
    }
    $flagCitaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    if ($flagCitaten !== []) {
        $rawForStore['flag_citaten'] = $flagCitaten;
    }

    $narrativeVersion = 1;
    if (ce_table_exists($pdo, 'brand_narratives')) {
        $verStmt = $pdo->prepare('SELECT version FROM brand_narratives WHERE id = :id LIMIT 1');
        $verStmt->execute(['id' => $narrativeId]);
        $verRow = $verStmt->fetch();
        $narrativeVersion = max(1, (int) ($verRow['version'] ?? 1));
    }

    return ce_hba_insert_score(
        $pdo,
        $articleId,
        $narrativeId,
        $tool,
        $rawForStore,
        $llm['input_tokens'],
        $llm['output_tokens'],
        $needsReview,
        isset($llm['model']) ? (string) $llm['model'] : null,
        is_array($hygieneContext) ? ($hygieneContext['version'] ?? null) : null,
        'baseline',
        (string) ($post['id'] ?? ''),
        $narrativeVersion,
    );
}

function ce_hba_baseline_rescore_app_articles(PDO $pdo, string $clientId, string $narrativeId): void
{
    if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'hba_scores')) {
        return;
    }

    $originFilter = ce_table_has_column($pdo, 'hba_scores', 'origin')
        ? " AND COALESCE(hs.origin, 'app') = 'app'"
        : '';

    $stmt = $pdo->prepare(
        "SELECT a.id
         FROM articles a
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         LEFT JOIN hba_scores hs ON hs.post_id = a.id AND hs.brand_narrative_id = :narrative_id{$originFilter}
         WHERE cs.client_id = :client_id
           AND COALESCE(cs.origin, '') <> 'baseline'
           AND a.status IN ('published', 'archived')
           AND hs.id IS NULL
         ORDER BY COALESCE(a.published_at, a.id) DESC
         LIMIT 20"
    );
    $stmt->execute(['client_id' => $clientId, 'narrative_id' => $narrativeId]);

    foreach ($stmt->fetchAll() ?: [] as $row) {
        $articleId = trim((string) ($row['id'] ?? ''));
        if ($articleId === '') {
            continue;
        }
        try {
            ce_hba_score_article($pdo, ['article_id' => $articleId, 'force' => false]);
        } catch (Throwable $e) {
            error_log('HBA app rescore after baseline failed for ' . $articleId . ': ' . $e->getMessage());
        }
    }
}

function ce_hba_baseline_count_scored(PDO $pdo, string $clientId): int
{
    if (!ce_table_exists($pdo, 'hba_scores') || !ce_table_has_column($pdo, 'hba_scores', 'origin')) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cnt
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND hs.origin = 'baseline'"
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();

    return (int) ($row['cnt'] ?? 0);
}

function ce_hba_baseline_progress_payload(PDO $pdo, string $clientId, int $totalScoredPosts): array
{
    $state = ce_hba_baseline_client_state($pdo, $clientId);
    $baselineScored = ce_hba_baseline_count_scored($pdo, $clientId);
    $preliminary = $totalScoredPosts > 0 && $totalScoredPosts < 20;

    $message = null;
    $status = $state['status'];
    if ($status === 'pending' || $status === 'running') {
        $message = 'Nulmeting van eerdere LinkedIn-posts wordt uitgevoerd.';
    } elseif ($baselineScored > 0 && $preliminary) {
        $message = sprintf(
            'Nulmeting: %d eerdere LinkedIn-post%s gescoord — nog geen stabiel gemiddelde (minimaal 20).',
            $baselineScored,
            $baselineScored === 1 ? '' : 's',
        );
    } elseif ($status === 'skipped') {
        $message = 'Nulmeting overgeslagen — koppel een LinkedIn-profiel onder Sources.';
    }

    return [
        'baselineStatus' => $status,
        'baselineScoredPosts' => $baselineScored,
        'baselinePreliminary' => $preliminary,
        'baselineMessage' => $message,
    ];
}

function ce_hba_baseline_invalidate_on_prompt_change(PDO $pdo, string $clientId): void
{
    if (!ce_table_has_column($pdo, 'clients', 'hba_baseline_prompt_version')) {
        return;
    }
    $state = ce_hba_baseline_client_state($pdo, $clientId);
    if (($state['promptVersion'] ?? '') === CE_HBA_PROMPT_VERSION) {
        return;
    }
    if (($state['status'] ?? '') === 'running') {
        return;
    }

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    $narrativeId = is_array($narrative) ? (string) ($narrative['id'] ?? '') : '';
    ce_hba_baseline_set_status($pdo, $clientId, 'pending', $narrativeId !== '' ? $narrativeId : null, CE_HBA_PROMPT_VERSION, null);
}
