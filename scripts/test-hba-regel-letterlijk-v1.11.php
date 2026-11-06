<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/hba-prompt.php';
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

function contains_letterlijk_eis(string $text): bool
{
    return str_contains($text, 'ouder dan zes weken')
        && str_contains($text, 'nadert')
        && str_contains($text, 'REGEL LETTERLIJK');
}

$eis = ce_hba_regel_letterlijk_eis();
hba_assert('gedeelde eis laadt', $eis !== '' && contains_letterlijk_eis($eis));
hba_assert('v2-helper dezelfde eis', ce_hba_v2_regel_letterlijk_eis() === $eis);

hba_assert('hintprompt', contains_letterlijk_eis(ce_hba_v2_article_system_prompt()));
hba_assert('themaprompt', contains_letterlijk_eis(ce_hba_v2_theme_system_prompt()));
hba_assert('researchprompt', contains_letterlijk_eis(ce_hba_v2_research_system_prompt()));
hba_assert('architectuurcheck', contains_letterlijk_eis(ce_hba_v2_architecture_check_system_prompt()));

$advies = ce_hba_verbeteradvies_definitie();
hba_assert('verbeteradvies + diagnose-eis', contains_letterlijk_eis($advies) && str_contains($advies, 'diagnose'));

$score = ce_hba_build_system_prompt([], 'linkedin', 'post', 'test');
hba_assert(
    'scoreprompt diagnose + verbeteradvies',
    str_contains($score, 'zijpaneel') && contains_letterlijk_eis($score)
);

hba_assert(
    'filter vangt nadert',
    ce_hba_v2_hint_uses_derived_boundary(['aanwijzing' => 'koffie die de grens nadert'])
);
hba_assert(
    'filter laat letterlijke regel door',
    !ce_hba_v2_hint_uses_derived_boundary(['aanwijzing' => 'koffie ouder dan zes weken gaat naar de voedselbank'])
);

if ($failed > 0) {
    fwrite(STDERR, "\n$failed check(s) gefaald\n");
    exit(1);
}

fwrite(STDOUT, "\nAlle checks geslaagd\n");
