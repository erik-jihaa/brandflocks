<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/mappers.php';
require_once dirname(__DIR__) . '/web/api/hba-v2-repository.php';
require_once dirname(__DIR__) . '/web/api/ce03-meta-repository.php';

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

$parsed = ce_parse_self_review([
    'weakest_claim' => '',
    'unsupported_assumption' => '',
    'missed_counterpoint' => '',
    'merk_beweringen' => [
        ['citaat' => 'Koffie ouder dan zes weken gaat naar de voedselbank.', 'bron' => 'bewijsveld'],
        ['citaat' => 'Wij branden op bestelling.', 'bron' => 'geen_bron'],
    ],
]);
hba_assert('parst merk_beweringen', is_array($parsed) && count($parsed['merkBeweringen'] ?? []) === 2);
hba_assert(
    'geen_bron blokkeert indienen',
    $parsed !== null && ce_hba_article_self_review_blocks_flow($parsed)
);

$okReview = ce_parse_self_review([
    'weakest_claim' => '',
    'unsupported_assumption' => '',
    'missed_counterpoint' => '',
    'merk_beweringen' => [
        ['citaat' => 'Ouder dan zes weken gaat naar de voedselbank.', 'bron' => 'merkarchitectuur'],
    ],
]);
hba_assert(
    'bron aanwezig blokkeert niet',
    $okReview !== null && ce_hba_article_self_review_blocks_flow($okReview) === false
);

hba_assert(
    'instructie je',
    str_contains(ce_ce03_aanspreekvorm_instruction('je'), 'je/jij')
    && str_contains(ce_ce03_aanspreekvorm_instruction('je'), 'geen u')
);
hba_assert(
    'instructie u',
    str_contains(ce_ce03_aanspreekvorm_instruction('u'), 'u/uw')
    && str_contains(ce_ce03_aanspreekvorm_instruction('u'), 'geen je')
);
hba_assert('lege vorm geen instructie', ce_ce03_aanspreekvorm_instruction('') === '');

if ($failed > 0) {
    fwrite(STDERR, "{$failed} test(s) gefaald\n");
    exit(1);
}

fwrite(STDOUT, "Alle tests OK\n");
