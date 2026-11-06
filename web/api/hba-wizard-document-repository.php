<?php

declare(strict_types=1);

require_once __DIR__ . '/strategy-document-extract.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-v2-prompts.php';
require_once __DIR__ . '/hba-wizard-rate-limit.php';

function ce_hba_wizard_document_storage_dir(string $clientId): string
{
    return dirname(__DIR__) . '/uploads/hba-wizard-docs/' . $clientId;
}

function ce_hba_wizard_document_meta_path(string $clientId): string
{
    return ce_hba_wizard_document_storage_dir($clientId) . '/meta.json';
}

/** @return array<string, mixed>|null */
function ce_hba_wizard_document_load_meta(string $clientId): ?array
{
    $path = ce_hba_wizard_document_meta_path($clientId);
    if (!is_readable($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

function ce_hba_wizard_document_has_upload(string $clientId): bool
{
    $meta = ce_hba_wizard_document_load_meta($clientId);

    return is_array($meta) && trim((string) ($meta['storedPath'] ?? '')) !== '';
}

function ce_hba_wizard_document_clear_storage(string $clientId): void
{
    $meta = ce_hba_wizard_document_load_meta($clientId);
    if (is_array($meta)) {
        $storedPath = trim((string) ($meta['storedPath'] ?? ''));
        if ($storedPath !== '' && is_file($storedPath)) {
            @unlink($storedPath);
        }
    }

    $dir = ce_hba_wizard_document_storage_dir($clientId);
    if (!is_dir($dir)) {
        return;
    }

    foreach (glob($dir . '/*') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        if (basename($path) === 'meta.json') {
            continue;
        }
        @unlink($path);
    }
}

function ce_hba_wizard_document_extracted_text(string $clientId): string
{
    $meta = ce_hba_wizard_document_load_meta($clientId);
    if (!is_array($meta)) {
        return '';
    }

    $text = trim((string) ($meta['extractedText'] ?? ''));
    if ($text !== '') {
        return $text;
    }

    $storedPath = trim((string) ($meta['storedPath'] ?? ''));
    if ($storedPath === '' || !is_readable($storedPath)) {
        return '';
    }

    $ext = ce_strategy_document_extension_from_filename($storedPath);

    return ce_strategy_document_extract_text($storedPath, $ext);
}

/** @param mixed $value @return string|null */
function ce_hba_wizard_nullable_string(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);

    return $trimmed !== '' ? $trimmed : null;
}

/** @param mixed $value @return list<string|null>|null */
function ce_hba_wizard_nullable_regels(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    $out = [];
    foreach ($value as $item) {
        if ($item === null) {
            $out[] = null;
            continue;
        }
        if (!is_string($item)) {
            continue;
        }
        $trimmed = trim($item);
        $out[] = $trimmed !== '' ? $trimmed : null;
    }

    if ($out === []) {
        return null;
    }

    $hasContent = false;
    foreach ($out as $rule) {
        if (is_string($rule) && $rule !== '') {
            $hasContent = true;
            break;
        }
    }

    return $hasContent ? $out : null;
}

/** @param mixed $value @return list<string|null>|null */
function ce_hba_wizard_nullable_kernformuleringen(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    $out = [];
    foreach (array_slice($value, 0, 5) as $item) {
        if ($item === null) {
            $out[] = null;
            continue;
        }
        if (!is_string($item)) {
            continue;
        }
        $trimmed = trim($item);
        $out[] = $trimmed !== '' ? $trimmed : null;
    }

    if ($out === []) {
        return null;
    }

    $hasContent = false;
    foreach ($out as $line) {
        if (is_string($line) && $line !== '') {
            $hasContent = true;
            break;
        }
    }

    return $hasContent ? $out : null;
}

/** @param array<string, mixed> $stored @return list<string> */
function ce_hba_wizard_document_suggestions_missing_fields(array $stored): array
{
    $missing = [];
    foreach (['identity_intro', 'doel', 'distinctiviteit', 'wat_bewust_niet', 'regels', 'kernformuleringen'] as $field) {
        if (!array_key_exists($field, $stored)) {
            $missing[] = $field;
        }
    }

    return $missing;
}

/** @param array<string, mixed> $data @return array<string, mixed> */
function ce_hba_wizard_document_suggestions_dto(array $data): array
{
    return [
        'identityIntro' => ce_hba_wizard_nullable_string($data['identity_intro'] ?? null),
        'doel' => ce_hba_wizard_nullable_string($data['doel'] ?? null),
        'distinctiviteit' => ce_hba_wizard_nullable_string($data['distinctiviteit'] ?? null),
        'watBewustNiet' => ce_hba_wizard_nullable_string($data['wat_bewust_niet'] ?? null),
        'regels' => ce_hba_wizard_nullable_regels($data['regels'] ?? null),
        'kernformuleringen' => ce_hba_wizard_nullable_kernformuleringen($data['kernformuleringen'] ?? null),
    ];
}

/** @param array<string, mixed> $suggestions */
function ce_hba_wizard_document_suggestions_to_meta(array $suggestions): array
{
    return [
        'identity_intro' => $suggestions['identityIntro'] ?? null,
        'doel' => $suggestions['doel'] ?? null,
        'distinctiviteit' => $suggestions['distinctiviteit'] ?? null,
        'wat_bewust_niet' => $suggestions['watBewustNiet'] ?? null,
        'regels' => $suggestions['regels'] ?? null,
        'kernformuleringen' => $suggestions['kernformuleringen'] ?? null,
    ];
}

/** @return array{ok: bool, error?: string, code?: string, suggestions?: array<string, mixed>, noTextFound?: bool} */
function ce_hba_v2_wizard_document_suggestions_from_text(string $text): array
{
    $chunks = ce_strategy_document_text_chunks($text);
    if ($chunks === []) {
        return ['ok' => false, 'noTextFound' => true, 'error' => 'Geen tekst gevonden in dit document.'];
    }

    $documentBody = implode("\n\n---\n\n", $chunks);
    $system = ce_hba_v2_wizard_document_suggestions_system_prompt();
    $user = "STRATEGIEDOCUMENT:\n\n" . $documentBody;

    $llm = ce_hba_v2_call_json_for(CE_HBA_FN_WIZARD_HINTS, $system, $user, 0.1, 4096);
    if (!$llm['ok']) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Suggesties ophalen mislukt'];
    }

    $data = is_array($llm['data'] ?? null) ? $llm['data'] : [];

    return [
        'ok' => true,
        'suggestions' => ce_hba_wizard_document_suggestions_dto($data),
    ];
}

/** @return array{ok: bool, hasUpload: bool, filename?: string, suggestions?: array<string, mixed>} */
function ce_hba_wizard_document_status(PDO $pdo, string $clientId): array
{
    return ce_hba_wizard_document_resolve_status($clientId, false);
}

/** @return array{ok: bool, hasUpload: bool, filename?: string, suggestions?: array<string, mixed>, regenerated?: bool} */
function ce_hba_wizard_document_refresh_suggestions(string $clientId, bool $force = false): array
{
    return ce_hba_wizard_document_resolve_status($clientId, $force);
}

/** @return array{ok: bool, hasUpload: bool, filename?: string, suggestions?: array<string, mixed>, regenerated?: bool} */
function ce_hba_wizard_document_resolve_status(string $clientId, bool $forceRegenerate): array
{
    $meta = ce_hba_wizard_document_load_meta($clientId);
    $hasUpload = is_array($meta) && trim((string) ($meta['storedPath'] ?? '')) !== '';
    if (!$hasUpload) {
        return ['ok' => true, 'hasUpload' => false];
    }

    $suggestions = null;
    $regenerated = false;
    $storedSuggestions = isset($meta['suggestions']) && is_array($meta['suggestions']) ? $meta['suggestions'] : null;
    $needsRegeneration = $forceRegenerate
        || $storedSuggestions === null
        || ce_hba_wizard_document_suggestions_missing_fields($storedSuggestions) !== [];
    if (!$needsRegeneration) {
        $suggestions = ce_hba_wizard_document_suggestions_dto($storedSuggestions);
    } else {
        $text = ce_hba_wizard_document_extracted_text($clientId);
        if ($text !== '') {
            $regenerated = true;
            $regeneratedResult = ce_hba_v2_wizard_document_suggestions_from_text($text);
            if ($regeneratedResult['ok'] && is_array($regeneratedResult['suggestions'] ?? null)) {
                $suggestions = $regeneratedResult['suggestions'];
                $meta['suggestions'] = ce_hba_wizard_document_suggestions_to_meta($suggestions);
                file_put_contents(
                    ce_hba_wizard_document_meta_path($clientId),
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                );
            }
        } elseif ($storedSuggestions !== null) {
            $suggestions = ce_hba_wizard_document_suggestions_dto($storedSuggestions);
        }
    }

    return [
        'ok' => true,
        'hasUpload' => true,
        'filename' => trim((string) ($meta['filename'] ?? '')),
        'suggestions' => $suggestions,
        'regenerated' => $regenerated,
    ];
}

function ce_upload_file_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Bestand is te groot voor de server (PHP upload-limiet).',
        UPLOAD_ERR_PARTIAL => 'Upload afgebroken — probeer opnieuw.',
        UPLOAD_ERR_NO_FILE => 'Geen bestand gekozen.',
        default => 'Upload mislukt (code ' . $code . ').',
    };
}

/** @return array{ok: bool, error?: string, code?: string, suggestions?: array<string, mixed>, noTextFound?: bool, filename?: string} */
function ce_hba_wizard_document_store_upload(PDO $pdo, string $clientId, array $file): array
{
    $rateLimit = ce_hba_wizard_rate_limit_check($clientId, 'document_upload');
    if (!$rateLimit['ok']) {
        return $rateLimit;
    }

    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => ce_upload_file_error_message($uploadError)];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $original = trim((string) ($file['name'] ?? 'document.pdf'));
    $size = (int) ($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Ongeldig uploadbestand.'];
    }

    if ($size <= 0 || $size > CE_STRATEGY_DOCUMENT_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Bestand is te groot (max. 20 MB).'];
    }

    $ext = ce_strategy_document_extension_from_filename($original);
    if (!ce_strategy_document_extension_allowed($ext)) {
        return ['ok' => false, 'error' => 'Alleen PDF, Word (.docx) en PowerPoint (.pptx) worden ondersteund.'];
    }

    $text = ce_strategy_document_extract_text($tmp, $ext);
    if ($text === '') {
        return [
            'ok' => false,
            'noTextFound' => true,
            'error' => 'Geen tekst gevonden in dit document.',
        ];
    }

    ce_hba_wizard_document_clear_storage($clientId);

    $dir = ce_hba_wizard_document_storage_dir($clientId);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Opslagmap kon niet worden aangemaakt.'];
    }

    $timestamp = gmdate('YmdHis');
    $storedName = $timestamp . '.' . $ext;
    $storedPath = $dir . '/' . $storedName;
    if (!move_uploaded_file($tmp, $storedPath)) {
        return ['ok' => false, 'error' => 'Bestand kon niet worden opgeslagen.'];
    }

    $suggestionResult = ce_hba_v2_wizard_document_suggestions_from_text($text);
    if (!$suggestionResult['ok']) {
        @unlink($storedPath);

        return $suggestionResult;
    }

    $suggestions = $suggestionResult['suggestions'] ?? [];

    $meta = [
        'clientId' => $clientId,
        'filename' => mb_substr($original, 0, 255),
        'storedPath' => $storedPath,
        'extractedText' => mb_substr($text, 0, 120000),
        'uploadedAt' => gmdate('c'),
        'suggestions' => ce_hba_wizard_document_suggestions_to_meta($suggestions),
    ];
    file_put_contents(
        ce_hba_wizard_document_meta_path($clientId),
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    );

    ce_hba_wizard_rate_limit_increment($clientId, 'document_upload');

    return [
        'ok' => true,
        'filename' => $original,
        'suggestions' => $suggestionResult['suggestions'] ?? [],
    ];
}
