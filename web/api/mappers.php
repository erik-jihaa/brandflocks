<?php

declare(strict_types=1);

function ce_angle_to_ui(?string $value): string
{
    $key = strtolower(trim((string) $value));
    if (in_array($key, ['archetype', 'adversary', 'general'], true)) {
        return $key;
    }

    return 'general';
}

function ce_image_ratio_to_ui(?string $value): string
{
    $key = strtolower(trim((string) $value));

    return $key === 'landscape' ? 'landscape' : 'square';
}

/** Bepaal ratio: final → geüploade variant → suggested. */
function ce_resolve_article_image_ratio(array $row): string
{
    $final = trim((string) ($row['image_ratio_final'] ?? ''));
    if ($final !== '') {
        return ce_image_ratio_to_ui($final);
    }

    $square = trim((string) ($row['image_url_square'] ?? ''));
    $landscape = trim((string) ($row['image_url_landscape'] ?? ''));

    if ($square !== '' && $landscape === '') {
        return 'square';
    }
    if ($landscape !== '' && $square === '') {
        return 'landscape';
    }

    return ce_image_ratio_to_ui($row['image_ratio_suggested'] ?? null);
}

function ce_optional_url(mixed $value): ?string
{
    $url = trim((string) $value);

    return $url !== '' ? $url : null;
}

function ce_tone_to_ui(?string $value): string
{
    $map = [
        'b2b' => 'b to b',
        'b2c' => 'b to c',
        'internal' => 'internal',
        'partners' => 'partners',
        'personal' => 'personal',
        'archetype' => 'archetype',
        'adversary' => 'adversary',
    ];

    $key = strtolower(trim((string) $value));
    return $map[$key] ?? ($value ?: 'b to b');
}

function ce_tone_to_db(string $value): string
{
    $map = [
        'b to b' => 'b2b',
        'b to c' => 'b2c',
        'internal' => 'internal',
        'partners' => 'partners',
        'personal' => 'personal',
        'archetype' => 'archetype',
        'adversary' => 'adversary',
    ];

    return $map[$value] ?? strtolower(str_replace(' ', '', $value));
}

/**
 * PATCH-body wijzigt de schrijfvorm (Toon: ik-vorm / je-vorm / zakelijk).
 * Headline/content-save telt niet; alleen dan moet CE-03 opnieuw schrijven.
 */
function ce_article_patch_changes_schrijfvorm(array $row, array $body): bool
{
    if (!array_key_exists('tone', $body)) {
        return false;
    }
    $incoming = trim((string) $body['tone']);
    if ($incoming === '') {
        return false;
    }
    $current = ce_tone_to_db(ce_tone_to_ui($row['tone_final'] ?? $row['tone_suggested'] ?? null));
    $next = ce_tone_to_db($incoming);

    return $current !== $next;
}

function ce_platform_to_ui(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Linkedin';
    }

    $map = [
        'linkedin' => 'Linkedin',
        'instagram' => 'Instagram',
        'x' => 'X',
        'facebook' => 'Facebook',
        'blog' => 'Blog',
    ];

    $key = strtolower(trim($value));
    return $map[$key] ?? ucfirst($key);
}

function ce_platform_to_db(string $value): string
{
    $map = [
        'Linkedin' => 'linkedin',
        'Instagram' => 'instagram',
        'X' => 'x',
        'Facebook' => 'facebook',
        'Blog' => 'blog',
    ];

    return $map[$value] ?? strtolower($value);
}

function ce_status_to_ui(?string $value): string
{
    $key = strtolower(trim((string) $value));
    if (in_array($key, ['feedback', 'feedback_received', 'needs_feedback', 'revision'], true)) {
        return 'feedback';
    }

    if (in_array($key, ['pending', 'pending_review', 'submitted', 'review', 'proposed'], true)) {
        return 'pending';
    }

    if (in_array($key, ['themed', 'theme_ready'], true)) {
        return 'themed';
    }

    if (in_array($key, ['research_done', 'planned', 'open'], true)) {
        return 'research_done';
    }

    return $key !== '' ? $key : 'draft';
}

/** Pipeline-ENUM op `content_slots` — niet `proposed` als redactioneel pending lezen. */
function ce_slot_status_to_ui(?string $value): string
{
    $key = strtolower(trim((string) $value));
    if (in_array($key, ['proposed', 'researching'], true)) {
        return 'themed';
    }

    return ce_status_to_ui($value);
}

function ce_article_row_has_real_body(?array $row): bool
{
    if ($row === null) {
        return false;
    }

    require_once __DIR__ . '/editor-questions.php';
    $human = trim((string) ($row['content_human'] ?? ''));
    if ($human !== '') {
        return true;
    }

    return ce_article_has_real_body((string) ($row['content_ai'] ?? ''));
}

function ce_slot_is_campaign_theme(array $slot): bool
{
    $origin = strtolower(trim((string) ($slot['origin'] ?? '')));
    $campaignId = trim((string) ($slot['campaign_id'] ?? ''));

    return $origin === 'campagne_preview' || $campaignId !== '';
}

/** @param array<string, mixed> $slot */
function ce_build_theme_start_api_payload(PDO $pdo, array $slot): array
{
    require_once __DIR__ . '/theme-repository.php';
    require_once __DIR__ . '/tone-styles.php';
    require_once __DIR__ . '/campagne-preview-templates.php';
    require_once __DIR__ . '/copy-modus.php';

    $slotId = (string) ($slot['id'] ?? '');
    $ctx = ce_resolve_slot_research_context($pdo, $slotId);
    $camp = ce_campagne_context_meta_from_slot_row($pdo, $slot);
    $title = trim((string) ($ctx['slotTheme'] ?? $slot['theme'] ?? ''));
    $rationale = trim((string) ($ctx['themeRationale'] ?? ''));
    if ($rationale === '') {
        $rationale = trim((string) ($camp['campagne_opdracht'] ?? ''));
    }
    $platformRaw = trim((string) ($slot['campaign_platform'] ?? ''));
    $faseKey = trim((string) ($slot['campaign_fase'] ?? $camp['campagne_fase'] ?? ''));
    $templateKey = trim((string) ($slot['campaign_template_key'] ?? ''));
    $faseLabel = $faseKey !== '' ? ce_campagne_preview_fase_label($templateKey !== '' ? $templateKey : null, $faseKey) : null;
    $publishDate = trim((string) ($slot['publish_date'] ?? ''));
    $campaignId = trim((string) ($camp['campagne_id'] ?? $slot['campaign_id'] ?? ''));
    $copyResolved = ce_resolve_copy_modus_for_slot($pdo, $slotId);

    return [
        'ok' => true,
        'themeOnly' => true,
        'id' => '',
        'slotId' => $slotId,
        'themeId' => trim((string) ($ctx['themeId'] ?? $slot['theme_id'] ?? '')),
        'weekLabel' => ce_week_label($slot['phase_key'] ?? null, null),
        'headline' => $title,
        'headlineAi' => $title,
        'content' => '',
        'contentAi' => '',
        'hashtags' => '',
        'rationale' => $rationale,
        'sourceUrl' => null,
        'tone' => '',
        'angle' => 'general',
        'imageRatio' => 'square',
        'platform' => $platformRaw !== '' ? ce_platform_to_ui($platformRaw) : '',
        'status' => 'themed',
        'slotStatus' => ce_slot_status_to_ui($slot['status'] ?? null),
        'campaignId' => $campaignId !== '' ? $campaignId : null,
        'campaignTitle' => $camp['campagne_title'] ?? null,
        'campaignFaseLabel' => $faseLabel,
        'publishDate' => $publishDate !== '' ? $publishDate : null,
        'slotOrigin' => trim((string) ($slot['origin'] ?? '')) ?: null,
        'copyModus' => $copyResolved['modus'],
        'copyModusOverride' => $copyResolved['override'],
        'copyModusLabel' => ce_copy_modus_ui_label($copyResolved['modus']),
    ];
}

/** Pipeline-fase voor Agenda (§8.3.5): Theme / Research / Article. */
function ce_resolve_agenda_phase(?string $slotStatus, ?string $articleId, ?string $slotOrigin = null): string
{
    if (trim((string) $articleId) !== '') {
        return 'article';
    }

    if (strtolower(trim((string) $slotOrigin)) === 'campagne_preview') {
        return 'theme';
    }

    $slotUi = ce_slot_status_to_ui($slotStatus);
    if ($slotUi === 'themed') {
        return 'theme';
    }
    if ($slotUi === 'research_done') {
        return 'article';
    }

    return 'article';
}

function ce_agenda_phase_label(string $phase, string $editorFirstName = ''): string
{
    $base = match ($phase) {
        'theme' => 'Thema',
        'research' => 'Onderzoek',
        'article' => 'Artikel',
        default => 'Artikel',
    };

    if ($phase === 'article' && trim($editorFirstName) !== '') {
        return $base . '-' . trim($editorFirstName);
    }

    return $base;
}

function ce_editor_first_name(?string $editorName): string
{
    $name = trim((string) $editorName);
    if ($name === '') {
        return '';
    }

    $parts = preg_split('/\s+/u', $name) ?: [];

    return trim((string) ($parts[0] ?? $name));
}

/** Ruwe DB-waarde: redacteur heeft ingeleverd (Submit for approval). */
function ce_is_submitted_status(?string $value): bool
{
    $key = strtolower(trim((string) $value));

    return in_array($key, ['pending', 'pending_review', 'submitted', 'review', 'proposed'], true);
}

function ce_status_priority(string $uiStatus): int
{
    return match ($uiStatus) {
        'published' => 7,
        'approved' => 6,
        'feedback' => 5,
        'pending' => 4,
        'written' => 3,
        'draft' => 2,
        'research_done' => 1,
        'themed' => 0,
        default => 0,
    };
}

/** Meest ver gevorderde UI-status (article vs slot ENUM kunnen verschillen). */
function ce_resolve_ui_status(?string $articleStatus, ?string $slotStatus): string
{
    $candidates = [ce_status_to_ui($articleStatus), ce_slot_status_to_ui($slotStatus)];

    return array_reduce(
        $candidates,
        static fn (string $best, string $current) =>
            ce_status_priority($current) > ce_status_priority($best) ? $current : $best,
        'draft',
    );
}

/**
 * Agenda/article UI-status.
 * Ingeleverd (pending/proposed/submitted) → altijd Pending, ook boven Feedback.
 * Feedback alleen als hoofdredacteur correcties heeft teruggestuurd.
 */
function ce_resolve_editorial_status(
    ?string $articleStatus,
    ?string $slotStatus,
    int $openFeedbackCount = 0,
): string {
    $articleRaw = trim((string) ($articleStatus ?? ''));
    $slotUi = ce_slot_status_to_ui($slotStatus);

    // Geen artikel yet — toon pipeline-fase van het slot (themed / research_done)
    if ($articleRaw === '') {
        if (in_array($slotUi, ['themed', 'research_done'], true)) {
            return $slotUi;
        }

        return $slotUi !== '' && $slotUi !== 'draft' ? $slotUi : 'themed';
    }

    $articleUi = ce_status_to_ui($articleStatus);

    if (in_array($articleUi, ['published', 'approved'], true)
        || in_array($slotUi, ['published', 'approved'], true)) {
        return ce_resolve_ui_status($articleStatus, $slotStatus);
    }

    // Submit for approval → Pending wint altijd (ook bij resterende feedback-records)
    if (ce_is_submitted_status($articleStatus) || ce_is_submitted_status($slotStatus)) {
        return 'pending';
    }

    if ($openFeedbackCount > 0 || $articleUi === 'feedback' || $slotUi === 'feedback') {
        return 'feedback';
    }

    return ce_resolve_ui_status($articleStatus, $slotStatus);
}

function ce_week_start(): int
{
    ce_load_env();
    $raw = getenv('CE_WEEK_START') ?: '';
    $override = (int) $raw;
    if ($override > 0) {
        return $override;
    }

    $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
    } catch (Throwable) {
        $now = new DateTimeImmutable('now');
    }

    return (int) $now->format('W');
}

function ce_ce_timezone(): DateTimeZone
{
    ce_load_env();
    $tz = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
    try {
        return new DateTimeZone($tz);
    } catch (Throwable) {
        return new DateTimeZone('Europe/Amsterdam');
    }
}

/** Nederlandse maandnaam (klant-UI — geen locale-afhankelijke `F`). */
function ce_nl_month_long(int $month): string
{
    static $names = [
        1 => 'januari',
        2 => 'februari',
        3 => 'maart',
        4 => 'april',
        5 => 'mei',
        6 => 'juni',
        7 => 'juli',
        8 => 'augustus',
        9 => 'september',
        10 => 'oktober',
        11 => 'november',
        12 => 'december',
    ];

    return $names[$month] ?? '';
}

function ce_format_agenda_day(DateTimeImmutable $dt): string
{
    $day = (int) $dt->format('j');
    $month = ce_nl_month_long((int) $dt->format('n'));

    return trim($day . ' ' . $month);
}

function ce_today_agenda_label(): string
{
    $now = new DateTimeImmutable('now', ce_ce_timezone());

    return ce_format_agenda_day($now) . ' ' . $now->format('Y');
}

function ce_resolve_iso_week_year(int $weekNum): int
{
    $now = new DateTimeImmutable('now', ce_ce_timezone());
    $currentWeek = (int) $now->format('W');
    $currentYear = (int) $now->format('o');

    if ($weekNum > $currentWeek + 26) {
        return $currentYear - 1;
    }

    if ($weekNum < $currentWeek - 26) {
        return $currentYear + 1;
    }

    return $currentYear;
}

/** @return array{start: DateTimeImmutable, end: DateTimeImmutable} */
function ce_iso_week_range(int $weekNum, ?int $isoYear = null): array
{
    $isoYear ??= ce_resolve_iso_week_year($weekNum);
    $tz = ce_ce_timezone();
    $monday = (new DateTimeImmutable('now', $tz))->setISODate($isoYear, $weekNum, 1);

    return [
        'start' => $monday,
        'end' => $monday->modify('+6 days'),
    ];
}

function ce_week_range_label(int $weekNum, ?int $isoYear = null): string
{
    $range = ce_iso_week_range($weekNum, $isoYear);
    $start = $range['start'];
    $end = $range['end'];
    $sameMonth = $start->format('n') === $end->format('n')
        && $start->format('Y') === $end->format('Y');
    if ($sameMonth) {
        $month = ce_nl_month_long((int) $start->format('n'));

        return (int) $start->format('j') . ' - ' . (int) $end->format('j') . ' ' . $month;
    }

    return ce_format_agenda_day($start) . ' - ' . ce_format_agenda_day($end);
}

/** ISO-weeknummer als string (bijv. "26") uit YYYY-MM-DD of datetime. */
function ce_phase_key_from_date(?string $isoDate): ?string
{
    if ($isoDate === null || trim($isoDate) === '') {
        return null;
    }

    $timezone = getenv('CE_TIMEZONE') ?: 'Europe/Amsterdam';
    try {
        $dt = new DateTimeImmutable(trim($isoDate), new DateTimeZone($timezone));
    } catch (Throwable) {
        return null;
    }

    return (string) (int) $dt->format('W');
}

/** Maandag (Y-m-d) van de week waarin de gegeven kalenderdatum valt. */
function ce_monday_of_calendar_date(string $isoDate): string
{
    $tz = ce_ce_timezone();
    try {
        $dt = new DateTimeImmutable(trim($isoDate), $tz);
    } catch (Throwable) {
        $dt = new DateTimeImmutable('now', $tz);
        $dowFallback = (int) $dt->format('N');
        if ($dowFallback !== 1) {
            $dt = $dt->modify('-' . ($dowFallback - 1) . ' days');
        }

        return $dt->format('Y-m-d');
    }
    $dow = (int) $dt->format('N');
    if ($dow !== 1) {
        $dt = $dt->modify('-' . ($dow - 1) . ' days');
    }

    return $dt->format('Y-m-d');
}

/** ISO-week 1–53 uit phase_key; jaartallen (bv. 2026) worden genegeerd. */
function ce_parse_phase_week_number(?string $phaseKey): ?int
{
    if ($phaseKey === null || trim($phaseKey) === '') {
        return null;
    }

    $phaseKey = trim($phaseKey);

    if (preg_match('/(?:^|[_-])week[_-]?(\d{1,2})\b/i', $phaseKey, $matches)) {
        $week = (int) $matches[1];

        return ($week >= 1 && $week <= 53) ? $week : null;
    }

    if (preg_match('/^\d{1,2}$/', $phaseKey)) {
        $week = (int) $phaseKey;

        return ($week >= 1 && $week <= 53) ? $week : null;
    }

    return null;
}

/** @param list<array<string, mixed>> $rows */
function ce_build_phase_week_map(array $rows): array
{
    $phaseKeys = [];
    foreach ($rows as $row) {
        $phaseKey = trim((string) ($row['phase_key'] ?? ''));
        if ($phaseKey !== '' && !in_array($phaseKey, $phaseKeys, true)) {
            $phaseKeys[] = $phaseKey;
        }
    }

    $start = ce_week_start();
    $map = [];

    foreach ($phaseKeys as $index => $phaseKey) {
        $parsed = ce_parse_phase_week_number($phaseKey);
        if ($parsed !== null) {
            $map[$phaseKey] = $parsed;
            continue;
        }

        $map[$phaseKey] = $start + $index;
    }

    return $map;
}

function ce_week_label(?string $phaseKey, ?int $phaseWeekNumber = null): string
{
    if ($phaseWeekNumber !== null && $phaseWeekNumber > 0) {
        return 'Week ' . $phaseWeekNumber;
    }

    if ($phaseKey === null || $phaseKey === '') {
        return 'Agenda';
    }

    $parsed = ce_parse_phase_week_number($phaseKey);
    if ($parsed !== null) {
        return 'Week ' . $parsed;
    }

    return 'Week ' . ce_week_start();
}

/**
 * Agenda loopt door voorbij de planning-horizon: extra weekrijen voor publicaties en slepen.
 * Horizon bepaalt theme-vulling; de kalender toont minstens minWeeksBeyondHorizon extra weken.
 *
 * @param array<string, array<string, mixed>> $groups
 * @param list<string> $horizonMondays Planning-horizon maandagen (Y-m-d)
 * @return array<string, array<string, mixed>>
 */
function ce_agenda_extend_groups_beyond_horizon(
    array $groups,
    int $currentWeek,
    array $horizonMondays,
    int $minWeeksBeyondHorizon = 4,
    int $bufferWeeks = 2,
): array {
    $weekNumbersPresent = [];
    foreach ($groups as $group) {
        $weekNumber = (int) ($group['weekNumber'] ?? 0);
        if ($weekNumber > 0) {
            $weekNumbersPresent[$weekNumber] = true;
        }
    }

    $lastHorizonWeek = $currentWeek;
    foreach ($horizonMondays as $monday) {
        $monday = substr(trim($monday), 0, 10);
        if ($monday === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday)) {
            continue;
        }

        $weekNumber = (int) ce_phase_key_from_date($monday);
        if ($weekNumber > $lastHorizonWeek) {
            $lastHorizonWeek = $weekNumber;
        }
    }

    $maxWeek = $lastHorizonWeek;
    foreach ($groups as $group) {
        $weekNumber = (int) ($group['weekNumber'] ?? 0);
        if ($weekNumber > $maxWeek) {
            $maxWeek = $weekNumber;
        }

        foreach ($group['rows'] ?? [] as $row) {
            $publishDate = trim((string) ($row['publishDate'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishDate)) {
                continue;
            }

            $publishWeek = (int) ce_phase_key_from_date($publishDate);
            if ($publishWeek > $maxWeek) {
                $maxWeek = $publishWeek;
            }
        }
    }

    $targetWeek = min(
        $currentWeek + 26,
        max($maxWeek + max(0, $bufferWeeks), $lastHorizonWeek + max(1, $minWeeksBeyondHorizon)),
    );

    $addWeek = static function (int $weekNumber) use (&$groups, &$weekNumbersPresent, $currentWeek): void {
        if ($weekNumber < $currentWeek || $weekNumber > $currentWeek + 26) {
            return;
        }
        if (isset($weekNumbersPresent[$weekNumber])) {
            return;
        }

        $weekNumbersPresent[$weekNumber] = true;
        $range = ce_iso_week_range($weekNumber);
        $monday = $range['start']->format('Y-m-d');
        $weekLabel = 'Week ' . $weekNumber;
        $groups[$weekLabel] = [
            'week' => $weekLabel,
            'weekNumber' => $weekNumber,
            'weekRange' => ce_week_range_label($weekNumber),
            'cycleWeek' => $monday,
            'rows' => [],
        ];
    };

    for ($weekNumber = $lastHorizonWeek + 1; $weekNumber <= $targetWeek; $weekNumber++) {
        $addWeek($weekNumber);
    }

    foreach ($groups as $group) {
        foreach ($group['rows'] ?? [] as $row) {
            $publishDate = trim((string) ($row['publishDate'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishDate)) {
                continue;
            }

            $addWeek((int) ce_phase_key_from_date($publishDate));
        }
    }

    uasort(
        $groups,
        static function (array $a, array $b): int {
            $wa = (int) ($a['weekNumber'] ?? 999);
            $wb = (int) ($b['weekNumber'] ?? 999);

            return $wa <=> $wb;
        },
    );

    return $groups;
}

/**
 * @deprecated Gebruik ce_agenda_extend_groups_beyond_horizon na publish_date-invulling.
 *
 * @param array<string, array<string, mixed>> $groups
 * @param list<string> $horizonMondays Planning-horizon maandagen (Y-m-d)
 * @return list<string>
 */
function ce_agenda_display_week_mondays(
    array $groups,
    int $currentWeek,
    array $horizonMondays,
    int $bufferWeeks = 2,
): array {
    $extended = ce_agenda_extend_groups_beyond_horizon($groups, $currentWeek, $horizonMondays, 4, $bufferWeeks);
    $mondays = [];
    foreach ($extended as $group) {
        $weekNumber = (int) ($group['weekNumber'] ?? 0);
        if ($weekNumber < $currentWeek) {
            continue;
        }
        $monday = trim((string) ($group['cycleWeek'] ?? ''));
        if ($monday !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday)) {
            $mondays[] = $monday;
        }
    }

    return $mondays;
}

/**
 * Voegt open theme-weken toe aan de agenda wanneer er nog geen content_slots zijn
 * (bv. Week 30 met theme-voorstellen maar zonder ingediend slot).
 *
 * @param array<string, array<string, mixed>> $groups
 * @param list<array{weekKey: string, weekLabel: string, cycleWeek: string, pendingCount: int}> $themeWeeks
 * @param list<string> $horizonMondays Planning-horizon + eventuele weken voorbij horizon (Y-m-d)
 * @return array<string, array<string, mixed>>
 */
function ce_merge_agenda_theme_horizon_weeks(
    array $groups,
    array $themeWeeks,
    int $currentWeek,
    array $horizonMondays = [],
): array {
    $weekNumbersPresent = [];
    foreach ($groups as $group) {
        $weekNumber = $group['weekNumber'] ?? null;
        if ($weekNumber !== null && (int) $weekNumber > 0) {
            $weekNumbersPresent[(int) $weekNumber] = true;
        }
    }

    foreach ($themeWeeks as $themeWeek) {
        $weekLabel = trim((string) ($themeWeek['weekLabel'] ?? ''));
        if ($weekLabel === '' || !preg_match('/Week\s+(\d{1,2})\b/i', $weekLabel, $matches)) {
            continue;
        }

        $weekNumber = (int) $matches[1];
        if ($weekNumber < $currentWeek) {
            continue;
        }
        if ($weekNumber > $currentWeek + 26) {
            continue;
        }
        if (isset($weekNumbersPresent[$weekNumber])) {
            continue;
        }

        $weekNumbersPresent[$weekNumber] = true;
        $groups[$weekLabel] = [
            'week' => $weekLabel,
            'weekNumber' => $weekNumber,
            'weekRange' => ce_week_range_label($weekNumber),
            'cycleWeek' => trim((string) ($themeWeek['cycleWeek'] ?? $themeWeek['weekKey'] ?? '')),
            'rows' => [],
            'themeOnly' => true,
        ];
    }

    foreach ($horizonMondays as $monday) {
        $monday = substr(trim($monday), 0, 10);
        if ($monday === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday)) {
            continue;
        }

        $weekNumber = (int) ce_phase_key_from_date($monday);
        if ($weekNumber < 1 || $weekNumber > 53) {
            continue;
        }
        if ($weekNumber < $currentWeek) {
            continue;
        }
        if ($weekNumber > $currentWeek + 26) {
            continue;
        }
        if (isset($weekNumbersPresent[$weekNumber])) {
            continue;
        }

        $weekLabel = 'Week ' . $weekNumber;
        $weekNumbersPresent[$weekNumber] = true;
        $groups[$weekLabel] = [
            'week' => $weekLabel,
            'weekNumber' => $weekNumber,
            'weekRange' => ce_week_range_label($weekNumber),
            'cycleWeek' => $monday,
            'rows' => [],
            'themeOnly' => true,
        ];
    }

    uasort(
        $groups,
        static function (array $a, array $b): int {
            $wa = (int) ($a['weekNumber'] ?? 999);
            $wb = (int) ($b['weekNumber'] ?? 999);

            return $wa <=> $wb;
        },
    );

    return $groups;
}

/** Weergave-titel: redactie-edit, anders AI-baseline uit CE-03. */
function ce_article_headline(array $row): string
{
    foreach (['headline_human', 'headline_final'] as $column) {
        if (!array_key_exists($column, $row)) {
            continue;
        }
        $value = trim((string) ($row[$column] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string) ($row['headline'] ?? ''));
}

/** Toon reviewtekst wanneer er nog open review-edits zijn (feedback / pending na review). */
function ce_apply_pending_review_content(array &$payload): void
{
    $edits = $payload['reviewEdits'] ?? null;
    if (!is_array($edits)) {
        return;
    }

    $pending = array_values(array_filter(
        $edits,
        static fn(array $edit): bool => ($edit['status'] ?? '') === 'pending',
    ));

    if ($pending === []) {
        return;
    }

    $latest = $pending[count($pending) - 1];
    $suggested = trim((string) ($latest['suggestedText'] ?? ''));
    if ($suggested !== '') {
        $payload['content'] = $suggested;
    }
}

/** @return array{weakestClaim: string, unsupportedAssumption: string, missedCounterpoint: string, regelbreuk: array{regel: string, citaat: string}|null}|null */
function ce_parse_self_review(mixed $value): ?array
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_string($value)) {
        $decoded = json_decode($value, true);
    } elseif (is_array($value)) {
        $decoded = $value;
    } else {
        return null;
    }

    if (!is_array($decoded)) {
        return null;
    }

    $ce03Error = trim((string) ($decoded['_ce03_self_review_error'] ?? ''));
    if ($ce03Error !== '') {
        return [
            'weakestClaim' => '',
            'unsupportedAssumption' => '',
            'missedCounterpoint' => '',
            'regelbreuk' => null,
            'ce03SelfReviewError' => $ce03Error,
        ];
    }

    $items = [
        'weakestClaim' => trim((string) ($decoded['weakest_claim'] ?? '')),
        'unsupportedAssumption' => trim((string) ($decoded['unsupported_assumption'] ?? '')),
        'missedCounterpoint' => trim((string) ($decoded['missed_counterpoint'] ?? '')),
    ];

    $hasContent = false;
    foreach ($items as $text) {
        if ($text !== '') {
            $hasContent = true;
            break;
        }
    }

    $regelbreuk = null;
    $rawRegelbreuk = $decoded['regelbreuk'] ?? null;
    if (is_array($rawRegelbreuk)) {
        $regel = trim((string) ($rawRegelbreuk['regel'] ?? ''));
        if ($regel !== '') {
            $regelbreuk = [
                'regel' => $regel,
                'citaat' => trim((string) ($rawRegelbreuk['citaat'] ?? '')),
            ];
            $hasContent = true;
        }
    }

    $items['regelbreuk'] = $regelbreuk;

    if (array_key_exists('theme_conflict', $decoded)) {
        $items['themeConflict'] = (bool) ($decoded['theme_conflict'] ?? false);
        $hasContent = $hasContent || $items['themeConflict'];
    }
    $resolution = trim((string) ($decoded['theme_conflict_resolution'] ?? ''));
    if ($resolution !== '') {
        $items['themeConflictResolution'] = $resolution;
        $hasContent = true;
    }
    $why = trim((string) ($decoded['theme_conflict_why'] ?? ''));
    if ($why !== '') {
        $items['themeConflictWhy'] = $why;
        $hasContent = true;
    }

    if (array_key_exists('theme_premisse', $decoded)) {
        $items['themePremisse'] = (bool) ($decoded['theme_premisse'] ?? false);
        $hasContent = $hasContent || $items['themePremisse'];
    }
    $premisseWat = trim((string) ($decoded['theme_premisse_wat'] ?? ''));
    if ($premisseWat !== '') {
        $items['themePremisseWat'] = $premisseWat;
        $hasContent = true;
    }
    $premisseActie = trim((string) ($decoded['theme_premisse_actie'] ?? ''));
    if ($premisseActie !== '') {
        $items['themePremisseActie'] = $premisseActie;
        $hasContent = true;
    }

    $merkBeweringen = [];
    $rawBeweringen = $decoded['merk_beweringen'] ?? $decoded['merkBeweringen'] ?? null;
    if (is_array($rawBeweringen)) {
        foreach ($rawBeweringen as $row) {
            if (!is_array($row)) {
                continue;
            }
            $citaat = trim((string) ($row['citaat'] ?? ''));
            $bron = strtolower(trim((string) ($row['bron'] ?? '')));
            if ($citaat === '') {
                continue;
            }
            if (!in_array($bron, ['merkarchitectuur', 'bewijsveld', 'research', 'geen_bron'], true)) {
                $bron = 'geen_bron';
            }
            $entry = [
                'citaat' => $citaat,
                'bron' => $bron,
            ];
            $steunt = trim((string) ($row['steunt_op'] ?? $row['steuntOp'] ?? ''));
            if ($steunt !== '') {
                $entry['steuntOp'] = $steunt;
            }
            $conf = $row['klant_bevestiging'] ?? $row['klantBevestiging'] ?? null;
            if (is_array($conf)) {
                $entry['klantBevestiging'] = [
                    'type' => trim((string) ($conf['type'] ?? $conf['bron_type'] ?? '')),
                    'waarde' => trim((string) ($conf['waarde'] ?? $conf['bron_waarde'] ?? '')),
                    'bevestigdOp' => trim((string) ($conf['bevestigd_op'] ?? $conf['bevestigdOp'] ?? '')),
                ];
            }
            $merkBeweringen[] = $entry;
        }
    }
    if ($merkBeweringen !== []) {
        $items['merkBeweringen'] = $merkBeweringen;
        $hasContent = true;
    }

    if (!$hasContent) {
        return null;
    }

    require_once __DIR__ . '/self-review-verbodslijst.php';
    foreach (['weakestClaim', 'unsupportedAssumption', 'missedCounterpoint'] as $clientField) {
        $text = trim((string) ($items[$clientField] ?? ''));
        if ($text !== '' && ce_self_review_forbidden_hits_in_text($text) !== []) {
            $items[$clientField] = '';
        }
    }

    return $items;
}

function ce_wordpress_post_id_from_row(array $row): ?string
{
    $id = trim((string) ($row['social_post_id'] ?? ''));
    if ($id !== '' && ctype_digit($id)) {
        return $id;
    }

    return null;
}

function ce_format_article_row(array $row, int $openFeedbackCount = 0): array
{
    require_once __DIR__ . '/editor-questions.php';

    $platformKey = strtolower(trim((string) ($row['platform_final'] ?? $row['platform_suggested'] ?? 'linkedin')));
    $allowPlainSubheads = $platformKey === 'blog';

    $contentAiRaw = (string) ($row['content_ai'] ?? '');
    $contentAi = ce_article_has_real_body($contentAiRaw)
        ? ce_strip_markdown_headings(ce_strip_editor_questions_block($contentAiRaw), $allowPlainSubheads)
        : '';
    $contentHuman = trim((string) ($row['content_human'] ?? ''));
    $content = $contentHuman !== ''
        ? ce_strip_markdown_headings(ce_strip_editor_questions_block($contentHuman), $allowPlainSubheads)
        : $contentAi;

    $hashtags = $row['hashtags_final'] ?? null;
    if ($hashtags === null || $hashtags === '') {
        $hashtags = $row['hashtags_suggested'] ?? '';
    }

    $angle = ce_angle_to_ui($row['angle_final'] ?? $row['angle_suggested'] ?? null);
    $angleSuggested = ce_angle_to_ui($row['angle_suggested'] ?? null);

    $editor = trim((string) ($row['editor_final'] ?? $row['editor_suggested'] ?? ''));
    $editorSuggested = trim((string) ($row['editor_suggested'] ?? ''));

    require_once __DIR__ . '/channel-copy-constraints.php';
    $channelHardIssues = ce_channel_hard_validation_for_article_row($row);

    return [
        'id' => $row['id'],
        'slotId' => $row['slot_id'],
        'weekLabel' => ce_week_label($row['phase_key'] ?? null, $row['phase_week_number'] ?? null),
        'headline' => ce_article_headline($row),
        'headlineAi' => trim((string) ($row['headline'] ?? '')),
        'content' => $content,
        'contentAi' => $contentAi,
        'hashtags' => $hashtags,
        'rationale' => $row['rationale'] ?? '',
        'sourceUrl' => $row['source_url'] ?? null,
        'tone' => ce_tone_to_ui($row['tone_final'] ?? $row['tone_suggested'] ?? null),
        'toneSuggested' => ce_tone_to_ui($row['tone_suggested'] ?? null),
        'angle' => $angle,
        'angleSuggested' => $angleSuggested,
        'editor' => $editor !== '' ? $editor : null,
        'editorSuggested' => $editorSuggested !== '' ? $editorSuggested : null,
        'imageRatio' => ce_resolve_article_image_ratio($row),
        'imageRatioSuggested' => ce_image_ratio_to_ui($row['image_ratio_suggested'] ?? null),
        'platform' => ce_platform_to_ui($row['platform_final'] ?? $row['platform_suggested'] ?? null),
        'platformSuggested' => ce_platform_to_ui($row['platform_suggested'] ?? null),
        'contentTextvorm' => (function () use ($row, $platformKey) {
            require_once __DIR__ . '/channel-copy-constraints.php';
            $stored = trim((string) ($row['content_textvorm'] ?? ''));

            return $stored !== ''
                ? ce_normalize_textvorm($stored)
                : ce_default_textvorm_for_platform($platformKey);
        })(),
        'hashtagsSuggested' => trim((string) ($row['hashtags_suggested'] ?? '')),
        'selfReview' => ce_parse_self_review($row['self_review'] ?? null),
        'imageUrlSquare' => ce_optional_url($row['image_url_square'] ?? null),
        'imageUrlLandscape' => ce_optional_url($row['image_url_landscape'] ?? null),
        'imageSource' => trim((string) ($row['image_source'] ?? '')) ?: null,
        'imageAdvice' => ($t = trim((string) ($row['image_advice'] ?? ''))) !== '' ? $t : null,
        'status' => ce_resolve_editorial_status(
            $row['status'] ?? null,
            $row['slot_status'] ?? null,
            $openFeedbackCount,
        ),
        'slotStatus' => ce_status_to_ui($row['slot_status'] ?? null),
        'articleStatus' => ce_status_to_ui($row['status'] ?? null),
        'wordpressPostId' => ce_wordpress_post_id_from_row($row),
        'channelHardIssues' => $channelHardIssues,
    ];
}

/** RSS-feed / Google News-zoek-URL — geen direct artikel. */
function ce_is_feed_or_search_url(?string $url): bool
{
    $url = strtolower(trim((string) $url));
    if ($url === '') {
        return true;
    }

    if (preg_match('#^https?://news\.google\.com/rss/search#i', $url)) {
        return true;
    }

    if (preg_match('#^https?://news\.google\.com/rss/feeds#i', $url)) {
        return true;
    }

    if (preg_match('#/rss(?:/|\?|$)#i', $url) && !preg_match('#/rss/articles/#i', $url)) {
        return true;
    }

    if (preg_match('#\.(?:xml|rss)(?:\?|$)#i', $url)) {
        return true;
    }

    return false;
}

/** Volg Google News redirect naar uitgever-URL waar mogelijk. */
function ce_resolve_publisher_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if (!preg_match('#^https?://news\.google\.com/#i', $url)) {
        return $url;
    }

    if (!function_exists('curl_init')) {
        return $url;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    curl_exec($ch);
    $final = trim((string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
    curl_close($ch);

    if ($final !== '' && !preg_match('#^https?://news\.google\.com/#i', $final)) {
        return $final;
    }

    return $url;
}

/** LinkedIn-posts horen niet in Theme (eigen content → geen loop). */
function ce_is_linkedin_content_url(?string $url): bool
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('#^www\.#', '', $host);

    return in_array($host, ['linkedin.com', 'lnkd.in'], true);
}

/** @return list<string> Normalized slugs: in/username, company/name */
function ce_linkedin_profile_slugs(?string $raw): array
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }

    $slugs = [];
    if (preg_match('~linkedin\.com/in/([^/?#]+)~i', $raw, $matches)) {
        $slugs[] = 'in/' . strtolower((string) $matches[1]);
    }
    if (preg_match('~linkedin\.com/company/([^/?#]+)~i', $raw, $matches)) {
        $slugs[] = 'company/' . strtolower((string) $matches[1]);
    }

    return $slugs;
}

/** @return array{source_ids: list<string>, slugs: list<string>} */
function ce_client_own_linkedin_research_exclusions(PDO $pdo, ?string $clientId): array
{
    static $cache = [];
    $cacheKey = $clientId ?? '';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $empty = ['source_ids' => [], 'slugs' => []];
    if ($clientId === null || trim($clientId) === '' || !ce_table_exists($pdo, 'sources')) {
        return $cache[$cacheKey] = $empty;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        SELECT id, scraper_linkedin_profile
        FROM sources
        WHERE client_id = :client_id
          AND category = 'linkedin'
        SQL
    );
    $stmt->execute(['client_id' => $clientId]);

    $sourceIds = [];
    $slugs = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $sourceId = trim((string) ($row['id'] ?? ''));
        if ($sourceId !== '') {
            $sourceIds[] = $sourceId;
        }
        foreach (ce_linkedin_profile_slugs((string) ($row['scraper_linkedin_profile'] ?? '')) as $slug) {
            $slugs[] = $slug;
        }
    }

    return $cache[$cacheKey] = [
        'source_ids' => $sourceIds,
        'slugs' => array_values(array_unique($slugs)),
    ];
}

function ce_research_item_is_own_linkedin(PDO $pdo, ?string $clientId, array $row): bool
{
    $type = strtolower(trim((string) ($row['source_type'] ?? '')));
    if ($type === 'linkedin') {
        return true;
    }

    $exclusions = ce_client_own_linkedin_research_exclusions($pdo, $clientId);
    $sourceId = trim((string) ($row['source_id'] ?? ''));
    if ($sourceId !== '' && in_array($sourceId, $exclusions['source_ids'], true)) {
        return true;
    }

    if ($exclusions['slugs'] === []) {
        return false;
    }

    $haystack = strtolower(trim(implode(' ', [
        (string) ($row['url'] ?? ''),
        (string) ($row['title'] ?? ''),
        (string) ($row['snippet'] ?? ''),
        (string) ($row['item_rationale'] ?? ''),
    ])));

    if ($haystack === '') {
        return false;
    }

    foreach ($exclusions['slugs'] as $slug) {
        if (str_contains($haystack, 'linkedin.com/' . $slug) || str_contains($haystack, $slug)) {
            return true;
        }
    }

    return false;
}

function ce_source_is_own_linkedin_page(PDO $pdo, array $sourceRow): bool
{
    if (strtolower(trim((string) ($sourceRow['category'] ?? ''))) === 'linkedin') {
        return true;
    }

    $clientId = trim((string) ($sourceRow['client_id'] ?? ''));
    $profile = trim((string) ($sourceRow['scraper_linkedin_profile'] ?? ''));
    if ($clientId === '' || $profile === '') {
        return false;
    }

    $exclusions = ce_client_own_linkedin_research_exclusions($pdo, $clientId);
    if ($exclusions['slugs'] === []) {
        return false;
    }

    return count(array_intersect(ce_linkedin_profile_slugs($profile), $exclusions['slugs'])) > 0;
}

function ce_research_item_excluded_from_theme_ui(array $row): bool
{
    $type = strtolower(trim((string) ($row['source_type'] ?? '')));
    if (in_array($type, ['linkedin', 'scraper'], true)) {
        return true;
    }

    return ce_is_linkedin_content_url((string) ($row['url'] ?? ''));
}

/** Verwijder ritmrol-/trending-leadins uit themaverhaal (UI toont ritmrol apart). */
function ce_strip_theme_story_leadin(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    for ($i = 0; $i < 6; $i++) {
        $next = $text;
        $next = preg_replace('/^ritmrol\s*(?:\d+\s*=\s*)?[^.\n]+(?:\.\s*)?/iu', '', $next) ?? $next;
        $next = preg_replace('/^ritmrol:\s*[^.\n]+\.\s*/iu', '', $next) ?? $next;
        $next = preg_replace('/^trending signaal:\s*/iu', '', $next) ?? $next;
        $next = trim($next);
        if ($next === $text) {
            break;
        }
        $text = $next;
    }

    return $text;
}

function ce_extract_article_url_from_text(?string $text): string
{
    $text = trim((string) $text);
    if ($text === '' || !preg_match('#https?://[^\s<>"\')\]]+#i', $text, $matches)) {
        return '';
    }

    $url = rtrim($matches[0], '.,;:!?)');
    if ($url === '' || ce_is_feed_or_search_url($url) || ce_is_linkedin_content_url($url)) {
        return '';
    }

    return ce_resolve_publisher_url($url);
}

/**
 * @param list<array<string, mixed>> $researchItems
 */
function ce_resolve_theme_source_article_url(?string $stored, array $researchItems, ?string $rationale = null, ?string $motivation = null): string
{
    $stored = trim((string) $stored);
    if ($stored !== '' && !ce_is_feed_or_search_url($stored) && !ce_is_linkedin_content_url($stored)) {
        return ce_resolve_publisher_url($stored);
    }

    foreach ($researchItems as $item) {
        if ((string) ($item['feedback'] ?? 'none') !== 'liked') {
            continue;
        }
        $candidate = trim((string) ($item['url'] ?? ''));
        if ($candidate === '' || ce_is_feed_or_search_url($candidate) || ce_is_linkedin_content_url($candidate)) {
            continue;
        }

        return ce_resolve_publisher_url($candidate);
    }

    foreach ($researchItems as $item) {
        $candidate = trim((string) ($item['url'] ?? ''));
        if ($candidate === '' || ce_is_feed_or_search_url($candidate) || ce_is_linkedin_content_url($candidate)) {
            continue;
        }

        return ce_resolve_publisher_url($candidate);
    }

    $fromText = ce_extract_article_url_from_text($rationale);
    if ($fromText !== '') {
        return $fromText;
    }

    return ce_extract_article_url_from_text($motivation);
}

/** @return string|null DB-waarde voor source_article_url (feed-URLs worden geweigerd). */
function ce_normalize_theme_source_article_url(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '' || ce_is_feed_or_search_url($url) || ce_is_linkedin_content_url($url)) {
        return null;
    }

    $resolved = ce_resolve_publisher_url($url);
    if ($resolved === '' || ce_is_feed_or_search_url($resolved) || ce_is_linkedin_content_url($resolved)) {
        return null;
    }

    return $resolved;
}
