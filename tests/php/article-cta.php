#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CTA-keuze (klikmodel p-artikeldetail) — Ja/Nee + tekst, niet afleiden uit body.
 */
require_once dirname(__DIR__, 2) . '/web/api/mappers.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "article-cta FAIL: {$msg}\n");
    exit(1);
};

$unset = ce_article_cta_from_row([]);
if ($unset['hasCta'] !== null || $unset['ctaText'] !== '') {
    $fail('lege rij moet hasCta=null en lege tekst geven');
}

$ja = ce_article_cta_from_row(['has_cta' => 1, 'cta_text' => '  Plan een intake in bij ons team.  ']);
if ($ja['hasCta'] !== true || $ja['ctaText'] !== 'Plan een intake in bij ons team.') {
    $fail('has_cta=1 moet true + getrimde tekst geven');
}

$nee = ce_article_cta_from_row(['has_cta' => 0, 'cta_text' => 'oud']);
if ($nee['hasCta'] !== false || $nee['ctaText'] !== 'oud') {
    $fail('has_cta=0 moet false blijven (wissen gebeurt in PATCH)');
}

$patchNee = ce_article_cta_patch_from_body(['hasCta' => false, 'ctaText' => 'blijft niet']);
if (($patchNee['has_cta'] ?? null) !== 0 || ($patchNee['cta_text'] ?? null) !== '') {
    $fail('Nee moet has_cta=0 en cta_text wissen');
}

$patchJa = ce_article_cta_patch_from_body([
    'hasCta' => true,
    'ctaText' => '  Lees hier hoe wij dit aanpakken.  ',
]);
if (($patchJa['has_cta'] ?? null) !== 1 || ($patchJa['cta_text'] ?? null) !== 'Lees hier hoe wij dit aanpakken.') {
    $fail('Ja moet has_cta=1 + getrimde tekst geven');
}

$patchJsonTrue = ce_article_cta_patch_from_body(['hasCta' => 'true']);
if (($patchJsonTrue['has_cta'] ?? null) !== 1) {
    $fail('string "true" moet als Ja tellen');
}

if (ce_article_patch_changes_schrijfvorm(
    ['tone_final' => 'b2b'],
    ['hasCta' => true, 'ctaText' => 'Plan een intake in bij ons team.'],
)) {
    $fail('CTA-PATCH mag geen schrijfvorm-herschrijf triggeren');
}

$formatted = ce_format_article_row([
    'id' => 'cta-test',
    'slot_id' => 'slot-cta',
    'headline' => 'Test',
    'content_ai' => 'Body zonder call-to-action.',
    'has_cta' => 1,
    'cta_text' => 'Neem contact op voor een vrijblijvend kennismakingsgesprek.',
]);
if (($formatted['hasCta'] ?? null) !== true) {
    $fail('DTO hasCta ontbreekt of is niet true');
}
if (($formatted['ctaText'] ?? null) !== 'Neem contact op voor een vrijblijvend kennismakingsgesprek.') {
    $fail('DTO ctaText komt niet overeen');
}

echo "article-cta OK\n";
