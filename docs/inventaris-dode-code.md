# Inventaris dode code (automatisch)

Gegenereerd: 2026-11-06 08:40 UTC
Bron: `brandflocks` — `./scripts/inventaris-dode-code.sh`

> Handmatig aanvullen: crontab/n8n op CE-server (zie onder). False positives mogelijk — altijd dubbelchecken vóór schrappen.

## 1. API-routes — runtime (frontend/scripts/n8n JSON)

Totaal routes in `web/api/index.php`: **139**. Geen runtime-referentie: **30** (kandidaat ronde B).

- `/account/linkedin/oauth/callback`
- `/admin/agenda-preview`
- `/admin/agenda-repair`
- `/admin/campagne-preview-accept`
- `/admin/debug-research`
- `/admin/fix-slot-status`
- `/admin/hba-aanspreekvorm-patch`
- `/admin/hba-article-hint-slot`
- `/admin/hba-baseline`
- `/admin/hba-ce03-self-review-rerun`
- `/admin/hba-ensure-guardrail`
- `/admin/hba-hashtag-bewijs`
- `/admin/hba-kernformuleringen`
- `/admin/hba-narrative-repair`
- `/admin/hba-phase1`
- `/admin/hba-phase3`
- `/admin/hba-pilot-negeer-alert-test`
- `/admin/hba-research-verdict-slot`
- `/admin/hba-status`
- `/admin/hba-theme-verdict-slot`
- `/admin/hba-v0-verleiding`
- `/admin/hba-wizard-document-refresh`
- `/admin/hba-woorden-patch`
- `/admin/hygiene-acceptance`
- `/admin/republish-wp`
- `/admin/theme-source-backfill`
- `/ce03/slot-meta`
- `/sources/analytics/oauth/callback`
- `/sources/calendar/oauth/callback`
- `/themes/generate-now`

Zoek ook in **`../JSON/`** (n8n) vóór je een admin-route verwijdert.

## 2. PHP-functies met ≤1 referentie (definitie only)

Gecontroleerd: **1703** functies (`ce_*`, `hba_*`, … in `web/api/`).

- `ce_agenda_display_week_mondays` — `web/api/mappers.php`
- `ce_analytics_fetch_page_views` — `web/api/metrics-repository.php`
- `ce_analytics_oauth_frontend_redirect` — `web/api/analytics-oauth-helper.php`
- `ce_calendar_access_token_for_source` — `web/api/calendar-oauth-helper.php`
- `ce_calendar_list_for_source` — `web/api/calendar-oauth-helper.php`
- `ce_calendar_oauth_frontend_redirect` — `web/api/calendar-oauth-helper.php`
- `ce_client_hba_scoring_allowed` — `web/api/hba-repository.php`
- `ce_client_website_publish_configured` — `web/api/publish-settings-repository.php`
- `ce_count_pending_themes` — `web/api/theme-repository.php`
- `ce_delete_slot_content` — `web/api/slot-repository.php`
- `ce_ensure_article_image_advice` — `web/api/image-advice-repository.php`
- `ce_feedback_status` — `web/api/schema-helper.php`
- `ce_feedback_uuid_v4` — `web/api/feedback-repository.php`
- `ce_hba_category_and_foe_share_kern_citation` — `web/api/hba-prompt.php`
- `ce_hba_format_score_for_display` — `web/api/hba-determinism-repository.php`
- `ce_hba_hoofdoordeel_from_flags` — `web/api/hba-prompt.php`
- `ce_hba_hoofdoordeel_valid_for_flags` — `web/api/hba-prompt.php`
- `ce_hba_ijk_case_by_id` — `web/api/hba-ijkrun-repository.php`
- `ce_hba_ijk_infer_archetype` — `web/api/hba-ijkrun-repository.php`
- `ce_hba_ijk_narrative_from_merkarchitectuur` — `web/api/hba-ijkrun-repository.php`
- `ce_hba_plan_check` — `web/api/campagne-preview-plan-check.php`
- `ce_hba_regel_literally_in_architecture` — `web/api/hba-hint-verificatie.php`
- `ce_hba_store_derived_branche` — `web/api/hba-architectuur-check-repository.php`
- `ce_hba_table_has_prompt_version_column` — `web/api/hba-model-config.php`
- `ce_hba_v2_branche_derive_system_prompt` — `web/api/hba-v2-prompts.php`
- `ce_hba_wizard_document_has_upload` — `web/api/hba-wizard-document-repository.php`
- `ce_home_pipeline_status` — `web/api/home-repository.php`
- `ce_llm_visibility_ask_models_parallel` — `web/api/llm-repository.php`
- `ce_objectives_validate_percentages` — `web/api/objectives-repository.php`
- `ce_platform_cms_configured` — `web/api/platform-repository.php`
- `ce_platform_role_allows` — `web/api/platform-guard.php`
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

## 5. Server-check (Erik) — niet uit repo te halen

Noteer welke `/admin/*`- endpoints **cron** of **n8n** aanroept:
- `mailbox-sync`, `analytics-sync`, `scraper-sync`, `llm-visibility-sync`
- `poll-n8n-failures`, `import-support-emails`, `ce02-trigger`, `hygiene`

| Endpoint | Cron/n8n | Notities |
|----------|----------|----------|
| `/admin/mailbox-sync` | ☐ | |
| `/admin/analytics-sync` | ☐ | |
| `/admin/scraper-sync` | ☐ | |
| `/admin/llm-visibility-sync` | ☐ | |
| `/admin/poll-n8n-failures` | ☐ | |
| `/admin/import-support-emails` | ☐ | |
| `/admin/ce02-trigger` | ☐ | |
| `/admin/hygiene` | ☐ | |

