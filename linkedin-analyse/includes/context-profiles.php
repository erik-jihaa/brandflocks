<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

/**
 * Statische kopie van HBA_CONTEXT_PROFILES — bron: frontend/src/constants/hba.ts
 *
 * @return list<array{value: string, label: string}>
 */
function li_context_profile_rows(): array
{
    return [
        ['value' => 'nieuw_merk', 'label' => 'Nieuw merk'],
        ['value' => 'herpositionering', 'label' => 'Herpositionering'],
        ['value' => 'groeiend', 'label' => 'Groeiend'],
        ['value' => 'volwassen', 'label' => 'Volwassen'],
        ['value' => 'tijdelijke_salesdruk', 'label' => 'Tijdelijke salesdruk'],
        ['value' => 'b2b_complex_sale', 'label' => 'B2B complex sale'],
    ];
}

/** @return array<string, array{value: string, label: string}> */
function li_context_profile_index(): array
{
    static $index = null;
    if (is_array($index)) {
        return $index;
    }

    $index = [];
    foreach (li_context_profile_rows() as $row) {
        $index[$row['value']] = $row;
    }

    return $index;
}

/** @return list<array{value: string, label: string}> */
function li_context_profile_pulldown_options(): array
{
    return array_merge(
        [['value' => '', 'label' => 'Contextprofiel (optioneel)']],
        li_context_profile_rows(),
    );
}

function li_contextprofiel_is_valid(string $code): bool
{
    return isset(li_context_profile_index()[$code]);
}

function li_contextprofiel_code_for_scan(?string $raw): ?string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    return li_contextprofiel_is_valid($raw) ? $raw : null;
}

function li_contextprofiel_label(?string $code): ?string
{
    $code = li_contextprofiel_code_for_scan($code);
    if ($code === null) {
        return null;
    }

    return li_context_profile_index()[$code]['label'] ?? null;
}

function li_scan_normalize_company_description(?string $raw): ?string
{
    $raw = trim(preg_replace('/\s+/u', ' ', (string) $raw) ?? '');
    if ($raw === '') {
        return null;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($raw, 0, 2000);
    }

    return substr($raw, 0, 2000);
}

/**
 * @return array{branche_code: ?string, company_description: ?string, contextprofiel: ?string}
 */
function li_scan_normalize_context(?string $brancheRaw, ?string $companyDescriptionRaw, ?string $contextProfielRaw): array
{
    return [
        'branche_code' => li_branche_code_for_scan($brancheRaw),
        'company_description' => li_scan_normalize_company_description($companyDescriptionRaw),
        'contextprofiel' => li_contextprofiel_code_for_scan($contextProfielRaw),
    ];
}
