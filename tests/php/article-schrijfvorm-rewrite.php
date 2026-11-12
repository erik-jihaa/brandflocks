#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PATCH met schrijfvorm (Toon) moet CE-03 starten; headline/content-save niet.
 */
require_once dirname(__DIR__, 2) . '/web/api/mappers.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "article-schrijfvorm-rewrite FAIL: {$msg}\n");
    exit(1);
};

$row = [
    'tone_final' => 'b2b',
    'tone_suggested' => 'b2b',
];

if (ce_article_patch_changes_schrijfvorm($row, ['headline' => 'x', 'content' => 'y'])) {
    $fail('headline/content-only PATCH mag geen schrijfvorm-wijziging zijn');
}

if (ce_article_patch_changes_schrijfvorm($row, ['tone' => 'b to b'])) {
    $fail('zelfde toon (b to b vs b2b) mag geen herschrijf triggeren');
}

if (ce_article_patch_changes_schrijfvorm($row, ['tone' => 'b2b'])) {
    $fail('zelfde toon (b2b) mag geen herschrijf triggeren');
}

if (ce_article_patch_changes_schrijfvorm($row, ['tone' => ''])) {
    $fail('lege toon mag geen herschrijf triggeren');
}

if (!ce_article_patch_changes_schrijfvorm($row, ['tone' => 'personal'])) {
    $fail('personal vs b2b moet een schrijfvorm-wijziging zijn');
}

if (!ce_article_patch_changes_schrijfvorm($row, ['tone' => 'b to c', 'headline' => 'blijft'])) {
    $fail('toon + headline moet als schrijfvorm-wijziging tellen');
}

echo "article-schrijfvorm-rewrite OK\n";
