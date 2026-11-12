<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-contentflow-schema.php';
require_once __DIR__ . '/hba-repository.php';
require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';

function ce_voice_profile_threshold(): int
{
    $raw = getenv('CE_VOICE_PROFILE_MIN_POSTS');
    $value = is_string($raw) && $raw !== '' ? (int) $raw : 20;

    return max(5, $value);
}

function ce_count_published_posts(PDO $pdo, string $clientId): int
{
    if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'content_slots')) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM articles a
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND a.status IN ('published', 'archived')"
    );
    $stmt->execute(['client_id' => $clientId]);

    return (int) $stmt->fetchColumn();
}

/** @return list<array{headline: string, content: string}> */
function ce_fetch_recent_published_posts(PDO $pdo, string $clientId, int $limit = 20): array
{
    if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'content_slots')) {
        return [];
    }
    $headlineCol = ce_table_has_column($pdo, 'articles', 'headline_human') ? 'headline_human' : 'headline';
    $contentCol = ce_table_has_column($pdo, 'articles', 'content_human') ? 'content_human' : 'content_ai';
    $order = 'a.id DESC';
    if (ce_table_has_column($pdo, 'articles', 'updated_at')) {
        $order = 'a.updated_at DESC, a.id DESC';
    } elseif (ce_table_has_column($pdo, 'articles', 'created_at')) {
        $order = 'a.created_at DESC, a.id DESC';
    }
    $stmt = $pdo->prepare(
        "SELECT a.headline, a.{$headlineCol} AS headline_use, a.{$contentCol} AS content_use, a.content_ai
         FROM articles a
         INNER JOIN content_slots cs ON cs.id = a.slot_id
         WHERE cs.client_id = :client_id
           AND a.status IN ('published', 'archived')
         ORDER BY {$order}
         LIMIT {$limit}"
    );
    $stmt->execute(['client_id' => $clientId]);
    $rows = $stmt->fetchAll() ?: [];
    $out = [];
    foreach ($rows as $row) {
        $headline = trim((string) ($row['headline_use'] ?? $row['headline'] ?? ''));
        $content = trim((string) ($row['content_use'] ?? $row['content_ai'] ?? ''));
        if ($headline === '' && $content === '') {
            continue;
        }
        $out[] = ['headline' => $headline, 'content' => mb_substr($content, 0, 800)];
    }

    return $out;
}

/** @return array<string, mixed>|null */
function ce_voice_profile_latest(PDO $pdo, string $clientId): ?array
{
    ce_ensure_hba_contentflow_schema($pdo);
    if (!ce_table_exists($pdo, 'hba_voice_profiles')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM hba_voice_profiles WHERE client_id = :client_id ORDER BY analyzed_at DESC LIMIT 1'
    );
    $stmt->execute(['client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    return [
        'id' => (string) ($row['id'] ?? ''),
        'publishedCount' => (int) ($row['published_count'] ?? 0),
        'recurringAngles' => ce_hba_decode_json_list($row['recurring_angles'] ?? []),
        'typicalFormulations' => ce_hba_decode_json_list($row['typical_formulations'] ?? []),
        'typicalInvalshoeken' => ce_hba_decode_json_list($row['typical_invalshoeken'] ?? []),
        'analyzedAt' => (string) ($row['analyzed_at'] ?? ''),
    ];
}

function ce_voice_profile_refresh(PDO $pdo, string $clientId): array
{
    $published = ce_count_published_posts($pdo, $clientId);
    $threshold = ce_voice_profile_threshold();
    if ($published < $threshold) {
        return [
            'ok' => true,
            'active' => false,
            'publishedCount' => $published,
            'targetPosts' => $threshold,
            'profile' => null,
        ];
    }

    $existing = ce_voice_profile_latest($pdo, $clientId);
    if (is_array($existing) && (int) ($existing['publishedCount'] ?? 0) >= $published) {
        return [
            'ok' => true,
            'active' => true,
            'publishedCount' => $published,
            'targetPosts' => $threshold,
            'profile' => $existing,
        ];
    }

    $posts = ce_fetch_recent_published_posts($pdo, $clientId, 20);
    $profile = [
        'recurring_angles' => [],
        'typical_formulations' => [],
        'typical_invalshoeken' => [],
    ];

    if ($posts !== [] && function_exists('ce_hba_v2_call_json')) {
        $blob = '';
        foreach ($posts as $index => $post) {
            $blob .= ($index + 1) . ". {$post['headline']}\n{$post['content']}\n\n";
        }
        $llm = ce_hba_v2_call_json(
            'Je extraheert het stem-patroon uit eerdere posts. Geen merkanalyse, alleen terugkerende hoeken, formuleringen en invalshoeken. Nieuwe posts moeten herkenbaar zijn binnen dit patroon, niet identiek.',
            "Geef JSON:\n{\"recurring_angles\":[],\"typical_formulations\":[],\"typical_invalshoeken\":[]}\n\nPOSTS:\n{$blob}",
            0.2,
            2048,
        );
        if ($llm['ok']) {
            $profile['recurring_angles'] = array_values(array_filter(array_map('strval', is_array($llm['data']['recurring_angles'] ?? null) ? $llm['data']['recurring_angles'] : [])));
            $profile['typical_formulations'] = array_values(array_filter(array_map('strval', is_array($llm['data']['typical_formulations'] ?? null) ? $llm['data']['typical_formulations'] : [])));
            $profile['typical_invalshoeken'] = array_values(array_filter(array_map('strval', is_array($llm['data']['typical_invalshoeken'] ?? null) ? $llm['data']['typical_invalshoeken'] : [])));
        }
    }

    $id = ce_uuid();
    $stmt = $pdo->prepare(
        'INSERT INTO hba_voice_profiles (id, client_id, published_count, recurring_angles, typical_formulations, typical_invalshoeken)
         VALUES (:id, :client_id, :published_count, :angles, :formulations, :invalshoeken)'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'published_count' => $published,
        'angles' => json_encode($profile['recurring_angles'], JSON_UNESCAPED_UNICODE),
        'formulations' => json_encode($profile['typical_formulations'], JSON_UNESCAPED_UNICODE),
        'invalshoeken' => json_encode($profile['typical_invalshoeken'], JSON_UNESCAPED_UNICODE),
    ]);

    return [
        'ok' => true,
        'active' => true,
        'publishedCount' => $published,
        'targetPosts' => $threshold,
        'profile' => [
            'id' => $id,
            'publishedCount' => $published,
            'recurringAngles' => $profile['recurring_angles'],
            'typicalFormulations' => $profile['typical_formulations'],
            'typicalInvalshoeken' => $profile['typical_invalshoeken'],
        ],
    ];
}

function ce_voice_profile_directive_lines(PDO $pdo, string $clientId): string
{
    $published = ce_count_published_posts($pdo, $clientId);
    $threshold = ce_voice_profile_threshold();
    if ($published < $threshold) {
        return 'STEM: het merk wordt nog opgebouwd (' . $published . '/' . $threshold
            . ' posts). Gebruik architectuur + kern-bronnen voor perspectief. Doe niet alsof er al een vast stempatroon is.';
    }

    $state = ce_voice_profile_refresh($pdo, $clientId);
    $profile = is_array($state['profile'] ?? null) ? $state['profile'] : null;
    if ($profile === null) {
        return 'STEM: varieer binnen herkenbaarheid. Vermijd letterlijke herhaling van eerdere openingszinnen.';
    }

    $lines = ['STEM (variatie binnen herkenbaarheid, niet identiek):'];
    if (($profile['recurringAngles'] ?? []) !== []) {
        $lines[] = 'Terugkerende hoeken: ' . implode('; ', $profile['recurringAngles']) . '.';
    }
    if (($profile['typicalFormulations'] ?? []) !== []) {
        $lines[] = 'Typische formuleringen (herkenbaar houden, niet kopiëren): ' . implode('; ', $profile['typicalFormulations']) . '.';
    }
    if (($profile['typicalInvalshoeken'] ?? []) !== []) {
        $lines[] = 'Typische invalshoeken: ' . implode('; ', $profile['typicalInvalshoeken']) . '.';
    }

    return implode("\n", $lines);
}

function ce_core_source_categories(): array
{
    return ['url_rss', 'blogs', 'document', 'linkedin', 'youtube', 'drive'];
}

function ce_default_source_tier(string $category): string
{
    return in_array($category, ce_core_source_categories(), true) ? 'core' : 'supplementary';
}

function ce_client_has_core_source(PDO $pdo, ?string $clientId): bool
{
    if ($clientId === null || trim($clientId) === '') {
        return false;
    }
    if (!ce_table_exists($pdo, 'sources') || !ce_table_has_column($pdo, 'sources', 'source_tier')) {
        return false;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM sources WHERE client_id = :client_id AND source_tier = 'core' AND active = 1 AND category <> 'asana'"
    );
    $stmt->execute(['client_id' => $clientId]);

    return (int) $stmt->fetchColumn() > 0;
}

/** @return array{core: list<string>, supplementary: list<string>} */
function ce_source_context_split(PDO $pdo, string $clientId): array
{
    $core = [];
    $extra = [];
    if (!ce_table_exists($pdo, 'sources')) {
        return ['core' => $core, 'supplementary' => $extra];
    }
    $tierSql = ce_table_has_column($pdo, 'sources', 'source_tier')
        ? 'source_tier'
        : "CASE WHEN category IN ('url_rss','blogs','document','linkedin','youtube','drive') THEN 'core' ELSE 'supplementary' END";
    $stmt = $pdo->prepare(
        "SELECT title, url, description, {$tierSql} AS source_tier
         FROM sources WHERE client_id = :client_id AND active = 1 AND category <> 'asana' ORDER BY title ASC"
    );
    $stmt->execute(['client_id' => $clientId]);
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $line = trim((string) ($row['title'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        if ($url !== '') {
            $line .= $line !== '' ? " ({$url})" : $url;
        }
        $desc = trim((string) ($row['description'] ?? ''));
        if ($desc !== '') {
            $line .= ' — ' . mb_substr($desc, 0, 160);
        }
        if ($line === '') {
            continue;
        }
        if (($row['source_tier'] ?? '') === 'core') {
            $core[] = $line;
        } else {
            $extra[] = $line;
        }
    }

    return ['core' => $core, 'supplementary' => $extra];
}

function ce_source_context_directive_lines(PDO $pdo, string $clientId): string
{
    $split = ce_source_context_split($pdo, $clientId);
    $lines = [
        'KERN-BRONNEN (toon, hoek, perspectief, typische invalshoeken — verplicht herkenbaar in de post):',
        $split['core'] === [] ? '- (geen kern-bron; waarschuw de redacteur, verzin geen klantstem)' : '- ' . implode("\n- ", $split['core']),
        'AANVULLENDE BRONNEN (alleen onderwerp, actualiteit, relevantie — nooit de stem vervangen):',
        $split['supplementary'] === [] ? '- (geen)' : '- ' . implode("\n- ", $split['supplementary']),
    ];

    return implode("\n", $lines);
}
