#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * v1.13-replay: 95 opgeslagen scores → replay nu = 19/19 stable (5/5 per scorebare post).
 * Fixtures in tests/fixtures/ (docs/ wordt later opgeruimd).
 */
require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$jsonPath = $root . '/tests/fixtures/hba-testmerken-stability-v1.13-5runs.json';
$postsPath = $root . '/tests/fixtures/hba-testmerken/alle_drie_testmerken.json';

if (!is_readable($jsonPath) || !is_readable($postsPath)) {
    fwrite(STDERR, "hba-v113-replay: fixtures ontbreken\n");
    exit(2);
}

$payload = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($payload)) {
    fwrite(STDERR, "hba-v113-replay: ongeldige JSON\n");
    exit(2);
}

$postsByMerk = hba_diagnose_load_testposts($postsPath);
$runsPerPost = (int) ($payload['runs'] ?? 5);

$stableNow = 0;
$stableActual = 0;
$scoreable = 0;

foreach ($payload['rows'] ?? [] as $row) {
    $merk = (string) ($row['merk'] ?? '');
    $label = (string) ($row['label'] ?? '');
    $idx = (int) ($row['index'] ?? 0);
    $verwacht = isset($row['verwacht_hoofdoordeel']) ? (string) $row['verwacht_hoofdoordeel'] : null;
    if ($verwacht === null || $verwacht === '') {
        continue;
    }
    ++$scoreable;

    $postText = $postsByMerk[$merk][$idx]['post_tekst'] ?? '';

    $goodNow = 0;
    $goodActual = 0;

    foreach ($row['runs'] ?? [] as $run) {
        $tool = hba_diagnose_build_tool($run, $merk, $label);
        $foeCitaat = trim((string) ($tool['flag_citaten']['foe_vervaging'] ?? ''));
        $zone = hba_diagnose_foe_zone($foeCitaat, $postText);
        $foeOnKern = $zone === 'kop' || $zone === 'openingszin';
        $ctx = ['foe_on_kern' => $foeOnKern];
        $now = ce_hba_resolve_hoofdoordeel($tool, $ctx);
        $actual = (string) ($run['hoofdoordeel'] ?? '');

        if (hba_diagnose_judge($now, $verwacht) === 'goed') {
            ++$goodNow;
        }
        if (hba_diagnose_judge($actual, $verwacht) === 'goed') {
            ++$goodActual;
        }
    }

    if ($goodNow === $runsPerPost) {
        ++$stableNow;
    }
    if ($goodActual === $runsPerPost) {
        ++$stableActual;
    }
}

$expected = 19;
$ok = $stableNow === $expected && $stableActual === $expected;

echo sprintf(
    "hba-v113-replay: stable replay_now=%d/%d live=%d/%d (scorebare posts=%d)\n",
    $stableNow,
    $expected,
    $stableActual,
    $expected,
    $scoreable,
);

exit($ok ? 0 : 1);
