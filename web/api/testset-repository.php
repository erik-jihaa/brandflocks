<?php

declare(strict_types=1);

/** SQL AND-fragment: verberg nulmeting-freeze-kopieën in Agenda/Home-lijsten. */
function ce_sql_exclude_testset_freeze_slots(string $slotAlias = 's', ?string $themeAlias = 't'): string
{
    $slotAlias = preg_replace('/[^a-z_]/', '', strtolower($slotAlias)) ?: 's';
    $parts = [
        "({$slotAlias}.input_human IS NULL OR {$slotAlias}.input_human NOT LIKE '%testsetFreeze%')",
    ];
    if ($themeAlias !== null) {
        $themeAlias = preg_replace('/[^a-z_]/', '', strtolower($themeAlias)) ?: 't';
        $parts[] = "({$themeAlias}.title IS NULL OR {$themeAlias}.title NOT LIKE '[nulmeting]%')";
    }

    return ' AND ' . implode(' AND ', $parts);
}
