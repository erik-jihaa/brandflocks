<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/hba-prompt.php';
require_once dirname(__DIR__) . '/web/api/hba-v2-repository.php';
require_once dirname(__DIR__) . '/web/api/hba-hint-verificatie.php';

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

$artikel = 'Wij verkopen geen koffie die langer dan zes weken geleden is gebrand. Die gaat naar de voedselbank.';

$quotes = ce_hba_extract_quoted_fragments(
    "De zin 'koffie die de zes weken na het branden nadert, verkopen wij niet' beschrijft een grens."
);
hba_assert('haalt verzonnen citaat', $quotes === [
    'koffie die de zes weken na het branden nadert, verkopen wij niet',
]);

$check = ce_hba_validate_article_hint(
    [
        'aanwijzing' => "De zin 'koffie die de zes weken na het branden nadert, verkopen wij niet' is fout.",
        'regel_overtreden' => '',
        'passage_voor' => '',
        'passage_na' => 'Koffie die langer dan zes weken geleden is gebrand, verkopen wij niet.',
    ],
    $artikel,
    'je'
);
hba_assert('verzonnen citaat faalt', $check['ok'] === false && ($check['violations'][0]['reason'] ?? '') === 'quote_not_in_source');

$check = ce_hba_validate_article_hint(
    [
        'aanwijzing' => "De zin 'Wij verkopen geen koffie die langer dan zes weken geleden is gebrand' staat er al.",
        'passage_na' => 'Koffie die langer dan zes weken geleden is gebrand, verkopen wij niet.',
    ],
    $artikel,
    'je'
);
hba_assert('echt citaat slaagt', $check['ok'] === true);

$check = ce_hba_validate_article_hint(
    ['passage_voor' => 'Deze zin staat nergens in het artikel over koffie.'],
    $artikel,
    ''
);
hba_assert('passage_voor moet in artikel', $check['ok'] === false);

$check = ce_hba_validate_article_hint(
    ['passage_na' => 'Wat u koopt is altijd vers.'],
    $artikel,
    'je'
);
hba_assert('u faalt bij je', ($check['violations'][0]['reason'] ?? '') === 'wrong_address');

$check = ce_hba_validate_article_hint(
    ['passage_na' => 'Wat je koopt is altijd vers.'],
    $artikel,
    'je'
);
hba_assert('je slaagt bij je', $check['ok'] === true);

$check = ce_hba_validate_article_hint(
    ['passage_na' => 'Wat je koopt is altijd vers.'],
    $artikel,
    ''
);
hba_assert('lege aanspreekvorm slaat over', $check['ok'] === true);

$moved = ce_hba_move_placeholders_out_of_running_text([
    'passage_na' => 'Die gaat naar de voedselbank. [hoeveel kilo er vorige maand is afgevoerd] Wat je koopt is vers.',
    'aanwijzing' => 'Voeg een getal toe. [niet in advies]',
    'invulvelden' => [],
]);
hba_assert(
    'placeholder blijft in passage_na',
    str_contains((string) $moved['passage_na'], '[hoeveel kilo er vorige maand is afgevoerd]') &&
    ($moved['invulvelden'][0] ?? '') === 'hoeveel kilo er vorige maand is afgevoerd'
);
hba_assert(
    'placeholder uit aanwijzing',
    !str_contains((string) $moved['aanwijzing'], '[') &&
    in_array('niet in advies', $moved['invulvelden'], true)
);

$after = ce_hba_validate_article_hint($moved, $artikel, 'je');
hba_assert('passage met invulveld is ok', $after['ok'] === true);

$check = ce_hba_validate_article_hint(
    [
        'aanwijzing' => 'Maak scherper.',
        'passage_voor' => 'Die gaat naar de voedselbank.',
        'passage_na' => 'Die gaat naar de voedselbank.',
    ],
    $artikel,
    'je'
);
hba_assert('noop herschrijving faalt', $check['ok'] === false && ($check['violations'][0]['reason'] ?? '') === 'noop_rewrite');

$check = ce_hba_validate_article_hint(
    [
        'aanwijzing' => 'Voeg een cijfer toe.',
        'passage_voor' => 'Die gaat naar de voedselbank.',
        'passage_na' => 'Die gaat naar de voedselbank. [hoeveel kilo er vorige maand is afgevoerd]',
    ],
    $artikel,
    'je'
);
hba_assert('zelfde zin + invulveld is ok', $check['ok'] === true);

hba_assert('vorm je', ce_hba_normalize_aanspreekvorm('Jij') === 'je');
hba_assert('vorm u', ce_hba_normalize_aanspreekvorm('U') === 'u');

if ($failed > 0) {
    fwrite(STDERR, "\n$failed check(s) gefaald\n");
    exit(1);
}

fwrite(STDOUT, "\nAlle checks geslaagd\n");
