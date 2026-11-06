<?php

declare(strict_types=1);

require_once __DIR__ . '/../web/api/bootstrap.php';
require_once __DIR__ . '/../web/api/mappers.php';

function assert_contains(string $needle, string $haystack, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL {$label}: expected «{$needle}» in «{$haystack}»\n");
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $label): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL {$label}: unwanted «{$needle}» in «{$haystack}»\n");
        exit(1);
    }
}

$today = ce_today_agenda_label();
assert_not_contains('October', $today, 'todayLabel');
assert_contains('oktober', strtolower($today), 'todayLabel month');

$w41 = ce_week_range_label(41, 2025);
assert_contains('oktober', strtolower($w41), 'week 41 range');
assert_not_contains('October', $w41, 'week 41 range EN');

$w44 = ce_week_range_label(44, 2025);
assert_contains('oktober', strtolower($w44), 'week 44 start');
assert_contains('november', strtolower($w44), 'week 44 end');

echo "OK ce-agenda-nl-dates\n";
