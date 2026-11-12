<?php

declare(strict_types=1);

require_once __DIR__ . '/copy-modus.php';

/**
 * Ronde 8 P2 — sjablonen A (lancering) en B (serie), vaste fase-aandelen (addendum §2).
 *
 * @return array<string, array{key: string, label: string, phases: list<array{key: string, label: string, share: float, rol: string, merkOnderdeel: string|null, opdracht: string}>}>
 */
function ce_campagne_preview_template_catalog(): array
{
    return [
        'lancering' => [
            'key' => 'lancering',
            'label' => 'Lancering product of productlijn',
            'phases' => [
                [
                    'key' => 'aankondiging',
                    'label' => 'Aankondiging',
                    'share' => 0.15,
                    'rol' => 'activatie',
                    'merkOnderdeel' => 'wat_je_doet',
                    'opdracht' => 'Leg uit wat het product is en voor wie het bedoeld is.',
                ],
                [
                    'key' => 'waarom',
                    'label' => 'Waarom we dit maken',
                    'share' => 0.20,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'waarom',
                    'opdracht' => 'Toon het doel of de tegenkracht die dit product adresseert.',
                ],
                [
                    'key' => 'hoe_werkt',
                    'label' => 'Hoe het werkt',
                    'share' => 0.20,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'hoe_je_werkt',
                    'opdracht' => 'Laat zien wat dit product anders maakt dan vergelijkbare producten in de branche.',
                ],
                [
                    'key' => 'bewust_niet',
                    'label' => 'Wat we bewust niet doen',
                    'share' => 0.10,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'waar_je_nee_tegen_zegt',
                    'opdracht' => 'Noem één bewuste keuze rond het product die laat zien waar jullie nee tegen zeggen.',
                ],
                [
                    'key' => 'bewijs',
                    'label' => 'Bewijs',
                    'share' => 0.20,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'hoe_je_werkt',
                    'opdracht' => 'Gebruik concreet bewijs: klant, maker of test.',
                ],
                [
                    'key' => 'actie',
                    'label' => 'Actie',
                    'share' => 0.15,
                    'rol' => 'activatie',
                    'merkOnderdeel' => null,
                    'opdracht' => 'Roep op tot de gewenste actie: bestellen, proberen of aanmelden.',
                ],
            ],
        ],
        'touch' => [
            'key' => 'touch',
            'label' => 'Touch',
            'phases' => [
                [
                    'key' => 'herkenning',
                    'label' => 'Herkenning',
                    'share' => 0.40,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'wat_je_doet',
                    'opdracht' => 'Laat in één beeld of zin zien wie jullie zijn. Kort, herkenbaar, geen opbouw.',
                ],
                [
                    'key' => 'anker',
                    'label' => 'Anker/Inzicht',
                    'share' => 0.35,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'hoe_je_werkt',
                    'opdracht' => 'Eén anker: één vraag of één feit. Geen argumentatie in stappen.',
                ],
                [
                    'key' => 'connect',
                    'label' => 'Call-to-connect',
                    'share' => 0.25,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'waarom',
                    'opdracht' => 'Nodig uit tot contact of herkenning, zonder verkooptaal of prijsaanbieding.',
                ],
            ],
        ],
        'serie' => [
            'key' => 'serie',
            'label' => 'Serie of vaste rubriek',
            'phases' => [
                [
                    'key' => 'vraag',
                    'label' => 'Vraag',
                    'share' => 0.34,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'wat_je_doet',
                    'opdracht' => 'Open de aflevering met de vraag of het hook van deze rubriek.',
                ],
                [
                    'key' => 'antwoord',
                    'label' => 'Antwoord',
                    'share' => 0.33,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'hoe_je_werkt',
                    'opdracht' => 'Beantwoord de vraag vanuit één vast merkonderdeel — geen algemene branchetips.',
                ],
                [
                    'key' => 'voorbeeld',
                    'label' => 'Voorbeeld',
                    'share' => 0.33,
                    'rol' => 'merk',
                    'merkOnderdeel' => 'hoe_je_werkt',
                    'opdracht' => 'Sluit af met een concreet voorbeeld dat hetzelfde merkonderdeel laat zien.',
                ],
            ],
        ],
    ];
}

function ce_campagne_preview_normalize_template_key(string $raw): string
{
    $key = strtolower(trim($raw));
    if ($key === '' || $key === 'default') {
        return 'lancering';
    }
    if (in_array($key, ['a', 'lancering', 'lancering_product'], true)) {
        return 'lancering';
    }
    if (in_array($key, ['b', 'serie', 'rubriek', 'tell'], true)) {
        return 'serie';
    }
    if (in_array($key, ['sell'], true)) {
        return 'lancering';
    }
    if (in_array($key, ['touch'], true)) {
        return 'touch';
    }
    if (isset(ce_campagne_preview_template_catalog()[$key])) {
        return $key;
    }

    throw new InvalidArgumentException('templateKey moet sell, tell of touch zijn');
}

/**
 * @param array<string, mixed>|null $phasePlan disabledKeys[], order[], merkOnderdeelByKey[]
 * @return list<array{key: string, label: string, share: float, rol: string, merkOnderdeel: string|null, opdracht: string}>
 */
function ce_campagne_preview_resolve_phases(string $templateKey, ?array $phasePlan = null): array
{
    $catalog = ce_campagne_preview_template_catalog();
    $template = $catalog[$templateKey] ?? null;
    if ($template === null) {
        throw new InvalidArgumentException('Onbekend sjabloon: ' . $templateKey);
    }

    $phases = $template['phases'];
    $disabled = [];
    if (is_array($phasePlan['disabledKeys'] ?? null)) {
        foreach ($phasePlan['disabledKeys'] as $k) {
            $disabled[strtolower(trim((string) $k))] = true;
        }
    }

    $merkOverrides = [];
    if (is_array($phasePlan['merkOnderdeelByKey'] ?? null)) {
        foreach ($phasePlan['merkOnderdeelByKey'] as $phaseKey => $onderdeel) {
            $k = strtolower(trim((string) $phaseKey));
            if ($k === '') {
                continue;
            }
            if ($onderdeel === null || $onderdeel === '') {
                $merkOverrides[$k] = null;
            } else {
                $merkOverrides[$k] = ce_campagne_normalize_merk_onderdeel((string) $onderdeel);
            }
        }
    }

    $active = [];
    foreach ($phases as $phase) {
        $pKey = (string) ($phase['key'] ?? '');
        if ($pKey === '' || isset($disabled[$pKey])) {
            continue;
        }
        if (array_key_exists($pKey, $merkOverrides)) {
            $phase['merkOnderdeel'] = $merkOverrides[$pKey];
        }
        $active[] = $phase;
    }

    if ($active === []) {
        throw new InvalidArgumentException('Minimaal één fase moet actief blijven');
    }

    $order = $phasePlan['order'] ?? null;
    if (is_array($order) && $order !== []) {
        $byKey = [];
        foreach ($active as $phase) {
            $byKey[(string) $phase['key']] = $phase;
        }
        $sorted = [];
        foreach ($order as $key) {
            $k = strtolower(trim((string) $key));
            if (isset($byKey[$k])) {
                $sorted[] = $byKey[$k];
                unset($byKey[$k]);
            }
        }
        foreach ($byKey as $phase) {
            $sorted[] = $phase;
        }
        $active = $sorted;
    }

    $shareSum = array_sum(array_map(static fn (array $p): float => (float) ($p['share'] ?? 0), $active));
    if ($shareSum <= 0) {
        throw new InvalidArgumentException('Fases hebben geen geldige aandelen');
    }

    $normalized = [];
    foreach ($active as $phase) {
        $phase['share'] = (float) $phase['share'] / $shareSum;
        $normalized[] = $phase;
    }

    return $normalized;
}

/**
 * @param list<array{key: string, label: string, share: float, rol: string, merkOnderdeel: string|null, opdracht: string}> $phases
 * @return list<array{key: string, label: string, share: float, rol: string, merkOnderdeel: string|null, opdracht: string}>
 */
function ce_campagne_preview_allocate_phases(array $phases, int $totalPosts): array
{
    if ($totalPosts <= 0) {
        return [];
    }

    $phaseCount = count($phases);
    if ($totalPosts < $phaseCount) {
        throw new InvalidArgumentException(
            'Te weinig posts (' . $totalPosts . ') voor ' . $phaseCount . ' actieve fases (minimaal één post per fase)'
        );
    }

    $counts = array_fill(0, $phaseCount, 0);
    $remainders = [];
    $floorSum = 0;
    foreach ($phases as $i => $phase) {
        $exact = $totalPosts * (float) $phase['share'];
        $counts[$i] = (int) floor($exact);
        $remainders[$i] = $exact - $counts[$i];
        $floorSum += $counts[$i];
    }

    $left = $totalPosts - $floorSum;
    arsort($remainders);
    foreach (array_keys($remainders) as $i) {
        if ($left <= 0) {
            break;
        }
        ++$counts[$i];
        --$left;
    }

    foreach ($counts as $i => $count) {
        if ($count < 1) {
            $counts[$i] = 1;
        }
    }

    while (array_sum($counts) > $totalPosts) {
        $pick = null;
        $pickVal = 0;
        foreach ($counts as $i => $count) {
            if ($count > 1 && $count > $pickVal) {
                $pickVal = $count;
                $pick = $i;
            }
        }
        if ($pick === null) {
            break;
        }
        --$counts[$pick];
    }

    while (array_sum($counts) < $totalPosts) {
        $pick = 0;
        $pickShare = -1.0;
        foreach ($phases as $i => $phase) {
            $share = (float) $phase['share'];
            if ($share > $pickShare) {
                $pickShare = $share;
                $pick = $i;
            }
        }
        ++$counts[$pick];
    }

    $sequence = [];
    foreach ($phases as $i => $phase) {
        for ($n = 0; $n < $counts[$i]; $n++) {
            $sequence[] = $phase;
        }
    }

    return $sequence;
}

/** @return list<string> Y-m-d */
function ce_campagne_preview_distribute_dates(string $startDate, string $endDate, int $count): array
{
    if ($count <= 0) {
        return [];
    }

    $start = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    if ($end < $start) {
        throw new InvalidArgumentException('Einddatum moet op of na startdatum liggen');
    }

    if ($count === 1) {
        return [$start->format('Y-m-d')];
    }

    $days = (int) $start->diff($end)->days;
    if ($days + 1 < $count) {
        throw new InvalidArgumentException(
            'Te weinig dagen in de periode voor ' . $count . ' posts op dit kanaal (max. 1 per dag)'
        );
    }

    $dates = [];
    for ($i = 0; $i < $count; $i++) {
        $offset = (int) round($i * $days / ($count - 1));
        $dates[] = $start->modify('+' . $offset . ' days')->format('Y-m-d');
    }

    return ce_campagne_preview_unique_dates($dates, $endDate);
}

/** @param list<string> $dates */
function ce_campagne_preview_unique_dates(array $dates, string $maxDate): array
{
    $used = [];
    $max = new DateTimeImmutable($maxDate);
    $out = [];

    foreach ($dates as $date) {
        $cursor = new DateTimeImmutable($date);
        while (isset($used[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('+1 day');
            if ($cursor > $max) {
                throw new InvalidArgumentException('Periode te kort: kan niet uniek 1 post per dag per kanaal plannen');
            }
        }
        $key = $cursor->format('Y-m-d');
        $used[$key] = true;
        $out[] = $key;
    }

    return $out;
}

/** ISO weekday 1=ma … 7=zo */
function ce_campagne_preview_normalize_weekday(mixed $value): int
{
    $day = (int) $value;
    if ($day < 1 || $day > 7) {
        throw new InvalidArgumentException('schedule.weekday moet 1 (ma) t/m 7 (zo) zijn');
    }

    return $day;
}

/** @return list<string> Y-m-d */
function ce_campagne_preview_weekly_dates(string $startDate, int $weekdayIso, int $count, ?string $endDate = null): array
{
    if ($count <= 0) {
        return [];
    }

    $cursor = new DateTimeImmutable($startDate);
    $cursorWeekday = (int) $cursor->format('N');
    $delta = ($weekdayIso - $cursorWeekday + 7) % 7;
    $first = $cursor->modify('+' . $delta . ' days');
    $max = $endDate !== null && $endDate !== '' ? new DateTimeImmutable($endDate) : null;

    $dates = [];
    $current = $first;
    for ($i = 0; $i < $count; $i++) {
        if ($max !== null && $current > $max) {
            throw new InvalidArgumentException('Periode te kort voor ' . $count . ' posts op vaste weekdag');
        }
        $dates[] = $current->format('Y-m-d');
        $current = $current->modify('+7 days');
    }

    return $dates;
}

/**
 * @param array<string, int> $channels
 * @param array<string, mixed>|null $schedule
 * @return list<array{platform: string, publishDate: string, slotIndexOnPlatform: int, totalOnPlatform: int}>
 */
function ce_campagne_preview_build_slot_schedule(
    string $startDate,
    string $endDate,
    array $channels,
    ?array $schedule = null,
): array {
    $mode = strtolower(trim((string) ($schedule['mode'] ?? 'spread')));
    $stubs = [];

    if ($mode === 'weekly') {
        $weekday = ce_campagne_preview_normalize_weekday($schedule['weekday'] ?? 2);
        foreach ($channels as $platform => $count) {
            $dates = ce_campagne_preview_weekly_dates($startDate, $weekday, $count, $endDate);
            for ($i = 0; $i < $count; $i++) {
                $stubs[] = [
                    'platform' => $platform,
                    'publishDate' => $dates[$i],
                    'slotIndexOnPlatform' => $i + 1,
                    'totalOnPlatform' => $count,
                ];
            }
        }
    } else {
        foreach ($channels as $platform => $count) {
            $dates = ce_campagne_preview_distribute_dates($startDate, $endDate, $count);
            for ($i = 0; $i < $count; $i++) {
                $stubs[] = [
                    'platform' => $platform,
                    'publishDate' => $dates[$i],
                    'slotIndexOnPlatform' => $i + 1,
                    'totalOnPlatform' => $count,
                ];
            }
        }
    }

    usort(
        $stubs,
        static function (array $a, array $b): int {
            $cmp = strcmp($a['publishDate'], $b['publishDate']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['platform'], $b['platform']);
        },
    );

    return $stubs;
}

function ce_campagne_sell_fit_hint(PDO $pdo, ?string $clientId): ?string
{
    if ($clientId === null || $clientId === '' || !function_exists('ce_hba_v2_architecture_context')) {
        return null;
    }
    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    $blob = mb_strtolower(implode(' ', [
        (string) ($ctx['regels'] ?? ''),
        (string) ($ctx['verboden_logica'] ?? ''),
        (string) ($ctx['wat_bewust_niet'] ?? ''),
    ]));
    if (!preg_match('/afprijs|korting|prijsactie|nooit[^.]{0,40}prijs|geen korting/u', $blob)) {
        return null;
    }

    return 'Let op: dit merk profileert zich normaal niet met prijsacties. Sell kan hier wringen. Je kunt toch doorgaan.';
}

function ce_campagne_preview_templates_payload(): array
{
    $templates = [];
    foreach (ce_campagne_preview_template_catalog() as $key => $def) {
        $templates[] = [
            'key' => $key,
            'label' => $def['label'],
            'phases' => array_map(
                static fn (array $p): array => [
                    'key' => $p['key'],
                    'label' => $p['label'],
                    'share' => $p['share'],
                    'rol' => $p['rol'],
                    'merkOnderdeel' => $p['merkOnderdeel'],
                    'opdracht' => $p['opdracht'],
                ],
                $def['phases'],
            ),
        ];
    }

    return [
        'templates' => $templates,
        'planProgress' => ce_campagne_plan_progress_stages(),
    ];
}

/** @return list<array{key: string, label: string}> */
function ce_campagne_plan_progress_stages(): array
{
    return [
        ['key' => 'plan', 'label' => 'Plan maken'],
        ['key' => 'check', 'label' => 'Check uitvoeren'],
        ['key' => 'posts', 'label' => 'Posts beoordelen'],
    ];
}

function ce_campagne_plan_progress_label(string $key): string
{
    foreach (ce_campagne_plan_progress_stages() as $stage) {
        if ($stage['key'] === $key) {
            return $stage['label'];
        }
    }

    return $key;
}

function ce_campagne_preview_fase_label(?string $templateKey, ?string $faseKey): ?string
{
    $key = trim((string) $faseKey);
    if ($key === '') {
        return null;
    }

    try {
        $template = ce_campagne_preview_normalize_template_key(trim((string) ($templateKey ?? 'lancering')));
        foreach (ce_campagne_preview_resolve_phases($template, null) as $phase) {
            if ((string) ($phase['key'] ?? '') === $key) {
                return (string) ($phase['label'] ?? $key);
            }
        }
    } catch (InvalidArgumentException) {
    }

    return $key;
}
