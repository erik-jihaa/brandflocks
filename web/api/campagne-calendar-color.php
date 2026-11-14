<?php

declare(strict_types=1);

/**
 * Kalenderkleur 0–2 volgt de oude colorIndex-rotatie (groen, oranje, blauw).
 * Kleur 3 is de vierde swatch en komt alleen uit een nieuwe keuze.
 *
 * @param list<string> $campaignIdsInRowOrder Zelfde volgorde als de agenda-rijen (phase_key, theme).
 * @return array<string, int>
 */
function ce_campagne_calendar_colors_for_row_order(array $campaignIdsInRowOrder): array
{
    $assigned = [];
    $index = 0;
    foreach ($campaignIdsInRowOrder as $raw) {
        $id = trim((string) $raw);
        if ($id === '' || array_key_exists($id, $assigned)) {
            continue;
        }
        $assigned[$id] = $index % 3;
        $index++;
    }

    return $assigned;
}

function ce_campagne_normalize_calendar_color(mixed $raw): int
{
    if ($raw === null || $raw === '') {
        throw new InvalidArgumentException('Kies een kleur voor de kalender.');
    }
    $color = (int) $raw;
    if ($color < 0 || $color > 3) {
        throw new InvalidArgumentException('Kies een van de vier kalenderkleuren.');
    }

    return $color;
}
