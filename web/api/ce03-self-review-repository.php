<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-prompt.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/mappers.php';

/** Tekstcorrectie Brandpunt week 41 (stap 3a) — geen rewrite. */
function ce_ce03_stap3a_brandpunt_text_fix(string $content): string
{
    return str_replace(
        'Dan zetten wij een vers gezette kop voor je neer en leg je uit wat je proeft.',
        'Dan zetten wij een vers gezette kop voor je neer en leggen wij uit wat je proeft.',
        $content,
    );
}

/** @return array<string, mixed> */
function ce_ce03_self_review_tool_schema(): array
{
    return [
        'name' => 'self_review',
        'description' => 'Zelfcontrole van artikel tegen research, bewijsvelden en merkregels',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'weakest_claim' => ['type' => ['string', 'null']],
                'unsupported_assumption' => ['type' => ['string', 'null']],
                'missed_counterpoint' => ['type' => ['string', 'null']],
                'regelbreuk' => [
                    'type' => ['object', 'null'],
                    'properties' => [
                        'regel' => ['type' => 'string'],
                        'citaat' => ['type' => 'string'],
                    ],
                    'required' => ['regel', 'citaat'],
                ],
                'merk_beweringen' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'citaat' => ['type' => 'string'],
                            'bron' => [
                                'type' => 'string',
                                'enum' => ['merkarchitectuur', 'bewijsveld', 'research', 'geen_bron'],
                            ],
                            'steunt_op' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['citaat', 'bron'],
                    ],
                ],
            ],
            'required' => [
                'weakest_claim',
                'unsupported_assumption',
                'missed_counterpoint',
                'regelbreuk',
                'merk_beweringen',
            ],
        ],
    ];
}

/**
 * @param array<string, mixed> $articleRow
 */
function ce_ce03_build_step_d_user_prompt(PDO $pdo, array $articleRow, string $contentAi): string
{
    $clientId = trim((string) ($articleRow['client_id'] ?? ''));
    $ctx = $clientId !== '' ? ce_hba_v2_architecture_context($pdo, $clientId) : [];
    $bewijsvelden = is_array($ctx['bewijsvelden'] ?? null) ? $ctx['bewijsvelden'] : [];
    $bewijsText = function_exists('ce_hba_format_list')
        ? ce_hba_format_list($bewijsvelden)
        : implode("\n", array_map(static fn ($v) => '- ' . $v, $bewijsvelden));
    $regels = trim((string) ($ctx['regels'] ?? ''));
    $aanspreekvorm = trim((string) ($ctx['aanspreekvorm'] ?? ''));
    $researchText = ce_hba_v2_research_summary_for_article($pdo, $articleRow);

    $regelbreukDef = ce_hba_regelbreuk_definitie();
    $merkDef = ce_hba_merkbewering_definitie();

    return "ARTIKEL:\n{$contentAi}\n\n"
        . "ONDERLIGGENDE RESEARCH (de items waarop dit artikel gebaseerd zou moeten zijn):\n{$researchText}\n\n"
        . "REGELS VAN HET MERK:\n" . ($regels !== '' ? $regels : 'Geen regels vastgelegd.') . "\n\n"
        . "BEWIJSVELDEN VAN HET MERK:\n" . ($bewijsText !== '' ? $bewijsText : '(niet ingevuld)') . "\n\n"
        . "AANSPREEKVORM VAN HET MERK:\n" . ($aanspreekvorm !== '' ? $aanspreekvorm : '(niet ingevuld)') . "\n\n"
        . $regelbreukDef . "\n\n"
        . $merkDef . "\n\n"
        . "OPDRACHT: Beantwoord kort en concreet:\n"
        . "1. Welke claim of bewering in het artikel is het minst goed onderbouwd door de research hierboven (geen onderbouwing, of gebaseerd op een item met reliability = low)? Citeer de zin uit het artikel.\n"
        . "2. Is er een aanname in het artikel die niet expliciet uit de research volgt (het model 'vult zelf in')? Zo ja, welke?\n"
        . "3. Mist het artikel een tegengeluid dat wel in de research aanwezig was (een bron die het tegenspreekt)?\n"
        . "4. Breekt het artikel een regel van het merk, volgens de definitie van REGELBREUK hierboven? Zo ja: vul regelbreuk. regel = de geschonden regel als bijzin die grammaticaal past na 'Dit stuk gaat in tegen je regel dat' (bijvoorbeeld: 'je nooit een prijs noemt voordat je het werk ter plekke hebt gezien'), in gewone taal, zonder woorden als geloofwaardigheidstest of verboden logica. citaat = de letterlijke zin uit het artikel die ertegenin gaat. Zo nee, of als er geen regels zijn vastgelegd: regelbreuk = null.\n"
        . "5. MERKBEWERINGEN: volg de definitie MERKBEWERING hierboven. Beoordeel het artikel als gehele passage in context — geen losse zinnen zonder voorgaande context. Per merkbewering: citaat, bron (merkarchitectuur | bewijsveld | research | geen_bron), en optioneel steunt_op = citaat van een eerdere bewering in dezelfde passage waar deze op voortbouwt (zelfde bron als die eerdere bewering). Laat retoriek, standpunten en lezer-aansprekingen weg.\n"
        . "Als vraag 1 tot en met 3 niets concreets opleveren, antwoord dan kort dat het artikel goed aansluit bij de research — forceer geen kritiekpunt. Ook bij vraag 4: forceer geen regelbreuk.\n"
        . "TAAL: antwoord in gewone klanttaal. Gebruik nooit interne veldnamen of enum-waarden (stance, reliability, spreekt_tegen, low, geen_bron, bewijsveld, merkarchitectuur) in weakest_claim, unsupported_assumption of missed_counterpoint.\n"
        . 'Output via de tool.';
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ce_ce03_self_review_enforce_client_language(PDO $pdo, string $articleId, string $userPrompt, array $input): array
{
    require_once __DIR__ . '/self-review-verbodslijst.php';

    $hits = ce_self_review_forbidden_hits_in_input($input);
    if ($hits === []) {
        return $input;
    }

    $retryPrompt = $userPrompt . ce_self_review_forbidden_correction_suffix();
    $llm = ce_ce03_call_step_d_self_review($retryPrompt);
    if (($llm['ok'] ?? false) && is_array($llm['input'] ?? null)) {
        $retryInput = $llm['input'];
        $retryInput['merk_beweringen'] = ce_ce03_normalize_merk_beweringen(
            is_array($retryInput['merk_beweringen'] ?? null) ? $retryInput['merk_beweringen'] : []
        );
        if (ce_self_review_forbidden_hits_in_input($retryInput) === []) {
            return $retryInput;
        }
        $input = $retryInput;
    }

    $stripped = ce_self_review_strip_forbidden_fields($input);
    require_once __DIR__ . '/event-log-repository.php';
    ce_log_event($pdo, 'warning', 'hba', 'self_review_verbodslijst', 'Methodiektermen verwijderd uit self_review', [
        'articleId' => $articleId,
        'hits' => $hits,
    ]);

    return $stripped;
}

/** @param list<array<string, mixed>> $rows */
function ce_ce03_normalize_merk_beweringen(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $citaat = trim((string) ($row['citaat'] ?? ''));
        if ($citaat === '') {
            continue;
        }
        $bron = strtolower(trim((string) ($row['bron'] ?? '')));
        if (!in_array($bron, ['merkarchitectuur', 'bewijsveld', 'research', 'geen_bron'], true)) {
            $bron = 'geen_bron';
        }
        $entry = ['citaat' => $citaat, 'bron' => $bron];
        $steunt = trim((string) ($row['steunt_op'] ?? $row['steuntOp'] ?? ''));
        if ($steunt !== '') {
            $entry['steunt_op'] = $steunt;
        }
        $out[] = $entry;
    }

    return $out;
}

/**
 * @return array{ok: bool, input?: array<string, mixed>, model?: string, error?: string}
 */
function ce_ce03_call_step_d_self_review(string $userPrompt, int $maxTokens = 1400): array
{
    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    if ($key === '') {
        return ['ok' => false, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    $model = 'claude-sonnet-4-6';
    $system = 'Je bent een kritische eindredacteur die het zojuist geschreven artikel controleert tegen de onderliggende research, de bewijsvelden en de merkarchitectuur. Wees specifiek, geen algemene opmerkingen zoals \'het artikel is goed geschreven\'.';

    $request = [
        'model' => $model,
        'max_tokens' => max(256, min(8192, $maxTokens)),
        'system' => $system,
        'messages' => [['role' => 'user', 'content' => $userPrompt]],
        'tools' => [ce_ce03_self_review_tool_schema()],
        'tool_choice' => ['type' => 'tool', 'name' => 'self_review'],
    ];
    if (!ce_hba_anthropic_model_omits_temperature($model)) {
        $request['temperature'] = 0.2;
    }

    $payload = json_encode($request, JSON_THROW_ON_ERROR);
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
        return ['ok' => false, 'error' => $res['error']];
    }

    $raw = json_decode($res['body'], true);
    if (!is_array($raw)) {
        return ['ok' => false, 'error' => 'Ongeldig Anthropic-antwoord'];
    }

    foreach (($raw['content'] ?? []) as $block) {
        if (!is_array($block) || ($block['type'] ?? '') !== 'tool_use' || ($block['name'] ?? '') !== 'self_review') {
            continue;
        }
        $input = $block['input'] ?? null;
        if (is_array($input)) {
            return ['ok' => true, 'input' => $input, 'model' => $model];
        }
    }

    return ['ok' => false, 'error' => 'Geen self_review tool output'];
}

/**
 * @return array{ok: bool, articleId?: string, contentAi?: string, selfReviewRaw?: array<string, mixed>, selfReviewParsed?: array<string, mixed>, error?: string}
 */
function ce_ce03_rerun_step_d_for_article(PDO $pdo, string $articleId, bool $applyStap3aTextFix = true): array
{
    $article = ce_fetch_article_by_id($pdo, $articleId);
    if ($article === null) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }

    $clientId = trim((string) ($article['client_id'] ?? ''));
    if ($clientId === '') {
        $slotId = trim((string) ($article['slot_id'] ?? ''));
        if ($slotId !== '') {
            $slotStmt = $pdo->prepare('SELECT client_id FROM content_slots WHERE id = :id LIMIT 1');
            $slotStmt->execute(['id' => $slotId]);
            $slotRow = $slotStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($slotRow)) {
                $clientId = trim((string) ($slotRow['client_id'] ?? ''));
                if ($clientId !== '') {
                    $article['client_id'] = $clientId;
                }
            }
        }
    }

    $content = trim((string) ($article['content_ai'] ?? ''));
    if ($content === '') {
        return ['ok' => false, 'error' => 'Geen content_ai'];
    }
    if ($applyStap3aTextFix) {
        $content = ce_ce03_stap3a_brandpunt_text_fix($content);
    }

    $userPrompt = ce_ce03_build_step_d_user_prompt($pdo, $article, $content);
    $llm = ce_ce03_call_step_d_self_review($userPrompt);
    if (!$llm['ok'] || !is_array($llm['input'] ?? null)) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Stap D mislukt'];
    }

    $input = $llm['input'];
    $input = ce_ce03_self_review_enforce_client_language($pdo, $articleId, $userPrompt, $input);
    $merk = ce_ce03_normalize_merk_beweringen(
        is_array($input['merk_beweringen'] ?? null) ? $input['merk_beweringen'] : []
    );
    $input['merk_beweringen'] = $merk;

    $existingJson = trim((string) ($article['self_review'] ?? ''));
    $existing = $existingJson !== '' ? json_decode($existingJson, true) : null;
    if (!is_array($existing)) {
        $existing = [];
    }

    $merged = array_merge($existing, [
        'weakest_claim' => $input['weakest_claim'] ?? null,
        'unsupported_assumption' => $input['unsupported_assumption'] ?? null,
        'missed_counterpoint' => $input['missed_counterpoint'] ?? null,
        'regelbreuk' => $input['regelbreuk'] ?? null,
        'merk_beweringen' => $merk,
    ]);

    $parsed = ce_parse_self_review($merged);

    return [
        'ok' => true,
        'articleId' => $articleId,
        'contentAi' => $content,
        'selfReviewRaw' => $merged,
        'selfReviewParsed' => $parsed ?? [],
    ];
}

/**
 * @param array<string, mixed> $selfReviewRaw
 */
function ce_ce03_persist_self_review_and_content(PDO $pdo, string $articleId, string $contentAi, array $selfReviewRaw): void
{
    $json = json_encode($selfReviewRaw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $pdo->prepare(
        'UPDATE articles SET content_ai = :content, self_review = :sr WHERE id = :id'
    )->execute([
        'content' => $contentAi,
        'sr' => $json,
        'id' => $articleId,
    ]);
}
