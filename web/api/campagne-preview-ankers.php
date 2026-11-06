<?php

declare(strict_types=1);

const CE_CAMPAGNE_GEO_ANKER_PREFIX = 'Anker (GEO):';
const CE_CAMPAGNE_GEO_ANKER_MAX = 3;

/** @var list<string> */
const CE_CAMPAGNE_GEO_ANKER_FASES = [
    'aankondiging',
    'waarom',
    'hoe_werkt',
    'bewust_niet',
    'vraag',
    'antwoord',
];

function ce_campagne_preview_strip_geo_anker(string $opdracht): string
{
    $stripped = preg_replace('/(?:\n\n)?' . preg_quote(CE_CAMPAGNE_GEO_ANKER_PREFIX, '/') . '.*$/s', '', $opdracht);

    return trim((string) $stripped);
}

function ce_campagne_preview_opdracht_with_geo_anker(string $opdracht, string $question): string
{
    $base = ce_campagne_preview_strip_geo_anker($opdracht);
    $q = trim($question);
    if ($q === '') {
        return $base;
    }
    $line = CE_CAMPAGNE_GEO_ANKER_PREFIX . ' ' . $q;

    return $base !== '' ? $base . "\n\n" . $line : $line;
}

/**
 * @param list<array<string, mixed>> $slots
 * @param list<array{id: string, question: string}> $questions
 * @return list<array{slotId: string, questionId: string, question: string}>
 */
function ce_campagne_preview_allocate_geo_ankers(array $slots, array $questions): array
{
    $n = count($slots);
    $qCount = count($questions);
    if ($n === 0 || $qCount === 0) {
        return [];
    }

    $target = (int) round($n / 3);
    if ($target < 1) {
        $target = 1;
    }
    if ($target > $n) {
        $target = $n;
    }

    $sorted = $slots;
    usort(
        $sorted,
        static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['publishDate'] ?? ''), (string) ($b['publishDate'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['slotIndex'] ?? 0)) <=> ((int) ($b['slotIndex'] ?? 0));
        },
    );

    $preferred = [];
    foreach ($sorted as $slot) {
        $fase = strtolower(trim((string) ($slot['faseKey'] ?? '')));
        if (in_array($fase, CE_CAMPAGNE_GEO_ANKER_FASES, true)) {
            $preferred[] = $slot;
        }
    }
    $pool = count($preferred) >= $target ? $preferred : $sorted;
    $poolN = count($pool);
    if ($poolN === 0) {
        return [];
    }

    $picked = [];
    $seen = [];
    if ($target === 1) {
        $candidates = [$pool[0]];
    } else {
        $candidates = [];
        for ($i = 0; $i < $target; $i++) {
            $idx = (int) round($i * ($poolN - 1) / ($target - 1));
            $candidates[] = $pool[$idx];
        }
    }

    $out = [];
    $qi = 0;
    foreach ($candidates as $slot) {
        $slotId = (string) ($slot['slotId'] ?? $slot['id'] ?? '');
        if ($slotId === '' || isset($seen[$slotId])) {
            continue;
        }
        $seen[$slotId] = true;
        $question = $questions[$qi % $qCount];
        $out[] = [
            'slotId' => $slotId,
            'questionId' => (string) ($question['id'] ?? ''),
            'question' => (string) ($question['question'] ?? ''),
        ];
        ++$qi;
    }

    return $out;
}
