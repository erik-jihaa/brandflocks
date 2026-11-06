<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-model-config.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/campagne-preview-templates.php';
require_once __DIR__ . '/campagne-preview-product-extract.php';
require_once __DIR__ . '/campagne-preview-bank.php';

const CE_CAMPAGNE_PLAN_CHECK_PROMPT_VERSION = 'plan_check_v0.2';
const CE_CAMPAGNE_PLAN_CHECK_MERK_MIN = 0.60;

/** @var list<string> */
const CE_CAMPAGNE_PLAN_CHECK_SOORTEN = [
    'balans',
    'branchehygiene',
    'regel',
    'algemeen',
    'dekking',
    'ankers_te_weinig',
    'ankers_te_veel',
    'doelpagina',
];

function ce_campagne_plan_check_system_prompt(): string
{
    return <<<'PROMPT'
Je beoordeelt een contentcampagne als geheel, vóórdat er posts worden geschreven.
Je bent GEEN tekstschrijver. Toon NOOIT vaktermen (geen archetype, Foe, mechanisme, hoofdoordeel, slotId, waar_je_tegen_bent).
Noem een post altijd als datum en kanaal, bijvoorbeeld "de post van wo 11 november op LinkedIn". Het veld slotId is alleen voor de JSON, nooit in tekst voor de klant.

MERKARCHITECTUUR:
- Wat het merk doet: {{doel}}
- Waar het merk tegen is: {{foe}}
- Wat het anders maakt: {{distinctiviteit}}
- Waar het merk nee tegen zegt: {{wat_bewust_niet}}
- Regel: {{regels}}
- Categorietaal (vermijden): {{categorietaal}}
- Hygiënefactoren: {{hygienefactoren}}

PRODUCTPAGINA-EXTRACT (kan leeg zijn):
{{product_extract}}

GEPLANDE POSTS:
{{posts}}

Beoordeel:
1. per post of de opdracht het merk versterkt, neutraal is, of verwatert (branchetaal of regelbreuk);
2. één gezamenlijk advies voor het plan;
3. signalen in klanttaal, elk met een voorstel.

Antwoord als JSON:
{
  "advies": "string",
  "post_oordelen": [
    {"slotId": "uuid", "label": "versterkt|neutraal|verwatert", "toelichting": "string"}
  ],
  "signalen": [
    {"soort": "branchehygiene|regel|algemeen|dekking", "tekst": "string", "voorstel": "string", "signaal": true}
  ]
}
PROMPT;
}

/**
 * @param array<string, mixed> $architecture
 * @param array<string, mixed>|null $extract
 * @param list<array<string, mixed>> $slots
 */
function ce_campagne_plan_check_user_vars(array $architecture, ?array $extract, array $slots): array
{
    $postLines = [];
    foreach ($slots as $slot) {
        $anker = is_array($slot['anker'] ?? null) ? $slot['anker'] : [];
        $ankerTekst = trim((string) ($anker['tekst'] ?? $anker['vraag'] ?? $anker['formulering'] ?? ''));
        $postLines[] = sprintf(
            "- slotId=%s | kanaal=%s | fase=%s | rol=%s | merkonderdeel=%s | datum=%s | anker=%s | opdracht=%s",
            (string) ($slot['slotId'] ?? ''),
            (string) ($slot['platformKey'] ?? $slot['platform'] ?? ''),
            (string) ($slot['faseLabel'] ?? $slot['faseKey'] ?? ''),
            (string) ($slot['rol'] ?? ''),
            (string) ($slot['merkOnderdeel'] ?? '(geen)'),
            (string) ($slot['publishDate'] ?? ''),
            $ankerTekst !== '' ? $ankerTekst : '(geen)',
            trim((string) ($slot['opdracht'] ?? '')) !== '' ? (string) $slot['opdracht'] : '(geen opdracht)',
        );
    }

    $extractText = '(geen extract)';
    if (is_array($extract) && $extract !== []) {
        $extractText = json_encode($extract, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    return [
        'doel' => trim((string) ($architecture['doel'] ?? '')) ?: '(niet ingevuld)',
        'foe' => trim((string) ($architecture['foe'] ?? '')) ?: '(niet ingevuld)',
        'distinctiviteit' => trim((string) ($architecture['distinctiviteit'] ?? '')) ?: '(niet ingevuld)',
        'wat_bewust_niet' => trim((string) ($architecture['wat_bewust_niet'] ?? '')) ?: '(niet ingevuld)',
        'regels' => trim((string) ($architecture['regels'] ?? '')) ?: '(niet ingevuld)',
        'categorietaal' => trim((string) ($architecture['categorietaal'] ?? '')) ?: '(geen branche)',
        'hygienefactoren' => trim((string) ($architecture['hygienefactoren'] ?? '')) ?: '(niet ingevuld)',
        'product_extract' => $extractText,
        'posts' => $postLines === [] ? '(geen posts)' : implode("\n", $postLines),
    ];
}

/** @param list<array<string, mixed>> $slots */
function ce_campagne_plan_check_php_balans(array $slots, string $templateKey): ?array
{
    $merk = 0;
    $activatie = 0;
    foreach ($slots as $slot) {
        $rol = strtolower(trim((string) ($slot['rol'] ?? $slot['campaign_rol'] ?? '')));
        if ($rol === 'merk') {
            ++$merk;
        } elseif ($rol === 'activatie') {
            ++$activatie;
        }
    }
    $total = $merk + $activatie;
    if ($total === 0) {
        return null;
    }
    $share = $merk / $total;
    $pct = (int) round($share * 100);
    $isLancering = $templateKey === 'lancering';

    if ($share + 0.0001 >= CE_CAMPAGNE_PLAN_CHECK_MERK_MIN) {
        return [
            'soort' => 'balans',
            'bron' => 'php',
            'tekst' => "Merkbouw in dit plan: {$pct} procent ({$merk} van {$total} posts). Dat ligt op of boven de richtlijn van ongeveer 60 procent.",
            'voorstel' => '',
            'merkShare' => $share,
            'signaal' => false,
        ];
    }

    if ($isLancering) {
        return [
            'soort' => 'balans',
            'bron' => 'php',
            'tekst' => "Merkbouw in dit plan: {$pct} procent ({$merk} van {$total} posts). Dat is lager dan ongeveer 60 procent, maar dit is een lancering: tijdelijk meer activatie is toegestaan.",
            'voorstel' => 'Houd na de lancering weer merkbouw als hoofdmoot.',
            'merkShare' => $share,
            'signaal' => false,
        ];
    }

    return [
        'soort' => 'balans',
        'bron' => 'php',
        'tekst' => "Merkbouw in dit plan: {$pct} procent ({$merk} van {$total} posts). Dat is lager dan ongeveer 60 procent.",
        'voorstel' => 'Schuif een of meer activatieposts naar een merkfase, of markeer de periode bewust als actieperiode.',
        'merkShare' => $share,
        'signaal' => true,
    ];
}

function ce_campagne_plan_check_has_korting(string $text): bool
{
    $t = mb_strtolower($text);

    return preg_match('/korting|afgeprijsd|afprijz|stapelkorting|black friday|%\s*off/u', $t) === 1;
}

function ce_campagne_plan_check_regels_raken_korting(string $regels): bool
{
    $t = mb_strtolower($regels);

    return preg_match('/korting|afprijz|afgeprijsd|aanbieding/u', $t) === 1;
}

/**
 * @param array<string, mixed> $architecture
 * @param array<string, mixed>|null $extract
 * @param list<array<string, mixed>> $slots
 * @param array<string, mixed> $campaign
 */
function ce_campagne_plan_check_php_regelbreuk(
    array $architecture,
    ?array $extract,
    array $slots,
    array $campaign,
): ?array {
    $regels = trim(implode("\n", array_filter([
        (string) ($architecture['regels'] ?? ''),
        (string) ($architecture['wat_bewust_niet'] ?? ''),
        (string) ($architecture['verboden_logica'] ?? ''),
    ])));
    if ($regels === '') {
        return null;
    }

    $parts = [
        (string) ($campaign['title'] ?? ''),
        json_encode($extract ?? [], JSON_UNESCAPED_UNICODE) ?: '',
    ];
    foreach ($slots as $slot) {
        $parts[] = (string) ($slot['opdracht'] ?? '');
        $parts[] = (string) ($slot['title'] ?? '');
    }
    $haystack = implode("\n", $parts);

    if (!ce_campagne_plan_check_has_korting($haystack) || !ce_campagne_plan_check_regels_raken_korting($regels)) {
        return null;
    }

    $regelCitaat = trim((string) ($architecture['regels'] ?? ''));
    if ($regelCitaat === '') {
        $regelCitaat = trim((string) ($architecture['wat_bewust_niet'] ?? ''));
    }

    return [
        'soort' => 'regel',
        'bron' => 'php',
        'tekst' => 'Dit plan botst met je regel: ' . rtrim($regelCitaat, '.') . '.',
        'voorstel' => 'Haal de korting of actie op oude voorraad uit het plan, of herschrijf de opdracht zodat de regel zichtbaar blijft.',
        'signaal' => true,
    ];
}

/** @param array<string, mixed>|null $extract */
function ce_campagne_plan_check_php_ontbreekt(?array $extract): ?array
{
    $items = [];
    foreach ($extract['ontbreekt'] ?? [] as $line) {
        $t = trim((string) $line);
        if ($t !== '') {
            $items[] = $t;
        }
    }
    if ($items === []) {
        return null;
    }

    return [
        'soort' => 'doelpagina',
        'bron' => 'php',
        'tekst' => 'De productpagina mist nog iets dat de posts nodig hebben: ' . implode('; ', $items) . '.',
        'voorstel' => 'Vul de productpagina eerst aan. Posts die dit nodig hebben blijven zwak zolang het er niet staat.',
        'signaal' => true,
        'status' => 'aandachtspunt',
    ];
}

/**
 * @param list<array<string, mixed>> $slots
 */
function ce_campagne_plan_check_php_algemeen(array $slots): array
{
    $n = count($slots);
    if ($n === 0) {
        return ce_campagne_plan_check_ok_row('algemeen', 'Nog geen posts om algemene content te beoordelen.');
    }
    $algemeen = 0;
    foreach ($slots as $slot) {
        $rol = strtolower(trim((string) ($slot['rol'] ?? '')));
        $onderdeel = trim((string) ($slot['merkOnderdeel'] ?? $slot['campaign_merk_onderdeel'] ?? ''));
        $opdracht = trim((string) ($slot['opdracht'] ?? ''));
        if ($rol === 'activatie' && $onderdeel === '' && $opdracht === '') {
            ++$algemeen;
        }
    }
    $threshold = (int) floor($n * 0.2);
    if ($threshold < 2 && $n >= 10) {
        $threshold = 2;
    }
    if ($algemeen > max(2, $threshold)) {
        return [
            'soort' => 'algemeen',
            'bron' => 'php',
            'tekst' => "{$algemeen} van {$n} posts zijn algemene content zonder merk.",
            'voorstel' => 'Koppel die posts aan een merkonderdeel, of schrap ze uit het plan.',
            'signaal' => true,
            'status' => 'aandachtspunt',
        ];
    }

    return ce_campagne_plan_check_ok_row('algemeen', 'Geen overmaat aan algemene content zonder merk.');
}

/**
 * @param list<array<string, mixed>> $slots
 * @param list<string> $wanted
 */
function ce_campagne_plan_check_php_dekking(array $slots, array $wanted): array
{
    if ($wanted === []) {
        return ce_campagne_plan_check_ok_row('dekking', 'Geen merkonderdelen gekozen om te dekken.');
    }
    $seen = [];
    foreach ($slots as $slot) {
        $k = ce_campagne_normalize_merk_onderdeel((string) ($slot['merkOnderdeel'] ?? ''));
        if ($k !== null) {
            $seen[$k] = true;
        }
    }
    $missing = [];
    foreach ($wanted as $k) {
        if (!isset($seen[$k])) {
            $missing[] = $k;
        }
    }
    if ($missing !== []) {
        return [
            'soort' => 'dekking',
            'bron' => 'php',
            'tekst' => 'Dit onderdeel van het merk komt in het plan niet voor: ' . implode(', ', array_map(
                static fn (string $key): string => ce_campagne_merk_onderdeel_label($key),
                $missing,
            )) . '.',
            'voorstel' => 'Wijs het toe aan een of meer posts, of haal het uit wat deze campagne moet laten zien.',
            'signaal' => true,
            'status' => 'aandachtspunt',
        ];
    }

    return ce_campagne_plan_check_ok_row('dekking', 'Elk gekozen merkonderdeel komt in het plan voor.');
}

/**
 * @param list<array<string, mixed>> $slots
 */
function ce_campagne_plan_check_php_ankers(array $slots): array
{
    $n = count($slots);
    $with = 0;
    $factWeekChannel = [];
    $doubleFact = false;
    foreach ($slots as $slot) {
        $anker = $slot['anker'] ?? null;
        $soort = is_array($anker) ? strtolower((string) ($anker['soort'] ?? '')) : '';
        $has = is_array($anker) && (
            trim((string) ($anker['tekst'] ?? $anker['vraag'] ?? $anker['formulering'] ?? '')) !== ''
        );
        if ($has) {
            ++$with;
        }
        if ($has && $soort === 'feit') {
            $date = trim((string) ($slot['publishDate'] ?? ''));
            $week = $date;
            if ($date !== '') {
                try {
                    $week = (new DateTimeImmutable($date))->format('o-W');
                } catch (Throwable) {
                }
            }
            $channel = strtolower((string) ($slot['platformKey'] ?? $slot['platform'] ?? ''));
            $fid = (string) ($anker['id'] ?? '');
            $key = $fid . '|' . $week . '|' . $channel;
            if ($fid !== '' && isset($factWeekChannel[$key])) {
                $doubleFact = true;
            }
            $factWeekChannel[$key] = true;
        }
    }

    $teWeinig = null;
    $teVeel = null;
    if ($n > 0 && $with > 0 && $with < ($n / 5)) {
        $teWeinig = [
            'soort' => 'ankers_te_weinig',
            'bron' => 'php',
            'tekst' => "{$with} van {$n} posts dragen een anker — minder dan één op de vijf.",
            'voorstel' => 'Kies extra vragen of feiten, of laat de app meer posts een anker geven.',
            'signaal' => true,
            'status' => 'aandachtspunt',
        ];
    }
    $share = $n > 0 ? $with / $n : 0;
    if ($n > 0 && ($share > 0.6 + 0.0001 || $doubleFact)) {
        $tekst = $doubleFact
            ? 'Hetzelfde feit staat twee keer in één week op hetzelfde kanaal.'
            : "{$with} van {$n} posts dragen een anker — meer dan zes op de tien. De content dreigt eentonig te worden.";
        $teVeel = [
            'soort' => 'ankers_te_veel',
            'bron' => 'php',
            'tekst' => $tekst,
            'voorstel' => 'Haal ankers van een deel van de posts, of wissel het feit per week en kanaal.',
            'signaal' => true,
            'status' => 'aandachtspunt',
        ];
    }

    return [
        $teWeinig ?? ce_campagne_plan_check_ok_row(
            'ankers_te_weinig',
            $with === 0
                ? 'Deze campagne heeft geen ankers gekozen.'
                : "{$with} van {$n} posts dragen een anker.",
        ),
        $teVeel ?? ce_campagne_plan_check_ok_row('ankers_te_veel', 'Geen overmaat aan ankers.'),
    ];
}

function ce_campagne_plan_check_ok_row(string $soort, string $tekst): array
{
    return [
        'soort' => $soort,
        'bron' => 'php',
        'tekst' => $tekst,
        'voorstel' => '',
        'signaal' => false,
        'status' => 'ok',
    ];
}

function ce_campagne_plan_check_with_status(array $row): array
{
    $signaal = (bool) ($row['signaal'] ?? false);
    $row['signaal'] = $signaal;
    $row['status'] = $signaal ? 'aandachtspunt' : (string) ($row['status'] ?? 'ok');

    return $row;
}

/**
 * @param list<array<string, mixed>> $slots
 * @param list<string> $merkOnderdelen
 * @return list<array<string, mixed>>
 */
function ce_campagne_plan_check_php_signalen(
    array $architecture,
    ?array $extract,
    array $slots,
    array $campaign,
    string $templateKey,
    array $merkOnderdelen = [],
): array {
    $balans = ce_campagne_plan_check_php_balans($slots, $templateKey);
    $regel = ce_campagne_plan_check_php_regelbreuk($architecture, $extract, $slots, $campaign);
    $doelpagina = ce_campagne_plan_check_php_ontbreekt($extract);
    [$teWeinig, $teVeel] = ce_campagne_plan_check_php_ankers($slots);

    $bySoort = [
        'balans' => $balans !== null
            ? ce_campagne_plan_check_with_status($balans)
            : ce_campagne_plan_check_ok_row('balans', 'Nog geen merkbouw/activatie-rollen om te toetsen.'),
        'branchehygiene' => ce_campagne_plan_check_ok_row(
            'branchehygiene',
            'Nog geen oordeel over branchetaal. Dat komt uit de merk-check.',
        ),
        'regel' => $regel !== null
            ? ce_campagne_plan_check_with_status($regel)
            : ce_campagne_plan_check_ok_row('regel', 'Geen botsing met een merkregel gevonden.'),
        'algemeen' => ce_campagne_plan_check_php_algemeen($slots),
        'dekking' => ce_campagne_plan_check_php_dekking($slots, $merkOnderdelen),
        'ankers_te_weinig' => $teWeinig,
        'ankers_te_veel' => $teVeel,
        'doelpagina' => $doelpagina !== null
            ? ce_campagne_plan_check_with_status($doelpagina)
            : ce_campagne_plan_check_ok_row('doelpagina', 'Geen ontbrekend antwoord op de doelpagina gemeld.'),
    ];

    $out = [];
    foreach (CE_CAMPAGNE_PLAN_CHECK_SOORTEN as $soort) {
        $out[] = $bySoort[$soort];
    }

    return $out;
}

function ce_hba_plan_check(PDO $pdo, string $campaignId, ?string $clientId): array
{
    return ce_campagne_preview_run_plan_check($pdo, $campaignId, $clientId);
}

/** @param array<string, mixed> $raw */
function ce_campagne_plan_check_normalize_llm(array $raw, array $slots): array
{
    $advies = trim((string) ($raw['advies'] ?? ''));
    $oordelen = [];
    $knownIds = [];
    foreach ($slots as $slot) {
        $id = (string) ($slot['slotId'] ?? '');
        if ($id !== '') {
            $knownIds[$id] = true;
        }
    }
    foreach ($raw['post_oordelen'] ?? $raw['postOordelen'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $slotId = trim((string) ($item['slotId'] ?? $item['slot_id'] ?? ''));
        $label = ce_hba_v2_normalize_theme_verdict_label((string) ($item['label'] ?? ''));
        if ($slotId === '' || !isset($knownIds[$slotId]) || !in_array($label, ['versterkt', 'neutraal', 'verwatert'], true)) {
            continue;
        }
        $oordelen[] = [
            'slotId' => $slotId,
            'label' => $label,
            'toelichting' => trim((string) ($item['toelichting'] ?? '')),
        ];
    }

    $signalen = [];
    foreach ($raw['signalen'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $tekst = trim((string) ($item['tekst'] ?? ''));
        if ($tekst === '') {
            continue;
        }
        $signalen[] = [
            'soort' => strtolower(trim((string) ($item['soort'] ?? 'algemeen'))) ?: 'algemeen',
            'bron' => 'llm',
            'tekst' => $tekst,
            'voorstel' => trim((string) ($item['voorstel'] ?? '')),
            'signaal' => true,
        ];
    }

    return [
        'advies' => $advies,
        'postOordelen' => $oordelen,
        'signalen' => $signalen,
    ];
}
