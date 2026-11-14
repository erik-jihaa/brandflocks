<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/branche-bibliotheek-repository.php';

function ce_hba_derived_branche_normalize(string $label): string
{
    $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

    return mb_strtolower($label);
}

function ce_hba_derived_branche_ensure_schema(PDO $pdo): void
{
    ce_ensure_hba_contentflow_schema($pdo);

    if (!ce_table_exists($pdo, 'hba_derived_branche_log')) {
        try {
            $pdo->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS hba_derived_branche_log (
    id CHAR(36) NOT NULL PRIMARY KEY,
    typed_label VARCHAR(255) NOT NULL,
    typed_normalized VARCHAR(255) NOT NULL,
    hit_count INT NOT NULL DEFAULT 0,
    last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_client_id CHAR(36) NULL,
    branche_code VARCHAR(64) NULL,
    UNIQUE KEY uq_hba_derived_branche_norm (typed_normalized),
    KEY idx_hba_derived_branche_hits (hit_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            );
        } catch (Throwable) {
            // Geen CREATE-rechten.
        }
    }

    if (!ce_table_exists($pdo, 'hba_branche_tenant_cache')) {
        try {
            $pdo->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS hba_branche_tenant_cache (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    typed_normalized VARCHAR(255) NOT NULL,
    branche_code VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hba_branche_tenant_cache (client_id, typed_normalized),
    KEY idx_hba_branche_tenant_code (branche_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            );
        } catch (Throwable) {
            // Geen CREATE-rechten.
        }
    }
}

function ce_hba_derived_branche_log_hit(PDO $pdo, string $typedLabel, string $clientId, ?string $brancheCode): void
{
    ce_hba_derived_branche_ensure_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_derived_branche_log')) {
        return;
    }
    $typedLabel = trim($typedLabel);
    if ($typedLabel === '') {
        return;
    }
    $norm = ce_hba_derived_branche_normalize($typedLabel);
    if ($norm === '') {
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM hba_derived_branche_log WHERE typed_normalized = :norm LIMIT 1');
    $stmt->execute(['norm' => $norm]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare(
            'UPDATE hba_derived_branche_log
             SET hit_count = hit_count + 1,
                 last_seen_at = NOW(),
                 last_client_id = :client_id,
                 typed_label = :typed_label,
                 branche_code = COALESCE(:branche_code, branche_code)
             WHERE id = :id'
        )->execute([
            'client_id' => $clientId !== '' ? $clientId : null,
            'typed_label' => $typedLabel,
            'branche_code' => $brancheCode !== null && $brancheCode !== '' ? $brancheCode : null,
            'id' => $id,
        ]);

        return;
    }

    $pdo->prepare(
        'INSERT INTO hba_derived_branche_log (
            id, typed_label, typed_normalized, hit_count, last_seen_at, last_client_id, branche_code
        ) VALUES (
            :id, :typed_label, :typed_normalized, 1, NOW(), :last_client_id, :branche_code
        )'
    )->execute([
        'id' => ce_uuid(),
        'typed_label' => $typedLabel,
        'typed_normalized' => $norm,
        'last_client_id' => $clientId !== '' ? $clientId : null,
        'branche_code' => $brancheCode !== null && $brancheCode !== '' ? $brancheCode : null,
    ]);
}

function ce_hba_branche_tenant_cache_get(PDO $pdo, string $clientId, string $typedLabel): ?string
{
    ce_hba_derived_branche_ensure_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_branche_tenant_cache') || $clientId === '') {
        return null;
    }
    $norm = ce_hba_derived_branche_normalize($typedLabel);
    if ($norm === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT branche_code FROM hba_branche_tenant_cache
         WHERE client_id = :client_id AND typed_normalized = :norm LIMIT 1'
    );
    $stmt->execute(['client_id' => $clientId, 'norm' => $norm]);
    $code = $stmt->fetchColumn();

    return is_string($code) && $code !== '' ? $code : null;
}

function ce_hba_branche_tenant_cache_put(PDO $pdo, string $clientId, string $typedLabel, string $brancheCode): void
{
    ce_hba_derived_branche_ensure_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_branche_tenant_cache') || $clientId === '' || $brancheCode === '') {
        return;
    }
    $norm = ce_hba_derived_branche_normalize($typedLabel);
    if ($norm === '') {
        return;
    }
    $pdo->prepare(
        'INSERT INTO hba_branche_tenant_cache (id, client_id, typed_normalized, branche_code)
         VALUES (:id, :client_id, :typed_normalized, :branche_code)
         ON DUPLICATE KEY UPDATE branche_code = VALUES(branche_code)'
    )->execute([
        'id' => ce_uuid(),
        'client_id' => $clientId,
        'typed_normalized' => $norm,
        'branche_code' => $brancheCode,
    ]);
}

/** @return list<array<string, mixed>> */
function ce_hba_derived_branche_log_rows(PDO $pdo): array
{
    ce_hba_derived_branche_ensure_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_derived_branche_log')) {
        return [];
    }
    $stmt = $pdo->query(
        'SELECT typed_label, hit_count, last_seen_at, last_client_id, branche_code
         FROM hba_derived_branche_log
         ORDER BY hit_count DESC, last_seen_at DESC'
    );

    return $stmt ? ($stmt->fetchAll() ?: []) : [];
}

function ce_hba_derived_branche_csv_header(): string
{
    return 'nr;branche;afbakening;markt;ronde1_volgorde;status;bestand;versie_instructie;aantal;laatste_datum';
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed>|null $library
 */
function ce_hba_derived_branche_csv_line(int $nr, array $row, ?array $library = null): string
{
    $afbakening = '';
    $markt = '';
    if (is_array($library)) {
        $afbakening = trim((string) ($library['branche_definitie'] ?? $library['market_definition'] ?? ''));
        $vak = $library['vakcontext'] ?? [];
        if (is_string($vak)) {
            $decoded = json_decode($vak, true);
            $vak = is_array($decoded) ? $decoded : [];
        }
        if (is_array($vak)) {
            $markt = trim((string) ($vak['markt'] ?? ''));
        }
    }

    $fields = [
        (string) $nr,
        (string) ($row['typed_label'] ?? ''),
        $afbakening,
        $markt,
        '',
        'open',
        '',
        '',
        (string) (int) ($row['hit_count'] ?? 0),
        (string) ($row['last_seen_at'] ?? ''),
    ];

    return implode(';', array_map(static function (string $value): string {
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }, $fields));
}

/** @param list<array<string, mixed>> $rows */
function ce_hba_derived_branche_csv(PDO $pdo, array $rows): string
{
    $lines = [ce_hba_derived_branche_csv_header()];
    $n = 1;
    foreach ($rows as $row) {
        $library = null;
        $code = trim((string) ($row['branche_code'] ?? ''));
        if ($code !== '' && function_exists('ce_hba_fetch_branche_by_code')) {
            $library = ce_hba_fetch_branche_by_code($pdo, $code);
        }
        $lines[] = ce_hba_derived_branche_csv_line($n, $row, $library);
        $n++;
    }

    return implode("\n", $lines) . "\n";
}

/** @param array<string, mixed>|null $catalogRow @param array<string, mixed>|null $existing */
function ce_hba_derived_branche_should_log(?array $catalogRow, ?array $existing): bool
{
    if (is_array($catalogRow) && !empty($catalogRow['is_gecureerd'])) {
        return false;
    }
    if (is_array($existing) && (string) ($existing['bron'] ?? '') === 'gecureerd') {
        return false;
    }

    return true;
}