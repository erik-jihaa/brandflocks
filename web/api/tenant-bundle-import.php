<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/publications-repository.php';

/** @param list<array<string, mixed>> $rows */
function ce_bundle_ids(array $rows): array
{
    return array_values(array_filter(array_map(
        static fn ($row) => is_array($row) ? (string) ($row['id'] ?? '') : '',
        $rows,
    )));
}

/** @param array<string, mixed> $row */
function ce_import_replace(PDO $pdo, string $table, array $row): void
{
    if (!ce_table_exists($pdo, $table) || $row === []) {
        return;
    }
    $allowed = [];
    foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
        $name = (string) ($col['Field'] ?? '');
        if ($name !== '') {
            $allowed[$name] = true;
        }
    }
    $filtered = [];
    foreach ($row as $col => $value) {
        if (isset($allowed[$col])) {
            $filtered[$col] = $value;
        }
    }
    if ($filtered === []) {
        return;
    }
    $cols = array_keys($filtered);
    $colSql = implode(', ', array_map(static fn ($c) => '`' . str_replace('`', '``', $c) . '`', $cols));
    $ph = implode(', ', array_map(static fn ($c) => ':' . $c, $cols));
    $stmt = $pdo->prepare("REPLACE INTO `{$table}` ({$colSql}) VALUES ({$ph})");
    $params = [];
    foreach ($cols as $col) {
        $params[$col] = $filtered[$col];
    }
    $stmt->execute($params);
}

/** @param list<string> $sections */
function ce_import_tenant_bundle_sections(PDO $pdo, string $clientId, array $bundle, array $sections): array
{
    $clientId = trim($clientId);
    if ($clientId === '') {
        throw new InvalidArgumentException('clientId is verplicht');
    }

    $check = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
    $check->execute(['id' => $clientId]);
    if (!$check->fetch()) {
        throw new RuntimeException('Client niet gevonden');
    }

    $sections = array_values(array_unique(array_map(
        static fn ($s) => strtolower(trim((string) $s)),
        $sections,
    )));
    $allowed = ['agenda', 'findability', 'hba'];
    foreach ($sections as $section) {
        if (!in_array($section, $allowed, true)) {
            throw new InvalidArgumentException('Onbekende sectie: ' . $section);
        }
    }
    if ($sections === []) {
        throw new InvalidArgumentException('Minstens één sectie verplicht (agenda, findability)');
    }

    $tables = is_array($bundle['tables'] ?? null) ? $bundle['tables'] : [];
    $related = is_array($bundle['related'] ?? null) ? $bundle['related'] : [];
    $applied = [];

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    try {
        if (in_array('agenda', $sections, true)) {
            $pipeline = ce_reset_client_pipeline_content($pdo, $clientId);
            foreach (['themes', 'campaigns'] as $table) {
                foreach ($tables[$table] ?? [] as $row) {
                    if (is_array($row)) {
                        ce_import_replace($pdo, $table, $row);
                    }
                }
            }
            $campaignIds = array_fill_keys(array_values(array_filter(array_map(
                static fn ($row) => is_array($row) ? (string) ($row['id'] ?? '') : '',
                $tables['campaigns'] ?? [],
            ))), true);
            foreach ($tables['content_slots'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $campaignId = (string) ($row['campaign_id'] ?? '');
                if ($campaignId !== '' && !isset($campaignIds[$campaignId])) {
                    $row['campaign_id'] = null;
                }
                ce_import_replace($pdo, 'content_slots', $row);
            }
            foreach ($bundle['articles'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'articles', $row);
                }
            }
            $seenResearch = [];
            foreach ($related['research_items'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || isset($seenResearch[$id])) {
                    continue;
                }
                $seenResearch[$id] = true;
                ce_import_replace($pdo, 'research_items', $row);
            }
            foreach ($related['hba_scores'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'hba_scores', $row);
                }
            }
            foreach ($related['publications'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'publications', $row);
                }
            }
            if (ce_table_exists($pdo, 'linkedin_posts') && ce_table_has_column($pdo, 'linkedin_posts', 'client_id')) {
                $pdo->prepare('DELETE FROM linkedin_posts WHERE client_id = :id')->execute(['id' => $clientId]);
            }
            foreach ($related['linkedin_posts'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'linkedin_posts', $row);
                }
            }
            $applied['agenda'] = [
                'pipelineCleared' => $pipeline,
                'themes' => count($tables['themes'] ?? []),
                'campaigns' => count($tables['campaigns'] ?? []),
                'contentSlots' => count($tables['content_slots'] ?? []),
                'articles' => count(is_array($bundle['articles'] ?? null) ? $bundle['articles'] : []),
                'researchItems' => count($seenResearch),
                'publications' => count($related['publications'] ?? []),
                'linkedinPosts' => count($related['linkedin_posts'] ?? []),
            ];
        }

        if (in_array('hba', $sections, true)) {
            foreach (['hba_theme_verdicts', 'hba_research_verdicts'] as $table) {
                if (ce_table_exists($pdo, $table) && ce_table_has_column($pdo, $table, 'client_id')) {
                    $pdo->prepare("DELETE FROM `{$table}` WHERE client_id = :id")->execute(['id' => $clientId]);
                }
            }
            foreach ($related['hba_theme_verdicts'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'hba_theme_verdicts', $row);
                }
            }
            foreach ($related['hba_research_verdicts'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'hba_research_verdicts', $row);
                }
            }
            $guardrailRows = array_values(array_filter(
                $related['hba_guardrail_objects'] ?? [],
                static fn ($row) => is_array($row),
            ));
            if (
                $guardrailRows !== []
                && ce_table_exists($pdo, 'hba_guardrail_objects')
                && ce_table_has_column($pdo, 'hba_guardrail_objects', 'client_id')
            ) {
                $pdo->prepare('DELETE FROM hba_guardrail_objects WHERE client_id = :id')->execute(['id' => $clientId]);
                foreach ($guardrailRows as $row) {
                    ce_import_replace($pdo, 'hba_guardrail_objects', $row);
                }
            }
            $applied['hba'] = [
                'themeVerdicts' => count($related['hba_theme_verdicts'] ?? []),
                'researchVerdicts' => count($related['hba_research_verdicts'] ?? []),
                'guardrails' => count($related['hba_guardrail_objects'] ?? []),
            ];
        }

        if (in_array('findability', $sections, true)) {
            if (ce_table_exists($pdo, 'llm_visibility_checks') && ce_table_has_column($pdo, 'llm_visibility_checks', 'client_id')) {
                $pdo->prepare('DELETE FROM llm_visibility_checks WHERE client_id = :id')->execute(['id' => $clientId]);
            }
            if (ce_table_exists($pdo, 'llm_probleemvragen') && ce_table_has_column($pdo, 'llm_probleemvragen', 'client_id')) {
                $pdo->prepare('DELETE FROM llm_probleemvragen WHERE client_id = :id')->execute(['id' => $clientId]);
            }
            foreach ($related['llm_probleemvragen'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'llm_probleemvragen', $row);
                }
            }
            foreach ($related['llm_visibility_checks'] ?? [] as $row) {
                if (is_array($row)) {
                    ce_import_replace($pdo, 'llm_visibility_checks', $row);
                }
            }
            $applied['findability'] = [
                'llmQuestions' => count($related['llm_probleemvragen'] ?? []),
                'llmChecks' => count($related['llm_visibility_checks'] ?? []),
            ];
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    return $applied;
}
