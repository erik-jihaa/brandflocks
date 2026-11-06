<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';

const CE_LLM_QUESTIONS_MAX = 5;

const CE_LLM_QUESTION_KINDS = ['geo', 'seo'];

function ce_llm_question_kind(?string $kind): string
{
    $k = strtolower(trim((string) ($kind ?? '')));

    return in_array($k, CE_LLM_QUESTION_KINDS, true) ? $k : 'geo';
}

function ce_ensure_llm_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'llm_probleemvragen')) {
        try {
            $pdo->exec(
                "CREATE TABLE llm_probleemvragen (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    client_id CHAR(36) NOT NULL,
                    question TEXT NOT NULL,
                    question_kind VARCHAR(10) NOT NULL DEFAULT 'geo',
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_llm_vragen_client (client_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Tabel bestaat al of geen rechten.
        }
    }

    if (ce_table_exists($pdo, 'llm_probleemvragen') && !ce_table_has_column($pdo, 'llm_probleemvragen', 'question_kind')) {
        try {
            $pdo->exec(
                "ALTER TABLE llm_probleemvragen ADD COLUMN question_kind VARCHAR(10) NOT NULL DEFAULT 'geo'"
            );
            unset($GLOBALS['ce_table_columns']['llm_probleemvragen']);
        } catch (Throwable) {
            // Kolom bestaat al of ALTER niet toegestaan.
        }
    }

    if (!ce_table_exists($pdo, 'llm_visibility_checks')) {
        try {
            $pdo->exec(
                "CREATE TABLE llm_visibility_checks (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    client_id CHAR(36) NOT NULL,
                    question_id CHAR(36) NOT NULL,
                    model VARCHAR(50) NOT NULL,
                    mentioned TINYINT(1) NOT NULL,
                    raw_response TEXT,
                    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_llm_check_client (client_id),
                    INDEX idx_llm_check_question (question_id),
                    INDEX idx_llm_check_checked_at (checked_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Tabel bestaat al of geen rechten.
        }
    }
}

/** @return array<string, mixed> */
function ce_llm_question_dto(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'clientId' => (string) ($row['client_id'] ?? ''),
        'question' => (string) ($row['question'] ?? ''),
        'kind' => ce_llm_question_kind((string) ($row['question_kind'] ?? 'geo')),
        'active' => (bool) ((int) ($row['active'] ?? 1)),
        'createdAt' => (string) ($row['created_at'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function ce_fetch_llm_questions(PDO $pdo, ?string $clientId = null, ?string $kind = null): array
{
    ce_ensure_llm_schema($pdo);
    if (!ce_table_exists($pdo, 'llm_probleemvragen')) {
        return [];
    }

    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }
    if ($clientId === null) {
        return [];
    }

    $sql = 'SELECT * FROM llm_probleemvragen WHERE client_id = :client_id';
    $params = ['client_id' => $clientId];
    if ($kind !== null) {
        $sql .= ' AND question_kind = :question_kind';
        $params['question_kind'] = ce_llm_question_kind($kind);
    }
    $sql .= ' ORDER BY created_at ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = ce_llm_question_dto($row);
    }

    return $out;
}

/** @return array{questions: list<array<string, mixed>>} */
function ce_fetch_llm_questions_payload(PDO $pdo, ?string $clientId = null): array
{
    return [
        'questions' => ce_fetch_llm_questions($pdo, $clientId),
        'geoQuestions' => ce_fetch_llm_questions($pdo, $clientId, 'geo'),
        'seoQuestions' => ce_fetch_llm_questions($pdo, $clientId, 'seo'),
    ];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_create_llm_question(PDO $pdo, array $body): array
{
    ce_ensure_llm_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $kind = ce_llm_question_kind((string) ($body['kind'] ?? 'geo'));
    $existing = ce_fetch_llm_questions($pdo, $clientId, $kind);
    if (count($existing) >= CE_LLM_QUESTIONS_MAX) {
        $label = $kind === 'seo' ? 'SEO-vragen' : 'GEO-vragen';
        throw new InvalidArgumentException('Maximaal ' . CE_LLM_QUESTIONS_MAX . ' ' . $label . ' per klant.');
    }

    $question = trim((string) ($body['question'] ?? ''));
    if ($question === '') {
        throw new InvalidArgumentException('Vul een vraag in.');
    }
    $id = ce_uuid_v4();
    $stmt = $pdo->prepare(
        'INSERT INTO llm_probleemvragen (id, client_id, question, question_kind, active) VALUES (:id, :client_id, :question, :question_kind, :active)'
    );
    $stmt->execute([
        'id' => $id,
        'client_id' => $clientId,
        'question' => $question,
        'question_kind' => $kind,
        'active' => 1,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM llm_probleemvragen WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('LLM-probleemvraag aanmaken mislukt');
    }

    return ['question' => ce_llm_question_dto($row)];
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_patch_llm_question(PDO $pdo, string $questionId, array $body): array
{
    ce_ensure_llm_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $stmt = $pdo->prepare('SELECT * FROM llm_probleemvragen WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $questionId, 'client_id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('LLM-probleemvraag niet gevonden');
    }

    $sets = [];
    $params = ['id' => $questionId, 'client_id' => $clientId];

    if (array_key_exists('question', $body)) {
        $question = trim((string) $body['question']);
        if ($question === '') {
            throw new InvalidArgumentException('Vul een vraag in.');
        }
        $sets[] = 'question = :question';
        $params['question'] = $question;
    }

    if (array_key_exists('active', $body)) {
        $sets[] = 'active = :active';
        $params['active'] = !empty($body['active']) ? 1 : 0;
    }

    if ($sets !== []) {
        $pdo->prepare(
            'UPDATE llm_probleemvragen SET ' . implode(', ', $sets) . ' WHERE id = :id AND client_id = :client_id'
        )->execute($params);
    }

    $stmt = $pdo->prepare('SELECT * FROM llm_probleemvragen WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $questionId]);
    $updated = $stmt->fetch();

    return ['question' => ce_llm_question_dto($updated ?: $row)];
}

/** @return array{ok: bool} */
function ce_delete_llm_question(PDO $pdo, string $questionId): array
{
    ce_ensure_llm_schema($pdo);
    $clientId = ce_default_client_id($pdo);
    if ($clientId === null) {
        throw new RuntimeException('Geen client gevonden');
    }

    $stmt = $pdo->prepare('SELECT id FROM llm_probleemvragen WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $questionId, 'client_id' => $clientId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('LLM-probleemvraag niet gevonden');
    }

    if (ce_table_exists($pdo, 'llm_visibility_checks')) {
        $pdo->prepare('DELETE FROM llm_visibility_checks WHERE question_id = :id')->execute(['id' => $questionId]);
    }
    $pdo->prepare('DELETE FROM llm_probleemvragen WHERE id = :id AND client_id = :client_id')
        ->execute(['id' => $questionId, 'client_id' => $clientId]);

    return ['ok' => true];
}

/**
 * Rapport-sectie "LLM-vindbaarheid" — laatste meting + vorige-maand-vergelijking.
 *
 * @return array{
 *   kind: string,
 *   hasQuestions: bool,
 *   latest: array{checkedAt: string, items: list<array<string, mixed>>, mentionedCount: int, totalCount: int}|null,
 *   previous: array{checkedAt: string, mentionedCount: int, totalCount: int}|null,
 *   advice: list<string>
 * }
 */
function ce_fetch_llm_visibility_report(PDO $pdo, ?string $clientId = null, ?string $kind = null): array
{
    ce_ensure_llm_schema($pdo);
    $kind = ce_llm_question_kind($kind ?? 'geo');
    if ($clientId === null) {
        $clientId = ce_default_client_id($pdo);
    }

    $empty = ['kind' => $kind, 'hasQuestions' => false, 'latest' => null, 'previous' => null, 'advice' => []];
    if ($clientId === null || !ce_table_exists($pdo, 'llm_probleemvragen')) {
        return $empty;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM llm_probleemvragen WHERE client_id = :client_id AND active = 1 AND question_kind = :question_kind'
    );
    $stmt->execute(['client_id' => $clientId, 'question_kind' => $kind]);
    $questionCount = (int) $stmt->fetchColumn();

    if ($questionCount === 0 || !ce_table_exists($pdo, 'llm_visibility_checks')) {
        return array_merge($empty, [
            'hasQuestions' => false,
            'advice' => ce_findability_improvement_advice($kind, null, null, $pdo, $clientId),
        ]);
    }

    $checkedAtStmt = $pdo->prepare(
        <<<'SQL'
SELECT DISTINCT c.checked_at
FROM llm_visibility_checks c
JOIN llm_probleemvragen q ON q.id = c.question_id
WHERE c.client_id = :client_id AND q.question_kind = :question_kind
ORDER BY c.checked_at DESC
LIMIT 2
SQL
    );
    $checkedAtStmt->execute(['client_id' => $clientId, 'question_kind' => $kind]);
    $checkedAts = array_map(static fn (array $r): string => (string) $r['checked_at'], $checkedAtStmt->fetchAll() ?: []);

    if ($checkedAts === []) {
        return [
            'kind' => $kind,
            'hasQuestions' => true,
            'latest' => null,
            'previous' => null,
            'advice' => ce_findability_improvement_advice($kind, null, null, $pdo, $clientId),
        ];
    }

    $fetchGroup = function (string $checkedAt) use ($pdo, $clientId, $kind): array {
        $stmt = $pdo->prepare(
            <<<'SQL'
SELECT q.id AS question_id, q.question, c.model, c.mentioned
FROM llm_visibility_checks c
JOIN llm_probleemvragen q ON q.id = c.question_id
WHERE c.client_id = :client_id AND c.checked_at = :checked_at
  AND q.question_kind = :question_kind
ORDER BY q.created_at ASC, c.model ASC
SQL
        );
        $stmt->execute(['client_id' => $clientId, 'checked_at' => $checkedAt, 'question_kind' => $kind]);
        $rows = $stmt->fetchAll() ?: [];

        $byQuestion = [];
        $mentionedCount = 0;
        $totalCount = 0;
        foreach ($rows as $row) {
            $qid = (string) ($row['question_id'] ?? '');
            if (!isset($byQuestion[$qid])) {
                $byQuestion[$qid] = [
                    'questionId' => $qid,
                    'question' => (string) ($row['question'] ?? ''),
                    'models' => [],
                ];
            }
            $mentioned = (bool) ((int) ($row['mentioned'] ?? 0));
            $byQuestion[$qid]['models'][] = [
                'model' => (string) ($row['model'] ?? ''),
                'mentioned' => $mentioned,
            ];
            $totalCount++;
            if ($mentioned) {
                $mentionedCount++;
            }
        }

        return [
            'checkedAt' => $checkedAt,
            'items' => array_values($byQuestion),
            'mentionedCount' => $mentionedCount,
            'totalCount' => $totalCount,
        ];
    };

    $latest = $fetchGroup($checkedAts[0]);
    $previous = isset($checkedAts[1])
        ? array_intersect_key($fetchGroup($checkedAts[1]), ['checkedAt' => true, 'mentionedCount' => true, 'totalCount' => true])
        : null;

    return [
        'kind' => $kind,
        'hasQuestions' => true,
        'latest' => $latest,
        'previous' => $previous,
        'advice' => ce_findability_improvement_advice($kind, $latest, $previous, $pdo, $clientId),
    ];
}

/** @param array{mentionedCount?: int, totalCount?: int}|null $latest */
function ce_findability_kind_score_percent(?array $latest): ?int
{
    if ($latest === null) {
        return null;
    }
    $total = (int) ($latest['totalCount'] ?? 0);
    if ($total <= 0) {
        return null;
    }

    return (int) round(((int) ($latest['mentionedCount'] ?? 0) / $total) * 100);
}

function ce_findability_combined_score(?int $geoScore, ?int $seoScore): ?int
{
    $scores = array_values(array_filter([$geoScore, $seoScore], static fn (?int $score): bool => $score !== null));
    if ($scores === []) {
        return null;
    }

    return (int) round(array_sum($scores) / count($scores));
}

/**
 * @return list<array{date: string, score: int, geoScore: int|null, seoScore: int|null}>
 */
function ce_fetch_findability_score_history(PDO $pdo, string $clientId, int $limit = 8): array
{
    ce_ensure_llm_schema($pdo);
    if (!ce_table_exists($pdo, 'llm_visibility_checks')) {
        return [];
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
SELECT DATE(c.checked_at) AS check_date,
       q.question_kind,
       SUM(c.mentioned) AS mentioned,
       COUNT(*) AS total
FROM llm_visibility_checks c
INNER JOIN llm_probleemvragen q ON q.id = c.question_id AND q.active = 1
WHERE c.client_id = :client_id
GROUP BY DATE(c.checked_at), q.question_kind
ORDER BY check_date ASC
SQL
    );
    $stmt->execute(['client_id' => $clientId]);
    $rows = $stmt->fetchAll() ?: [];

    $byDate = [];
    foreach ($rows as $row) {
        $date = (string) ($row['check_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $kind = (string) ($row['question_kind'] ?? 'geo');
        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            continue;
        }
        $byDate[$date][$kind] = (int) round(((int) ($row['mentioned'] ?? 0) / $total) * 100);
    }

    $points = [];
    foreach ($byDate as $date => $kinds) {
        $scores = [];
        if (isset($kinds['geo'])) {
            $scores[] = $kinds['geo'];
        }
        if (isset($kinds['seo'])) {
            $scores[] = $kinds['seo'];
        }
        if ($scores === []) {
            continue;
        }
        $points[] = [
            'date' => $date,
            'score' => (int) round(array_sum($scores) / count($scores)),
            'geoScore' => $kinds['geo'] ?? null,
            'seoScore' => $kinds['seo'] ?? null,
        ];
    }

    if (count($points) > $limit) {
        $points = array_slice($points, -$limit);
    }

    return $points;
}

/** @return array<string, mixed> */
function ce_fetch_home_findability(PDO $pdo, ?string $clientId): array
{
    $empty = [
        'score' => null,
        'geoScore' => null,
        'seoScore' => null,
        'hasQuestions' => false,
        'hasMeasurement' => false,
        'checkedAt' => null,
        'trendData' => [],
        'trendLabels' => [],
        'hasEnoughData' => false,
        'previousScore' => null,
        'trendDirection' => 'stable',
    ];

    if ($clientId === null || trim($clientId) === '') {
        return $empty;
    }

    $geo = ce_fetch_llm_visibility_report($pdo, $clientId, 'geo');
    $seo = ce_fetch_llm_visibility_report($pdo, $clientId, 'seo');

    $hasQuestions = ($geo['hasQuestions'] ?? false) || ($seo['hasQuestions'] ?? false);
    $geoScore = ce_findability_kind_score_percent(is_array($geo['latest'] ?? null) ? $geo['latest'] : null);
    $seoScore = ce_findability_kind_score_percent(is_array($seo['latest'] ?? null) ? $seo['latest'] : null);
    $score = ce_findability_combined_score($geoScore, $seoScore);

    $checkedAt = null;
    if (is_array($geo['latest'] ?? null) && !empty($geo['latest']['checkedAt'])) {
        $checkedAt = (string) $geo['latest']['checkedAt'];
    } elseif (is_array($seo['latest'] ?? null) && !empty($seo['latest']['checkedAt'])) {
        $checkedAt = (string) $seo['latest']['checkedAt'];
    }

    $history = ce_fetch_findability_score_history($pdo, $clientId, 8);
    $trendData = array_map(static fn (array $point): int => (int) $point['score'], $history);
    $months = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    $trendLabels = array_map(static function (array $point) use ($months): string {
        $date = (string) ($point['date'] ?? '');
        if ($date === '') {
            return '';
        }
        $monthIndex = ((int) substr($date, 5, 2)) - 1;
        $day = (int) substr($date, 8, 2);

        return ($monthIndex >= 0 && $monthIndex < 12 ? $months[$monthIndex] : '') . ' ' . $day;
    }, $history);

    $previousScore = count($trendData) >= 2 ? $trendData[count($trendData) - 2] : null;
    $trendDirection = 'stable';
    if ($score !== null && $previousScore !== null) {
        if ($score > $previousScore + 2) {
            $trendDirection = 'up';
        } elseif ($score < $previousScore - 2) {
            $trendDirection = 'down';
        }
    }

    return [
        'score' => $score,
        'geoScore' => $geoScore,
        'seoScore' => $seoScore,
        'hasQuestions' => $hasQuestions,
        'hasMeasurement' => $score !== null,
        'checkedAt' => $checkedAt,
        'trendData' => $trendData,
        'trendLabels' => $trendLabels,
        'hasEnoughData' => count($trendData) >= 2,
        'previousScore' => $previousScore,
        'trendDirection' => $trendDirection,
    ];
}

function ce_client_findability_host(PDO $pdo, string $clientId): string
{
    if (!ce_table_exists($pdo, 'clients')) {
        return '';
    }

    $cols = ['website_blog_url', 'wordpress_site_url'];
    $available = array_values(array_filter($cols, static fn (string $c): bool => ce_table_has_column($pdo, 'clients', $c)));
    if ($available === []) {
        return '';
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $available) . ' FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return '';
    }

    foreach ($available as $col) {
        $url = trim((string) ($row[$col] ?? ''));
        if ($url === '') {
            continue;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }
    }

    return '';
}

/** @return array{key: string, cx: string} */
function ce_google_custom_search_config(): array
{
    ce_load_env();
    $key = trim((string) (getenv('GOOGLE_GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: ''));
    $cx = trim((string) (getenv('GOOGLE_CUSTOM_SEARCH_ENGINE_ID') ?: ''));

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            if ($key === '' && trim((string) ($config['google_gemini_api_key'] ?? '')) !== '') {
                $key = trim((string) $config['google_gemini_api_key']);
            }
            if ($key === '' && trim((string) ($config['google_api_key'] ?? '')) !== '') {
                $key = trim((string) $config['google_api_key']);
            }
            if ($cx === '' && trim((string) ($config['google_custom_search_engine_id'] ?? '')) !== '') {
                $cx = trim((string) $config['google_custom_search_engine_id']);
            }
        }
    }

    return ['key' => $key, 'cx' => $cx];
}

/** Eerste N Google-zoekresultaten die we meten (≈5 schermen). */
const CE_SEO_RESULTS_LIMIT = 50;

/** @param list<array<string, mixed>> $items */
function ce_seo_results_mention_client(array $items, string $host, string $clientName): bool
{
    $host = strtolower(trim($host));
    $name = strtolower(trim($clientName));

    foreach (array_slice($items, 0, CE_SEO_RESULTS_LIMIT) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $link = strtolower((string) ($item['link'] ?? ''));
        if ($host !== '' && $link !== '' && str_contains($link, $host)) {
            return true;
        }
        $blob = strtolower(trim((string) ($item['title'] ?? '') . ' ' . (string) ($item['snippet'] ?? '')));
        if ($name !== '' && $blob !== '' && str_contains($blob, $name)) {
            return true;
        }
    }

    return false;
}

/**
 * SEO-provider: SerpAPI (Google) → legacy Custom Search → Perplexity web-zoek (fallback).
 *
 * @return array{provider: string, model: string, label: string}
 */
function ce_seo_search_provider(array $keys): array
{
    if (trim((string) ($keys['serpapi'] ?? '')) !== '') {
        return ['provider' => 'serpapi', 'model' => 'google', 'label' => 'Google (SerpAPI)'];
    }

    $search = ce_google_custom_search_config();
    if ($search['key'] !== '' && $search['cx'] !== '') {
        return ['provider' => 'google_cse', 'model' => 'google', 'label' => 'Google (Custom Search)'];
    }

    if (trim((string) ($keys['perplexity'] ?? '')) !== '') {
        return ['provider' => 'perplexity', 'model' => 'web', 'label' => 'Web-zoek (Perplexity)'];
    }

    return ['provider' => 'none', 'model' => 'google', 'label' => ''];
}

/**
 * @return array{items: list<array{link?: string, title?: string, snippet?: string}>, error: string, answer: string}
 */
function ce_seo_fetch_results(string $question, array $providerInfo, array $keys): array
{
    $provider = (string) ($providerInfo['provider'] ?? 'none');
    $empty = ['items' => [], 'error' => '', 'answer' => ''];

    if ($provider === 'serpapi') {
        $url = 'https://serpapi.com/search.json?' . http_build_query([
            'engine' => 'google',
            'q' => $question,
            'google_domain' => 'google.nl',
            'gl' => 'nl',
            'hl' => 'nl',
            'num' => CE_SEO_RESULTS_LIMIT,
            'api_key' => trim((string) ($keys['serpapi'] ?? '')),
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            return ['items' => [], 'error' => 'SerpAPI-request mislukt', 'answer' => ''];
        }
        $data = json_decode((string) $body, true);
        if ($status >= 400 || !is_array($data)) {
            return ['items' => [], 'error' => ce_llm_visibility_api_error_message((string) $body, $status), 'answer' => ''];
        }
        $items = [];
        foreach (is_array($data['organic_results'] ?? null) ? $data['organic_results'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'link' => (string) ($row['link'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'snippet' => (string) ($row['snippet'] ?? ''),
            ];
        }

        return ['items' => array_slice($items, 0, CE_SEO_RESULTS_LIMIT), 'error' => '', 'answer' => ''];
    }

    if ($provider === 'google_cse') {
        $search = ce_google_custom_search_config();
        $items = [];
        for ($start = 1; $start <= CE_SEO_RESULTS_LIMIT && count($items) < CE_SEO_RESULTS_LIMIT; $start += 10) {
            $num = min(10, CE_SEO_RESULTS_LIMIT - count($items));
            $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
                'key' => $search['key'],
                'cx' => $search['cx'],
                'q' => $question,
                'num' => $num,
                'start' => $start,
                'hl' => 'nl',
                'gl' => 'nl',
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($body === false) {
                return ['items' => array_slice($items, 0, CE_SEO_RESULTS_LIMIT), 'error' => 'Google Search request mislukt', 'answer' => ''];
            }
            $data = json_decode((string) $body, true);
            if ($status >= 400 || !is_array($data)) {
                $error = $items === []
                    ? ce_llm_visibility_api_error_message((string) $body, $status)
                    : '';
                return ['items' => array_slice($items, 0, CE_SEO_RESULTS_LIMIT), 'error' => $error, 'answer' => ''];
            }
            $pageItems = is_array($data['items'] ?? null) ? $data['items'] : [];
            if ($pageItems === []) {
                break;
            }
            foreach ($pageItems as $pageItem) {
                $items[] = $pageItem;
                if (count($items) >= CE_SEO_RESULTS_LIMIT) {
                    break;
                }
            }
        }

        return ['items' => array_slice($items, 0, CE_SEO_RESULTS_LIMIT), 'error' => '', 'answer' => ''];
    }

    if ($provider === 'perplexity') {
        $payload = json_encode([
            'model' => 'sonar',
            'messages' => [['role' => 'user', 'content' => $question]],
        ], JSON_THROW_ON_ERROR);
        $res = ce_llm_visibility_http_post(
            'https://api.perplexity.ai/chat/completions',
            [
                'Authorization: Bearer ' . trim((string) ($keys['perplexity'] ?? '')),
                'Content-Type: application/json',
            ],
            $payload,
            40,
        );
        if (!$res['ok']) {
            return ['items' => [], 'error' => $res['error'], 'answer' => ''];
        }
        $data = json_decode($res['body'], true);
        $answer = (string) ($data['choices'][0]['message']['content'] ?? '');
        $items = [];
        foreach (is_array($data['citations'] ?? null) ? $data['citations'] : [] as $cite) {
            $link = trim((string) $cite);
            if ($link === '') {
                continue;
            }
            $items[] = ['link' => $link, 'title' => '', 'snippet' => ''];
        }

        return ['items' => array_slice($items, 0, CE_SEO_RESULTS_LIMIT), 'error' => '', 'answer' => $answer];
    }

    return array_merge($empty, [
        'error' => 'SEO-check vereist PERPLEXITY_API_KEY (fallback) of SERPAPI_API_KEY (Google). Google Programmable Search ondersteunt sinds jan 2026 geen nieuwe "hele web"-engines meer.',
    ]);
}

function ce_seo_mentions_client(array $fetch, string $host, string $clientName): bool
{
    if (ce_seo_results_mention_client($fetch['items'], $host, $clientName)) {
        return true;
    }

    $answer = trim((string) ($fetch['answer'] ?? ''));
    if ($answer !== '' && ce_llm_visibility_mentions_client($answer, $clientName)) {
        return true;
    }

    return false;
}

/**
 * @param array{mentionedCount?: int, totalCount?: int, items?: list<array<string, mixed>>}|null $latest
 * @param array{mentionedCount?: int, totalCount?: int}|null $previous
 * @return list<string>
 */
function ce_findability_improvement_advice(
    string $kind,
    ?array $latest,
    ?array $previous,
    PDO $pdo,
    ?string $clientId,
): array {
    $kind = ce_llm_question_kind($kind);
    $tips = [];

    if ($clientId === null || $clientId === '') {
        return $tips;
    }

    $host = ce_client_findability_host($pdo, $clientId);
    $clientName = '';
    if (ce_table_exists($pdo, 'clients')) {
        $stmt = $pdo->prepare('SELECT name FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $clientName = trim((string) ($stmt->fetchColumn() ?: ''));
    }

    if ($latest === null) {
        if ($kind === 'seo') {
            $keys = ce_llm_visibility_api_keys();
            $provider = ce_seo_search_provider($keys);
            if ($provider['provider'] === 'none') {
                $tips[] = 'SEO-check gebruikt PERPLEXITY_API_KEY (web-zoek) of optioneel SERPAPI_API_KEY voor de eerste ' . CE_SEO_RESULTS_LIMIT . ' Google-resultaten. Google Programmable Search ondersteunt sinds jan 2026 geen nieuwe "hele web"-engines meer.';
            } elseif ($provider['provider'] === 'perplexity') {
                $tips[] = 'SEO-meting via Perplexity web-zoek (indicatie). Voor exacte Google-top-' . CE_SEO_RESULTS_LIMIT . ': voeg SERPAPI_API_KEY toe.';
            }
            $tips[] = 'Voeg SEO-zoekvragen toe in Findability en start een meting.';
            if ($host === '') {
                $tips[] = 'Vul je website- of blog-URL in bij Integrations zodat we je domein in zoekresultaten herkennen.';
            }
        } else {
            $tips[] = 'Voeg GEO-vragen toe in Findability — formuleer ze zoals je doelgroep ze aan AI stelt, zonder je merknaam in de vraag.';
        }

        return $tips;
    }

    $mentioned = (int) ($latest['mentionedCount'] ?? 0);
    $total = max(1, (int) ($latest['totalCount'] ?? 0));
    $ratio = $mentioned / $total;
    $prevMentioned = (int) ($previous['mentionedCount'] ?? -1);

    if ($kind === 'geo') {
        if ($mentioned === 0) {
            $tips[] = 'Je merk verschijnt nog niet in AI-antwoorden op deze vragen. Publiceer herkenbare expertcontent over exact deze thema\'s — blog, LinkedIn en website — met consistente merknaam.';
            $tips[] = 'Zorg dat je merknaam en kernboodschap op je website, About-pagina en social profielen duidelijk en eenduidig staan (AI leest veel van het open web).';
            $tips[] = 'Overweeg concrete cases, how-to\'s en branche-inzichten i.p.v. generieke posts — AI citeert merken die een duidelijk standpunt hebben.';
        } elseif ($ratio < 0.35) {
            $tips[] = 'Incidentele vermelding — versterk content rond de vragen waar je níet genoemd wordt. Schrijf artikelen die die vraag letterlijk beantwoorden.';
        } else {
            $tips[] = 'Goede basis — blijf maandelijks meten en koppel nieuwe posts aan je GEO-vragen.';
        }

        $weakModels = [];
        $modelTotals = [];
        foreach (($latest['items'] ?? []) as $item) {
            foreach (($item['models'] ?? []) as $modelRow) {
                $model = (string) ($modelRow['model'] ?? '');
                if ($model === '') {
                    continue;
                }
                $modelTotals[$model] = ($modelTotals[$model] ?? 0) + 1;
                if (!((bool) ($modelRow['mentioned'] ?? false))) {
                    $weakModels[$model] = ($weakModels[$model] ?? 0) + 1;
                }
            }
        }
        foreach ($modelTotals as $model => $count) {
            if (($weakModels[$model] ?? 0) === $count) {
                $label = match ($model) {
                    'chatgpt' => 'ChatGPT',
                    'perplexity' => 'Perplexity',
                    'claude' => 'Claude',
                    'gemini' => 'Gemini',
                    default => $model,
                };
                $tips[] = "Nog niet zichtbaar in {$label} — extra webcontent en externe vermeldingen (media, partners) helpen vooral bij dit model.";
            }
        }
    } else {
        if ($host === '') {
            $tips[] = 'Vul je website- of blog-URL in bij Integrations — zonder domein kunnen we SEO-resultaten niet koppelen.';
        }
        if ($mentioned === 0) {
            $tips[] = 'Je site staat niet in de eerste ' . CE_SEO_RESULTS_LIMIT . ' Google-resultaten voor deze zoekvragen. Schrijf een artikel per vraag met de zoekterm in titel, H1 en meta-description.';
            $tips[] = 'Link intern vanaf je homepage en gerelateerde posts naar nieuwe SEO-artikelen.';
            $tips[] = 'Publiceer regelmatig via Content Engine op de vraag-thema\'s die je in Findability hebt staan.';
        } elseif ($ratio < 0.5) {
            $tips[] = 'Deels zichtbaar — optimaliseer bestaande pagina\'s en schrijf aanvullende content voor vragen zonder ranking.';
        } else {
            $tips[] = 'Sterke SEO-basis — houd de vragen actueel en meet maandelijks opnieuw.';
        }
    }

    if ($prevMentioned >= 0 && $mentioned > $prevMentioned) {
        $tips[] = 'Vooruitgang t.o.v. vorige meting — ga door op de contentlijn die werkt.';
    } elseif ($prevMentioned >= 0 && $mentioned < $prevMentioned) {
        $tips[] = 'Minder vermeldingen dan vorige maand — check of recente content nog aansluit op je findability-vragen.';
    }

    return array_values(array_unique($tips));
}

/** @return array<string, mixed> */
function ce_run_findability_checks(PDO $pdo, ?string $clientId = null, ?string $kind = null): array
{
    $kind = ce_llm_question_kind($kind ?? 'geo');

    return $kind === 'seo'
        ? ce_run_seo_visibility_checks($pdo, $clientId)
        : ce_run_llm_visibility_checks($pdo, $clientId);
}

/** @return array<string, mixed> */
function ce_run_seo_visibility_checks(PDO $pdo, ?string $clientId = null): array
{
    ce_ensure_llm_schema($pdo);
    require_once __DIR__ . '/sources-repository.php';

    $clientId = trim((string) ($clientId ?? ''));
    $sql = <<<'SQL'
SELECT q.id AS question_id, q.client_id, q.question, c.name AS client_name
FROM llm_probleemvragen q
JOIN clients c ON c.id = q.client_id
WHERE q.active = 1 AND q.question_kind = 'seo'
SQL;
    $params = [];
    if ($clientId !== '') {
        $sql .= ' AND q.client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $sql .= ' ORDER BY q.client_id, q.created_at';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll() ?: [];

    if ($questions === []) {
        return [
            'ok' => false,
            'kind' => 'seo',
            'error' => 'Geen actieve SEO-vragen — voeg ze toe in Findability.',
            'questionCount' => 0,
            'inserted' => 0,
        ];
    }

    $keys = ce_llm_visibility_api_keys();
    $providerInfo = ce_seo_search_provider($keys);
    if ($providerInfo['provider'] === 'none') {
        return [
            'ok' => false,
            'kind' => 'seo',
            'error' => 'SEO-check vereist PERPLEXITY_API_KEY (standaard) of SERPAPI_API_KEY (Google top-' . CE_SEO_RESULTS_LIMIT . ').',
            'questionCount' => count($questions),
            'inserted' => 0,
        ];
    }

    $checkedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $inserted = 0;
    $errors = [];
    $model = (string) ($providerInfo['model'] ?? 'web');
    $insert = $pdo->prepare(
        'INSERT INTO llm_visibility_checks (id, client_id, question_id, model, mentioned, raw_response, checked_at)
         VALUES (:id, :client_id, :question_id, :model, :mentioned, :raw_response, :checked_at)'
    );

    foreach ($questions as $questionRow) {
        $questionId = (string) ($questionRow['question_id'] ?? '');
        $questionClientId = (string) ($questionRow['client_id'] ?? '');
        $questionText = trim((string) ($questionRow['question'] ?? ''));
        $clientName = (string) ($questionRow['client_name'] ?? '');
        $host = ce_client_findability_host($pdo, $questionClientId);

        $fetch = ce_seo_fetch_results($questionText, $providerInfo, $keys);
        if ($fetch['error'] !== '') {
            $errors[] = ['model' => $model, 'questionId' => $questionId, 'error' => $fetch['error']];
        }

        $summary = [];
        foreach (array_slice($fetch['items'], 0, CE_SEO_RESULTS_LIMIT) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $line = trim((string) ($item['title'] ?? ''));
            $link = trim((string) ($item['link'] ?? ''));
            if ($line === '' && $link !== '') {
                $line = $link;
            } elseif ($link !== '') {
                $line = $line . ' — ' . $link;
            }
            if ($line !== '') {
                $summary[] = $line;
            }
        }
        if ($summary === [] && trim((string) ($fetch['answer'] ?? '')) !== '') {
            $summary[] = mb_substr(trim((string) $fetch['answer']), 0, 400);
        }
        $raw = $summary !== [] ? implode("\n", $summary) : ($fetch['error'] !== '' ? '[API-fout] ' . $fetch['error'] : 'Geen resultaten');

        $insert->execute([
            'id' => ce_uuid_v4(),
            'client_id' => $questionClientId,
            'question_id' => $questionId,
            'model' => $model,
            'mentioned' => ce_seo_mentions_client($fetch, $host, $clientName) ? 1 : 0,
            'raw_response' => $raw,
            'checked_at' => $checkedAt,
        ]);
        $inserted++;
    }

    return [
        'ok' => $inserted > 0,
        'kind' => 'seo',
        'provider' => $providerInfo['provider'],
        'providerLabel' => $providerInfo['label'],
        'questionCount' => count($questions),
        'inserted' => $inserted,
        'checkedAt' => $checkedAt,
        'configured' => [$model => true],
        'errors' => $errors,
    ];
}

/** @return array<string, mixed> */
function ce_trigger_llm_visibility_check(?string $clientId = null, bool $fireAndForget = true): array
{
    $webhook = trim((string) (getenv('N8N_CE_LLM_VISIBILITY_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['ce_llm_visibility_webhook_url'] ?? ''));
        }
    }

    if ($webhook === '') {
        return ['ok' => false, 'error' => 'N8N_CE_LLM_VISIBILITY_WEBHOOK_URL is niet geconfigureerd'];
    }

    $payload = [];
    $clientId = trim((string) ($clientId ?? ''));
    if ($clientId !== '') {
        $payload['clientId'] = $clientId;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'timeout' => $fireAndForget ? 3 : 120,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($webhook, false, $ctx);
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int) $m[1];
    }

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'webhookTriggered' => $httpCode > 0,
        'httpCode' => $httpCode,
        'response' => is_string($response) ? mb_substr($response, 0, 500) : null,
    ];
}

/** @return array<string, string> */
function ce_llm_visibility_api_keys(): array
{
    ce_load_env();
    $keys = [
        'openai' => trim((string) (getenv('OPENAI_API_KEY') ?: '')),
        'perplexity' => trim((string) (getenv('PERPLEXITY_API_KEY') ?: '')),
        'anthropic' => trim((string) (getenv('ANTHROPIC_API_KEY') ?: '')),
        'gemini' => trim((string) (getenv('GOOGLE_GEMINI_API_KEY') ?: '')),
        'serpapi' => trim((string) (getenv('SERPAPI_API_KEY') ?: '')),
    ];

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            foreach ([
                'openai' => 'openai_api_key',
                'perplexity' => 'perplexity_api_key',
                'anthropic' => 'anthropic_api_key',
                'gemini' => 'google_gemini_api_key',
                'serpapi' => 'serpapi_api_key',
            ] as $slot => $configKey) {
                if ($keys[$slot] === '' && trim((string) ($config[$configKey] ?? '')) !== '') {
                    $keys[$slot] = trim((string) $config[$configKey]);
                }
            }
        }
    }

    return $keys;
}

/** @return array{ok: bool, status: int, body: string, error: string} */
function ce_llm_visibility_http_post(string $url, array $headers, string $payload, int $timeout = 60): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $curlError !== '' ? $curlError : 'HTTP-request mislukt'];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => (string) $body,
        'error' => $status >= 400 ? ce_llm_visibility_api_error_message((string) $body, $status) : '',
    ];
}

function ce_llm_visibility_api_error_message(string $body, int $status): string
{
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return 'HTTP ' . $status;
    }

    if (isset($data['error']['message']) && is_string($data['error']['message'])) {
        return $data['error']['message'];
    }
    if (isset($data['error']) && is_string($data['error'])) {
        return $data['error'];
    }

    return 'HTTP ' . $status;
}

function ce_llm_visibility_mentions_client(string $text, string $clientName): bool
{
    $name = strtolower(trim($clientName));
    if ($name === '') {
        return false;
    }

    return str_contains(strtolower($text), $name);
}

/** @return array{model: string, text: string, error: string, configured: bool} */
function ce_llm_visibility_ask_model(string $model, string $question, array $keys): array
{
    $question = trim($question);
    if ($question === '') {
        return ['model' => $model, 'text' => '', 'error' => 'Lege vraag', 'configured' => true];
    }

    if ($model === 'chatgpt') {
        $key = trim((string) ($keys['openai'] ?? ''));
        if ($key === '') {
            return ['model' => $model, 'text' => '', 'error' => 'OPENAI_API_KEY ontbreekt', 'configured' => false];
        }
        $payload = json_encode([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => $question]],
        ], JSON_THROW_ON_ERROR);
        $res = ce_llm_visibility_http_post(
            'https://api.openai.com/v1/chat/completions',
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            $payload,
        );
        if (!$res['ok']) {
            return ['model' => $model, 'text' => '', 'error' => $res['error'], 'configured' => true];
        }
        $data = json_decode($res['body'], true);
        $text = (string) ($data['choices'][0]['message']['content'] ?? '');

        return ['model' => $model, 'text' => $text, 'error' => '', 'configured' => true];
    }

    if ($model === 'perplexity') {
        $key = trim((string) ($keys['perplexity'] ?? ''));
        if ($key === '') {
            return ['model' => $model, 'text' => '', 'error' => 'PERPLEXITY_API_KEY ontbreekt', 'configured' => false];
        }
        $payload = json_encode([
            'model' => 'sonar',
            'messages' => [['role' => 'user', 'content' => $question]],
        ], JSON_THROW_ON_ERROR);
        $res = ce_llm_visibility_http_post(
            'https://api.perplexity.ai/chat/completions',
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            $payload,
        );
        if (!$res['ok']) {
            return ['model' => $model, 'text' => '', 'error' => $res['error'], 'configured' => true];
        }
        $data = json_decode($res['body'], true);
        $text = (string) ($data['choices'][0]['message']['content'] ?? '');

        return ['model' => $model, 'text' => $text, 'error' => '', 'configured' => true];
    }

    if ($model === 'claude') {
        $key = trim((string) ($keys['anthropic'] ?? ''));
        if ($key === '') {
            return ['model' => $model, 'text' => '', 'error' => 'ANTHROPIC_API_KEY ontbreekt', 'configured' => false];
        }
        $payload = json_encode([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $question]],
        ], JSON_THROW_ON_ERROR);
        $res = ce_llm_visibility_http_post(
            'https://api.anthropic.com/v1/messages',
            [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            $payload,
        );
        if (!$res['ok']) {
            return ['model' => $model, 'text' => '', 'error' => $res['error'], 'configured' => true];
        }
        $data = json_decode($res['body'], true);
        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return ['model' => $model, 'text' => trim($text), 'error' => '', 'configured' => true];
    }

    if ($model === 'gemini') {
        $key = trim((string) ($keys['gemini'] ?? ''));
        if ($key === '') {
            return ['model' => $model, 'text' => '', 'error' => 'GOOGLE_GEMINI_API_KEY ontbreekt', 'configured' => false];
        }
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $question]]]],
        ], JSON_THROW_ON_ERROR);
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key='
            . rawurlencode($key);
        $res = ce_llm_visibility_http_post(
            $url,
            ['Content-Type: application/json'],
            $payload,
        );
        if (!$res['ok']) {
            return ['model' => $model, 'text' => '', 'error' => $res['error'], 'configured' => true];
        }
        $data = json_decode($res['body'], true);
        $text = (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

        return ['model' => $model, 'text' => $text, 'error' => '', 'configured' => true];
    }

    return ['model' => $model, 'text' => '', 'error' => 'Onbekend model', 'configured' => false];
}

/**
 * @return array{model: string, url: string, headers: list<string>, payload: string, configured: bool}|null
 */
function ce_llm_visibility_request_for_model(string $model, string $question, array $keys): ?array
{
    $question = trim($question);
    if ($question === '') {
        return null;
    }

    if ($model === 'chatgpt') {
        $key = trim((string) ($keys['openai'] ?? ''));
        if ($key === '') {
            return null;
        }

        return [
            'model' => $model,
            'url' => 'https://api.openai.com/v1/chat/completions',
            'headers' => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'payload' => json_encode([
                'model' => 'gpt-4o',
                'messages' => [['role' => 'user', 'content' => $question]],
            ], JSON_THROW_ON_ERROR),
            'configured' => true,
        ];
    }

    if ($model === 'perplexity') {
        $key = trim((string) ($keys['perplexity'] ?? ''));
        if ($key === '') {
            return null;
        }

        return [
            'model' => $model,
            'url' => 'https://api.perplexity.ai/chat/completions',
            'headers' => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'payload' => json_encode([
                'model' => 'sonar',
                'messages' => [['role' => 'user', 'content' => $question]],
            ], JSON_THROW_ON_ERROR),
            'configured' => true,
        ];
    }

    if ($model === 'claude') {
        $key = trim((string) ($keys['anthropic'] ?? ''));
        if ($key === '') {
            return null;
        }

        return [
            'model' => $model,
            'url' => 'https://api.anthropic.com/v1/messages',
            'headers' => [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
            'payload' => json_encode([
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 512,
                'messages' => [['role' => 'user', 'content' => $question]],
            ], JSON_THROW_ON_ERROR),
            'configured' => true,
        ];
    }

    if ($model === 'gemini') {
        $key = trim((string) ($keys['gemini'] ?? ''));
        if ($key === '') {
            return null;
        }

        return [
            'model' => $model,
            'url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key='
                . rawurlencode($key),
            'headers' => ['Content-Type: application/json'],
            'payload' => json_encode([
                'contents' => [['parts' => [['text' => $question]]]],
            ], JSON_THROW_ON_ERROR),
            'configured' => true,
        ];
    }

    return null;
}

/** @return array{model: string, text: string, error: string, configured: bool} */
function ce_llm_visibility_parse_model_response(string $model, int $status, string $body): array
{
    if ($status >= 400 || $body === '') {
        return [
            'model' => $model,
            'text' => '',
            'error' => $status > 0 ? ce_llm_visibility_api_error_message($body, $status) : 'HTTP-request mislukt',
            'configured' => true,
        ];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['model' => $model, 'text' => '', 'error' => 'Onleesbaar antwoord', 'configured' => true];
    }

    if ($model === 'chatgpt' || $model === 'perplexity') {
        $text = (string) ($data['choices'][0]['message']['content'] ?? '');

        return ['model' => $model, 'text' => $text, 'error' => '', 'configured' => true];
    }

    if ($model === 'claude') {
        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return ['model' => $model, 'text' => trim($text), 'error' => '', 'configured' => true];
    }

    if ($model === 'gemini') {
        $text = (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

        return ['model' => $model, 'text' => $text, 'error' => '', 'configured' => true];
    }

    return ['model' => $model, 'text' => '', 'error' => 'Onbekend model', 'configured' => false];
}

/**
 * Vraag meerdere LLM's parallel (sneller dan sequentieel; past binnen server-timeout).
 *
 * @param list<string> $models
 * @return array<string, array{model: string, text: string, error: string, configured: bool}>
 */
function ce_llm_visibility_ask_models_parallel(string $question, array $keys, array $models, int $timeout = 35): array
{
    $results = [];
    $pending = [];

    foreach ($models as $model) {
        $missingKeyMessages = [
            'chatgpt' => 'OPENAI_API_KEY ontbreekt',
            'perplexity' => 'PERPLEXITY_API_KEY ontbreekt',
            'claude' => 'ANTHROPIC_API_KEY ontbreekt',
            'gemini' => 'GOOGLE_GEMINI_API_KEY ontbreekt',
        ];
        $req = ce_llm_visibility_request_for_model($model, $question, $keys);
        if ($req === null) {
            $results[$model] = [
                'model' => $model,
                'text' => '',
                'error' => $missingKeyMessages[$model] ?? 'API-key ontbreekt',
                'configured' => false,
            ];
            continue;
        }
        $pending[$model] = $req;
    }

    if ($pending === []) {
        return $results;
    }

    $multi = curl_multi_init();
    $handles = [];
    foreach ($pending as $model => $req) {
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $req['headers'],
            CURLOPT_POSTFIELDS => $req['payload'],
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$model] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 && $status === CURLM_OK);

    foreach ($handles as $model => $ch) {
        $body = curl_multi_getcontent($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $results[$model] = ce_llm_visibility_parse_model_response(
            $model,
            $status,
            is_string($body) ? $body : '',
        );
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);

    return $results;
}

/**
 * @param list<array{key: string, model: string, url: string, headers: list<string>, payload: string}> $requests
 * @return array<string, array{model: string, text: string, error: string, configured: bool}>
 */
function ce_llm_visibility_run_curl_multi(array $requests, int $timeout = 40): array
{
    if ($requests === []) {
        return [];
    }

    $multi = curl_multi_init();
    $handles = [];
    $results = [];

    foreach ($requests as $req) {
        $key = (string) ($req['key'] ?? '');
        $model = (string) ($req['model'] ?? '');
        if ($key === '' || $model === '') {
            continue;
        }
        $ch = curl_init($req['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $req['headers'],
            CURLOPT_POSTFIELDS => $req['payload'],
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$key] = ['ch' => $ch, 'model' => $model];
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 && $status === CURLM_OK);

    foreach ($handles as $key => $meta) {
        $ch = $meta['ch'];
        $body = curl_multi_getcontent($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $results[$key] = ce_llm_visibility_parse_model_response(
            $meta['model'],
            $status,
            is_string($body) ? $body : '',
        );
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);

    return $results;
}

/**
 * GEO-vindbaarheid direct vanuit PHP (keys uit .env / ce.local.php).
 *
 * @return array<string, mixed>
 */
function ce_run_llm_visibility_checks(PDO $pdo, ?string $clientId = null): array
{
    ce_ensure_llm_schema($pdo);
    require_once __DIR__ . '/sources-repository.php';

    $clientId = trim((string) ($clientId ?? ''));
    $sql = <<<'SQL'
SELECT q.id AS question_id, q.client_id, q.question, c.name AS client_name
FROM llm_probleemvragen q
JOIN clients c ON c.id = q.client_id
WHERE q.active = 1 AND q.question_kind = 'geo'
SQL;
    $params = [];
    if ($clientId !== '') {
        $sql .= ' AND q.client_id = :client_id';
        $params['client_id'] = $clientId;
    }
    $sql .= ' ORDER BY q.client_id, q.created_at';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll() ?: [];

    if ($questions === []) {
        return [
            'ok' => false,
            'kind' => 'geo',
            'error' => 'Geen actieve GEO-vragen — voeg ze toe in Search (Findability).',
            'questionCount' => 0,
            'inserted' => 0,
        ];
    }

    $keys = ce_llm_visibility_api_keys();
    $models = ['chatgpt', 'perplexity', 'claude', 'gemini'];
    $checkedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $inserted = 0;
    $errors = [];
    $configured = [
        'chatgpt' => trim((string) ($keys['openai'] ?? '')) !== '',
        'perplexity' => trim((string) ($keys['perplexity'] ?? '')) !== '',
        'claude' => trim((string) ($keys['anthropic'] ?? '')) !== '',
        'gemini' => trim((string) ($keys['gemini'] ?? '')) !== '',
    ];

    $insert = $pdo->prepare(
        'INSERT INTO llm_visibility_checks (id, client_id, question_id, model, mentioned, raw_response, checked_at)
         VALUES (:id, :client_id, :question_id, :model, :mentioned, :raw_response, :checked_at)'
    );

    $pendingRequests = [];
    $missingKeyMessages = [
        'chatgpt' => 'OPENAI_API_KEY ontbreekt',
        'perplexity' => 'PERPLEXITY_API_KEY ontbreekt',
        'claude' => 'ANTHROPIC_API_KEY ontbreekt',
        'gemini' => 'GOOGLE_GEMINI_API_KEY ontbreekt',
    ];

    foreach ($questions as $questionRow) {
        $questionId = (string) ($questionRow['question_id'] ?? '');
        $questionText = (string) ($questionRow['question'] ?? '');

        foreach ($models as $model) {
            $req = ce_llm_visibility_request_for_model($model, $questionText, $keys);
            $resultKey = $questionId . ':' . $model;
            if ($req === null) {
                $pendingRequests[$resultKey] = [
                    'prefilled' => [
                        'model' => $model,
                        'text' => '',
                        'error' => $missingKeyMessages[$model] ?? 'API-key ontbreekt',
                        'configured' => false,
                    ],
                ];
                continue;
            }
            $pendingRequests[$resultKey] = [
                'model' => $model,
                'url' => $req['url'],
                'headers' => $req['headers'],
                'payload' => $req['payload'],
            ];
        }
    }

    $curlRequests = [];
    foreach ($pendingRequests as $resultKey => $req) {
        if (isset($req['prefilled'])) {
            continue;
        }
        $curlRequests[] = [
            'key' => $resultKey,
            'model' => (string) $req['model'],
            'url' => (string) $req['url'],
            'headers' => $req['headers'],
            'payload' => (string) $req['payload'],
        ];
    }

    $answers = ce_llm_visibility_run_curl_multi($curlRequests);
    foreach ($pendingRequests as $resultKey => $req) {
        if (isset($req['prefilled'])) {
            $answers[$resultKey] = $req['prefilled'];
        }
    }

    foreach ($questions as $questionRow) {
        $questionId = (string) ($questionRow['question_id'] ?? '');
        $questionClientId = (string) ($questionRow['client_id'] ?? '');
        $clientName = (string) ($questionRow['client_name'] ?? '');

        foreach ($models as $model) {
            $resultKey = $questionId . ':' . $model;
            $answer = $answers[$resultKey]
                ?? ['model' => $model, 'text' => '', 'error' => 'Geen antwoord', 'configured' => true];
            if ($answer['error'] !== '') {
                $errors[] = [
                    'model' => $model,
                    'questionId' => $questionId,
                    'error' => $answer['error'],
                ];
            }

            $raw = $answer['text'];
            if ($answer['error'] !== '' && $raw === '') {
                $raw = '[API-fout] ' . $answer['error'];
            }

            $insert->execute([
                'id' => ce_uuid_v4(),
                'client_id' => $questionClientId,
                'question_id' => $questionId,
                'model' => $model,
                'mentioned' => ce_llm_visibility_mentions_client($raw, $clientName) ? 1 : 0,
                'raw_response' => $raw,
                'checked_at' => $checkedAt,
            ]);
            $inserted++;
        }
    }

    $uniqueErrors = [];
    foreach ($errors as $err) {
        $key = $err['model'] . ':' . ($err['error'] ?? '');
        $uniqueErrors[$key] = $err;
    }

    return [
        'ok' => $inserted > 0,
        'kind' => 'geo',
        'questionCount' => count($questions),
        'inserted' => $inserted,
        'checkedAt' => $checkedAt,
        'configured' => $configured,
        'errors' => array_values($uniqueErrors),
    ];
}
