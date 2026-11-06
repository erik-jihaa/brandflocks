<?php

declare(strict_types=1);

/**
 * Klantzichtbare self_review-teksten (stap 3b / §5.3) — geen research-schema of methodiek.
 *
 * @return list<string>
 */
function ce_self_review_client_forbidden_terms(): array
{
    return [
        'stance',
        'reliability',
        'spreekt_tegen',
        'spreekt_voor',
        'stance=',
        'reliability=',
        'research-item',
        'research item',
        'research_item',
        'geen_bron',
        'bewijsveld',
        'merkarchitectuur',
        'regelbreuk',
        'tool_use',
        'enum',
        'low',
        'medium',
        'high',
        'premisse',
        'themePremisse',
    ];
}

function ce_self_review_forbidden_correction_suffix(): string
{
    return "\n\nCORRECTIE: je vorige antwoord bevatte interne veldnamen of waarden uit het researchschema "
        . '(bijv. stance, reliability, spreekt_tegen, low). '
        . 'Schrijf alle antwoorden in gewone taal voor de klant. '
        . 'Verwijs naar research als: "een bron die het tegenspreekt, met beperkte betrouwbaarheid" '
        . 'in plaats van stance=reliability. Gebruik nooit geen_bron, bewijsveld of merkarchitectuur als woord in de zin — '
        . 'zeg "zonder bron", "uit je bewijsveld" of "uit je merkprofiel".';
}

/** @return list<string> */
function ce_self_review_forbidden_hits_in_text(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $lower = mb_strtolower($text, 'UTF-8');
    $hits = [];
    foreach (ce_self_review_client_forbidden_terms() as $term) {
        $needle = mb_strtolower(trim($term), 'UTF-8');
        if ($needle === '') {
            continue;
        }
        if (str_contains($needle, '=') || str_contains($needle, '_')) {
            if (str_contains($lower, $needle)) {
                $hits[] = $term;
            }
            continue;
        }
        if (preg_match('/\\b' . preg_quote($needle, '/') . '\\b/u', $lower)) {
            $hits[] = $term;
        }
    }

    return array_values(array_unique($hits));
}

/**
 * @param array<string, mixed> $input
 * @return list<string>
 */
function ce_self_review_forbidden_hits_in_input(array $input): array
{
    $parts = [];
    foreach (['weakest_claim', 'unsupported_assumption', 'missed_counterpoint'] as $key) {
        $v = trim((string) ($input[$key] ?? ''));
        if ($v !== '') {
            $parts[] = $v;
        }
    }
    $merk = is_array($input['merk_beweringen'] ?? null) ? $input['merk_beweringen'] : [];
    foreach ($merk as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach (['citaat', 'steunt_op'] as $field) {
            $v = trim((string) ($row[$field] ?? ''));
            if ($v !== '') {
                $parts[] = $v;
            }
        }
    }

    return ce_self_review_forbidden_hits_in_text(implode("\n", $parts));
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ce_self_review_strip_forbidden_fields(array $input): array
{
    foreach (['weakest_claim', 'unsupported_assumption', 'missed_counterpoint'] as $key) {
        $v = trim((string) ($input[$key] ?? ''));
        if ($v === '') {
            continue;
        }
        if (ce_self_review_forbidden_hits_in_text($v) !== []) {
            $input[$key] = null;
        }
    }

    return $input;
}
