# Inventaris dode code (automatisch)

Gegenereerd: 2026-11-06 09:01 UTC
Bron: `brandflocks` — `./scripts/inventaris-dode-code.sh`

> Handmatig aanvullen: crontab/n8n op CE-server (zie onder). False positives mogelijk — altijd dubbelchecken vóór schrappen.

## 1. API-routes — runtime (frontend/scripts/n8n JSON)

Totaal routes in `web/api/index.php`: **102**. Geen runtime-referentie: **8** (kandidaat ronde B).

- `/account/linkedin/oauth/callback`
- `/admin/agenda-repair`
- `/admin/debug-research`
- `/admin/hba-article-hint-slot`
- `/ce03/slot-meta`
- `/sources/analytics/oauth/callback`
- `/sources/calendar/oauth/callback`
- `/themes/generate-now`

Zoek ook in **`../JSON/`** (n8n) vóór je een admin-route verwijdert.

## 2. PHP-functies met ≤1 referentie (definitie only)

Gecontroleerd: **1599** functies (`ce_*`, `hba_*`, … in `web/api/`).

- `ce_agenda_display_week_mondays` — `web/api/mappers.php`
- `ce_analytics_fetch_page_views` — `web/api/metrics-repository.php`
- `ce_analytics_oauth_frontend_redirect` — `web/api/analytics-oauth-helper.php`
- `ce_backfill_theme_source_article_urls` — `web/api/theme-repository.php`
- `ce_calendar_access_token_for_source` — `web/api/calendar-oauth-helper.php`
- `ce_calendar_list_for_source` — `web/api/calendar-oauth-helper.php`
- `ce_calendar_oauth_frontend_redirect` — `web/api/calendar-oauth-helper.php`
- `ce_client_hba_scoring_allowed` — `web/api/hba-repository.php`
- `ce_client_website_publish_configured` — `web/api/publish-settings-repository.php`
- `ce_copy_client_sources` — `web/api/sources-repository.php`
- `ce_count_pending_themes` — `web/api/theme-repository.php`
- `ce_delete_slot_content` — `web/api/slot-repository.php`
- `ce_ensure_article_image_advice` — `web/api/image-advice-repository.php`
- `ce_feedback_status` — `web/api/schema-helper.php`
- `ce_feedback_uuid_v4` — `web/api/feedback-repository.php`
- `ce_hba_category_and_foe_share_kern_citation` — `web/api/hba-prompt.php`
- `ce_hba_ce04_regel_feedback_ok` — `web/api/hba-hint-verificatie.php`
- `ce_hba_constraint_breach_verificatie_from_raw` — `web/api/hba-prompt.php`
- `ce_hba_content_score_tool_summary` — `web/api/hba-repository.php`
- `ce_hba_count_content_score_test_articles` — `web/api/hba-repository.php`
- `ce_hba_determinism_run_measurement` — `web/api/hba-determinism-repository.php`
- `ce_hba_determinism_run_measurement_batch` — `web/api/hba-determinism-repository.php`
- `ce_hba_dry_run_content_score_for_article` — `web/api/hba-repository.php`
- `ce_hba_fetch_content_score_test_articles` — `web/api/hba-repository.php`
- `ce_hba_flag_citaten_from_raw` — `web/api/hba-prompt.php`
- `ce_hba_flags_verwijderd_from_raw` — `web/api/hba-prompt.php`
- `ce_hba_format_score_for_display` — `web/api/hba-determinism-repository.php`
- `ce_hba_hoofdoordeel_from_flags` — `web/api/hba-prompt.php`
- `ce_hba_hoofdoordeel_valid_for_flags` — `web/api/hba-prompt.php`
- `ce_hba_hoofdoordeel_volgt_uit_flags` — `web/api/hba-prompt.php`
- `ce_hba_merkbewering_definitie` — `web/api/hba-prompt.php`
- `ce_hba_pilot_negeer_alert_test` — `web/api/hba-pilot-alert-repository.php`
- `ce_hba_pilot_weekly_report_markdown` — `web/api/hba-pilot-weekly-repository.php`
- `ce_hba_pilot_weekly_report_payload` — `web/api/hba-pilot-weekly-repository.php`
- `ce_hba_plan_check` — `web/api/campagne-preview-plan-check.php`
- `ce_hba_regel_literally_in_architecture` — `web/api/hba-hint-verificatie.php`
- `ce_hba_repair_brand_narrative_from_client` — `web/api/hba-repository.php`
- `ce_hba_store_derived_branche` — `web/api/hba-architectuur-check-repository.php`
- `ce_hba_table_has_prompt_version_column` — `web/api/hba-model-config.php`
- `ce_hba_v2_branche_derive_system_prompt` — `web/api/hba-v2-prompts.php`
- `ce_hba_v2_guardrail_check_count` — `web/api/hba-v2-repository.php`
- `ce_hba_v2_hint_uses_derived_boundary` — `web/api/hba-prompt.php`
- `ce_hba_wizard_document_has_upload` — `web/api/hba-wizard-document-repository.php`
- `ce_hba_wizard_document_refresh_suggestions` — `web/api/hba-wizard-document-repository.php`
- `ce_home_pipeline_status` — `web/api/home-repository.php`
- `ce_llm_visibility_ask_models_parallel` — `web/api/llm-repository.php`
- `ce_objectives_validate_percentages` — `web/api/objectives-repository.php`
- `ce_platform_cms_configured` — `web/api/platform-repository.php`
- `ce_platform_role_allows` — `web/api/platform-guard.php`
- `ce_reset_client_pipeline_content` — `web/api/content-reset.php`
- `ce_self_review_forbidden_correction_suffix` — `web/api/self-review-verbodslijst.php`
- `ce_self_review_forbidden_hits_in_input` — `web/api/self-review-verbodslijst.php`
- `ce_self_review_strip_forbidden_fields` — `web/api/self-review-verbodslijst.php`
- `ce_submit_status` — `web/api/schema-helper.php`
- `ce_theme_row_week_key` — `web/api/theme-repository.php`
- `ce_trigger_llm_visibility_check` — `web/api/llm-repository.php`
- `ce_wordpress_config` — `web/api/publish-repository.php`

## 3. Frontend-pagina’s/componenten (weinig of geen import)

Heuristiek — controleer handmatig (lazy routes, dynamic import).

- `frontend/src/components/ArticleEditorBodyField.tsx`
- `frontend/src/components/reports/ReportLlmVisibilitySection.tsx`
- `frontend/src/pages/CampagnePlanReview.tsx`

## 4. DB-tabellen niet in PHP-code (migrations)

- `calibration_reviews`
- `client_alert_settings`
- `erosion_alerts`
- `portfolio_scores`
- `tracker_snapshots`

## 5. Server-check (Erik) — cron/n8n op hosting

Operationele allowlist: `docs/operational-admin-routes.md`.

**In repo gevonden** (scripts/`cron-daily.sh`/`generate-*-workflow.py`):

- `/admin/agenda-preview` — `docs/inventaris-dode-code.md`
- `/admin/agenda-repair` — `docs/inventaris-dode-code.md`
- `/admin/analytics-sync` — `docs/inventaris-dode-code.md`
- `/admin/campagne-preview-accept` — `docs/inventaris-dode-code.md`
- `/admin/ce02-trigger` — `scripts/test-tenant-idor.py`, `docs/inventaris-dode-code.md`
- `/admin/create-client` — `scripts/test-tenant-idor.py`, `scripts/create-client.sh`, `scripts/test-tenant-isolation.py` (+2)
- `/admin/create-platform-user` — `scripts/create-platform-user.sh`, `docs/inventaris-dode-code.md`
- `/admin/db-dump` — `scripts/backup-db.sh`, `docs/inventaris-dode-code.md`
- `/admin/debug-research` — `scripts/test-hba-slot-research-isolation.py`, `docs/inventaris-dode-code.md`
- `/admin/delete-client` — `scripts/delete-client.sh`, `docs/inventaris-dode-code.md`
- `/admin/events` — `scripts/prune-event-log.sh`, `scripts/smoke-test.sh`, `docs/inventaris-dode-code.md`
- `/admin/export-tenant` — `scripts/sync-tenant-section-staging-to-production.sh`, `scripts/sync-tenant-to-staging.sh`, `docs/inventaris-dode-code.md`
- `/admin/fix-slot-status` — `docs/inventaris-dode-code.md`
- `/admin/hba-aanspreekvorm-patch` — `docs/inventaris-dode-code.md`
- `/admin/hba-article-hint-slot` — `scripts/generate-ce03-workflow.py`, `docs/inventaris-dode-code.md`
- `/admin/hba-baseline` — `docs/inventaris-dode-code.md`
- `/admin/hba-ce03-self-review-rerun` — `docs/inventaris-dode-code.md`
- `/admin/hba-ensure-guardrail` — `docs/inventaris-dode-code.md`
- `/admin/hba-hashtag-bewijs` — `docs/inventaris-dode-code.md`
- `/admin/hba-kernformuleringen` — `docs/inventaris-dode-code.md`
- `/admin/hba-narrative-repair` — `docs/inventaris-dode-code.md`
- `/admin/hba-phase1` — `docs/inventaris-dode-code.md`
- `/admin/hba-phase3` — `docs/inventaris-dode-code.md`
- `/admin/hba-pilot-negeer-alert-test` — `docs/inventaris-dode-code.md`
- `/admin/hba-research-verdict-slot` — `docs/inventaris-dode-code.md`
- `/admin/hba-status` — `docs/inventaris-dode-code.md`
- `/admin/hba-theme-verdict-slot` — `docs/inventaris-dode-code.md`
- `/admin/hba-v0-verleiding` — `docs/inventaris-dode-code.md`
- `/admin/hba-wizard-document-refresh` — `docs/inventaris-dode-code.md`
- `/admin/hba-woorden-patch` — `docs/inventaris-dode-code.md`
- `/admin/hygiene` — `docs/inventaris-dode-code.md`
- `/admin/hygiene-acceptance` — `docs/inventaris-dode-code.md`
- `/admin/import-support-emails` — `scripts/import-support-emails.sh`, `docs/inventaris-dode-code.md`
- `/admin/import-tenant` — `scripts/sync-tenant-to-staging.sh`, `docs/inventaris-dode-code.md`
- `/admin/import-tenant-section` — `scripts/sync-tenant-section-staging-to-production.sh`, `docs/inventaris-dode-code.md`
- `/admin/list-slots` — `scripts/test-tenant-idor.py`, `scripts/test-tenant-isolation.py`, `scripts/test-hba-slot-research-isolation.py` (+2)
- `/admin/llm-visibility-sync` — `docs/inventaris-dode-code.md`
- `/admin/mailbox-sync` — `docs/inventaris-dode-code.md`
- `/admin/poll-n8n-failures` — `scripts/poll-n8n-failures.sh`, `docs/inventaris-dode-code.md`
- `/admin/prune-support-service` — `scripts/prune-support-service.sh`, `docs/inventaris-dode-code.md`
- `/admin/republish-wp` — `docs/inventaris-dode-code.md`
- `/admin/reset-client-content` — `scripts/reset-client-content.sh`, `scripts/provision-tenant.sh`, `docs/inventaris-dode-code.md`
- `/admin/reset-content` — `scripts/reset-content-remote.sh`, `docs/inventaris-dode-code.md`
- `/admin/run-migration` — `scripts/run-migration.sh`, `scripts/smoke-test.sh`, `scripts/migrations/README.md` (+1)
- `/admin/schema-export` — `scripts/export-production-schema.sh`, `docs/inventaris-dode-code.md`
- `/admin/schema-migrations-init` — `scripts/export-production-schema.sh`, `scripts/sync-staging-from-production.sh`, `scripts/migrations/README.md` (+1)
- `/admin/scraper-sync` — `docs/inventaris-dode-code.md`
- `/admin/set-password` — `scripts/test-tenant-idor.py`, `scripts/test-tenant-isolation.py`, `scripts/reset-login-password.sh` (+1)
- `/admin/set-platform-password` — `scripts/reset-platform-password.sh`, `docs/inventaris-dode-code.md`
- `/admin/theme-refill` — `docs/inventaris-dode-code.md`
- `/admin/theme-source-backfill` — `docs/inventaris-dode-code.md`
- `/admin/update-client` — `scripts/update-client.sh`, `scripts/copy-sources-strategy.sh`, `scripts/provision-tenant.sh` (+1)
- `/admin/wordpress-db` — `scripts/separate-wordpress-database.sh`, `docs/inventaris-dode-code.md`
- `/admin/wp-diagnose` — `scripts/copy-sources-strategy.sh`, `scripts/smoke-test.sh`, `docs/inventaris-dode-code.md`

**Alleen op server** (vul handmatig als endpoint weg mag):

| Endpoint | Server cron/n8n | Notities |
|----------|-----------------|----------|
| `/admin/mailbox-sync` | ☐ | script-ref: repo |
| `/admin/analytics-sync` | ☐ | script-ref: repo |
| `/admin/scraper-sync` | ☐ | script-ref: repo |
| `/admin/llm-visibility-sync` | ☐ | script-ref: repo |
| `/admin/poll-n8n-failures` | ☐ | script-ref: repo |
| `/admin/import-support-emails` | ☐ | script-ref: repo |
| `/admin/ce02-trigger` | ☐ | script-ref: repo |
| `/admin/hygiene` | ☐ | script-ref: repo |
| `/admin/theme-refill` | ☐ | script-ref: repo |

