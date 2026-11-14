<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

/**
 * Drempels thema-onderbouwing — briefing/spec/hba-input/drempels-thema-onderbouwing.md
 *
 * Aantal thema's = floor(bruikbare bronfragmenten / 2), max 6.
 * Label "licht onderbouwd" bij < 2 bronfragmenten.
 */

const CE_THEME_PROPOSAL_MAX = 6;

function ce_theme_proposal_count_from_fragments(int $usableFragments): int
{
    $n = max(0, $usableFragments);

    return min(CE_THEME_PROPOSAL_MAX, intdiv($n, 2));
}

function ce_theme_is_licht_onderbouwd(int $usableFragments): bool
{
    return max(0, $usableFragments) < 2;
}

function ce_theme_licht_onderbouwd_label(int $usableFragments): ?string
{
    $n = max(0, $usableFragments);
    if ($n >= 2) {
        return null;
    }
    if ($n === 1) {
        return 'licht onderbouwd — gebaseerd op 1 bron, nog niet elders bevestigd';
    }

    return 'licht onderbouwd thema';
}

/**
 * Pre-research proxy: actieve kennisbronnen (geen Asana).
 * Zelfde ordegrootte als “bruikbare eenheden” tot fragment-inventaris bestaat.
 */
function ce_count_usable_bronfragmenten_for_client(PDO $pdo, string $clientId): int
{
    if ($clientId === '' || !ce_table_exists($pdo, 'sources')) {
        return 0;
    }

    $activeSql = ce_table_has_column($pdo, 'sources', 'active')
        ? ' AND COALESCE(active, 1) = 1'
        : '';

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM sources
         WHERE client_id = :cid AND category <> 'asana'{$activeSql}"
    );
    $stmt->execute(['cid' => $clientId]);

    return (int) $stmt->fetchColumn();
}
