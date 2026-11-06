-- ContentFlow HBA-laag (briefing 30-08-2026): branch_record, evidence, guardrail, ritme, stem.
-- Idempotent: CREATE IF NOT EXISTS + ADD COLUMN IF NOT EXISTS (MariaDB).

ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS market_definition TEXT NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS audience_scope VARCHAR(255) NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS geography VARCHAR(128) NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS language VARCHAR(16) NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS inclusion_exclusion TEXT NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS market_archetype_rationale TEXT NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS market_archetype_evidence_ids JSON NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS confidence_total TINYINT NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS confidence_dimensions JSON NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS last_validated_at TIMESTAMP NULL;
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS status VARCHAR(32) NOT NULL DEFAULT 'active';
ALTER TABLE branche_library ADD COLUMN IF NOT EXISTS parent_id CHAR(36) NULL;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hba_voice_profiles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    published_count INT NOT NULL DEFAULT 0,
    recurring_angles JSON NULL,
    typical_formulations JSON NULL,
    typical_invalshoeken JSON NULL,
    analyzed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_voice_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS goal_identity_level VARCHAR(32) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS foe_identity_level VARCHAR(32) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS identity_gate_pass TINYINT(1) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS criterion_scores JSON NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS total_score INT NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS decision VARCHAR(16) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS hard_fail_reason TEXT NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS return_action TEXT NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS guardrail_id CHAR(36) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS architecture_version INT NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS branch_id CHAR(36) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS branch_version INT NULL;

ALTER TABLE sources ADD COLUMN IF NOT EXISTS source_tier VARCHAR(20) NOT NULL DEFAULT 'supplementary';

ALTER TABLE content_slots ADD COLUMN IF NOT EXISTS rhythm_role VARCHAR(64) NULL;

ALTER TABLE hba_theme_verdicts ADD COLUMN IF NOT EXISTS verdict_code VARCHAR(16) NULL;
ALTER TABLE hba_theme_verdicts ADD COLUMN IF NOT EXISTS architecture_version INT NULL;
ALTER TABLE hba_theme_verdicts ADD COLUMN IF NOT EXISTS branch_version INT NULL;
ALTER TABLE hba_research_verdicts ADD COLUMN IF NOT EXISTS verdict_code VARCHAR(16) NULL;
ALTER TABLE hba_research_verdicts ADD COLUMN IF NOT EXISTS architecture_version INT NULL;
ALTER TABLE hba_research_verdicts ADD COLUMN IF NOT EXISTS branch_version INT NULL;
