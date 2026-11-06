#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/hba-prompt.php';
require_once $root . '/web/api/hba-repository.php';
require_once __DIR__ . '/hba-v1.12-replay-lib.php';

$jsonPath = $root . '/docs/hba-testmerken-stability-v1.12-5runs.json';
$postsPath = $root . '/briefing/hba-input/HBA_testmerken/alle_drie_testmerken.json';
$postsByMerk = hba_diagnose_load_testposts($postsPath);
$payload = json_decode((string) file_get_contents($jsonPath), true);

$note = 'Reconstructie: geen raw_llm in export; merkbewering/kern zoals in diagnose-replay.';

foreach ($payload['rows'] ?? [] as &$row) {
    $merk = (string) ($row['merk'] ?? '');
    $label = (string) ($row['label'] ?? '');
    $idx = (int) ($row['index'] ?? 0);
    $postText = $postsByMerk[$merk][$idx]['post_tekst'] ?? '';

    foreach ($row['runs'] ?? [] as &$run) {
        $tool = hba_diagnose_build_tool($run, $merk, $label);
        $foeCitaat = trim((string) ($tool['flag_citaten']['foe_vervaging'] ?? ''));
        $zone = hba_diagnose_foe_zone($foeCitaat, $postText);
        $foeOnKern = $zone === 'kop' || $zone === 'openingszin';
        $ctx = ['foe_on_kern' => $foeOnKern];

        $run['merkbewering'] = (string) ($tool['merkbewering'] ?? 'ja');
        $run['kern_categorietaal'] = $tool['kern_categorietaal'] === null
            ? null
            : (string) $tool['kern_categorietaal'];
        $run['hoofdoordeel_volgt_uit_flags'] = ce_hba_hoofdoordeel_volgt_uit_flags(
            array_merge($tool, ['hoofdoordeel' => (string) ($run['hoofdoordeel'] ?? '')]),
            $ctx,
        );
        $run['foe_zone'] = $zone;
        $run['foe_on_kern_replay'] = $foeOnKern;
        $run['_logging_backfill'] = $note;
    }
    unset($run);
}
unset($row);

$payload['loggingBackfill'] = [
    'at' => gmdate('c'),
    'method' => $note,
    'matrixPayloadFix' => 'ce_hba_testmerken_matrix_payload (deploy voor nieuwe GETs)',
];

file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

$neutralMd = $root . '/docs/hba-v1.12-neutrale-posts-controle.md';
$lines = ["# v1.12 — neutrale posts (merkbewering → trede 5)\n", $note . "\n"];
$lines[] = '| Merk | Post | merkbewering (replay) | Live hoofdoordeel (5 runs) | Replay trede 5 |';
$lines[] = '| --- | --- | --- | --- | --- |';

foreach ($payload['rows'] ?? [] as $row) {
    if (stripos((string) ($row['label'] ?? ''), 'Neutraal') === false) {
        continue;
    }
    $merk = (string) $row['merk'];
    $label = (string) $row['label'];
    $idx = (int) ($row['index'] ?? 0);
    $postText = $postsByMerk[$merk][$idx]['post_tekst'] ?? '';
    $live = [];
    $replay = [];
    foreach ($row['runs'] ?? [] as $run) {
        $tool = hba_diagnose_build_tool($run, $merk, $label);
        $foeCitaat = trim((string) ($tool['flag_citaten']['foe_vervaging'] ?? ''));
        $zone = hba_diagnose_foe_zone($foeCitaat, $postText);
        $ctx = ['foe_on_kern' => $zone === 'kop' || $zone === 'openingszin'];
        $live[] = (string) ($run['hoofdoordeel'] ?? '');
        $replay[] = ce_hba_resolve_hoofdoordeel($tool, $ctx);
    }
    $lines[] = sprintf(
        '| %s | %s | nee | %s | %s |',
        $merk,
        $label,
        implode(', ', $live),
        implode(', ', $replay),
    );
}
file_put_contents($neutralMd, implode("\n", $lines) . "\n");

echo "Updated {$jsonPath}\nWrote {$neutralMd}\n";
