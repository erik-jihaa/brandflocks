<?php

declare(strict_types=1);

function ce_youtube_normalize_input(string $url): string
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

function ce_youtube_extract_channel_id_from_url(string $url): ?string
{
    $url = ce_youtube_normalize_input($url);
    if ($url === '') {
        return null;
    }

    if (preg_match('#youtube\.com/channel/(UC[a-zA-Z0-9_-]{22})#i', $url, $m)) {
        return $m[1];
    }

    if (preg_match('#youtube\.com/feeds/videos\.xml\?channel_id=(UC[a-zA-Z0-9_-]{22})#i', $url, $m)) {
        return $m[1];
    }

    return null;
}

function ce_youtube_extract_channel_id_from_html(string $html): ?string
{
    if (preg_match('#"channelId"\s*:\s*"(UC[a-zA-Z0-9_-]{22})"#', $html, $m)) {
        return $m[1];
    }
    if (preg_match('#"externalId"\s*:\s*"(UC[a-zA-Z0-9_-]{22})"#', $html, $m)) {
        return $m[1];
    }
    if (preg_match('#itemprop="channelId"\s+content="(UC[a-zA-Z0-9_-]{22})"#i', $html, $m)) {
        return $m[1];
    }

    return null;
}

function ce_youtube_resolve_channel_id(string $url): ?string
{
    $direct = ce_youtube_extract_channel_id_from_url($url);
    if ($direct !== null) {
        return $direct;
    }

    $url = ce_youtube_normalize_input($url);
    if ($url === '') {
        return null;
    }

    $fetch = ce_http_fetch_url($url, 15);
    $body = $fetch['body'];
    if ($body === null || $body === '' || $fetch['httpCode'] >= 400) {
        return null;
    }

    return ce_youtube_extract_channel_id_from_html($body);
}

function ce_youtube_feed_url_for_channel_id(string $channelId): string
{
    return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
}

function ce_youtube_resolve_feed_url(string $url): ?string
{
    $channelId = ce_youtube_resolve_channel_id($url);
    if ($channelId === null) {
        return null;
    }

    return ce_youtube_feed_url_for_channel_id($channelId);
}

/** @return array{ok: bool, message: string, itemCount: int, kind: string, resolvedFeedUrl?: string} */
function ce_probe_youtube_channel(string $url): array
{
    $url = ce_youtube_normalize_input($url);
    if ($url === '') {
        return ['ok' => false, 'message' => 'YouTube-URL ontbreekt.', 'itemCount' => 0, 'kind' => 'youtube_rss'];
    }
    if (ce_url_looks_like_local_path($url)) {
        return ['ok' => false, 'message' => 'Gebruik een web-URL (https://…).', 'itemCount' => 0, 'kind' => 'youtube_rss'];
    }

    $feedUrl = ce_youtube_resolve_feed_url($url);
    if ($feedUrl === null) {
        return [
            'ok' => false,
            'message' => 'Kon geen YouTube-kanaal vinden — gebruik een kanaal-URL (youtube.com/channel/… of @handle).',
            'itemCount' => 0,
            'kind' => 'youtube_rss',
        ];
    }

    $probe = ce_probe_rss_feed($feedUrl, false);
    if (!($probe['ok'] ?? false)) {
        return [
            'ok' => false,
            'message' => $probe['message'] ?? 'YouTube-feed kon niet worden gelezen.',
            'itemCount' => 0,
            'kind' => 'youtube_rss',
        ];
    }

    $count = (int) ($probe['itemCount'] ?? 0);
    $countMsg = $probe['message'] ?? '';

    return [
        'ok' => true,
        'message' => trim($countMsg . ' Feed: ' . $feedUrl),
        'itemCount' => $count,
        'kind' => 'youtube_rss',
        'resolvedFeedUrl' => $feedUrl,
    ];
}
