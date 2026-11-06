#!/usr/bin/env php
<?php
/**
 * Offline replay hoofdoordeel uit v1.13 stability JSON (geen LLM, geen staging).
 * Levert docs/hba-v1.13-tegenkracht-diagnose.md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/hba-prompt.php';
require_once $root . '/web/api/hba-repository.php';
require_once __DIR__ . '/hba-v1.12-replay-lib.php';

$jsonPath = $root . '/docs/hba-testmerken-stability-v1.13-5runs.json';
$postsPath = $root . '/briefing/hba-input/HBA_testmerken/alle_drie_testmerken.json';
$outPath = $root . '/docs/hba-v1.13-tegenkracht-diagnose.md';

$payload = json_decode((string) file_get_contents($jsonPath), true);
$postsByMerk = hba_diagnose_load_testposts($postsPath);
$runsPerPost = (int) ($payload['runs'] ?? 5);

$lines = [];
$lines[] = '# HBA v1.13 — tegenkracht-diagnose (offline replay, spoor 1b)';
$lines[] = '';
$lines[] = 'Gegenereerd: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = 'Bron: `docs/hba-testmerken-stability-v1.13-5runs.json` (95 scores, geen modelcalls).';
$lines[] = '';
$lines[] = 'Twee varianten van `ce_hba_resolve_hoofdoordeel()` op dezelfde flags/citaten:';
$lines[] = '- **Live v1.13:** trede 2 = `foe_vervaging` + `foe_on_kern` (nieuwe foe-definitie, zonder `&& !kernCat`).';
$lines[] = '- **Met voorwaarde terug:** trede 2 = `foe_vervaging` + `foe_on_kern` + **`!kern_categorietaal`**.';
$lines[] = '';
$lines[] = '`merkbewering` / `kern_categorietaal` komen uit de matrix-export per run (geen backfill).';
$lines[] = '';

$tableRows = [];
$stableNow = 0;
$stableLegacy = 0;
$stableActual = 0;

foreach ($payload['rows'] ?? [] as $row) {
    $merk = (string) ($row['merk'] ?? '');
    $label = (string) ($row['label'] ?? '');
    $idx = (int) ($row['index'] ?? 0);
    $verwacht = isset($row['verwacht_hoofdoordeel']) ? (string) $row['verwacht_hoofdoordeel'] : null;
    $postText = $postsByMerk[$merk][$idx]['post_tekst'] ?? '';
    $zones = ce_hba_resolve_kop_en_openingszin($postText, null);

    $goodNow = 0;
    $goodLegacy = 0;
    $goodActual = 0;
    $runDetails = [];

    foreach ($row['runs'] ?? [] as $run) {
        $tool = hba_diagnose_build_tool($run, $merk, $label);
        $foeCitaat = trim((string) ($tool['flag_citaten']['foe_vervaging'] ?? ''));
        $zone = hba_diagnose_foe_zone($foeCitaat, $postText);
        $foeOnKern = $zone === 'kop' || $zone === 'openingszin';
        $ctx = ['foe_on_kern' => $foeOnKern];
        $now = ce_hba_resolve_hoofdoordeel($tool, $ctx);
        $legacy = hba_diagnose_resolve_legacy_trede2($tool, $ctx);
        $actual = (string) ($run['hoofdoordeel'] ?? '');

        if (hba_diagnose_judge($now, $verwacht) === 'goed') {
            $goodNow++;
        }
        if (hba_diagnose_judge($legacy, $verwacht) === 'goed') {
            $goodLegacy++;
        }
        if (hba_diagnose_judge($actual, $verwacht) === 'goed') {
            $goodActual++;
        }

        $runDetails[] = [
            'run' => (int) ($run['run'] ?? 0),
            'actual' => $actual,
            'now' => $now,
            'legacy' => $legacy,
            'zone' => $zone,
            'foe_on_kern' => $foeOnKern,
            'foe' => $foeCitaat,
            'flags' => ce_hba_flags_from_tool($tool),
        ];
    }

    $scoreable = $verwacht !== null && $verwacht !== '';
    if ($scoreable && $goodNow === $runsPerPost) {
        $stableNow++;
    }
    if ($scoreable && $goodLegacy === $runsPerPost) {
        $stableLegacy++;
    }
    if ($scoreable && $goodActual === $runsPerPost) {
        $stableActual++;
    }

    $tableRows[] = [
        'merk' => $merk,
        'label' => $label,
        'verwacht' => $verwacht,
        'goodNow' => $goodNow,
        'goodLegacy' => $goodLegacy,
        'goodActual' => $goodActual,
        'kop' => $zones['kop'],
        'opening' => $zones['opening'],
        'runs' => $runDetails,
    ];
}

$lines[] = '## Samenvatting stable 5/5 (scorebare posts)';
$lines[] = '';
$lines[] = '| Variant | Stable goed |';
$lines[] = '| --- | ---: |';
$lines[] = '| Opgeslagen in run (v1.13 live) | **' . $stableActual . '** / 19 |';
$lines[] = '| Replay **nu** (zelfde regels) | **' . $stableNow . '** / 19 |';
$lines[] = '| Replay **met `&& !kernCat` terug** | **' . $stableLegacy . '** / 19 |';
$lines[] = '';
$lines[] = 'Bij v1.13 live **19/19** stable: replay nu zou gelijk moeten lopen met live; legacy toont wat de oude trede-2-voorwaarde zou doen op dezelfde scores.';
$lines[] = '';

$lines[] = '## Per post (5 runs)';
$lines[] = '';
$lines[] = '| Merk | Post | Verwacht | Goed (live) | Goed (replay nu) | Goed (replay +voorwaarde) |';
$lines[] = '| --- | --- | --- | ---: | ---: | ---: |';
foreach ($tableRows as $tr) {
    $lines[] = sprintf(
        '| %s | %s | %s | %d/5 | %d/5 | %d/5 |',
        $tr['merk'],
        $tr['label'],
        $tr['verwacht'] ?? '—',
        $tr['goodActual'],
        $tr['goodNow'],
        $tr['goodLegacy'],
    );
}
$lines[] = '';

$lines[] = '## Geen code-wijziging';
$lines[] = '';
$lines[] = 'Alleen diagnose op geëxporteerde scores. Pilotbesluit v1.13 baseert op live run + bovenstaande vergelijking.';
$lines[] = '';

file_put_contents($outPath, implode("\n", $lines) . "\n");
echo "Wrote {$outPath}\n";
echo "Stable: live={$stableActual} replay_now={$stableNow} replay_legacy={$stableLegacy}\n";
