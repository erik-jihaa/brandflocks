<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-architecture-verbodslijst.php';

/**
 * Scan alle stringwaarden in LLM-JSON op methodiektermen (zelfde lijst als architectuurcheck).
 */
function ce_hba_json_has_forbidden_client_text(array $data): bool
{
    foreach (ce_hba_json_walk_strings($data) as $text) {
        if (ce_hba_architecture_forbidden_hits_in_text($text) !== []) {
            return true;
        }
    }

    return false;
}

/**
 * Na herpoging: lege strings waar de term nog staat.
 *
 * @return array{data: array<string, mixed>, stripped: list<string>}
 */
function ce_hba_json_strip_forbidden_client_strings(array $data): array
{
    $stripped = [];
    $data = ce_hba_json_strip_forbidden_recursive($data, '', $stripped);

    return [
        'data' => $data,
        'stripped' => array_values(array_unique($stripped)),
    ];
}

/**
 * Eerste LLM-call + optionele herpoging met correctie + leegmaken resterende treffers.
 *
 * @param callable(string): array $retryCall ontvangt extra user-suffix (correctiezin)
 */
function ce_hba_apply_client_forbidden_guard_to_llm(
    PDO $pdo,
    ?string $clientId,
    string $surface,
    array $llm,
    callable $retryCall,
): array {
    if (!($llm['ok'] ?? false) || !is_array($llm['data'] ?? null)) {
        return $llm;
    }

    $data = $llm['data'];
    if (!ce_hba_json_has_forbidden_client_text($data)) {
        return $llm;
    }

    $correction = ce_hba_architecture_forbidden_correction_suffix();
    $retry = $retryCall($correction);
    $retried = false;
    if (($retry['ok'] ?? false) && is_array($retry['data'] ?? null)) {
        $llm = $retry;
        $data = $llm['data'];
        $retried = true;
    }

    if (ce_hba_json_has_forbidden_client_text($data)) {
        $stripped = ce_hba_json_strip_forbidden_client_strings($data);
        $llm['data'] = $stripped['data'];
        ce_hba_client_output_log_forbidden($pdo, $clientId, $surface, $retried, $stripped['stripped']);
    } elseif ($retried) {
        ce_hba_client_output_log_forbidden($pdo, $clientId, $surface, true, []);
    }

    return $llm;
}

function ce_hba_sanitize_client_visible_string(string $text): string
{
    return ce_hba_architecture_forbidden_hits_in_text($text) === [] ? $text : '';
}

/** @return list<string> */
function ce_hba_json_walk_strings(array $data, string $prefix = ''): array
{
    $strings = [];
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
        if (is_string($value)) {
            $strings[$path] = $value;
        } elseif (is_array($value)) {
            foreach (ce_hba_json_walk_strings($value, $path) as $p => $text) {
                $strings[$p] = $text;
            }
        }
    }

    return $strings;
}

/**
 * @param list<string> $stripped
 */
function ce_hba_json_strip_forbidden_recursive(array $data, string $prefix, array &$stripped): array
{
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
        if (is_string($value)) {
            if (ce_hba_architecture_forbidden_hits_in_text($value) !== []) {
                $data[$key] = '';
                $stripped[] = $path;
            }
            continue;
        }
        if (is_array($value)) {
            $data[$key] = ce_hba_json_strip_forbidden_recursive($value, $path, $stripped);
        }
    }

    return $data;
}

/** @param list<string> $strippedFields */
function ce_hba_client_output_log_forbidden(
    PDO $pdo,
    ?string $clientId,
    string $surface,
    bool $retried,
    array $strippedFields,
): void {
    require_once __DIR__ . '/event-log-repository.php';
    ce_log_event($pdo, 'warning', 'hba', 'client_output_verbodslijst', 'Methodiekterm in klantzichtbare HBA-output', [
        'clientId' => $clientId,
        'surface' => $surface,
        'retried' => $retried,
        'strippedFields' => $strippedFields,
    ]);
}