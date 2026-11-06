<?php

declare(strict_types=1);

/** Stap 2a: hint mag niets citeren dat niet in het artikel staat. Geen promptwijziging. */

function ce_hba_normalize_aanspreekvorm(string $raw): string
{
    $v = mb_strtolower(trim($raw), 'UTF-8');
    if ($v === 'je' || $v === 'jij') {
        return 'je';
    }
    if ($v === 'u') {
        return 'u';
    }

    return '';
}

/** @return list<string> */
function ce_hba_extract_quoted_fragments(string $text): array
{
    if (trim($text) === '') {
        return [];
    }
    $patterns = [
        '/"([^"\n]{8,400})"/u',
        '/\'([^\'\n]{8,400})\'/u',
        '/“([^”\n]{8,400})”/u',
        '/„([^“\n]{8,400})“/u',
        '/‘([^’\n]{8,400})’/u',
        '/«([^»\n]{8,400})»/u',
    ];
    $found = [];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $matches)) {
            continue;
        }
        foreach ($matches[1] as $frag) {
            $t = trim((string) $frag);
            if ($t === '' || !preg_match('/\s/u', $t)) {
                continue;
            }
            $found[$t] = $t;
        }
    }

    return array_values($found);
}

function ce_hba_strip_placeholder_brackets(string $text): string
{
    $clean = preg_replace('/\[[^\[\]]{3,200}\]/u', '', $text) ?? $text;
    $clean = preg_replace("/[ \t]+/u", ' ', $clean) ?? $clean;
    $clean = preg_replace("/ *\n[ \t]*/u", "\n", $clean) ?? $clean;

    return trim($clean);
}

/**
 * Invulvelden uit tekst verzamelen. In aanwijzing: haken weg (geen lopende tekst).
 * In passage_na / cta_na / headline_na: haken laten staan — UI markeert ze oranje;
 * strippen maakte de preview gelijk aan de originele copy (noop).
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function ce_hba_move_placeholders_out_of_running_text(array $data): array
{
    $extra = [];
    foreach (['passage_na', 'cta_na', 'headline_na', 'aanwijzing'] as $key) {
        $raw = (string) ($data[$key] ?? '');
        if ($raw === '') {
            continue;
        }
        foreach (ce_hba_extract_invulvelden($raw) as $field) {
            $extra[$field] = $field;
        }
        if ($key === 'aanwijzing') {
            $data[$key] = ce_hba_strip_placeholder_brackets($raw);
        }
    }
    $merged = [];
    $existing = is_array($data['invulvelden'] ?? null) ? $data['invulvelden'] : [];
    foreach (array_merge($existing, array_values($extra)) as $item) {
        $t = trim((string) $item);
        if ($t === '') {
            continue;
        }
        $merged[$t] = $t;
        if (count($merged) >= 2) {
            break;
        }
    }
    $data['invulvelden'] = array_values($merged);

    return $data;
}

function ce_hba_normalize_hint_compare(string $value): string
{
    $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

    return mb_strtolower($collapsed, 'UTF-8');
}

/**
 * Herschrijving die (na strip van invulhaken) gelijk is aan de bronpassage = geen advies.
 *
 * @param array<string, mixed> $data
 * @return list<array{reason: string, fragment: string}>
 */
function ce_hba_hint_noop_rewrite_violations(array $data): array
{
    $pairs = [
        ['passage_voor', 'passage_na'],
        ['cta_voor', 'cta_na'],
        ['headline_voor', 'headline_na'],
    ];
    $out = [];
    foreach ($pairs as [$voorKey, $naKey]) {
        $voor = trim((string) ($data[$voorKey] ?? ''));
        $na = trim((string) ($data[$naKey] ?? ''));
        if ($voor === '' || $na === '') {
            continue;
        }
        $naStripped = ce_hba_strip_placeholder_brackets($na);
        if ($naStripped === '') {
            continue;
        }
        if (ce_hba_normalize_hint_compare($voor) === ce_hba_normalize_hint_compare($naStripped)
            && !ce_hba_text_has_open_placeholders($na)
        ) {
            $out[] = ['reason' => 'noop_rewrite', 'fragment' => $naKey];
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $data
 * @return list<array{reason: string, fragment: string}>
 */
function ce_hba_hint_quote_violations(array $data, string $articleText): array
{
    $quotes = ce_hba_extract_quoted_fragments(
        (string) ($data['aanwijzing'] ?? '') . "\n" . (string) ($data['regel_overtreden'] ?? '')
    );
    $passageVoor = trim((string) ($data['passage_voor'] ?? ''));
    if ($passageVoor !== '') {
        $quotes[] = $passageVoor;
    }
    $articleNorm = ce_hba_normalize_text_for_citation_match($articleText);
    $out = [];
    foreach ($quotes as $frag) {
        $fragNorm = ce_hba_normalize_text_for_citation_match($frag);
        if ($fragNorm === '' || !str_contains($articleNorm, $fragNorm)) {
            $out[] = ['reason' => 'quote_not_in_source', 'fragment' => $frag];
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $data
 * @return array{reason: string, fragment: string}|null
 */
function ce_hba_hint_address_violation(array $data, string $aanspreekvorm): ?array
{
    $vorm = ce_hba_normalize_aanspreekvorm($aanspreekvorm);
    if ($vorm === '') {
        return null;
    }
    $blob = mb_strtolower(implode("\n", [
        (string) ($data['passage_na'] ?? ''),
        (string) ($data['cta_na'] ?? ''),
        (string) ($data['headline_na'] ?? ''),
    ]), 'UTF-8');
    if ($vorm === 'je' && preg_match('/\b(u|uw|uwe)\b/u', $blob)) {
        return ['reason' => 'wrong_address', 'fragment' => 'u'];
    }
    if ($vorm === 'u' && preg_match('/\b(je|jij|jou|jouw)\b/u', $blob)) {
        return ['reason' => 'wrong_address', 'fragment' => 'je'];
    }

    return null;
}

/**
 * @param array<string, mixed> $data
 * @return array{ok: bool, violations: list<array{reason: string, fragment: string}>}
 */
function ce_hba_validate_article_hint(array $data, string $articleText, string $aanspreekvorm): array
{
    $violations = ce_hba_hint_quote_violations($data, $articleText);
    $address = ce_hba_hint_address_violation($data, $aanspreekvorm);
    if ($address !== null) {
        $violations[] = $address;
    }
    // Alleen aanwijzing: geen [invulveld] in de advieszin. In passage_na horen haken wél
    // (oranje markering + Pas toe); die strippen = preview herhaalt de copy.
    if (ce_hba_text_has_open_placeholders((string) ($data['aanwijzing'] ?? ''))) {
        $violations[] = ['reason' => 'placeholder', 'fragment' => 'aanwijzing'];
    }
    foreach (ce_hba_hint_noop_rewrite_violations($data) as $noop) {
        $violations[] = $noop;
    }

    return ['ok' => $violations === [], 'violations' => $violations];
}

/**
 * @param list<array{reason: string, fragment: string}> $violations
 */
function ce_hba_hint_verificatie_retry_message(array $violations, string $aanspreekvorm): string
{
    $lines = ['Je vorige output is afgekeurd.'];
    foreach ($violations as $v) {
        $reason = (string) ($v['reason'] ?? '');
        if ($reason === 'quote_not_in_source') {
            $lines[] = 'Een citaat tussen aanhalingstekens in aanwijzing of regel_overtreden staat niet letterlijk in CONCEPT-ARTIKEL.';
        } elseif ($reason === 'wrong_address') {
            $vorm = ce_hba_normalize_aanspreekvorm($aanspreekvorm);
            $lines[] = 'De herschreven passage moet de lezer aanspreken met "' . $vorm . '".';
        } elseif ($reason === 'placeholder') {
            $lines[] = 'Zet ontbrekende feiten niet tussen vierkante haken in de aanwijzing — alleen in passage_na, cta_na of headline_na.';
        } elseif ($reason === 'noop_rewrite') {
            $lines[] = 'passage_na / cta_na / headline_na mag niet gelijk zijn aan de originele passage. Herschrijf of voeg een invulveld [feit] toe.';
        }
    }
    $lines[] = 'Citeer in aanwijzing alleen zinnen die letterlijk in het concept staan. De herschrijving zelf moet wél anders zijn. Geef nu de gevraagde JSON-output.';

    return implode(' ', array_unique($lines));
}

/**
 * @param list<array{reason: string, fragment: string}> $violations
 */
function ce_hba_log_hint_verificatie(PDO $pdo, string $articleId, array $violations, int $attempt): void
{
    require_once __DIR__ . '/event-log-repository.php';
    foreach ($violations as $v) {
        $reason = (string) ($v['reason'] ?? 'onbekend');
        $fragment = trim((string) ($v['fragment'] ?? ''));
        ce_log_event($pdo, 'warning', 'hba', 'hint_citaatcontrole', $reason, [
            'article_id' => $articleId,
            'fragment' => mb_substr($fragment, 0, 200),
            'reason' => $reason,
            'attempt' => $attempt,
        ]);
    }
}

function ce_hba_persist_aanspreekvorm(PDO $pdo, string $narrativeId, mixed $raw): void
{
    if ($narrativeId === '' || !ce_table_has_column($pdo, 'brand_narratives', 'aanspreekvorm')) {
        return;
    }
    $value = ce_hba_normalize_aanspreekvorm(is_string($raw) ? $raw : '');
    $pdo->prepare('UPDATE brand_narratives SET aanspreekvorm = :v WHERE id = :id')
        ->execute(['v' => $value !== '' ? $value : null, 'id' => $narrativeId]);
}

function ce_hba_normalize_regel_whitespace(string $text): string
{
    $clean = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

    return $clean;
}

/**
 * "Jullie regels" uit Merkarchitectuur (DB-veld geloofwaardigheidstest, één regel per regel).
 *
 * @return list<string>
 */
function ce_hba_jullie_regels_lines(string $geloofwaardigheidstest): array
{
    $raw = trim($geloofwaardigheidstest);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/\r\n|\r|\n/u', $raw) ?: [$raw];
    $lines = [];
    foreach ($parts as $part) {
        $t = ce_hba_normalize_regel_whitespace((string) $part);
        if ($t !== '') {
            $lines[] = $t;
        }
    }

    return $lines;
}

/**
 * @deprecated Gebruik ce_hba_jullie_regels_lines; verboden_logica hoort niet in CE-04-citaat.
 *
 * @return list<string>
 */
function ce_hba_brand_regel_lines(string $geloofwaardigheidstest, array $verbodenLogica = []): array
{
    return ce_hba_jullie_regels_lines($geloofwaardigheidstest);
}

/** Letterlijke regel uit architectuur; anders beste match of eerste regel. */
function ce_hba_resolve_literal_brand_regel(string $candidate, string $geloofwaardigheidstest, array $verbodenLogica = []): string
{
    $candidate = ce_hba_normalize_regel_whitespace($candidate);
    $pool = ce_hba_brand_regel_lines($geloofwaardigheidstest, $verbodenLogica);
    if ($pool === []) {
        return $candidate;
    }
    foreach ($pool as $line) {
        if ($candidate !== '' && ($line === $candidate || str_contains($line, $candidate) || str_contains($candidate, $line))) {
            return $line;
        }
    }
    if ($candidate !== '') {
        $lower = mb_strtolower($candidate, 'UTF-8');
        foreach ($pool as $line) {
            $l = mb_strtolower($line, 'UTF-8');
            if (str_contains($l, $lower) || str_contains($lower, $l)) {
                return $line;
            }
        }
    }

    return $pool[0];
}

function ce_hba_regel_literally_in_jullie_regels(string $regel, string $geloofwaardigheidstest): bool
{
    $regel = ce_hba_normalize_regel_whitespace(rtrim($regel, '.'));
    if ($regel === '') {
        return false;
    }
    foreach (ce_hba_jullie_regels_lines($geloofwaardigheidstest) as $line) {
        $lineNorm = ce_hba_normalize_regel_whitespace(rtrim($line, '.'));
        if ($lineNorm === $regel || $line === $regel) {
            return true;
        }
    }

    return false;
}

/** @deprecated Gebruik ce_hba_regel_literally_in_jullie_regels */
function ce_hba_regel_literally_in_architecture(string $regel, string $geloofwaardigheidstest, array $verbodenLogica = []): bool
{
    return ce_hba_regel_literally_in_jullie_regels($regel, $geloofwaardigheidstest);
}

function ce_hba_extract_quoted_regel_from_ce04_comment(string $comment): string
{
    if (!preg_match('/Dit stuk gaat in tegen je regel:\s*"([^"]+)"/u', $comment, $matches)) {
        return '';
    }

    return ce_hba_normalize_regel_whitespace(rtrim((string) $matches[1], '.'));
}

function ce_hba_ce04_regel_feedback_ok(string $comment, string $excerpt, string $geloofwaardigheidstest): bool
{
    if (trim($excerpt) === '') {
        return false;
    }
    $quoted = ce_hba_extract_quoted_regel_from_ce04_comment($comment);

    return $quoted !== '' && ce_hba_regel_literally_in_jullie_regels($quoted, $geloofwaardigheidstest);
}

function ce_hba_ce04_regelbreuk_comment(string $literalRegel, string $citaat): string
{
    $regel = ce_hba_normalize_regel_whitespace(rtrim($literalRegel, '.'));
    $citaat = trim($citaat);
    $comment = 'Dit stuk gaat in tegen je regel: "' . $regel . '."';
    if ($citaat !== '') {
        $comment .= ' In je tekst staat: «' . $citaat . '».';
    }

    return $comment;
}

function ce_ce04_comment_is_regelbreuk(string $comment): bool
{
    return str_starts_with($comment, 'Dit stuk gaat in tegen je regel:')
        || str_starts_with($comment, 'Dit stuk gaat in tegen je regel dat');
}
