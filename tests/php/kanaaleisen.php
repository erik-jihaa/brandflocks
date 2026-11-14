<?php

declare(strict_types=1);

require_once __DIR__ . '/../../web/api/channel-copy-constraints.php';

function fail(string $msg): void
{
    fwrite(STDERR, "kanaaleisen FAIL: {$msg}\n");
    exit(1);
}

$catalog = ce_channel_copy_constraint_catalog();
$labels = ce_channel_copy_constraint_labels();
if (count($catalog) !== 13) {
    fail('catalog verwacht 13 rijen addendum §3, got ' . count($catalog));
}

foreach ($catalog as $key => $expectedBody) {
    [$platform, $form] = array_pad(explode('|', $key, 2), 2, '');
    $line = ce_channel_copy_constraint_line($platform, $form);
    if ($line === '' || !str_contains($line, $expectedBody)) {
        fail("directive mist letterlijke tekst voor {$key}");
    }
    $label = $labels[$key] ?? '';
    if ($label === '') {
        fail("label ontbreekt voor {$key}");
    }
}

$x280 = ce_channel_hard_validation_messages('x', 'post', '', str_repeat('a', 280), '');
if ($x280 !== []) {
    fail('x post 280 tekens combined moet ok zijn');
}
$x281 = ce_channel_hard_validation_messages('x', 'post', '', str_repeat('a', 281), '');
if ($x281 === []) {
    fail('x post 281 tekens combined moet melding geven');
}

$ig5 = ce_channel_hard_validation_messages(
    'instagram',
    'bijschrift',
    'T',
    'Body',
    '#a #b #c #d #e',
);
if ($ig5 !== []) {
    fail('instagram 5 hashtags moet ok zijn');
}
$ig6 = ce_channel_hard_validation_messages(
    'instagram',
    'bijschrift',
    'T',
    'Body',
    '#a #b #c #d #e #f',
);
if ($ig6 === [] || !str_contains($ig6[0], 'hashtags')) {
    fail('instagram 6 hashtags moet melding geven');
}

$payload = ce_channel_constraints_public_payload();
if (($payload['hardLimits']['x|post']['max_combined_chars'] ?? 0) !== 280) {
    fail('API hardLimits x|post max_combined_chars');
}
if (($payload['textvormByPlatform']['linkedin'][0]['label'] ?? '') !== 'zelfstandige post') {
    fail('linkedin label moet zelfstandige post zijn');
}

echo "kanaaleisen OK\n";