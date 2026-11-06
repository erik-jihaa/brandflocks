#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/web/api/campagne-preview-product-extract.php';

$normalized = ce_campagne_product_extract_normalize([
    'wat' => 'Blend voor thuisbaristas.',
    'anders' => [['citaat' => 'Langzaam gebrand', 'branchetaal' => false]],
    'bewijs' => [['citaat' => 'SCA 86']],
    'ontbreekt' => ['Waarom deze blend bestaat'],
]);

$arch = ce_campagne_product_extract_architecture_block([
    'categorietaal' => 'premium koffie, artisan',
    'hygienefactoren' => 'vers gebrand; herkomst',
    'branche_label' => 'koffie',
    'doel' => 'eerlijke koffie',
]);

$ok = $normalized['wat'] !== ''
    && count($normalized['anders']) === 1
    && $normalized['anders'][0]['branchetaal'] === false
    && ce_campagne_product_extract_snippet_for_fase('aankondiging', $normalized) !== ''
    && ce_campagne_product_extract_snippet_for_fase('bewijs', $normalized) !== ''
    && str_contains($arch, 'Categorietaal')
    && str_contains($arch, 'Hygiënefactoren')
    && str_contains(ce_campagne_product_extract_system_prompt(), 'categorietaal');

echo $ok ? "P3 selftest OK\n" : "P3 selftest FAIL\n";
exit($ok ? 0 : 1);
