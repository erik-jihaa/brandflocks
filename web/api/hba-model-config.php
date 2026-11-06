<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/bootstrap.php';

/** Fallback wanneer geen env is gezet — v1 blijft op huidige gedeelde model. */
const CE_HBA_DEFAULT_MODEL = 'claude-sonnet-4-6';

/** Functiesleutels voor per-taak modelkeuze (zie briefing/cursor-hba-model-allocatie.md). */
const CE_HBA_FN_WIZARD_HINTS = 'wizard_hints';
const CE_HBA_FN_ARCHITECTUUR_CHECK = 'architectuur_check';
const CE_HBA_FN_BRANCHE_AFLEIDER = 'branche_afleider';
const CE_HBA_FN_THEMA_VERDICT = 'thema_verdict';
const CE_HBA_FN_ARTICLE_HINT = 'article_hint';
const CE_HBA_FN_CONTENT_SCORE = 'content_score';
const CE_HBA_FN_CAMPAGNE_PRODUCT_EXTRACT = 'campagne_product_extract';
const CE_HBA_FN_CAMPAGNE_PLAN_CHECK = 'campagne_plan_check';

/** @return array<string, string> functiesleutel → env-var */
function ce_hba_function_model_env_map(): array
{
    return [
        CE_HBA_FN_WIZARD_HINTS => 'HBA_MODEL_WIZARD_HINTS',
        CE_HBA_FN_ARCHITECTUUR_CHECK => 'HBA_MODEL_ARCHITECTUUR_CHECK',
        CE_HBA_FN_BRANCHE_AFLEIDER => 'HBA_MODEL_BRANCHE_AFLEIDER',
        CE_HBA_FN_THEMA_VERDICT => 'HBA_MODEL_THEMA_VERDICT',
        CE_HBA_FN_ARTICLE_HINT => 'HBA_MODEL_ARTICLE_HINT',
        CE_HBA_FN_CONTENT_SCORE => 'HBA_MODEL_CONTENT_SCORE',
        CE_HBA_FN_CAMPAGNE_PRODUCT_EXTRACT => 'HBA_MODEL_CAMPAGNE_PRODUCT_EXTRACT',
        CE_HBA_FN_CAMPAGNE_PLAN_CHECK => 'HBA_MODEL_CAMPAGNE_PLAN_CHECK',
    ];
}

function ce_hba_model_reject_fable(string $model, string $context = ''): string
{
    if (stripos($model, 'fable') === false) {
        return $model;
    }

    $suffix = $context !== '' ? " ({$context})" : '';
    error_log("HBA model config: Fable 5 geweigerd{$suffix} — fallback naar " . CE_HBA_DEFAULT_MODEL);

    return CE_HBA_DEFAULT_MODEL;
}

function ce_hba_model(): string
{
    ce_load_env();
    $model = trim((string) (getenv('HBA_ANTHROPIC_MODEL') ?: ''));
    if ($model === '') {
        return CE_HBA_DEFAULT_MODEL;
    }

    return ce_hba_model_reject_fable($model, 'HBA_ANTHROPIC_MODEL');
}

function ce_hba_model_for(string $functionKey): string
{
    ce_load_env();
    $map = ce_hba_function_model_env_map();
    $envKey = $map[$functionKey] ?? null;
    if ($envKey !== null) {
        $model = trim((string) (getenv($envKey) ?: ''));
        if ($model !== '') {
            return ce_hba_model_reject_fable($model, $envKey);
        }
    }

    return ce_hba_model();
}

function ce_hba_table_has_model_column(PDO $pdo, string $table): bool
{
    return ce_table_exists($pdo, $table) && ce_table_has_column($pdo, $table, 'model');
}

function ce_hba_table_has_prompt_version_column(PDO $pdo, string $table): bool
{
    return ce_table_exists($pdo, $table) && ce_table_has_column($pdo, $table, 'prompt_version');
}

/** Promptversie voor coaching-uitkomsten (thema/research/hint/architectuurcheck). */
function ce_hba_coaching_prompt_version(): string
{
    require_once __DIR__ . '/hba-prompt.php';

    return CE_HBA_PROMPT_VERSION;
}

/**
 * Zet prompt_version (en optioneel model) op een opgeslagen HBA-uitkomst.
 * Gebruik na INSERT wanneer prompt_version niet in de INSERT-variants zit.
 */
function ce_hba_persist_outcome_meta(
    PDO $pdo,
    string $table,
    string $id,
    ?string $model = null,
    ?string $promptVersion = null,
): void {
    if ($id === '' || !ce_table_exists($pdo, $table)) {
        return;
    }

    $promptVersion = trim((string) ($promptVersion ?? ce_hba_coaching_prompt_version()));
    $sets = [];
    $params = ['id' => $id];

    if ($model !== null && trim($model) !== '' && ce_table_has_column($pdo, $table, 'model')) {
        $sets[] = 'model = :model';
        $params['model'] = trim($model);
    }
    if (ce_table_has_column($pdo, $table, 'prompt_version')) {
        $sets[] = 'prompt_version = :prompt_version';
        $params['prompt_version'] = $promptVersion;
    }

    if ($sets === []) {
        return;
    }

    $safeTable = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?? $table;
    try {
        $pdo->prepare(
            'UPDATE `' . $safeTable . '` SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1',
        )->execute($params);
    } catch (Throwable) {
        // Kolom ontbreekt nog op oude schema's.
    }
}

function ce_hba_ensure_verdict_model_columns(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    foreach (['hba_theme_verdicts', 'hba_research_verdicts', 'hba_article_hints'] as $table) {
        if (!ce_table_exists($pdo, $table) || ce_table_has_column($pdo, $table, 'model')) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `model` VARCHAR(64) NULL");
            unset($GLOBALS['ce_table_columns'][$table]);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }

    foreach (['hba_theme_verdicts', 'hba_research_verdicts', 'hba_article_hints', 'architectuur_check_result'] as $table) {
        if (!ce_table_exists($pdo, $table) || ce_table_has_column($pdo, $table, 'prompt_version')) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `prompt_version` VARCHAR(16) NULL");
            unset($GLOBALS['ce_table_columns'][$table]);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }

    if (ce_table_exists($pdo, 'architectuur_check_result') && !ce_table_has_column($pdo, 'architectuur_check_result', 'model')) {
        try {
            $pdo->exec('ALTER TABLE architectuur_check_result ADD COLUMN model VARCHAR(64) NULL');
            unset($GLOBALS['ce_table_columns']['architectuur_check_result']);
        } catch (Throwable) {
            // ignore
        }
    }
}
