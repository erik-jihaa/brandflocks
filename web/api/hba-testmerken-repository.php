<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/linkedin-engagement-repository.php';
require_once __DIR__ . '/hba-baseline-repository.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-identity-gate.php';
require_once __DIR__ . '/hba-branche-seed.php';
require_once __DIR__ . '/hba-architectuur-check-repository.php';
require_once __DIR__ . '/hba-contentflow-schema.php';

const CE_HBA_TESTPOST_SLOT_LABELS = [
    'Op merk',
    'Categorietaal',
    'Breekt de regel',
    'Neutraal / grensgeval',
];

const CE_HBA_TESTPOST_ACTIVITY_PREFIX = 'hba-test-';

function ce_hba_testpost_platform_db(string $platform): string
{
    $key = strtolower(trim($platform));
    if ($key === 'website_blog' || $key === 'newsletter') {
        return 'blog';
    }
    if (in_array($key, ['linkedin', 'instagram', 'facebook', 'x', 'blog'], true)) {
        return $key;
    }

    return 'blog';
}

function ce_hba_ensure_content_slots_origin_varchar(PDO $pdo): void
{
    if (!ce_table_has_column($pdo, 'content_slots', 'origin')) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("ALTER TABLE content_slots MODIFY COLUMN origin VARCHAR(16) NOT NULL DEFAULT 'normal'");
        unset($GLOBALS['ce_table_columns']['content_slots']);
    } catch (Throwable) {
        // Migratie via run-migration indien ALTER hier faalt.
    }
}

/** Synthetische LinkedIn-bron zodat nulmeting/baseline de testposts oppakt. */
function ce_hba_ensure_testmerk_linkedin_source(PDO $pdo, string $clientId, string $companyName): string
{
    ce_ensure_linkedin_posts_schema($pdo);
    if (!ce_table_exists($pdo, 'sources')) {
        throw new RuntimeException('sources-tabel ontbreekt');
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyName) ?? '');
    $slug = trim($slug, '-') ?: 'hba-testmerk';
    $profile = 'hba-test/' . $slug;

    $stmt = $pdo->prepare(
        "SELECT id FROM sources
         WHERE client_id = :client_id
           AND category IN ('linkedin', 'scraper')
           AND scraper_linkedin_profile = :profile
         LIMIT 1"
    );
    $stmt->execute(['client_id' => $clientId, 'profile' => $profile]);
    $existing = $stmt->fetchColumn();
    if (is_string($existing) && $existing !== '') {
        return $existing;
    }

    $sourceId = ce_uuid_v4();
    $cols = ['id', 'client_id', 'category', 'source_kind', 'title', 'active'];
    $vals = [':id', ':client_id', ':category', ':source_kind', ':title', ':active'];
    $params = [
        'id' => $sourceId,
        'client_id' => $clientId,
        'category' => 'linkedin',
        'source_kind' => 'linkedin_profile',
        'title' => 'HBA-testposts (nulmeting)',
        'active' => 1,
    ];
    if (ce_table_has_column($pdo, 'sources', 'scraper_linkedin_profile')) {
        $cols[] = 'scraper_linkedin_profile';
        $vals[] = ':profile';
        $params['profile'] = $profile;
    }
    if (ce_table_has_column($pdo, 'sources', 'description')) {
        $cols[] = 'description';
        $vals[] = ':description';
        $params['description'] = 'Synthetische bron voor HBA-testposts; geen echte LinkedIn-scrape.';
    }

    $pdo->prepare(
        'INSERT INTO sources (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
    )->execute($params);

    return $sourceId;
}

/** @return array{code: string, label: string} */
/** @return array<string, mixed> */
function ce_hba_bundle_merk_meta(array $bundle): array
{
    if (is_array($bundle['testmerk'] ?? null)) {
        return $bundle['testmerk'];
    }
    if (is_array($bundle['referentiemerk'] ?? null)) {
        return $bundle['referentiemerk'];
    }

    return [];
}

function ce_hba_bundle_merk_name(array $bundle): string
{
    return trim((string) (ce_hba_bundle_merk_meta($bundle)['merknaam'] ?? ''));
}

/**
 * Zet gecureerde markthygiëne in branche_library (referentiemerk / kalibratie).
 *
 * @param array<string, mixed> $markthygiene
 * @return array{code: string, label: string, id: string}
 */
function ce_hba_upsert_markthygiene_branche(PDO $pdo, array $markthygiene): array
{
    ce_hba_seed_branche_library($pdo);
    ce_ensure_hba_contentflow_schema($pdo);

    $code = trim((string) ($markthygiene['branche_id'] ?? $markthygiene['branche_code'] ?? ''));
    if ($code === '') {
        throw new InvalidArgumentException('markthygiene.branche_id ontbreekt');
    }
    $label = trim((string) ($markthygiene['branche_naam'] ?? $code));

    $marktRaw = $markthygiene['markt_archetype'] ?? '';
    $markt = is_array($marktRaw)
        ? trim((string) ($marktRaw['archetype'] ?? ''))
        : trim((string) $marktRaw);

    $hyg = $markthygiene['hygienefactoren'] ?? [];
    $cat = $markthygiene['categorietaal'] ?? [];
    $diff = $markthygiene['differentiatie_assen'] ?? [];
    $bronnen = $markthygiene['bronnen'] ?? [];
    $herkomst = trim((string) ($markthygiene['herkomst'] ?? 'gecureerd'));

    $existing = ce_hba_fetch_branche_by_code($pdo, $code);
    $params = [
        'code' => $code,
        'label' => $label,
        'markt' => $markt !== '' ? $markt : 'Maker',
        'hyg' => json_encode(is_array($hyg) ? $hyg : [], JSON_UNESCAPED_UNICODE),
        'cat' => json_encode(is_array($cat) ? $cat : [], JSON_UNESCAPED_UNICODE),
        'bron' => $herkomst !== '' ? $herkomst : 'gecureerd',
        'branche_definitie' => $label,
        'differentiatie_assen' => json_encode(is_array($diff) ? $diff : [], JSON_UNESCAPED_UNICODE),
        'bronnen_kwaliteit' => is_array($bronnen) && $bronnen !== [] ? 'bronnen in JSON' : 'mager',
    ];

    if ($existing !== null) {
        $sets = [
            'branche_label = :label',
            'markt_archetype = :markt',
            'hygienefactoren = :hyg',
            'categorietaal = :cat',
            'bron = :bron',
        ];
        if (ce_table_has_column($pdo, 'branche_library', 'branche_definitie')) {
            $sets[] = 'branche_definitie = :branche_definitie';
        }
        if (ce_table_has_column($pdo, 'branche_library', 'differentiatie_assen')) {
            $sets[] = 'differentiatie_assen = :differentiatie_assen';
        }
        if (ce_table_has_column($pdo, 'branche_library', 'bronnen_kwaliteit')) {
            $sets[] = 'bronnen_kwaliteit = :bronnen_kwaliteit';
        }
        if (ce_table_has_column($pdo, 'branche_library', 'confidence_total')) {
            $sets[] = 'confidence_total = 9';
        }
        if (ce_table_has_column($pdo, 'branche_library', 'status')) {
            $sets[] = "status = 'active'";
        }
        $pdo->prepare(
            'UPDATE branche_library SET ' . implode(', ', $sets) . ' WHERE branche_code = :code LIMIT 1'
        )->execute($params);
        $row = ce_hba_fetch_branche_by_code($pdo, $code);

        return [
            'code' => $code,
            'label' => $label,
            'id' => is_array($row) ? (string) ($row['id'] ?? '') : (string) ($existing['id'] ?? ''),
        ];
    }

    $id = ce_uuid();
    $cols = ['id', 'branche_code', 'branche_label', 'markt_archetype', 'hygienefactoren', 'categorietaal', 'bron'];
    $vals = [':id', ':code', ':label', ':markt', ':hyg', ':cat', ':bron'];
    $insertParams = array_merge(['id' => $id], $params);
    if (ce_table_has_column($pdo, 'branche_library', 'branche_definitie')) {
        $cols[] = 'branche_definitie';
        $vals[] = ':branche_definitie';
    }
    if (ce_table_has_column($pdo, 'branche_library', 'differentiatie_assen')) {
        $cols[] = 'differentiatie_assen';
        $vals[] = ':differentiatie_assen';
    }
    if (ce_table_has_column($pdo, 'branche_library', 'bronnen_kwaliteit')) {
        $cols[] = 'bronnen_kwaliteit';
        $vals[] = ':bronnen_kwaliteit';
    }
    if (ce_table_has_column($pdo, 'branche_library', 'confidence_total')) {
        $cols[] = 'confidence_total';
        $vals[] = '9';
    }
    if (ce_table_has_column($pdo, 'branche_library', 'status')) {
        $cols[] = 'status';
        $vals[] = "'active'";
    }
    $pdo->prepare(
        'INSERT INTO branche_library (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')'
    )->execute($insertParams);

    return ['code' => $code, 'label' => $label, 'id' => $id];
}

function ce_hba_testmerk_branche_for_company(string $companyName): array
{
    $name = mb_strtolower(trim($companyName));
    if (str_contains($name, 'duinroos') || str_contains($name, 'thuiszorg') || str_contains($name, 'zorg')) {
        return ['code' => 'zorg_welzijn', 'label' => 'Zorg / welzijn'];
    }
    if (str_contains($name, 'stelwerk') || str_contains($name, 'erp')) {
        return ['code' => 'zakelijke_dienstverlening', 'label' => 'Zakelijke dienstverlening / consultancy'];
    }
    if (str_contains($name, 'brandpunt') || str_contains($name, 'koffie')) {
        return ['code' => 'koffie', 'label' => 'Koffiebranderij / specialty coffee'];
    }

    return ['code' => 'retail', 'label' => 'Retail / consumentenmerken'];
}

/**
 * Zet branche_code + bruikbaar marktframe, en forceer PASS-guardrail als de
 * architectuurcheck (zonder branche) zou blijven falen — alleen voor HBA-testmerken.
 *
 * @return array{brancheCode: string, guardrailForced: bool, guardrailActive: bool}
 */
function ce_hba_ensure_testmerk_scoring_gate(
    PDO $pdo,
    string $clientId,
    string $companyName,
    ?string $brancheCodeOverride = null,
): array {
    ce_hba_seed_branche_library($pdo);
    ce_ensure_hba_contentflow_schema($pdo);

    if ($brancheCodeOverride !== null && trim($brancheCodeOverride) !== '') {
        $code = trim($brancheCodeOverride);
        $branche = ce_hba_fetch_branche_by_code($pdo, $code);
        $map = [
            'code' => $code,
            'label' => is_array($branche) ? (string) ($branche['branche_label'] ?? $code) : $code,
        ];
    } else {
        $map = ce_hba_testmerk_branche_for_company($companyName);
        $code = $map['code'];
    }
    $branche = ce_hba_fetch_branche_by_code($pdo, $code);
    if ($branche === null) {
        // Seed mislukte of code ontbreekt — maak minimale bruikbare rij.
        $id = ce_uuid();
        $pdo->prepare(
            'INSERT INTO branche_library (
                id, branche_code, branche_label, markt_archetype, hygienefactoren, categorietaal, bron,
                confidence_total, confidence_dimensions, status
             ) VALUES (
                :id, :code, :label, :markt, :hyg, :cat, :bron, 9, :dims, :status
             )'
        )->execute([
            'id' => $id,
            'code' => $code,
            'label' => $map['label'],
            'markt' => 'Maker',
            'hyg' => '[]',
            'cat' => '[]',
            'bron' => 'gecureerd',
            'dims' => json_encode(['marktframe' => 3, 'hygiene' => 2, 'categorietaal' => 2, 'bronnen' => 2], JSON_UNESCAPED_UNICODE),
            'status' => 'active',
        ]);
        $branche = ce_hba_fetch_branche_by_code($pdo, $code);
    } else {
        $use = ce_hba_branch_confidence_use($branche);
        if ($use['blocked'] || !$use['usable']) {
            $sets = [];
            $params = ['code' => $code];
            if (ce_table_has_column($pdo, 'branche_library', 'confidence_total')) {
                $sets[] = 'confidence_total = :confidence_total';
                $params['confidence_total'] = 9;
            }
            if (ce_table_has_column($pdo, 'branche_library', 'confidence_dimensions')) {
                $sets[] = 'confidence_dimensions = :confidence_dimensions';
                $params['confidence_dimensions'] = json_encode(
                    ['marktframe' => 3, 'hygiene' => 2, 'categorietaal' => 2, 'bronnen' => 2],
                    JSON_UNESCAPED_UNICODE,
                );
            }
            if (ce_table_has_column($pdo, 'branche_library', 'status')) {
                $sets[] = "status = 'active'";
            }
            if ($sets !== []) {
                $pdo->prepare(
                    'UPDATE branche_library SET ' . implode(', ', $sets) . ' WHERE branche_code = :code LIMIT 1'
                )->execute($params);
                $branche = ce_hba_fetch_branche_by_code($pdo, $code);
            }
        }
    }

    if (ce_table_has_column($pdo, 'brand_narratives', 'branche_code')) {
        $pdo->prepare(
            'UPDATE brand_narratives SET branche_code = :code
             WHERE client_id = :client_id AND is_current = 1'
        )->execute(['code' => $code, 'client_id' => $clientId]);
    }

    // Testmerken: forceer PASS-guardrail zodat nulmeting/scoring niet wacht op
    // een losse architectuurcheck (marktframe ontbrak bij provision).
    $forced = false;
    if (ce_hba_active_guardrail($pdo, $clientId) === null) {
        $narrative = ce_fetch_current_brand_narrative($pdo, $clientId) ?: [];
        $regels = [];
        if (trim((string) ($narrative['geloofwaardigheidstest'] ?? '')) !== '') {
            $regels[] = (string) $narrative['geloofwaardigheidstest'];
        }
        $payload = ce_hba_build_guardrail_object(
            [
                'archetype' => (string) ($narrative['archetype'] ?? ''),
                'doel' => (string) ($narrative['doel'] ?? ''),
                'foe' => (string) ($narrative['foe'] ?? ''),
                'wat_bewust_niet' => (string) ($narrative['wat_bewust_niet'] ?? ''),
                'regels' => $regels,
            ],
            $branche,
            ['total_score' => 18],
            1,
        );
        ce_hba_store_guardrail(
            $pdo,
            $clientId,
            $payload,
            'PASS',
            18,
            is_array($branche) ? ((string) ($branche['id'] ?? '') !== '' ? (string) $branche['id'] : null) : null,
            is_array($branche) ? (int) ($branche['version'] ?? 1) : null,
            1,
        );
        $forced = true;
    }

    return [
        'brancheCode' => $code,
        'guardrailForced' => $forced,
        'guardrailActive' => ce_hba_active_guardrail($pdo, $clientId) !== null,
    ];
}

/** @return array{slots: list<string>, linkedinPosts: int, scores: int} */
function ce_hba_delete_testposts_for_client(PDO $pdo, string $clientId): array
{
    $slotIds = [];

    $themeStmt = $pdo->prepare(
        "SELECT id FROM content_slots
         WHERE client_id = :client_id
           AND (theme LIKE 'HBA-test:%' OR phase_key = 'hba_test')"
    );
    $themeStmt->execute(['client_id' => $clientId]);
    $slotIds = array_merge($slotIds, array_column($themeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id'));

    $slotIds = array_values(array_unique(array_filter(array_map('strval', $slotIds))));
    $articleIds = [];
    foreach ($slotIds as $slotId) {
        $aStmt = $pdo->prepare('SELECT id FROM articles WHERE slot_id = :slot_id');
        $aStmt->execute(['slot_id' => $slotId]);
        foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $articleIds[] = (string) ($row['id'] ?? '');
        }
    }
    $articleIds = array_values(array_filter($articleIds));

    $scoresDeleted = 0;
    if ($articleIds !== [] && ce_table_exists($pdo, 'hba_scores')) {
        $ph = implode(',', array_fill(0, count($articleIds), '?'));
        $scoresDeleted = $pdo->prepare("DELETE FROM hba_scores WHERE post_id IN ($ph)")->execute($articleIds)
            ? count($articleIds)
            : 0;
    }

    foreach ($slotIds as $slotId) {
        $pdo->prepare('DELETE FROM articles WHERE slot_id = :slot_id')->execute(['slot_id' => $slotId]);
        if (ce_table_exists($pdo, 'research_items')) {
            $pdo->prepare('DELETE FROM research_items WHERE slot_id = :slot_id')->execute(['slot_id' => $slotId]);
        }
        $pdo->prepare('DELETE FROM content_slots WHERE id = :id')->execute(['id' => $slotId]);
    }

    $linkedinDeleted = 0;
    if (ce_table_exists($pdo, 'linkedin_posts')) {
        $del = $pdo->prepare(
            'DELETE FROM linkedin_posts
             WHERE client_id = :client_id AND activity_urn LIKE :prefix'
        );
        $del->execute([
            'client_id' => $clientId,
            'prefix' => CE_HBA_TESTPOST_ACTIVITY_PREFIX . '%',
        ]);
        $linkedinDeleted = $del->rowCount();
    }

    return [
        'slots' => $slotIds,
        'linkedinPosts' => $linkedinDeleted,
        'scores' => $scoresDeleted,
    ];
}

/**
 * @param list<array<string, mixed>> $testposts
 * @return array<string, mixed>
 */
function ce_hba_import_testposts_for_client(PDO $pdo, string $clientId, array $testposts, string $companyName = '', bool $score = true): array
{
    $clientId = trim($clientId);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        throw new InvalidArgumentException('Ongeldig clientId');
    }

    $stmt = $pdo->prepare('SELECT id, name FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        throw new InvalidArgumentException('Client niet gevonden');
    }
    if ($companyName === '') {
        $companyName = trim((string) ($client['name'] ?? 'HBA-testmerk'));
    }

    if (!ce_table_exists($pdo, 'content_slots') || !ce_table_exists($pdo, 'articles')) {
        throw new RuntimeException('content_slots/articles ontbreekt');
    }

    ce_ensure_slot_pipeline_statuses($pdo);
    ce_ensure_headline_human_column($pdo);
    ce_ensure_content_human_column($pdo);
    ce_ensure_hba_baseline_schema($pdo);
    ce_hba_ensure_content_slots_origin_varchar($pdo);

    $removed = ce_hba_delete_testposts_for_client($pdo, $clientId);
    $sourceId = ce_hba_ensure_testmerk_linkedin_source($pdo, $clientId, $companyName);
    $narrativeForGate = ce_fetch_current_brand_narrative($pdo, $clientId);
    $brancheOverride = is_array($narrativeForGate)
        ? trim((string) ($narrativeForGate['branche_code'] ?? ''))
        : '';
    $gate = ce_hba_ensure_testmerk_scoring_gate(
        $pdo,
        $clientId,
        $companyName,
        $brancheOverride !== '' ? $brancheOverride : null,
    );
    $guardrailActive = !empty($gate['guardrailActive']);

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    $narrativeId = is_array($narrative) ? (string) ($narrative['id'] ?? '') : '';
    $canScore = $guardrailActive
        && is_array($narrative)
        && ce_brand_narrative_missing_fields($narrative) === [];

    $imported = [];
    $scored = 0;
    $scoreErrors = [];
    $baseTime = time();

    foreach (array_values($testposts) as $index => $post) {
        if (!is_array($post)) {
            continue;
        }
        $text = trim((string) ($post['post_tekst'] ?? ''));
        if ($text === '') {
            continue;
        }

        $label = trim((string) ($post['label'] ?? ''));
        if ($label === '') {
            $label = CE_HBA_TESTPOST_SLOT_LABELS[$index] ?? ('Test ' . ($index + 1));
        }
        $verwacht = trim((string) ($post['verwacht'] ?? ''));
        $parsedVerwacht = ce_hba_apply_explicit_testpost_fields(
            ce_hba_parse_verwacht_string($verwacht, $index),
            $post,
        );
        $platform = ce_hba_testpost_platform_db((string) ($post['platform'] ?? 'linkedin'));
        $headline = 'HBA-test: ' . $label;
        // Kop = label; body = volledige posttekst (niet opnieuw de headline).
        $body = $text;

        $slotId = ce_uuid_v4();
        $linkedinPostId = ce_uuid_v4();
        $articleId = ce_uuid_v4();
        $activityUrn = CE_HBA_TESTPOST_ACTIVITY_PREFIX . substr(md5($clientId . ':' . ($index + 1)), 0, 16);
        $postedAt = date('Y-m-d H:i:s', $baseTime - (($index + 1) * 86400));
        $slotMarker = json_encode(array_merge([
            'hbaTestpost' => true,
            'nulmeting' => true,
            'index' => $index + 1,
            'label' => $label,
            'verwacht' => $verwacht,
            'platform' => $platform,
        ], $parsedVerwacht), JSON_THROW_ON_ERROR);

        $slotCols = ['id', 'client_id', 'theme', 'status'];
        $slotVals = [':id', ':client_id', ':theme', ':status'];
        $slotParams = [
            'id' => $slotId,
            'client_id' => $clientId,
            'theme' => $headline,
            'status' => ce_db_status_for_ui($pdo, 'content_slots', 'archived'),
        ];
        if (ce_table_has_column($pdo, 'content_slots', 'phase_key')) {
            $slotCols[] = 'phase_key';
            $slotVals[] = ':phase_key';
            $slotParams['phase_key'] = 'hba_test';
        }
        if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
            $slotCols[] = 'origin';
            $slotVals[] = ':origin';
            $slotParams['origin'] = 'baseline';
        }
        if (ce_table_has_column($pdo, 'content_slots', 'input_human')) {
            $slotCols[] = 'input_human';
            $slotVals[] = ':input_human';
            $slotParams['input_human'] = $slotMarker;
        }
        $pdo->prepare(
            'INSERT INTO content_slots (' . implode(', ', $slotCols) . ') VALUES (' . implode(', ', $slotVals) . ')'
        )->execute($slotParams);

        $pdo->prepare(
            'INSERT INTO linkedin_posts (
                id, client_id, source_id, activity_urn, post_url, posted_at, text_preview,
                reactions, likes, comments, reposts, article_id
             ) VALUES (
                :id, :client_id, :source_id, :activity_urn, NULL, :posted_at, :text_preview,
                0, 0, 0, 0, :article_id
             )'
        )->execute([
            'id' => $linkedinPostId,
            'client_id' => $clientId,
            'source_id' => $sourceId,
            'activity_urn' => $activityUrn,
            'posted_at' => $postedAt,
            'text_preview' => $text,
            'article_id' => $articleId,
        ]);

        $artCols = ['id', 'slot_id', 'headline', 'content_ai', 'status'];
        $artVals = [':id', ':slot_id', ':headline', ':content_ai', ':status'];
        $artParams = [
            'id' => $articleId,
            'slot_id' => $slotId,
            'headline' => $headline,
            'content_ai' => $body,
            'status' => ce_db_status_for_ui($pdo, 'articles', 'archived'),
        ];
        if (ce_table_has_column($pdo, 'articles', 'headline_human')) {
            $artCols[] = 'headline_human';
            $artVals[] = ':headline_human';
            $artParams['headline_human'] = $headline;
        }
        if (ce_table_has_column($pdo, 'articles', 'content_human')) {
            $artCols[] = 'content_human';
            $artVals[] = ':content_human';
            $artParams['content_human'] = $body;
        }
        if (ce_table_has_column($pdo, 'articles', 'platform_final')) {
            $artCols[] = 'platform_final';
            $artVals[] = ':platform_final';
            $artParams['platform_final'] = $platform === 'blog' ? 'linkedin' : $platform;
        }
        if (ce_table_has_column($pdo, 'articles', 'platform_suggested')) {
            $artCols[] = 'platform_suggested';
            $artVals[] = ':platform_suggested';
            $artParams['platform_suggested'] = $platform === 'blog' ? 'linkedin' : $platform;
        }
        if (ce_table_has_column($pdo, 'articles', 'published_at')) {
            $artCols[] = 'published_at';
            $artVals[] = ':published_at';
            $artParams['published_at'] = $postedAt;
        }
        if (ce_table_has_column($pdo, 'articles', 'archive_note') && $verwacht !== '') {
            $artCols[] = 'archive_note';
            $artVals[] = ':archive_note';
            $artParams['archive_note'] = 'Verwacht: ' . $verwacht;
        }

        $pdo->prepare(
            'INSERT INTO articles (' . implode(', ', $artCols) . ') VALUES (' . implode(', ', $artVals) . ')'
        )->execute($artParams);

        $scoreOk = false;
        if ($canScore && $score) {
            try {
                ce_hba_score_baseline_post(
                    $pdo,
                    $clientId,
                    $articleId,
                    ['id' => $linkedinPostId, 'posted_at' => $postedAt],
                    $narrative,
                    $text,
                );
                $scored++;
                $scoreOk = true;
            } catch (Throwable $e) {
                $scoreErrors[] = $label . ': ' . $e->getMessage();
            }
        }

        $imported[] = [
            'slotId' => $slotId,
            'articleId' => $articleId,
            'linkedinPostId' => $linkedinPostId,
            'label' => $label,
            'platform' => $platform,
            'verwacht' => $verwacht,
            'scored' => $scoreOk,
            'textPreview' => mb_substr($text, 0, 80),
        ];
    }

    if ($canScore && $score) {
        ce_hba_baseline_set_status(
            $pdo,
            $clientId,
            $scoreErrors !== [] && $scored === 0 ? 'failed' : 'complete',
            $narrativeId !== '' ? $narrativeId : null,
            CE_HBA_PROMPT_VERSION,
            count($imported),
        );
    } elseif ($narrativeId !== '') {
        ce_hba_baseline_set_status($pdo, $clientId, 'pending', $narrativeId, CE_HBA_PROMPT_VERSION, null);
    }

    return [
        'ok' => true,
        'clientId' => $clientId,
        'company' => $companyName,
        'sourceId' => $sourceId,
        'removed' => $removed,
        'importedCount' => count($imported),
        'scoredCount' => $scored,
        'guardrailActive' => $guardrailActive,
        'guardrailForced' => !empty($gate['guardrailForced']),
        'brancheCode' => (string) ($gate['brancheCode'] ?? ''),
        'scoreErrors' => $scoreErrors,
        'posts' => $imported,
    ];
}

/** @param array<string, mixed> $bundle */
function ce_hba_import_testposts_from_bundle(PDO $pdo, array $bundle, ?string $clientId = null): array
{
    $name = ce_hba_bundle_merk_name($bundle);
    if ($clientId === null || trim($clientId) === '') {
        if ($name === '') {
            throw new InvalidArgumentException('clientId of merknaam (testmerk/referentiemerk) verplicht');
        }
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE TRIM(name) = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new InvalidArgumentException('Geen client met naam: ' . $name);
        }
        $clientId = (string) ($row['id'] ?? '');
    }

    if (isset($bundle['scoreIndex'])) {
        return ce_hba_score_testpost_for_client($pdo, $clientId, (int) $bundle['scoreIndex']);
    }

    $testposts = $bundle['testposts'] ?? [];
    if (!is_array($testposts) || $testposts === []) {
        throw new InvalidArgumentException('testposts ontbreekt in bundle');
    }

    $score = !filter_var($bundle['deferScore'] ?? false, FILTER_VALIDATE_BOOLEAN);

    return ce_hba_import_testposts_for_client($pdo, $clientId, $testposts, $name, $score);
}

/** Scoort één geïmporteerde testpost (1-based index) — houdt elk verzoek onder de server-timeout. */
function ce_hba_score_testpost_for_client(PDO $pdo, string $clientId, int $index): array
{
    if ($index < 1) {
        throw new InvalidArgumentException('scoreIndex is 1-based');
    }
    $activityUrn = CE_HBA_TESTPOST_ACTIVITY_PREFIX . substr(md5($clientId . ':' . $index), 0, 16);
    $stmt = $pdo->prepare(
        'SELECT id, article_id, posted_at, text_preview FROM linkedin_posts
         WHERE client_id = :client_id AND activity_urn = :urn LIMIT 1'
    );
    $stmt->execute(['client_id' => $clientId, 'urn' => $activityUrn]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        throw new InvalidArgumentException('Testpost ' . $index . ' niet gevonden — eerst importeren');
    }
    if (ce_hba_active_guardrail($pdo, $clientId) === null) {
        throw new RuntimeException('Geen actieve meetlat — importeer opnieuw om de scoring-gate te zetten');
    }
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!is_array($narrative) || ce_brand_narrative_missing_fields($narrative) !== []) {
        throw new RuntimeException('Merkverhaal onvolledig — kan niet scoren');
    }

    $pdo->prepare('DELETE FROM hba_scores WHERE post_id = :post_id')
        ->execute(['post_id' => (string) $post['article_id']]);
    ce_hba_score_baseline_post(
        $pdo,
        $clientId,
        (string) $post['article_id'],
        ['id' => (string) $post['id'], 'posted_at' => (string) $post['posted_at']],
        $narrative,
        (string) $post['text_preview'],
    );

    return [
        'ok' => true,
        'clientId' => $clientId,
        'scoreIndex' => $index,
        'articleId' => (string) $post['article_id'],
        'promptVersion' => CE_HBA_PROMPT_VERSION,
    ];
}

function ce_hba_testpost_niveau_for_index(int $index): string
{
    return match ($index) {
        0 => 'hoog',
        1, 2 => 'laag',
        default => 'neutraal',
    };
}

/**
 * @param array<string, mixed> $post
 * @param array<string, mixed> $parsed
 * @return array<string, mixed>
 */
function ce_hba_apply_explicit_testpost_fields(array $parsed, array $post): array
{
    foreach (['verwacht_flags', 'verwacht_flags_een_van', 'verboden_flags', 'toegestane_flags'] as $key) {
        if (isset($post[$key]) && is_array($post[$key])) {
            $parsed[$key] = array_values(array_map(static fn ($v): string => (string) $v, $post[$key]));
        }
    }
    if (isset($post['max_distinctiviteit']) && is_numeric($post['max_distinctiviteit'])) {
        $parsed['max_distinctiviteit'] = (float) $post['max_distinctiviteit'];
    }
    if (isset($post['max_brand_contribution']) && is_numeric($post['max_brand_contribution'])) {
        $parsed['max_brand_contribution'] = (float) $post['max_brand_contribution'];
    }
    if (isset($post['verwacht_niveau']) && is_string($post['verwacht_niveau']) && trim($post['verwacht_niveau']) !== '') {
        $parsed['verwacht_niveau'] = trim($post['verwacht_niveau']);
    }
    if (isset($post['verwacht_hoofdoordeel']) && is_string($post['verwacht_hoofdoordeel'])) {
        $parsed['verwacht_hoofdoordeel'] = trim($post['verwacht_hoofdoordeel']);
    }
    if (!empty($post['ambigu'])) {
        $parsed['ambiguous'] = true;
    }
    if (isset($parsed['verwacht_flags_een_van']) && is_array($parsed['verwacht_flags_een_van'])) {
        $anySet = array_map(static fn ($f): string => (string) $f, $parsed['verwacht_flags_een_van']);
        $parsed['verwacht_flags'] = array_values(array_filter(
            is_array($parsed['verwacht_flags'] ?? null) ? $parsed['verwacht_flags'] : [],
            static fn ($f): bool => !in_array((string) $f, $anySet, true),
        ));
    }

    return $parsed;
}

/**
 * @param array<string, mixed> $marker
 * @return array<string, mixed>
 */
function ce_hba_expected_from_testpost_marker(array $marker, int $index): array
{
    $verwachtRaw = (string) ($marker['verwacht'] ?? '');
    $expected = ce_hba_parse_verwacht_string($verwachtRaw, $index);
    foreach (
        [
            'verwacht_flags',
            'verwacht_flags_een_van',
            'verboden_flags',
            'toegestane_flags',
            'max_distinctiviteit',
            'max_brand_contribution',
            'verwacht_niveau',
            'verwacht_hoofdoordeel',
        ] as $key
    ) {
        if (array_key_exists($key, $marker)) {
            $expected[$key] = $marker[$key];
        }
    }
    if (!empty($marker['ambiguous']) || !empty($marker['ambigu'])) {
        $expected['ambiguous'] = true;
    }

    $flagsAny = is_array($expected['verwacht_flags_een_van'] ?? null) ? $expected['verwacht_flags_een_van'] : [];
    if ($flagsAny !== []) {
        $anySet = array_map(static fn ($f): string => (string) $f, $flagsAny);
        $expected['verwacht_flags'] = array_values(array_filter(
            is_array($expected['verwacht_flags'] ?? null) ? $expected['verwacht_flags'] : [],
            static fn ($f): bool => !in_array((string) $f, $anySet, true),
        ));
    }

    return $expected;
}

/**
 * @return array{
 *   verwacht: string,
 *   verwacht_flags: list<string>,
 *   verwacht_niveau: string,
 *   ambiguous: bool
 * }
 */
function ce_hba_parse_verwacht_string(string $verwacht, int $index): array
{
    $raw = trim($verwacht);
    $lower = mb_strtolower($raw);
    $ambiguous = false;

    if (preg_match('/\b(mogelijk|eventueel)\b/u', $lower)) {
        $ambiguous = true;
    }
    if (preg_match('/\btenzij\b/u', $lower)) {
        $ambiguous = true;
    }
    if (preg_match('/\bkan\b/u', $lower) && preg_match('/\b(mogelijk|flag)\b/u', $lower)) {
        $ambiguous = true;
    }
    if (preg_match('/\b(mogelijk|eventueel|tenzij)\b/u', $lower) && preg_match('/\bof\b/u', $lower)) {
        $ambiguous = true;
    }

    $flags = [];
    foreach (
        [
            'category_fallback',
            'constraint_breach',
            'opportunistische_trendcontent',
            'proof_gap',
            'foe_vervaging',
        ] as $flag
    ) {
        if (str_contains($lower, $flag)) {
            $flags[] = $flag;
        }
    }

    return [
        'verwacht' => $raw,
        'verwacht_flags' => $flags,
        'verwacht_niveau' => ce_hba_testpost_niveau_for_index($index),
        'ambiguous' => $ambiguous,
    ];
}

/**
 * v1.8 V3: citaat lijkt op functioneel onderdeel (geen merkbewering).
 */
function ce_hba_citaat_is_functioneel_onderdeel(string $citaat): bool
{
    $c = mb_strtolower(trim($citaat));
    if ($c === '') {
        return false;
    }
    if (preg_match('#https?://#i', $citaat)) {
        return true;
    }
    if (preg_match(
        '/\b(neem contact|contact op|bel ons|mail ons|vrijblijvend gesprek|plan een (gesprek|afspraak)|schrijf ons|bezoek ons)\b/u',
        $c
    )) {
        return true;
    }
    if (preg_match('/\b\d{1,2}:\d{2}\b/u', $c)) {
        return true;
    }
    if (preg_match('/\bom\s+\d{1,2}\s*uur\b/u', $c)) {
        return true;
    }
    if (preg_match('/\b\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}\b/u', $c)) {
        return true;
    }
    if (preg_match(
        '/\b(januari|februari|maart|april|mei|juni|juli|augustus|september|oktober|november|december)\b/u',
        $c
    ) && preg_match('/\b\d{1,2}\b/u', $c)) {
        return true;
    }
    if (preg_match('/€\s*\d/u', $c)) {
        return true;
    }

    return false;
}

/** Strip trailing zinnen die puur CTA/datum zijn — flag mag merkdeel + CTA citeren. */
function ce_hba_citaat_strip_trailing_functionele_zinnen(string $citaat): string
{
    $text = trim($citaat);
    if ($text === '') {
        return '';
    }
    $parts = preg_split('/(?<=[.!?])\s+/u', $text);
    if (!is_array($parts) || $parts === []) {
        return $text;
    }
    while ($parts !== []) {
        $last = trim((string) end($parts));
        if ($last === '' || !ce_hba_citaat_is_functioneel_onderdeel($last)) {
            break;
        }
        array_pop($parts);
    }

    return trim(implode(' ', $parts));
}

/**
 * @param array<string, string> $flagCitaten
 * @return list<array{flag: string, citaat: string}>
 */
function ce_hba_functionele_flag_hits(array $flagCitaten): array
{
    $hits = [];
    foreach ($flagCitaten as $flag => $citaat) {
        $flagKey = trim((string) $flag);
        if ($flagKey === '' || str_ends_with($flagKey, '_uitleg')) {
            continue;
        }
        $text = trim((string) $citaat);
        if ($text === '') {
            continue;
        }
        $merkdeel = ce_hba_citaat_strip_trailing_functionele_zinnen($text);
        if ($merkdeel !== '' && !ce_hba_citaat_is_functioneel_onderdeel($merkdeel)) {
            continue;
        }
        if (ce_hba_citaat_is_functioneel_onderdeel($text)) {
            $hits[] = ['flag' => $flagKey, 'citaat' => $text];
        }
    }

    return $hits;
}

/** @param array<string, mixed> $expected */
function ce_hba_testpost_expectation_is_scored(array $expected): bool
{
    if (trim((string) ($expected['verwacht_hoofdoordeel'] ?? '')) !== '') {
        return true;
    }
    foreach (['verwacht_flags', 'verwacht_flags_een_van', 'verboden_flags'] as $key) {
        if (!empty($expected[$key]) && is_array($expected[$key])) {
            return true;
        }
    }
    if (isset($expected['max_distinctiviteit']) && is_numeric($expected['max_distinctiviteit'])) {
        return true;
    }
    if (isset($expected['max_brand_contribution']) && is_numeric($expected['max_brand_contribution'])) {
        return true;
    }
    if (!empty($expected['ambiguous'])) {
        return true;
    }

    return trim((string) ($expected['verwacht'] ?? '')) !== '';
}

/**
 * @param array<string, mixed> $expected
 * @param array<string, mixed> $actual
 * @return array{judgment: string, reasons: list<string>, warnings: list<string>}
 */
function ce_hba_judge_testpost_row(array $expected, array $actual, int $index): array
{
    if (!ce_hba_testpost_expectation_is_scored($expected)) {
        return [
            'judgment' => 'niet_beoordeeld',
            'reasons' => ['Geen verwachting in matrix — niet meegeteld'],
            'warnings' => [],
        ];
    }

    $reasons = [];
    $warnings = [];
    $flags = is_array($actual['flags'] ?? null) ? $actual['flags'] : [];
    $expectedFlags = is_array($expected['verwacht_flags'] ?? null) ? $expected['verwacht_flags'] : [];
    $verwachtHoofd = trim((string) ($expected['verwacht_hoofdoordeel'] ?? ''));
    $actualHoofd = trim((string) ($actual['hoofdoordeel'] ?? ''));
    $ambiguous = !empty($expected['ambiguous']);

    // Ambigue posts: geen hoofdoordeel-eis, wel verwachte flags en merkbijdrage-cap.
    if (!$ambiguous) {
        if ($verwachtHoofd !== '') {
            if ($actualHoofd === '') {
                $reasons[] = 'hoofdoordeel ontbreekt (verwacht: ' . $verwachtHoofd . ')';
            } elseif ($actualHoofd !== $verwachtHoofd) {
                $reasons[] = 'hoofdoordeel ' . $actualHoofd . ' (verwacht: ' . $verwachtHoofd . ')';
            }
        }
    }

    foreach ($expectedFlags as $flag) {
        if (!in_array((string) $flag, $flags, true)) {
            $reasons[] = 'Ontbreekt verwachte flag: ' . (string) $flag;
        }
    }

    $expectedFlagsAny = is_array($expected['verwacht_flags_een_van'] ?? null)
        ? $expected['verwacht_flags_een_van']
        : [];
    if ($expectedFlagsAny !== []) {
        $anyPresent = false;
        foreach ($expectedFlagsAny as $flag) {
            if (in_array((string) $flag, $flags, true)) {
                $anyPresent = true;
                break;
            }
        }
        if (!$anyPresent) {
            $reasons[] = 'Geen van verwachte flags: ' . implode(' / ', array_map('strval', $expectedFlagsAny));
        }
    }

    $verbodenFlags = is_array($expected['verboden_flags'] ?? null) ? $expected['verboden_flags'] : [];
    foreach ($verbodenFlags as $flag) {
        if (in_array((string) $flag, $flags, true)) {
            $reasons[] = 'Verboden flag aanwezig: ' . (string) $flag;
        }
    }

    if ($verwachtHoofd === 'op_merk' && in_array('proof_gap', $flags, true) && $actualHoofd === 'op_merk') {
        $warnings[] = 'proof_gap gemeld (niet meetellend bij verwacht op_merk)';
    }

    if (isset($expected['max_distinctiviteit']) && is_numeric($expected['max_distinctiviteit'])) {
        $maxDist = (float) $expected['max_distinctiviteit'];
        $dist = isset($actual['distinctiviteit']) ? (float) $actual['distinctiviteit'] : null;
        if ($dist !== null && $dist > $maxDist) {
            $reasons[] = 'distinctiviteit ' . $dist . ' boven max ' . $maxDist;
        }
    }

    if (isset($expected['max_brand_contribution']) && is_numeric($expected['max_brand_contribution'])) {
        $maxBc = (float) $expected['max_brand_contribution'];
        $bc = isset($actual['brandContribution']) ? (float) $actual['brandContribution'] : null;
        if ($bc !== null && $bc > $maxBc) {
            $reasons[] = 'merkbijdrage ' . $bc . ' boven max ' . $maxBc;
        }
    }

    $niveau = (string) ($expected['verwacht_niveau'] ?? '');
    if ($niveau === 'laag' && !$ambiguous) {
        $dist = isset($actual['distinctiviteit']) ? (float) $actual['distinctiviteit'] : null;
        $maxDist = isset($expected['max_distinctiviteit']) && is_numeric($expected['max_distinctiviteit'])
            ? (float) $expected['max_distinctiviteit']
            : 2.0;
        if ($dist !== null && $dist > $maxDist) {
            $reasons[] = 'verwacht_niveau laag: distinctiviteit ' . $dist . ' boven ' . $maxDist;
        }
    }

    if ($ambiguous) {
        if ($reasons !== []) {
            return ['judgment' => 'fout', 'reasons' => $reasons, 'warnings' => $warnings];
        }

        return [
            'judgment' => 'review',
            'reasons' => ['Ambigue verwachting — handmatige beoordeling'],
            'warnings' => $warnings,
        ];
    }

    if ($reasons === []) {
        return ['judgment' => 'goed', 'reasons' => [], 'warnings' => $warnings];
    }

    return ['judgment' => 'fout', 'reasons' => $reasons, 'warnings' => $warnings];
}

/** @return list<string> */
function ce_hba_testmerk_client_names(): array
{
    return ['Brandpunt Koffie', 'Stelwerk', 'Duinroos Thuiszorg'];
}

/**
 * @param array<string, mixed> $scoreRow
 * @param array<string, mixed> $dto
 * @return array{volgt: bool, kern: ?string, merkbewering: ?string, context: array<string, mixed>}
 */
function ce_hba_testpost_hoofdoordeel_flags_check(array $scoreRow, array $dto): array
{
    require_once __DIR__ . '/hba-prompt.php';
    $raw = $scoreRow['raw_llm_response'] ?? null;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    $ctx = is_array($raw['hoofdoordeel_context'] ?? null) ? $raw['hoofdoordeel_context'] : [];
    $kernRaw = array_key_exists('kern_categorietaal', $raw) ? $raw['kern_categorietaal'] : null;
    $merkRaw = isset($raw['merkbewering']) ? (string) $raw['merkbewering'] : 'ja';
    $tool = [
        'hoofdoordeel' => $dto['hoofdoordeel'] ?? ($raw['hoofdoordeel'] ?? ''),
        'merkbewering' => $merkRaw,
        'kern_categorietaal' => $kernRaw,
        'contentrol' => $dto['contentrol'] ?? '',
        'flag_citaten' => ce_hba_flag_citaten_from_raw($scoreRow['raw_llm_response'] ?? null),
        'scores' => [
            'brand_contribution' => ['score' => $dto['brandContribution'] ?? 0],
            'architecture_risk' => ['flags' => $dto['architectureRiskFlags'] ?? []],
        ],
    ];

    return [
        'volgt' => ce_hba_hoofdoordeel_volgt_uit_flags($tool, $ctx),
        'kern' => $kernRaw === null ? null : (string) $kernRaw,
        'merkbewering' => isset($raw['merkbewering']) ? (string) $raw['merkbewering'] : null,
        'context' => $ctx,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function ce_hba_testmerken_collect_slot_targets(PDO $pdo): array
{
    $targets = [];

    foreach (ce_hba_testmerk_client_names() as $merk) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE TRIM(name) = :name LIMIT 1');
        $stmt->execute(['name' => $merk]);
        $clientId = (string) ($stmt->fetchColumn() ?: '');
        if ($clientId === '') {
            continue;
        }

        $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
        $narrativeId = is_array($narrative) ? (string) ($narrative['id'] ?? '') : '';

        $slotStmt = $pdo->prepare(
            "SELECT s.id AS slot_id, s.theme, s.input_human, a.id AS article_id
             FROM content_slots s
             INNER JOIN articles a ON a.slot_id = s.id
             WHERE s.client_id = :client_id
               AND s.theme LIKE 'HBA-test:%'
             ORDER BY s.theme ASC"
        );
        $slotStmt->execute(['client_id' => $clientId]);
        $slotRows = $slotStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        usort($slotRows, static function (array $a, array $b): int {
            $ia = 99;
            $ib = 99;
            $ma = json_decode((string) ($a['input_human'] ?? ''), true);
            $mb = json_decode((string) ($b['input_human'] ?? ''), true);
            if (is_array($ma) && isset($ma['index'])) {
                $ia = (int) $ma['index'];
            }
            if (is_array($mb) && isset($mb['index'])) {
                $ib = (int) $mb['index'];
            }

            return $ia <=> $ib;
        });

        foreach ($slotRows as $i => $slotRow) {
            $marker = json_decode((string) ($slotRow['input_human'] ?? ''), true);
            if (!is_array($marker)) {
                $marker = [];
            }
            $index = max(0, (int) ($marker['index'] ?? ($i + 1)) - 1);
            $label = (string) ($marker['label'] ?? CE_HBA_TESTPOST_SLOT_LABELS[$index] ?? ('Post ' . ($index + 1)));
            $targets[] = [
                'merk' => $merk,
                'clientId' => $clientId,
                'narrativeId' => $narrativeId,
                'scoreIndex' => $index + 1,
                'index' => $index,
                'label' => $label,
                'marker' => $marker,
                'expected' => ce_hba_expected_from_testpost_marker($marker, $index),
                'articleId' => (string) ($slotRow['article_id'] ?? ''),
            ];
        }
    }

    usort($targets, static function (array $a, array $b): int {
        $cmp = strcmp((string) ($a['merk'] ?? ''), (string) ($b['merk'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }

        return ((int) ($a['scoreIndex'] ?? 0)) <=> ((int) ($b['scoreIndex'] ?? 0));
    });

    return $targets;
}

/** @return array<string, mixed> */
function ce_hba_testmerken_stability_payload(PDO $pdo, int $runs = 5): array
{
    require_once __DIR__ . '/hba-prompt.php';

    $runs = max(1, min(10, $runs));
    $targets = ce_hba_testmerken_collect_slot_targets($pdo);
    $models = [];
    $rows = [];
    $unstable = [];
    $correctionCount = 0;
    $volgtFlagsCount = 0;
    $categoryFallbackPosts = 0;
    $totalScored = 0;
    $functionalFlagHits = [];

    foreach ($targets as $target) {
        $expected = is_array($target['expected'] ?? null) ? $target['expected'] : [];
        $runDetails = [];
        $bcValues = [];

        for ($run = 1; $run <= $runs; $run++) {
            ce_hba_score_testpost_for_client($pdo, (string) $target['clientId'], (int) $target['scoreIndex']);

            $articleId = (string) ($target['articleId'] ?? '');
            $narrativeId = (string) ($target['narrativeId'] ?? '');
            $scoreRow = ($articleId !== '' && $narrativeId !== '')
                ? ce_hba_fetch_score_for_article($pdo, $articleId, $narrativeId)
                : null;

            $actual = [
                'brandContribution' => null,
                'distinctiviteit' => null,
                'contentrol' => null,
                'flags' => [],
                'hoofdoordeel' => null,
                'hoofdoordeelGecorrigeerd' => null,
                'hoofdoordeelVolgtUitFlags' => null,
                'kernCategorietaal' => null,
                'merkbewering' => null,
                'flagsVerwijderd' => [],
                'flagCitaten' => [],
                'constraintBreachVerificatie' => null,
            ];

            if (is_array($scoreRow)) {
                $dto = ce_hba_score_dto($scoreRow);
                $actual['brandContribution'] = $dto['brandContribution'];
                $actual['distinctiviteit'] = $dto['brandContributionDimensions']['distinctiviteit'] ?? null;
                $actual['contentrol'] = $dto['contentrol'];
                $actual['flags'] = $dto['architectureRiskFlags'];
                $actual['hoofdoordeel'] = $dto['hoofdoordeel'] ?? null;
                $actual['hoofdoordeelGecorrigeerd'] = $dto['hoofdoordeelGecorrigeerd'] ?? null;
                $actual['flagsVerwijderd'] = ce_hba_flags_verwijderd_from_raw($scoreRow['raw_llm_response'] ?? null);
                $actual['flagCitaten'] = ce_hba_flag_citaten_from_raw($scoreRow['raw_llm_response'] ?? null);
                $actual['constraintBreachVerificatie'] = ce_hba_constraint_breach_verificatie_from_raw(
                    $scoreRow['raw_llm_response'] ?? null,
                );
                if ($dto['modelUsed'] !== '') {
                    $models[(string) $dto['modelUsed']] = true;
                }
                $flagsCheck = ce_hba_testpost_hoofdoordeel_flags_check($scoreRow, $dto);
                $actual['hoofdoordeelVolgtUitFlags'] = $flagsCheck['volgt'];
                $actual['kernCategorietaal'] = $flagsCheck['kern'];
                $actual['merkbewering'] = $flagsCheck['merkbewering'];
                if ($flagsCheck['volgt']) {
                    $volgtFlagsCount++;
                }
                $totalScored++;
            }

            $judgment = ce_hba_judge_testpost_row($expected, $actual, (int) $target['index']);
            if ($actual['brandContribution'] !== null) {
                $bcValues[] = (float) $actual['brandContribution'];
            }
            $funcHits = ce_hba_functionele_flag_hits($actual['flagCitaten'] ?? []);
            foreach ($funcHits as $hit) {
                $functionalFlagHits[] = [
                    'merk' => $target['merk'],
                    'label' => $target['label'],
                    'run' => $run,
                    'flag' => $hit['flag'],
                    'citaat' => $hit['citaat'],
                ];
            }

            $runDetails[] = [
                'run' => $run,
                'judgment' => $judgment['judgment'],
                'reasons' => $judgment['reasons'],
                'warnings' => $judgment['warnings'] ?? [],
                'brandContribution' => $actual['brandContribution'],
                'distinctiviteit' => $actual['distinctiviteit'],
                'flags' => $actual['flags'],
                'flags_verwijderd' => $actual['flagsVerwijderd'],
                'flag_citaten' => $actual['flagCitaten'] ?? [],
                'functionele_flag_hits' => $funcHits,
                'hoofdoordeel' => $actual['hoofdoordeel'],
                'hoofdoordeel_gecorrigeerd' => null,
                'hoofdoordeel_volgt_uit_flags' => $actual['hoofdoordeelVolgtUitFlags'] ?? null,
                'kern_categorietaal' => $actual['kernCategorietaal'] ?? null,
                'merkbewering' => $actual['merkbewering'] ?? null,
                'constraint_breach_verificatie' => $actual['constraintBreachVerificatie'],
            ];
        }

        $goodRuns = count(array_filter($runDetails, static fn (array $r): bool => ($r['judgment'] ?? '') === 'goed'));
        $foutRuns = count(array_filter($runDetails, static fn (array $r): bool => ($r['judgment'] ?? '') === 'fout'));
        $bcSpread = $bcValues === [] ? null : (max($bcValues) - min($bcValues));
        $hoofdoordeelPerRun = array_map(
            static fn (array $r): ?string => isset($r['hoofdoordeel']) ? (string) $r['hoofdoordeel'] : null,
            $runDetails,
        );
        $ambiguous = !empty($expected['ambiguous']);
        $scoreable = ce_hba_testpost_expectation_is_scored($expected);
        $stableGood = $scoreable && $goodRuns === $runs && !$ambiguous;
        if (!$scoreable) {
            $rowJudgment = 'niet_beoordeeld';
        } elseif ($ambiguous) {
            if ($foutRuns === 0) {
                $rowJudgment = 'review';
            } elseif ($foutRuns === $runs) {
                $rowJudgment = 'fout';
            } else {
                $rowJudgment = 'instabiel';
            }
        } else {
            $rowJudgment = $stableGood ? 'goed' : ($goodRuns === 0 ? 'fout' : 'instabiel');
        }
        $lastFlags = is_array($runDetails[$runs - 1]['flags'] ?? null) ? $runDetails[$runs - 1]['flags'] : [];
        if (in_array('category_fallback', $lastFlags, true)) {
            $categoryFallbackPosts++;
        }

        $row = [
            'merk' => $target['merk'],
            'clientId' => $target['clientId'],
            'index' => $target['scoreIndex'],
            'label' => $target['label'],
            'verwacht_hoofdoordeel' => $expected['verwacht_hoofdoordeel'] ?? null,
            'ambiguous' => $ambiguous,
            'goodRuns' => $goodRuns,
            'runsTotal' => $runs,
            'stableGood' => $stableGood,
            'hoofdoordeelPerRun' => $hoofdoordeelPerRun,
            'brandContributionSpread' => $bcSpread,
            'runs' => $runDetails,
            'judgment' => $rowJudgment,
        ];
        $rows[] = $row;

        if ($rowJudgment === 'instabiel' || ($rowJudgment === 'fout' && !$ambiguous)) {
            $unstable[] = [
                'merk' => $target['merk'],
                'label' => $target['label'],
                'goodRuns' => $goodRuns,
                'hoofdoordeelPerRun' => $hoofdoordeelPerRun,
            ];
        }
    }

    $summary = ['stable_goed' => 0, 'instabiel' => 0, 'review' => 0, 'fout' => 0, 'niet_beoordeeld' => 0];
    foreach ($rows as $row) {
        $judgment = (string) ($row['judgment'] ?? '');
        if ($judgment === 'niet_beoordeeld') {
            $summary['niet_beoordeeld']++;
        } elseif ($judgment === 'goed' || !empty($row['stableGood'])) {
            $summary['stable_goed']++;
        } elseif ($judgment === 'review') {
            $summary['review']++;
        } elseif ($judgment === 'fout') {
            $summary['fout']++;
        } else {
            $summary['instabiel']++;
        }
    }

    $volgtPercent = $totalScored > 0 ? round(100 * $volgtFlagsCount / $totalScored, 1) : 0.0;

    return [
        'ok' => true,
        'generatedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
        'promptVersion' => CE_HBA_PROMPT_VERSION,
        'runs' => $runs,
        'modelsUsed' => array_keys($models),
        'environment' => ce_is_production() ? 'production' : (getenv('CE_ENV') ?: 'staging'),
        'summary' => $summary,
        'categoryFallbackPostsLastRun' => $categoryFallbackPosts,
        'totalScoreCount' => $totalScored,
        'hoofdoordeelGecorrigeerdCount' => 0,
        'hoofdoordeelGecorrigeerdPercent' => 0.0,
        'hoofdoordeelVolgtUitFlagsCount' => $volgtFlagsCount,
        'hoofdoordeelVolgtUitFlagsPercent' => $volgtPercent,
        'flagsOpFunctioneleOnderdelen' => $functionalFlagHits,
        'unstablePosts' => $unstable,
        'rows' => $rows,
    ];
}

/** @return array<string, mixed> */
function ce_hba_testmerken_matrix_payload(PDO $pdo): array
{
    require_once __DIR__ . '/hba-prompt.php';

    $rows = [];
    $reviewList = [];
    $models = [];

    foreach (ce_hba_testmerk_client_names() as $merk) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE TRIM(name) = :name LIMIT 1');
        $stmt->execute(['name' => $merk]);
        $clientId = (string) ($stmt->fetchColumn() ?: '');
        if ($clientId === '') {
            continue;
        }

        $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
        $narrativeId = is_array($narrative) ? (string) ($narrative['id'] ?? '') : '';

        $slotStmt = $pdo->prepare(
            "SELECT s.id AS slot_id, s.theme, s.input_human, a.id AS article_id
             FROM content_slots s
             INNER JOIN articles a ON a.slot_id = s.id
             WHERE s.client_id = :client_id
               AND s.theme LIKE 'HBA-test:%'
             ORDER BY s.theme ASC"
        );
        $slotStmt->execute(['client_id' => $clientId]);
        $slotRows = $slotStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        usort($slotRows, static function (array $a, array $b): int {
            $ia = 99;
            $ib = 99;
            $ma = json_decode((string) ($a['input_human'] ?? ''), true);
            $mb = json_decode((string) ($b['input_human'] ?? ''), true);
            if (is_array($ma) && isset($ma['index'])) {
                $ia = (int) $ma['index'];
            }
            if (is_array($mb) && isset($mb['index'])) {
                $ib = (int) $mb['index'];
            }

            return $ia <=> $ib;
        });

        foreach ($slotRows as $i => $slotRow) {
            $marker = json_decode((string) ($slotRow['input_human'] ?? ''), true);
            if (!is_array($marker)) {
                $marker = [];
            }
            $index = max(0, (int) ($marker['index'] ?? ($i + 1)) - 1);
            $label = (string) ($marker['label'] ?? CE_HBA_TESTPOST_SLOT_LABELS[$index] ?? ('Post ' . ($index + 1)));
            $expected = ce_hba_expected_from_testpost_marker($marker, $index);

            $articleId = (string) ($slotRow['article_id'] ?? '');
            $scoreRow = ($articleId !== '' && $narrativeId !== '')
                ? ce_hba_fetch_score_for_article($pdo, $articleId, $narrativeId)
                : null;

            $actual = [
                'articleId' => $articleId,
                'brandContribution' => null,
                'distinctiviteit' => null,
                'contentrol' => null,
                'flags' => [],
                'flagsVerwijderd' => [],
                'flagCitaten' => [],
                'hoofdoordeel' => null,
                'hoofdoordeelGecorrigeerd' => null,
                'hoofdoordeelVolgtUitFlags' => null,
                'kernCategorietaal' => null,
                'merkbewering' => null,
                'constraintBreachVerificatie' => null,
                'modelUsed' => null,
                'promptVersion' => null,
                'scoredAt' => null,
            ];

            if (is_array($scoreRow)) {
                $dto = ce_hba_score_dto($scoreRow);
                $actual['brandContribution'] = $dto['brandContribution'];
                $actual['distinctiviteit'] = $dto['brandContributionDimensions']['distinctiviteit'] ?? null;
                $actual['contentrol'] = $dto['contentrol'];
                $actual['flags'] = $dto['architectureRiskFlags'];
                $actual['flagsVerwijderd'] = ce_hba_flags_verwijderd_from_raw($scoreRow['raw_llm_response'] ?? null);
                $actual['flagCitaten'] = ce_hba_flag_citaten_from_raw($scoreRow['raw_llm_response'] ?? null);
                $actual['constraintBreachVerificatie'] = ce_hba_constraint_breach_verificatie_from_raw(
                    $scoreRow['raw_llm_response'] ?? null,
                );
                $actual['hoofdoordeel'] = $dto['hoofdoordeel'] ?? null;
                $actual['hoofdoordeelGecorrigeerd'] = $dto['hoofdoordeelGecorrigeerd'] ?? null;
                $actual['modelUsed'] = $dto['modelUsed'];
                $actual['promptVersion'] = $dto['promptVersion'];
                $actual['scoredAt'] = $dto['scoredAt'];
                if ($dto['modelUsed'] !== '') {
                    $models[(string) $dto['modelUsed']] = true;
                }
                $flagsCheck = ce_hba_testpost_hoofdoordeel_flags_check($scoreRow, $dto);
                $actual['hoofdoordeelVolgtUitFlags'] = $flagsCheck['volgt'];
                $actual['kernCategorietaal'] = $flagsCheck['kern'];
                $actual['merkbewering'] = $flagsCheck['merkbewering'];
            }

            $judgment = ce_hba_judge_testpost_row($expected, $actual, $index);
            $funcHits = ce_hba_functionele_flag_hits($actual['flagCitaten'] ?? []);

            $row = [
                'merk' => $merk,
                'clientId' => $clientId,
                'articleId' => $articleId,
                'slotId' => (string) ($slotRow['slot_id'] ?? ''),
                'index' => $index + 1,
                'label' => $label,
                'verwacht' => $expected['verwacht'],
                'verwacht_flags' => $expected['verwacht_flags'] ?? [],
                'verboden_flags' => $expected['verboden_flags'] ?? [],
                'toegestane_flags' => $expected['toegestane_flags'] ?? null,
                'max_distinctiviteit' => $expected['max_distinctiviteit'] ?? null,
                'verwacht_niveau' => $expected['verwacht_niveau'],
                'verwacht_hoofdoordeel' => $expected['verwacht_hoofdoordeel'] ?? null,
                'ambiguous' => $expected['ambiguous'],
                'brandContribution' => $actual['brandContribution'],
                'distinctiviteit' => $actual['distinctiviteit'],
                'contentrol' => $actual['contentrol'],
                'flags' => $actual['flags'],
                'flags_verwijderd' => $actual['flagsVerwijderd'],
                'flag_citaten' => $actual['flagCitaten'],
                'hoofdoordeel' => $actual['hoofdoordeel'],
                'hoofdoordeel_gecorrigeerd' => $actual['hoofdoordeelGecorrigeerd'],
                'hoofdoordeel_volgt_uit_flags' => $actual['hoofdoordeelVolgtUitFlags'] ?? null,
                'kern_categorietaal' => $actual['kernCategorietaal'] ?? null,
                'merkbewering' => $actual['merkbewering'] ?? null,
                'constraint_breach_verificatie' => $actual['constraintBreachVerificatie'],
                'judgment' => $judgment['judgment'],
                'reasons' => $judgment['reasons'],
                'warnings' => $judgment['warnings'] ?? [],
                'functionele_flag_hits' => $funcHits,
                'modelUsed' => $actual['modelUsed'],
                'promptVersion' => $actual['promptVersion'],
            ];
            $rows[] = $row;

            if ($judgment['judgment'] === 'review') {
                $reviewList[] = [
                    'merk' => $merk,
                    'post' => $label,
                    'verwacht' => $expected['verwacht'],
                ];
            }
        }
    }

    // Herordenen: per merk op index 1..4
    usort($rows, static function (array $a, array $b): int {
        $cmp = strcmp((string) ($a['merk'] ?? ''), (string) ($b['merk'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }

        return ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0));
    });

    $summary = ['goed' => 0, 'fout' => 0, 'review' => 0, 'geen_score' => 0];
    foreach ($rows as $row) {
        if ($row['brandContribution'] === null && ($row['judgment'] ?? '') !== 'review') {
            $summary['geen_score']++;
            continue;
        }
        $j = (string) ($row['judgment'] ?? '');
        if (isset($summary[$j])) {
            $summary[$j]++;
        }
    }

    $scoredVersions = [];
    foreach ($rows as $row) {
        $version = trim((string) ($row['promptVersion'] ?? ''));
        if ($version !== '') {
            $scoredVersions[$version] = true;
        }
    }
    $scoredVersions = array_keys($scoredVersions);
    sort($scoredVersions);
    $note = 'Testmerken op staging kunnen guardrail PASS geforceerd hebben voor scoring.';
    if ($scoredVersions !== [CE_HBA_PROMPT_VERSION]) {
        $note = 'Let op: scores zijn niet (allemaal) gemaakt met de huidige promptversie '
            . CE_HBA_PROMPT_VERSION . ' — herimporteer de testposts om opnieuw te scoren. ' . $note;
    }

    return [
        'ok' => true,
        'generatedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
        'promptVersion' => $scoredVersions === [] ? CE_HBA_PROMPT_VERSION : implode('+', $scoredVersions),
        'codePromptVersion' => CE_HBA_PROMPT_VERSION,
        'modelsUsed' => array_keys($models),
        'environment' => ce_is_production() ? 'production' : (getenv('CE_ENV') ?: 'staging'),
        'note' => $note,
        'rows' => $rows,
        'reviewList' => $reviewList,
        'summary' => $summary,
    ];
}

/** @param list<string> $verboden */
function ce_hba_testmerk_infer_woorden_die_passen(array $narrative): array
{
    $existing = array_values(array_filter(array_map('trim', $narrative['woorden_die_passen'] ?? [])));
    if (count($existing) >= 3) {
        return $existing;
    }

    $fromTone = [];
    $tone = trim((string) ($narrative['tone_of_behaviour'] ?? ''));
    foreach (preg_split('/[,;]+|\s+en\s+/u', $tone) ?: [] as $chunk) {
        $word = trim($chunk);
        if ($word !== '' && mb_strlen($word) >= 3 && mb_strlen($word) <= 48) {
            $fromTone[] = $word;
        }
    }

    $merged = array_values(array_unique(array_merge($existing, $fromTone)));
    while (count($merged) < 3) {
        $merged[] = match (count($merged)) {
            0 => 'herkenbaar',
            1 => 'concreet',
            default => 'eigen',
        };
    }

    return $merged;
}

/** @param list<string> $verboden */
function ce_hba_testmerk_infer_woorden_die_niet_passen(array $verboden, array $existing): array
{
    $merged = array_values(array_unique(array_filter(array_map('trim', array_merge($existing, $verboden)))));
    if (count($merged) >= 3) {
        return $merged;
    }

    while (count($merged) < 3) {
        $merged[] = 'generieke branchetaal';
    }

    return $merged;
}

/** @param array<string, mixed> $narrative */
function ce_hba_testmerk_enrich_narrative_fields(array $narrative): array
{
    $verboden = $narrative['verboden_logica'];
    $narrative['woorden_die_passen'] = ce_hba_testmerk_infer_woorden_die_passen($narrative);
    $narrative['woorden_die_niet_passen'] = ce_hba_testmerk_infer_woorden_die_niet_passen(
        $verboden,
        $narrative['woorden_die_niet_passen'],
    );

    if (trim((string) ($narrative['wat_bewust_niet'] ?? '')) === '' && $verboden !== []) {
        $narrative['wat_bewust_niet'] = implode(
            "\n",
            array_map(static fn (string $line): string => '• ' . $line, $verboden),
        );
    }

    $kern = array_values(array_filter(array_map('trim', $narrative['kernformuleringen'] ?? [])));
    if ($kern === []) {
        $mech = trim((string) ($narrative['mechanisme'] ?? ''));
        $parts = preg_split('/(?<=[.!?])\s+/u', $mech) ?: [];
        $kern = array_values(array_filter(array_map('trim', $parts)));
        if ($kern === [] && $mech !== '') {
            $kern = [$mech];
        }
    }
    $narrative['kernformuleringen'] = array_slice($kern, 0, 5);

    return $narrative;
}

/** @param array<string, mixed> $meta testmerk|referentiemerk */
function ce_hba_apply_testmerk_tone_defaults(PDO $pdo, string $clientId, array $meta): void
{
    require_once __DIR__ . '/tone-styles.php';
    ce_ensure_tone_styles_column($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'tone_styles')) {
        return;
    }

    $type = strtolower(trim((string) ($meta['type'] ?? '')));
    $styles = ce_default_tone_styles_ui();
    $preferred = (str_contains($type, 'consument') || str_contains($type, 'b2c'))
        ? ['b to c', 'personal', 'b to b', 'partners', 'internal']
        : ['b to b', 'partners', 'b to c', 'personal', 'internal'];

    $ordered = [];
    foreach ($preferred as $label) {
        if (isset($styles[$label])) {
            $ordered[$label] = $styles[$label];
        }
    }
    foreach ($styles as $label => $text) {
        if (!isset($ordered[$label])) {
            $ordered[$label] = $text;
        }
    }

    $encoded = ce_encode_tone_styles_for_db($ordered);
    $pdo->prepare('UPDATE clients SET tone_styles = :tone_styles WHERE id = :id')
        ->execute(['tone_styles' => $encoded, 'id' => $clientId]);
}

/** @param array<string, mixed> $bundle JSON uit HBA_testmerken/*.json */
function ce_hba_narrative_from_testmerk_bundle(array $bundle): array
{
    $raw = is_array($bundle['brand_narrative'] ?? null) ? $bundle['brand_narrative'] : [];
    $narrative = ce_brand_narrative_from_request($raw);
    $narrative = ce_hba_testmerk_enrich_narrative_fields($narrative);

    $meta = ce_hba_bundle_merk_meta($bundle);
    $merknaam = mb_strtolower(trim((string) ($meta['merknaam'] ?? '')), 'UTF-8');
    if (str_contains($merknaam, 'brandpunt')) {
        $narrative['aanspreekvorm'] = 'je';
    }
    $branche = trim((string) ($meta['branche'] ?? ''));
    if ($branche !== '' && trim((string) ($narrative['identity_intro'] ?? '')) === '') {
        $narrative['identity_intro'] = $branche;
    }
    $status = trim((string) ($meta['status'] ?? ''));
    if ($status !== '') {
        $intro = trim((string) ($narrative['identity_intro'] ?? ''));
        $narrative['identity_intro'] = $intro === ''
            ? '[Intern HBA-referentie] ' . $status
            : $intro . "\n\n[Intern HBA-referentie] " . $status;
    }

    return $narrative;
}

/** @param array<string, mixed> $bundle @return array<string, mixed> */
function ce_provision_hba_testmerk_client(PDO $pdo, array $bundle, array $options = []): array
{
    $meta = ce_hba_bundle_merk_meta($bundle);
    $name = trim((string) ($meta['merknaam'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('merknaam ontbreekt (testmerk of referentiemerk)');
    }

    $narrative = ce_hba_narrative_from_testmerk_bundle($bundle);
    if (is_array($bundle['markthygiene'] ?? null)) {
        $narrative['branche_code'] = ce_hba_upsert_markthygiene_branche($pdo, $bundle['markthygiene'])['code'];
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
    $slug = trim($slug, '-') ?: 'testmerk';
    $defaultEmail = $slug . '-hba@igisnext.com';
    $contactEmailOpt = trim((string) ($options['contactEmail'] ?? ''));
    $contactEmail = $contactEmailOpt !== ''
        ? strtolower($contactEmailOpt)
        : strtolower($defaultEmail);

    $reuseExisting = !empty($options['reuseExisting']);
    $dup = $pdo->prepare('SELECT id FROM clients WHERE LOWER(TRIM(name)) = LOWER(:name) LIMIT 1');
    $dup->execute(['name' => $name]);
    $existingClientId = (string) ($dup->fetchColumn() ?: '');

    if ($existingClientId !== '' && !$reuseExisting) {
        throw new InvalidArgumentException('Er bestaat al een client met deze bedrijfsnaam');
    }

    if ($existingClientId !== '') {
        $clientId = $existingClientId;
    } else {
        $createBody = [
            'company' => $name,
            'contactEmail' => $contactEmail,
            'contactName' => explode(' ', $name)[0],
            'contactRole' => 'both',
            'planningHorizonWeeks' => (int) ($options['planningHorizonWeeks'] ?? 4),
            'postsPerWeek' => (int) ($options['postsPerWeek'] ?? 1),
            'archetype' => (string) ($narrative['archetype'] ?? ''),
            'adversary' => (string) ($narrative['foe'] ?? ''),
            'brandstory' => (string) ($narrative['doel'] ?? ''),
            'toneOfVoice' => (string) ($narrative['tone_of_behaviour'] ?? ''),
            'writingGuide' => (string) ($narrative['tone_of_behaviour'] ?? ''),
        ];
        if (!empty($options['password'])) {
            $createBody['password'] = (string) $options['password'];
        }

        $created = ce_create_client($pdo, $createBody);
        $clientId = (string) ($created['client']['clientId'] ?? $created['client']['id'] ?? '');
        if ($clientId === '') {
            throw new RuntimeException('Client-id ontbreekt na aanmaken');
        }
    }

    ce_ensure_account_columns($pdo);
    if (ce_table_has_column($pdo, 'clients', 'ce01_weekly_schedule')) {
        $pdo->prepare('UPDATE clients SET ce01_weekly_schedule = 0 WHERE id = :id')
            ->execute(['id' => $clientId]);
    }

    ce_save_brand_narrative($pdo, $clientId, $narrative, 'hba-testmerken-import');
    ce_hba_apply_testmerk_tone_defaults($pdo, $clientId, $meta);
    $savedRow = ce_fetch_current_brand_narrative($pdo, $clientId);
    $missing = $savedRow ? ce_brand_narrative_missing_fields($savedRow) : CE_HBA_NARRATIVE_FIELDS;

    require_once __DIR__ . '/hba-v2-repository.php';
    if ($missing === []) {
        ce_hba_v2_mark_architecture_complete($pdo, $clientId);
    }

    $brancheCode = trim((string) ($narrative['branche_code'] ?? ''));
    $gate = ce_hba_ensure_testmerk_scoring_gate(
        $pdo,
        $clientId,
        $name,
        $brancheCode !== '' ? $brancheCode : null,
    );

    return [
        'ok' => true,
        'clientId' => $clientId,
        'company' => $name,
        'contactEmail' => $contactEmail,
        'narrativeComplete' => $missing === [],
        'missingNarrativeFields' => $missing,
        'testpostsCount' => is_array($bundle['testposts'] ?? null) ? count($bundle['testposts']) : 0,
        'brancheCode' => $gate['brancheCode'] ?? $brancheCode,
        'referentiemerk' => is_array($bundle['referentiemerk'] ?? null),
        'guardrailActive' => !empty($gate['guardrailActive']),
    ];
}
