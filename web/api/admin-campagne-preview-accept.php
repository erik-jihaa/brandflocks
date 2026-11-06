<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/campagne-preview-repository.php';
require_once __DIR__ . '/llm-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

if (ce_is_production()) {
    ce_json_error('Alleen op staging/local', 403);
}

set_time_limit(240);

$pdo = ce_db();
$stmt = $pdo->prepare(
    "SELECT id, name FROM clients WHERE name LIKE :q ORDER BY CASE WHEN name = 'Brandpunt Koffie' THEN 0 ELSE 1 END, name ASC LIMIT 1"
);
$stmt->execute(['q' => '%Brandpunt%']);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) {
    ce_json_error('Brandpunt-klant niet gevonden', 404);
}

$clientId = (string) $client['id'];
$start = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$end = (new DateTimeImmutable($start))->modify('+27 days')->format('Y-m-d');
$serieEnd = (new DateTimeImmutable($start))->modify('+41 days')->format('Y-m-d');
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$productPageUrl = ($host !== '' ? 'https://' . $host : 'https://staging.igisnext.com')
    . '/fixtures/brandpunt-huila-blend.html';
$geoIds = ce_admin_campagne_preview_ensure_brandpunt_geo($pdo, $clientId);
$bank = ce_campagne_bank_seed_brandpunt($pdo, $clientId);
$vraagIds = array_slice($bank['vragen'], 0, 2);
$feitIds = array_slice($bank['feiten'], 0, 2);

/**
 * Zet 1–3 GEO-vragen uit de Brandpunt-merkarchitectuur, zodat P5 ankers kan kiezen.
 *
 * @return list<string>
 */
function ce_admin_campagne_preview_ensure_brandpunt_geo(PDO $pdo, string $clientId): array
{
    $wanted = [
        'Wat gebeurt er met Brandpunt-koffie die ouder is dan zes weken?',
        'Waarom staat de branddatum op elke zak Brandpunt?',
        'Van welke boer komt de koffie van Brandpunt?',
    ];
    $existing = ce_fetch_llm_questions($pdo, $clientId, 'geo');
    $byText = [];
    foreach ($existing as $row) {
        $text = trim((string) ($row['question'] ?? ''));
        if ($text !== '') {
            $byText[$text] = trim((string) ($row['id'] ?? ''));
        }
    }

    $ids = [];
    foreach ($wanted as $question) {
        if (isset($byText[$question]) && $byText[$question] !== '') {
            $ids[] = $byText[$question];
            continue;
        }
        if (count($existing) + count($ids) >= 5) {
            break;
        }
        $id = ce_uuid_v4();
        $stmt = $pdo->prepare(
            'INSERT INTO llm_probleemvragen (id, client_id, question, question_kind, active) VALUES (:id, :client_id, :question, :question_kind, 1)'
        );
        $stmt->execute([
            'id' => $id,
            'client_id' => $clientId,
            'question' => $question,
            'question_kind' => 'geo',
        ]);
        $ids[] = $id;
    }

    if ($ids !== []) {
        return array_slice($ids, 0, 3);
    }

    return array_slice(
        array_values(array_filter(array_map(
            static fn (array $q): string => trim((string) ($q['id'] ?? '')),
            $existing,
        ))),
        0,
        3,
    );
}

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function ce_admin_campagne_preview_accept_case(PDO $pdo, array $body, bool $runCheck): array
{
    $created = ce_campagne_preview_create($pdo, $body);
    $campaignId = (string) ($created['campaignId'] ?? '');
    $slots = is_array($created['slots'] ?? null) ? $created['slots'] : [];
    $platforms = [];
    $fases = [];
    $ankers = 0;
    $weekdays = [];
    foreach ($slots as $slot) {
        $platform = strtolower((string) ($slot['platformKey'] ?? $slot['platform'] ?? ''));
        if ($platform !== '') {
            $platforms[$platform] = ($platforms[$platform] ?? 0) + 1;
        }
        $fase = trim((string) ($slot['faseLabel'] ?? $slot['faseKey'] ?? ''));
        if ($fase !== '') {
            $fases[$fase] = true;
        }
        $anker = is_array($slot['anker'] ?? null) ? $slot['anker'] : null;
        if ($anker !== null && trim((string) ($anker['tekst'] ?? $anker['vraag'] ?? $anker['formulering'] ?? '')) !== '') {
            ++$ankers;
        }
        $publishDate = trim((string) ($slot['publishDate'] ?? ''));
        if ($publishDate !== '') {
            $weekdays[] = (int) (new DateTimeImmutable($publishDate))->format('N');
        }
    }

    $planCheck = null;
    if ($runCheck && $campaignId !== '') {
        $planCheck = ce_campagne_preview_run_plan_check($pdo, $campaignId, (string) $body['clientId']);
    }

    $signalen = is_array($planCheck['signalen'] ?? null) ? $planCheck['signalen'] : [];
    $regel = false;
    foreach ($signalen as $sig) {
        if (($sig['soort'] ?? '') === 'regel' && ($sig['signaal'] ?? false) === true) {
            $regel = true;
        }
    }

    return [
        'campaignId' => $campaignId,
        'title' => (string) ($created['campaign']['title'] ?? $body['title'] ?? ''),
        'slotCount' => count($slots),
        'platforms' => $platforms,
        'weekdays' => $weekdays,
        'faseLabels' => array_keys($fases),
        'ankerCount' => $ankers,
        'opdrachtSample' => trim((string) ($slots[0]['opdracht'] ?? '')),
        'productExtractOk' => (bool) ($created['productExtract']['ok'] ?? false),
        'productExtractError' => trim((string) ($created['productExtract']['error'] ?? '')),
        'productWat' => trim((string) ($created['productExtract']['extract']['wat'] ?? $created['campaign']['productExtract']['wat'] ?? '')),
        'planCheckOk' => (bool) ($planCheck['ok'] ?? false),
        'planCheckAdvies' => (string) ($planCheck['advies'] ?? ''),
        'planCheckLlmOk' => (bool) ($planCheck['llmOk'] ?? false),
        'regelbreukSignaal' => $regel,
        'planCheckSoorten' => array_values(array_unique(array_map(
            static fn (array $s): string => (string) ($s['soort'] ?? ''),
            $signalen,
        ))),
        'signalen' => array_map(
            static fn (array $s): array => [
                'soort' => (string) ($s['soort'] ?? ''),
                'signaal' => (bool) ($s['signaal'] ?? false),
                'status' => (string) ($s['status'] ?? (($s['signaal'] ?? false) ? 'aandachtspunt' : 'ok')),
                'tekst' => (string) ($s['tekst'] ?? ''),
            ],
            $signalen,
        ),
    ];
}

$body = ce_read_json_body();
$only = strtolower(trim((string) ($body['only'] ?? 'lancering')));
$catalog = [
    'lancering' => [
        'runCheck' => true,
        'expectSlots' => 20,
        'payload' => [
            'clientId' => $clientId,
            'title' => 'Ronde 8 preview — lancering blend',
            'startDate' => $start,
            'endDate' => $end,
            'channels' => ['linkedin' => 8, 'instagram' => 12],
            'goal' => 'beide',
            'templateKey' => 'lancering',
            'geoQuestionIds' => $geoIds,
            'vraagIds' => $vraagIds,
            'feitIds' => $feitIds,
            'merkOnderdelen' => CE_CAMPAGNE_MERK_ONDERDELEN,
        ],
    ],
    'extract' => [
        'runCheck' => false,
        'expectSlots' => 20,
        'payload' => [
            'clientId' => $clientId,
            'title' => 'Ronde 8 preview — lancering blend + productpagina',
            'startDate' => $start,
            'endDate' => $end,
            'channels' => ['linkedin' => 8, 'instagram' => 12],
            'goal' => 'beide',
            'templateKey' => 'lancering',
            'productPageUrl' => $productPageUrl,
            'geoQuestionIds' => $geoIds,
        ],
    ],
    'korting' => [
        'runCheck' => true,
        'expectSlots' => 20,
        'payload' => [
            'clientId' => $clientId,
            'title' => 'Ronde 8 preview — Black Friday korting op koffie die al weken ligt',
            'startDate' => $start,
            'endDate' => $end,
            'channels' => ['linkedin' => 8, 'instagram' => 12],
            'goal' => 'verkopen',
            'templateKey' => 'lancering',
            'geoQuestionIds' => $geoIds,
            'vraagIds' => $vraagIds,
            'feitIds' => $feitIds,
            'merkOnderdelen' => CE_CAMPAGNE_MERK_ONDERDELEN,
        ],
    ],
    'ankers-teveel' => [
        'runCheck' => true,
        'expectSlots' => 20,
        'payload' => [
            'clientId' => $clientId,
            'title' => 'Ronde 6 — alle posts anker',
            'startDate' => $start,
            'endDate' => $end,
            'channels' => ['linkedin' => 8, 'instagram' => 12],
            'goal' => 'beide',
            'templateKey' => 'lancering',
            'vraagIds' => $vraagIds,
            'feitIds' => $feitIds,
            'forceAllAnkers' => true,
            'merkOnderdelen' => CE_CAMPAGNE_MERK_ONDERDELEN,
        ],
    ],
    'serie' => [
        'runCheck' => false,
        'expectSlots' => 6,
        'payload' => [
            'clientId' => $clientId,
            'title' => 'Ronde 8 preview — serie 6× dinsdag',
            'startDate' => $start,
            'endDate' => $serieEnd,
            'channels' => ['linkedin' => 6],
            'goal' => 'merk',
            'templateKey' => 'serie',
            'schedule' => ['mode' => 'weekly', 'weekday' => 2],
            'geoQuestionIds' => $geoIds,
        ],
    ],
];

if (!isset($catalog[$only])) {
    ce_json_error('only moet lancering, extract, korting, serie of ankers-teveel zijn', 400);
}

$def = $catalog[$only];
try {
    $result = ce_admin_campagne_preview_accept_case($pdo, $def['payload'], (bool) $def['runCheck']);
} catch (InvalidArgumentException $e) {
    ce_json_error($e->getMessage(), 400);
}

$slotOk = $result['slotCount'] === (int) $def['expectSlots'];
$ok = $slotOk;
$checks = [
    'slotCount' => $slotOk,
];
if ($only === 'lancering') {
    $expectAnkers = (int) round(((int) $def['expectSlots']) / 3);
    $checks['heeftFases'] = $result['faseLabels'] !== [];
    $checks['heeftOpdracht'] = $result['opdrachtSample'] !== '';
    $checks['ankers'] = $result['ankerCount'] === $expectAnkers;
    $checks['geenRegel'] = $result['regelbreukSignaal'] === false;
    $checks['achtSignalen'] = count($result['planCheckSoorten'] ?? []) >= 8;
    $ok = $ok && $checks['heeftFases'] && $checks['heeftOpdracht'] && $checks['ankers']
        && $checks['geenRegel'] && $checks['achtSignalen'];
}
if ($only === 'extract') {
    $checks['productExtract'] = $result['productExtractOk'] === true && $result['productWat'] !== '';
    $checks['opdrachtHeeftProduct'] = str_contains((string) $result['opdrachtSample'], 'Product (van pagina):');
    $ok = $ok && $checks['productExtract'] && $checks['opdrachtHeeftProduct'];
}
if ($only === 'korting') {
    $checks['regelbreuk'] = $result['regelbreukSignaal'] === true;
    $ok = $ok && $checks['regelbreuk'];
}
if ($only === 'ankers-teveel') {
    $checks['alleAnkers'] = $result['ankerCount'] === (int) $def['expectSlots'];
    $teveel = false;
    foreach ($result['signalen'] as $sig) {
        if (($sig['soort'] ?? '') === 'ankers_te_veel' && ($sig['signaal'] ?? false) === true) {
            $teveel = true;
        }
    }
    $checks['ankersTeveel'] = $teveel;
    $ok = $ok && $checks['alleAnkers'] && $checks['ankersTeveel'];
}
if ($only === 'serie') {
    $weekdays = is_array($result['weekdays'] ?? null) ? $result['weekdays'] : [];
    $allTuesday = $weekdays !== [] && count(array_filter($weekdays, static fn ($d): bool => (int) $d !== 2)) === 0;
    $checks['linkedin6'] = ($result['platforms']['linkedin'] ?? 0) === 6;
    $checks['dinsdag'] = $allTuesday;
    $ok = $ok && $checks['linkedin6'] && $checks['dinsdag'];
}

ce_json_response([
    'ok' => $ok,
    'only' => $only,
    'client' => ['id' => $clientId, 'name' => (string) $client['name']],
    'geoQuestionCount' => count($geoIds),
    'bank' => ['vragen' => count($bank['vragen']), 'feiten' => count($bank['feiten'])],
    'case' => $result,
    'checks' => $checks,
]);
