<?php

declare(strict_types=1);

/**
 * Ronde 5 stap 1 — losse test van constraint_breach_verificatie (6 gevallen × N runs).
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/hba-prompt.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/hba-testmerken-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$runs = max(1, min(10, (int) ($_GET['runs'] ?? 5)));
$includeVerboden = (string) ($_GET['verboden'] ?? '1') !== '0';

/** @var array<string, array{geloofwaardigheidstest: string, verboden_logica: list<string>}> $narratives */
$narratives = [];
$jsonPath = dirname(__DIR__, 2) . '/briefing/hba-input/HBA_testmerken/alle_drie_testmerken.json';
if (is_readable($jsonPath)) {
    $pack = json_decode((string) file_get_contents($jsonPath), true);
    if (!is_array($pack)) {
        ce_json_error('Ongeldige testmerken-JSON', 500);
    }
    foreach ($pack['merken'] ?? [] as $block) {
        if (!is_array($block)) {
            continue;
        }
        $name = trim((string) ($block['testmerk']['merknaam'] ?? ''));
        $bn = $block['brand_narrative'] ?? null;
        if ($name === '' || !is_array($bn)) {
            continue;
        }
        $verboden = $bn['verboden_logica'] ?? [];
        $narratives[$name] = [
            'geloofwaardigheidstest' => trim((string) ($bn['geloofwaardigheidstest'] ?? '')),
            'verboden_logica' => is_array($verboden)
                ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $verboden)))
                : [],
        ];
    }
}

if ($narratives === []) {
    $pdo = ce_db();
    foreach (ce_hba_testmerk_client_names() as $name) {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE TRIM(name) = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $clientId = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($clientId === '') {
            continue;
        }
        $row = ce_fetch_current_brand_narrative($pdo, $clientId);
        if (!is_array($row)) {
            continue;
        }
        $dto = ce_brand_narrative_dto($row);
        $narratives[$name] = [
            'geloofwaardigheidstest' => trim((string) ($dto['geloofwaardigheidstest'] ?? '')),
            'verboden_logica' => $dto['verbodenLogica'] ?? [],
        ];
    }
}

if ($narratives === []) {
    ce_json_error('Geen HBA-testmerk narratives (JSON of database)', 500);
}

$cases = [
    [
        'merk' => 'Stelwerk',
        'citaat' => 'Wij zijn uw betrouwbare partner voor ERP-implementaties.',
        'uitleg' => 'Het merk presenteert zich als partner voor ERP-implementaties, wat een partnerschap met een leverancier impliceert.',
        'verwachtJa' => false,
    ],
    [
        'merk' => 'Stelwerk',
        'citaat' => 'Wij zijn volledig onafhankelijk.',
        'uitleg' => 'Het merk claimt onafhankelijkheid zonder de commissieregel te noemen.',
        'verwachtJa' => false,
    ],
    [
        'merk' => 'Duinroos Thuiszorg',
        'citaat' => 'Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.',
        'uitleg' => 'Het merk viert efficiëntie en productiviteit als prestatie.',
        'verwachtJa' => false,
    ],
    [
        'merk' => 'Brandpunt Koffie',
        'citaat' => 'Black Friday: alle zakken van vorige maand voor de halve prijs!',
        'uitleg' => 'Het merk biedt oudere voorraad met korting aan, terwijl de regel zegt dat koffie ouder dan zes weken niet verkocht wordt, ook niet met korting.',
        'verwachtJa' => true,
    ],
    [
        'merk' => 'Stelwerk',
        'citaat' => 'Stelwerk is nu officieel gecertificeerd resellerpartner van een groot ERP-merk!',
        'uitleg' => 'Het merk gaat een resellerpartnerschap met een softwareleverancier aan.',
        'verwachtJa' => true,
    ],
    [
        'merk' => 'Duinroos Thuiszorg',
        'citaat' => 'door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst in een maand weggewerkt',
        'uitleg' => 'Het merk zet invalkrachten in om een wachtlijst weg te werken.',
        'verwachtJa' => true,
    ],
];

$results = [];
$summary = ['pass' => 0, 'fail' => 0];

foreach ($cases as $caseIndex => $case) {
    $merk = (string) $case['merk'];
    $narr = $narratives[$merk] ?? null;
    if ($narr === null) {
        $results[] = ['case' => $caseIndex + 1, 'merk' => $merk, 'error' => 'Narrative ontbreekt in JSON'];
        $summary['fail']++;
        continue;
    }

    $verbodenText = $includeVerboden
        ? ce_hba_format_list($narr['verboden_logica'])
        : '';
    $runOut = [];
    $okCount = 0;

    for ($i = 1; $i <= $runs; $i++) {
        $call = ce_hba_call_constraint_breach_verification(
            $narr['geloofwaardigheidstest'],
            (string) $case['citaat'],
            (string) $case['uitleg'],
            $verbodenText,
        );
        $ja = ($call['ok'] ?? false) && ($call['ja'] ?? null) !== null ? (bool) $call['ja'] : null;
        $match = $ja !== null && $ja === (bool) $case['verwachtJa'];
        if ($match) {
            $okCount++;
        }
        $runOut[] = [
            'run' => $i,
            'ok' => $call['ok'] ?? false,
            'ja' => $ja,
            'reden' => $call['reden'] ?? '',
            'match' => $match,
            'error' => $call['error'] ?? '',
        ];
    }

    $casePass = $okCount === $runs;
    if ($casePass) {
        $summary['pass']++;
    } else {
        $summary['fail']++;
    }

    $results[] = [
        'case' => $caseIndex + 1,
        'merk' => $merk,
        'citaat' => $case['citaat'],
        'verwachtJa' => $case['verwachtJa'],
        'runsOk' => $okCount,
        'runsTotal' => $runs,
        'pass' => $casePass,
        'runs' => $runOut,
    ];
}

ce_json_response([
    'ok' => $summary['fail'] === 0,
    'runsPerCase' => $runs,
    'includeVerbodenLogica' => $includeVerboden,
    'summary' => $summary,
    'results' => $results,
]);
