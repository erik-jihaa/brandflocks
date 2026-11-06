#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test narratief-analyse op GrowthElements-fixture (live Claude-call).
 * Gebruik: php scripts/test-narrative.php [pad/naar/fixture.json]
 *
 * Vereist config.local.php met anthropic_api_key + ai_summary_enabled=true,
 * of zet ANTHROPIC_API_KEY in de omgeving.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

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

$analysis = li_analyze_posts($raw, 'growthelements', 'Growth Elements');
$narrative = li_analyze_narrative($analysis['posts'], $analysis['company_name']);

if ($narrative === null) {
    fwrite(STDERR, "Narratief-analyse gaf null (uitgeschakeld, geen key, of mislukt).\n");
    exit(1);
}

echo "core_message: {$narrative['core_message']}\n";
echo 'themes: ' . implode(', ', $narrative['recurring_themes']) . "\n";
echo "consistency: {$narrative['consistency_label']}\n";
echo "reasoning: {$narrative['consistency_reasoning']}\n";
if ($narrative['inconsistency_examples'] !== []) {
    echo "examples:\n";
    foreach ($narrative['inconsistency_examples'] as $example) {
        echo "  - {$example}\n";
    }
}
echo 'cost_usd: ' . ($narrative['cost_usd'] ?? '—') . "\n";

$metrics = $analysis['metrics'];
$metrics['narrative'] = $narrative;
$caseLine = li_render_case_line($metrics);
$usesNarrativeCase = str_contains($caseLine, 'uiteenlopende onderwerpen')
    || str_contains($caseLine, 'staat dat verhaal er al')
    || str_contains($caseLine, 'niet elke post draagt hem even scherp uit');
echo ($usesNarrativeCase ? 'PASS' : 'INFO') . " case_uses_narrative=" . ($usesNarrativeCase ? 'yes' : 'no') . "\n";
echo "case_line: " . strip_tags($caseLine) . "\n";

exit(0);
