<?php

declare(strict_types=1);

require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';

/** @return array<string, string> db-key => NL beschrijving */
function ce_default_tone_styles(): array
{
    return [
        'b2b' => 'Schrijf voor beslissers en vakgenoten: concreet, understatement, geen hype. '
            . 'Leg uit waarom het ertoe doet voor hun bedrijf of sector.',
        'b2c' => 'Schrijf voor eindgebruikers: helder, warm, geen jargon. '
            . 'Focus op herkenbare situaties en wat iemand er zelf aan heeft.',
        'internal' => 'Schrijf voor collega\'s binnen het bedrijf: direct, informeel-professioneel, '
            . 'alsof je een teamupdate deelt. Geen externe sales-taal; wel duidelijk wat we doen of verwachten.',
        'partners' => 'Schrijf voor partners en allianties: respectvol, samenwerkingsgericht, '
            . 'benoem gedeelde belangen zonder te verkopen.',
        'personal' => 'Schrijf in ik-vorm vanuit één menselijk gezicht (founder, expert of merkstem). '
            . 'Authentiek en concreet; geen corporate boilerplate.',
    ];
}

function ce_ensure_tone_styles_column(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'clients')) {
        return;
    }
    if (ce_table_has_column($pdo, 'clients', 'tone_styles')) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE clients ADD COLUMN tone_styles JSON NULL');
        unset($GLOBALS['ce_table_columns']['clients']);
    } catch (Throwable) {
        // Kolom bestaat al of geen ALTER-rechten.
    }
}

function ce_ensure_editor_styles_column(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'clients')) {
        return;
    }
    if (ce_table_has_column($pdo, 'clients', 'editor_styles')) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE clients ADD COLUMN editor_styles JSON NULL');
        unset($GLOBALS['ce_table_columns']['clients']);
    } catch (Throwable) {
        // Kolom bestaat al of geen ALTER-rechten.
    }
}

function ce_ensure_article_editor_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }

    foreach ([
        'editor_suggested' => 'VARCHAR(100) NULL',
        'editor_final' => 'VARCHAR(100) NULL',
    ] as $column => $definition) {
        if (ce_table_has_column($pdo, 'articles', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE articles ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }
}

function ce_ensure_article_write_directive_column(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }
    if (ce_table_has_column($pdo, 'articles', 'write_directive')) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE articles ADD COLUMN write_directive TEXT NULL');
        unset($GLOBALS['ce_table_columns']['articles']);
    } catch (Throwable) {
        // Kolom bestaat al of geen ALTER-rechten.
    }
}

/** @return array<string, string> ui label => NL beschrijving */
function ce_default_tone_styles_ui(): array
{
    $ui = [];
    foreach (ce_default_tone_styles() as $dbKey => $text) {
        $ui[ce_tone_to_ui($dbKey)] = $text;
    }

    return $ui;
}

function ce_tone_style_key_to_label(string $key): string
{
    $key = trim($key);
    if ($key === '') {
        return '';
    }

    $knownDb = ['b2b', 'b2c', 'internal', 'partners', 'personal', 'archetype', 'adversary'];
    if (in_array(strtolower($key), $knownDb, true)) {
        return ce_tone_to_ui($key);
    }

    return $key;
}

/** @return array<string, string>|null null = nooit opgeslagen */
function ce_fetch_stored_tone_styles_ui(PDO $pdo, string $clientId): ?array
{
    if ($clientId === '' || !ce_table_exists($pdo, 'clients')) {
        return null;
    }
    if (!ce_table_has_column($pdo, 'clients', 'tone_styles')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT tone_styles FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $out = [];
    foreach ($decoded as $key => $value) {
        $label = ce_tone_style_key_to_label((string) $key);
        if ($label === '') {
            continue;
        }
        $out[$label] = trim((string) $value);
    }

    return $out;
}

/** @return array<string, string> ui keys for Strategy form */
function ce_client_tone_styles_for_ui(PDO $pdo, string $clientId): array
{
    ce_ensure_tone_styles_column($pdo);
    $stored = ce_fetch_stored_tone_styles_ui($pdo, $clientId);
    if ($stored !== null) {
        return $stored;
    }

    return ce_default_tone_styles_ui();
}

/** @return list<string> */
function ce_client_tone_option_labels(PDO $pdo, string $clientId): array
{
    return array_keys(ce_client_tone_styles_for_ui($pdo, $clientId));
}

/** @return list<string> db-keys voor CE-03 (één-op-één met schrijfwijzer-namen) */
function ce_client_tone_db_keys(PDO $pdo, string $clientId): array
{
    $keys = [];
    foreach (ce_client_tone_option_labels($pdo, $clientId) as $label) {
        $keys[] = ce_tone_to_db($label);
    }

    return $keys;
}

function ce_client_default_tone_ui(PDO $pdo, string $clientId): string
{
    $labels = ce_client_tone_option_labels($pdo, $clientId);

    return $labels[0] ?? '';
}

function ce_client_default_tone_db(PDO $pdo, string $clientId): string
{
    $ui = ce_client_default_tone_ui($pdo, $clientId);

    return $ui !== '' ? ce_tone_to_db($ui) : '';
}

/** Map DB/API-waarde naar geldige schrijfwijzer-tone; anders eerste beschikbare. */
function ce_resolve_client_tone_ui(PDO $pdo, string $clientId, ?string $value): string
{
    $options = ce_client_tone_option_labels($pdo, $clientId);
    if ($options === []) {
        return '';
    }

    $raw = trim((string) ($value ?? ''));
    if ($raw !== '') {
        $ui = ce_tone_to_ui($raw);
        if (in_array($ui, $options, true)) {
            return $ui;
        }
        $dbKey = strtolower($raw);
        foreach ($options as $label) {
            if (ce_tone_to_db($label) === $dbKey) {
                return $label;
            }
        }
    }

    return $options[0];
}

/** @return array<string, string> label => text (voor write_directive lookup) */
function ce_client_tone_styles(PDO $pdo, string $clientId): array
{
    return ce_client_tone_styles_for_ui($pdo, $clientId);
}

/** @param array<string, string> $uiMap label => text */
function ce_encode_tone_styles_for_db(array $uiMap): string
{
    $payload = [];
    foreach ($uiMap as $label => $text) {
        $label = trim((string) $label);
        if ($label === '') {
            continue;
        }
        $payload[$label] = trim((string) $text);
    }

    if ($payload === []) {
        return '{}';
    }

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function ce_lookup_tone_style_line(array $toneStyles, ?string $toneUi): string
{
    if ($toneUi === null || trim($toneUi) === '') {
        return '';
    }
    $label = ce_tone_to_ui($toneUi);
    if (isset($toneStyles[$label])) {
        return trim((string) $toneStyles[$label]);
    }
    if (isset($toneStyles[$toneUi])) {
        return trim((string) $toneStyles[$toneUi]);
    }

    return '';
}

function ce_build_article_write_directive(
    PDO $pdo,
    string $clientId,
    ?string $toneUi,
    ?string $angleUi,
    ?string $platformUi,
    ?array $objective = null,
    ?string $editorUi = null,
    ?string $slotId = null,
    ?array $articleRow = null,
): string {
    require_once __DIR__ . '/objectives-repository.php';
    require_once __DIR__ . '/copy-modus.php';
    require_once __DIR__ . '/channel-copy-constraints.php';

    $toneKey = ce_tone_to_db(ce_tone_to_ui($toneUi));
    $toneStyles = ce_client_tone_styles($pdo, $clientId);
    $toneLine = ce_lookup_tone_style_line($toneStyles, $toneUi);
    $editorStyles = ce_client_editor_styles($pdo, $clientId);
    $editorLabel = trim((string) ($editorUi ?? ''));
    $editorLine = $editorLabel !== '' ? ce_lookup_editor_style_line($editorStyles, $editorLabel) : '';

    $lines = [];
    $toneUiNorm = ce_tone_to_ui($toneUi);
    if ($toneUiNorm !== '') {
        $lines[] = 'Tone (doelgroep/kanaal): ' . $toneUiNorm . '.';
        if ($toneLine !== '') {
            $lines[] = $toneLine;
        }
    } elseif ($toneLine === '') {
        $lines[] = 'Schrijfstijl: standaardtoon van het merk (geen aparte doelgroep gekozen).';
    }
    if ($editorLabel !== '') {
        $lines[] = 'Redacteur: ' . $editorLabel . '.';
        if ($editorLine !== '') {
            $lines[] = $editorLine;
        }
    }
    $platformDb = '';
    if ($platformUi !== null && trim($platformUi) !== '') {
        $platformDb = ce_platform_to_db(ce_platform_to_ui($platformUi));
        $lines[] = 'Platform: ' . trim($platformUi) . ' — pas lengte, hook en CTA daarop aan.';
    }

    $styleLine = '';
    if ($objective !== null) {
        $writingStyle = trim((string) ($objective['writingStyle'] ?? ''));
        if ($writingStyle !== '') {
            $styleLine = ce_lookup_tone_style_line($toneStyles, $writingStyle);
        }
    }
    foreach (ce_objective_write_directive_lines($objective, $styleLine) as $objectiveLine) {
        $lines[] = $objectiveLine;
    }

    $mandatory = ce_tone_mandatory_rules($toneKey);
    if ($mandatory !== '') {
        $lines[] = $mandatory;
    }

    require_once __DIR__ . '/voice-profile-repository.php';
    $lines[] = ce_source_context_directive_lines($pdo, $clientId);
    $lines[] = ce_voice_profile_directive_lines($pdo, $clientId);

    require_once __DIR__ . '/hba-identity-gate.php';
    $guardrail = ce_hba_active_guardrail($pdo, $clientId);
    if (is_array($guardrail)) {
        $lines[] = 'GUARDRAIL (compact, geen lange brandtekst): '
            . 'hero=' . ($guardrail['hero_role'] ?? '')
            . '; goal=' . ($guardrail['goal_change'] ?? '')
            . '; foe=' . ($guardrail['foe_force'] ?? '')
            . '; nooit=' . ($guardrail['constraint_never'] ?? '')
            . '; vermijd categorietaal=' . implode(', ', $guardrail['branch_category_language'] ?? []);
    }

    if ($slotId !== null && trim($slotId) !== '') {
        $resolved = ce_resolve_copy_modus_for_slot($pdo, trim($slotId));
        foreach (ce_copy_modus_directive_lines($resolved['modus'], $resolved['override']) as $copyLine) {
            $lines[] = $copyLine;
        }
    }

    if ($platformDb !== '') {
        $textvorm = '';
        if (is_array($articleRow)) {
            $textvorm = ce_resolve_article_textvorm($pdo, $articleRow);
        } else {
            $textvorm = ce_default_textvorm_for_platform($platformDb);
        }
        $channelLine = ce_channel_copy_constraint_line($platformDb, $textvorm);
        if ($channelLine !== '') {
            $lines[] = $channelLine;
        }
    }

    return implode("\n", $lines);
}

/** Harde regels per tone — leidend boven Schrijfwijzer als die botsen (bv. wij vs ik vs je). */
function ce_tone_mandatory_rules(string $toneDbKey): string
{
    return match ($toneDbKey) {
        'b2b' => 'VERPLICHT (b to b): Schrijf vanuit het bedrijf in wij-vorm ("wij", "ons", "onze klanten") '
            . '— GEEN ik-vorm en geen persoonlijke anekdotes in de eerste persoon. '
            . 'Zakelijk register voor beslissers/vakgenoten: concreet, benoem impact of resultaat voor hun bedrijf; geen hype.',
        'b2c' => 'VERPLICHT (b to c): Spreek de lezer rechtstreeks aan met "je"/"jij" — GEEN ik-vorm vanuit het bedrijf '
            . 'en geen formeel "wij benaderen u". Warm en toegankelijk, geen vakjargon, focus op wat de lezer er zelf aan heeft.',
        'internal' => 'VERPLICHT (internal): Spreek collega\'s aan met "wij"/"jullie" — GEEN ik-vorm vanuit één persoon. '
            . 'Informeel-professioneel als een teamupdate; geen externe marketing- of salestaal.',
        'partners' => 'VERPLICHT (partners): Schrijf vanuit het bedrijf in wij-vorm, gericht aan partners/allianties '
            . '("samen", "gezamenlijk") — GEEN ik-vorm en geen consumenten-toon. Respectvol, gedeeld belang centraal.',
        'personal' => 'VERPLICHT (personal): Schrijf de hele post in ik-vorm — gebruik "ik", "mijn", "mij" '
            . 'in opening, midden en slot — GEEN "wij"/"ons" namens het bedrijf. '
            . 'Minimaal drie zinnen moeten expliciet met "ik" of "mijn" beginnen of die woorden bevatten.',
        default => '',
    };
}

function ce_persist_article_write_directive(PDO $pdo, string $slotId): void
{
    require_once __DIR__ . '/article-repository.php';
    require_once __DIR__ . '/objectives-repository.php';
    ce_ensure_article_write_directive_column($pdo);
    if ($slotId === '' || !ce_table_exists($pdo, 'articles')) {
        return;
    }

    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        return;
    }

    $clientId = (string) ($row['client_id'] ?? '');
    $tone = ce_resolve_client_tone_ui($pdo, $clientId, $row['tone_final'] ?? $row['tone_suggested'] ?? null);
    $angle = ce_angle_to_ui($row['angle_final'] ?? $row['angle_suggested'] ?? null);
    $platform = ce_platform_to_ui($row['platform_final'] ?? $row['platform_suggested'] ?? null);
    $editor = trim((string) ($row['editor_final'] ?? $row['editor_suggested'] ?? ''));
    $objective = ce_resolve_article_objective_for_writing($pdo, $row);

    $directive = ce_build_article_write_directive(
        $pdo,
        $clientId,
        $tone,
        $angle,
        $platform,
        $objective,
        $editor !== '' ? $editor : null,
        $slotId,
        $row,
    );

    $articleId = (string) ($row['id'] ?? '');
    if ($articleId === '') {
        return;
    }

    $params = ['id' => $articleId, 'write_directive' => $directive];
    $sets = ['write_directive = :write_directive'];

    if (ce_table_has_column($pdo, 'articles', 'tone_suggested')) {
        $sets[] = 'tone_suggested = :tone_suggested';
        $params['tone_suggested'] = ce_tone_to_db($tone);
    }
    if (ce_table_has_column($pdo, 'articles', 'angle_suggested')) {
        $sets[] = 'angle_suggested = :angle_suggested';
        $params['angle_suggested'] = $angle;
    }
    if (ce_table_has_column($pdo, 'articles', 'platform_suggested')) {
        $sets[] = 'platform_suggested = :platform_suggested';
        $params['platform_suggested'] = ce_platform_to_db($platform);
    }

    $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
}

/**
 * Campagne-context voor CE-03 / rationale (content_slots-rij).
 *
 * @param array<string, mixed> $campSlot
 *
 * @return array<string, string>
 */
function ce_campagne_context_meta_from_slot_row(PDO $pdo, array $campSlot): array
{
    $meta = [];
    require_once __DIR__ . '/campagne-preview-repository.php';
    $campSlot = ce_campagne_enrich_slot_row($campSlot);
    $campagneId = trim((string) ($campSlot['campaign_id'] ?? ''));
    if ($campagneId === '' || !ce_table_exists($pdo, 'campaigns')) {
        return $meta;
    }

    $meta['campagne_id'] = $campagneId;

    $campTitle = '';
    $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', null);
    $titleStmt = $pdo->prepare(
        'SELECT title FROM campaigns c WHERE c.id = :id' . $scope['sql'] . ' LIMIT 1'
    );
    $titleStmt->execute(array_merge(['id' => $campagneId], $scope['params']));
    $titleRow = $titleStmt->fetch(PDO::FETCH_ASSOC);
    if ($titleRow) {
        $campTitle = trim((string) ($titleRow['title'] ?? ''));
    }
    if ($campTitle !== '') {
        $meta['campagne_title'] = $campTitle;
    }

    $fase = trim((string) ($campSlot['campaign_fase'] ?? ''));
    if ($fase !== '') {
        $meta['campagne_fase'] = $fase;
    }
    $opdracht = trim((string) ($campSlot['campaign_opdracht'] ?? ''));
    $instructie = trim((string) ($campSlot['campaign_instructie'] ?? ''));
    if ($instructie !== '') {
        $line = 'Aanvullende instructie: ' . $instructie;
        $opdracht = $opdracht !== '' ? $opdracht . "\n\n" . $line : $line;
    }
    if ($opdracht !== '') {
        $meta['campagne_opdracht'] = $opdracht;
    }
    $ankerRaw = $campSlot['campaign_anker'] ?? null;
    if (is_string($ankerRaw) && $ankerRaw !== '') {
        $meta['campagne_anker'] = $ankerRaw;
    } elseif (is_array($ankerRaw)) {
        $meta['campagne_anker'] = json_encode($ankerRaw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    return $meta;
}

/** Meta mee naar CE-03 webhook (betrouwbaarder dan alleen DB-join). @return array<string, string> */
function ce_ce03_webhook_meta_from_slot(PDO $pdo, string $slotId): array
{
    require_once __DIR__ . '/objectives-repository.php';
    require_once __DIR__ . '/ce03-meta-repository.php';
    ce_persist_article_write_directive($pdo, $slotId);
    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        return [];
    }

    $clientId = (string) ($row['client_id'] ?? '');
    $toneUi = ce_resolve_client_tone_ui($pdo, $clientId, $row['tone_final'] ?? $row['tone_suggested'] ?? null);
    $angleUi = ce_angle_to_ui($row['angle_final'] ?? $row['angle_suggested'] ?? null);
    $platformUi = ce_platform_to_ui($row['platform_final'] ?? $row['platform_suggested'] ?? null);
    $editorUi = trim((string) ($row['editor_final'] ?? $row['editor_suggested'] ?? ''));
    $ratioUi = ce_resolve_article_image_ratio($row);
    $objective = ce_resolve_article_objective_for_writing($pdo, $row);
    $directive = ce_build_article_write_directive(
        $pdo,
        $clientId,
        $toneUi,
        $angleUi,
        $platformUi,
        $objective,
        $editorUi !== '' ? $editorUi : null,
        $slotId,
        $row,
    );

    $meta = [
        'write_directive' => $directive,
        'tone_final' => ce_tone_to_db($toneUi),
        'angle_final' => $angleUi,
        'platform_final' => ce_platform_to_db($platformUi),
        'image_ratio_final' => $ratioUi,
    ];

    if ($editorUi !== '') {
        $meta['editor_final'] = $editorUi;
    }

    if ($objective !== null) {
        $objId = trim((string) ($objective['id'] ?? ''));
        if ($objId !== '') {
            $meta['objective_id'] = $objId;
        }
        $meta['objective_label'] = (string) ($objective['label'] ?? '');
        $meta['objective_description'] = (string) ($objective['description'] ?? '');
        $meta['objective_writing_style'] = (string) ($objective['writingStyle'] ?? '');
        if (($objective['_source'] ?? '') === 'brand_architecture') {
            $meta['objective_source'] = 'brand_architecture';
            $meta['brand_goal'] = trim((string) ($objective['description'] ?? ''));
        }
    }

    $catalog = [];
    foreach (ce_fetch_objectives($pdo, $clientId !== '' ? $clientId : null) as $item) {
        $catalog[] = [
            'id' => (string) ($item['id'] ?? ''),
            'label' => (string) ($item['label'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'percentage' => (int) ($item['percentage'] ?? 0),
        ];
    }
    if ($catalog !== []) {
        $meta['objectives_catalog'] = json_encode($catalog, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    $toneDbKeys = ce_client_tone_db_keys($pdo, $clientId);
    if ($toneDbKeys !== []) {
        $meta['tone_options'] = json_encode($toneDbKeys, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $meta['default_tone'] = ce_client_default_tone_db($pdo, $clientId);
    }

    foreach (ce_ce03_platform_meta($pdo, $row, $clientId) as $key => $value) {
        if ($value !== '') {
            $meta[$key] = $value;
        }
    }

    $copyResolved = ce_resolve_copy_modus_for_slot($pdo, $slotId);
    $meta['copy_modus'] = $copyResolved['modus'];
    if ($copyResolved['override']) {
        $meta['copy_modus_override'] = '1';
    }

    if (ce_table_exists($pdo, 'content_slots') && ce_table_has_column($pdo, 'content_slots', 'campaign_id')) {
        $campCols = 'id, campaign_id, campaign_fase, campaign_opdracht';
        if (ce_table_has_column($pdo, 'content_slots', 'campaign_anker')) {
            $campCols .= ', campaign_anker';
        }
        if (ce_table_has_column($pdo, 'content_slots', 'campaign_instructie')) {
            $campCols .= ', campaign_instructie';
        }
        $campStmt = $pdo->prepare("SELECT {$campCols} FROM content_slots WHERE id = :id LIMIT 1");
        $campStmt->execute(['id' => $slotId]);
        $campSlot = $campStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (ce_campagne_context_meta_from_slot_row($pdo, $campSlot) as $k => $v) {
            $meta[$k] = $v;
        }
    }

    $aanspreekvormBlock = trim((string) ($meta['aanspreekvorm_block'] ?? ''));
    if ($aanspreekvormBlock !== '') {
        $directive = trim((string) ($meta['write_directive'] ?? ''));
        if ($directive === '' || !str_contains($directive, 'AANSPREEKVORM')) {
            $meta['write_directive'] = $directive !== ''
                ? $directive . "\n" . $aanspreekvormBlock
                : $aanspreekvormBlock;
        }
    }

    return $meta;
}

/** @return array<string, string>|null null = nooit opgeslagen */
function ce_fetch_stored_editor_styles_ui(PDO $pdo, string $clientId): ?array
{
    if ($clientId === '' || !ce_table_exists($pdo, 'clients')) {
        return null;
    }
    if (!ce_table_has_column($pdo, 'clients', 'editor_styles')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT editor_styles FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $raw = $stmt->fetchColumn();
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $out = [];
    foreach ($decoded as $key => $value) {
        $label = trim((string) $key);
        if ($label === '') {
            continue;
        }
        $out[$label] = trim((string) $value);
    }

    return $out;
}

/** @return array<string, string> ui keys for Strategy form */
function ce_client_editor_styles_for_ui(PDO $pdo, string $clientId): array
{
    ce_ensure_editor_styles_column($pdo);
    $stored = ce_fetch_stored_editor_styles_ui($pdo, $clientId);

    return $stored ?? [];
}

/** @return list<string> */
function ce_client_editor_option_labels(PDO $pdo, string $clientId): array
{
    return array_keys(ce_client_editor_styles_for_ui($pdo, $clientId));
}

/** @return array<string, string> label => angle description */
function ce_client_editor_styles(PDO $pdo, string $clientId): array
{
    return ce_client_editor_styles_for_ui($pdo, $clientId);
}

function ce_lookup_editor_style_line(array $editorStyles, ?string $editorUi): string
{
    if ($editorUi === null || trim($editorUi) === '') {
        return '';
    }
    $label = trim($editorUi);
    if (isset($editorStyles[$label])) {
        return trim((string) $editorStyles[$label]);
    }

    return '';
}
