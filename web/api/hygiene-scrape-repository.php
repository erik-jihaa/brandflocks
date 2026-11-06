<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/scrape-repository.php';
require_once __DIR__ . '/hygiene-repository.php';

function ce_hygiene_page_candidate_paths(): array
{
    return ['', '/about', '/about-us', '/over-ons', '/over', '/diensten', '/services', '/product', '/oplossingen'];
}

function ce_hygiene_page_type_for_path(string $path): string
{
    $lower = strtolower($path);
    if ($lower === '' || $lower === '/') {
        return 'homepage';
    }
    if (preg_match('#/(about|over-ons|over|team|wie-zijn-wij)#', $lower)) {
        return 'about';
    }

    return 'services';
}

/** @return list<array{pageType: string, url: string}> */
function ce_hygiene_urls_for_competitor(string $baseUrl): array
{
    $base = ce_hygiene_normalize_url($baseUrl);
    if ($base === '') {
        return [];
    }

    $seen = [];
    $out = [];
    foreach (ce_hygiene_page_candidate_paths() as $path) {
        $url = $path === '' ? $base . '/' : $base . $path;
        $url = rtrim($url, '/');
        if ($url === $base) {
            $url = $base . '/';
        }
        $key = strtolower($url);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = [
            'pageType' => ce_hygiene_page_type_for_path($path),
            'url' => $url,
        ];
        if (count($out) >= 6) {
            break;
        }
    }

    return $out;
}

function ce_hygiene_html_to_text(string $html): string
{
    $html = preg_replace('/<(script|style|noscript|svg)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
    $html = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

/** @return array{ok: bool, text: ?string, httpStatus: int, message: ?string} */
function ce_hygiene_fetch_page_text(string $url): array
{
    $fetch = ce_http_fetch_url($url, 15);
    $code = (int) ($fetch['httpCode'] ?? 0);
    $body = $fetch['body'] ?? null;
    if ($body === null || $body === '' || $code >= 400) {
        if (ce_apify_platform_configured() && ce_http_body_is_bot_block(is_string($body) ? $body : null, $code)) {
            return ce_hygiene_fetch_page_via_apify($url);
        }

        return [
            'ok' => false,
            'text' => null,
            'httpStatus' => $code,
            'message' => 'Pagina niet bereikbaar (HTTP ' . $code . ')',
        ];
    }

    $text = ce_hygiene_html_to_text($body);
    if (mb_strlen($text) < 80) {
        return [
            'ok' => false,
            'text' => $text !== '' ? $text : null,
            'httpStatus' => $code,
            'message' => 'Te weinig tekst op pagina',
        ];
    }

    return ['ok' => true, 'text' => mb_substr($text, 0, 12000), 'httpStatus' => $code, 'message' => null];
}

/** @return array{ok: bool, text: ?string, httpStatus: int, message: ?string} */
function ce_hygiene_fetch_page_via_apify(string $url): array
{
    $token = ce_apify_platform_token();
    if ($token === '') {
        return ['ok' => false, 'text' => null, 'httpStatus' => 0, 'message' => 'Apify niet geconfigureerd'];
    }

    $result = ce_scraper_fetch_via_apify_actor(
        $token,
        null,
        'apify/website-content-crawler',
        [
            'startUrls' => [['url' => $url]],
            'maxCrawlPages' => 1,
            'maxCrawlDepth' => 0,
        ],
    );
    if (!$result['ok'] || $result['items'] === []) {
        return [
            'ok' => false,
            'text' => null,
            'httpStatus' => 0,
            'message' => (string) ($result['message'] ?? 'Apify scrape mislukt'),
        ];
    }

    $item = $result['items'][0] ?? [];
    $text = trim((string) ($item['text'] ?? $item['markdown'] ?? $item['content'] ?? ''));
    if ($text === '') {
        $text = ce_hygiene_html_to_text((string) ($item['html'] ?? ''));
    }
    if (mb_strlen($text) < 80) {
        return ['ok' => false, 'text' => null, 'httpStatus' => 200, 'message' => 'Apify: te weinig tekst'];
    }

    return ['ok' => true, 'text' => mb_substr($text, 0, 12000), 'httpStatus' => 200, 'message' => null];
}

/** @return array<string, mixed> */
function ce_hygiene_scrape_competitor(PDO $pdo, string $competitorId): array
{
    ce_ensure_hygiene_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM hygiene_competitors WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $competitorId]);
    $competitor = $stmt->fetch();
    if (!is_array($competitor)) {
        return ['ok' => false, 'error' => 'Concurrent niet gevonden'];
    }

    $urls = ce_hygiene_urls_for_competitor((string) ($competitor['base_url'] ?? ''));
    $pages = [];
    foreach ($urls as $target) {
        $fetch = ce_hygiene_fetch_page_text($target['url']);
        $delete = $pdo->prepare(
            'DELETE FROM hygiene_scrape_pages WHERE competitor_id = :competitor_id AND url = :url'
        );
        $delete->execute(['competitor_id' => $competitorId, 'url' => $target['url']]);
        $insert = $pdo->prepare(
            'INSERT INTO hygiene_scrape_pages (id, competitor_id, page_type, url, text_content, http_status, scraped_at)
             VALUES (:id, :competitor_id, :page_type, :url, :text_content, :http_status, NOW())'
        );
        $insert->execute([
            'id' => ce_uuid(),
            'competitor_id' => $competitorId,
            'page_type' => $target['pageType'],
            'url' => $target['url'],
            'text_content' => $fetch['text'],
            'http_status' => $fetch['httpStatus'],
        ]);
        $pages[] = [
            'pageType' => $target['pageType'],
            'url' => $target['url'],
            'ok' => $fetch['ok'],
            'textLength' => $fetch['text'] !== null ? mb_strlen($fetch['text']) : 0,
            'message' => $fetch['message'],
        ];
    }

    return [
        'ok' => true,
        'competitorId' => $competitorId,
        'name' => (string) ($competitor['name'] ?? ''),
        'pages' => $pages,
    ];
}

/** @return array<string, mixed> */
function ce_hygiene_scrape_list(PDO $pdo, string $listId): array
{
    ce_ensure_hygiene_schema($pdo);
    $stmt = $pdo->prepare('SELECT id, name FROM hygiene_competitors WHERE list_id = :list_id ORDER BY sort_order ASC');
    $stmt->execute(['list_id' => $listId]);
    $competitors = $stmt->fetchAll() ?: [];
    if ($competitors === []) {
        return ['ok' => false, 'error' => 'Geen concurrenten — voeg 6–10 concurrenten toe'];
    }

    $results = [];
    foreach ($competitors as $row) {
        if (!is_array($row)) {
            continue;
        }
        $results[] = ce_hygiene_scrape_competitor($pdo, (string) ($row['id'] ?? ''));
    }

    return ['ok' => true, 'listId' => $listId, 'competitors' => $results];
}

/** @return list<array{competitor: string, pageType: string, url: string, excerpt: string}> */
function ce_hygiene_collect_scrape_corpus(PDO $pdo, string $listId): array
{
    ce_ensure_hygiene_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT c.name AS competitor, p.page_type, p.url, p.text_content
         FROM hygiene_scrape_pages p
         INNER JOIN hygiene_competitors c ON c.id = p.competitor_id
         WHERE c.list_id = :list_id AND p.text_content IS NOT NULL AND TRIM(p.text_content) <> \'\''
    );
    $stmt->execute(['list_id' => $listId]);
    $rows = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $text = trim((string) ($row['text_content'] ?? ''));
        if ($text === '') {
            continue;
        }
        $rows[] = [
            'competitor' => (string) ($row['competitor'] ?? ''),
            'pageType' => (string) ($row['page_type'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'excerpt' => mb_substr($text, 0, 2500),
        ];
    }

    return $rows;
}
