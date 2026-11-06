<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/publish-repository.php';
require_once __DIR__ . '/editor-questions.php';
require_once __DIR__ . '/mappers.php';

/** Alle publish-modes zijn handmatig: beeldadvies is altijd nodig. */
function ce_client_needs_image_advice(PDO $pdo, ?string $clientId): bool
{
    return true;
}

function ce_image_advice_is_legacy_format(string $advice): bool
{
    $advice = trim($advice);

    if ($advice === '') {
        return false;
    }

    if (str_starts_with($advice, 'Kies een ')
        || str_contains($advice, 'Laat visueel iets zien')
        || str_contains($advice, 'Concreet onderwerp of scène')
        || str_contains($advice, 'documentaire stijl')
        || str_contains($advice, 'stockclich')) {
        return true;
    }

    if (preg_match('/\b(sleutel|verhuisdoos|brievenbus|archiefmap|op kantoor|werkplek|natuurlijk licht|vierkant)\b/iu', $advice) === 1) {
        return true;
    }

    if (str_contains($advice, ' — ')) {
        return true;
    }

    // Nieuwe prompts: alleen korte Engelse zoektermen.
    return !preg_match('/^[a-z0-9 ,-]+$/i', $advice);
}

function ce_image_advice_normalize_key(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? '';
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';

    return trim($text);
}

function ce_image_advice_texts_similar(string $a, string $b): bool
{
    if ($a === '' || $b === '') {
        return false;
    }

    $na = ce_image_advice_normalize_key($a);
    $nb = ce_image_advice_normalize_key($b);
    if ($na === '' || $nb === '') {
        return false;
    }
    if ($na === $nb) {
        return true;
    }
    if (str_contains($na, $nb) || str_contains($nb, $na)) {
        return true;
    }

    similar_text($na, $nb, $pct);

    return $pct > 82;
}

/** @return list<string> */
function ce_image_advice_search_terms_for_theme(string $themeKey): array
{
    return match ($themeKey) {
        'employee_leaving' => ['office employee leaving', 'cardboard box', 'empty desk'],
        'senior_retirement' => ['retirement office party', 'colleagues applauding', 'empty desk'],
        'team_meeting' => ['business team meeting', 'conference room', 'whiteboard'],
        'factory' => ['factory worker', 'production floor', 'industrial'],
        'construction' => ['construction worker', 'building site', 'hard hat'],
        'warehouse' => ['warehouse worker', 'logistics', 'storage shelves'],
        'retail' => ['retail store', 'shop assistant', 'customer service'],
        'healthcare' => ['healthcare professional', 'clinic', 'medical team'],
        'creative' => ['creative studio', 'designer desk', 'workspace'],
        default => ['modern office', 'professional workspace', 'business'],
    };
}

function ce_image_advice_detect_theme(string $headline, string $theme, string $body): string
{
    $blob = mb_strtolower(trim($headline . ' ' . $theme . ' ' . $body));

    if (preg_match('/\b(vertrek|vertrekt|verlaat|opstap|uitdienst|achterblijft|weggaan)\b/u', $blob)) {
        return 'employee_leaving';
    }
    if (preg_match('/\b(sleutelpersoon|kennisvertrek|brain drain|ervaren medewerker|veteraan)\b/u', $blob)
        || (preg_match('/\b(kennis|expert|specialist)\b/u', $blob) && preg_match('/\b(vertrek|verlaat|weggaan|mist)\b/u', $blob))) {
        return 'employee_leaving';
    }
    if (preg_match('/\b(pensioen|met pensioen|afscheid)\b/u', $blob)) {
        return 'senior_retirement';
    }
    if (preg_match('/\b(fabriek|productie|machine|magazijn|logistiek|warehouse)\b/u', $blob)) {
        return preg_match('/\b(magazijn|logistiek|warehouse)\b/u', $blob) ? 'warehouse' : 'factory';
    }
    if (preg_match('/\b(bouw|constructie|aannemer)\b/u', $blob)) {
        return 'construction';
    }
    if (preg_match('/\b(winkel|retail|verkoop|shop)\b/u', $blob)) {
        return 'retail';
    }
    if (preg_match('/\b(zorg|kliniek|patient|medisch|hospital)\b/u', $blob)) {
        return 'healthcare';
    }
    if (preg_match('/\b(vergader|overleg|team|collega)\b/u', $blob)) {
        return 'team_meeting';
    }
    if (preg_match('/\b(design|studio|creatief|agency)\b/u', $blob)) {
        return 'creative';
    }

    return 'generic';
}

/**
 * Korte Engelse zoektermen voor Pexels/Pixabay/Google — geen dubbelzinnige NL-woorden (sleutel, …).
 *
 * @param array<string, mixed> $row
 */
function ce_build_image_advice_heuristic(array $row): string
{
    $headline = ce_article_headline($row);
    $theme = trim((string) ($row['theme'] ?? $row['slot_theme'] ?? ''));
    if ($theme !== '' && ce_image_advice_texts_similar($theme, $headline)) {
        $theme = '';
    }

    $body = trim(strip_tags((string) ($row['content_human'] ?? '')));
    if ($body === '') {
        $body = ce_strip_editor_questions_block((string) ($row['content_ai'] ?? ''));
        $body = trim(strip_tags($body));
    }

    $themeKey = ce_image_advice_detect_theme($headline, $theme, $body);
    $terms = ce_image_advice_search_terms_for_theme($themeKey);
    $search = implode(', ', array_slice($terms, 0, 3));

    return mb_strlen($search) > 980 ? mb_substr($search, 0, 977) . '…' : $search;
}

/**
 * Genereer en persist image_advice voor handmatige publish-modes.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function ce_ensure_article_image_advice(PDO $pdo, array $row): array
{
    ce_ensure_publish_columns($pdo);

    if (!ce_table_has_column($pdo, 'articles', 'image_advice')) {
        return $row;
    }

    $existing = trim((string) ($row['image_advice'] ?? ''));

    $clientId = trim((string) ($row['client_id'] ?? ''));
    if ($clientId === '' || !ce_client_needs_image_advice($pdo, $clientId)) {
        return $row;
    }

    $contentAi = (string) ($row['content_ai'] ?? '');
    if (!ce_article_has_real_body($contentAi) && trim((string) ($row['content_human'] ?? '')) === '') {
        return $row;
    }

    if ($existing !== '' && !ce_image_advice_is_legacy_format($existing)) {
        return $row;
    }

    $advice = ce_build_image_advice_heuristic($row);
    if ($advice === '') {
        return $row;
    }

    $articleId = trim((string) ($row['id'] ?? ''));
    if ($articleId === '') {
        $row['image_advice'] = $advice;

        return $row;
    }

    $pdo->prepare('UPDATE articles SET image_advice = :advice WHERE id = :id')->execute([
        'advice' => $advice,
        'id' => $articleId,
    ]);
    $row['image_advice'] = $advice;

    return $row;
}
