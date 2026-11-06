<?php

declare(strict_types=1);

require_once __DIR__ . '/llm-repository.php';
require_once __DIR__ . '/hba-model-config.php';
require_once __DIR__ . '/hygiene-scrape-repository.php';
require_once __DIR__ . '/uuid-helper.php';

const CE_HYGIENE_DISTILL_MIN_COMPETITORS = 3;

/** @return array<string, mixed> */
function ce_hygiene_distill_tool_schema(): array
{
    return [
        'name' => 'hygiene_distill',
        'description' => 'Waargenomen categorietaal uit concurrent-websites',
        'input_schema' => [
            'type' => 'object',
            'required' => ['phrases'],
            'properties' => [
                'phrases' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['phrase', 'competitor_count', 'sources'],
                        'properties' => [
                            'phrase' => ['type' => 'string'],
                            'competitor_count' => ['type' => 'integer', 'minimum' => 3],
                            'sources' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['url', 'competitor'],
                                    'properties' => [
                                        'url' => ['type' => 'string'],
                                        'competitor' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/** @return array{ok: bool, tool: ?array<string, mixed>, error: string} */
function ce_hygiene_call_distill_llm(string $prompt): array
{
    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    if ($key === '') {
        return ['ok' => false, 'tool' => null, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    $model = ce_hba_model_for(CE_HBA_FN_CONTENT_SCORE);
    $payload = json_encode([
        'model' => $model,
        'max_tokens' => 4096,
        'temperature' => 0.2,
        'tools' => [ce_hygiene_distill_tool_schema()],
        'tool_choice' => ['type' => 'tool', 'name' => 'hygiene_distill'],
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ], JSON_THROW_ON_ERROR);

    $res = ce_llm_visibility_http_post(
        'https://api.anthropic.com/v1/messages',
        [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        $payload,
        120,
    );
    if (!$res['ok']) {
        return ['ok' => false, 'tool' => null, 'error' => $res['error']];
    }

    $data = json_decode($res['body'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'tool' => null, 'error' => 'Ongeldig LLM-antwoord'];
    }

    foreach (($data['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'hygiene_distill') {
            $input = $block['input'] ?? null;
            if (is_array($input)) {
                return ['ok' => true, 'tool' => $input, 'error' => ''];
            }
        }
    }

    return ['ok' => false, 'tool' => null, 'error' => 'Geen hygiene_distill output'];
}

/** @return array<string, mixed> */
function ce_hygiene_distill_list(PDO $pdo, string $listId): array
{
    ce_ensure_hygiene_schema($pdo);
    $corpus = ce_hygiene_collect_scrape_corpus($pdo, $listId);
    if ($corpus === []) {
        return ['ok' => false, 'error' => 'Geen scrape-tekst — eerst concurrenten scrapen'];
    }

    $competitorNames = [];
    foreach ($corpus as $chunk) {
        $name = trim((string) ($chunk['competitor'] ?? ''));
        if ($name !== '') {
            $competitorNames[$name] = true;
        }
    }
    if (count($competitorNames) < CE_HYGIENE_DISTILL_MIN_COMPETITORS) {
        return [
            'ok' => false,
            'error' => 'Minstens ' . CE_HYGIENE_DISTILL_MIN_COMPETITORS . ' concurrenten met scrape-tekst vereist',
        ];
    }

    $lines = [];
    foreach ($corpus as $chunk) {
        $lines[] = '=== ' . $chunk['competitor'] . ' | ' . $chunk['pageType'] . ' | ' . $chunk['url'] . " ===\n"
            . $chunk['excerpt'];
    }
    $prompt = <<<'PROMPT'
Je destilleert waargenomen categorietaal uit concurrent-websites voor een positioneringsmeetlat.

REGels:
- Alleen claims of formuleringen die letterlijk of substantieel hetzelfde zijn bij DRIE of meer verschillende concurrenten.
- Geen losse buzzwords zonder context.
- Geen productnamen of merkspecifieke slogans.
- Formuleer elke phrase als korte NL-zin of formulering (max 120 tekens).
- Geef per phrase de vindplaatsen (url + concurrent) uit de input.

Gebruik de tool hygiene_distill.

BRONTEKST:
PROMPT;
    $prompt .= "\n\n" . implode("\n\n", $lines);

    $llm = ce_hygiene_call_distill_llm($prompt);
    if (!$llm['ok'] || !is_array($llm['tool'])) {
        return ['ok' => false, 'error' => $llm['error'] !== '' ? $llm['error'] : 'Distillatie mislukt'];
    }

    $phrases = is_array($llm['tool']['phrases'] ?? null) ? $llm['tool']['phrases'] : [];
    $pdo->prepare(
        "DELETE FROM hygiene_factors
         WHERE list_id = :list_id AND kind = :kind AND review_status = 'pending'"
    )->execute(['list_id' => $listId, 'kind' => CE_HYGIENE_KIND_OBSERVED]);

    $inserted = 0;
    foreach ($phrases as $row) {
        if (!is_array($row)) {
            continue;
        }
        $phrase = trim((string) ($row['phrase'] ?? ''));
        if ($phrase === '') {
            continue;
        }
        $count = (int) ($row['competitor_count'] ?? 0);
        if ($count < CE_HYGIENE_DISTILL_MIN_COMPETITORS) {
            continue;
        }
        $sources = is_array($row['sources'] ?? null) ? $row['sources'] : [];
        $stmt = $pdo->prepare(
            'INSERT INTO hygiene_factors (id, list_id, phrase, kind, sources, added_by, review_status, competitor_count)
             VALUES (:id, :list_id, :phrase, :kind, :sources, :added_by, :review_status, :competitor_count)'
        );
        $stmt->execute([
            'id' => ce_uuid(),
            'list_id' => $listId,
            'phrase' => $phrase,
            'kind' => CE_HYGIENE_KIND_OBSERVED,
            'sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
            'added_by' => 'distill',
            'review_status' => 'pending',
            'competitor_count' => $count,
        ]);
        $inserted++;
    }

    return ['ok' => true, 'listId' => $listId, 'inserted' => $inserted, 'corpusPages' => count($corpus)];
}
