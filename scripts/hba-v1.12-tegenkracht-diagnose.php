#!/usr/bin/env php
<?php
/**
 * Offline replay hoofdoordeel uit v1.12 stability JSON (geen LLM, geen staging).
 * Levert docs/hba-v1.12-tegenkracht-diagnose.md
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/hba-prompt.php';
require_once $root . '/web/api/hba-repository.php';
require_once __DIR__ . '/hba-v1.12-replay-lib.php';

$jsonPath = $root . '/docs/hba-testmerken-stability-v1.12-5runs.json';
$postsPath = $root . '/briefing/hba-input/HBA_testmerken/alle_drie_testmerken.json';
$outPath = $root . '/docs/hba-v1.12-tegenkracht-diagnose.md';

$payload = json_decode((string) file_get_contents($jsonPath), true);
$postsByMerk = hba_diagnose_load_testposts($postsPath);
$runsPerPost = (int) ($payload['runs'] ?? 5);

$lines = [];
$lines[] = '# HBA v1.12 — tegenkracht-diagnose (offline replay)';
$lines[] = '';
$lines[] = 'Gegenereerd: ' . gmdate('Y-m-d H:i:s') . ' UTC';
$lines[] = 'Bron: `docs/hba-testmerken-stability-v1.12-5runs.json` (95 scores, geen modelcalls).';
$lines[] = '';
$lines[] = 'Twee varianten van `ce_hba_resolve_hoofdoordeel()`:';
$lines[] = '- **Nu:** trede 2 = `foe_vervaging` + `foe_on_kern` (v1.12, zonder `&& !kernCat`).';
$lines[] = '- **Met voorwaarde terug:** trede 2 = `foe_vervaging` + `foe_on_kern` + **`!kern_categorietaal`**.';
$lines[] = '';
$lines[] = '`foe_on_kern` en zone (kop / openingszin / verderop) zijn herberekend uit posttekst + foe-citaat.';
$lines[] = '`merkbewering`/`kern_categorietaal` in de export ontbraken; replay gebruikt label Neutraal → `nee`, anders `ja`, en `kern_categorietaal=ja` wanneer `category_fallback` in flags (zelfde fallback als in de filter).';
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
$lines[] = '| Opgeslagen in run (v1.12 live) | **' . $stableActual . '** / 19 |';
$lines[] = '| Replay **nu** (zelfde regels) | **' . $stableNow . '** / 19 |';
$lines[] = '| Replay **met `&& !kernCat` terug** | **' . $stableLegacy . '** / 19 |';
$lines[] = '';
$lines[] = 'Verschil live → replay nu: wijkt af als herberekende `foe_on_kern`/tool-reconstructie niet exact de score-run weerspiegelt.';
$lines[] = '';
$lines[] = '**Leeswijzer:** vijf van de zes niet-stabiele posts falen vooral doordat `tegenkracht` wint waar `categorietaal` verwacht wordt (`foe_vervaging`). Met **`&& !kernCat` terug** zou replay **' . $stableLegacy . '/19** stable geven i.p.v. **' . $stableActual . '/19** — vooral Brandpunt categorietaal en Duinroos categorietaal. Stelwerk branchetaal blijft grotendeels fout: check zone-kolom (`foe_on_kern` / verderop). Geen terugzetten zonder besluit Rogier.';
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

$lines[] = '## Tegenkracht-cases (foe_vervaging in flags)';
$lines[] = '';

foreach ($tableRows as $tr) {
    $hasFoe = false;
    foreach ($tr['runs'] as $rd) {
        if (in_array('foe_vervaging', $rd['flags'], true)) {
            $hasFoe = true;
            break;
        }
    }
    if (!$hasFoe) {
        continue;
    }
    $lines[] = '### ' . $tr['merk'] . ' — ' . $tr['label'];
    $lines[] = '';
    $lines[] = 'Kop (referentie): «' . str_replace('|', '\\|', mb_substr($tr['kop'], 0, 120)) . '»';
    $lines[] = 'Openingszin: «' . str_replace('|', '\\|', mb_substr($tr['opening'], 0, 120)) . '»';
    $lines[] = '';
    $lines[] = '| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |';
    $lines[] = '| ---: | --- | --- | --- | --- | --- | --- |';
    foreach ($tr['runs'] as $rd) {
        if (!in_array('foe_vervaging', $rd['flags'], true)) {
            continue;
        }
        $lines[] = sprintf(
            '| %d | %s | %s | %s | %s | %s | %s |',
            $rd['run'],
            $rd['zone'],
            $rd['foe_on_kern'] ? 'ja' : 'nee',
            $rd['actual'],
            $rd['now'],
            $rd['legacy'],
            str_replace('|', '\\|', mb_substr($rd['foe'], 0, 80)),
        );
    }
    $lines[] = '';
}

$lines[] = '## Geen code- of volgorde-wijziging';
$lines[] = '';
$lines[] = 'Deze diagnose beschrijft alleen wat de verwijdering van `&& !$kernCat` zou hebben betekend op dezelfde 95 scores. Besluit over terugzetten ligt bij Rogier.';
$lines[] = '';

file_put_contents($outPath, implode("\n", $lines) . "\n");
echo "Wrote {$outPath}\n";
echo "Stable: live={$stableActual} replay_now={$stableNow} replay_legacy={$stableLegacy}\n";
