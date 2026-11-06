<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/tone-styles.php';
require_once __DIR__ . '/hba-repository.php';

/** Hoofddoel-categorieën (product-vision.md §9) — bepalen welke KPI's in Report vooraan staan. */
const CE_BRAND_GOAL_CATEGORIES = [
    'awareness',
    'leads',
    'sales',
    'recruitment',
    'community',
    'service',
    'thought_leadership',
    'brand_preference',
];

function ce_ensure_strategy_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'clients')) {
        return;
    }

    $columns = [
        'archetype' => 'TEXT NULL',
        'adversary' => 'TEXT NULL',
        'brand_goal' => 'TEXT NULL',
        'brand_goal_category' => 'VARCHAR(32) NULL',
        'writing_guide' => 'TEXT NULL',
        'tone_styles' => 'JSON NULL',
        'editor_styles' => 'JSON NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'clients', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE clients ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['clients']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }

    foreach (['archetype', 'adversary', 'brand_goal', 'writing_guide'] as $column) {
        if (!ce_table_has_column($pdo, 'clients', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE clients MODIFY COLUMN $column TEXT NULL");
        } catch (Throwable) {
            // Al TEXT of geen ALTER-rechten.
        }
    }
}

/** @return list<string> */
function ce_strategy_db_columns(PDO $pdo, string $logical): array
{
    ce_ensure_strategy_columns($pdo);

    $candidates = match ($logical) {
        'archetype' => ['archetype'],
        'adversary' => ['adversary'],
        'brandstory' => ['brand_goal', 'brandstory'],
        'brandGoalCategory' => ['brand_goal_category'],
        'writingGuide' => ['writing_guide', 'tone_of_voice'],
        default => [],
    };

    $cols = [];
    foreach ($candidates as $column) {
        if (ce_table_has_column($pdo, 'clients', $column)) {
            $cols[] = $column;
        }
    }

    return $cols;
}

/** @param list<string> $columns */
function ce_strategy_read_field(array $row, array $columns): string
{
    foreach ($columns as $column) {
        $value = trim((string) ($row[$column] ?? ''));
        if ($value !== '') {
            return (string) ($row[$column] ?? '');
        }
    }

    if ($columns !== []) {
        return (string) ($row[$columns[0]] ?? '');
    }

    return '';
}

/** @return array<string, mixed>|null */
function ce_fetch_client_row(PDO $pdo, ?string $clientId = null): ?array
{
    if (!ce_table_exists($pdo, 'clients')) {
        return null;
    }

    if ($clientId === null || $clientId === '') {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return array<string, string> */
function ce_strategy_dto(PDO $pdo, array $row): array
{
    $clientId = (string) ($row['id'] ?? '');

    return array_merge([
        'clientId' => $clientId,
        'companyName' => (string) ($row['name'] ?? ''),
        'archetype' => ce_strategy_read_field($row, ce_strategy_db_columns($pdo, 'archetype')),
        'adversary' => ce_strategy_read_field($row, ce_strategy_db_columns($pdo, 'adversary')),
        'brandstory' => ce_strategy_read_field($row, ce_strategy_db_columns($pdo, 'brandstory')),
        'brandGoalCategory' => ce_strategy_read_field($row, ce_strategy_db_columns($pdo, 'brandGoalCategory')),
        'writingGuide' => ce_strategy_read_field($row, ce_strategy_db_columns($pdo, 'writingGuide')),
        'toneStyles' => ce_client_tone_styles_for_ui($pdo, $clientId),
        'editorStyles' => ce_client_editor_styles_for_ui($pdo, $clientId),
    ], ce_hba_strategy_extras($pdo, $clientId));
}

/** @return array<string, mixed> */
function ce_fetch_strategy(PDO $pdo): array
{
    ce_ensure_strategy_columns($pdo);
    $row = ce_fetch_client_row($pdo);
    if (!$row) {
        return [
            'strategy' => null,
            'error' => 'Geen client gevonden — voeg een rij toe in clients.',
        ];
    }

    return ['strategy' => ce_strategy_dto($pdo, $row)];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_patch_strategy(PDO $pdo, array $body): array
{
    ce_ensure_strategy_columns($pdo);
    $row = ce_fetch_client_row($pdo);
    if (!$row) {
        throw new RuntimeException('Geen client gevonden');
    }

    $clientId = (string) $row['id'];
    $map = [
        'archetype' => 'archetype',
        'adversary' => 'adversary',
        'brandstory' => 'brandstory',
        'brandGoalCategory' => 'brandGoalCategory',
        'writingGuide' => 'writingGuide',
    ];

    if (array_key_exists('brandGoalCategory', $body)) {
        $category = trim((string) $body['brandGoalCategory']);
        if ($category !== '' && !in_array($category, CE_BRAND_GOAL_CATEGORIES, true)) {
            throw new InvalidArgumentException('Ongeldige brandGoalCategory: ' . $category);
        }
    }

    $sets = [];
    $params = ['id' => $clientId];

    foreach ($map as $jsonKey => $logical) {
        if (!array_key_exists($jsonKey, $body)) {
            continue;
        }

        $columns = ce_strategy_db_columns($pdo, $logical);
        if ($columns === []) {
            continue;
        }

        $value = (string) $body[$jsonKey];
        foreach ($columns as $dbCol) {
            $sets[] = "$dbCol = :$dbCol";
            $params[$dbCol] = $value;
        }
    }

    if ($sets === [] && !array_key_exists('toneStyles', $body) && !array_key_exists('editorStyles', $body) && !array_key_exists('brandNarrative', $body)) {
        throw new InvalidArgumentException('Geen velden om bij te werken');
    }

    if (array_key_exists('toneStyles', $body) && is_array($body['toneStyles'])) {
        ce_ensure_tone_styles_column($pdo);
        if (ce_table_has_column($pdo, 'clients', 'tone_styles')) {
            /** @var array<string, mixed> $toneUi */
            $toneUi = $body['toneStyles'];
            $normalized = [];
            foreach ($toneUi as $label => $text) {
                $label = trim((string) $label);
                if ($label === '') {
                    continue;
                }
                $normalized[$label] = trim((string) $text);
            }
            $encoded = ce_encode_tone_styles_for_db($normalized);
            $sets[] = 'tone_styles = :tone_styles';
            $params['tone_styles'] = $encoded;
        }
    }

    if (array_key_exists('editorStyles', $body) && is_array($body['editorStyles'])) {
        ce_ensure_editor_styles_column($pdo);
        if (ce_table_has_column($pdo, 'clients', 'editor_styles')) {
            /** @var array<string, mixed> $editorUi */
            $editorUi = $body['editorStyles'];
            $normalized = [];
            foreach ($editorUi as $label => $text) {
                $label = trim((string) $label);
                if ($label === '') {
                    continue;
                }
                $normalized[$label] = trim((string) $text);
            }
            $encoded = ce_encode_tone_styles_for_db($normalized);
            $sets[] = 'editor_styles = :editor_styles';
            $params['editor_styles'] = $encoded;
        }
    }

    if (array_key_exists('brandNarrative', $body) && is_array($body['brandNarrative'])) {
        if ($sets !== []) {
            $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
            $sets = [];
            $params = ['id' => $clientId];
        }
        $narrative = ce_brand_narrative_from_request($body['brandNarrative']);
        ce_save_brand_narrative($pdo, $clientId, $narrative, $clientId);
    }

    if ($sets === [] && !array_key_exists('brandNarrative', $body)) {
        throw new InvalidArgumentException('Geen velden om bij te werken');
    }

    if ($sets === [] && array_key_exists('brandNarrative', $body)) {
        $updated = ce_fetch_client_row($pdo, $clientId);

        return ['strategy' => ce_strategy_dto($pdo, $updated ?: $row)];
    }

    if ($sets === []) {
        throw new InvalidArgumentException('Geen velden om bij te werken');
    }

    $pdo->prepare('UPDATE clients SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    $updated = ce_fetch_client_row($pdo, $clientId);

    return ['strategy' => ce_strategy_dto($pdo, $updated ?: $row)];
}
