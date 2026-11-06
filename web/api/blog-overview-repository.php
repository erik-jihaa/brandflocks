<?php

declare(strict_types=1);

const CE_BLOG_OVERVIEW_MAX_ITEMS = 20;
const CE_BLOG_OVERVIEW_MIN_SCORE = 2;
const CE_BLOG_OVERVIEW_MAX_PAGE_FETCHES = 8;

function ce_blog_normalize_page_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    return $url;
}

function ce_blog_host_key(string $host): string
{
    $host = strtolower(trim($host));

    return preg_replace('#^www\.#', '', $host) ?? $host;
}

function ce_blog_same_site(string $url, string $pageUrl): bool
{
    $a = parse_url($url);
    $b = parse_url($pageUrl);
    if ($a === false || $b === false || !isset($a['host'], $b['host'])) {
        return false;
    }

    return ce_blog_host_key((string) $a['host']) === ce_blog_host_key((string) $b['host']);
}

function ce_blog_resolve_href(string $href, string $pageUrl): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '') {
        return null;
    }
    $lower = strtolower($href);
    if (
        str_starts_with($href, '#')
        || str_starts_with($lower, 'javascript:')
        || str_starts_with($lower, 'mailto:')
        || str_starts_with($lower, 'tel:')
        || str_starts_with($lower, 'data:')
    ) {
        return null;
    }

    if (!preg_match('#^https?://#i', $href)) {
        $base = parse_url($pageUrl);
        if ($base === false || !isset($base['scheme'], $base['host'])) {
            return null;
        }
        $origin = $base['scheme'] . '://' . $base['host'];
        if (str_starts_with($href, '//')) {
            $href = $base['scheme'] . ':' . $href;
        } elseif (str_starts_with($href, '/')) {
            $href = $origin . $href;
        } else {
            $path = $base['path'] ?? '/';
            $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
            $href = $origin . rtrim($dir, '/') . '/' . $href;
        }
    }

    $href = preg_replace('/#.*$/', '', $href) ?? $href;
    $href = preg_replace('/\?utm_[^#]*/i', '', $href) ?? $href;

    return $href;
}

function ce_blog_url_key(string $url): string
{
    $parts = parse_url($url);
    if ($parts === false) {
        return strtolower(rtrim($url, '/'));
    }
    $host = ce_blog_host_key((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '/');
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return $host . $path;
}

function ce_blog_path_is_excluded(string $path): bool
{
    $path = strtolower($path);
    $path = rtrim($path, '/') ?: '/';

    $exact = [
        '/',
        '/blog',
        '/blogs',
        '/news',
        '/nieuws',
        '/artikelen',
        '/articles',
        '/posts',
        '/inzichten',
        '/insights',
        '/index',
        '/index.html',
        '/blogs.html',
        '/blog.html',
    ];
    if (in_array($path, $exact, true)) {
        return true;
    }

    $prefixes = [
        '/tag/',
        '/tags/',
        '/category/',
        '/categories/',
        '/author/',
        '/authors/',
        '/page/',
        '/paged/',
        '/wp-admin',
        '/wp-json',
        '/wp-content',
        '/wp-includes',
        '/feed',
        '/rss',
        '/cart',
        '/search',
        '/login',
        '/account',
        '/privacy',
        '/cookie',
        '/contact',
        '/about',
        '/over-ons',
        '/overons',
        '/team',
        '/jobs',
        '/vacatures',
        '/shop',
        '/product',
        '/products',
        '/winkel',
        '/cdn-cgi',
    ];
    foreach ($prefixes as $prefix) {
        $bare = rtrim($prefix, '/');
        if ($path === $bare || str_starts_with($path, $prefix) || str_starts_with($path, $bare . '/')) {
            return true;
        }
    }

    return (bool) preg_match(
        '#\.(css|js|png|jpe?g|gif|webp|svg|pdf|xml|zip|ico|woff2?|ttf|mp4|mp3)$#i',
        $path,
    );
}

function ce_blog_link_text_is_cta(string $text): bool
{
    $t = strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    if ($t === '') {
        return true;
    }

    $ctas = [
        'lees meer',
        'read more',
        'meer lezen',
        'meer',
        'verder',
        'view',
        'bekijk',
        'bekijk artikel',
        'continue',
        'next',
        'previous',
        'vorige',
        'volgende',
        'hier',
        'click here',
        'klik hier',
        'meer info',
        'lees verder',
        'view post',
        'open',
    ];

    return in_array($t, $ctas, true);
}

function ce_blog_clean_text(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function ce_blog_strip_noise(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>[\s\S]*?</script>#i', ' ', $html) ?? $html;
    $html = preg_replace('#<style\b[^>]*>[\s\S]*?</style>#i', ' ', $html) ?? $html;
    $html = preg_replace('#<nav\b[^>]*>[\s\S]*?</nav>#i', ' ', $html) ?? $html;
    $html = preg_replace('#<footer\b[^>]*>[\s\S]*?</footer>#i', ' ', $html) ?? $html;
    $html = preg_replace('#<!--[\s\S]*?-->#', ' ', $html) ?? $html;

    return $html;
}

function ce_blog_score_article_url(string $url, string $pageUrl, string $text, int $contextBonus): int
{
    $parts = parse_url($url);
    $path = strtolower((string) ($parts['path'] ?? '/'));
    $score = $contextBonus;

    if (preg_match('#/(blog|blogs|artikel|artikelen|news|nieuws|inzichten|insights|posts|post)/#i', $path)) {
        $score += 4;
    }
    if (preg_match('#/\d{4}/\d{2}/#', $path)) {
        $score += 3;
    }

    $slug = basename($path);
    $slug = preg_replace('#\.(html?|php)$#i', '', $slug) ?? $slug;
    if (substr_count($slug, '-') >= 2) {
        $score += 2;
    }
    if (preg_match('#\.html?$#i', $path)) {
        $score += 1;
    }

    $pageParts = parse_url($pageUrl);
    $pageDir = preg_replace('#/[^/]*$#', '/', (string) ($pageParts['path'] ?? '/')) ?: '/';
    if ($pageDir !== '/' && str_starts_with($path, rtrim($pageDir, '/') . '/')) {
        $score += 1;
    }

    $textLen = mb_strlen(trim($text));
    if ($textLen >= 20 && $textLen <= 160 && !ce_blog_link_text_is_cta($text)) {
        $score += 2;
    }

    if (preg_match('#/(tag|category|author|page|paged)/#i', $path)) {
        $score -= 8;
    }

    return $score;
}

/**
 * @return list<array{url: string, title: string, snippet: string, score: int}>
 */
function ce_blog_extract_overview_candidates(string $html, string $pageUrl): array
{
    $html = ce_blog_strip_noise($html);
    $pageKey = ce_blog_url_key($pageUrl);
    $best = [];

    $consider = static function (
        string $href,
        string $title,
        string $snippet,
        int $contextBonus,
    ) use ($pageUrl, $pageKey, &$best): void {
        $url = ce_blog_resolve_href($href, $pageUrl);
        if ($url === null || !ce_blog_same_site($url, $pageUrl)) {
            return;
        }
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '/');
        if (ce_blog_path_is_excluded($path)) {
            return;
        }
        $key = ce_blog_url_key($url);
        if ($key === $pageKey) {
            return;
        }

        $title = ce_blog_clean_text($title);
        $snippet = ce_blog_clean_text($snippet);
        if (ce_blog_link_text_is_cta($title)) {
            $title = '';
        }

        $score = ce_blog_score_article_url($url, $pageUrl, $title, $contextBonus);
        if ($score < CE_BLOG_OVERVIEW_MIN_SCORE) {
            return;
        }

        $existing = $best[$key] ?? null;
        $betterTitle = $existing !== null && mb_strlen($title) > mb_strlen((string) $existing['title']);
        if ($existing === null || $score > $existing['score'] || ($score === $existing['score'] && $betterTitle)) {
            $best[$key] = [
                'url' => $url,
                'title' => mb_substr($title, 0, 500),
                'snippet' => mb_substr($snippet, 0, 2000),
                'score' => $score,
            ];
        }
    };

    if (preg_match_all('#<article\b[^>]*>([\s\S]*?)</article>#i', $html, $articles)) {
        foreach ($articles[1] as $inner) {
            ce_blog_consider_card_html((string) $inner, '', $consider, 3);
        }
    }

    if (preg_match_all(
        '#<a\b([^>]*\bclass=["\'][^"\']*(?:blog-post|post-card|post-item|blog-item|article-card)[^"\']*["\'][^>]*)>([\s\S]*?)</a>#i',
        $html,
        $cards,
        PREG_SET_ORDER,
    )) {
        foreach ($cards as $card) {
            $href = '';
            if (preg_match('#\bhref=["\']([^"\']+)#i', $card[1], $hm)) {
                $href = $hm[1];
            }
            ce_blog_consider_card_html($card[2], $href, $consider, 3);
        }
    }

    if (preg_match_all('#<h([1-3])\b[^>]*>([\s\S]*?)</h\1>#i', $html, $headings, PREG_SET_ORDER)) {
        foreach ($headings as $heading) {
            $inner = $heading[2];
            if (!preg_match('#<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>([\s\S]*?)</a>#i', $inner, $hm)) {
                continue;
            }
            $consider($hm[1], $hm[2] !== '' ? $hm[2] : $inner, '', 3);
        }
    }

    if (preg_match_all('#<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>([\s\S]*?)</a>#i', $html, $links, PREG_SET_ORDER)) {
        foreach ($links as $link) {
            $consider($link[1], $link[2], '', 0);
        }
    }

    $list = array_values($best);
    usort($list, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

    return array_slice($list, 0, CE_BLOG_OVERVIEW_MAX_ITEMS);
}

/**
 * @param callable(string, string, string, int): void $consider
 */
function ce_blog_consider_card_html(string $inner, string $fallbackHref, callable $consider, int $bonus): void
{
    $href = $fallbackHref;
    if ($href === '' && preg_match('#<a\b[^>]*\bhref=["\']([^"\']+)#i', $inner, $hm)) {
        $href = $hm[1];
    }
    if ($href === '') {
        return;
    }

    $title = '';
    if (preg_match('#<h[1-3][^>]*>([\s\S]*?)</h[1-3]>#i', $inner, $tm)) {
        $title = $tm[1];
    } elseif (preg_match(
        '#<(?:span|div)[^>]*class=["\'][^"\']*(?:title|heading)[^"\']*["\'][^>]*>([\s\S]*?)</(?:span|div)>#i',
        $inner,
        $tm,
    )) {
        $title = $tm[1];
    }

    $snippet = '';
    if (preg_match(
        '#<(?:p|div)[^>]*class=["\'][^"\']*(?:excerpt|summary|intro|teaser)[^"\']*["\'][^>]*>([\s\S]*?)</(?:p|div)>#i',
        $inner,
        $sm,
    )) {
        $snippet = $sm[1];
    } elseif (preg_match('#<p[^>]*>([\s\S]*?)</p>#i', $inner, $sm)) {
        $snippet = $sm[1];
    }

    $consider($href, $title, $snippet, $bonus);
}

/**
 * @return list<array{url: string, title: string, snippet: string}>
 */
function ce_blog_parse_rss_items(string $xml, int $max = CE_BLOG_OVERVIEW_MAX_ITEMS): array
{
    $items = [];
    foreach (['item', 'entry'] as $tag) {
        if (!preg_match_all('#<' . $tag . '\b[\s\S]*?</' . $tag . '>#i', $xml, $blocks)) {
            continue;
        }
        foreach ($blocks[0] as $block) {
            $title = '';
            if (preg_match('#<title[^>]*>([\s\S]*?)</title>#i', $block, $m)) {
                $title = ce_blog_clean_text($m[1]);
            }
            $link = '';
            if (preg_match('#<link[^>]*href=["\']([^"\']+)#i', $block, $m)) {
                $link = trim($m[1]);
            } elseif (preg_match('#<link[^>]*>([\s\S]*?)</link>#i', $block, $m)) {
                $link = ce_blog_clean_text($m[1]);
            }
            $snippet = '';
            if (preg_match('#<(?:description|summary|content)[^>]*>([\s\S]*?)</(?:description|summary|content)>#i', $block, $m)) {
                $snippet = ce_blog_clean_text($m[1]);
            }
            if ($title === '' || $link === '') {
                continue;
            }
            $items[] = [
                'url' => $link,
                'title' => mb_substr($title, 0, 500),
                'snippet' => mb_substr($snippet, 0, 2000),
            ];
            if (count($items) >= $max) {
                return $items;
            }
        }
    }

    return $items;
}

/**
 * @return list<array{url: string, title: string, snippet: string}>
 */
function ce_blog_items_from_sitemap(string $pageUrl, int $max = CE_BLOG_OVERVIEW_MAX_ITEMS): array
{
    $origin = parse_url($pageUrl);
    if ($origin === false || !isset($origin['scheme'], $origin['host'])) {
        return [];
    }
    $base = $origin['scheme'] . '://' . $origin['host'];
    $guesses = [
        $base . '/sitemap.xml',
        $base . '/wp-sitemap-posts-post-1.xml',
        $base . '/post-sitemap.xml',
        $base . '/sitemap_index.xml',
        $base . '/wp-sitemap.xml',
    ];

    $locs = [];
    foreach ($guesses as $sitemapUrl) {
        $body = ce_http_get_body($sitemapUrl, 10);
        if ($body === null || $body === '') {
            continue;
        }
        if (!preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $body, $m)) {
            continue;
        }
        foreach ($m[1] as $loc) {
            $loc = trim(html_entity_decode((string) $loc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($loc === '' || !ce_blog_same_site($loc, $pageUrl)) {
                continue;
            }
            if (preg_match('#sitemap.*\.xml$#i', $loc)) {
                continue;
            }
            $path = (string) (parse_url($loc, PHP_URL_PATH) ?? '/');
            if (ce_blog_path_is_excluded($path)) {
                continue;
            }
            $score = ce_blog_score_article_url($loc, $pageUrl, '', 1);
            if ($score < CE_BLOG_OVERVIEW_MIN_SCORE) {
                continue;
            }
            $locs[ce_blog_url_key($loc)] = $loc;
            if (count($locs) >= $max) {
                break 2;
            }
        }
        if ($locs !== []) {
            break;
        }
    }

    $items = [];
    foreach (array_values($locs) as $url) {
        $items[] = [
            'url' => $url,
            'title' => '',
            'snippet' => '',
        ];
    }

    return $items;
}

function ce_blog_ensure_http_helpers(): void
{
    if (!function_exists('ce_http_fetch_url')) {
        require_once __DIR__ . '/sources-repository.php';
    }
}

/**
 * @return list<array{url: string, title: string, snippet: string}>
 */
function ce_blog_fetch_rss_items_from_feed_urls(array $feedUrls): array
{
    ce_blog_ensure_http_helpers();
    foreach ($feedUrls as $feedUrl) {
        if (!is_string($feedUrl) || trim($feedUrl) === '') {
            continue;
        }
        $feedBody = ce_http_get_body($feedUrl, 12);
        if ($feedBody === null) {
            continue;
        }
        $rssItems = ce_blog_parse_rss_items($feedBody);
        if ($rssItems !== []) {
            return $rssItems;
        }
    }

    return [];
}

/**
 * When the overview page cannot be fetched (WAF, timeout), try /feed/ guesses and Google News RSS.
 *
 * @return array{ok: bool, message: string, itemCount: int, kind: string, items: list<array{url: string, title: string, snippet: string}>}|null
 */
function ce_blog_discover_unreachable_fallback(string $pageUrl): ?array
{
    ce_blog_ensure_http_helpers();

    $feedGuesses = function_exists('ce_rss_guess_feed_urls_for_page')
        ? ce_rss_guess_feed_urls_for_page($pageUrl)
        : [];
    $rssItems = ce_blog_fetch_rss_items_from_feed_urls($feedGuesses);
    if ($rssItems !== []) {
        $count = count($rssItems);

        return [
            'ok' => true,
            'message' => $count === 1
                ? 'RSS-feed gevonden — 1 artikel (overzichtspagina niet bereikbaar).'
                : "RSS-feed gevonden — {$count} artikelen (overzichtspagina niet bereikbaar).",
            'itemCount' => $count,
            'kind' => 'blog_rss',
            'items' => $rssItems,
        ];
    }

    $googleFeedUrls = [];
    if (function_exists('ce_google_news_rss_url_for_page')) {
        $scoped = ce_google_news_rss_url_for_page($pageUrl);
        if ($scoped !== null) {
            $googleFeedUrls[] = $scoped;
        }
    }
    if (function_exists('ce_google_news_rss_url_for_site')) {
        $siteWide = ce_google_news_rss_url_for_site($pageUrl);
        if ($siteWide !== null) {
            $googleFeedUrls[] = $siteWide;
        }
    }
    $googleFeedUrls = array_values(array_unique($googleFeedUrls));
    $rssItems = ce_blog_fetch_rss_items_from_feed_urls($googleFeedUrls);
    if ($rssItems === []) {
        return null;
    }

    $host = preg_replace('#^www\.#', '', strtolower((string) parse_url($pageUrl, PHP_URL_HOST)));
    $count = count($rssItems);

    return [
        'ok' => true,
        'message' => $count === 1
            ? "1 artikel via Google News (site:{$host} — overzichtspagina blokkeert bots)."
            : "{$count} artikelen via Google News (site:{$host} — overzichtspagina blokkeert bots).",
        'itemCount' => $count,
        'kind' => 'blog_rss',
        'items' => $rssItems,
    ];
}

/**
 * @return array{ok: bool, message: string, itemCount: int, kind: string, items?: list<array{url: string, title: string, snippet: string}>}
 */
function ce_discover_blog_overview(string $url, bool $hydratePages = false): array
{
    $url = ce_blog_normalize_page_url($url);
    if ($url === '') {
        return ['ok' => false, 'message' => 'URL ontbreekt.', 'itemCount' => 0, 'kind' => 'blog_index'];
    }
    if (ce_url_looks_like_local_path($url)) {
        return [
            'ok' => false,
            'message' => 'Gebruik een web-URL (https://…).',
            'itemCount' => 0,
            'kind' => 'blog_index',
        ];
    }

    ce_blog_ensure_http_helpers();
    $fetch = ce_http_fetch_url($url, 15);
    $body = $fetch['body'];
    if ($body === null || $body === '' || $fetch['httpCode'] >= 400) {
        $botBlocked = function_exists('ce_http_body_is_bot_block')
            && ce_http_body_is_bot_block($body, $fetch['httpCode']);
        $fallback = ce_blog_discover_unreachable_fallback($url);
        if ($fallback !== null) {
            return $fallback;
        }

        if ($botBlocked) {
            return [
                'ok' => false,
                'message' => 'Blogoverzicht blokkeert automatische toegang (403). Geen RSS of Google News-alternatief gevonden.',
                'itemCount' => 0,
                'kind' => 'blog_index',
                'botBlocked' => true,
            ];
        }

        $googleHint = '';
        if (function_exists('ce_google_news_rss_url_for_site')) {
            $googleUrl = ce_google_news_rss_url_for_site($url);
            if ($googleUrl !== null) {
                $googleHint = ' Probeer als RSS-bron: ' . $googleUrl;
            }
        }
        $connHint = $fetch['httpCode'] === 0
            ? 'Geen verbinding met de site (timeout of geblokkeerd). '
            : '';

        return [
            'ok' => false,
            'message' => trim($connHint . 'Blogoverzicht kon niet worden opgehaald.' . $googleHint),
            'itemCount' => 0,
            'kind' => 'blog_index',
        ];
    }

    $feedUrls = ce_rss_discover_feed_urls($url, $body);
    foreach ($feedUrls as $feedUrl) {
        $feedBody = ce_http_get_body($feedUrl, 12);
        if ($feedBody === null) {
            continue;
        }
        $rssItems = ce_blog_parse_rss_items($feedBody);
        if ($rssItems !== []) {
            return [
                'ok' => true,
                'message' => count($rssItems) === 1
                    ? 'RSS-feed gevonden — 1 artikel.'
                    : 'RSS-feed gevonden — ' . count($rssItems) . ' artikelen.',
                'itemCount' => count($rssItems),
                'kind' => 'blog_rss',
                'items' => $rssItems,
            ];
        }
    }

    $candidates = ce_blog_extract_overview_candidates($body, $url);
    $kind = 'blog_index';
    if (count($candidates) < 3) {
        $sitemapItems = ce_blog_items_from_sitemap($url);
        if (count($sitemapItems) > count($candidates)) {
            $candidates = array_map(
                static fn (array $row): array => $row + ['score' => 2],
                $sitemapItems,
            );
            $kind = 'blog_sitemap';
        }
    }

    if ($candidates === []) {
        $fallback = ce_blog_discover_unreachable_fallback($url);
        if ($fallback !== null) {
            return $fallback;
        }

        return [
            'ok' => false,
            'message' => 'Geen blogposts gevonden op deze overzichtspagina (pagina leeg of alleen JavaScript).',
            'itemCount' => 0,
            'kind' => 'blog_index',
        ];
    }

    $items = [];
    $fetched = 0;
    foreach ($candidates as $row) {
        $title = trim((string) ($row['title'] ?? ''));
        $snippet = trim((string) ($row['snippet'] ?? ''));
        $itemUrl = (string) $row['url'];
        if ($hydratePages && ($title === '' || $snippet === '') && $fetched < CE_BLOG_OVERVIEW_MAX_PAGE_FETCHES) {
            $pageBody = ce_http_get_body($itemUrl, 8);
            $fetched++;
            if ($pageBody !== null) {
                $meta = ce_parse_webpage_metadata($pageBody, $itemUrl);
                if ($meta !== null) {
                    if ($title === '') {
                        $title = $meta['title'];
                    }
                    if ($snippet === '') {
                        $snippet = $meta['snippet'];
                    }
                }
            }
        }
        if ($title === '') {
            $slug = basename((string) (parse_url($itemUrl, PHP_URL_PATH) ?? ''));
            $slug = preg_replace('#\.(html?|php)$#i', '', $slug) ?? $slug;
            $title = trim(str_replace(['-', '_'], ' ', $slug));
        }
        if ($title === '') {
            continue;
        }
        $items[] = [
            'url' => $itemUrl,
            'title' => mb_substr($title, 0, 500),
            'snippet' => mb_substr($snippet !== '' ? $snippet : 'Blogpost: ' . $itemUrl, 0, 2000),
        ];
    }

    if ($items === []) {
        return [
            'ok' => false,
            'message' => 'Geen leesbare blogposts gevonden op deze overzichtspagina.',
            'itemCount' => 0,
            'kind' => $kind,
        ];
    }

    $count = count($items);
    $label = $kind === 'blog_sitemap' ? 'via sitemap' : 'op overzichtspagina';

    return [
        'ok' => true,
        'message' => $count === 1
            ? "1 blogpost gevonden {$label}."
            : "{$count} blogposts gevonden {$label}.",
        'itemCount' => $count,
        'kind' => $kind,
        'items' => $items,
    ];
}

/** @return array{ok: bool, message: string, itemCount: int, kind: string} */
function ce_probe_blog_overview(string $url): array
{
    $found = ce_discover_blog_overview($url, false);
    unset($found['items'], $found['botBlocked']);

    return $found;
}

/**
 * @return list<array{url: string, title: string, snippet: string}>
 */
function ce_fetch_blog_overview_items(string $url, int $max = CE_BLOG_OVERVIEW_MAX_ITEMS): array
{
    $found = ce_discover_blog_overview($url, true);
    $items = $found['items'] ?? [];
    if ($items === []) {
        return [];
    }

    return array_slice($items, 0, max(1, $max));
}

/** @return array{ok: bool, items: list<array<string, mixed>>, run: array<string, mixed>} */
function ce_run_blog_source(PDO $pdo, string $sourceId): array
{
    $stmt = $pdo->prepare('SELECT id, title, url, category FROM sources WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Source not found']];
    }
    if ((string) ($row['category'] ?? '') !== 'blogs') {
        return ['ok' => false, 'items' => [], 'run' => ['status' => 'error', 'message' => 'Geen blog-bron']];
    }

    $url = trim((string) ($row['url'] ?? ''));
    $title = trim((string) ($row['title'] ?? 'Blogs'));
    $rawItems = ce_fetch_blog_overview_items($url);
    $items = [];
    foreach ($rawItems as $item) {
        $items[] = [
            'title' => $item['title'],
            'url' => $item['url'],
            'snippet' => $item['snippet'],
            'source_id' => $sourceId,
            'source_title' => $title !== '' ? $title : 'Blogs',
            'source_type' => 'blog',
        ];
    }

    return [
        'ok' => true,
        'items' => $items,
        'run' => [
            'status' => $items === [] ? 'empty' : 'ok',
            'message' => $items === []
                ? 'Geen blogposts gevonden.'
                : count($items) . ' blogposts ingelezen.',
            'items_count' => count($items),
        ],
    ];
}
