<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

const CE_COPY_MODUS_SELL = 'sell_activatie';
const CE_COPY_MODUS_MERK = 'merkbouw_bewijs';

/** @return list<string> */
function ce_copy_modus_allowed_values(): array
{
    return [CE_COPY_MODUS_SELL, CE_COPY_MODUS_MERK];
}

function ce_copy_modus_is_valid(?string $value): bool
{
    return in_array(trim((string) $value), ce_copy_modus_allowed_values(), true);
}

function ce_copy_modus_from_template_key(string $templateKey): string
{
    $key = strtolower(trim($templateKey));
    if ($key === '' || $key === 'default') {
        return CE_COPY_MODUS_SELL;
    }
    if (in_array($key, ['a', 'lancering', 'lancering_product', 'sell'], true)) {
        return CE_COPY_MODUS_SELL;
    }

    return CE_COPY_MODUS_MERK;
}

function ce_ensure_theme_copy_modus_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked || !ce_table_exists($pdo, 'themes')) {
        $checked = true;

        return;
    }
    $checked = true;
    if (function_exists('ce_ensure_themes_schema')) {
        ce_ensure_themes_schema($pdo);
    }

    if (!ce_table_has_column($pdo, 'themes', 'copy_modus')) {
        try {
            $pdo->exec(
                "ALTER TABLE themes ADD COLUMN copy_modus VARCHAR(32) NULL AFTER rationale"
            );
            unset($GLOBALS['ce_table_columns']['themes']);
        } catch (Throwable) {
            // bestaat al of geen rechten
        }
    }
    if (!ce_table_has_column($pdo, 'themes', 'copy_modus_override')) {
        try {
            $pdo->exec(
                'ALTER TABLE themes ADD COLUMN copy_modus_override TINYINT(1) NOT NULL DEFAULT 0'
            );
            unset($GLOBALS['ce_table_columns']['themes']);
        } catch (Throwable) {
            // bestaat al
        }
    }
}

/**
 * @return array{modus: string, override: bool, source: string}
 */
function ce_resolve_copy_modus_for_slot(PDO $pdo, string $slotId): array
{
    $fallback = ['modus' => CE_COPY_MODUS_MERK, 'override' => false, 'source' => 'default'];

    if ($slotId === '' || !ce_table_exists($pdo, 'content_slots')) {
        return $fallback;
    }

    $slotStmt = $pdo->prepare(
        'SELECT theme_id, campaign_id FROM content_slots WHERE id = :id LIMIT 1'
    );
    $slotStmt->execute(['id' => $slotId]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
    if (!$slot) {
        return $fallback;
    }

    $themeId = trim((string) ($slot['theme_id'] ?? ''));
    if ($themeId !== '' && ce_table_exists($pdo, 'themes')) {
        ce_ensure_theme_copy_modus_columns($pdo);
        $tStmt = $pdo->prepare('SELECT copy_modus, copy_modus_override FROM themes WHERE id = :id LIMIT 1');
        $tStmt->execute(['id' => $themeId]);
        $theme = $tStmt->fetch(PDO::FETCH_ASSOC);
        if ($theme) {
            $modus = trim((string) ($theme['copy_modus'] ?? ''));
            $override = (int) ($theme['copy_modus_override'] ?? 0) === 1;
            if (ce_copy_modus_is_valid($modus)) {
                return [
                    'modus' => $modus,
                    'override' => $override,
                    'source' => 'theme',
                ];
            }
        }
    }

    $campaignId = trim((string) ($slot['campaign_id'] ?? ''));
    if ($campaignId !== '' && ce_table_exists($pdo, 'campaigns')) {
        $cStmt = $pdo->prepare('SELECT template_key FROM campaigns WHERE id = :id LIMIT 1');
        $cStmt->execute(['id' => $campaignId]);
        $templateKey = trim((string) ($cStmt->fetchColumn() ?: ''));
        if ($templateKey !== '') {
            return [
                'modus' => ce_copy_modus_from_template_key($templateKey),
                'override' => false,
                'source' => 'campaign',
            ];
        }
    }

    return $fallback;
}

/** @return list<string> */
function ce_copy_modus_directive_lines(string $modus, bool $userOverride = false): array
{
    if ($modus === CE_COPY_MODUS_SELL) {
        $lines = [
            'COPY-MODUS: activatie — oproep tot actie en promotionele afsluiting zijn toegestaan waar passend.',
        ];
        if ($userOverride) {
            $lines[] = 'Je koos expliciet activatie-toon voor dit thema (overschrijving van merkbouw).';
        }

        return $lines;
    }

    return [
        'COPY-MODUS: merkbouw — geen verkooptaal, geen prijsacties of kortingshooks; '
        . 'mechanisme en bewijs staan voorop.',
    ];
}

/** Backwards compat voor campagne-opdrachtregels. */
function ce_campagne_copy_mode_line(string $templateKey): string
{
    $modus = ce_copy_modus_from_template_key($templateKey);

    return implode(' ', ce_copy_modus_directive_lines($modus));
}

function ce_copy_modus_ui_label(string $modus): string
{
    return $modus === CE_COPY_MODUS_SELL ? 'Activatie' : 'Merkbouw';
}
