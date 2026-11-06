#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/campagne-preview-bank.php';

$slots = [];
$phases = ['aankondiging', 'waarom', 'hoe_werkt', 'bewijs', 'actie', 'aankondiging'];
$channels = ['linkedin', 'instagram', 'linkedin', 'instagram', 'linkedin', 'instagram'];
$dates = ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-09'];
foreach ($phases as $i => $fase) {
    $slots[] = [
        'slotId' => 's' . $i,
        'faseKey' => $fase,
        'platformKey' => $channels[$i],
        'publishDate' => $dates[$i],
        'slotIndex' => $i,
    ];
}
$vragen = [
    ['id' => 'v1', 'vraag' => 'Wat gebeurt er met koffie ouder dan zes weken?'],
    ['id' => 'v2', 'vraag' => 'Waarom staat de branddatum op de zak?'],
];
$feiten = [
    ['id' => 'f1', 'formulering' => 'Op elke zak staan de branddatum en de boer.'],
    ['id' => 'f2', 'formulering' => 'We branden in kleine partijen per herkomst.'],
];

$alloc = ce_campagne_preview_allocate_bank_ankers($slots, $vragen, $feiten, false);
$ok = count($alloc) === 2;

$all = ce_campagne_preview_allocate_bank_ankers($slots, $vragen, $feiten, true);
$ok = $ok && count($all) === 6;

$soorten = array_column($alloc, 'soort');
$ok = $ok && in_array('vraag', $soorten, true) && in_array('feit', $soorten, true);

$onderdeel = ce_campagne_normalize_merk_onderdeel('waar_je_tegen_bent');
$ok = $ok && $onderdeel === 'waar_je_tegen_bent';
$ok = $ok && ce_campagne_normalize_merk_onderdeel('onbekend') === null;

echo $ok ? "P6 bank selftest OK\n" : "P6 bank selftest FAIL\n";
exit($ok ? 0 : 1);
