<?php

declare(strict_types=1);

require_once __DIR__ . '/source-document-repository.php';

const CE_STRATEGY_DOCUMENT_MAX_BYTES = 20 * 1024 * 1024;

/** @return list<string> */
function ce_strategy_document_allowed_extensions(): array
{
    return ['pdf', 'docx', 'pptx'];
}

function ce_strategy_document_extension_from_filename(string $filename): string
{
    return strtolower(pathinfo(trim($filename), PATHINFO_EXTENSION));
}

function ce_strategy_document_extension_allowed(string $ext): bool
{
    return in_array(strtolower($ext), ce_strategy_document_allowed_extensions(), true);
}

function ce_extract_text_from_docx(string $path): string
{
    if (!is_readable($path) || !class_exists('ZipArchive')) {
        return '';
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!is_string($xml) || trim($xml) === '') {
        return '';
    }

    $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
    $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
    preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/', $xml, $matches);
    $parts = $matches[1] ?? [];
    if ($parts === []) {
        return '';
    }

    return ce_source_document_normalize_extracted_text(implode('', $parts));
}

function ce_extract_text_from_pptx(string $path): string
{
    if (!is_readable($path) || !class_exists('ZipArchive')) {
        return '';
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return '';
    }

    $blocks = [];
    for ($i = 1; $i <= 200; $i++) {
        $slideXml = $zip->getFromName('ppt/slides/slide' . $i . '.xml');
        if (!is_string($slideXml) || trim($slideXml) === '') {
            if ($i === 1) {
                continue;
            }
            break;
        }

        preg_match_all('/<a:t(?:\s[^>]*)?>([^<]*)<\/a:t>/', $slideXml, $matches);
        $parts = $matches[1] ?? [];
        if ($parts === []) {
            continue;
        }
        $slideText = trim(implode('', $parts));
        if ($slideText !== '') {
            $blocks[] = $slideText;
        }
    }

    $zip->close();
    if ($blocks === []) {
        return '';
    }

    return ce_source_document_normalize_extracted_text(implode("\n\n", $blocks));
}

function ce_extract_text_from_pdf(string $path): string
{
    return ce_source_document_extract_pdf_text($path);
}

function ce_strategy_document_extract_text(string $path, string $ext): string
{
    $ext = strtolower(trim($ext));

    return match ($ext) {
        'pdf' => ce_extract_text_from_pdf($path),
        'docx' => ce_extract_text_from_docx($path),
        'pptx' => ce_extract_text_from_pptx($path),
        default => '',
    };
}

/** @return list<string> */
function ce_strategy_document_text_chunks(string $text): array
{
    return ce_source_document_split_text($text);
}
