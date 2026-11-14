<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/uuid-helper.php';

/** @return list<array{list_nr: int, branche_label: string, afbakening: string, markt: string, csv_status: string, json_filename: string, is_gecureerd: bool}> */
function ce_branche_bibliotheek_parse_csv(string $csvPath): array
{
    if (!is_readable($csvPath)) {
        throw new RuntimeException('branchelijst.csv niet leesbaar: ' . $csvPath);
    }
    $handle = fopen($csvPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Kan branchelijst.csv niet openen.');
    }
    $header = fgetcsv($handle, 0, ';', '"', '\\');
    if (!is_array($header)) {
        fclose($handle);
        throw new RuntimeException('branchelijst.csv heeft geen header.');
    }
    $rows = [];
    while (($line = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
        if ($line === [null] || $line === false) {
            continue;
        }
        $nr = (int) trim((string) ($line[0] ?? '0'));
        if ($nr <= 0) {
            continue;
        }
        $status = strtolower(trim((string) ($line[5] ?? '')));
        $jsonFile = trim((string) ($line[6] ?? ''));
        $rows[] = [
            'list_nr' => $nr,
            'branche_label' => trim((string) ($line[1] ?? '')),
            'afbakening' => trim((string) ($line[2] ?? '')),
            'markt' => trim((string) ($line[3] ?? '')),
            'csv_status' => $status !== '' ? $status : 'open',
            'json_filename' => $jsonFile,
            'is_gecureerd' => $status === 'gecontroleerd' && $jsonFile !== '',
        ];
    }
    fclose($handle);

    return $rows;
}

function ce_branche_bibliotheek_normalize_label(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/^\d+\.\s*(.+)$/u', $raw, $m)) {
        return trim($m[1]);
    }

    return $raw;
}

/** @param list<array<string, mixed>> $items */
function ce_branche_bibliotheek_item_strings(array $items, ?string $status = null): array
{
    $out = [];
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($status !== null && (string) ($row['status'] ?? '') !== $status) {
            continue;
        }
        $item = trim((string) ($row['item'] ?? ''));
        if ($item === '') {
            continue;
        }
        $out[] = $item;
    }

    return array_values(array_unique($out));
}

/** @param array<string, mixed> $doc */
function ce_branche_bibliotheek_library_payload_from_json(array $doc, array $csvRow): array
{
    $code = trim((string) ($doc['branche_id'] ?? ''));
    if ($code === '') {
        throw new RuntimeException('JSON mist branche_id: ' . (string) ($csvRow['json_filename'] ?? ''));
    }
    $label = ce_branche_bibliotheek_normalize_label((string) ($doc['branche_naam'] ?? $csvRow['branche_label']));
    if ($label === '') {
        $label = trim((string) ($csvRow['branche_label'] ?? ''));
    }

    $archetypeBlock = is_array($doc['markt_archetype'] ?? null) ? $doc['markt_archetype'] : [];
    $bronnen = is_array($doc['bronnen_kwaliteit'] ?? null) ? $doc['bronnen_kwaliteit'] : [];

    $hygieneFactors = is_array($doc['hygienefactoren'] ?? null) ? $doc['hygienefactoren'] : [];
    $categoryLang = is_array($doc['categorietaal'] ?? null) ? $doc['categorietaal'] : [];
    $axes = is_array($doc['differentiatie_assen'] ?? null) ? $doc['differentiatie_assen'] : [];

    $hygieneItems = ce_branche_bibliotheek_item_strings($hygieneFactors, 'hygiene');
    $categoryItems = ce_branche_bibliotheek_item_strings($categoryLang);

    return [
        'branche_code' => $code,
        'branche_label' => $label,
        'markt_archetype' => trim((string) ($archetypeBlock['archetype'] ?? '')),
        'market_archetype_rationale' => trim((string) ($archetypeBlock['toelichting'] ?? '')),
        'hygienefactoren' => $hygieneItems,
        'categorietaal' => $categoryItems,
        'branche_definitie' => trim((string) ($doc['afbakening'] ?? $csvRow['afbakening'] ?? '')),
        'market_definition' => trim((string) ($doc['afbakening'] ?? $csvRow['afbakening'] ?? '')),
        'bronnen_kwaliteit' => trim((string) ($bronnen['oordeel'] ?? 'middel')),
        'differentiatie_assen' => $axes,
        'vakcontext' => [
            'markt' => trim((string) ($doc['markt'] ?? $csvRow['markt'] ?? '')),
            'peildatum' => trim((string) ($doc['peildatum'] ?? '')),
        ],
        'status' => 'active',
        'bron' => 'gecureerd',
    ];
}

function ce_branche_bibliotheek_ensure_catalog_schema(PDO $pdo): void
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (ce_table_exists($pdo, 'branche_catalog')) {
        return;
    }
    try {
        $pdo->exec(
            <<<'SQL'
CREATE TABLE IF NOT EXISTS branche_catalog (
    list_nr INT NOT NULL PRIMARY KEY,
    branche_label VARCHAR(255) NOT NULL,
    afbakening TEXT NULL,
    markt VARCHAR(16) NULL,
    csv_status VARCHAR(32) NOT NULL DEFAULT 'open',
    json_filename VARCHAR(128) NULL,
    is_gecureerd TINYINT(1) NOT NULL DEFAULT 0,
    branche_code VARCHAR(64) NULL,
    KEY idx_branche_catalog_label (branche_label),
    KEY idx_branche_catalog_code (branche_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    } catch (Throwable) {
        // Geen CREATE-rechten.
    }
}

/** @param array<string, mixed> $payload */
function ce_branche_bibliotheek_upsert_library(PDO $pdo, array $payload): void
{
    if (!ce_table_exists($pdo, 'branche_library')) {
        return;
    }
    $code = trim((string) ($payload['branche_code'] ?? ''));
    if ($code === '') {
        return;
    }

    $stmt = $pdo->prepare('SELECT id FROM branche_library WHERE branche_code = :code LIMIT 1');
    $stmt->execute(['code' => $code]);
    $existingId = $stmt->fetchColumn();

    $hygiene = $payload['hygienefactoren'] ?? [];
    $category = $payload['categorietaal'] ?? [];
    $axes = $payload['differentiatie_assen'] ?? [];
    $vakcontext = $payload['vakcontext'] ?? [];

    $fields = [
        'branche_label' => trim((string) ($payload['branche_label'] ?? '')),
        'markt_archetype' => trim((string) ($payload['markt_archetype'] ?? '')),
        'hygienefactoren' => json_encode(is_array($hygiene) ? $hygiene : [], JSON_UNESCAPED_UNICODE),
        'categorietaal' => json_encode(is_array($category) ? $category : [], JSON_UNESCAPED_UNICODE),
        'bron' => 'gecureerd',
        'branche_definitie' => trim((string) ($payload['branche_definitie'] ?? '')),
        'bronnen_kwaliteit' => trim((string) ($payload['bronnen_kwaliteit'] ?? 'middel')),
        'vakcontext' => json_encode(is_array($vakcontext) ? $vakcontext : [], JSON_UNESCAPED_UNICODE),
        'differentiatie_assen' => json_encode(is_array($axes) ? $axes : [], JSON_UNESCAPED_UNICODE),
        'market_definition' => trim((string) ($payload['market_definition'] ?? '')),
        'market_archetype_rationale' => trim((string) ($payload['market_archetype_rationale'] ?? '')),
        'status' => trim((string) ($payload['status'] ?? 'active')),
        'version' => 1,
        'last_validated_at' => date('Y-m-d H:i:s'),
    ];

    if ($existingId) {
        $sets = [];
        $params = ['id' => $existingId];
        foreach ($fields as $col => $val) {
            $sets[] = "`$col` = :$col";
            $params[$col] = $val;
        }
        $pdo->prepare('UPDATE branche_library SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        return;
    }

    $id = ce_uuid();
    $pdo->prepare(
        'INSERT INTO branche_library (
            id, branche_code, branche_label, markt_archetype, hygienefactoren, categorietaal, bron,
            branche_definitie, bronnen_kwaliteit, vakcontext, differentiatie_assen,
            market_definition, market_archetype_rationale, status, version, last_validated_at
        ) VALUES (
            :id, :code, :branche_label, :markt_archetype, :hygienefactoren, :categorietaal, :bron,
            :branche_definitie, :bronnen_kwaliteit, :vakcontext, :differentiatie_assen,
            :market_definition, :market_archetype_rationale, :status, :version, :last_validated_at
        )'
    )->execute(array_merge(['id' => $id, 'code' => $code], $fields));
}

/** @param array{list_nr: int, branche_label: string, afbakening: string, markt: string, csv_status: string, json_filename: string, is_gecureerd: bool, branche_code?: string|null} $row */
function ce_branche_bibliotheek_upsert_catalog_row(PDO $pdo, array $row): void
{
    ce_branche_bibliotheek_ensure_catalog_schema($pdo);
    if (!ce_table_exists($pdo, 'branche_catalog')) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO branche_catalog (
            list_nr, branche_label, afbakening, markt, csv_status, json_filename, is_gecureerd, branche_code
        ) VALUES (
            :list_nr, :branche_label, :afbakening, :markt, :csv_status, :json_filename, :is_gecureerd, :branche_code
        ) ON DUPLICATE KEY UPDATE
            branche_label = VALUES(branche_label),
            afbakening = VALUES(afbakening),
            markt = VALUES(markt),
            csv_status = VALUES(csv_status),
            json_filename = VALUES(json_filename),
            is_gecureerd = VALUES(is_gecureerd),
            branche_code = VALUES(branche_code)'
    );
    $stmt->execute([
        'list_nr' => (int) $row['list_nr'],
        'branche_label' => (string) $row['branche_label'],
        'afbakening' => (string) $row['afbakening'],
        'markt' => (string) $row['markt'],
        'csv_status' => (string) $row['csv_status'],
        'json_filename' => (string) $row['json_filename'],
        'is_gecureerd' => !empty($row['is_gecureerd']) ? 1 : 0,
        'branche_code' => isset($row['branche_code']) && $row['branche_code'] !== '' ? (string) $row['branche_code'] : null,
    ]);
}

function ce_branche_bibliotheek_resolve_dir(?string $override = null): string
{
    if ($override !== null && $override !== '') {
        $dir = rtrim($override, '/');
        if (!is_file($dir . '/branchelijst.csv')) {
            throw new RuntimeException('Geen branchelijst.csv in: ' . $dir);
        }

        return $dir;
    }

    $root = dirname(__DIR__, 2);
    $candidates = [
        $root . '/web/data',
        $root . '/docs/Branchebibliotheek-voor-Erik-2nov',
        $root . '/briefing/spec/hba-input/Branchebibliotheek-voor-Erik-2nov',
    ];
    foreach ($candidates as $dir) {
        if (is_file($dir . '/branchelijst.csv')) {
            return $dir;
        }
    }

    throw new RuntimeException(
        'Branchebibliotheek-map niet gevonden. Zet de uitgepakte map in docs/Branchebibliotheek-voor-Erik-2nov of geef --dir=...'
    );
}

/** @return array{catalog: int, curated: int, errors: list<string>} */
function ce_branche_bibliotheek_import(?PDO $pdo, string $dir, bool $dryRun = false): array
{
    $csvRows = ce_branche_bibliotheek_parse_csv($dir . '/branchelijst.csv');
    $branchesDir = $dir . '/Branches';
    $stats = ['catalog' => 0, 'curated' => 0, 'errors' => []];

    if (!$dryRun) {
        ce_branche_bibliotheek_ensure_catalog_schema($pdo);
    }

    foreach ($csvRows as $csvRow) {
        $catalogRow = $csvRow;
        $catalogRow['branche_code'] = null;

        if ($csvRow['is_gecureerd']) {
            $jsonPath = $branchesDir . '/' . $csvRow['json_filename'];
            if (!is_readable($jsonPath)) {
                $stats['errors'][] = 'Ontbrekend JSON: ' . $csvRow['json_filename'];
            } else {
                $raw = file_get_contents($jsonPath);
                $doc = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($doc)) {
                    $stats['errors'][] = 'Ongeldig JSON: ' . $csvRow['json_filename'];
                } else {
                    try {
                        $payload = ce_branche_bibliotheek_library_payload_from_json($doc, $csvRow);
                        $catalogRow['branche_code'] = $payload['branche_code'];
                        if (!$dryRun) {
                            ce_branche_bibliotheek_upsert_library($pdo, $payload);
                        }
                        $stats['curated']++;
                    } catch (Throwable $e) {
                        $stats['errors'][] = $csvRow['json_filename'] . ': ' . $e->getMessage();
                    }
                }
            }
        }

        if (!$dryRun) {
            ce_branche_bibliotheek_upsert_catalog_row($pdo, $catalogRow);
        }
        $stats['catalog']++;
    }

    return $stats;
}

function ce_branche_bibliotheek_catalog_csv_path(): ?string
{
    $root = dirname(__DIR__, 2);
    $candidates = [
        $root . '/web/data/branchelijst.csv',
        $root . '/docs/Branchebibliotheek-voor-Erik-2nov/branchelijst.csv',
        $root . '/briefing/spec/hba-input/Branchebibliotheek-voor-Erik-2nov/branchelijst.csv',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

/** @return list<array<string, mixed>> */
function ce_branche_bibliotheek_catalog_from_csv(?string $query = null, int $limit = 120): array
{
    $path = ce_branche_bibliotheek_catalog_csv_path();
    if ($path === null) {
        return [];
    }
    $limit = max(1, min(120, $limit));
    $q = mb_strtolower(trim((string) ($query ?? '')));
    $out = [];
    foreach (ce_branche_bibliotheek_parse_csv($path) as $row) {
        if ($q !== '') {
            $hay = mb_strtolower($row['branche_label'] . ' ' . $row['afbakening']);
            if (!str_contains($hay, $q)) {
                continue;
            }
        }
        $out[] = $row;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

function ce_branche_bibliotheek_find_catalog_row(PDO $pdo, string $label): ?array
{
    $label = trim($label);
    if ($label === '') {
        return null;
    }
    foreach (ce_branche_bibliotheek_fetch_catalog($pdo, null, 120) as $row) {
        if (strcasecmp((string) ($row['branche_label'] ?? ''), $label) === 0) {
            return $row;
        }
    }

    return null;
}

/** @return list<array<string, mixed>> */
function ce_branche_bibliotheek_fetch_catalog(PDO $pdo, ?string $query = null, int $limit = 120): array
{
    ce_branche_bibliotheek_ensure_catalog_schema($pdo);
    $limit = max(1, min(120, $limit));
    $q = trim((string) ($query ?? ''));

    if (ce_table_exists($pdo, 'branche_catalog')) {
        if ($q === '') {
            $stmt = $pdo->query(
                'SELECT list_nr, branche_label, afbakening, markt, csv_status, json_filename, is_gecureerd, branche_code
                 FROM branche_catalog ORDER BY list_nr ASC LIMIT ' . $limit
            );
            $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        } else {
            $stmt = $pdo->prepare(
                'SELECT list_nr, branche_label, afbakening, markt, csv_status, json_filename, is_gecureerd, branche_code
                 FROM branche_catalog
                 WHERE branche_label LIKE :q OR afbakening LIKE :q2
                 ORDER BY list_nr ASC
                 LIMIT ' . $limit
            );
            $like = '%' . $q . '%';
            $stmt->execute(['q' => $like, 'q2' => $like]);
            $rows = $stmt->fetchAll() ?: [];
        }
        if ($rows !== []) {
            return $rows;
        }
    }

    return ce_branche_bibliotheek_catalog_from_csv($q === '' ? null : $q, $limit);
}

/** @param array<string, mixed> $row */
function ce_branche_bibliotheek_catalog_dto(array $row): array
{
    return [
        'listNr' => (int) ($row['list_nr'] ?? 0),
        'brancheLabel' => (string) ($row['branche_label'] ?? ''),
        'afbakening' => (string) ($row['afbakening'] ?? ''),
        'markt' => (string) ($row['markt'] ?? ''),
        'csvStatus' => (string) ($row['csv_status'] ?? ''),
        'isGecureerd' => (bool) (int) ($row['is_gecureerd'] ?? 0),
        'brancheCode' => ($row['branche_code'] ?? null) !== null && $row['branche_code'] !== ''
            ? (string) $row['branche_code']
            : null,
    ];
}