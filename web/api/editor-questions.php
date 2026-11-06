<?php

declare(strict_types=1);

/** Marker van het (legacy) redacteur-vragenblok in content_ai; CE-03 genereert dit blok niet meer, oude artikelen kunnen het nog bevatten. */
const CE_EDITOR_BLOCK_MARKER = "\n\n---\nREDACTEUR";

function ce_strip_editor_questions_block(string $body): string
{
    $pos = strpos($body, CE_EDITOR_BLOCK_MARKER);
    if ($pos === false) {
        return $body;
    }

    return rtrim(substr($body, 0, $pos));
}

/** Verwijder markdown-koppen (## Waarom …); blog: alleen hekjes weg, social: hele kopregel weg. */
function ce_strip_markdown_headings(string $body, bool $allowPlainSubheads = false): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    if ($body === '') {
        return '';
    }

    $lines = explode("\n", $body);
    $out = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*#{1,6}\s+(.+?)\s*$/u', $line, $matches)) {
            if ($allowPlainSubheads) {
                $out[] = trim($matches[1]);
            }
            continue;
        }
        $out[] = $line;
    }

    $joined = implode("\n", $out);
    $joined = preg_replace('/\n{3,}/u', "\n\n", $joined) ?? $joined;

    return trim($joined);
}

/** Artikeltekst zonder redacteur-blok — leeg = nog geen CE-03-body. */
function ce_article_has_real_body(string $body): bool
{
    return trim(ce_strip_editor_questions_block($body)) !== '';
}

/** Verwijder optionele platform-regel direct na redactie-wrapper (bv. LINKEDIN POST — DRAFT). */
function ce_strip_platform_draft_header_line(string $body): string
{
    $body = ltrim($body);
    if ($body === '') {
        return '';
    }
    if (preg_match('/^[^\n]*DRAFT[^\n]*\n/u', $body)) {
        return ltrim(preg_replace('/^[^\n]*DRAFT[^\n]*\n/u', '', $body, 1) ?? $body);
    }

    return $body;
}

/**
 * CE-03 redactie-wrapper boven de echte copy (LET OP / META … — … PLATFORM — DRAFT).
 *
 * @return string|null Restcopy na de wrapper, of null als patroon niet herkend.
 */
function ce_strip_ce03_editorial_wrapper(string $body): ?string
{
    $body = trim($body);
    if ($body === '') {
        return null;
    }
    if (!preg_match('/^(?:LET OP\b|META\s*\(\s*redacteur)/is', $body)) {
        return null;
    }
    if (!preg_match('/\r?\n\s*(?:—|---|-)\s*\r?\n/u', $body, $sep, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $rest = ltrim(substr($body, $sep[0][1] + strlen($sep[0][0])));
    $rest = ce_strip_platform_draft_header_line($rest);

    return $rest !== '' ? $rest : null;
}

/**
 * Publiceerbare body: geen redacteur-vragenblok, geen CE-03 redactie-aantekeningen bovenaan.
 * Niet gebruiken in de editor — alleen WP, static site, LinkedIn, enz.
 */
function ce_strip_editorial_annotations_for_publish(string $body, bool $allowPlainSubheads = false): string
{
    $body = ce_strip_editor_questions_block($body);
    $wrapper = ce_strip_ce03_editorial_wrapper($body);
    if ($wrapper !== null) {
        return ce_strip_markdown_headings($wrapper, $allowPlainSubheads);
    }

    return ce_strip_markdown_headings(trim($body), $allowPlainSubheads);
}
