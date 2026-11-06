<?php

declare(strict_types=1);

/** Gedeeld NL-calque-blok voor alle Dutch-output LLM-calls (CE-01/02/03, LinkedIn Scan). */
function ce_nl_avoid_calques_block(): string
{
    static $block = null;
    if ($block !== null) {
        return $block;
    }

    $path = __DIR__ . '/prompts/nl-avoid-calques-block.txt';
    if (!is_readable($path)) {
        $block = '';

        return $block;
    }

    $block = trim((string) file_get_contents($path));

    return $block;
}
