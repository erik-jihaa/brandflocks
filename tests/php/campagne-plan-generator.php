#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Campagne-plangenerator: Lancering 20 → 3/4/4/2/4/3; kanalen; Touch 40/35/25; max 100 slots.
 */
require_once __DIR__ . '/bootstrap.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "campagne-plan-generator FAIL: {$msg}\n");
    exit(1);
};

$lancering = ce_campagne_preview_resolve_phases('lancering', null);
$seq20 = ce_campagne_preview_allocate_phases($lancering, 20);
$counts = [];
foreach ($seq20 as $phase) {
    $k = (string) $phase['key'];
    $counts[$k] = ($counts[$k] ?? 0) + 1;
}
$expected = [
    'aankondiging' => 3,
    'waarom' => 4,
    'hoe_werkt' => 4,
    'bewust_niet' => 2,
    'bewijs' => 4,
    'actie' => 3,
];
if ($counts !== $expected) {
    $fail('lancering 20 fases: verwacht ' . json_encode($expected) . ', kreeg ' . json_encode($counts));
}

$channels = ['linkedin' => 8, 'instagram' => 12];
try {
    $normalized = ce_campagne_preview_normalize_channels($channels);
} catch (Throwable $e) {
    $fail('normalize channels: ' . $e->getMessage());
}
if (array_sum($normalized) !== 20) {
    $fail('kanaaltotalen moeten 20 zijn');
}

$stubs = ce_campagne_preview_build_slot_schedule(
    '2026-11-10',
    '2026-12-31',
    $normalized,
    ['mode' => 'spread'],
);
if (count($stubs) !== 20) {
    $fail('schedule moet 20 slots hebben');
}

$byPlatform = [];
foreach ($stubs as $stub) {
    $platform = (string) ($stub['platform'] ?? '');
    $date = (string) ($stub['publishDate'] ?? '');
    $byPlatform[$platform][$date] = ($byPlatform[$platform][$date] ?? 0) + 1;
}
foreach ($byPlatform as $platform => $dates) {
    if (($normalized[$platform] ?? 0) !== count($dates)) {
        $fail("platform {$platform}: aantal datums klopt niet");
    }
    foreach ($dates as $date => $n) {
        if ($n !== 1) {
            $fail("platform {$platform}: meer dan 1 post op {$date}");
        }
    }
}

$touch = ce_campagne_preview_resolve_phases('touch', null);
$touch10 = ce_campagne_preview_allocate_phases($touch, 10);
$touchCounts = [];
foreach ($touch10 as $phase) {
    $k = (string) $phase['key'];
    $touchCounts[$k] = ($touchCounts[$k] ?? 0) + 1;
}
$touchExpected = ['herkenning' => 4, 'anker' => 4, 'connect' => 2];
if ($touchCounts !== $touchExpected) {
    $fail('touch 10: verwacht ' . json_encode($touchExpected) . ', kreeg ' . json_encode($touchCounts));
}

try {
    ce_campagne_preview_normalize_channels(['linkedin' => 60, 'instagram' => 41]);
    $fail('100-slots-cap moet InvalidArgumentException geven');
} catch (InvalidArgumentException) {
    // ok
} catch (Throwable $e) {
    $fail('100-slots-cap: verkeerde exception: ' . $e->getMessage());
}

echo "campagne-plan-generator OK\n";
echo 'lancering 20: ' . json_encode($counts) . "\n";
echo 'touch 10: ' . json_encode($touchCounts) . "\n";
exit(0);
