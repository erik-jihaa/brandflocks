<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-testmerken-repository.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/client-context.php';
require_once __DIR__ . '/home-repository.php';
require_once __DIR__ . '/agenda-portfolio-signals-repository.php';

const CE_HBA_STROOMTEST_CLIENT_NAME = 'Brandpunt Koffie Stroomtest';
const CE_HBA_STROOMTEST_ACTIVITY_PREFIX = 'stroomtest-';
const CE_HBA_STROOMTEST_CHECKPOINTS = [10, 15, 20, 21, 25];

function ce_hba_stroomtest_bundle_path(): string
{
    return dirname(__DIR__, 2) . '/briefing/hba-input/Brandpunt_stroomtest_25.json';
}

/** @return array<string, mixed> */
/** @param array<string, mixed>|null $inline */
function ce_hba_stroomtest_load_bundle(?array $inline = null): array
{
    if (is_array($inline) && $inline !== []) {
        return $inline;
    }
    $path = ce_hba_stroomtest_bundle_path();
    if (!is_readable($path)) {
        throw new RuntimeException(
            'Stroomtest-bundle ontbreekt op server — stuur JSON mee in body.bundle (staging heeft geen briefing/)',
        );
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException('Ongeldige stroomtest JSON');
    }

    return $data;
}

/** @return array<string, mixed> */
function ce_hba_stroomtest_koffie_markthygiene(): array
{
    $path = dirname(__DIR__, 2) . '/briefing/hba-input/HBA_testmerken/Brandpunt_Koffie.json';
    if (!is_readable($path)) {
        throw new RuntimeException('Brandpunt_Koffie.json ontbreekt voor markthygiëne');
    }
    $bundle = json_decode((string) file_get_contents($path), true);
    if (!is_array($bundle['markthygiene'] ?? null)) {
        throw new RuntimeException('markthygiene ontbreekt in Brandpunt_Koffie.json');
    }

    return $bundle['markthygiene'];
}

/** @param array<string, mixed>|null $markthygieneOverride */
function ce_hba_stroomtest_ensure_branche(PDO $pdo, ?array $markthygieneOverride = null): string
{
    if (is_array($markthygieneOverride) && $markthygieneOverride !== []) {
        $row = ce_hba_upsert_markthygiene_branche($pdo, $markthygieneOverride);

        return trim((string) ($row['code'] ?? 'koffie'));
    }

    $existing = ce_hba_fetch_branche_by_code($pdo, 'koffie');
    if ($existing !== null) {
        return 'koffie';
    }

    try {
        $hygiene = ce_hba_stroomtest_koffie_markthygiene();
    } catch (RuntimeException) {
        throw new RuntimeException(
            'Branche koffie ontbreekt — stuur markthygiene uit Brandpunt_Koffie.json mee in body.markthygiene',
        );
    }
    $row = ce_hba_upsert_markthygiene_branche($pdo, $hygiene);

    return trim((string) ($row['code'] ?? 'koffie'));
}

/** @param array<string, mixed> $antwoorden */
function ce_hba_stroomtest_narrative_from_poging(array $antwoorden, bool $full): array
{
    $raw = [
        'identity_intro' => trim((string) ($antwoorden['1_wie_en_branche'] ?? '')),
        'doel' => trim((string) ($antwoorden['2_doel'] ?? '')),
        'distinctiviteit' => trim((string) ($antwoorden['3_wat_anders'] ?? '')),
        'mechanisme' => trim((string) ($antwoorden['3_wat_anders'] ?? '')),
        'contextprofiel' => trim((string) ($antwoorden['contextprofiel'] ?? 'groeiend')) ?: 'groeiend',
        'aanspreekvorm' => 'je',
    ];

    if ($full) {
        $raw['foe'] = trim((string) ($antwoorden['4_waar_niet_in_geloven'] ?? ''));
        $raw['geloofwaardigheidstest'] = trim((string) ($antwoorden['5_regels'] ?? ''));
        $raw['archetype'] = 'Maker';
        $raw['spanning'] = 'Een maker die smaak terug wil brengen in een kopje dat de meeste mensen alleen drinken om wakker te worden.';
        $raw['tone_of_behaviour'] = trim((string) ($antwoorden['toon'] ?? ''));
        $raw['goede_voorbeelden'] = is_array($antwoorden['7_voorbeelden_goed'] ?? null)
            ? array_slice($antwoorden['7_voorbeelden_goed'], 0, 3)
            : [];
        $raw['zwakke_voorbeelden'] = is_array($antwoorden['7_voorbeelden_zwak'] ?? null)
            ? array_slice($antwoorden['7_voorbeelden_zwak'], 0, 3)
            : [];
        $raw['verboden_logica'] = is_array($antwoorden['wat_nooit_over_ons'] ?? null)
            ? $antwoorden['wat_nooit_over_ons']
            : [];
        $raw['bewijsvelden'] = is_array($antwoorden['bewijs'] ?? null) ? $antwoorden['bewijs'] : [];
        $raw['default_platforms'] = ['instagram', 'website_blog', 'newsletter'];
        $raw['target_post_volume_per_month'] = 12;
        $never = is_array($antwoorden['wat_nooit_over_ons'] ?? null)
            ? implode("\n", array_map('strval', $antwoorden['wat_nooit_over_ons']))
            : '';
        $raw['wat_bewust_niet'] = $never;
    }

    $narrative = ce_brand_narrative_from_request($raw);
    if ($full) {
        $narrative = ce_hba_testmerk_enrich_narrative_fields($narrative);
    }

    return $narrative;
}

/** @param array<string, mixed> $antwoorden @return array<string, mixed> */
function ce_hba_stroomtest_architecture_body_from_poging(array $antwoorden, string $brancheCode, bool $full): array
{
    $regelsRaw = trim((string) ($antwoorden['5_regels'] ?? ''));
    $regels = $regelsRaw !== '' ? preg_split('/\R+/u', $regelsRaw) ?: [] : [];

    $goede = is_array($antwoorden['7_voorbeelden_goed'] ?? null) ? $antwoorden['7_voorbeelden_goed'] : [];
    $zwakke = is_array($antwoorden['7_voorbeelden_zwak'] ?? null) ? $antwoorden['7_voorbeelden_zwak'] : [];
    $never = is_array($antwoorden['wat_nooit_over_ons'] ?? null)
        ? implode("\n", array_map('strval', $antwoorden['wat_nooit_over_ons']))
        : '';

    return [
        'archetype' => $full ? 'Maker' : '',
        'doel' => trim((string) ($antwoorden['2_doel'] ?? '')),
        'foe' => trim((string) ($antwoorden['4_waar_niet_in_geloven'] ?? '')),
        'distinctiviteit' => trim((string) ($antwoorden['3_wat_anders'] ?? '')),
        'watBewustNiet' => $full ? $never : '',
        'regels' => array_values(array_filter(array_map('strval', $regels))),
        'toneOfBehaviour' => $full ? trim((string) ($antwoorden['toon'] ?? '')) : '',
        'goedeVoorbeelden' => $full ? $goede : [],
        'zwakkeVoorbeelden' => $full ? $zwakke : [],
        'brancheCode' => $brancheCode,
    ];
}

/** @return array<string, mixed> */
function ce_hba_stroomtest_ensure_client(PDO $pdo, array $options = []): array
{
    $brancheCode = ce_hba_stroomtest_ensure_branche(
        $pdo,
        is_array($options['markthygiene'] ?? null) ? $options['markthygiene'] : null,
    );
    $name = CE_HBA_STROOMTEST_CLIENT_NAME;
    $dup = $pdo->prepare('SELECT id, name FROM clients WHERE LOWER(TRIM(name)) = LOWER(:name) LIMIT 1');
    $dup->execute(['name' => $name]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC);

    if (is_array($existing) && !empty($options['reuseExisting'])) {
        $clientId = (string) ($existing['id'] ?? '');

        return [
            'ok' => true,
            'created' => false,
            'clientId' => $clientId,
            'company' => $name,
            'brancheCode' => $brancheCode,
        ];
    }

    if (is_array($existing) && empty($options['reuseExisting'])) {
        throw new InvalidArgumentException('Client bestaat al: ' . $name . ' — zet reuseExisting=true');
    }

    $slug = 'brandpunt-koffie-stroomtest';
    $createBody = [
        'company' => $name,
        'contactEmail' => $slug . '@igisnext.com',
        'contactName' => 'Brandpunt',
        'contactRole' => 'both',
        'planningHorizonWeeks' => 4,
        'postsPerWeek' => 2,
        'archetype' => '',
        'adversary' => '',
        'brandstory' => '',
    ];
    if (!empty($options['password'])) {
        $createBody['password'] = (string) $options['password'];
    }

    $created = ce_create_client($pdo, $createBody);
    $clientId = (string) ($created['client']['clientId'] ?? $created['client']['id'] ?? '');
    if ($clientId === '') {
        throw new RuntimeException('Client-id ontbreekt na aanmaken');
    }

    if (ce_table_has_column($pdo, 'clients', 'ce01_weekly_schedule')) {
        $pdo->prepare('UPDATE clients SET ce01_weekly_schedule = 0 WHERE id = :id')->execute(['id' => $clientId]);
    }

    return [
        'ok' => true,
        'created' => true,
        'clientId' => $clientId,
        'company' => $name,
        'brancheCode' => $brancheCode,
        'contactEmail' => $createBody['contactEmail'],
    ];
}

/** @return array<string, mixed> */
/** @param array<string, mixed>|null $bundleOverride */
function ce_hba_stroomtest_run_onboarding_poging(
    PDO $pdo,
    string $clientId,
    int $poging,
    ?array $bundleOverride = null,
): array {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
        throw new InvalidArgumentException('Ongeldig clientId');
    }
    if ($poging !== 1 && $poging !== 2) {
        throw new InvalidArgumentException('poging moet 1 of 2 zijn');
    }

    $bundle = ce_hba_stroomtest_load_bundle($bundleOverride);
    $onboarding = is_array($bundle['onboarding'] ?? null) ? $bundle['onboarding'] : [];
    $key = $poging === 1 ? 'poging_1' : 'poging_2';
    $block = is_array($onboarding[$key] ?? null) ? $onboarding[$key] : [];
    $antwoorden = is_array($block['antwoorden'] ?? null) ? $block['antwoorden'] : [];
    if ($antwoorden === []) {
        throw new RuntimeException('Onboarding-antwoorden ontbreken voor ' . $key);
    }

    $markt = is_array($bundle['markthygiene'] ?? null) ? $bundle['markthygiene'] : null;
    $brancheCode = ce_hba_stroomtest_ensure_branche($pdo, $markt);
    $full = $poging === 2;
    $narrative = ce_hba_stroomtest_narrative_from_poging($antwoorden, $full);
    $narrative['branche_code'] = $brancheCode;

    ce_save_brand_narrative($pdo, $clientId, $narrative, 'hba-stroomtest-poging-' . $poging);
    ce_hba_apply_testmerk_tone_defaults($pdo, $clientId, ['type' => 'mkb, consument']);

    $checkBody = $full
        ? ce_hba_v2_architecture_check_body_from_narrative($pdo, $clientId)
        : ce_hba_stroomtest_architecture_body_from_poging($antwoorden, $brancheCode, false);
    if ($checkBody === null) {
        $checkBody = ce_hba_stroomtest_architecture_body_from_poging($antwoorden, $brancheCode, $full);
    }

    $checkResult = ce_hba_v2_run_architecture_check($pdo, $clientId, $checkBody);
    $checkDto = is_array($checkResult['check'] ?? null) ? $checkResult['check'] : [];
    $gate = ce_hba_v2_guardrail_gate($pdo, $clientId);
    $progress = ce_hba_v2_progress($pdo, $clientId);

    $meetlatActief = ($checkDto['meetlatStatus'] ?? '') === 'actief'
        || ($checkDto['totaaloordeel'] ?? '') === 'meetlat_klaar';
    if ($full && ($checkResult['ok'] ?? false) && $meetlatActief) {
        ce_hba_v2_mark_architecture_complete($pdo, $clientId);
        $gate = ce_hba_v2_guardrail_gate($pdo, $clientId);
        $progress = ce_hba_v2_progress($pdo, $clientId);
    }

    return [
        'ok' => true,
        'poging' => $poging,
        'clientId' => $clientId,
        'verwacht' => $block['verwacht'] ?? [],
        'narrativeMissing' => ce_brand_narrative_missing_fields(
            ce_fetch_current_brand_narrative($pdo, $clientId) ?: [],
        ),
        'architectureCheck' => $checkResult,
        'meetlatActief' => $meetlatActief,
        'guardrailGate' => $gate,
        'guardrailActive' => ce_hba_active_guardrail($pdo, $clientId) !== null,
        'latestArchitectuurRow' => ce_hba_v2_latest_architectuur_check($pdo, $clientId),
        'progress' => $progress,
    ];
}

/** @return array{slots: list<string>, linkedinPosts: int, scores: int} */
function ce_hba_stroomtest_delete_posts(PDO $pdo, string $clientId): array
{
    $slotIds = [];
    $stmt = $pdo->prepare(
        "SELECT id FROM content_slots
         WHERE client_id = :client_id
           AND (theme LIKE 'Stroomtest:%' OR phase_key = 'stroomtest')"
    );
    $stmt->execute(['client_id' => $clientId]);
    $slotIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id');

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
        $pdo->prepare("DELETE FROM hba_scores WHERE post_id IN ($ph)")->execute($articleIds);
        $scoresDeleted = count($articleIds);
    }

    foreach ($slotIds as $slotId) {
        $pdo->prepare('DELETE FROM articles WHERE slot_id = :slot_id')->execute(['slot_id' => $slotId]);
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
            'prefix' => CE_HBA_STROOMTEST_ACTIVITY_PREFIX . '%',
        ]);
        $linkedinDeleted = $del->rowCount();
    }

    return [
        'slots' => array_map('strval', $slotIds),
        'linkedinPosts' => $linkedinDeleted,
        'scores' => $scoresDeleted,
    ];
}

function ce_hba_stroomtest_max_imported_nr(PDO $pdo, string $clientId): int
{
    $stmt = $pdo->prepare(
        "SELECT input_human FROM content_slots
         WHERE client_id = :client_id AND theme LIKE 'Stroomtest:%'
         ORDER BY created_at DESC LIMIT 50"
    );
    $stmt->execute(['client_id' => $clientId]);
    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $raw = trim((string) ($row['input_human'] ?? ''));
        if ($raw === '') {
            continue;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            continue;
        }
        $nr = (int) ($meta['stroomtestNr'] ?? 0);
        if ($nr > $max) {
            $max = $nr;
        }
    }

    return $max;
}

/**
 * @param list<array<string, mixed>> $posts
 * @return array<string, mixed>
 */
function ce_hba_stroomtest_import_posts(
    PDO $pdo,
    string $clientId,
    array $posts,
    int $throughNr,
    bool $score = true,
    bool $reset = false,
): array {
    if ($reset) {
        ce_hba_stroomtest_delete_posts($pdo, $clientId);
    }

    $clientStmt = $pdo->prepare('SELECT name FROM clients WHERE id = :id LIMIT 1');
    $clientStmt->execute(['id' => $clientId]);
    $companyName = trim((string) ($clientStmt->fetchColumn() ?: CE_HBA_STROOMTEST_CLIENT_NAME));

    $sorted = array_values(array_filter($posts, static function (array $post) use ($throughNr): bool {
        $nr = (int) ($post['nr'] ?? 0);

        return $nr >= 1 && $nr <= $throughNr;
    }));
    usort($sorted, static fn (array $a, array $b): int => ((int) ($a['nr'] ?? 0)) <=> ((int) ($b['nr'] ?? 0)));

    $already = ce_hba_stroomtest_max_imported_nr($pdo, $clientId);
    $toImport = array_values(array_filter(
        $sorted,
        static fn (array $post): bool => (int) ($post['nr'] ?? 0) > $already,
    ));

    $gate = ce_hba_ensure_testmerk_scoring_gate($pdo, $clientId, $companyName, 'koffie');
    $guardrailActive = !empty($gate['guardrailActive']);
    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    $canScore = $guardrailActive
        && is_array($narrative)
        && ce_brand_narrative_missing_fields($narrative) === [];

    if (!$canScore && $score) {
        return [
            'ok' => false,
            'code' => 'scoring_blocked',
            'guardrailActive' => $guardrailActive,
            'missingNarrative' => is_array($narrative)
                ? ce_brand_narrative_missing_fields($narrative)
                : CE_HBA_NARRATIVE_FIELDS,
            'gate' => $gate,
        ];
    }

    ce_ensure_slot_pipeline_statuses($pdo);
    ce_ensure_headline_human_column($pdo);
    ce_ensure_content_human_column($pdo);
    ce_hba_ensure_content_slots_origin_varchar($pdo);

    $sourceId = ce_hba_ensure_testmerk_linkedin_source($pdo, $clientId, $companyName);
    $imported = [];
    $scored = 0;
    $scoreErrors = [];

    foreach ($toImport as $post) {
        $nr = (int) ($post['nr'] ?? 0);
        $text = trim((string) ($post['post_tekst'] ?? ''));
        if ($text === '' || $nr < 1) {
            continue;
        }

        $datum = trim((string) ($post['datum'] ?? ''));
        $postedAt = $datum !== '' ? ($datum . ' 12:00:00') : date('Y-m-d H:i:s');
        $platform = ce_hba_testpost_platform_db((string) ($post['platform'] ?? 'instagram'));
        $label = 'Stroom ' . $nr;
        $headline = 'Stroomtest: ' . $label;
        $body = $text;

        $slotId = ce_uuid_v4();
        $linkedinPostId = ce_uuid_v4();
        $articleId = ce_uuid_v4();
        $activityUrn = CE_HBA_STROOMTEST_ACTIVITY_PREFIX . str_pad((string) $nr, 2, '0', STR_PAD_LEFT);
        $slotMarker = json_encode([
            'stroomtest' => true,
            'stroomtestNr' => $nr,
            'datum' => $datum,
            'platform' => $platform,
            'verwacht_hoofdoordeel' => (string) ($post['verwacht_hoofdoordeel'] ?? ''),
            'contentrol_verwacht' => (string) ($post['contentrol_verwacht'] ?? ''),
        ], JSON_THROW_ON_ERROR);

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
            $slotParams['phase_key'] = 'stroomtest';
        }
        if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
            $slotCols[] = 'origin';
            $slotVals[] = ':origin';
            $slotParams['origin'] = 'normal';
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
        if (ce_table_has_column($pdo, 'articles', 'published_at')) {
            $artCols[] = 'published_at';
            $artVals[] = ':published_at';
            $artParams['published_at'] = $postedAt;
        }
        if (ce_table_has_column($pdo, 'articles', 'platform_final')) {
            $artCols[] = 'platform_final';
            $artVals[] = ':platform_final';
            $artParams['platform_final'] = $platform === 'blog' ? 'instagram' : $platform;
        }

        $pdo->prepare(
            'INSERT INTO articles (' . implode(', ', $artCols) . ') VALUES (' . implode(', ', $artVals) . ')'
        )->execute($artParams);

        $scoreOk = false;
        if ($canScore && $score && is_array($narrative)) {
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
            'nr' => $nr,
            'articleId' => $articleId,
            'datum' => $datum,
            'scored' => $scoreOk,
        ];
    }

    return [
        'ok' => true,
        'clientId' => $clientId,
        'throughNr' => $throughNr,
        'importedCount' => count($imported),
        'scoredCount' => $scored,
        'totalPostsNow' => ce_hba_stroomtest_max_imported_nr($pdo, $clientId),
        'scoreErrors' => $scoreErrors,
        'imported' => $imported,
    ];
}

/** @return list<array<string, mixed>> */
function ce_hba_stroomtest_scored_posts(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'hba_scores')) {
        return [];
    }

    $flagSelect = ce_table_has_column($pdo, 'hba_scores', 'architecture_risk_flags')
        ? 'hs.architecture_risk_flags'
        : '\'[]\' AS architecture_risk_flags';
    $rawSelect = ce_table_has_column($pdo, 'hba_scores', 'raw_llm_response')
        ? 'hs.raw_llm_response'
        : 'NULL AS raw_llm_response';

    $stmt = $pdo->prepare(
        "SELECT cs.input_human, cs.theme, hs.contentrol, hs.brand_contribution_score,
                hs.brand_consistency_score, {$flagSelect}, {$rawSelect},
                a.published_at, hs.scored_at
         FROM hba_scores hs
         INNER JOIN articles a ON a.id = hs.post_id
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND cs.theme LIKE 'Stroomtest:%'
         ORDER BY COALESCE(a.published_at, hs.scored_at) ASC"
    );
    $stmt->execute(['client_id' => $clientId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $meta = json_decode((string) ($row['input_human'] ?? ''), true);
        $flags = ce_hba_decode_json_list($row['architecture_risk_flags'] ?? []);
        $hoofdMeta = ce_hba_hoofdoordeel_dto_fields($row['raw_llm_response'] ?? null);
        $out[] = [
            'nr' => is_array($meta) ? (int) ($meta['stroomtestNr'] ?? 0) : 0,
            'datum' => is_array($meta) ? (string) ($meta['datum'] ?? '') : '',
            'hoofdoordeel' => (string) ($hoofdMeta['hoofdoordeel'] ?? ''),
            'hoofdoordeelKlantlabel' => (string) ($hoofdMeta['hoofdoordeelKlantlabel'] ?? ''),
            'contentrol' => (string) ($row['contentrol'] ?? ''),
            'brandContribution' => $row['brand_contribution_score'] !== null
                ? (float) $row['brand_contribution_score']
                : null,
            'flags' => $flags,
            'hasRiskFlag' => $flags !== [],
        ];
    }

    return $out;
}

/** @return array<string, mixed> */
function ce_hba_stroomtest_rescore_missing(PDO $pdo, string $clientId): array
{
    if (!ce_table_exists($pdo, 'hba_scores') || !ce_table_exists($pdo, 'articles')) {
        return ['ok' => false, 'error' => 'schema ontbreekt'];
    }

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!is_array($narrative)) {
        return ['ok' => false, 'error' => 'geen narrative'];
    }

    $stmt = $pdo->prepare(
        "SELECT a.id AS article_id, cs.input_human, lp.id AS linkedin_post_id, lp.posted_at, a.content_ai
         FROM content_slots cs
         INNER JOIN articles a ON a.slot_id = cs.id
         LEFT JOIN linkedin_posts lp ON lp.article_id = a.id
         LEFT JOIN hba_scores hs ON hs.post_id = a.id
         WHERE cs.client_id = :client_id
           AND cs.theme LIKE 'Stroomtest:%'
           AND hs.post_id IS NULL"
    );
    $stmt->execute(['client_id' => $clientId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $scored = [];
    $errors = [];

    foreach ($rows as $row) {
        $meta = json_decode((string) ($row['input_human'] ?? ''), true);
        $nr = is_array($meta) ? (int) ($meta['stroomtestNr'] ?? 0) : 0;
        $text = trim((string) ($row['content_ai'] ?? ''));
        $articleId = (string) ($row['article_id'] ?? '');
        $lpId = (string) ($row['linkedin_post_id'] ?? '');
        $postedAt = (string) ($row['posted_at'] ?? '');
        if ($articleId === '' || $text === '') {
            continue;
        }
        try {
            ce_hba_score_baseline_post(
                $pdo,
                $clientId,
                $articleId,
                ['id' => $lpId !== '' ? $lpId : ce_uuid_v4(), 'posted_at' => $postedAt],
                $narrative,
                $text,
            );
            $scored[] = $nr;
        } catch (Throwable $e) {
            $errors[] = ['nr' => $nr, 'error' => $e->getMessage()];
        }
    }

    return [
        'ok' => true,
        'rescoredNrs' => $scored,
        'errors' => $errors,
    ];
}

/** @param list<array<string, mixed>> $posts */
function ce_hba_stroomtest_analyze_window(array $posts, int $fromNr, int $toNr): array
{
    $window = array_values(array_filter(
        $posts,
        static fn (array $p): bool => (int) ($p['nr'] ?? 0) >= $fromNr && (int) ($p['nr'] ?? 0) <= $toNr,
    ));
    $riskNrs = [];
    foreach ($window as $p) {
        if (!empty($p['hasRiskFlag'])) {
            $riskNrs[] = (int) ($p['nr'] ?? 0);
        }
    }

    $hoofdCounts = [];
    foreach ($window as $p) {
        $h = (string) ($p['hoofdoordeel'] ?? '');
        if ($h !== '') {
            $hoofdCounts[$h] = ($hoofdCounts[$h] ?? 0) + 1;
        }
    }

    $roleCounts = [];
    foreach ($window as $p) {
        $r = (string) ($p['contentrol'] ?? '');
        if ($r !== '') {
            $roleCounts[$r] = ($roleCounts[$r] ?? 0) + 1;
        }
    }

    return [
        'fromNr' => $fromNr,
        'toNr' => $toNr,
        'postCount' => count($window),
        'riskFlagCount' => count($riskNrs),
        'riskFlagPostNrs' => $riskNrs,
        'hoofdoordeelCounts' => $hoofdCounts,
        'contentrolCounts' => $roleCounts,
    ];
}

/** @return array<string, mixed> */
function ce_hba_stroomtest_checkpoint_snapshot(PDO $pdo, string $clientId, int $afterPostNr): array
{
    $progress = ce_hba_v2_progress($pdo, $clientId);
    $phase = (string) ($progress['phase'] ?? 'A');
    $pbs = ce_hba_v2_compute_pbs_metrics($pdo, $clientId, $phase);
    $portfolio = ce_agenda_portfolio_signals($pdo, $clientId);
    $portfolioHeaviest = ce_agenda_portfolio_heaviest_signal($pdo, $clientId);
    $homeResult = ce_fetch_home_result($pdo, $clientId);
    $posts = ce_hba_stroomtest_scored_posts($pdo, $clientId);

    $analysis = match ($afterPostNr) {
        10 => [
            'window' => ce_hba_stroomtest_analyze_window($posts, 1, 10),
            'rogierWindow' => 'posts 1–10 (basislijn)',
        ],
        15 => [
            'window' => ce_hba_stroomtest_analyze_window($posts, 6, 15),
            'rogierWindow' => 'laatste 10 vóór post 15 = posts 6–15',
        ],
        20 => [
            'window' => ce_hba_stroomtest_analyze_window($posts, 11, 20),
            'rogierWindow' => 'posts 11–20',
        ],
        21 => [
            'window' => ce_hba_stroomtest_analyze_window($posts, 11, 21),
            'rogierWindow' => 'herstelpost 21; alerts blijven',
        ],
        25 => [
            'window' => ce_hba_stroomtest_analyze_window($posts, 16, 25),
            'rogierWindow' => 'posts 16–25',
        ],
        default => ['window' => ce_hba_stroomtest_analyze_window($posts, 1, $afterPostNr)],
    };

    return [
        'ok' => true,
        'afterPostNr' => $afterPostNr,
        'scoredPosts' => $posts,
        'analysis' => $analysis,
        'progress' => $progress,
        'pbs' => $pbs,
        'portfolioSignals' => $portfolio,
        'portfolioHeaviest' => $portfolioHeaviest,
        'homeResult' => $homeResult,
        'appThresholdsNote' => ce_hba_stroomtest_threshold_notes(),
    ];
}

/** @return array<string, string> */
function ce_hba_stroomtest_threshold_notes(): array
{
    return [
        'risicopatroon_kit' => 'Implementation Kit: 3 of 5 risicoflags in laatste 10 posts.',
        'risicopatroon_app' => 'App: geen aparte risicopatroon-meter; flags zitten in architecture_risk_flags per post; Home/Agenda gebruiken portfolio-signalen (declining_line, skewed_distribution, …).',
        'pbs_erosion_kit' => 'Kit: daling portfoliobalans 8 (licht) of 15 (zwaar) punten t.o.v. basislijn.',
        'pbs_erosion_app' => 'App: ce_hba_v2_compute_pbs_metrics — erosionAlert alleen in fase C, ≥3 maanden history, recent < priorAvg − 10 punten (maandgemiddelde positioning score).',
        'contentrol_kit' => 'Kit: aandeel één contentrol >50% of >65%.',
        'contentrol_app' => 'App: skewed_distribution als ≥4 van laatste 6 posts dezelfde contentrol hebben.',
        'declining_trend' => 'App declining_line: gemiddelde merkbijdrage laatste 6 posts < vorige 6 (drempel −0,05).',
    ];
}

/** @return array<string, mixed> */
function ce_hba_stroomtest_run_phase(PDO $pdo, array $body): array
{
    $phase = trim((string) ($body['phase'] ?? ''));
    $clientId = trim((string) ($body['clientId'] ?? ''));
    $throughNr = max(1, min(25, (int) ($body['throughPost'] ?? 25)));
    $score = !array_key_exists('score', $body) || !empty($body['score']);
    $resetPosts = !empty($body['resetPosts']);
    $bundle = is_array($body['bundle'] ?? null) ? $body['bundle'] : null;

    return match ($phase) {
        'ensure_client' => ce_hba_stroomtest_ensure_client($pdo, [
            'reuseExisting' => !empty($body['reuseExisting']),
            'password' => (string) ($body['password'] ?? ''),
            'markthygiene' => is_array($body['markthygiene'] ?? null) ? $body['markthygiene'] : null,
        ]),
        'onboarding_poging_1', 'onboarding_poging1' => ce_hba_stroomtest_run_onboarding_poging($pdo, $clientId, 1, $bundle),
        'onboarding_poging_2', 'onboarding_poging2' => ce_hba_stroomtest_run_onboarding_poging($pdo, $clientId, 2, $bundle),
        'import_posts' => ce_hba_stroomtest_import_posts(
            $pdo,
            $clientId,
            is_array($body['posts'] ?? null)
                ? $body['posts']
                : (ce_hba_stroomtest_load_bundle($bundle)['posts'] ?? []),
            $throughNr,
            $score,
            $resetPosts,
        ),
        'checkpoint' => ce_hba_stroomtest_checkpoint_snapshot(
            $pdo,
            $clientId,
            max(1, (int) ($body['afterPost'] ?? $throughNr)),
        ),
        'rescore_missing' => ce_hba_stroomtest_rescore_missing($pdo, $clientId),
        'full_pipeline' => ce_hba_stroomtest_full_pipeline($pdo, $body),
        default => throw new InvalidArgumentException(
            'Onbekende phase: ' . $phase . ' (ensure_client, onboarding_poging_1/2, import_posts, checkpoint, full_pipeline)',
        ),
    };
}

/** @return array<string, mixed> */
function ce_hba_stroomtest_full_pipeline(PDO $pdo, array $body): array
{
    $bundle = is_array($body['bundle'] ?? null) ? $body['bundle'] : null;
    $ensure = ce_hba_stroomtest_ensure_client($pdo, [
        'reuseExisting' => !empty($body['reuseExisting']),
        'password' => (string) ($body['password'] ?? ''),
        'markthygiene' => is_array($body['markthygiene'] ?? null) ? $body['markthygiene'] : null,
    ]);
    $clientId = (string) ($ensure['clientId'] ?? '');
    $p1 = ce_hba_stroomtest_run_onboarding_poging($pdo, $clientId, 1, $bundle);
    $p2 = ce_hba_stroomtest_run_onboarding_poging($pdo, $clientId, 2, $bundle);

    $checkpoints = [];
    $importLog = [];

    if (empty($p2['meetlatActief']) && empty($p2['guardrailActive'])) {
        return [
            'ok' => false,
            'code' => 'onboarding_blocked',
            'ensure' => $ensure,
            'poging1' => $p1,
            'poging2' => $p2,
            'message' => 'Poging 2 gaf geen meetlat_klaar — stroomtest niet gestart (Rogier-regel).',
        ];
    }

    $posts = ce_hba_stroomtest_load_bundle($bundle)['posts'] ?? [];
    $checkpointsToRun = CE_HBA_STROOMTEST_CHECKPOINTS;
    $lastNr = 0;
    foreach ($checkpointsToRun as $cp) {
        $import = ce_hba_stroomtest_import_posts($pdo, $clientId, $posts, $cp, true, false);
        $importLog[] = $import;
        $lastNr = $cp;
        $checkpoints[(string) $cp] = ce_hba_stroomtest_checkpoint_snapshot($pdo, $clientId, $cp);
    }

    if ($lastNr < 25) {
        $import = ce_hba_stroomtest_import_posts($pdo, $clientId, $posts, 25, true, false);
        $importLog[] = $import;
    }

    return [
        'ok' => true,
        'ensure' => $ensure,
        'poging1' => $p1,
        'poging2' => $p2,
        'importLog' => $importLog,
        'checkpoints' => $checkpoints,
        'promptVersion' => defined('CE_HBA_PROMPT_VERSION') ? CE_HBA_PROMPT_VERSION : null,
    ];
}
