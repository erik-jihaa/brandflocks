#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Valideer analyzer tegen GrowthElements-verwachtingen (§8 briefing).
 * Gebruik: php scripts/test-analyzer.php [pad/naar/fixture.json]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../api/analyzer.php';

$fixturePath = $argv[1] ?? __DIR__ . '/../fixtures/growthelements-sample.json';
if (!is_readable($fixturePath)) {
    fwrite(STDERR, "Fixture niet gevonden: {$fixturePath}\n");
    exit(1);
}

$raw = json_decode((string) file_get_contents($fixturePath), true);
if (!is_array($raw)) {
    fwrite(STDERR, "Ongeldige JSON in fixture.\n");
    exit(1);
}

$result = li_analyze_posts($raw, 'growthelements', 'Growth Elements');
$m = $result['metrics'];

echo "Raw rows: {$result['raw_count']}\n";
echo "Unique posts: {$m['posts_count']}\n";
echo "Period: {$m['period_start']} – {$m['period_end']}\n";
echo "Posts/week: {$m['posts_per_week']}\n";
echo "Silences: " . implode(', ', $m['silences_days'] ?? []) . "\n";
echo "Avg reactions: {$m['avg_reactions']} (median {$m['median_reactions']})\n";
echo "No comments: {$m['posts_without_comments']} / {$m['posts_count']}\n";
echo "Followers: {$m['followers']}\n";

$checks = [
    'posts' => ($m['posts_count'] ?? 0) === 17,
    'raw' => $result['raw_count'] === 21,
    'period_start' => ($m['period_start'] ?? '') === '2026-01-08',
    'period_end' => ($m['period_end'] ?? '') === '2026-07-30',
    'posts_per_week' => abs((float) ($m['posts_per_week'] ?? 0) - 0.6) < 0.15,
    'silences' => ($m['silences_days'] ?? []) === [35, 32, 26],
    'no_comments' => ($m['posts_without_comments'] ?? 0) === 14,
    'followers' => ($m['followers'] ?? 0) === 130,
];

$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " {$name}\n";
    if (!$ok) {
        $failed++;
    }
}

exit($failed > 0 ? 1 : 0);
