<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/hba-v2-prompts.php';

$failed = 0;

function hba_assert(string $label, bool $ok): void
{
    global $failed;
    if ($ok) {
        fwrite(STDOUT, "OK  $label\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL $label\n");
}

$prompt = ce_hba_v2_theme_system_prompt();
hba_assert('themaprompt noemt regel_botsing', str_contains($prompt, 'regel_botsing'));
hba_assert('themaprompt noemt invalshoek', str_contains($prompt, 'invalshoek'));
hba_assert('themaprompt accepteert botst', str_contains($prompt, 'botst'));

hba_assert('botst wordt verwatert', ce_hba_v2_normalize_theme_verdict_label('botst') === 'verwatert');
hba_assert('versterkt blijft', ce_hba_v2_normalize_theme_verdict_label('versterkt') === 'versterkt');
hba_assert('ja is true', ce_hba_v2_theme_verdict_bool('ja'));
hba_assert('false is false', ce_hba_v2_theme_verdict_bool(false) === false);

$composed = ce_hba_v2_compose_theme_fit_toelichting(
    'Een Black Friday-actie draait om korting.',
    true,
    'We verkopen geen koffie die langer dan zes weken geleden is gebrand, ook niet met korting en ook niet op Black Friday.',
    'Schrijf over waarom het merk niet meedoet',
);
hba_assert('toelichting noemt botst', str_contains($composed, 'Dit thema botst met je regel:'));
hba_assert('toelichting heeft letterlijke regel', str_contains($composed, 'langer dan zes weken geleden is gebrand'));
hba_assert('toelichting heeft invalshoek', str_contains($composed, 'waarom het merk niet meedoet'));

$plain = ce_hba_v2_compose_theme_fit_toelichting('Past bij hoe jullie werken.', false, '', '');
hba_assert('geen botsing laat zin staan', $plain === 'Past bij hoe jullie werken.');

$fallback = ce_hba_v2_compose_theme_fit_toelichting('', true, 'Geen korting op oudere voorraad.', '');
hba_assert(
    'lege invalshoek krijgt default',
    str_contains($fallback, 'Schrijf over waarom het merk dit niet doet.')
);

if ($failed > 0) {
    fwrite(STDERR, "{$failed} test(s) gefaald\n");
    exit(1);
}

fwrite(STDOUT, "Alle tests OK\n");
