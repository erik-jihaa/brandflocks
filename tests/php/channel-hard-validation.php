<?php

declare(strict_types=1);

require_once __DIR__ . '/../../web/api/channel-copy-constraints.php';

function fail(string $msg): void
{
    fwrite(STDERR, "channel-hard-validation FAIL: {$msg}\n");
    exit(1);
}

$catalog = ce_channel_copy_constraint_catalog();
if (!str_starts_with($catalog['blog|artikel'] ?? '', 'Beantwoord één duidelijke lezersvraag')) {
    fail('blog catalog moet letterlijk addendum §3 zijn');
}

$xIssues = ce_channel_hard_validation_messages('x', 'post', 'Titel', str_repeat('a', 290), '');
if ($xIssues === []) {
    fail('x post > 280 combined moet falen');
}
$tooLong = ce_channel_hard_validation_messages('x', 'post', 'Hi', str_repeat('x', 300), '');
if ($tooLong === []) {
    fail('x combined chars');
}

$tags = ce_channel_hard_validation_messages('linkedin', 'post', 'T', 'Body', '#a #b #c #d');
if ($tags === [] || !str_contains($tags[0], 'hashtags')) {
    fail('linkedin max 3 hashtags');
}

$newsletter = ce_channel_hard_validation_messages('newsletter', 'editie', 'Onderwerp', 'Body', '#een');
if ($newsletter === [] || !str_contains($newsletter[0], 'geen hashtags')) {
    fail('newsletter geen hashtags');
}

if (ce_validate_textvorm_for_platform('linkedin', 'promotie') !== 'promotie') {
    fail('linkedin promotie tekstvorm');
}
if (ce_validate_textvorm_for_platform('linkedin', 'carrousel') !== 'post') {
    fail('ongeldige tekstvorm valt terug op default');
}

$soft = ce_channel_soft_hints_for_proposal('linkedin', 'post');
if ($soft === [] || !str_contains($soft[0], 'kerninzicht')) {
    fail('soft reminders linkedin post');
}
$longBody = implode(' ', array_fill(0, 200, 'woord'));
$wordSoft = ce_channel_soft_word_count_messages('linkedin', 'promotie', $longBody);
if ($wordSoft === [] || !str_contains($wordSoft[0], 'lang')) {
    fail('soft word count promotie');
}

echo "channel-hard-validation OK\n";
