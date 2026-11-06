<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

function ce_ensure_hba_contentflow_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (ce_table_exists($pdo, 'branche_library')) {
        $brancheColumns = [
            'market_definition' => 'TEXT NULL',
            'audience_scope' => 'VARCHAR(255) NULL',
            'geography' => 'VARCHAR(128) NULL',
            'language' => 'VARCHAR(16) NULL',
            'inclusion_exclusion' => 'TEXT NULL',
            'market_archetype_rationale' => 'TEXT NULL',
            'market_archetype_evidence_ids' => 'JSON NULL',
            'confidence_total' => 'TINYINT NULL',
            'confidence_dimensions' => 'JSON NULL',
            'version' => 'INT NOT NULL DEFAULT 1',
            'last_validated_at' => 'TIMESTAMP NULL',
            'status' => "VARCHAR(32) NOT NULL DEFAULT 'active'",
            'parent_id' => 'CHAR(36) NULL',
        ];
        foreach ($brancheColumns as $column => $definition) {
            if (!ce_table_has_column($pdo, 'branche_library', $column)) {
                try {
                    $pdo->exec("ALTER TABLE branche_library ADD COLUMN `$column` $definition");
                    unset($GLOBALS['ce_table_columns']['branche_library']);
                } catch (Throwable) {
                    // Kolom bestaat al.
                }
            }
        }
    }

    if (!ce_table_exists($pdo, 'hba_branch_evidence')) {
        try {
            $pdo->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS hba_branch_evidence (
    id CHAR(36) NOT NULL PRIMARY KEY,
    branch_id CHAR(36) NOT NULL,
    source VARCHAR(255) NOT NULL,
    url_or_internal_ref VARCHAR(500) NULL,
    observed_claim TEXT NOT NULL,
    observed_at DATE NULL,
    phase TINYINT NOT NULL DEFAULT 2,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_branch_evidence_branch (branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            );
        } catch (Throwable) {
            // Geen CREATE-rechten.
        }
    }

    if (!ce_table_exists($pdo, 'hba_guardrail_objects')) {
        try {
            $pdo->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS hba_guardrail_objects (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    architecture_version INT NOT NULL DEFAULT 1,
    branch_id CHAR(36) NULL,
    branch_version INT NULL,
    decision VARCHAR(16) NOT NULL,
    check_score INT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_guardrail_client (client_id),
    KEY idx_hba_guardrail_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            );
        } catch (Throwable) {
            // Geen CREATE-rechten.
        }
    }

    if (!ce_table_exists($pdo, 'content_rhythms')) {
        try {
            $pdo->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS content_rhythms (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    posts_per_week TINYINT NOT NULL DEFAULT 3,
    roles JSON NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    proposed TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_content_rhythms_client (client_id),
    KEY idx_content_rhythms_active (client_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            );
        } catch (Throwable) {
            // Geen CREATE-rechten.
        }
    }

    if (!ce_table_exists($pdo, 'hba_voice_profiles')) {
        try {
            $pdo->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS hba_voice_profiles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    published_count INT NOT NULL DEFAULT 0,
    recurring_angles JSON NULL,
    typical_formulations JSON NULL,
    typical_invalshoeken JSON NULL,
    analyzed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_voice_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
            );
        } catch (Throwable) {
            // Geen CREATE-rechten.
        }
    }

    if (ce_table_exists($pdo, 'architectuur_check_result')) {
        $checkColumns = [
            'goal_identity_level' => 'VARCHAR(32) NULL',
            'foe_identity_level' => 'VARCHAR(32) NULL',
            'identity_gate_pass' => 'TINYINT(1) NULL',
            'criterion_scores' => 'JSON NULL',
            'total_score' => 'INT NULL',
            'decision' => 'VARCHAR(16) NULL',
            'hard_fail_reason' => 'TEXT NULL',
            'return_action' => 'TEXT NULL',
            'guardrail_id' => 'CHAR(36) NULL',
            'architecture_version' => 'INT NULL',
            'branch_id' => 'CHAR(36) NULL',
            'branch_version' => 'INT NULL',
        ];
        foreach ($checkColumns as $column => $definition) {
            if (!ce_table_has_column($pdo, 'architectuur_check_result', $column)) {
                try {
                    $pdo->exec("ALTER TABLE architectuur_check_result ADD COLUMN `$column` $definition");
                    unset($GLOBALS['ce_table_columns']['architectuur_check_result']);
                } catch (Throwable) {
                    // Kolom bestaat al.
                }
            }
        }
    }

    if (ce_table_exists($pdo, 'sources') && !ce_table_has_column($pdo, 'sources', 'source_tier')) {
        try {
            $pdo->exec("ALTER TABLE sources ADD COLUMN source_tier VARCHAR(20) NOT NULL DEFAULT 'supplementary'");
            unset($GLOBALS['ce_table_columns']['sources']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }

    if (ce_table_exists($pdo, 'content_slots') && !ce_table_has_column($pdo, 'content_slots', 'rhythm_role')) {
        try {
            $pdo->exec('ALTER TABLE content_slots ADD COLUMN rhythm_role VARCHAR(64) NULL');
            unset($GLOBALS['ce_table_columns']['content_slots']);
        } catch (Throwable) {
            // Kolom bestaat al.
        }
    }

    foreach (['hba_theme_verdicts', 'hba_research_verdicts'] as $table) {
        if (!ce_table_exists($pdo, $table)) {
            continue;
        }
        $verdictColumns = [
            'verdict_code' => 'VARCHAR(16) NULL',
            'architecture_version' => 'INT NULL',
            'branch_version' => 'INT NULL',
        ];
        foreach ($verdictColumns as $column => $definition) {
            if (!ce_table_has_column($pdo, $table, $column)) {
                try {
                    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                    unset($GLOBALS['ce_table_columns'][$table]);
                } catch (Throwable) {
                    // Kolom bestaat al.
                }
            }
        }
    }

    foreach (['hba_article_hints', 'hba_scores'] as $table) {
        if (!ce_table_exists($pdo, $table)) {
            continue;
        }
        if (!ce_table_has_column($pdo, $table, 'architecture_version')) {
            try {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN architecture_version INT NULL");
                unset($GLOBALS['ce_table_columns'][$table]);
            } catch (Throwable) {
                // Kolom bestaat al.
            }
        }
    }
}

/** @return array{usable: bool, indicative: bool, blocked: bool, reason: string|null} */
function ce_hba_branch_confidence_use(array $row): array
{
    $marktframe = 0;
    $dims = $row['confidence_dimensions'] ?? [];
    if (is_string($dims)) {
        $decoded = json_decode($dims, true);
        $dims = is_array($decoded) ? $decoded : [];
    }
    if (is_array($dims)) {
        $marktframe = (int) ($dims['marktframe'] ?? $dims['market_frame'] ?? 0);
    }
    $total = (int) ($row['confidence_total'] ?? 0);

    if ($marktframe === 0 && $total > 0) {
        return [
            'usable' => false,
            'indicative' => false,
            'blocked' => true,
            'reason' => 'marktcontext onvoldoende — marktframe-score 0 blokkeert gebruik',
        ];
    }
    if ($total <= 5) {
        return [
            'usable' => false,
            'indicative' => false,
            'blocked' => true,
            'reason' => 'marktcontext onvoldoende — confidence te laag om uniciteit hard te beoordelen',
        ];
    }
    if ($total <= 7) {
        return [
            'usable' => false,
            'indicative' => true,
            'blocked' => false,
            'reason' => 'alleen indicatief signaal — branchelaag is nog niet hard genoeg',
        ];
    }

    return ['usable' => true, 'indicative' => false, 'blocked' => false, 'reason' => null];
}
