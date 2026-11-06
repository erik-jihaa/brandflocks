<?php

declare(strict_types=1);

require_once __DIR__ . '/credential-crypto.php';

/** @return array{key: string, token: string}|null */
function ce_trello_auth(): ?array
{
    $key = trim((string) (getenv('TRELLO_API_KEY') ?: ''));
    $token = trim((string) (getenv('TRELLO_TOKEN') ?: ''));

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if (is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            if ($key === '') {
                $key = trim((string) ($config['trello_api_key'] ?? ''));
            }
            if ($token === '') {
                $token = trim((string) ($config['trello_token'] ?? ''));
            }
        }
    }

    if ($key === '' || $token === '') {
        return null;
    }

    return ['key' => $key, 'token' => $token];
}

/** @return array<string, mixed>|null */
function ce_trello_get(string $path, array $query = [], ?array $auth = null): ?array
{
    $auth ??= ce_trello_auth();
    if ($auth === null) {
        return null;
    }

    $query['key'] = $auth['key'];
    $query['token'] = $auth['token'];
    $path = ltrim($path, '/');
    $url = 'https://api.trello.com/1/' . $path . '?' . http_build_query($query);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
            'user_agent' => 'ContentEngine/1.0',
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

/** @return list<array{id: string, name: string, url: string}> */
function ce_list_trello_boards(): array
{
    $member = ce_trello_get('members/me', ['fields' => 'id,username', 'boards' => 'open', 'board_fields' => 'name,url']);
    if ($member === null || !isset($member['boards']) || !is_array($member['boards'])) {
        return [];
    }

    $boards = [];
    foreach ($member['boards'] as $board) {
        if (!is_array($board)) {
            continue;
        }
        $id = trim((string) ($board['id'] ?? ''));
        $name = trim((string) ($board['name'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }
        $boards[] = [
            'id' => $id,
            'name' => $name,
            'url' => (string) ($board['url'] ?? ''),
        ];
    }

    usort($boards, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $boards;
}

function ce_probe_trello_board(string $boardId, ?array $auth = null): bool
{
    $boardId = trim($boardId);
    if ($boardId === '') {
        return false;
    }

    $board = ce_trello_get('boards/' . rawurlencode($boardId), ['fields' => 'name,closed'], $auth);
    if ($board === null) {
        return false;
    }

    return ($board['closed'] ?? false) !== true;
}

/** @return array{key: string, token: string}|null */
function ce_trello_auth_for_source(?string $sourceToken): ?array
{
    $base = ce_trello_auth();
    if ($base === null) {
        return null;
    }

    $token = trim((string) ($sourceToken ?? ''));
    if ($token === '') {
        return $base;
    }

    if (ce_credential_is_encrypted($token)) {
        $decrypted = ce_credential_try_decrypt($token);
        if ($decrypted === null || $decrypted === '') {
            return $base;
        }
        $token = $decrypted;
    }

    if ($token === '') {
        return $base;
    }

    return ['key' => $base['key'], 'token' => $token];
}

function ce_trello_boards_webhook_url(): string
{
    ce_load_env();

    $webhook = trim((string) (getenv('N8N_TRELLO_BOARDS_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['trello_boards_webhook_url'] ?? ''));
        }
    }
    if ($webhook === '') {
        $n8nBase = rtrim((string) (getenv('N8N_API_URL') ?: 'https://erikjihaa.app.n8n.cloud'), '/');
        $webhook = $n8nBase . '/webhook/ce-trello-boards';
    }

    return $webhook;
}

function ce_trello_cards_webhook_url(string $boardId): string
{
    ce_load_env();

    $boardId = trim($boardId);
    $webhook = trim((string) (getenv('N8N_TRELLO_CARDS_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['trello_cards_webhook_url'] ?? ''));
        }
    }
    if ($webhook === '') {
        $n8nBase = rtrim((string) (getenv('N8N_API_URL') ?: 'https://erikjihaa.app.n8n.cloud'), '/');
        $webhook = $n8nBase . '/webhook/ce-trello-cards';
    }

    return $webhook . '?board_id=' . rawurlencode($boardId);
}

/** @return list<array{id: string, name: string, url: string}> */
function ce_list_trello_boards_via_n8n(): array
{
    $raw = @file_get_contents(ce_trello_boards_webhook_url());
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !is_array($decoded['boards'] ?? null)) {
        return [];
    }

    $boards = [];
    foreach ($decoded['boards'] as $board) {
        if (!is_array($board)) {
            continue;
        }
        $id = trim((string) ($board['id'] ?? ''));
        $name = trim((string) ($board['name'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }
        $boards[] = [
            'id' => $id,
            'name' => $name,
            'url' => (string) ($board['url'] ?? ''),
        ];
    }

    usort($boards, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $boards;
}

/** @return list<array<string, mixed>> */
function ce_fetch_trello_cards_via_n8n(string $boardId): array
{
    $boardId = trim($boardId);
    if ($boardId === '') {
        return [];
    }

    $raw = @file_get_contents(ce_trello_cards_webhook_url($boardId));
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !is_array($decoded['cards'] ?? null)) {
        return [];
    }

    return array_values(array_filter(
        $decoded['cards'],
        static fn ($card): bool => is_array($card) && trim((string) ($card['id'] ?? '')) !== '',
    ));
}

/** @return array{ok: bool, message: ?string, cardCount: int} */
function ce_trello_probe_result_from_card_count(int $cardCount): array
{
    if ($cardCount === 0) {
        return ['ok' => false, 'message' => 'Board bereikbaar maar bevat geen kaarten.', 'cardCount' => 0];
    }

    return [
        'ok' => true,
        'message' => $cardCount === 1 ? '1 kaart gevonden.' : "{$cardCount} kaarten gevonden.",
        'cardCount' => $cardCount,
    ];
}

/** Direct Trello API, met n8n-fallback (CE-Trello Boards workflow). */
function ce_list_trello_boards_resolved(): array
{
    $boards = ce_list_trello_boards();
    if ($boards !== []) {
        return $boards;
    }

    return ce_list_trello_boards_via_n8n();
}

function ce_probe_trello_board_resolved(string $boardId, ?string $sourceToken = null): bool
{
    $boardId = trim($boardId);
    if ($boardId === '') {
        return false;
    }

    $auth = ce_trello_auth_for_source($sourceToken !== '' ? $sourceToken : null);
    if ($auth !== null && ce_probe_trello_board($boardId, $auth)) {
        return true;
    }

    foreach (ce_list_trello_boards_via_n8n() as $board) {
        if (($board['id'] ?? '') === $boardId) {
            return true;
        }
    }

    return false;
}

/** @return array{ok: bool, message: ?string, cardCount: int} */
function ce_probe_trello_board_content(string $boardId, ?string $sourceToken = null): array
{
    $boardId = trim($boardId);
    if ($boardId === '') {
        return ['ok' => false, 'message' => 'Board-ID ontbreekt.', 'cardCount' => 0];
    }

    $auth = ce_trello_auth_for_source($sourceToken !== '' ? $sourceToken : null);
    if ($auth !== null && ce_probe_trello_board($boardId, $auth)) {
        $cards = ce_trello_get(
            'boards/' . rawurlencode($boardId) . '/cards',
            ['filter' => 'visible', 'fields' => 'id', 'limit' => 100],
            $auth,
        );
        if (is_array($cards)) {
            return ce_trello_probe_result_from_card_count(count($cards));
        }
    }

    if (!ce_probe_trello_board_resolved($boardId, $sourceToken)) {
        return ['ok' => false, 'message' => 'Trello-board niet bereikbaar.', 'cardCount' => 0];
    }

    $n8nCards = ce_fetch_trello_cards_via_n8n($boardId);
    if ($n8nCards !== []) {
        return ce_trello_probe_result_from_card_count(count($n8nCards));
    }

    return ['ok' => true, 'message' => null, 'cardCount' => -1];
}
