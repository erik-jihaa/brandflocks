<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';

const CE_OBJECTIVES_MAX = 6;

const CE_BRAND_ARCHITECTURE_OBJECTIVE_LABEL = 'Merkdoel (merkarchitectuur)';

/**
 * Tekstveld "Doel" / merkverhaal onder Strategy (Wie je bent) — leidend voor schrijfprompts sinds stap 1a.
 */
function ce_client_brand_architecture_goal_text(PDO $pdo, string $clientId): string
{
    $clientId = trim($clientId);
    if ($clientId === '' || !ce_table_exists($pdo, 'clients')) {
        return '';
    }
    ce_ensure_strategy_columns($pdo);
    if (!ce_table_has_column($pdo, 'clients', 'brand_goal')) {
        return '';
    }
    $stmt = $pdo->prepare('SELECT brand_goal FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $raw = $stmt->fetchColumn();

    return trim(is_string($raw) ? $raw : '');
}

/**
 * @return array{id: string, clientId: string, label: string, description: string, percentage: int, sortOrder: int, writingStyle: string, sourceFolderId: null, _source: string}
 */
function ce_brand_architecture_objective_dto(PDO $pdo, string $clientId): ?array
{
    $goal = ce_client_brand_architecture_goal_text($pdo, $clientId);
    if ($goal === '') {
        return null;
    }
    ce_ensure_strategy_columns($pdo);
    $writingStyle = 'zakelijk_direct';
    if (ce_table_has_column($pdo, 'clients', 'writing_style')) {
        $stmt = $pdo->prepare('SELECT writing_style FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => trim($clientId)]);
        $ws = $stmt->fetchColumn();
        $writingStyle = trim(is_string($ws) ? $ws : '') ?: 'zakelijk_direct';
    }

    return [
        'id' => '',
        'clientId' => trim($clientId),
        'label' => CE_BRAND_ARCHITECTURE_OBJECTIVE_LABEL,
        'description' => $goal,
        'percentage' => 0,
        'sortOrder' => 0,
        'writingStyle' => $writingStyle,
        'sourceFolderId' => null,
        '_source' => 'brand_architecture',
    ];
}

function ce_ensure_objectives_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'objectives')) {
        try {
            $pdo->exec(
                "CREATE TABLE objectives (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    client_id CHAR(36) NOT NULL,
                    label VARCHAR(255) NOT NULL,
                    description TEXT,
                    percentage TINYINT NOT NULL DEFAULT 0,
                    sort_order TINYINT NOT NULL DEFAULT 0,
                    writing_style VARCHAR(100) NULL DEFAULT 'zakelijk_direct',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_objectives_client (client_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Tabel bestaat al of geen rechten.
        }
    }

    if (ce_table_exists($pdo, 'objectives') && !ce_table_has_column($pdo, 'objectives', 'writing_style')) {
        try {
            $pdo->exec("ALTER TABLE objectives ADD COLUMN writing_style VARCHAR(100) NULL DEFAULT 'zakelijk_direct'");
            unset($GLOBALS['ce_table_columns']['objectives']);
        } catch (Throwable) {
        }
    }

    if (ce_table_exists($pdo, 'objectives') && !ce_table_has_column($pdo, 'objectives', 'source_folder_id')) {
        try {
            $pdo->exec('ALTER TABLE objectives ADD COLUMN source_folder_id CHAR(36) NULL');
            unset($GLOBALS['ce_table_columns']['objectives']);
        } catch (Throwable) {
        }
    }

    if (ce_table_exists($pdo, 'clients')) {
        foreach ([
            'google_place_id' => 'VARCHAR(255) NULL',
            'writing_style' => "VARCHAR(100) NULL DEFAULT 'zakelijk_direct'",
        ] as $column => $definition) {
            if (!ce_table_has_column($pdo, 'clients', $column)) {
                try {
                    $pdo->exec("ALTER TABLE clients ADD COLUMN $column $definition");
                    unset($GLOBALS['ce_table_columns']['clients']);
                } catch (Throwable) {
                }
            }
        }
    }

    foreach ([
        'themes' => 'objective_id CHAR(36) NULL',
        'content_slots' => 'objective_id CHAR(36) NULL',
        'articles' => 'objective_suggested CHAR(36) NULL',
    ] as $table => $definition) {
        if (ce_table_exists($pdo, $table) && !ce_table_has_column($pdo, $table, explode(' ', $definition)[0])) {
            try {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $definition");
                unset($GLOBALS['ce_table_columns'][$table]);
            } catch (Throwable) {
            }
        }
    }

    if (ce_table_exists($pdo, 'articles') && !ce_table_has_column($pdo, 'articles', 'objective_final')) {
        try {
            $pdo->exec('ALTER TABLE articles ADD COLUMN objective_final CHAR(36) NULL');
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
        }
    }
}

/** Valideer source map-id voor objective (null = geen koppeling). */
function ce_objective_normalize_source_folder_id(PDO $pdo, string $clientId, mixed $folderId): ?string
{
    require_once __DIR__ . '/source-folders-repository.php';

    $folderId = trim((string) ($folderId ?? ''));
    if ($folderId === '') {
        return null;
    }

    ce_ensure_source_folders_schema($pdo);
    if (!ce_table_exists($pdo, 'source_folders')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM source_folders WHERE id = :id AND client_id = :client_id LIMIT 1'
    );
    $stmt->execute(['id' => $folderId, 'client_id' => $clientId]);
    if (!$stmt->fetchColumn()) {
        throw new InvalidArgumentException('Sources-map hoort niet bij deze klant.');
    }

    return $folderId;
}

/** @return array<string, mixed> */
function ce_objective_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'clientId' => (string) ($row['client_id'] ?? ''),
        'label' => (string) ($row['label'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'percentage' => (int) ($row['percentage'] ?? 0),
        'sortOrder' => (int) ($row['sort_order'] ?? 0),
        'writingStyle' => trim((string) ($row['writing_style'] ?? 'zakelijk_direct')) ?: 'zakelijk_direct',
        'sourceFolderId' => trim((string) ($row['source_folder_id'] ?? '')) ?: null,
    ];
}

/** @return list<array<string, mixed>> */
function ce_fetch_objectives(PDO $pdo, ?string $clientId = null): array
{
    ce_ensure_objectives_schema($pdo);
    if (!ce_table_exists($pdo, 'objectives')) {
        return [];
    }

    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM objectives WHERE client_id = :client_id ORDER BY sort_order ASC, created_at ASC'
    );
    $stmt->execute(['client_id' => $clientId]);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = ce_objective_dto($row);
    }

    return $out;
}

/** @return array{objectives: list<array<string, mixed>>, percentageTotal: int, percentageValid: bool} */
function ce_fetch_objectives_payload(PDO $pdo, ?string $clientId = null): array
{
    $objectives = ce_fetch_objectives($pdo, $clientId);
    $total = 0;
    foreach ($objectives as $objective) {
        $total += (int) ($objective['percentage'] ?? 0);
    }

    return [
        'objectives' => $objectives,
        'percentageTotal' => $total,
        'percentageValid' => count($objectives) === 0 || $total === 100,
    ];
}

/** @return array<string, mixed>|null */
function ce_fetch_objective_by_id(PDO $pdo, string $objectiveId, ?string $clientId = null): ?array
{
    ce_ensure_objectives_schema($pdo);
    $objectiveId = trim($objectiveId);
    if ($objectiveId === '' || !ce_table_exists($pdo, 'objectives')) {
        return null;
    }

    if ($clientId !== null && $clientId !== '') {
        $stmt = $pdo->prepare(
            'SELECT * FROM objectives WHERE id = :id AND client_id = :client_id LIMIT 1'
        );
        $stmt->execute(['id' => $objectiveId, 'client_id' => $clientId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM objectives WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $objectiveId]);
    }

    $row = $stmt->fetch();
    return $row ? ce_objective_dto($row) : null;
}

/** Objective-id van slot of gekoppeld theme (fallback vóór article final/suggested). */
function ce_slot_theme_objective_id(PDO $pdo, string $slotId): string
{
    if ($slotId === '' || !ce_table_exists($pdo, 'content_slots')) {
        return '';
    }

    $hasSlotObj = ce_table_has_column($pdo, 'content_slots', 'objective_id');
    $hasThemeObj = ce_table_exists($pdo, 'themes') && ce_table_has_column($pdo, 'themes', 'objective_id');

    if ($hasSlotObj && $hasThemeObj) {
        $stmt = $pdo->prepare(
            'SELECT s.objective_id AS slot_objective_id, t.objective_id AS theme_objective_id
             FROM content_slots s
             LEFT JOIN themes t ON t.id = s.theme_id
             WHERE s.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $slotId]);
        $row = $stmt->fetch() ?: [];
        $slotObj = trim((string) ($row['slot_objective_id'] ?? ''));
        if ($slotObj !== '') {
            return $slotObj;
        }

        return trim((string) ($row['theme_objective_id'] ?? ''));
    }

    if ($hasSlotObj) {
        $stmt = $pdo->prepare('SELECT objective_id FROM content_slots WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $slotId]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    return '';
}

/**
 * Gekozen objective voor artikel-slot (final → suggested → slot/theme).
 *
 * @return array<string, mixed>|null
 */
function ce_resolve_article_objective(PDO $pdo, array $articleRow): ?array
{
    ce_ensure_objectives_schema($pdo);
    $clientId = trim((string) ($articleRow['client_id'] ?? ''));
    $final = ce_table_has_column($pdo, 'articles', 'objective_final')
        ? trim((string) ($articleRow['objective_final'] ?? ''))
        : '';
    $suggested = ce_table_has_column($pdo, 'articles', 'objective_suggested')
        ? trim((string) ($articleRow['objective_suggested'] ?? ''))
        : '';
    $slotId = trim((string) ($articleRow['slot_id'] ?? ''));
    $inherited = $slotId !== '' ? ce_slot_theme_objective_id($pdo, $slotId) : '';

    foreach ([$final, $suggested, $inherited] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $objective = ce_fetch_objective_by_id($pdo, $candidate, $clientId !== '' ? $clientId : null);
        if ($objective !== null) {
            return $objective;
        }
    }

    return null;
}

/**
 * Doel voor schrijven: merkarchitectuur (clients.brand_goal) eerst; anders oud objectives-record.
 *
 * @return array<string, mixed>|null
 */
function ce_resolve_article_objective_for_writing(PDO $pdo, array $articleRow): ?array
{
    $clientId = trim((string) ($articleRow['client_id'] ?? ''));
    $fromBrand = $clientId !== '' ? ce_brand_architecture_objective_dto($pdo, $clientId) : null;
    if ($fromBrand !== null) {
        return $fromBrand;
    }

    return ce_resolve_article_objective($pdo, $articleRow);
}

/**
 * Regels voor write_directive / CE-03 — doelstelling + prompt-instructie.
 *
 * @param array<string, mixed>|null $objective
 * @return list<string>
 */
function ce_objective_write_directive_lines(?array $objective, string $styleLine = ''): array
{
    if ($objective === null) {
        return [];
    }

    $label = trim((string) ($objective['label'] ?? ''));
    $description = trim((string) ($objective['description'] ?? ''));
    $percentage = (int) ($objective['percentage'] ?? 0);
    $writingStyle = trim((string) ($objective['writingStyle'] ?? ''));
    $isBrandArch = (($objective['_source'] ?? '') === 'brand_architecture') || ($label === CE_BRAND_ARCHITECTURE_OBJECTIVE_LABEL);

    $lines = [];
    if ($isBrandArch) {
        if ($description !== '') {
            $lines[] = 'MERKDOEL (merkarchitectuur — wie je bent): ' . $description;
        } elseif ($label !== '') {
            $lines[] = 'MERKDOEL (merkarchitectuur — wie je bent): ' . $label;
        }
        if ($description !== '' || $label !== '') {
            $lines[] = 'Richt rationale, boodschap en CTA op dit merkdoel en de merkarchitectuur.';
        }
    } else {
        if ($label !== '') {
            $pct = $percentage > 0 ? " ({$percentage}%)" : '';
            $lines[] = 'DOELSTELLING VAN DEZE POST: ' . $label . $pct . '.';
        }
        if ($description !== '') {
            $lines[] = $description;
        }
        if ($label !== '' || $description !== '') {
            $lines[] = 'Richt rationale, boodschap en CTA expliciet op deze doelstelling.';
        }
    }
    if ($writingStyle !== '') {
        $lines[] = 'Schrijfstijl bij deze doelstelling: ' . $writingStyle . '.';
        if ($styleLine !== '') {
            $lines[] = $styleLine;
        }
    }

    return $lines;
}

/**
 * Article meta: gekozen objective + opties uit Targets.
 *
 * @return array{objective: string|null, objectiveSuggested: string|null, objectiveOptions: list<array{id: string, label: string, percentage: int}>}
 */
function ce_article_objective_payload(PDO $pdo, array $row): array
{
    ce_ensure_objectives_schema($pdo);

    $clientId = trim((string) ($row['client_id'] ?? ''));
    $objectives = $clientId !== '' ? ce_fetch_objectives($pdo, $clientId) : [];
    $options = [];
    foreach ($objectives as $objective) {
        $id = trim((string) ($objective['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $options[] = [
            'id' => $id,
            'label' => (string) ($objective['label'] ?? ''),
            'percentage' => (int) ($objective['percentage'] ?? 0),
        ];
    }

    if ($options === []) {
        return [
            'objective' => null,
            'objectiveSuggested' => null,
            'objectiveOptions' => [],
        ];
    }

    $final = ce_table_has_column($pdo, 'articles', 'objective_final')
        ? trim((string) ($row['objective_final'] ?? ''))
        : '';
    $suggested = ce_table_has_column($pdo, 'articles', 'objective_suggested')
        ? trim((string) ($row['objective_suggested'] ?? ''))
        : '';
    $slotId = trim((string) ($row['slot_id'] ?? ''));
    $inherited = $slotId !== '' ? ce_slot_theme_objective_id($pdo, $slotId) : '';

    $selected = $final !== '' ? $final : ($suggested !== '' ? $suggested : $inherited);
    $validIds = array_column($options, 'id');
    if ($selected !== '' && !in_array($selected, $validIds, true)) {
        $selected = '';
    }
    if ($suggested !== '' && !in_array($suggested, $validIds, true)) {
        $suggested = '';
    }

    return [
        'objective' => $selected !== '' ? $selected : null,
        'objectiveSuggested' => $suggested !== '' ? $suggested : null,
        'objectiveOptions' => $options,
    ];
}

/** Zet articles.objective_final (+ slot.objective_id) na redacteur-keuze. */
function ce_set_article_objective(PDO $pdo, string $articleId, string $slotId, string $objectiveId, string $clientId): void
{
    ce_ensure_objectives_schema($pdo);
    $objectiveId = trim($objectiveId);
    if ($objectiveId === '') {
        return;
    }

    $check = $pdo->prepare(
        'SELECT id FROM objectives WHERE id = :id AND client_id = :client_id LIMIT 1'
    );
    $check->execute(['id' => $objectiveId, 'client_id' => $clientId]);
    if (!$check->fetchColumn()) {
        throw new InvalidArgumentException('Objective hoort niet bij deze klant.');
    }

    if (ce_table_has_column($pdo, 'articles', 'objective_final')) {
        $pdo->prepare('UPDATE articles SET objective_final = :objective_id WHERE id = :id')
            ->execute(['objective_id' => $objectiveId, 'id' => $articleId]);
    }

    if ($slotId !== '' && ce_table_exists($pdo, 'content_slots')
        && ce_table_has_column($pdo, 'content_slots', 'objective_id')) {
        $pdo->prepare('UPDATE content_slots SET objective_id = :objective_id WHERE id = :id')
            ->execute(['objective_id' => $objectiveId, 'id' => $slotId]);
    }
}

function ce_objectives_validate_percentages(array $objectives, ?string $excludeId = null): void
{
    $total = 0;
    foreach ($objectives as $objective) {
        if ($excludeId !== null && ($objective['id'] ?? '') === $excludeId) {
            continue;
        }
        $total += max(0, min(100, (int) ($objective['percentage'] ?? 0)));
    }
    if ($total !== 100) {
        throw new InvalidArgumentException("Percentages moeten optellen tot 100 (nu $total%).");
    }
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_create_objective(PDO $pdo, array $body): array
{
    ce_ensure_objectives_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $existing = ce_fetch_objectives($pdo, $clientId);
    if (count($existing) >= CE_OBJECTIVES_MAX) {
        throw new InvalidArgumentException('Maximaal ' . CE_OBJECTIVES_MAX . ' objectives per klant.');
    }

    $label = trim((string) ($body['label'] ?? ''));
    if ($label === '') {
        throw new InvalidArgumentException('label is verplicht');
    }

    $percentage = max(0, min(100, (int) ($body['percentage'] ?? 0)));
    $description = trim((string) ($body['description'] ?? ''));
    $writingStyle = trim((string) ($body['writingStyle'] ?? $body['writing_style'] ?? 'zakelijk_direct')) ?: 'zakelijk_direct';
    $sourceFolderId = ce_objective_normalize_source_folder_id(
        $pdo,
        $clientId,
        $body['sourceFolderId'] ?? $body['source_folder_id'] ?? null
    );
    $sortOrder = array_key_exists('sortOrder', $body)
        ? (int) $body['sortOrder']
        : (array_key_exists('sort_order', $body) ? (int) $body['sort_order'] : count($existing));

    $id = ce_uuid_v4();
    $stmt = $pdo->prepare(
        'INSERT INTO objectives (id, client_id, label, description, percentage, sort_order, writing_style, source_folder_id)
         VALUES (:id, :client_id, :label, :description, :percentage, :sort_order, :writing_style, :source_folder_id)'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'label' => $label,
        'description' => $description !== '' ? $description : null,
        'percentage' => $percentage,
        'sort_order' => max(0, $sortOrder),
        'writing_style' => $writingStyle,
        'source_folder_id' => $sourceFolderId,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM objectives WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Objective aanmaken mislukt');
    }

    return ['objective' => ce_objective_dto($row)];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_patch_objective(PDO $pdo, string $objectiveId, array $body): array
{
    ce_ensure_objectives_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $stmt = $pdo->prepare('SELECT * FROM objectives WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $objectiveId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Objective niet gevonden');
    }

    $sets = [];
    $params = ['id' => $objectiveId, 'client_id' => $clientId];

    if (array_key_exists('label', $body)) {
        $label = trim((string) $body['label']);
        if ($label === '') {
            throw new InvalidArgumentException('label mag niet leeg zijn');
        }
        $sets[] = 'label = :label';
        $params['label'] = $label;
    }

    if (array_key_exists('description', $body)) {
        $sets[] = 'description = :description';
        $params['description'] = trim((string) $body['description']) ?: null;
    }

    if (array_key_exists('percentage', $body)) {
        $sets[] = 'percentage = :percentage';
        $params['percentage'] = max(0, min(100, (int) $body['percentage']));
    }

    if (array_key_exists('sortOrder', $body) || array_key_exists('sort_order', $body)) {
        $sets[] = 'sort_order = :sort_order';
        $params['sort_order'] = max(0, (int) ($body['sortOrder'] ?? $body['sort_order'] ?? 0));
    }

    if (array_key_exists('writingStyle', $body) || array_key_exists('writing_style', $body)) {
        $sets[] = 'writing_style = :writing_style';
        $params['writing_style'] = trim((string) ($body['writingStyle'] ?? $body['writing_style'] ?? '')) ?: 'zakelijk_direct';
    }

    if (array_key_exists('sourceFolderId', $body) || array_key_exists('source_folder_id', $body)) {
        $sets[] = 'source_folder_id = :source_folder_id';
        $params['source_folder_id'] = ce_objective_normalize_source_folder_id(
            $pdo,
            $clientId,
            $body['sourceFolderId'] ?? $body['source_folder_id'] ?? null
        );
    }

    if ($sets !== []) {
        $pdo->prepare(
            'UPDATE objectives SET ' . implode(', ', $sets) . ' WHERE id = :id AND client_id = :client_id'
        )->execute($params);
    }

    $stmt = $pdo->prepare('SELECT * FROM objectives WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $objectiveId]);
    $updated = $stmt->fetch();

    return ['objective' => ce_objective_dto($updated ?: $row)];
}

function ce_delete_objective(PDO $pdo, string $objectiveId): array
{
    ce_ensure_objectives_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $stmt = $pdo->prepare('SELECT id FROM objectives WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $objectiveId, 'client_id' => $clientId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Objective niet gevonden');
    }

    foreach ([
        ['themes', 'objective_id'],
        ['content_slots', 'objective_id'],
        ['articles', 'objective_suggested'],
        ['articles', 'objective_final'],
    ] as [$table, $column]) {
        if (!ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, $column)) {
            continue;
        }
        $check = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = :id");
        $check->execute(['id' => $objectiveId]);
        if ((int) $check->fetchColumn() > 0) {
            throw new InvalidArgumentException('Objective is gekoppeld aan content — verwijderen niet mogelijk.');
        }
    }

    $pdo->prepare('DELETE FROM objectives WHERE id = :id AND client_id = :client_id')
        ->execute(['id' => $objectiveId, 'client_id' => $clientId]);

    return ['ok' => true];
}

/** @param list<array{id: string, sortOrder?: int}> $order */
function ce_reorder_objectives(PDO $pdo, array $order): array
{
    ce_ensure_objectives_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $stmt = $pdo->prepare(
        'UPDATE objectives SET sort_order = :sort_order WHERE id = :id AND client_id = :client_id'
    );
    $index = 0;
    foreach ($order as $entry) {
        $id = trim((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $sort = array_key_exists('sortOrder', $entry) ? (int) $entry['sortOrder'] : $index;
        $stmt->execute([
            'sort_order' => max(0, $sort),
            'id' => $id,
            'client_id' => $clientId,
        ]);
        $index++;
    }

    return ce_fetch_objectives_payload($pdo, $clientId);
}

/**
 * Vervang alle objectives voor de huidige client (bulk save vanuit Targets UI).
 *
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function ce_save_objectives(PDO $pdo, array $items): array
{
    ce_ensure_objectives_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    if (count($items) > CE_OBJECTIVES_MAX) {
        throw new InvalidArgumentException('Maximaal ' . CE_OBJECTIVES_MAX . ' objectives per klant.');
    }

    $normalized = [];
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $normalized[] = [
            'id' => trim((string) ($item['id'] ?? '')),
            'label' => $label,
            'description' => trim((string) ($item['description'] ?? '')),
            'percentage' => max(0, min(100, (int) ($item['percentage'] ?? 0))),
            'sortOrder' => array_key_exists('sortOrder', $item)
                ? (int) $item['sortOrder']
                : $index,
            'writingStyle' => trim((string) ($item['writingStyle'] ?? $item['writing_style'] ?? 'zakelijk_direct')) ?: 'zakelijk_direct',
            'sourceFolderId' => ce_objective_normalize_source_folder_id(
                $pdo,
                $clientId,
                $item['sourceFolderId'] ?? $item['source_folder_id'] ?? null
            ),
        ];
    }

    if ($normalized !== []) {
        $total = 0;
        foreach ($normalized as $entry) {
            $total += (int) $entry['percentage'];
        }
        if ($total > 100) {
            throw new InvalidArgumentException("Percentages mogen niet boven 100 uitkomen (nu $total%).");
        }
    }

    $existing = ce_fetch_objectives($pdo, $clientId);
    $existingIds = array_map(static fn (array $row): string => (string) $row['id'], $existing);
    $keepIds = [];

    $pdo->beginTransaction();
    try {
        foreach ($normalized as $entry) {
            $id = $entry['id'];
            if ($id === '' || str_starts_with($id, 'new-')) {
                $id = ce_uuid_v4();
                $pdo->prepare(
                    'INSERT INTO objectives (id, client_id, label, description, percentage, sort_order, writing_style, source_folder_id)
                     VALUES (:id, :client_id, :label, :description, :percentage, :sort_order, :writing_style, :source_folder_id)'
                )->execute([
                    'id' => $id,
                    'client_id' => $clientId,
                    'label' => $entry['label'],
                    'description' => $entry['description'] !== '' ? $entry['description'] : null,
                    'percentage' => $entry['percentage'],
                    'sort_order' => $entry['sortOrder'],
                    'writing_style' => $entry['writingStyle'],
                    'source_folder_id' => $entry['sourceFolderId'],
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE objectives
                     SET label = :label, description = :description, percentage = :percentage,
                         sort_order = :sort_order, writing_style = :writing_style,
                         source_folder_id = :source_folder_id
                     WHERE id = :id AND client_id = :client_id'
                )->execute([
                    'id' => $id,
                    'client_id' => $clientId,
                    'label' => $entry['label'],
                    'description' => $entry['description'] !== '' ? $entry['description'] : null,
                    'percentage' => $entry['percentage'],
                    'sort_order' => $entry['sortOrder'],
                    'writing_style' => $entry['writingStyle'],
                    'source_folder_id' => $entry['sourceFolderId'],
                ]);
            }
            $keepIds[] = $id;
        }

        foreach ($existingIds as $existingId) {
            if (in_array($existingId, $keepIds, true)) {
                continue;
            }
            ce_delete_objective($pdo, $existingId);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ce_fetch_objectives_payload($pdo, $clientId);
}
