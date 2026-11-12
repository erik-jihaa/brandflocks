-- Brandflocks — consolidated database baseline for new installs (Ronde F)
-- Derived from CE production export CE-Cursor/docs/schema.sql (2026-09-03)
-- Changes vs CE: Asana removed from `sources` (enum + columns); same app code as ronde D.
-- Apply before first deploy, then run only SQL files in web/migrations/*.sql (not archive/).


SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `architectuur_check_result`;
CREATE TABLE `architectuur_check_result` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `kracht_triangle_oordeel` varchar(32) DEFAULT NULL,
  `kracht_triangle_toelichting` text DEFAULT NULL,
  `kracht_triangle_verbetering` text DEFAULT NULL,
  `relevantie_tegenkracht_oordeel` varchar(32) DEFAULT NULL,
  `relevantie_tegenkracht_toelichting` text DEFAULT NULL,
  `relevantie_tegenkracht_verbetering` text DEFAULT NULL,
  `relevantie_rol_oordeel` varchar(32) DEFAULT NULL,
  `relevantie_rol_toelichting` text DEFAULT NULL,
  `relevantie_rol_verbetering` text DEFAULT NULL,
  `scherpte_uniciteit_oordeel` varchar(32) DEFAULT NULL,
  `scherpte_uniciteit_toelichting` text DEFAULT NULL,
  `scherpte_uniciteit_verbetering` text DEFAULT NULL,
  `strawman_gedetecteerd` tinyint(1) DEFAULT NULL,
  `zit_in_hygienefactoren` tinyint(1) DEFAULT NULL,
  `regels_oordeel` varchar(32) DEFAULT NULL,
  `regels_toelichting` text DEFAULT NULL,
  `regels_verbetering` text DEFAULT NULL,
  `totaaloordeel` varchar(64) DEFAULT NULL,
  `premium_laag_aangeraden` tinyint(1) NOT NULL DEFAULT 0,
  `toon_suggesties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`toon_suggesties`)),
  `voorbeeld_suggesties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`voorbeeld_suggesties`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `goal_identity_level` varchar(32) DEFAULT NULL,
  `foe_identity_level` varchar(32) DEFAULT NULL,
  `identity_gate_pass` tinyint(1) DEFAULT NULL,
  `criterion_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criterion_scores`)),
  `total_score` int(11) DEFAULT NULL,
  `decision` varchar(16) DEFAULT NULL,
  `hard_fail_reason` text DEFAULT NULL,
  `return_action` text DEFAULT NULL,
  `guardrail_id` char(36) DEFAULT NULL,
  `architecture_version` int(11) DEFAULT NULL,
  `branch_id` char(36) DEFAULT NULL,
  `branch_version` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_architectuur_check_client` (`client_id`),
  KEY `idx_architectuur_check_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `article_feedback`;
CREATE TABLE `article_feedback` (
  `id` char(36) NOT NULL,
  `slot_id` char(36) NOT NULL,
  `article_id` char(36) NOT NULL,
  `reviewer_name` varchar(100) NOT NULL DEFAULT 'Review',
  `feedback_type` varchar(20) NOT NULL DEFAULT 'text',
  `comment` text NOT NULL,
  `action_label` varchar(100) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_slot` (`slot_id`),
  KEY `idx_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

DROP TABLE IF EXISTS `article_review_edits`;
CREATE TABLE `article_review_edits` (
  `id` char(36) NOT NULL,
  `article_id` char(36) NOT NULL,
  `slot_id` char(36) DEFAULT NULL,
  `original_text` text DEFAULT NULL,
  `suggested_text` text DEFAULT NULL,
  `position_start` int(11) DEFAULT NULL,
  `position_end` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `progress_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_article_review_edits_article` (`article_id`),
  CONSTRAINT `fk_article_review_edits_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `articles`;
CREATE TABLE `articles` (
  `id` char(36) NOT NULL,
  `slot_id` char(36) NOT NULL,
  `rationale` text DEFAULT NULL,
  `headline` varchar(500) DEFAULT NULL,
  `headline_human` varchar(500) DEFAULT NULL,
  `content_ai` text DEFAULT NULL,
  `content_human` mediumtext DEFAULT NULL,
  `content_edited` text DEFAULT NULL,
  `tone_suggested` varchar(50) DEFAULT NULL,
  `tone_final` varchar(50) DEFAULT NULL,
  `angle_suggested` varchar(50) DEFAULT NULL,
  `angle_final` varchar(50) DEFAULT NULL,
  `image_ratio_suggested` varchar(50) DEFAULT NULL,
  `image_ratio_final` varchar(50) DEFAULT NULL,
  `platform_suggested` varchar(50) DEFAULT NULL,
  `platform_final` varchar(50) DEFAULT NULL,
  `hashtags_suggested` varchar(500) DEFAULT NULL,
  `self_review` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`self_review`)),
  `hashtags_final` varchar(500) DEFAULT NULL,
  `image_url_square` varchar(500) DEFAULT NULL,
  `image_url_landscape` varchar(500) DEFAULT NULL,
  `status` enum('draft','pending_review','approved','feedback_received','ready','published','failed','archived','written','pending','proposed','submitted','review','feedback') NOT NULL DEFAULT 'draft',
  `review_sent_at` timestamp NULL DEFAULT NULL,
  `review_token` varchar(255) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `social_post_id` varchar(255) DEFAULT NULL,
  `sponsored` tinyint(1) NOT NULL DEFAULT 0,
  `promo_budget` decimal(10,2) DEFAULT NULL,
  `interaction_views` int(11) DEFAULT 0,
  `interaction_likes` int(11) DEFAULT 0,
  `interaction_comments` int(11) DEFAULT 0,
  `interaction_traffic_site` int(11) DEFAULT 0,
  `archive_notes` text DEFAULT NULL,
  `archive_note` text DEFAULT NULL,
  `social_post_url` varchar(512) DEFAULT NULL,
  `write_directive` text DEFAULT NULL,
  `publish_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`publish_urls`)),
  `objective_suggested` char(36) DEFAULT NULL,
  `objective_final` char(36) DEFAULT NULL,
  `image_advice` varchar(1000) DEFAULT NULL,
  `auto_published` tinyint(1) NOT NULL DEFAULT 0,
  `auto_publish_error` varchar(500) DEFAULT NULL,
  `editor_suggested` varchar(100) DEFAULT NULL,
  `editor_final` varchar(100) DEFAULT NULL,
  `hba_contentrol_override` varchar(32) DEFAULT NULL,
  `poster_headline` text DEFAULT NULL,
  `poster_style_used` varchar(20) DEFAULT NULL,
  `image_source` varchar(20) DEFAULT NULL,
  `poster_word_main` varchar(20) DEFAULT NULL,
  `poster_word_struck` varchar(20) DEFAULT NULL,
  `editorial_status` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_articles_slot` (`slot_id`),
  CONSTRAINT `fk_articles_slot` FOREIGN KEY (`slot_id`) REFERENCES `content_slots` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `branche_library`;
CREATE TABLE `branche_library` (
  `id` char(36) NOT NULL,
  `branche_code` varchar(64) NOT NULL,
  `branche_label` varchar(255) NOT NULL,
  `markt_archetype` varchar(32) DEFAULT NULL,
  `hygienefactoren` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`hygienefactoren`)),
  `categorietaal` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`categorietaal`)),
  `bron` enum('afgeleid','gecureerd') NOT NULL DEFAULT 'afgeleid',
  `gecureerd_door` char(36) DEFAULT NULL,
  `laatst_bijgewerkt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branche_definitie` text DEFAULT NULL,
  `bronnen_kwaliteit` varchar(16) DEFAULT NULL,
  `vakcontext` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vakcontext`)),
  `differentiatie_assen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`differentiatie_assen`)),
  `waar_onderscheid_zit` text DEFAULT NULL,
  `market_definition` text DEFAULT NULL,
  `audience_scope` varchar(255) DEFAULT NULL,
  `geography` varchar(128) DEFAULT NULL,
  `language` varchar(16) DEFAULT NULL,
  `inclusion_exclusion` text DEFAULT NULL,
  `market_archetype_rationale` text DEFAULT NULL,
  `market_archetype_evidence_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`market_archetype_evidence_ids`)),
  `confidence_total` tinyint(4) DEFAULT NULL,
  `confidence_dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`confidence_dimensions`)),
  `version` int(11) NOT NULL DEFAULT 1,
  `last_validated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `parent_id` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branche_code` (`branche_code`),
  KEY `idx_branche_label` (`branche_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `brand_narratives`;
CREATE TABLE `brand_narratives` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `archetype` varchar(32) NOT NULL,
  `doel` text NOT NULL,
  `foe` text NOT NULL,
  `spanning` text NOT NULL,
  `mechanisme` text NOT NULL,
  `geloofwaardigheidstest` text NOT NULL,
  `verboden_logica` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`verboden_logica`)),
  `bewijsvelden` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`bewijsvelden`)),
  `tone_of_behaviour` text NOT NULL,
  `woorden_die_passen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`woorden_die_passen`)),
  `woorden_die_niet_passen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`woorden_die_niet_passen`)),
  `goede_voorbeelden` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`goede_voorbeelden`)),
  `zwakke_voorbeelden` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`zwakke_voorbeelden`)),
  `contextprofiel` varchar(32) NOT NULL,
  `default_platforms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`default_platforms`)),
  `target_post_volume_per_month` int(11) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` char(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `distinctiviteit` text DEFAULT NULL,
  `branche_code` varchar(64) DEFAULT NULL,
  `wat_bewust_niet` text DEFAULT NULL,
  `identity_intro` text DEFAULT NULL,
  `kernformuleringen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`kernformuleringen`)),
  `gedragsregels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gedragsregels`)),
  PRIMARY KEY (`id`),
  KEY `idx_brand_narratives_client` (`client_id`),
  KEY `idx_brand_narratives_current` (`client_id`,`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `calibration_reviews`;
CREATE TABLE `calibration_reviews` (
  `id` char(36) NOT NULL,
  `score_id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `human_reviewer_id` char(36) NOT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `human_contentrol` varchar(32) DEFAULT NULL,
  `human_brand_contribution_score` decimal(3,1) DEFAULT NULL,
  `human_brand_consistency_score` decimal(3,1) DEFAULT NULL,
  `human_architecture_risk_score` decimal(3,1) DEFAULT NULL,
  `human_long_term_value` decimal(3,1) DEFAULT NULL,
  `human_short_term_value` decimal(3,1) DEFAULT NULL,
  `human_risk_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`human_risk_flags`)),
  `disagreement_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`disagreement_fields`)),
  `disagreement_reason` text DEFAULT NULL,
  `correction_applied` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`correction_applied`)),
  `calibration_status` varchar(32) NOT NULL,
  `prompt_version` varchar(16) NOT NULL,
  `formula_version` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_calibration_reviews_score` (`score_id`),
  KEY `idx_calibration_reviews_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `campaigns`;
CREATE TABLE `campaigns` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `title` varchar(500) NOT NULL,
  `theme` text NOT NULL,
  `target_audience` text DEFAULT NULL,
  `post_count` int(11) NOT NULL DEFAULT 4,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `generated_plan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`generated_plan`)),
  `status` enum('active','completed','archived') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_campaigns_client` (`client_id`),
  CONSTRAINT `fk_campaigns_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ce_sessions`;
CREATE TABLE `ce_sessions` (
  `id` varchar(128) NOT NULL,
  `data` blob NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ce_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

DROP TABLE IF EXISTS `client_alert_settings`;
CREATE TABLE `client_alert_settings` (
  `client_id` char(36) NOT NULL,
  `alert_sensitivity` varchar(16) NOT NULL DEFAULT 'alert',
  `updated_by` char(36) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `client_images`;
CREATE TABLE `client_images` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `filename` varchar(500) NOT NULL,
  `url` varchar(500) NOT NULL,
  `alt_text` varchar(500) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_images_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `archetype` text DEFAULT NULL,
  `archetype_traits` text DEFAULT NULL,
  `adversary` text DEFAULT NULL,
  `brand_goal` text DEFAULT NULL,
  `phases` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`phases`)),
  `tone_of_voice` text DEFAULT NULL,
  `writing_guide` text DEFAULT NULL,
  `authors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`authors`)),
  `default_tone` varchar(50) DEFAULT NULL,
  `default_angle` varchar(50) DEFAULT NULL,
  `default_image_ratio` varchar(50) DEFAULT NULL,
  `default_platform` varchar(50) DEFAULT NULL,
  `image_required` tinyint(1) NOT NULL DEFAULT 0,
  `platform_publish_mode` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`platform_publish_mode`)),
  `wordpress_site_url` varchar(255) DEFAULT NULL,
  `wordpress_credential_ref` varchar(255) DEFAULT NULL,
  `max_research_items_per_theme` int(11) DEFAULT 10,
  `posts_per_week` int(11) DEFAULT 2,
  `planning_horizon_weeks` int(11) DEFAULT 4,
  `contact_role` enum('contact','reviewer','both') NOT NULL DEFAULT 'both',
  `contact_email` varchar(255) NOT NULL,
  `reviewer_email` varchar(255) DEFAULT NULL,
  `linkedin_credential_ref` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contact_name` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `publish_mode` enum('manual_with_review','manual_without_review','automatic') NOT NULL DEFAULT 'manual_with_review',
  `image_database_url` varchar(500) DEFAULT NULL,
  `image_database_password` varchar(500) DEFAULT NULL,
  `hba_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `website_blog_url` varchar(500) DEFAULT NULL,
  `wordpress_user` varchar(255) DEFAULT NULL,
  `wordpress_app_password` varchar(500) DEFAULT NULL,
  `wordpress_author_id` int(11) DEFAULT NULL,
  `site_ftp_host` varchar(255) DEFAULT NULL,
  `site_ftp_port` int(11) DEFAULT 21,
  `site_ftp_user` varchar(255) DEFAULT NULL,
  `site_ftp_password` varchar(500) DEFAULT NULL,
  `site_ftp_remote_path` varchar(500) DEFAULT NULL,
  `publish_extra_destinations` text DEFAULT NULL,
  `linkedin_oauth_access_token` text DEFAULT NULL,
  `linkedin_oauth_refresh_token` text DEFAULT NULL,
  `linkedin_oauth_access_expires_at` datetime DEFAULT NULL,
  `linkedin_oauth_refresh_expires_at` datetime DEFAULT NULL,
  `linkedin_member_urn` varchar(120) DEFAULT NULL,
  `linkedin_member_name` varchar(255) DEFAULT NULL,
  `linkedin_oauth_scopes` text DEFAULT NULL,
  `tone_styles` text DEFAULT NULL,
  `writing_style` varchar(100) DEFAULT 'zakelijk_direct',
  `google_place_id` varchar(255) DEFAULT NULL,
  `source_checks_per_week` int(11) NOT NULL DEFAULT 1,
  `brand_goal_category` varchar(32) DEFAULT NULL,
  `editor_styles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`editor_styles`)),
  `joomla_site_url` varchar(500) DEFAULT NULL,
  `joomla_api_token` varchar(500) DEFAULT NULL,
  `joomla_category_id` int(11) DEFAULT NULL,
  `website_cms` varchar(20) DEFAULT NULL,
  `hba_architecture_completed_at` timestamp NULL DEFAULT NULL,
  `hba_pbs_revealed_at` timestamp NULL DEFAULT NULL,
  `brand_color_bg` varchar(7) DEFAULT NULL,
  `brand_color_text` varchar(7) DEFAULT NULL,
  `brand_color_accent` varchar(7) DEFAULT NULL,
  `brand_font_family` varchar(100) DEFAULT NULL,
  `poster_style` varchar(20) DEFAULT NULL,
  `hba_baseline_status` varchar(24) DEFAULT NULL,
  `hba_baseline_completed_at` timestamp NULL DEFAULT NULL,
  `hba_baseline_post_count` int(11) DEFAULT NULL,
  `hba_baseline_narrative_id` char(36) DEFAULT NULL,
  `hba_baseline_prompt_version` varchar(16) DEFAULT NULL,
  `hba_determinism_status` varchar(16) DEFAULT NULL,
  `hba_determinism_measured_at` timestamp NULL DEFAULT NULL,
  `hba_determinism_report` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hba_determinism_report`)),
  `hba_score_display_mode` varchar(16) NOT NULL DEFAULT 'band',
  `hba_score_averaging` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `content_rhythms`;
CREATE TABLE `content_rhythms` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `posts_per_week` tinyint(4) NOT NULL DEFAULT 3,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `proposed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_content_rhythms_client` (`client_id`),
  KEY `idx_content_rhythms_active` (`client_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `content_slots`;
CREATE TABLE `content_slots` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `theme_id` char(36) DEFAULT NULL,
  `cycle_month` date DEFAULT NULL,
  `phase_key` varchar(100) DEFAULT NULL,
  `theme` varchar(500) DEFAULT NULL,
  `goal` text DEFAULT NULL,
  `search_terms` varchar(500) DEFAULT NULL,
  `author_email` varchar(255) DEFAULT NULL,
  `publish_date` date DEFAULT NULL,
  `input_human` text DEFAULT NULL,
  `origin` enum('normal','fastlane') NOT NULL DEFAULT 'normal',
  `status` enum('proposed','approved','feedback','edited','skipped','researching','written','archived','themed','research_done','draft','pending','pending_review','submitted','review','feedback_received','published') NOT NULL DEFAULT 'proposed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `campaign_id` char(36) DEFAULT NULL,
  `objective_id` char(36) DEFAULT NULL,
  `rhythm_role` varchar(64) DEFAULT NULL,
  `publish_time` time DEFAULT NULL,
  `research_grounded_candidates` int(11) DEFAULT NULL,
  `research_insufficient_sources` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_content_slots_client` (`client_id`),
  KEY `fk_content_slots_theme` (`theme_id`),
  KEY `fk_slots_campaign` (`campaign_id`),
  CONSTRAINT `fk_content_slots_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_content_slots_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`),
  CONSTRAINT `fk_slots_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `editorial_preferences`;
CREATE TABLE `editorial_preferences` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `category` enum('source_preference','theme_pattern','tone_pattern','angle_pattern','engagement_pattern','review_pattern','site_traffic_pattern') NOT NULL,
  `statement` text NOT NULL,
  `confidence` decimal(4,3) DEFAULT NULL,
  `based_on_count` int(11) DEFAULT NULL,
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_editorial_preferences_client` (`client_id`),
  CONSTRAINT `fk_editorial_preferences_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `erosion_alerts`;
CREATE TABLE `erosion_alerts` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `detected_at` timestamp NOT NULL,
  `alert_type` varchar(32) NOT NULL,
  `severity` varchar(16) NOT NULL,
  `detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detail`)),
  `message` text NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_erosion_alerts_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `event_log`;
CREATE TABLE `event_log` (
  `id` char(36) NOT NULL,
  `client_id` char(36) DEFAULT NULL,
  `severity` varchar(10) NOT NULL,
  `category` varchar(50) NOT NULL,
  `event` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `environment` varchar(20) NOT NULL,
  `notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_created` (`client_id`,`created_at`),
  KEY `idx_severity_created` (`severity`,`created_at`),
  KEY `idx_category` (`category`),
  KEY `idx_event_client` (`event`,`client_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_article_hints`;
CREATE TABLE `hba_article_hints` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `article_id` char(36) NOT NULL,
  `aanwijzing` text NOT NULL,
  `passage_voor` text DEFAULT NULL,
  `passage_na` text DEFAULT NULL,
  `headline_voor` text DEFAULT NULL,
  `headline_na` text DEFAULT NULL,
  `cta_voor` text DEFAULT NULL,
  `cta_na` text DEFAULT NULL,
  `regel_overtreden` text DEFAULT NULL,
  `toegepast` tinyint(1) NOT NULL DEFAULT 0,
  `judged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `model` varchar(64) DEFAULT NULL,
  `genegeerd` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_hba_article_hints_client` (`client_id`),
  KEY `idx_hba_article_hints_article` (`article_id`),
  KEY `idx_hba_article_hints_judged` (`judged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_branch_evidence`;
CREATE TABLE `hba_branch_evidence` (
  `id` char(36) NOT NULL,
  `branch_id` char(36) NOT NULL,
  `source` varchar(255) NOT NULL,
  `url_or_internal_ref` varchar(500) DEFAULT NULL,
  `observed_claim` text NOT NULL,
  `observed_at` date DEFAULT NULL,
  `phase` tinyint(4) NOT NULL DEFAULT 2,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hba_branch_evidence_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_guardrail_objects`;
CREATE TABLE `hba_guardrail_objects` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `architecture_version` int(11) NOT NULL DEFAULT 1,
  `branch_id` char(36) DEFAULT NULL,
  `branch_version` int(11) DEFAULT NULL,
  `decision` varchar(16) NOT NULL,
  `check_score` int(11) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hba_guardrail_client` (`client_id`),
  KEY `idx_hba_guardrail_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_research_verdicts`;
CREATE TABLE `hba_research_verdicts` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `slot_id` char(36) NOT NULL,
  `research_item_id` char(36) NOT NULL,
  `label` enum('versterkt','neutraal','verwatert') NOT NULL,
  `toelichting` text NOT NULL,
  `wat_ontbreekt` text DEFAULT NULL,
  `judged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `model` varchar(64) DEFAULT NULL,
  `verdict_code` varchar(16) DEFAULT NULL,
  `architecture_version` int(11) DEFAULT NULL,
  `branch_version` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hba_research_verdicts_client` (`client_id`),
  KEY `idx_hba_research_verdicts_slot` (`slot_id`),
  KEY `idx_hba_research_verdicts_item` (`research_item_id`),
  KEY `idx_hba_research_verdicts_judged` (`judged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_scores`;
CREATE TABLE `hba_scores` (
  `id` char(36) NOT NULL,
  `post_id` char(36) NOT NULL,
  `brand_narrative_id` char(36) NOT NULL,
  `scored_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(32) NOT NULL,
  `contentrol` varchar(32) DEFAULT NULL,
  `contentrol_motivation` text DEFAULT NULL,
  `bc_archetype_fit` decimal(3,1) DEFAULT NULL,
  `bc_doel_fit` decimal(3,1) DEFAULT NULL,
  `bc_tegenkracht_fit` decimal(3,1) DEFAULT NULL,
  `bc_spanning` decimal(3,1) DEFAULT NULL,
  `bc_mechanisme_proof` decimal(3,1) DEFAULT NULL,
  `bc_distinctiviteit` decimal(3,1) DEFAULT NULL,
  `brand_contribution_score` decimal(3,1) DEFAULT NULL,
  `brand_consistency_score` decimal(3,1) DEFAULT NULL,
  `brand_consistency_notes` text DEFAULT NULL,
  `architecture_risk_score` decimal(3,1) DEFAULT NULL,
  `architecture_risk_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`architecture_risk_flags`)),
  `long_term_value` decimal(3,1) DEFAULT NULL,
  `short_term_value` decimal(3,1) DEFAULT NULL,
  `content_weight` decimal(4,3) NOT NULL DEFAULT 1.000,
  `content_weight_method_version` varchar(16) NOT NULL DEFAULT 'cw_v1_1',
  `diagnose` text DEFAULT NULL,
  `verbeteradvies` text DEFAULT NULL,
  `raw_llm_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_llm_response`)),
  `input_tokens` int(11) DEFAULT NULL,
  `output_tokens` int(11) DEFAULT NULL,
  `model_used` varchar(64) NOT NULL,
  `prompt_version` varchar(16) NOT NULL,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `kernformulering_hit` tinyint(4) DEFAULT NULL,
  `hygiene_list_version` int(11) DEFAULT NULL,
  `origin` varchar(16) NOT NULL DEFAULT 'app',
  `linkedin_post_id` char(36) DEFAULT NULL,
  `gedragsregels_compliance` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gedragsregels_compliance`)),
  PRIMARY KEY (`id`),
  KEY `idx_hba_scores_post` (`post_id`),
  KEY `idx_hba_scores_narrative` (`brand_narrative_id`),
  KEY `idx_hba_scores_scored_at` (`scored_at`),
  KEY `idx_hba_scores_origin` (`origin`),
  KEY `idx_hba_scores_linkedin_post` (`linkedin_post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_theme_verdicts`;
CREATE TABLE `hba_theme_verdicts` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `theme_id` char(36) NOT NULL,
  `label` enum('versterkt','neutraal','verwatert') NOT NULL,
  `toelichting` text NOT NULL,
  `wat_ontbreekt` text DEFAULT NULL,
  `judged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `model` varchar(64) DEFAULT NULL,
  `verdict_code` varchar(16) DEFAULT NULL,
  `architecture_version` int(11) DEFAULT NULL,
  `branch_version` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hba_theme_verdicts_client` (`client_id`),
  KEY `idx_hba_theme_verdicts_theme` (`theme_id`),
  KEY `idx_hba_theme_verdicts_judged` (`judged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hba_voice_profiles`;
CREATE TABLE `hba_voice_profiles` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `published_count` int(11) NOT NULL DEFAULT 0,
  `recurring_angles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recurring_angles`)),
  `typical_formulations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`typical_formulations`)),
  `typical_invalshoeken` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`typical_invalshoeken`)),
  `analyzed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hba_voice_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hygiene_competitors`;
CREATE TABLE `hygiene_competitors` (
  `id` char(36) NOT NULL,
  `list_id` char(36) NOT NULL,
  `name` varchar(128) NOT NULL,
  `base_url` varchar(512) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_hygiene_competitor_list` (`list_id`),
  CONSTRAINT `fk_hygiene_competitor_list` FOREIGN KEY (`list_id`) REFERENCES `hygiene_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hygiene_factors`;
CREATE TABLE `hygiene_factors` (
  `id` char(36) NOT NULL,
  `list_id` char(36) NOT NULL,
  `phrase` text NOT NULL,
  `kind` varchar(24) NOT NULL,
  `sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sources`)),
  `added_by` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `review_status` varchar(16) NOT NULL DEFAULT 'approved',
  `competitor_count` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_hygiene_factor_list` (`list_id`),
  CONSTRAINT `fk_hygiene_factor_list` FOREIGN KEY (`list_id`) REFERENCES `hygiene_lists` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hygiene_lists`;
CREATE TABLE `hygiene_lists` (
  `id` char(36) NOT NULL,
  `contextprofiel` varchar(64) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hygiene_profile_version` (`contextprofiel`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `hygiene_scrape_pages`;
CREATE TABLE `hygiene_scrape_pages` (
  `id` char(36) NOT NULL,
  `competitor_id` char(36) NOT NULL,
  `page_type` varchar(32) NOT NULL,
  `url` varchar(512) NOT NULL,
  `text_content` mediumtext DEFAULT NULL,
  `http_status` smallint(6) DEFAULT NULL,
  `scraped_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_hygiene_page_competitor` (`competitor_id`),
  CONSTRAINT `fk_hygiene_page_competitor` FOREIGN KEY (`competitor_id`) REFERENCES `hygiene_competitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `linkedin_posts`;
CREATE TABLE `linkedin_posts` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `source_id` char(36) NOT NULL,
  `activity_urn` varchar(32) NOT NULL,
  `post_url` varchar(512) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `text_preview` text DEFAULT NULL,
  `reactions` int(11) NOT NULL DEFAULT 0,
  `likes` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `reposts` int(11) NOT NULL DEFAULT 0,
  `article_id` char(36) DEFAULT NULL,
  `scraped_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_activity` (`client_id`,`activity_urn`),
  KEY `idx_client_posted` (`client_id`,`posted_at`),
  KEY `idx_source` (`source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `llm_probleemvragen`;
CREATE TABLE `llm_probleemvragen` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `question` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `question_kind` varchar(10) NOT NULL DEFAULT 'geo',
  PRIMARY KEY (`id`),
  KEY `idx_llm_vragen_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `llm_visibility_checks`;
CREATE TABLE `llm_visibility_checks` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `question_id` char(36) NOT NULL,
  `model` varchar(50) NOT NULL,
  `mentioned` tinyint(1) NOT NULL,
  `raw_response` text DEFAULT NULL,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_llm_check_client` (`client_id`),
  KEY `idx_llm_check_question` (`question_id`),
  KEY `idx_llm_check_checked_at` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `newsletter_items`;
CREATE TABLE `newsletter_items` (
  `id` char(36) NOT NULL,
  `source_id` char(36) NOT NULL,
  `sender` varchar(255) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `body_text` text DEFAULT NULL,
  `client_id` char(36) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_newsletter_items_source` (`source_id`),
  CONSTRAINT `fk_newsletter_items_source` FOREIGN KEY (`source_id`) REFERENCES `sources` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `objectives`;
CREATE TABLE `objectives` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `label` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `percentage` tinyint(4) NOT NULL DEFAULT 0,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `writing_style` varchar(100) DEFAULT 'zakelijk_direct',
  `source_folder_id` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_objectives_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_client` (`client_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

DROP TABLE IF EXISTS `performance_reports`;
CREATE TABLE `performance_reports` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `scope` varchar(20) NOT NULL DEFAULT 'general',
  `scope_article_id` char(36) DEFAULT NULL,
  `pct_on_strategy` decimal(5,2) DEFAULT NULL,
  `pct_general` decimal(5,2) DEFAULT NULL,
  `tone_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tone_distribution`)),
  `execution_rate` decimal(5,2) DEFAULT NULL,
  `engagement_on_strategy` decimal(10,2) DEFAULT NULL,
  `engagement_general` decimal(10,2) DEFAULT NULL,
  `engagement_diff_pct` decimal(6,2) DEFAULT NULL,
  `article_count_strategy` int(11) DEFAULT NULL,
  `article_count_general` int(11) DEFAULT NULL,
  `article_count_total` int(11) DEFAULT NULL,
  `median_on_strategy` decimal(10,2) DEFAULT NULL,
  `median_general` decimal(10,2) DEFAULT NULL,
  `stddev_on_strategy` decimal(10,2) DEFAULT NULL,
  `stddev_general` decimal(10,2) DEFAULT NULL,
  `outlier_posts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`outlier_posts`)),
  `best_performing_post` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`best_performing_post`)),
  `daily_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`daily_breakdown`)),
  `traffic_publish_weeks` decimal(10,2) DEFAULT NULL,
  `traffic_baseline` decimal(10,2) DEFAULT NULL,
  `narrative` text DEFAULT NULL,
  `pdf_url` varchar(500) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `angle_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`angle_distribution`)),
  `report_title` varchar(255) DEFAULT NULL,
  `findability_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`findability_snapshot`)),
  PRIMARY KEY (`id`),
  KEY `fk_performance_reports_client` (`client_id`),
  KEY `fk_performance_reports_article` (`scope_article_id`),
  CONSTRAINT `fk_performance_reports_article` FOREIGN KEY (`scope_article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `fk_performance_reports_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `platform_password_reset_tokens`;
CREATE TABLE `platform_password_reset_tokens` (
  `id` char(36) NOT NULL,
  `platform_user_id` char(36) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_platform_reset_token_hash` (`token_hash`),
  KEY `idx_platform_reset_user` (`platform_user_id`),
  KEY `idx_platform_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `platform_users`;
CREATE TABLE `platform_users` (
  `id` char(36) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `role` varchar(20) NOT NULL DEFAULT 'support',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_platform_users_email` (`email`),
  KEY `idx_platform_users_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `portfolio_scores`;
CREATE TABLE `portfolio_scores` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `tracker_snapshot_id` char(36) NOT NULL,
  `scored_at` timestamp NOT NULL,
  `reliability` varchar(16) NOT NULL,
  `targetmix_fit_score` decimal(5,2) NOT NULL,
  `story_triangle_coverage_score` decimal(5,2) NOT NULL,
  `proof_constraint_balance_score` decimal(5,2) NOT NULL,
  `architecture_risk_avg` decimal(3,1) NOT NULL,
  `architecture_risk_deduction` decimal(5,2) NOT NULL,
  `content_weight_avg` decimal(4,3) NOT NULL DEFAULT 1.000,
  `portfolio_balance_score` decimal(5,2) NOT NULL,
  `narrative` text DEFAULT NULL,
  `formula_version` varchar(16) NOT NULL DEFAULT 'pbs_v1_1',
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_scores_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `publications`;
CREATE TABLE `publications` (
  `id` char(36) NOT NULL,
  `article_id` char(36) NOT NULL,
  `platform` varchar(32) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'gepland',
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `external_id` varchar(128) DEFAULT NULL,
  `external_url` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_publication_article_platform` (`article_id`,`platform`),
  KEY `idx_publication_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `research_items`;
CREATE TABLE `research_items` (
  `id` char(36) NOT NULL,
  `theme_id` char(36) DEFAULT NULL,
  `slot_id` char(36) DEFAULT NULL,
  `source_id` char(36) DEFAULT NULL,
  `source_type` varchar(50) DEFAULT 'rss',
  `title` varchar(500) DEFAULT NULL,
  `snippet` text DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `engagement_likes` int(11) DEFAULT NULL,
  `engagement_comments` int(11) DEFAULT NULL,
  `item_rationale` text DEFAULT NULL,
  `feedback` enum('none','liked','forgotten') NOT NULL DEFAULT 'none',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `invalshoek` varchar(20) DEFAULT NULL,
  `stance` enum('bevestigt','nuanceert','spreekt_tegen') DEFAULT NULL,
  `reliability` enum('low','medium','high') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_research_items_theme` (`theme_id`),
  KEY `fk_research_items_slot` (`slot_id`),
  KEY `fk_research_items_source` (`source_id`),
  CONSTRAINT `fk_research_items_slot` FOREIGN KEY (`slot_id`) REFERENCES `content_slots` (`id`),
  CONSTRAINT `fk_research_items_source` FOREIGN KEY (`source_id`) REFERENCES `sources` (`id`),
  CONSTRAINT `fk_research_items_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
  `filename` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scrape_runs`;
CREATE TABLE `scrape_runs` (
  `id` char(36) NOT NULL,
  `source_id` char(36) NOT NULL,
  `run_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `units_requested` int(11) DEFAULT NULL,
  `units_returned` int(11) DEFAULT NULL,
  `estimated_cost` decimal(10,4) DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'ok',
  `client_id` char(36) DEFAULT NULL,
  `theme_id` char(36) DEFAULT NULL,
  `slot_id` char(36) DEFAULT NULL,
  `units_used` decimal(10,4) DEFAULT NULL,
  `cost_eur` decimal(10,4) DEFAULT NULL,
  `items_count` int(11) DEFAULT NULL,
  `apify_run_id` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_scrape_runs_source` (`source_id`),
  CONSTRAINT `fk_scrape_runs_source` FOREIGN KEY (`source_id`) REFERENCES `sources` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `service_tickets`;
CREATE TABLE `service_tickets` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `note` text DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `external_message_id` varchar(512) DEFAULT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'manual',
  `created_by_platform_user_id` char(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_service_tickets_external_msg` (`external_message_id`(191)),
  KEY `idx_service_tickets_client` (`client_id`,`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `source_document_chunks`;
CREATE TABLE `source_document_chunks` (
  `id` char(36) NOT NULL,
  `source_id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `title` varchar(500) NOT NULL,
  `snippet` mediumtext DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_source_document_source` (`source_id`),
  KEY `idx_source_document_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `source_folders`;
CREATE TABLE `source_folders` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_sort` (`client_id`,`sort_order`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

DROP TABLE IF EXISTS `sources`;
CREATE TABLE `sources` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `category` enum('url_rss','scraper','mailbox','analytics','trello','linkedin','calendar','blogs','document','youtube','drive') NOT NULL,
  `source_kind` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `scraper_api_token` varchar(255) DEFAULT NULL,
  `scraper_actor_or_task_id` varchar(255) DEFAULT NULL,
  `scraper_webhook_url` varchar(500) DEFAULT NULL,
  `budget_per_unit` decimal(10,4) DEFAULT NULL,
  `max_units_per_run` int(11) DEFAULT NULL,
  `mailbox_email` varchar(255) DEFAULT NULL,
  `mailbox_password_ref` varchar(512) DEFAULT NULL,
  `analytics_api_secret_ref` varchar(512) DEFAULT NULL,
  `analytics_datastream_id` varchar(255) DEFAULT NULL,
  `trello_board_id` varchar(255) DEFAULT NULL,
  `trello_api_token_ref` varchar(512) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `url_status` enum('unchecked','reachable','unreachable') NOT NULL DEFAULT 'unchecked',
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scraper_api_token_ref` varchar(512) DEFAULT NULL,
  `analytics_oauth_refresh_token` text DEFAULT NULL,
  `analytics_oauth_email` varchar(255) DEFAULT NULL,
  `analytics_property_id` varchar(100) DEFAULT NULL,
  `scraper_linkedin_profile` varchar(500) DEFAULT NULL,
  `scraper_linkedin_password_ref` varchar(512) DEFAULT NULL,
  `mailbox_imap_host` varchar(255) DEFAULT NULL,
  `calendar_oauth_refresh_token` text DEFAULT NULL,
  `calendar_oauth_email` varchar(255) DEFAULT NULL,
  `calendar_id` varchar(255) DEFAULT NULL,
  `calendar_name` varchar(255) DEFAULT NULL,
  `folder_id` char(36) DEFAULT NULL,
  `drive_file_url` varchar(500) DEFAULT NULL,
  `drive_oauth_refresh_token` text DEFAULT NULL,
  `drive_oauth_email` varchar(255) DEFAULT NULL,
  `document_filename` varchar(255) DEFAULT NULL,
  `document_uploaded_at` datetime DEFAULT NULL,
  `document_chunk_count` int(11) DEFAULT NULL,
  `source_tier` varchar(20) NOT NULL DEFAULT 'supplementary',
  PRIMARY KEY (`id`),
  KEY `fk_sources_client` (`client_id`),
  CONSTRAINT `fk_sources_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_chat_messages`;
CREATE TABLE `support_chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` char(36) NOT NULL,
  `question` text NOT NULL,
  `redacted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_support_chat_client_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_chat_usage`;
CREATE TABLE `support_chat_usage` (
  `client_id` char(36) NOT NULL,
  `input_tokens` bigint(20) unsigned NOT NULL DEFAULT 0,
  `output_tokens` bigint(20) unsigned NOT NULL DEFAULT 0,
  `cost_eur` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `message_count` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `support_field_hits`;
CREATE TABLE `support_field_hits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` char(36) NOT NULL,
  `field_key` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_support_field_hits_client_created` (`client_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `tenant_activity_daily`;
CREATE TABLE `tenant_activity_daily` (
  `client_id` char(36) NOT NULL,
  `activity_date` date NOT NULL,
  `logins` int(11) NOT NULL DEFAULT 0,
  `articles_created` int(11) NOT NULL DEFAULT 0,
  `articles_published` int(11) NOT NULL DEFAULT 0,
  `themes_reviewed` int(11) NOT NULL DEFAULT 0,
  `api_requests` int(11) NOT NULL DEFAULT 0,
  `errors` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`client_id`,`activity_date`),
  KEY `idx_activity_date` (`activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `themes`;
CREATE TABLE `themes` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `cycle_week` date DEFAULT NULL,
  `phase_key` varchar(100) DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `motivation` text DEFAULT NULL,
  `rationale` text DEFAULT NULL,
  `source_article_url` varchar(500) DEFAULT NULL,
  `feedback` enum('none','liked','deleted') NOT NULL DEFAULT 'none',
  `status` enum('proposed','submitted','converted_to_slot','deleted') NOT NULL DEFAULT 'proposed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `objective_id` char(36) DEFAULT NULL,
  `editor_suggested` varchar(100) DEFAULT NULL,
  `research_grounded_candidates` int(11) DEFAULT NULL,
  `research_insufficient_sources` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_themes_client` (`client_id`),
  CONSTRAINT `fk_themes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tracker_snapshots`;
CREATE TABLE `tracker_snapshots` (
  `id` char(36) NOT NULL,
  `client_id` char(36) NOT NULL,
  `computed_at` timestamp NOT NULL,
  `window_from` timestamp NOT NULL,
  `window_to` timestamp NOT NULL,
  `post_count` int(11) NOT NULL,
  `reliability` varchar(16) NOT NULL,
  `bc_avg` decimal(3,1) NOT NULL,
  `bc_weighted_avg` decimal(3,1) NOT NULL,
  `bc_trend_30d_delta` decimal(4,2) DEFAULT NULL,
  `stc_archetype` decimal(3,1) NOT NULL,
  `stc_doel` decimal(3,1) NOT NULL,
  `stc_tegenkracht` decimal(3,1) NOT NULL,
  `stc_spanning` decimal(3,1) NOT NULL,
  `stc_mechanisme` decimal(3,1) NOT NULL,
  `pb_long_term_value` decimal(3,1) NOT NULL,
  `pb_short_term_value` decimal(3,1) NOT NULL,
  `pb_brand_building_share` decimal(4,3) NOT NULL,
  `pb_activation_share` decimal(4,3) NOT NULL,
  `pb_proof_trust_share` decimal(4,3) NOT NULL,
  `pb_service_share` decimal(4,3) NOT NULL,
  `pb_targetmix_fit_score` decimal(5,2) NOT NULL,
  `pb_proof_presence` decimal(4,3) NOT NULL,
  `pb_constraint_respect` decimal(4,3) NOT NULL,
  `cm_positionering` decimal(4,3) NOT NULL,
  `cm_bewijs` decimal(4,3) NOT NULL,
  `cm_educatie` decimal(4,3) NOT NULL,
  `cm_activatie` decimal(4,3) NOT NULL,
  `cm_community` decimal(4,3) NOT NULL,
  `cm_service` decimal(4,3) NOT NULL,
  `cm_employer_culture` decimal(4,3) NOT NULL,
  `cm_reactief` decimal(4,3) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tracker_snapshots_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
