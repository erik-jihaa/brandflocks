<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

/**
 * Anthropic Claude Haiku 4.5 — $1 / MTok input, $5 / MTok output (okt 2026).
 * Bron: https://docs.anthropic.com/en/docs/about-claude/pricing
 */
const LI_NARRATIVE_INPUT_USD_PER_TOKEN = 0.000001;
const LI_NARRATIVE_OUTPUT_USD_PER_TOKEN = 0.000005;

const LI_NARRATIVE_SYSTEM_PROMPT = <<<'PROMPT'
Je analyseert LinkedIn-posts van een bedrijf. Je geeft ALLEEN geldige JSON terug,
geen uitleg erbuiten, geen markdown-codeblok. Structuur:
{
  "core_message": string (max 2 zinnen, in het Nederlands, wat het bedrijf zelf
    beweert te bieden/waar te staan — parafraseer hun eigen taal, verzin niets),
  "recurring_themes": string[] (3-6 items, korte kernwoorden/thema's die
    meerdere keren terugkomen),
  "consistency_label": "hoog" | "gemiddeld" | "laag" (hoe consequent dezelfde
    kernboodschap terugkomt over alle posts heen — niet of de boodschap goed is,
    alleen of hij consistent is),
  "consistency_reasoning": string (max 2 zinnen, met een concreet voorbeeld),
  "inconsistency_examples": string[] (0-3 korte voorbeelden van afwijkende
    boodschappen; leeg als consistency_label "hoog" is)
}
Baseer je uitsluitend op de aangeleverde posts. Als de posts te weinig
gemeenschappelijke thematiek hebben om iets te concluderen, geef dat eerlijk
weer in consistency_reasoning in plaats of iets te verzinnen. Verzin niets —
parafraseer alleen wat in de posts staat.

TAALREGELS (strikt — geldt voor alle tekstvelden in de JSON):
- Schrijf natuurlijk Nederlands, zoals in een zakelijke e-mail aan een prospect — geen AI- of vertaalde toon.
- Korte, heldere zinnen. Actieve formuleringen. Geen omwegen of opgeblazen taal.
- Geen anglicismen en geen pseudo-Engelse vergelijkingen. Verboden o.a.:
  "genuin/genuiner/genuinere", "authentieker/authentiekere", "uniquer/uniquere",
  "engage/engagement" (gebruik: reacties/bereik), "impactful", "storytelling",
  "content", "brand story" — tenzij het bedrijf die woorden zelf letterlijk gebruikt.
  Gebruik die verboden woorden ook niet in je eigen voorbeelden; zeg "posts", "berichten"
  of beschrijf wat de post doet.
- Schrijf over het bedrijf in de derde persoon ("De posts…", "De kernboodschap…").
  Geen "jullie/u/jij" in de JSON — de mail eromheen spreekt de prospect aan.
- Gebruik liever: echt, herkenbaar, eigen, opvallend, concreet, scherp, consistent.
- recurring_themes: korte Nederlandse woorden of woordgroepen (2-5 woorden), geen Engelse hashtags,
  GEEN haakjes met onderbouwing/citaten erin — een thema is een label, geen redenering. Zet bewijs
  (voorbeeldzinnen, citaten) in consistency_reasoning of inconsistency_examples, nooit tussen
  haakjes achter een thema geplakt.
- consistency_reasoning en distinctiveness_reasoning: neutrale observatie in derde persoon
  ("De posts…", "De kernboodschap…") — geen "u/jij", geen advies, geen emoji.
- core_message: parafraseer de posts in gewoon Nederlands; vervang Engelse marketingtaal
  door een natuurlijk Nederlands equivalent als het bedrijf dat zelf niet zo schrijft.
- Gebruik nooit een Engelse redeneeraanduiding tussen haakjes, zoals "(implicit in ...)" of
  "(explicit in ...)". Schrijf de onderbouwing voluit in gewoon Nederlands, in de zin zelf.
- Verzin geen samengestelde woorden die geen standaard Nederlands zijn — gebruik bestaande
  Nederlandse woorden, ook als dat een langer zinsdeel wordt.
- inconsistency_examples: elk item is een volledige, op zichzelf staande zin die eindigt op een
  punt (deze items worden na elkaar getoond, zonder afsluitend leesteken loopt de ene zin door in
  de andere).

POSTVERWIJZINGEN (strikt — geldt voor alle tekstvelden):
- De chronologische lijst ("Posts (chronologisch)") en de ranking op reacties ("Top-posts op
  reacties") gebruiken VERSCHILLENDE nummering. Verwar die nooit.
- In de ranking op reacties is Post 1 altijd de post met de meeste reacties. Gebruik NOOIT
  0-gebaseerde nummering ("post 0") en verwijs NOOIT naar chronologische nummers als je over
  prestaties of herhaalbaarheid praat.
- Verwijs bij voorkeur naar "de post van [datum]" of "Post N op reacties ([datum])" waarbij N de
  ranking op reacties is uit de meegegeven top-lijst.
- In consistency_reasoning en inconsistency_examples: gebruik datum + korte onderwerpomschrijving
  in plaats van alleen een nummer, tenzij je expliciet verwijst naar "Post N op reacties".

FOUT → GOED:
- "De boodschap voelt genuiner" → "De boodschap klinkt echter"
- "Meer authentieke positionering" → "Een positionering die echt van hen is"
- "Het merk positioneert zich unieker" → "Het merk zegt iets dat opvalt"
- "Lage engagement" → "Weinig reacties op de posts"
- "Merkidentiteit (implicit in 'samen vieren', 'samenwerking')" → thema wordt gewoon
  "Samen vieren" of "Samenwerking" als los item; de onderbouwing ("dit blijkt uit woorden als
  'samen vieren' en 'samenwerking'") hoort in consistency_reasoning, niet tussen haakjes bij het
  thema.
- "Post 2 focust op seizoensgebonden acties en productpromotes" → "Post 2 focust op
  seizoensgebonden acties en productpromoties" (bestaand Nederlands woord, geen verzonnen vorm).
- "Post 3 is primair een bedrijfsfeestje-inhoud (25-jarig jubileum)" → "Post 3 gaat vooral
  over het jubileumfeest, zonder inhoudelijke stellingname" (geen samengesteld woord dat geen
  Nederlands is, en geen "content").

Lees elke zin hardop: klinkt het als iets wat een Nederlandse collega zo zou typen?
Zo niet, herschrijf eenvoudiger voordat je de JSON teruggeeft.
PROMPT;

const LI_NARRATIVE_DISTINCTIVENESS_PROMPT = <<<'PROMPT'

Als er een lijst met branche-clichés is meegegeven: beoordeel of de kernboodschap van dit bedrijf
zich onderscheidt van die clichés, of er juist in past. Voeg dan ook deze velden toe aan de JSON:
  "distinctiveness_label": "ja" | "gedeeltelijk" | "nee",
  "distinctiveness_reasoning": string (max 2 zinnen, concreet — welk cliché overlapt, of wat maakt het onderscheidend),
  "matched_cliches": string[] (0-3 items uit de meegegeven categorietaal-lijst die overlappen met hun boodschap; leeg als label "ja" is)

Regels voor distinctiveness_label:
  "ja"           — de kernboodschap zegt iets dat de meeste concurrenten in deze branche niet
                    zouden claimen; geen overlap met de meegegeven clichés
  "gedeeltelijk" — deels eigen taal, deels overlappend met een cliché uit de lijst
  "nee"          — de kernboodschap valt (bijna) letterlijk samen met één of meer clichés uit de
                    lijst; noem ze in matched_cliches

Wees hier net zo strikt als bij consistency: dit is geen compliment-generator. Een merk dat
"kwaliteit en persoonlijke service" claimt in een branche waar dat op de clichélijst staat, is
"nee" — ook als die claim consistent terugkomt.

Let op het verschil tussen de twee meegegeven lijsten:
- categorietaal = letterlijke cliché-frases die concurrenten ook gebruiken
- hygienefactoren = basisverwachtingen van de branche (geen unieke frase, maar ook geen onderscheid)
Beide tellen mee richting "nee"/"gedeeltelijk", maar benoem in distinctiveness_reasoning expliciet
of iets een "cliché-frase" of een "basisverwachting in de branche" is.
PROMPT;

const LI_NARRATIVE_DESCRIPTION_PROMPT = <<<'PROMPT'

Als er een bedrijfsomschrijving is meegegeven: neem die als waarheid over wat dit bedrijf
daadwerkelijk doet. Beoordeel of hun LinkedIn-posts dat ook laten zien. Voeg dan ook toe:
  "description_alignment_label": "ja" | "deels" | "nee",
  "description_alignment_reasoning": string (max 2 zinnen: laten de posts zien waar het bedrijf voor staat, of blijft het generiek?)

Citeer de meegegeven bedrijfsomschrijving NOOIT letterlijk, ook niet tussen aanhalingstekens.
Die notitie is interne input, geen tekst voor de prospect. Parafraseer of oordeel of de posts
laten zien waar het bedrijf voor staat.

Gebruik nooit een generieke samenvatting ("strategische partner", "toonaangevend", "innovatief")
als core_message als de posts dat zelf niet letterlijk onderbouwen — als de posts vaag zijn,
zeg dat expliciet in consistency_reasoning in plaats of de vaagheid over te nemen in core_message.
PROMPT;

const LI_NARRATIVE_CONTEXT_PROMPT = <<<'PROMPT'

Als er een contextprofiel is meegegeven: gebruik dat als kalibratie voor consistency_reasoning.
Een merk in "herpositionering" dat wisselende boodschappen post, is niet per se zwak — dat kan een
bewuste overgang zijn. Noem dat als het relevant is, in plaats van een lage consistency_label
toe te schrijven aan onwil of onkunde.
PROMPT;

const LI_NARRATIVE_REPEATABILITY_PROMPT = <<<'PROMPT'

Hierboven staan de best presterende posts, gesorteerd op reacties. Beoordeel per post of het
concept STRUCTUREEL HERHAALBAAR is — een thema, format of insteek die dit bedrijf regelmatig kan
terug laten komen — of dat het een EENMALIGE gebeurtenis is die toevallig veel reacties kreeg
(jubileum, verjaardag van het bedrijf, award, overlijden, personeelswissel, unieke aankondiging,
eenmalige samenwerking).

Kies de best presterende post die WEL herhaalbaar is. Dat hoeft niet nummer 1 te zijn.

Zijn er meerdere herhaalbare kandidaten, kies dan niet zomaar de eerste — kies degene die het
sterkst aansluit bij de kernboodschap/unieke positionering van dit merk (core_message hierboven).
Herhaalbaar zijn is niet genoeg als het concept zelf generiek is en niets zegt over wat dit merk
onderscheidt; een herhaalbaar concept dat ook de eigen positionering versterkt is een beter advies
dan een herhaalbaar maar inhoudsloos concept.

Voeg toe aan de JSON:
  "repeatable_best_post_index": integer (0-gebaseerde index in de meegegeven top-lijst op
    reacties — index 0 = Post 1 = meeste reacties),
  "repeatable_best_post_reasoning": string (max 2 zinnen: waarom is dit concept herhaalbaar —
    noem het onderliggende format/thema, niet alleen "goede post"),
  "repeatable_best_post_excluded_reason": string|null (alleen invullen als index NIET 0 is —
    waarom de sterkste post is overgeslagen, bijv. "eenmalig 25-jarig jubileum"),
  "repeatable_best_post_reinforces_positioning": "ja" | "nee" | "onduidelijk" (versterkt dit
    gekozen concept de kernboodschap/unieke positionering van het merk, of is het herhaalbaar maar
    inhoudelijk generiek — "onduidelijk" alleen als core_message zelf al te vaag is om te toetsen)

Als geen enkele van de meegegeven posts herhaalbaar is, zet index op 0 en leg dat eerlijk uit in
repeatable_best_post_reasoning — verzin geen herhaalbaarheid die er niet is. Kies ook dan geen
concept dat niet aansluit bij de positionering enkel omdat er niets beters is — wees eerlijk als
géén van de posts allebei (herhaalbaar én onderscheidend) is.

TOON in repeatable_best_post_excluded_reason: dit is geen afkeuring van de post zelf — een
eenmalige gebeurtenis kan een uitstekende post zijn geweest, hij is alleen geen format om te
herhalen. Vermijd afwijzende woorden ("inhoudsloos", "leunt op sentimentaliteit", "zwak"). Zeg
neutraal wat het WEL was en waarom dat niet herhaalbaar is.

FOUT → GOED (toon):
- "Deze post is inhoudsloos en leunt op sentimentaliteit" → "Deze post werkt sterk als
  teken van betrokkenheid bij een jubileum, maar is minder bruikbaar als herhaalbaar format voor de
  inhoudelijke positionering"
PROMPT;

/**
 * @param list<array{text?: string}> $posts
 * @param list<array{reactions: int, text: string, posted_at: string}> $topPosts
 *
 * @return array<string, mixed>|null
 */
function li_analyze_narrative(
    array $posts,
    string $companyName,
    ?string $brancheCode = null,
    ?string $companyDescription = null,
    ?string $contextProfiel = null,
    array $topPosts = [],
    ?PDO $costPdo = null,
    ?string $costScanId = null,
): ?array {
    if (!(bool) li_config('ai_summary_enabled', false)) {
        return null;
    }

    $apiKey = trim((string) li_config('anthropic_api_key', ''));
    if ($apiKey === '') {
        return null;
    }

    $brancheRow = li_branche_row($brancheCode);
    $withDistinctiveness = $brancheRow !== null;
    $companyDescription = li_scan_normalize_company_description($companyDescription);
    $withDescriptionAlignment = $companyDescription !== null;
    $contextProfiel = li_contextprofiel_code_for_scan($contextProfiel);
    $withContextProfiel = $contextProfiel !== null;
    $withRepeatability = $topPosts !== [];

    $textPosts = [];
    foreach ($posts as $post) {
        if (!is_array($post)) {
            continue;
        }
        $text = trim((string) ($post['text'] ?? ''));
        if ($text !== '') {
            $textPosts[] = $text;
        }
    }

    if (count($textPosts) < 4) {
        return null;
    }

    $maxPosts = (int) li_config('max_posts', 30);
    $textPosts = array_slice($textPosts, 0, $maxPosts);

    $lines = [];
    foreach ($textPosts as $index => $text) {
        $snippet = $text;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($snippet) > 400) {
                $snippet = mb_substr($snippet, 0, 400) . '…';
            }
        } elseif (strlen($snippet) > 400) {
            $snippet = substr($snippet, 0, 400) . '…';
        }
        $lines[] = ($index + 1) . '. ' . $snippet;
    }

    $userMessage = 'Bedrijf: ' . $companyName . "\nPosts (chronologisch):\n" . implode("\n", $lines);

    if ($withDescriptionAlignment) {
        $userMessage .= "\n\nBedrijfsomschrijving (grondwaarheid — niet ter discussie stellen):\n" . $companyDescription;
    }

    if ($withContextProfiel) {
        $contextLabel = li_contextprofiel_label($contextProfiel) ?? $contextProfiel;
        $userMessage .= "\n\nContextprofiel van dit bedrijf: " . $contextLabel . ' (' . $contextProfiel . ')';
    }

    if ($withDistinctiveness && $brancheRow !== null) {
        $userMessage .= "\n\nBranche: " . $brancheRow['label'];
        $userMessage .= "\n\nBranche-clichés (categorietaal — letterlijke frases die concurrenten ook gebruiken):";
        foreach ($brancheRow['categorietaal'] as $cliche) {
            $userMessage .= "\n- " . $cliche;
        }
        $userMessage .= "\n\nBranche-basisverwachtingen (hygienefactoren — geen unieke frase, maar ook geen onderscheid):";
        foreach ($brancheRow['hygienefactoren'] as $factor) {
            $userMessage .= "\n- " . $factor;
        }
    }

    if ($withRepeatability) {
        $userMessage .= "\n\nTop-posts op reacties (Post 1 = meeste reacties — gebruik deze nummering):";
        foreach ($topPosts as $index => $post) {
            $reactions = (int) ($post['reactions'] ?? 0);
            $postedAt = substr((string) ($post['posted_at'] ?? ''), 0, 10);
            $snippet = trim((string) ($post['text'] ?? ''));
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                if (mb_strlen($snippet) > 200) {
                    $snippet = mb_substr($snippet, 0, 200) . '…';
                }
            } elseif (strlen($snippet) > 200) {
                $snippet = substr($snippet, 0, 200) . '…';
            }
            $userMessage .= "\nPost " . ($index + 1) . ' [' . $reactions . ' reacties'
                . ($postedAt !== '' ? ', ' . $postedAt : '') . '] "' . $snippet . '"';
        }
    }

    $estimatedOutputTokens = 300;
    if ($withDistinctiveness) {
        $estimatedOutputTokens += 100;
    }
    if ($withDescriptionAlignment) {
        $estimatedOutputTokens += 80;
    }
    if ($withRepeatability) {
        $estimatedOutputTokens += 80;
    }
    $estimatedInputTokens = (int) ceil(strlen($userMessage) / 4) + 600;
    if ($withDistinctiveness) {
        $estimatedInputTokens += 300;
    }
    if ($withDescriptionAlignment) {
        $estimatedInputTokens += 200;
    }
    if ($withContextProfiel) {
        $estimatedInputTokens += 80;
    }
    if ($withRepeatability) {
        $estimatedInputTokens += 200;
    }
    $estimatedCost = ($estimatedInputTokens * LI_NARRATIVE_INPUT_USD_PER_TOKEN)
        + ($estimatedOutputTokens * LI_NARRATIVE_OUTPUT_USD_PER_TOKEN);
    $maxCost = (float) li_config('narrative_max_cost_usd_per_scan', 0.02);
    if ($estimatedCost > $maxCost) {
        return null;
    }

    $model = trim((string) li_config('anthropic_model', 'claude-haiku-4-5-20251001'));
    if ($model === '') {
        $model = 'claude-haiku-4-5-20251001';
    }

    $systemPrompt = LI_NARRATIVE_SYSTEM_PROMPT;
    if ($withDescriptionAlignment) {
        $systemPrompt .= LI_NARRATIVE_DESCRIPTION_PROMPT;
    }
    if ($withDistinctiveness) {
        $systemPrompt .= LI_NARRATIVE_DISTINCTIVENESS_PROMPT;
    }
    if ($withContextProfiel) {
        $systemPrompt .= LI_NARRATIVE_CONTEXT_PROMPT;
    }
    if ($withRepeatability) {
        $systemPrompt .= LI_NARRATIVE_REPEATABILITY_PROMPT;
    }

    $maxTokens = 512;
    if ($withDistinctiveness || $withDescriptionAlignment || $withRepeatability) {
        $maxTokens = 800;
    }
    if (($withDistinctiveness && $withDescriptionAlignment) || ($withRepeatability && ($withDistinctiveness || $withDescriptionAlignment))) {
        $maxTokens = 900;
    }

    try {
        $payload = json_encode([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userMessage]],
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    $response = li_narrative_anthropic_post($apiKey, $payload, 60);
    if ($response === null) {
        return null;
    }

    $inputTokens = isset($response['usage']['input_tokens']) ? (int) $response['usage']['input_tokens'] : null;
    $outputTokens = isset($response['usage']['output_tokens']) ? (int) $response['usage']['output_tokens'] : null;
    $costUsd = null;
    if ($inputTokens !== null && $outputTokens !== null) {
        $costUsd = round(
            ($inputTokens * LI_NARRATIVE_INPUT_USD_PER_TOKEN)
            + ($outputTokens * LI_NARRATIVE_OUTPUT_USD_PER_TOKEN),
            6,
        );
    }

    if ($costPdo !== null && $costScanId !== null && trim($costScanId) !== '' && $costUsd !== null && $costUsd > 0) {
        li_cost_log_record($costPdo, trim($costScanId), 'claude', $costUsd);
    }

    $parsed = li_narrative_parse_response(
        $response,
        $withDistinctiveness,
        $withDescriptionAlignment,
        $withRepeatability,
        count($topPosts),
    );
    if ($parsed === null) {
        return null;
    }

    $parsed['input_tokens'] = $inputTokens;
    $parsed['output_tokens'] = $outputTokens;
    $parsed['cost_usd'] = $costUsd;

    return $parsed;
}

/** @return array<string, mixed>|null */
function li_narrative_anthropic_post(string $apiKey, string $payload, int $timeout = 60): ?array
{
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        return null;
    }

    $data = json_decode((string) $body, true);

    return is_array($data) ? $data : null;
}

/**
 * @param array<string, mixed> $response
 *
 * @return array<string, mixed>|null
 */
function li_narrative_parse_response(
    array $response,
    bool $expectDistinctiveness = false,
    bool $expectDescriptionAlignment = false,
    bool $expectRepeatability = false,
    int $topPostsCount = 0,
): ?array {
    $text = '';
    foreach (($response['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'text') {
            $text .= (string) ($block['text'] ?? '');
        }
    }

    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
        $text = trim($matches[1]);
    }

    $data = json_decode($text, true);
    if (!is_array($data)) {
        return null;
    }

    $coreMessage = trim((string) ($data['core_message'] ?? ''));
    $reasoning = trim((string) ($data['consistency_reasoning'] ?? ''));
    $label = (string) ($data['consistency_label'] ?? '');

    if ($coreMessage === '' || $reasoning === '' || !in_array($label, ['hoog', 'gemiddeld', 'laag'], true)) {
        return null;
    }

    $themes = [];
    foreach (($data['recurring_themes'] ?? []) as $theme) {
        $theme = trim((string) $theme);
        if ($theme !== '') {
            $themes[] = $theme;
        }
    }
    if ($themes === []) {
        return null;
    }

    $examples = [];
    foreach (($data['inconsistency_examples'] ?? []) as $example) {
        $example = trim((string) $example);
        if ($example !== '') {
            $examples[] = $example;
        }
    }

    if ($label === 'hoog') {
        $examples = [];
    }

    $result = [
        'core_message' => $coreMessage,
        'recurring_themes' => $themes,
        'consistency_label' => $label,
        'consistency_reasoning' => $reasoning,
        'inconsistency_examples' => $examples,
    ];

    if ($expectDescriptionAlignment) {
        $alignmentLabel = (string) ($data['description_alignment_label'] ?? '');
        $alignmentReasoning = trim((string) ($data['description_alignment_reasoning'] ?? ''));
        if ($alignmentReasoning === '' || !in_array($alignmentLabel, ['ja', 'deels', 'nee'], true)) {
            return null;
        }
        $result['description_alignment_label'] = $alignmentLabel;
        $result['description_alignment_reasoning'] = $alignmentReasoning;
    }

    if ($expectDistinctiveness) {
        $distinctLabel = (string) ($data['distinctiveness_label'] ?? '');
        $distinctReasoning = trim((string) ($data['distinctiveness_reasoning'] ?? ''));
        if ($distinctReasoning === '' || !in_array($distinctLabel, ['ja', 'gedeeltelijk', 'nee'], true)) {
            return null;
        }

        $matchedCliches = [];
        foreach (($data['matched_cliches'] ?? []) as $cliche) {
            $cliche = trim((string) $cliche);
            if ($cliche !== '') {
                $matchedCliches[] = $cliche;
            }
        }
        $matchedCliches = array_slice($matchedCliches, 0, 3);
        if ($distinctLabel === 'ja') {
            $matchedCliches = [];
        }

        $result['distinctiveness_label'] = $distinctLabel;
        $result['distinctiveness_reasoning'] = $distinctReasoning;
        $result['matched_cliches'] = $matchedCliches;
    }

    if ($expectRepeatability) {
        if ($topPostsCount < 1 || !isset($data['repeatable_best_post_index']) || !is_numeric($data['repeatable_best_post_index'])) {
            return null;
        }
        $repeatIdx = (int) $data['repeatable_best_post_index'];
        if ($repeatIdx < 0 || $repeatIdx >= $topPostsCount) {
            return null;
        }
        $repeatReasoning = trim((string) ($data['repeatable_best_post_reasoning'] ?? ''));
        if ($repeatReasoning === '') {
            return null;
        }
        $result['repeatable_best_post_index'] = $repeatIdx;
        $result['repeatable_best_post_reasoning'] = $repeatReasoning;
        if ($repeatIdx !== 0) {
            $excludedReason = trim((string) ($data['repeatable_best_post_excluded_reason'] ?? ''));
            if ($excludedReason === '') {
                return null;
            }
            $result['repeatable_best_post_excluded_reason'] = $excludedReason;
        }

        $reinforces = (string) ($data['repeatable_best_post_reinforces_positioning'] ?? '');
        if (in_array($reinforces, ['ja', 'nee', 'onduidelijk'], true)) {
            $result['repeatable_best_post_reinforces_positioning'] = $reinforces;
        }
    }

    return li_narrative_polish_result($result);
}

/** Verwijder redeneer-parentheses zoals "(implicit in '…')" of "(impliciet in '…')". */
function li_narrative_strip_reasoning_parentheses(string $text): string
{
    if ($text === '') {
        return $text;
    }

    // Vangt: (implicit in …), (explicit in …), (impliciet/expliciet in …),
    // (implicit/explicit in …) — inclusief gemengde slash-vormen.
    return (string) preg_replace(
        '/\s*\(\s*(?:(?:im|ex)plici(?:et|t)(?:\s*\/\s*(?:im|ex)plici(?:et|t))?)\s+in\b[^)]*\)/ui',
        '',
        $text,
    );
}

/** Licht corrigeren van bekende LLM-anglicismen in narrative-tekst. */
function li_narrative_polish_dutch(string $text): string
{
    if ($text === '') {
        return $text;
    }

    // Eerst hele redeneer-parentheses strippen (Engels); daarna cognaten vervangen en
    // opnieuw strippen voor de vertaalde vorm "(impliciet in …)" als die toch doorglipt.
    $text = li_narrative_strip_reasoning_parentheses($text);

    $replacements = [
        '/\bgenuinere\b/ui' => 'echter',
        '/\bgenuiner\b/ui' => 'echter',
        '/\bgenuin\b/ui' => 'echt',
        '/\bauthentiekere\b/ui' => 'eerlijker',
        '/\bauthentieker\b/ui' => 'eerlijker',
        '/\buniquere\b/ui' => 'opvallender',
        '/\buniquer\b/ui' => 'opvallender',
        '/\bengagement\b/ui' => 'reacties',
        '/\bengag(?:e|ement)\s+blijft\b/ui' => 'reacties blijven',
        '/\bimpactful\b/ui' => 'overtuigend',
        '/\bstorytelling\b/ui' => 'verhaal',
        '/\bbrand story\b/ui' => 'merkverhaal',
        '/\bnext[- ]level\b/ui' => 'volgende stap',
        '/\bgame[- ]changer\b/ui' => 'doorbraak',
        '/\bempower(?:ing|ment)?\b/ui' => 'versterken',
        '/\bmensgerichteerd\b/ui' => 'mensgericht',
        '/\bklantgerichteerd\b/ui' => 'klantgericht',
        '/\bdatagedreveerd\b/ui' => 'datagedreven',
        '/\bimplicit\b/ui' => 'impliciet',
        '/\bexplicit\b/ui' => 'expliciet',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $text = (string) preg_replace($pattern, $replacement, $text);
    }

    $text = li_narrative_strip_reasoning_parentheses($text);

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

/** @param list<string> $items @return list<string> */
function li_narrative_polish_dutch_list(array $items): array
{
    $out = [];
    foreach ($items as $item) {
        $polished = li_narrative_polish_dutch(trim((string) $item));
        if ($polished !== '') {
            $out[] = $polished;
        }
    }

    return $out;
}

/** @param array<string, mixed> $result @return array<string, mixed> */
function li_narrative_polish_result(array $result): array
{
    $result['core_message'] = li_narrative_polish_dutch((string) ($result['core_message'] ?? ''));
    $result['consistency_reasoning'] = li_narrative_polish_dutch((string) ($result['consistency_reasoning'] ?? ''));
    $result['recurring_themes'] = li_narrative_polish_dutch_list(
        is_array($result['recurring_themes'] ?? null) ? $result['recurring_themes'] : [],
    );
    $result['inconsistency_examples'] = li_narrative_polish_dutch_list(
        is_array($result['inconsistency_examples'] ?? null) ? $result['inconsistency_examples'] : [],
    );

    if (isset($result['distinctiveness_reasoning'])) {
        $result['distinctiveness_reasoning'] = li_narrative_polish_dutch((string) $result['distinctiveness_reasoning']);
    }
    if (isset($result['description_alignment_reasoning'])) {
        $result['description_alignment_reasoning'] = li_narrative_polish_dutch((string) $result['description_alignment_reasoning']);
    }
    if (isset($result['repeatable_best_post_reasoning'])) {
        $result['repeatable_best_post_reasoning'] = li_narrative_polish_dutch((string) $result['repeatable_best_post_reasoning']);
    }
    if (isset($result['repeatable_best_post_excluded_reason'])) {
        $result['repeatable_best_post_excluded_reason'] = li_narrative_polish_dutch((string) $result['repeatable_best_post_excluded_reason']);
    }
    if (isset($result['matched_cliches'])) {
        $result['matched_cliches'] = li_narrative_polish_dutch_list(
            is_array($result['matched_cliches']) ? $result['matched_cliches'] : [],
        );
    }

    return $result;
}
