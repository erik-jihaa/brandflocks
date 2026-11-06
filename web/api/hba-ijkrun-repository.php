<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-branche-seed.php';

/** Sandbox-tenant voor ijkruns — geen productie-klanten. */
const CE_HBA_IJK_CLIENT_NAME = 'HBA Ijkrun sandbox';

const CE_HBA_IJK_ARCHITECTURE_PROMPT = 'hba-architectuurcheck-wizard-v2';

/** @return list<string> */
function ce_hba_ijk_json_paths(): array
{
    return [
        __DIR__ . '/../data/HBA_ijkset_v0_3.json',
        dirname(__DIR__, 2) . '/briefing/hba-input/HBA_ijkset_v0_3.json',
        dirname(__DIR__, 2) . '/briefing/hba-input/Voor Erik 2026-10-16/HBA_ijkset_v0_3.json',
        __DIR__ . '/../data/HBA_ijkset_v0_2.json',
        dirname(__DIR__, 2) . '/briefing/hba-input/HBA_ijkset_v0_2.json',
    ];
}

/** @return array<string, mixed> */
function ce_hba_ijk_load_set(): array
{
    foreach (ce_hba_ijk_json_paths() as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (is_array($raw) && is_array($raw['ijkgevallen'] ?? null)) {
            $raw['_sourcePath'] = $path;

            return $raw;
        }
    }

    throw new RuntimeException('HBA_ijkset niet gevonden (v0.3 of v0.2 in web/data of briefing/hba-input)');
}

/** @param array<string, mixed> $set */
function ce_hba_ijk_set_version(array $set): string
{
    return trim((string) ($set['ijkset_versie'] ?? '0.2'));
}

/**
 * @param array<string, mixed> $case
 * @return array{verwacht_oordeel: ?string, verwacht_meetlat: ?string}
 */
function ce_hba_ijk_case_expectations(array $case): array
{
    $meetlat = trim((string) ($case['verwacht_meetlat'] ?? ''));
    $oordeel = $case['verwacht_oordeel'] ?? null;
    if (is_string($oordeel)) {
        $oordeel = trim($oordeel) !== '' ? trim($oordeel) : null;
    }

    return [
        'verwacht_oordeel' => is_string($oordeel) ? $oordeel : (is_scalar($oordeel) ? (string) $oordeel : null),
        'verwacht_meetlat' => $meetlat !== '' ? $meetlat : null,
    ];
}

/**
 * Afgeleide meetlat t.o.v. v0.3 (stap 0 — volledige poort komt in stap 1).
 *
 * @param array<string, mixed> $check architectuurcheck DTO
 */
function ce_hba_ijk_derive_meetlat_status(array $check): ?string
{
    $explicit = (string) ($check['meetlatStatus'] ?? '');
    if ($explicit === 'actief' || $explicit === 'geblokkeerd') {
        return $explicit;
    }
    if (array_key_exists('guardrailActive', $check)) {
        return !empty($check['guardrailActive']) ? 'actief' : 'geblokkeerd';
    }
    $totaal = (string) ($check['totaaloordeel'] ?? '');
    if ($totaal === 'identity_gate_blocked' || $totaal === '') {
        return 'geblokkeerd';
    }
    if ($totaal === 'meetlat_klaar') {
        return 'actief';
    }
    if (in_array($totaal, ['meetlat_bruikbaar_met_waarschuwing', 'meetlat_te_zwak'], true)) {
        return 'geblokkeerd';
    }

    return null;
}

/**
 * @param array<string, mixed> $case
 * @return array{judgment: string, reasons: list<string>}
 */
function ce_hba_judge_ijk_meetlat(array $case, ?string $meetlatStatus): array
{
    $verwacht = ce_hba_ijk_case_expectations($case)['verwacht_meetlat'];
    if ($verwacht === null) {
        return ['judgment' => 'n.v.t.', 'reasons' => []];
    }
    if ($meetlatStatus === null || $meetlatStatus === '') {
        return ['judgment' => 'onbekend', 'reasons' => ['Meetlatstatus kon niet worden afgeleid']];
    }
    if ($meetlatStatus !== $verwacht) {
        return [
            'judgment' => 'fout',
            'reasons' => ['meetlat ' . $meetlatStatus . ' (verwacht: ' . $verwacht . ')'],
        ];
    }

    return ['judgment' => 'goed', 'reasons' => []];
}

/**
 * @param array<string, mixed> $case
 * @return array<string, mixed>
 */
function ce_hba_ijk_inventory_row(array $case): array
{
    $expect = ce_hba_ijk_case_expectations($case);
    $soort = (string) ($case['soort'] ?? '');

    return [
        'ijkgeval_id' => (string) ($case['id'] ?? ''),
        'naam' => (string) ($case['naam'] ?? ''),
        'soort' => $soort,
        'valkuil' => !empty($case['valkuil']),
        'runnable' => ce_hba_ijk_soort_runnable($soort),
        'verwacht_oordeel' => $expect['verwacht_oordeel'],
        'verwacht_meetlat' => $expect['verwacht_meetlat'],
    ];
}

/** @param array<string, mixed> $set */
function ce_hba_ijk_case_by_id(array $set, string $id): ?array
{
    $id = strtoupper(trim($id));
    foreach ($set['ijkgevallen'] as $case) {
        if (!is_array($case)) {
            continue;
        }
        if (strtoupper((string) ($case['id'] ?? '')) === $id) {
            return $case;
        }
    }

    return null;
}

function ce_hba_ijk_soort_runnable(string $soort): bool
{
    return in_array($soort, ['architectuurcheck', 'tekstscore', 'schrijfadvies', 'verleidingsthema'], true);
}

/**
 * Genormaliseerde ijk-input (v0.3 merkarchitectuur-blok).
 *
 * @param array<string, mixed> $input
 * @return array{merk: string, branche: string, wie: string, doel: string, wat_anders: string, niet_in_geloven: string, regels: string, bewijsvelden: list<string>}
 */
function ce_hba_ijk_normalized_arch(array $input): array
{
    $bewijs = $input['bewijsvelden'] ?? [];
    if (!is_array($bewijs)) {
        $bewijs = [];
    }

    return [
        'merk' => trim((string) ($input['merk'] ?? '')),
        'branche' => trim((string) ($input['branche'] ?? '')),
        'wie' => trim((string) ($input['wie'] ?? '')),
        'doel' => trim((string) ($input['doel'] ?? '')),
        'wat_anders' => trim((string) ($input['wat_anders'] ?? '')),
        'niet_in_geloven' => trim((string) ($input['niet_in_geloven'] ?? '')),
        'regels' => trim((string) ($input['regels'] ?? '')),
        'bewijsvelden' => array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $bewijs,
        ))),
    ];
}

/** @param array<string, mixed> $arch merkarchitectuur uit ijk */
function ce_hba_ijk_infer_archetype(PDO $pdo, array $arch): string
{
    require_once __DIR__ . '/hba-architectuur-check-repository.php';
    ce_hba_seed_branche_library($pdo);
    $label = trim((string) ($arch['branche'] ?? ''));
    $row = $label !== '' ? ce_hba_fetch_branche_by_label($pdo, $label) : null;
    $candidate = trim((string) ($row['markt_archetype'] ?? 'Maker'));

    return in_array($candidate, CE_HBA_ARCHETYPES, true) ? $candidate : 'Maker';
}

/** @param array<string, mixed> $arch */
function ce_hba_ijk_resolve_branche_code(PDO $pdo, array $arch): string
{
    require_once __DIR__ . '/hba-architectuur-check-repository.php';
    ce_hba_seed_branche_library($pdo);
    $label = trim((string) ($arch['branche'] ?? ''));
    if ($label === '') {
        return 'ijkrun_generic';
    }
    $row = ce_hba_fetch_branche_by_label($pdo, $label);
    if (is_array($row) && trim((string) ($row['branche_code'] ?? '')) !== '') {
        return (string) $row['branche_code'];
    }

    return ce_hba_branche_unique_code($pdo, $label);
}

/**
 * Ijkset → wizard/brand_narrative (stap 7). Alleen ijk-velden; rest leeg.
 * Zelfde afleiding als na Opslaan in Merkarchitectuur: enrich_scoring_fields, geen testmerk-stubs.
 *
 * @param array<string, mixed> $arch ruwe ijk-input of genormaliseerd blok
 * @return array<string, mixed>
 */
function ce_hba_ijk_narrative_from_wizard_fields(PDO $pdo, array $arch): array
{
    require_once __DIR__ . '/hba-repository.php';

    $n = ce_hba_ijk_normalized_arch($arch);
    $brancheCode = ce_hba_ijk_resolve_branche_code($pdo, ['branche' => $n['branche']]);

    $narrative = ce_brand_narrative_from_request([
        'archetype' => '',
        'doel' => $n['doel'],
        'foe' => $n['niet_in_geloven'],
        'spanning' => '',
        'mechanisme' => $n['wat_anders'],
        'distinctiviteit' => $n['wat_anders'],
        'watBewustNiet' => '',
        'geloofwaardigheidstest' => $n['regels'],
        'verbodenLogica' => [],
        'bewijsvelden' => $n['bewijsvelden'],
        'toneOfBehaviour' => '',
        'woordenDiePassen' => [],
        'woordenDieNietPassen' => [],
        'goedeVoorbeelden' => [],
        'zwakkeVoorbeelden' => [],
        'contextprofiel' => '',
        'brancheCode' => $brancheCode,
        'identityIntro' => $n['wie'],
        'kernformuleringen' => [],
        'aanspreekvorm' => '',
    ]);

    ce_brand_narrative_enrich_scoring_fields($narrative);

    return $narrative;
}

/** @deprecated alias — gebruik ce_hba_ijk_narrative_from_wizard_fields */
function ce_hba_ijk_narrative_from_merkarchitectuur(PDO $pdo, array $arch): array
{
    return ce_hba_ijk_narrative_from_wizard_fields($pdo, $arch);
}

function ce_hba_ijk_ensure_sandbox_client(PDO $pdo): string
{
    require_once __DIR__ . '/account-repository.php';

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE TRIM(name) = :name LIMIT 1');
    $stmt->execute(['name' => CE_HBA_IJK_CLIENT_NAME]);
    $existing = (string) ($stmt->fetchColumn() ?: '');
    if ($existing !== '') {
        return $existing;
    }

    $slug = 'hba-ijkrun-sandbox';
    $created = ce_create_client($pdo, [
        'company' => CE_HBA_IJK_CLIENT_NAME,
        'contactEmail' => $slug . '@igisnext.com',
        'contactName' => 'Ijkrun',
        'contactRole' => 'both',
        'planningHorizonWeeks' => 4,
        'postsPerWeek' => 1,
        'archetype' => 'Maker',
        'adversary' => 'Generieke marketingtaal',
        'brandstory' => 'Interne sandbox voor HBA ijkset — geen klantcontent.',
        'toneOfVoice' => 'Nuchter en concreet.',
        'writingGuide' => 'Nuchter en concreet.',
    ]);

    $clientId = (string) ($created['client']['clientId'] ?? $created['client']['id'] ?? '');
    if ($clientId === '') {
        throw new RuntimeException('Sandbox-client kon niet worden aangemaakt');
    }

    return $clientId;
}

/**
 * @param array<string, mixed> $narrative
 * @param array{allowIncompleteNarrative?: bool} $options
 */
function ce_hba_ijk_prepare_client_for_case(PDO $pdo, string $clientId, array $narrative, array $options = []): array
{
    require_once __DIR__ . '/hba-testmerken-repository.php';

    $allowIncomplete = !empty($options['allowIncompleteNarrative']);

    ce_save_brand_narrative($pdo, $clientId, $narrative, 'hba-ijkrun');
    $brancheCode = trim((string) ($narrative['branche_code'] ?? ''));
    $gate = ce_hba_ensure_testmerk_scoring_gate(
        $pdo,
        $clientId,
        CE_HBA_IJK_CLIENT_NAME,
        $brancheCode !== '' ? $brancheCode : null,
    );

    $saved = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!is_array($saved)) {
        throw new RuntimeException('Merkverhaal kon niet worden opgeslagen');
    }
    if (!$allowIncomplete) {
        $missing = ce_brand_narrative_missing_fields($saved);
        if ($missing !== []) {
            throw new RuntimeException('Merkverhaal onvolledig voor ijk: ' . implode(', ', $missing));
        }
    }

    return [
        'narrative' => $saved,
        'brancheCode' => $gate['brancheCode'] ?? $brancheCode,
        'guardrailActive' => !empty($gate['guardrailActive']),
        'narrativeIncomplete' => $allowIncomplete ? ce_brand_narrative_missing_fields($saved) : [],
    ];
}

/** @return array<string, mixed> */
function ce_hba_ijk_score_tekst(PDO $pdo, string $clientId, array $narrativeRow, string $ijkId, string $postText): array
{
    require_once __DIR__ . '/hba-baseline-repository.php';
    require_once __DIR__ . '/hba-prompt.php';

    $articleId = sprintf(
        '00000000-0000-4000-8000-%012s',
        substr(preg_replace('/[^a-f0-9]/i', '', md5('ijk:' . $ijkId)) ?? '', 0, 12),
    );

    $pdo->prepare('DELETE FROM hba_scores WHERE post_id = :post_id')
        ->execute(['post_id' => $articleId]);

    $scoreRow = ce_hba_score_baseline_post(
        $pdo,
        $clientId,
        $articleId,
        ['id' => '', 'posted_at' => gmdate('Y-m-d H:i:s')],
        $narrativeRow,
        $postText,
    );

    $scoreId = (string) ($scoreRow['id'] ?? '');
    if ($scoreId !== '' && ce_table_has_column($pdo, 'hba_scores', 'origin')) {
        $pdo->prepare("UPDATE hba_scores SET origin = 'ijkrun' WHERE id = :id")
            ->execute(['id' => $scoreId]);
    }

    if ($scoreId === '') {
        return $scoreRow;
    }
    $fetch = $pdo->prepare('SELECT * FROM hba_scores WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $scoreId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : $scoreRow;
}

/**
 * Wizard-architectuurcheck-body uit ijk-input (stap 7).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ce_hba_ijk_wizard_architecture_body_from_input(PDO $pdo, array $input): array
{
    $n = ce_hba_ijk_normalized_arch($input);
    $brancheCode = ce_hba_ijk_resolve_branche_code($pdo, ['branche' => $n['branche']]);
    $regelsLine = $n['regels'];

    return [
        'archetype' => '',
        'doel' => $n['doel'],
        'foe' => $n['niet_in_geloven'],
        'distinctiviteit' => $n['wat_anders'],
        'watBewustNiet' => '',
        'regels' => $regelsLine !== '' ? [$regelsLine] : [],
        'brancheCode' => $brancheCode,
        'brancheLabel' => $n['branche'],
        'merknaam' => $n['merk'] !== '' ? $n['merk'] : 'Ijkgeval',
        'identityIntro' => $n['wie'],
        'toneOfBehaviour' => '',
        'goedeVoorbeelden' => [],
        'zwakkeVoorbeelden' => [],
    ];
}

/** @param array<string, mixed> $case */
function ce_hba_ijk_architecture_body_from_case(PDO $pdo, array $case): array
{
    $input = is_array($case['input'] ?? null) ? $case['input'] : [];

    return ce_hba_ijk_wizard_architecture_body_from_input($pdo, $input);
}

function ce_hba_ijk_ensure_hint_article(PDO $pdo, string $clientId, string $ijkId, string $conceptText): string
{
    require_once __DIR__ . '/hba-baseline-repository.php';

    $articleId = sprintf(
        '00000000-0000-4001-8000-%012s',
        substr(preg_replace('/[^a-f0-9]/i', '', md5('ijk-hint:' . $ijkId)) ?? '', 0, 12),
    );
    $slotId = sprintf(
        '00000000-0000-4002-8000-%012s',
        substr(preg_replace('/[^a-f0-9]/i', '', md5('ijk-slot:' . $ijkId)) ?? '', 0, 12),
    );

    $headline = ce_hba_baseline_headline_from_text($conceptText);
    $body = trim($conceptText);
    $status = ce_db_status_for_ui($pdo, 'articles', 'draft');

    $pdo->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $articleId]);
    $pdo->prepare('DELETE FROM content_slots WHERE id = :id')->execute(['id' => $slotId]);

    $slotCols = ['id', 'client_id', 'theme', 'status'];
    $slotVals = [':id', ':client_id', ':theme', ':status'];
    $slotParams = [
        'id' => $slotId,
        'client_id' => $clientId,
        'theme' => 'HBA-ijk:' . $ijkId,
        'status' => ce_db_status_for_ui($pdo, 'content_slots', 'draft'),
    ];
    if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
        $slotCols[] = 'origin';
        $slotVals[] = ':origin';
        $slotParams['origin'] = 'ijkrun';
    }
    $pdo->prepare(
        'INSERT INTO content_slots (' . implode(', ', $slotCols) . ') VALUES (' . implode(', ', $slotVals) . ')'
    )->execute($slotParams);

    $artCols = ['id', 'slot_id', 'headline', 'content_ai', 'status'];
    $artVals = [':id', ':slot_id', ':headline', ':content_ai', ':status'];
    $artParams = [
        'id' => $articleId,
        'slot_id' => $slotId,
        'headline' => $headline,
        'content_ai' => $body,
        'status' => $status,
    ];
    if (ce_table_has_column($pdo, 'articles', 'content_human')) {
        $artCols[] = 'content_human';
        $artVals[] = ':content_human';
        $artParams['content_human'] = $body;
    }
    $pdo->prepare(
        'INSERT INTO articles (' . implode(', ', $artCols) . ') VALUES (' . implode(', ', $artVals) . ')'
    )->execute($artParams);

    return $articleId;
}

/** @param array<string, mixed> $case */
function ce_hba_ijk_tekstscore_expected(array $case): array
{
    $oordeel = trim((string) ($case['verwacht_oordeel'] ?? ''));
    $id = strtoupper((string) ($case['id'] ?? ''));

    $hoofd = match ($oordeel) {
        'sterk' => 'op_merk',
        'neutraal' => 'neutraal',
        'zwak' => 'op_merk',
        'middelmatig' => 'categorietaal',
        'regelbreuk' => 'regelbreuk',
        'tegenkracht' => 'tegenkracht',
        default => null,
    };

    $expected = [
        'verwacht' => $oordeel,
        'ambiguous' => false,
        'verwacht_hoofdoordeel' => $hoofd,
        'verwacht_flags' => [],
        'verwacht_flags_een_van' => [],
        'verboden_flags' => [],
        'max_brand_contribution' => $oordeel === 'zwak' ? 5.0 : null,
        'verwacht_niveau' => $oordeel === 'zwak' ? 'laag' : null,
    ];

    if ($id === 'T13') {
        $expected['verwacht_hoofdoordeel'] = 'tegenkracht';
        $expected['verwacht_flags_een_van'] = ['proof_gap', 'foe_vervaging'];
        $expected['verboden_flags'] = ['constraint_breach'];
    } elseif ($id === 'T14') {
        $expected['verwacht_flags'] = ['proof_gap'];
    } elseif ($id === 'T12') {
        $expected['verwacht_hoofdoordeel'] = 'tegenkracht';
        $expected['verwacht_flags_een_van'] = ['foe_vervaging', 'proof_gap'];
    } elseif ($id === 'T04' || $id === 'T09') {
        $expected['verwacht_hoofdoordeel'] = 'regelbreuk';
        $expected['verwacht_flags'] = ['constraint_breach'];
    }

    return $expected;
}

/**
 * @param array<string, mixed> $case
 * @param array<string, mixed> $actual
 * @return array{judgment: string, reasons: list<string>, warnings: list<string>}
 */
function ce_hba_judge_ijk_tekstscore(array $case, array $actual, string $postText): array
{
    require_once __DIR__ . '/hba-testmerken-repository.php';

    $expected = ce_hba_ijk_tekstscore_expected($case);
    $judgment = ce_hba_judge_testpost_row($expected, $actual, 0);

    $id = strtoupper((string) ($case['id'] ?? ''));
    $flags = is_array($actual['flags'] ?? null) ? $actual['flags'] : [];
    $flagCitaten = is_array($actual['flagCitaten'] ?? null) ? $actual['flagCitaten'] : [];
    $segments = ce_hba_resolve_kop_en_openingszin($postText);
    $kop = (string) ($segments['kop'] ?? '');

    if ($id === 'T13') {
        $hasKernFlag = false;
        foreach (['proof_gap', 'foe_vervaging'] as $flag) {
            if (!in_array($flag, $flags, true)) {
                continue;
            }
            $citaat = trim((string) ($flagCitaten[$flag . '_uitleg'] ?? $flagCitaten[$flag] ?? ''));
            if ($citaat === '' || ce_hba_flag_citation_on_kernbewering($citaat, $kop, (string) ($segments['opening'] ?? ''))) {
                $hasKernFlag = true;
                break;
            }
        }
        if (!$hasKernFlag) {
            $judgment['reasons'][] = 'Geen proof_gap/foe_vervaging op kop (T13 valkuil)';
            $judgment['judgment'] = 'fout';
        }
        if ((string) ($actual['hoofdoordeel'] ?? '') === 'regelbreuk') {
            $judgment['reasons'][] = 'hoofdoordeel regelbreuk (verboden bij T13)';
            $judgment['judgment'] = 'fout';
        }
    }

    if ($id === 'T14') {
        $hasProof = in_array('proof_gap', $flags, true);
        if (!$hasProof) {
            $judgment['reasons'][] = 'proof_gap ontbreekt (T14 valkuil)';
            $judgment['judgment'] = 'fout';
        } else {
            $citaat = trim((string) ($flagCitaten['proof_gap_uitleg'] ?? $flagCitaten['proof_gap'] ?? ''));
            if ($citaat !== '' && !ce_hba_flag_citation_on_kernbewering(
                $citaat,
                $kop,
                (string) ($segments['opening'] ?? ''),
            )) {
                $judgment['warnings'][] = 'proof_gap-citaat niet op kop/opening (handmatig nakijken)';
            }
        }
    }

    $min = $case['score_min'] ?? null;
    $max = $case['score_max'] ?? null;
    if (is_numeric($min) && is_numeric($max) && isset($actual['positionering'])) {
        $pos = (float) $actual['positionering'];
        if ($pos < (float) $min || $pos > (float) $max) {
            $judgment['warnings'][] = sprintf(
                'positionering %.1f buiten ijk-band [%.0f–%.0f] (Rogier 7.1: waarschuwing)',
                $pos,
                (float) $min,
                (float) $max,
            );
        }
    }

    foreach (['verplichte_signalen', 'verboden_signalen'] as $key) {
        $signals = is_array($case[$key] ?? null) ? $case[$key] : [];
        foreach ($signals as $signal) {
            $s = (string) $signal;
            if (str_starts_with($s, 'contentrol=')) {
                $ijkRol = substr($s, strlen('contentrol='));
                $appRol = match ($ijkRol) {
                    'kennis' => 'educatie',
                    'engagement' => 'employer_culture',
                    'verkoop', 'aankondiging' => 'activatie',
                    default => $ijkRol,
                };
                $actualRol = (string) ($actual['contentrol'] ?? '');
                if ($actualRol !== '' && $actualRol !== $appRol) {
                    $judgment['warnings'][] = 'contentrol ' . $actualRol . ' (ijk verwacht ' . $appRol . ')';
                }
            }
        }
    }

    if ($judgment['judgment'] === 'goed' && ($judgment['warnings'] ?? []) !== [] && !empty($case['valkuil'])) {
        // Valkuil: waarschuwingen blokkeren niet automatisch.
    }

    return $judgment;
}

/** @param array<string, mixed> $check */
function ce_hba_ijk_architectuur_check_blob(array $check): string
{
    $parts = [
        json_encode($check['signalen'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($check['signaalAdvies'] ?? [], JSON_UNESCAPED_UNICODE),
        (string) ($check['meetlatStatus'] ?? ''),
        (string) ($check['totaaloordeel'] ?? ''),
    ];
    $dim = is_array($check['dimensies'] ?? null) ? $check['dimensies'] : [];
    $scherpte = is_array($dim['scherpteEnUniciteit'] ?? null) ? $dim['scherpteEnUniciteit'] : [];
    if (!empty($scherpte['strawmanGedetecteerd'])) {
        $parts[] = 'strawman_gedetecteerd';
    }
    if (!empty($scherpte['zitInHygienefactoren'])) {
        $parts[] = 'hygienefactor_gedetecteerd';
    }
    foreach ($dim as $block) {
        if (!is_array($block)) {
            continue;
        }
        $parts[] = (string) ($block['toelichting'] ?? '');
        $parts[] = (string) ($block['verbetering'] ?? '');
    }
    $regels = is_array($check['regelsOordeel'] ?? null) ? $check['regelsOordeel'] : [];
    $parts[] = (string) ($regels['toelichting'] ?? '');

    return strtolower(implode("\n", $parts));
}

/** @param array<string, mixed> $check */
function ce_hba_ijk_architectuur_signal_ok(string $signal, array $check): bool
{
    $s = strtolower(trim($signal));
    $sig = is_array($check['signalen'] ?? null) ? $check['signalen'] : [];
    $blob = ce_hba_ijk_architectuur_check_blob($check);

    if (str_contains($s, 'tegenkracht_ontbreekt')) {
        return !empty($sig['tegenkrachtOntbreekt']);
    }
    if (str_contains($s, 'regel_ontbreekt')) {
        return !empty($sig['regelOntbreekt']);
    }
    if (str_contains($s, 'meetlat_geblokkeerd')) {
        return ($check['meetlatStatus'] ?? '') === 'geblokkeerd' || ($check['guardrailActive'] ?? true) === false;
    }
    if (str_contains($s, 'meetlat geactiveerd') || str_contains($s, 'content gegenereerd')) {
        return false;
    }
    if (str_contains($s, 'alleen een melding')) {
        return ($check['totaaloordeel'] ?? '') !== '';
    }
    if ($s === 'hygienefactor_gedetecteerd' || str_starts_with($s, 'hygienefactor_gedetecteerd')) {
        return str_contains($blob, 'hygienefactor');
    }
    if (str_contains($s, 'strawman')) {
        return str_contains($blob, 'strawman');
    }
    if (str_contains($s, 'regel_is_wettelijke') || str_contains($s, 'wettelijke plicht')) {
        return str_contains($blob, 'wettelijk') || str_contains($blob, 'plicht') || str_contains($blob, 'ratio');
    }

    $needle = preg_replace('/[^a-z0-9_]+/u', '_', $s) ?? $s;
    $needle = trim($needle, '_');

    return $needle !== '' && str_contains($blob, $needle);
}

/**
 * @param array<string, mixed> $case
 * @param array<string, mixed> $check
 * @return array{judgment: string, reasons: list<string>, warnings: list<string>}
 */
function ce_hba_judge_ijk_architectuur(array $case, array $check): array
{
    $verwacht = trim((string) ($case['verwacht_oordeel'] ?? ''));
    $totaal = (string) ($check['totaaloordeel'] ?? '');
    $reasons = [];
    $warnings = [];

    $dim = is_array($check['dimensies'] ?? null) ? $check['dimensies'] : [];
    $aanscherpen = 0;
    foreach ($dim as $block) {
        if (!is_array($block)) {
            continue;
        }
        if ((string) ($block['oordeel'] ?? '') === 'aanscherpen') {
            $aanscherpen++;
        }
    }

    if ($verwacht === 'sterk') {
        if (in_array($totaal, ['meetlat_te_zwak', 'identity_gate_blocked'], true)) {
            $reasons[] = 'totaaloordeel ' . $totaal . ' (verwacht sterk)';
        } elseif ($totaal === 'meetlat_bruikbaar_met_waarschuwing') {
            $warnings[] = 'totaaloordeel meetlat_bruikbaar_met_waarschuwing (verwacht sterk)';
        }
    } elseif ($verwacht === 'aanscherpen') {
        if ($totaal === 'meetlat_klaar') {
            $reasons[] = 'totaaloordeel meetlat_klaar (verwacht aanscherpen)';
        } elseif ($totaal === 'meetlat_te_zwak') {
            $warnings[] = 'totaaloordeel meetlat_te_zwak (sterker dan verwacht aanscherpen?)';
        } elseif ($aanscherpen === 0 && $totaal !== 'meetlat_bruikbaar_met_waarschuwing') {
            $warnings[] = 'geen dimensie aanscherpen zichtbaar';
        }
    } elseif ($verwacht === 'zwak') {
        if (!in_array($totaal, ['meetlat_te_zwak', 'meetlat_bruikbaar_met_waarschuwing'], true)) {
            $reasons[] = 'totaaloordeel ' . $totaal . ' (verwacht zwak)';
        }
    }

    foreach (is_array($case['verplichte_signalen'] ?? null) ? $case['verplichte_signalen'] : [] as $signal) {
        if (!ce_hba_ijk_architectuur_signal_ok((string) $signal, $check)) {
            $reasons[] = 'verplicht signaal ontbreekt: ' . $signal;
        }
    }
    foreach (is_array($case['verboden_signalen'] ?? null) ? $case['verboden_signalen'] : [] as $signal) {
        $sig = (string) $signal;
        if (str_contains(strtolower($sig), 'methodische term')) {
            continue;
        }
        if (ce_hba_ijk_architectuur_signal_ok($sig, $check)) {
            $reasons[] = 'verboden signaal: ' . $sig;
        }
    }

    // Klantzichtbaar in de wizard: suggesties én dimensie-toelichting/verbetering (stap 3).
    $klantTekst = [];
    foreach (['toonSuggesties', 'voorbeeldSuggesties'] as $key) {
        $block = $check[$key] ?? null;
        if (!is_array($block)) {
            continue;
        }
        foreach ($block as $item) {
            if (is_string($item)) {
                $klantTekst[] = $item;
            } elseif (is_array($item)) {
                $klantTekst[] = (string) ($item['tekst'] ?? '');
            }
        }
    }
    $dim = is_array($check['dimensies'] ?? null) ? $check['dimensies'] : [];
    foreach ($dim as $block) {
        if (!is_array($block)) {
            continue;
        }
        $klantTekst[] = (string) ($block['toelichting'] ?? '');
        $klantTekst[] = (string) ($block['verbetering'] ?? '');
    }
    $regels = is_array($check['regelsOordeel'] ?? null) ? $check['regelsOordeel'] : [];
    $klantTekst[] = (string) ($regels['toelichting'] ?? '');
    $klantTekst[] = (string) ($regels['verbetering'] ?? '');
    $klantBlob = strtolower(implode("\n", $klantTekst));
    foreach (is_array($case['verboden_signalen'] ?? null) ? $case['verboden_signalen'] : [] as $signal) {
        $s = strtolower((string) $signal);
        if (str_contains($s, 'methodische term')
            && preg_match('/\\b(archetype|story triangle|contextprofiel|held|gids|foe)\\b/u', $klantBlob)) {
            $reasons[] = 'methodische term in klantzichtbare architectuur-output';
        }
    }

    if ($reasons !== []) {
        return ['judgment' => 'fout', 'reasons' => $reasons, 'warnings' => $warnings];
    }
    if ($warnings !== []) {
        return ['judgment' => 'review', 'reasons' => ['Signalen/deels handmatig'], 'warnings' => $warnings];
    }

    return ['judgment' => 'goed', 'reasons' => [], 'warnings' => $warnings];
}

/**
 * @param array<string, mixed> $case
 * @param array<string, mixed>|null $hint
 * @return array{judgment: string, reasons: list<string>, warnings: list<string>}
 */
function ce_hba_judge_ijk_schrijfadvies(array $case, ?array $hint): array
{
    if ($hint === null) {
        return ['judgment' => 'fout', 'reasons' => ['Geen hint-output'], 'warnings' => []];
    }

    $aanwijzing = strtolower((string) ($hint['aanwijzing'] ?? ''));
    $invulvelden = is_array($hint['invulvelden'] ?? null) ? $hint['invulvelden'] : [];
    $reasons = [];
    $warnings = [];

    if (str_contains($aanwijzing, 'betrouwbare partner')) {
        $reasons[] = 'verboden formulering in aanwijzing';
    }
    if (preg_match('/\\b(archetype|story triangle|contextprofiel|held|gids|foe)\\b/u', $aanwijzing)) {
        $reasons[] = 'methodische term in aanwijzing';
    }

    $id = strtoupper((string) ($case['id'] ?? ''));
    if ($id === 'S05') {
        if (count($invulvelden) === 0 && !str_contains($aanwijzing, '[[')) {
            $reasons[] = 'S05: geen invulveld in hint';
        }
        if (count($invulvelden) > 2) {
            $reasons[] = 'S05: meer dan twee invulvelden';
        }
    }

    if ($reasons !== []) {
        return ['judgment' => 'fout', 'reasons' => $reasons, 'warnings' => $warnings];
    }

    return [
        'judgment' => 'review',
        'reasons' => ['Schrijfadvies — rubric/signalen deels handmatig (Rogier 7.5)'],
        'warnings' => $warnings,
    ];
}

/**
 * @param array<string, mixed> $case
 * @return array<string, mixed>
 */
function ce_hba_ijk_run_case(PDO $pdo, array $case, string $clientId, string $ijksetVersie = '0.2'): array
{
    require_once __DIR__ . '/hba-prompt.php';
    require_once __DIR__ . '/hba-v2-repository.php';
    require_once __DIR__ . '/hba-architectuur-check-repository.php';

    $id = (string) ($case['id'] ?? '');
    $soort = (string) ($case['soort'] ?? '');
    $input = is_array($case['input'] ?? null) ? $case['input'] : [];

    $expect = ce_hba_ijk_case_expectations($case);
    $base = [
        'ijkgeval_id' => $id,
        'naam' => (string) ($case['naam'] ?? ''),
        'soort' => $soort,
        'valkuil' => !empty($case['valkuil']),
        'verwacht_oordeel' => $expect['verwacht_oordeel'],
        'verwacht_meetlat' => $expect['verwacht_meetlat'],
        'ijkset_versie' => $ijksetVersie,
        'runStatus' => 'gedraaid',
        'model' => null,
        'promptVersion' => null,
        'oordeel_status' => null,
        'meetlat_status' => null,
        'judgment_oordeel' => null,
        'judgment_meetlat' => null,
        'judgment' => null,
        'reasons' => [],
        'warnings' => [],
        'output' => null,
        'error' => null,
    ];

    if ($soort === 'compressietest') {
        $base['runStatus'] = 'niet_gedraaid';
        $base['judgment'] = 'niet_gedraaid';
        $base['judgment_oordeel'] = 'niet_gedraaid';
        $base['judgment_meetlat'] = 'n.v.t.';
        $base['reasons'] = ['Compressietest — geen route (stap 9)'];

        return $base;
    }

    if ($soort === 'verleidingsthema') {
        $base['runStatus'] = 'niet_gedraaid';
        $base['judgment'] = 'niet_gedraaid';
        $base['judgment_oordeel'] = 'niet_gedraaid';
        $base['judgment_meetlat'] = 'n.v.t.';
        $base['reasons'] = ['V01 vereist CE-03 fastlane — gebruik admin-hba-v0-verleiding.php apart'];
        $base['promptVersion'] = 'ce-03-verleiding-v0';

        return $base;
    }

    try {
        if ($soort === 'architectuurcheck') {
            $body = ce_hba_ijk_architecture_body_from_case($pdo, $case);
            $result = ce_hba_v2_run_architecture_check($pdo, $clientId, $body);
            if (empty($result['ok'])) {
                throw new RuntimeException((string) ($result['error'] ?? 'Architectuurcheck mislukt'));
            }
            $check = is_array($result['check'] ?? null) ? $result['check'] : [];
            $base['output'] = $check;
            $base['promptVersion'] = CE_HBA_IJK_ARCHITECTURE_PROMPT;
            $base['model'] = $check['modelUsed'] ?? null;
            $base['bereikt_tot'] = trim((string) ($check['totaaloordeel'] ?? '')) !== ''
                ? 'inhoudelijke_check'
                : 'onbekend';
            $base['oordeel_status'] = (string) ($check['totaaloordeel'] ?? '');
            $base['meetlat_status'] = ce_hba_ijk_derive_meetlat_status($check);
            $jOordeel = ce_hba_judge_ijk_architectuur($case, $check);
            $jMeetlat = ce_hba_judge_ijk_meetlat($case, $base['meetlat_status']);
            $base['judgment_oordeel'] = $jOordeel['judgment'];
            $base['judgment_meetlat'] = $jMeetlat['judgment'];
            $base['warnings'] = $jOordeel['warnings'];
            $mergedReasons = array_merge($jOordeel['reasons'], $jMeetlat['reasons']);
            if ($jOordeel['judgment'] === 'fout' || $jMeetlat['judgment'] === 'fout') {
                $base['judgment'] = 'fout';
                $base['reasons'] = $mergedReasons;
            } elseif ($jOordeel['judgment'] === 'review' || in_array($jMeetlat['judgment'], ['review', 'onbekend'], true)) {
                $base['judgment'] = 'review';
                $base['reasons'] = $mergedReasons !== [] ? $mergedReasons : ['Handmatige review'];
            } else {
                $base['judgment'] = 'goed';
                $base['reasons'] = [];
            }

            return $base;
        }

        $arch = is_array($input['merkarchitectuur'] ?? null) ? $input['merkarchitectuur'] : $input;
        $prep = ce_hba_ijk_prepare_client_for_case(
            $pdo,
            $clientId,
            ce_hba_ijk_narrative_from_wizard_fields($pdo, $arch),
            ['allowIncompleteNarrative' => true],
        );
        $narrativeRow = $prep['narrative'];

        if ($soort === 'tekstscore') {
            $text = trim((string) ($input['tekst'] ?? ''));
            if ($text === '') {
                throw new RuntimeException('tekst ontbreekt');
            }
            $scoreRow = ce_hba_ijk_score_tekst($pdo, $clientId, $narrativeRow, $id, $text);
            $dto = ce_hba_score_dto($scoreRow);
            $pos = ce_hba_v2_post_positioning_score($scoreRow);
            $actual = [
                'brandContribution' => $dto['brandContribution'],
                'distinctiviteit' => $dto['brandContributionDimensions']['distinctiviteit'] ?? null,
                'contentrol' => $dto['contentrol'],
                'flags' => $dto['architectureRiskFlags'],
                'flagCitaten' => ce_hba_flag_citaten_from_raw($scoreRow['raw_llm_response'] ?? null),
                'hoofdoordeel' => $dto['hoofdoordeel'] ?? null,
                'positionering' => $pos,
            ];
            $base['output'] = $actual;
            $base['promptVersion'] = CE_HBA_PROMPT_VERSION;
            $base['model'] = $dto['modelUsed'];
            $j = ce_hba_judge_ijk_tekstscore($case, $actual, $text);
            $base['oordeel_status'] = is_string($actual['hoofdoordeel'] ?? null)
                ? (string) $actual['hoofdoordeel']
                : null;
            $base['judgment_oordeel'] = $j['judgment'];
            $base['judgment_meetlat'] = 'n.v.t.';
            $base['judgment'] = $j['judgment'];
            $base['reasons'] = $j['reasons'];
            $base['warnings'] = $j['warnings'];

            return $base;
        }

        if ($soort === 'schrijfadvies') {
            $concept = trim((string) ($input['concepttekst'] ?? ''));
            if ($concept === '') {
                throw new RuntimeException('concepttekst ontbreekt');
            }
            $articleId = ce_hba_ijk_ensure_hint_article($pdo, $clientId, $id, $concept);
            $hintResult = ce_hba_v2_run_article_hint($pdo, $clientId, $articleId, ['force' => true]);
            if (empty($hintResult['ok'])) {
                throw new RuntimeException((string) ($hintResult['error'] ?? 'Hint mislukt'));
            }
            $hint = is_array($hintResult['hint'] ?? null) ? $hintResult['hint'] : null;
            $base['output'] = $hint;
            $base['promptVersion'] = 'hba-article-hint-v2';
            $base['model'] = is_array($hint) ? ($hint['modelUsed'] ?? null) : null;
            $j = ce_hba_judge_ijk_schrijfadvies($case, $hint);
            $base['judgment_oordeel'] = $j['judgment'];
            $base['judgment_meetlat'] = 'n.v.t.';
            $base['judgment'] = $j['judgment'];
            $base['reasons'] = $j['reasons'];
            $base['warnings'] = $j['warnings'];

            return $base;
        }

        throw new RuntimeException('Onbekende soort: ' . $soort);
    } catch (Throwable $e) {
        $base['runStatus'] = 'fout';
        $base['judgment'] = 'fout';
        $base['error'] = $e->getMessage();
        $base['reasons'] = [$e->getMessage()];

        return $base;
    }
}

/**
 * @param array{execute?: bool, id?: string} $options
 * @return array<string, mixed>
 */
function ce_hba_ijkrun_payload(PDO $pdo, array $options = []): array
{
    require_once __DIR__ . '/hba-prompt.php';

    $set = ce_hba_ijk_load_set();
    $ijksetVersie = ce_hba_ijk_set_version($set);
    $execute = !empty($options['execute']);
    $filterId = strtoupper(trim((string) ($options['id'] ?? '')));

    $clientId = ce_hba_ijk_ensure_sandbox_client($pdo);

    $cases = [];
    $summary = [
        'goed' => 0,
        'fout' => 0,
        'review' => 0,
        'niet_gedraaid' => 0,
        'valkuil_goed' => 0,
        'valkuil_totaal' => 0,
    ];

    foreach ($set['ijkgevallen'] as $case) {
        if (!is_array($case)) {
            continue;
        }
        $id = (string) ($case['id'] ?? '');
        if ($filterId !== '' && strtoupper($id) !== $filterId) {
            continue;
        }

        $soort = (string) ($case['soort'] ?? '');
        $inventory = ce_hba_ijk_inventory_row($case);

        if (!$execute) {
            $cases[] = $inventory;
            continue;
        }

        if ($soort === 'compressietest') {
            $row = ce_hba_ijk_run_case($pdo, $case, $clientId, $ijksetVersie);
        } elseif (!ce_hba_ijk_soort_runnable($soort)) {
            continue;
        } else {
            $row = ce_hba_ijk_run_case($pdo, $case, $clientId, $ijksetVersie);
        }

        $cases[] = array_merge($inventory, $row);
        $j = (string) ($row['judgment'] ?? '');
        if (!empty($case['valkuil']) && $soort !== 'compressietest') {
            $summary['valkuil_totaal']++;
            if ($j === 'goed') {
                $summary['valkuil_goed']++;
            }
        }
        if (isset($summary[$j])) {
            $summary[$j]++;
        }
    }

    $runnableTotal = 0;
    $compressie = 0;
    foreach ($set['ijkgevallen'] as $case) {
        if (!is_array($case)) {
            continue;
        }
        $soort = (string) ($case['soort'] ?? '');
        if ($soort === 'compressietest') {
            $compressie++;
        } elseif (ce_hba_ijk_soort_runnable($soort)) {
            $runnableTotal++;
        }
    }

    return [
        'ok' => true,
        'generatedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
        'ijkset_versie' => $ijksetVersie,
        'ijkset_source' => (string) ($set['_sourcePath'] ?? ''),
        'ijkset_definities' => is_array($set['definities'] ?? null) ? $set['definities'] : null,
        'scoring_prompt_version' => CE_HBA_PROMPT_VERSION,
        'sandbox_client_id' => $clientId,
        'sandbox_client_name' => CE_HBA_IJK_CLIENT_NAME,
        'environment' => ce_is_production() ? 'production' : (getenv('CE_ENV') ?: 'staging'),
        'execute' => $execute,
        'filter_id' => $filterId !== '' ? $filterId : null,
        'counts' => [
            'ijkgevallen_totaal' => count($set['ijkgevallen']),
            'runnable' => $runnableTotal,
            'compressie_niet_gedraaid' => $compressie,
        ],
        'summary' => $summary,
        'cases' => $cases,
    ];
}
