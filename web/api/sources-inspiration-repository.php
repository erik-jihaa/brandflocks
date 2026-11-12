<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/hba-v2-repository.php';
require_once __DIR__ . '/event-log-repository.php';

/** @return list<string> */
function ce_sources_inspiration_allowed_categories(): array
{
    return ['URL/RSS', 'Blogs', 'YouTube', 'Linkedin'];
}

function ce_sources_inspiration_normalize_url(?string $url): string
{
    $url = trim(strtolower((string) $url));
    if ($url === '') {
        return '';
    }

    $url = rtrim($url, '/');
    if (str_starts_with($url, 'http://')) {
        $url = 'https://' . substr($url, 7);
    }

    return $url;
}

/** @param list<array<string, mixed>> $sources */
function ce_sources_inspiration_existing_url_set(array $sources): array
{
    $set = [];
    foreach ($sources as $source) {
        $url = ce_sources_inspiration_normalize_url((string) ($source['url'] ?? ''));
        if ($url !== '') {
            $set[$url] = true;
        }
        $profile = ce_sources_inspiration_normalize_url((string) ($source['scraperLinkedinProfile'] ?? ''));
        if ($profile !== '') {
            $set[$profile] = true;
        }
    }

    return $set;
}

/** @param list<array<string, mixed>> $sources */
function ce_sources_inspiration_format_existing_sources(array $sources): string
{
    if ($sources === []) {
        return 'Nog geen sources toegevoegd.';
    }

    $lines = [];
    foreach ($sources as $source) {
        $category = (string) ($source['category'] ?? '');
        $title = trim((string) ($source['title'] ?? ''));
        $url = trim((string) ($source['url'] ?? ''));
        $profile = trim((string) ($source['scraperLinkedinProfile'] ?? ''));
        $description = trim((string) ($source['description'] ?? ''));

        $detail = $title !== '' ? $title : '(zonder titel)';
        if ($url !== '' && !str_contains($url, 'apify.com')) {
            $detail .= ' — ' . $url;
        } elseif ($profile !== '') {
            $detail .= ' — ' . $profile;
        }
        if ($description !== '') {
            $detail .= ' (' . $description . ')';
        }

        $lines[] = sprintf('- [%s] %s', $category, $detail);
    }

    return implode("\n", $lines);
}

/** @param array<string, mixed> $strategy */
function ce_sources_inspiration_format_strategy(array $strategy): string
{
    $parts = [];
    $fields = [
        'companyName' => 'Bedrijf',
        'archetype' => 'Archetype',
        'adversary' => 'Tegenstander / foe',
        'brandstory' => 'Merkdoel / brandstory',
        'brandGoalCategory' => 'Doelcategorie',
        'writingGuide' => 'Schrijfwijzer',
    ];

    foreach ($fields as $key => $label) {
        $value = trim((string) ($strategy[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }

    $toneStyles = $strategy['toneStyles'] ?? null;
    if (is_array($toneStyles) && $toneStyles !== []) {
        $parts[] = 'Tone of voice: ' . implode(', ', array_map('strval', $toneStyles));
    }

    return $parts !== [] ? implode("\n", $parts) : 'Strategie nog niet ingevuld.';
}

/** @param array<string, mixed> $ctx */
function ce_sources_inspiration_format_hba(array $ctx): string
{
    $parts = [];
    $fields = [
        'archetype' => 'HBA-archetype',
        'doel' => 'Doel',
        'foe' => 'Foe',
        'distinctiviteit' => 'Distinctiviteit',
        'wat_bewust_niet' => 'Bewust niet',
        'regels' => 'Regels',
        'tone_of_behaviour' => 'Tone of behaviour',
        'categorietaal' => 'Categorietaal',
        'branche_label' => 'Branche',
    ];

    foreach ($fields as $key => $label) {
        $value = trim((string) ($ctx[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }

    return $parts !== [] ? implode("\n", $parts) : 'HBA-merkarchitectuur nog niet ingevuld.';
}

function ce_sources_inspiration_normalize_scope(mixed $raw): int
{
    if (is_string($raw) && !is_numeric($raw)) {
        $label = strtolower(trim($raw));
        if ($label === 'surprise' || $label === 'verrassend') {
            return 100;
        }
        if ($label === 'average' || $label === 'gemiddeld') {
            return 0;
        }
    }

    $scope = (int) round((float) $raw);

    return max(0, min(100, $scope));
}

function ce_sources_inspiration_scope_temperature(int $scope): float
{
    return round(0.25 + ($scope / 100) * 0.75, 2);
}

function ce_sources_inspiration_scope_guidance(int $scope): string
{
    if ($scope <= 20) {
        return <<<'TXT'
Zoekscope: **Average** — kies betrouwbare, mainstream bronnen die brede acceptatie hebben in de sector.
Vermijd niche of polariserende stemmen. Prioriteit: bekende vakmedia, grote blogs, gevestigde kanalen.
TXT;
    }

    if ($scope <= 55) {
        return <<<'TXT'
Zoekscope: **Gebalanceerd** — mix vertrouwde sectorbronnen met 1–2 minder voor de hand liggende invalshoeken.
Niet alles mag obvious zijn; zoek complementaire perspectieven buiten de bestaande sources.
TXT;
    }

    if ($scope <= 80) {
        return <<<'TXT'
Zoekscope: **Verrassend** — vermijd de meest voor de hand liggende keuzes (grote mainstream media, standaard brancheblogs die iedereen al kent).
Minstens 3 van 5 bronnen moeten onverwacht aanvoelen: scherpe contrarian stemmen, niche-experts, adjacent domains, debat-heavy feeds, internationale outliers.
Elke keuze moet nog steeds strategisch verdedigbaar zijn via HBA foe, distinctiviteit of schrijfwijzer — maar niet safe.
TXT;
    }

    return <<<'TXT'
Zoekscope: **Maximum surprise** — ga bewust off the beaten path.
Geen obvious top-10 branchefeeds, geen LinkedIn-influencer clichés, geen generieke business-podcasts.
Zoek 5 bronnen die een redacteur zou opmerken: ongewone hoek, scherpe stem, verrassend medium, of een tegenstem t.o.v. de foe.
Durven: contrarian newsletters, specialistische substacks, buitenlandse denkers, onderbelichte YouTube-kanalen, LinkedIn-profielen die niet in de comfort zone liggen.
Elke suggestie moet in de description expliciet uitleggen waarom dit verrassend én merk-passend is.
TXT;
}

/** @return list<string> */
function ce_sources_inspiration_allowed_invalshoeken(): array
{
    return ['tegenkracht', 'vakgenoot', 'aanpalend', 'data', 'praktijk', 'cultuur', 'buitenland'];
}

function ce_sources_inspiration_text(mixed $value): string
{
    if (is_string($value) || is_int($value) || is_float($value)) {
        return trim((string) $value);
    }
    if (!is_array($value)) {
        return '';
    }

    $parts = [];
    foreach ($value as $item) {
        if (is_string($item) || is_int($item) || is_float($item)) {
            $parts[] = (string) $item;
        } elseif (is_array($item) && isset($item['text'])) {
            $nested = ce_sources_inspiration_text($item['text']);
            if ($nested !== '') {
                $parts[] = $nested;
            }
        }
    }

    return trim(implode(' ', $parts));
}

/** @return array<string, mixed>|null */
function ce_sources_inspiration_decode_json_text(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text);
    }

    $data = json_decode($text, true);
    if (is_array($data)) {
        return $data;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $data = json_decode(substr($text, $start, $end - $start + 1), true);

    return is_array($data) ? $data : null;
}

function ce_sources_inspiration_log_rejection(
    PDO $pdo,
    string $clientId,
    string $category,
    string $url,
    string $reason,
): void {
    ce_log_event(
        $pdo,
        'info',
        'sources',
        'inspiration_suggestion_rejected',
        'Broninspiratie-suggestie afgewezen',
        [
            'client_id' => $clientId,
            'category' => $category,
            'url' => substr($url, 0, 500),
            'reason' => $reason,
        ],
    );
}

/**
 * @param list<string> $excludeUrls
 * @param list<string> $missingInvalshoeken
 */
function ce_sources_inspiration_build_prompt(
    array $sources,
    array $strategy,
    array $hba,
    int $searchScope,
    int $requestCount,
    array $excludeUrls = [],
    array $missingInvalshoeken = [],
): string {
    $allowed = ce_sources_inspiration_allowed_categories();
    $invalshoeken = ce_sources_inspiration_allowed_invalshoeken();

    $prompt = <<<PROMPT
Je bent content-strateeg voor Brandflocks. Stel {{REQUEST_COUNT}} nieuwe research-sources voor die passen bij dit merk.

## Bestaande sources
{{EXISTING_SOURCES}}

Kijk waar de bestaande set blind is — welk medium, welke invalshoek, welke geografie ontbreekt — en vul dáár aan. Vermijd duplicaten van bestaande URLs of profielen.

## Strategie
{{STRATEGY}}

## HBA merkarchitectuur
{{HBA}}

## Zoekscope
{{SEARCH_SCOPE}}

## Opdracht
- Gebruik echte, publiek toegankelijke URLs (RSS-feed, blog-overzicht, YouTube-kanaal, LinkedIn-profiel). Verzin geen URL die je niet kent; laat die suggestie dan weg.
- Geef {{REQUEST_COUNT}} concrete bronnen. Alleen categorieën uit: {{ALLOWED_CATEGORIES}}
- Elke suggestie moet verrassend én verdedigbaar zijn voor dit merk (foe, distinctiviteit, doelgroep) — niet alleen "relevant voor het onderwerp".
- Kan `verrassend_omdat` niet overtuigend ingevuld worden, laat die suggestie weg en kies een andere bron.
- Spreid over categorieën: niet {{REQUEST_COUNT}} keer hetzelfde medium.
- De uiteindelijke selectie moet vijf VERSCHILLENDE invalshoeken bestrijken. Kies per suggestie uit:
  tegenkracht   — benoemt precies waar dit merk tegen vecht
  vakgenoot     — een scherpe stem binnen de eigen sector
  aanpalend     — ander vakgebied, zelfde mechanisme
  data          — cijfers of onderzoek over wat mensen werkelijk doen
  praktijk      — de dagelijkse realiteit van de doelgroep
  cultuur       — het onderwerp als maatschappelijk of cultureel verschijnsel
  buitenland    — een stem van buiten het eigen taalgebied
  Elke invalshoek maximaal één keer in je antwoord.
- Voor Linkedin: url = linkedin.com/in/... of linkedin.com/company/...
- Voor URL/RSS: echte RSS/Atom-feeds met minstens 1 item.
- Voor Blogs: overzichtspagina of sitemap van een relevant blog/nieuwsmedium.
- Voor YouTube: kanaal-URL (/@handle of /channel/...) of het Atom-feed-URL van het kanaal.
- Schrijf in het Nederlands.
{{EXCLUDE_BLOCK}}

Antwoord uitsluitend met JSON in dit formaat:
{
  "suggestions": [
    {
      "category": "YouTube",
      "title": "Adam Ragusea",
      "stelling": "koken zonder mythes",
      "invalshoek": "praktijk",
      "wat": "Voormalig journalist die kookvideo's maakt vanuit één obsessie: waarom werkt dit eigenlijk?",
      "verrassend_omdat": "Raakt de overlap tussen kritisch denken en alledaags eten — contramal voor de anti-drempelpositie.",
      "url": "https://www.youtube.com/feeds/videos.xml?channel_id=..."
    }
  ]
}
PROMPT;

    $excludeBlock = '';
    if ($excludeUrls !== []) {
        $excludeBlock = "\n## Uitsluiten\nDeze URLs/profielen zijn al geprobeerd of geselecteerd — niet opnieuw voorstellen:\n"
            . implode("\n", array_map(static fn (string $u): string => '- ' . $u, $excludeUrls));
    }
    if ($missingInvalshoeken !== []) {
        $excludeBlock .= "\n\nNog nodig (invalshoeken die nog niet gedekt zijn): "
            . implode(', ', $missingInvalshoeken);
    }

    $prompt = str_replace(
        [
            '{{REQUEST_COUNT}}',
            '{{EXISTING_SOURCES}}',
            '{{STRATEGY}}',
            '{{HBA}}',
            '{{ALLOWED_CATEGORIES}}',
            '{{SEARCH_SCOPE}}',
            '{{EXCLUDE_BLOCK}}',
        ],
        [
            (string) $requestCount,
            ce_sources_inspiration_format_existing_sources($sources),
            ce_sources_inspiration_format_strategy($strategy),
            ce_sources_inspiration_format_hba($hba),
            implode(', ', $allowed),
            ce_sources_inspiration_scope_guidance($searchScope),
            $excludeBlock,
        ],
        $prompt,
    );

    return $prompt;
}

/** @return array{ok: bool, data?: array<string, mixed>, error?: string} */
function ce_sources_inspiration_call_llm(string $systemPrompt, string $userPrompt, float $temperature): array
{
    $keys = ce_llm_visibility_api_keys();
    $key = trim((string) ($keys['anthropic'] ?? ''));
    if ($key === '') {
        return ['ok' => false, 'error' => 'ANTHROPIC_API_KEY ontbreekt'];
    }

    $payload = json_encode([
        'model' => ce_hba_v2_model(),
        'max_tokens' => 1800,
        'temperature' => max(0.0, min(1.0, $temperature)),
        'system' => $systemPrompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $userPrompt . "\n\nAntwoord uitsluitend met geldige JSON, zonder markdown fences.",
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($payload === false) {
        return ['ok' => false, 'error' => 'Kon LLM-request niet encoderen'];
    }

    $res = ce_llm_visibility_http_post(
        'https://api.anthropic.com/v1/messages',
        [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        $payload,
        55,
    );
    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error'] !== '' ? $res['error'] : 'LLM-call mislukt'];
    }

    $raw = json_decode($res['body'], true);
    if (!is_array($raw)) {
        return ['ok' => false, 'error' => 'Ongeldig Anthropic-antwoord'];
    }

    $text = '';
    foreach (($raw['content'] ?? []) as $block) {
        if (is_array($block) && ($block['type'] ?? '') === 'text') {
            $text .= ce_sources_inspiration_text($block['text'] ?? '');
        }
    }

    $data = ce_sources_inspiration_decode_json_text($text);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'LLM gaf geen geldige JSON terug'];
    }

    return ['ok' => true, 'data' => $data];
}

/**
 * @param array<string, mixed> $item
 * @return array{suggestion?: array<string, string>, reason?: string}
 */
function ce_sources_inspiration_parse_suggestion_item(array $item): array
{
    $category = ce_sources_inspiration_text($item['category'] ?? '');
    $title = ce_sources_inspiration_text($item['title'] ?? '');
    $stelling = ce_sources_inspiration_text($item['stelling'] ?? '');
    $invalshoek = ce_sources_inspiration_text($item['invalshoek'] ?? '');
    $wat = ce_sources_inspiration_text($item['wat'] ?? '');
    $verrassend = ce_sources_inspiration_text($item['verrassend_omdat'] ?? ($item['verrassendOmdat'] ?? ''));
    $url = ce_sources_inspiration_text($item['url'] ?? '');
    $legacyDescription = ce_sources_inspiration_text($item['description'] ?? ($item['reason'] ?? ''));

    if ($wat === '' && $legacyDescription !== '') {
        $wat = $legacyDescription;
    }
    if ($verrassend === '' && $legacyDescription !== '' && $wat !== $legacyDescription) {
        $verrassend = $legacyDescription;
    }

    if ($title === '') {
        return ['reason' => 'missing_title'];
    }
    if ($wat === '' && $legacyDescription === '') {
        return ['reason' => 'missing_wat'];
    }
    if ($verrassend === '') {
        return ['reason' => 'missing_verrassend_omdat'];
    }
    if (!in_array($invalshoek, ce_sources_inspiration_allowed_invalshoeken(), true)) {
        return ['reason' => 'invalid_invalshoek'];
    }

    $description = $wat;
    if ($verrassend !== '') {
        $description .= ($description !== '' ? "\n\n" : '') . 'Verrassend omdat: ' . $verrassend;
    }

    return [
        'suggestion' => [
            'category' => $category,
            'title' => $title,
            'stelling' => $stelling,
            'invalshoek' => $invalshoek,
            'wat' => $wat !== '' ? $wat : $legacyDescription,
            'verrassendOmdat' => $verrassend,
            'url' => $url,
            'description' => $description,
        ],
    ];
}

/**
 * @param array<string, string> $existingUrls
 * @param array<string, bool> $seenUrls
 * @return array{suggestion?: array<string, string>, reason?: string}
 */
function ce_sources_inspiration_try_validate_item(
    PDO $pdo,
    string $clientId,
    array $item,
    array $allowed,
    array $existingUrls,
    array &$seenUrls,
    array &$usedInvalshoeken,
): array {
    if (!is_array($item)) {
        return ['reason' => 'invalid_item'];
    }

    $parsed = ce_sources_inspiration_parse_suggestion_item($item);
    if (!isset($parsed['suggestion'])) {
        $reason = (string) ($parsed['reason'] ?? 'invalid_fields');
        ce_sources_inspiration_log_rejection(
            $pdo,
            $clientId,
            trim((string) ($item['category'] ?? '')),
            trim((string) ($item['url'] ?? '')),
            $reason,
        );

        return ['reason' => $reason];
    }

    $suggestion = $parsed['suggestion'];
    $category = $suggestion['category'];
    $url = $suggestion['url'];

    if (!in_array($category, $allowed, true)) {
        ce_sources_inspiration_log_rejection($pdo, $clientId, $category, $url, 'category_not_allowed');

        return ['reason' => 'category_not_allowed'];
    }

    if (isset($usedInvalshoeken[$suggestion['invalshoek']])) {
        ce_sources_inspiration_log_rejection($pdo, $clientId, $category, $url, 'duplicate_invalshoek');

        return ['reason' => 'duplicate_invalshoek'];
    }

    if ($url !== '') {
        $probe = ce_sources_inspiration_probe_suggestion($category, $url);
        if (!($probe['ok'] ?? false)) {
            ce_sources_inspiration_log_rejection($pdo, $clientId, $category, $url, 'probe_failed');

            return ['reason' => 'probe_failed'];
        }
        $url = trim((string) ($probe['url'] ?? $url));
        $suggestion['url'] = $url;
    } elseif ($category !== 'Linkedin') {
        ce_sources_inspiration_log_rejection($pdo, $clientId, $category, $url, 'missing_url');

        return ['reason' => 'missing_url'];
    }

    $normalizedUrl = ce_sources_inspiration_normalize_url($url);
    if ($normalizedUrl !== '') {
        if (isset($existingUrls[$normalizedUrl]) || isset($seenUrls[$normalizedUrl])) {
            ce_sources_inspiration_log_rejection($pdo, $clientId, $category, $url, 'duplicate');

            return ['reason' => 'duplicate'];
        }
        $seenUrls[$normalizedUrl] = true;
    }

    $usedInvalshoeken[$suggestion['invalshoek']] = true;

    return ['suggestion' => $suggestion];
}

/**
 * @param list<array<string, string>> $pool
 * @return list<array<string, string>>
 */
function ce_sources_inspiration_pick_best_five(array $pool): array
{
    $selected = [];
    $angles = [];
    foreach ($pool as $item) {
        $angle = (string) ($item['invalshoek'] ?? '');
        if ($angle === '' || isset($angles[$angle])) {
            continue;
        }
        $angles[$angle] = true;
        $selected[] = $item;
        if (count($selected) >= 5) {
            break;
        }
    }

    return $selected;
}

/** @return array{ok: bool, url: string} */
function ce_sources_inspiration_probe_suggestion(string $category, string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'url' => ''];
    }

    if ($category === 'Linkedin') {
        if (!preg_match('#linkedin\.com/(in|company)/#i', $url)) {
            return ['ok' => false, 'url' => $url];
        }

        return ['ok' => true, 'url' => $url];
    }

    if (!preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'url' => $url];
    }

    return ['ok' => true, 'url' => $url];
}

/** Gerichte bronsuggestie bij een aandachtspunt (briefing aandachtspunten §3). */
function ce_sources_inspiration_build_claim_prompt(
    array $sources,
    array $strategy,
    array $hba,
    string $claimContext,
): string {
    return "Je bent content-strateeg voor Brandflocks. Een artikel doet een claim die niet goed onderbouwd is.\n\n"
        . "## Claim in het artikel\n"
        . $claimContext
        . "\n\n## Bestaande sources\n"
        . ce_sources_inspiration_format_existing_sources($sources)
        . "\n\n## Strategie\n"
        . ce_sources_inspiration_format_strategy($strategy)
        . "\n\n## HBA merkarchitectuur\n"
        . ce_sources_inspiration_format_hba($hba)
        . "\n\n## Opdracht\n"
        . "Welke terugkerende bron zou dit soort claims structureel onderbouwen voor dit merk?\n"
        . "Geef precies 3 suggesties (URL/RSS, Blogs, YouTube of Linkedin) met echte, publiek toegankelijke URLs.\n"
        . "Dit levert een bron voor volgende artikelen — niet directe onderbouwing voor dít artikel.\n\n"
        . "Antwoord uitsluitend met JSON:\n"
        . "{\n  \"suggestions\": [\n    {\n      \"category\": \"Blogs\",\n      \"title\": \"...\",\n"
        . "      \"stelling\": \"...\",\n      \"invalshoek\": \"data\",\n      \"wat\": \"...\",\n"
        . "      \"verrassend_omdat\": \"...\",\n      \"url\": \"https://...\"\n    }\n  ]\n}";
}

/** @return array{ok: bool, suggestions?: list<array<string, string>>, error?: string} */
function ce_sources_inspiration_run_for_claim(PDO $pdo, string $claimContext): array
{
    $claimContext = trim($claimContext);
    if ($claimContext === '') {
        return ['ok' => false, 'error' => 'Claim ontbreekt'];
    }

    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        return ['ok' => false, 'error' => 'Geen client gevonden'];
    }

    $clientRow = ce_fetch_client_row($pdo);
    if (!$clientRow) {
        return ['ok' => false, 'error' => 'Geen client gevonden'];
    }

    $sources = ce_fetch_all_sources($pdo);
    $strategy = ce_strategy_dto($pdo, $clientRow);
    $hba = ce_hba_v2_architecture_context($pdo, $clientId);
    $allowed = ce_sources_inspiration_allowed_categories();
    $seenUrls = [];

    $prompt = ce_sources_inspiration_build_claim_prompt($sources, $strategy, $hba, $claimContext);
    $llm = ce_sources_inspiration_call_llm(
        'Je antwoordt uitsluitend met geldige JSON volgens de opdracht.',
        $prompt,
        0.35,
    );
    if (!$llm['ok'] || !is_array($llm['data'])) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Broninspiratie mislukt'];
    }

    $rawSuggestions = $llm['data']['suggestions'] ?? null;
    if (!is_array($rawSuggestions)) {
        return ['ok' => false, 'error' => 'LLM gaf geen suggestions terug'];
    }

    $validated = [];
    foreach ($rawSuggestions as $item) {
        if (!is_array($item) || count($validated) >= 3) {
            continue;
        }
        $parsed = ce_sources_inspiration_parse_suggestion_item($item);
        if (!isset($parsed['suggestion'])) {
            continue;
        }
        $suggestion = $parsed['suggestion'];
        $category = $suggestion['category'];
        if (!in_array($category, $allowed, true)) {
            continue;
        }
        $url = $suggestion['url'];
        if ($url !== '') {
            $probe = ce_sources_inspiration_probe_suggestion($category, $url);
            if (!($probe['ok'] ?? false)) {
                continue;
            }
            $url = trim((string) ($probe['url'] ?? $url));
            $suggestion['url'] = $url;
            $normalizedUrl = ce_sources_inspiration_normalize_url($url);
            if ($normalizedUrl !== '' && isset($seenUrls[$normalizedUrl])) {
                continue;
            }
            if ($normalizedUrl !== '') {
                $seenUrls[$normalizedUrl] = true;
            }
        } elseif ($category !== 'Linkedin') {
            continue;
        }
        $validated[] = $suggestion;
    }

    if ($validated === []) {
        return ['ok' => false, 'error' => 'Geen bereikbare bronsuggesties gevonden voor deze claim'];
    }

    return ['ok' => true, 'suggestions' => array_slice($validated, 0, 3)];
}

/** @return array{ok: bool, suggestions?: list<array<string, string>>, error?: string, searchScope?: int, droppedProbeFailed?: int} */
function ce_sources_inspiration_run(PDO $pdo, int $searchScope = 50): array
{
    if (function_exists('set_time_limit')) {
        set_time_limit(90);
    }

    $searchScope = ce_sources_inspiration_normalize_scope($searchScope);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        return ['ok' => false, 'error' => 'Geen client gevonden'];
    }

    $clientRow = ce_fetch_client_row($pdo);
    if (!$clientRow) {
        return ['ok' => false, 'error' => 'Geen client gevonden'];
    }

    $sources = ce_fetch_all_sources($pdo);
    $strategy = ce_strategy_dto($pdo, $clientRow);
    $hba = ce_hba_v2_architecture_context($pdo, $clientId);
    $allowed = ce_sources_inspiration_allowed_categories();
    $temperature = ce_sources_inspiration_scope_temperature($searchScope);
    $systemPrompt = 'Je antwoordt uitsluitend met geldige JSON volgens de opdracht. Durf verrassende keuzes wanneer de zoekscope dat vraagt, maar alleen als verrassend_omdat overtuigend is. Gebruik alleen URLs waarvan je zeker bent dat ze bestaan.';

    $existingUrls = ce_sources_inspiration_existing_url_set($sources);
    $seenUrls = [];
    $usedInvalshoeken = [];
    $validated = [];
    $probeFailedCount = 0;
    $excludeUrls = [];
    $llmCalls = 0;
    $maxLlmCalls = 1;

    while (count($validated) < 5 && $llmCalls < $maxLlmCalls) {
        $llmCalls++;
        $requestCount = 5;
        $missingAngles = array_values(array_diff(
            ce_sources_inspiration_allowed_invalshoeken(),
            array_keys($usedInvalshoeken),
        ));

        $prompt = ce_sources_inspiration_build_prompt(
            $sources,
            $strategy,
            $hba,
            $searchScope,
            $requestCount,
            $excludeUrls,
            count($validated) < 5 ? $missingAngles : [],
        );

        $llm = ce_sources_inspiration_call_llm($systemPrompt, $prompt, $temperature);
        if (!$llm['ok'] || !is_array($llm['data'])) {
            if ($validated !== []) {
                break;
            }

            return ['ok' => false, 'error' => $llm['error'] ?? 'Broninspiratie mislukt'];
        }

        $rawSuggestions = $llm['data']['suggestions'] ?? null;
        if (!is_array($rawSuggestions)) {
            if ($validated !== []) {
                break;
            }

            return ['ok' => false, 'error' => 'LLM gaf geen suggestions terug'];
        }

        $probeAttempts = 0;
        foreach ($rawSuggestions as $item) {
            if (!is_array($item) || count($validated) >= 5 || $probeAttempts >= 6) {
                continue;
            }

            $urlHint = ce_sources_inspiration_text($item['url'] ?? '');
            if ($urlHint !== '') {
                $excludeUrls[] = $urlHint;
                $probeAttempts++;
            }

            $result = ce_sources_inspiration_try_validate_item(
                $pdo,
                $clientId,
                $item,
                $allowed,
                $existingUrls,
                $seenUrls,
                $usedInvalshoeken,
            );

            if (($result['reason'] ?? '') === 'probe_failed') {
                $probeFailedCount++;
            }

            if (!isset($result['suggestion'])) {
                continue;
            }

            $validated[] = $result['suggestion'];
            if (count($validated) >= 5) {
                break;
            }
        }
    }

    $validated = ce_sources_inspiration_pick_best_five($validated);

    if ($validated === []) {
        return [
            'ok' => false,
            'error' => 'We vonden wel suggesties, maar de bronnen bleken niet bereikbaar. Probeer opnieuw, of verlaag de scope voor bekendere bronnen.',
            'droppedProbeFailed' => $probeFailedCount,
        ];
    }

    return [
        'ok' => true,
        'suggestions' => $validated,
        'searchScope' => $searchScope,
        'droppedProbeFailed' => $probeFailedCount,
    ];
}
