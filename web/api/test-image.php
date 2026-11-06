<?php

declare(strict_types=1);

/** Placeholder-beeld voor sandbox/test-posts zonder upload. */
function ce_test_image_url(string $variant): string
{
    return '/images/test-image-' . ($variant === 'landscape' ? 'landscape' : 'square') . '.png';
}

function ce_is_test_image_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    return str_contains($url, '/images/test-image-square.png')
        || str_contains($url, '/images/test-image-landscape.png');
}

/** Alle publish-modes zijn handmatig: geen placeholder-beeld — gebruiker uploadt zelf. */
function ce_client_uses_manual_image_upload(PDO $pdo, ?string $clientId): bool
{
    return trim((string) $clientId) !== '';
}

/**
 * Verwijder test-placeholders uit article-row (en optioneel DB) voor handmatige modes.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function ce_strip_test_images_from_row(PDO $pdo, array $row, bool $persist = true): array
{
    $patch = [];
    foreach (['image_url_square', 'image_url_landscape'] as $column) {
        $url = trim((string) ($row[$column] ?? ''));
        if ($url !== '' && ce_is_test_image_url($url)) {
            $patch[$column] = null;
            $row[$column] = null;
        }
    }

    if ($patch === [] || !$persist || !ce_table_exists($pdo, 'articles')) {
        return $row;
    }

    $articleId = trim((string) ($row['id'] ?? ''));
    if ($articleId === '') {
        return $row;
    }

    $sets = [];
    $params = ['id' => $articleId];
    foreach ($patch as $column => $value) {
        if (!ce_table_has_column($pdo, 'articles', $column)) {
            continue;
        }
        $sets[] = "{$column} = :{$column}";
        $params[$column] = $value;
    }
    if ($sets !== []) {
        $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    return $row;
}

/** Test-post: sandbox-tenant, theme/titel met "test", of expliciete test-titel. */
function ce_is_test_post(PDO $pdo, array $row): bool
{
    $headline = strtolower(trim((string) ($row['headline_human'] ?? $row['headline'] ?? '')));
    if ($headline !== '' && preg_match('/\btest\b/', $headline)) {
        return true;
    }

    $theme = strtolower(trim((string) ($row['theme'] ?? '')));
    if ($theme !== '' && str_contains($theme, 'test')) {
        return true;
    }

    $clientId = trim((string) ($row['client_id'] ?? ''));
    if ($clientId === '') {
        return false;
    }

    static $clientNames = [];
    if (!array_key_exists($clientId, $clientNames)) {
        $stmt = $pdo->prepare('SELECT LOWER(name) FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $clientNames[$clientId] = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
    }

    return str_contains($clientNames[$clientId], 'sandbox');
}

/**
 * Vul lege square/landscape-URL met test-beeld; optioneel persist in articles.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function ce_apply_test_images_if_needed(PDO $pdo, array $row, bool $persist = true): array
{
    $clientId = trim((string) ($row['client_id'] ?? ''));
    if (ce_client_uses_manual_image_upload($pdo, $clientId)) {
        return ce_strip_test_images_from_row($pdo, $row, $persist);
    }

    if (!ce_is_test_post($pdo, $row)) {
        return $row;
    }

    $patch = [];
    $square = trim((string) ($row['image_url_square'] ?? ''));
    $landscape = trim((string) ($row['image_url_landscape'] ?? ''));
    if ($square === '') {
        $patch['image_url_square'] = ce_test_image_url('square');
    }
    if ($landscape === '') {
        $patch['image_url_landscape'] = ce_test_image_url('landscape');
    }
    if ($patch === []) {
        return $row;
    }

    $row = array_merge($row, $patch);

    if (!$persist || !ce_table_exists($pdo, 'articles')) {
        return $row;
    }

    $slotId = trim((string) ($row['slot_id'] ?? ''));
    if ($slotId === '') {
        return $row;
    }

    $sets = [];
    $params = ['slot_id' => $slotId];
    foreach ($patch as $column => $value) {
        if (!ce_table_has_column($pdo, 'articles', $column)) {
            continue;
        }
        $sets[] = "{$column} = :{$column}";
        $params[$column] = $value;
    }
    if ($sets !== []) {
        $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE slot_id = :slot_id')
            ->execute($params);
    }

    return $row;
}
