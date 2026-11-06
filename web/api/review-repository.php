<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';
require_once __DIR__ . '/mappers.php';

function ce_ensure_review_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_exists($pdo, 'articles') && !ce_table_has_column($pdo, 'articles', 'review_token')) {
        try {
            $pdo->exec(
                'ALTER TABLE articles ADD COLUMN review_token VARCHAR(64) NULL AFTER status,'
                . ' ADD COLUMN review_sent_at DATETIME NULL AFTER review_token'
            );
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }

    if (ce_table_exists($pdo, 'article_review_edits')) {
        if (!ce_table_has_column($pdo, 'article_review_edits', 'slot_id')) {
            try {
                $pdo->exec('ALTER TABLE article_review_edits ADD COLUMN slot_id CHAR(36) NULL AFTER article_id');
                unset($GLOBALS['ce_table_columns']['article_review_edits']);
            } catch (Throwable) {
                // Kolom bestaat al of geen ALTER-rechten.
            }
        }
        if (!ce_table_has_column($pdo, 'article_review_edits', 'progress_json')) {
            try {
                $pdo->exec('ALTER TABLE article_review_edits ADD COLUMN progress_json TEXT NULL AFTER status');
                unset($GLOBALS['ce_table_columns']['article_review_edits']);
            } catch (Throwable) {
                // Kolom bestaat al of geen ALTER-rechten.
            }
        }
        return;
    }

    try {
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS article_review_edits (
              id CHAR(36) NOT NULL PRIMARY KEY,
              article_id CHAR(36) NOT NULL,
              slot_id CHAR(36) NOT NULL,
              original_text TEXT NOT NULL,
              suggested_text TEXT NOT NULL,
              position_start INT NULL,
              position_end INT NULL,
              status VARCHAR(20) NOT NULL DEFAULT 'pending',
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              KEY idx_article (article_id),
              KEY idx_slot (slot_id)
            )
            SQL
        );
    } catch (Throwable) {
        // Tabel bestaat al.
    }
}

function ce_generate_review_token(): string
{
    return bin2hex(random_bytes(32));
}

function ce_public_base_url(): string
{
    ce_load_env();
    $base = trim((string) (getenv('CE_PUBLIC_BASE_URL') ?: ''));

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($base === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $base = trim((string) ($config['public_base_url'] ?? ''));
        }
    }

    if ($base !== '') {
        return rtrim($base, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'ce.igisnext.com';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $host;
}

function ce_review_page_url(string $token): string
{
    return ce_public_base_url() . '/review/' . rawurlencode($token);
}

/** @return array{reviewUrl?: string} */
function ce_article_review_meta(array $row): array
{
    $token = trim((string) ($row['review_token'] ?? ''));
    if ($token === '' || !ce_is_pending_review_status($row['status'] ?? null)) {
        return [];
    }

    return ['reviewUrl' => ce_review_page_url($token)];
}

/** @return array<string, mixed>|null */
function ce_fetch_article_by_review_token(PDO $pdo, string $token): ?array
{
    ce_ensure_review_schema($pdo);
    ce_ensure_content_human_column($pdo);

    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        SELECT
            a.*,
            s.phase_key,
            s.status AS slot_status,
            s.theme,
            s.client_id
        FROM articles a
        JOIN content_slots s ON s.id = a.slot_id
        WHERE a.review_token = :token
        LIMIT 1
        SQL
    );
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $slotId = (string) ($row['slot_id'] ?? '');
    if ($slotId !== '') {
        ce_consolidate_slot_articles($pdo, $slotId);
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch() ?: $row;
        $row = ce_hydrate_slot_article_images($pdo, $row);
    }

    return $row;
}

/** Regel-diff voor review-edits: regel-hunks i.p.v. één documentblok. */
function ce_build_review_edit_fragments(string $original, string $edited): array
{
    if ($original === $edited) {
        return [];
    }

    $baseLines = explode("\n", $original);
    $editLines = explode("\n", $edited);
    $parts = ce_diff_string_arrays($baseLines, $editLines);

    $fragments = [];
    $offset = 0;
    $pendingRemoved = [];

    foreach ($parts as $part) {
        if ($part['removed']) {
            $pendingRemoved = array_merge($pendingRemoved, $part['value']);
            continue;
        }
        if ($part['added']) {
            $removed = $pendingRemoved;
            $pendingRemoved = [];
            $fragments[] = [
                'original_text' => implode("\n", $removed),
                'suggested_text' => implode("\n", $part['value']),
                'position_start' => $offset,
                'position_end' => $offset + strlen(implode("\n", $removed)),
            ];
            $offset += strlen(implode("\n", $part['value']));
            continue;
        }
        if ($pendingRemoved !== []) {
            $fragments[] = [
                'original_text' => implode("\n", $pendingRemoved),
                'suggested_text' => '',
                'position_start' => $offset,
                'position_end' => $offset + strlen(implode("\n", $pendingRemoved)),
            ];
            $pendingRemoved = [];
        }
        $chunk = implode("\n", $part['value']);
        $offset += strlen($chunk) + (count($part['value']) > 0 ? 1 : 0);
    }

    if ($pendingRemoved !== []) {
        $fragments[] = [
            'original_text' => implode("\n", $pendingRemoved),
            'suggested_text' => '',
            'position_start' => $offset,
            'position_end' => $offset + strlen(implode("\n", $pendingRemoved)),
        ];
    }

    if ($fragments === []) {
        return [[
            'original_text' => $original,
            'suggested_text' => $edited,
            'position_start' => 0,
            'position_end' => strlen($original),
        ]];
    }

    return $fragments;
}

/**
 * Myers-achtige LCS-diff op string-arrays (zelfde aanpak als frontend diffArrays).
 *
 * @return list<array{value: list<string>, added?: bool, removed?: bool}>
 */
function ce_diff_string_arrays(array $old, array $new): array
{
    $n = count($old);
    $m = count($new);
    $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $m - 1; $j >= 0; $j--) {
            if ($old[$i] === $new[$j]) {
                $dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
            } else {
                $dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
    }

    $parts = [];
    $i = 0;
    $j = 0;
    while ($i < $n && $j < $m) {
        if ($old[$i] === $new[$j]) {
            $parts[] = ['value' => [$old[$i]]];
            $i++;
            $j++;
            continue;
        }
        if ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
            $parts[] = ['value' => [$old[$i]], 'removed' => true];
            $i++;
        } else {
            $parts[] = ['value' => [$new[$j]], 'added' => true];
            $j++;
        }
    }
    while ($i < $n) {
        $parts[] = ['value' => [$old[$i]], 'removed' => true];
        $i++;
    }
    while ($j < $m) {
        $parts[] = ['value' => [$new[$j]], 'added' => true];
        $j++;
    }

    // Merge aangrenzende delen van hetzelfde type
    $merged = [];
    foreach ($parts as $part) {
        $type = !empty($part['added']) ? 'added' : (!empty($part['removed']) ? 'removed' : 'same');
        if ($merged === []) {
            $merged[] = $part;
            continue;
        }
        $last = &$merged[count($merged) - 1];
        $lastType = !empty($last['added']) ? 'added' : (!empty($last['removed']) ? 'removed' : 'same');
        if ($lastType === $type) {
            $last['value'] = array_merge($last['value'], $part['value']);
        } else {
            $merged[] = $part;
        }
    }

    return $merged;
}

function ce_store_review_edits(
    PDO $pdo,
    string $articleId,
    string $slotId,
    string $original,
    string $edited,
): int {
    ce_ensure_review_schema($pdo);

    if (!ce_table_exists($pdo, 'article_review_edits')) {
        return 0;
    }

    $pdo->prepare('DELETE FROM article_review_edits WHERE article_id = :article_id AND status = :status')
        ->execute(['article_id' => $articleId, 'status' => 'pending']);

    // Eén consolidated edit — regel-hunks worden client-side opgebouwd (trackedChanges).
    $id = ce_uuid();
    $pdo->prepare(
        <<<'SQL'
        INSERT INTO article_review_edits
            (id, article_id, slot_id, original_text, suggested_text, position_start, position_end, status)
        VALUES
            (:id, :article_id, :slot_id, :original_text, :suggested_text, 0, :position_end, 'pending')
        SQL
    )->execute([
        'id' => $id,
        'article_id' => $articleId,
        'slot_id' => $slotId,
        'original_text' => $original,
        'suggested_text' => $edited,
        'position_end' => strlen($original),
    ]);

    return ce_build_review_edit_fragments($original, $edited) !== [] ? 1 : 0;
}

function ce_is_pending_review_status(?string $value): bool
{
    return ce_is_submitted_status($value);
}

/** @return array<string, mixed> */
function ce_format_review_payload(array $row, ?PDO $pdo = null): array
{
    $content = ce_article_body_text($row);
    $content = ce_article_body_with_hashtags($row, $content);
    $imageRatio = ce_image_ratio_to_ui($row['image_ratio_final'] ?? $row['image_ratio_suggested'] ?? null);
    $square = trim((string) ($row['image_url_square'] ?? ''));
    $landscape = trim((string) ($row['image_url_landscape'] ?? ''));
    if ($pdo !== null) {
        require_once __DIR__ . '/test-image.php';
        $clientId = trim((string) ($row['client_id'] ?? ''));
        if (ce_client_uses_manual_image_upload($pdo, $clientId)) {
            if ($square !== '' && ce_is_test_image_url($square)) {
                $square = '';
            }
            if ($landscape !== '' && ce_is_test_image_url($landscape)) {
                $landscape = '';
            }
        }
    }
    $imageUrl = $imageRatio === 'landscape'
        ? ($landscape !== '' ? $landscape : $square)
        : ($square !== '' ? $square : $landscape);

    return [
        'articleId' => $row['id'],
        'slotId' => $row['slot_id'],
        'weekLabel' => ce_week_label($row['phase_key'] ?? null),
        'headline' => ce_article_headline($row),
        'content' => $content,
        'contentBaseline' => $content,
        'hashtags' => trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? '')),
        'rationale' => $row['rationale'] ?? '',
        'tone' => ce_tone_to_ui($row['tone_final'] ?? $row['tone_suggested'] ?? null),
        'angle' => $row['angle_final'] ?? $row['angle_suggested'] ?? 'general',
        'platform' => ce_platform_to_ui($row['platform_final'] ?? $row['platform_suggested'] ?? null),
        'imageRatio' => $imageRatio,
        'imageUrlSquare' => $square !== '' ? $square : null,
        'imageUrlLandscape' => $landscape !== '' ? $landscape : null,
        'imageUrl' => $imageUrl !== '' ? $imageUrl : null,
        'status' => ce_status_to_ui($row['status'] ?? null),
        'canDecide' => ce_is_pending_review_status($row['status'] ?? null),
    ];
}

/** @return array<string, mixed> */
function ce_format_review_payload_with_edits(PDO $pdo, array $row): array
{
    require_once __DIR__ . '/review-edits-repository.php';

    $payload = ce_format_review_payload($row, $pdo);
    $slotId = (string) ($row['slot_id'] ?? '');
    if ($slotId === '') {
        return $payload;
    }

    $payload['reviewEdits'] = ce_fetch_review_edits($pdo, $slotId);
    ce_apply_pending_review_content($payload);
    $payload['contentBaseline'] = ce_article_body_with_hashtags(
        $row,
        ce_article_body_text($row),
    );

    return $payload;
}

function ce_apply_human_review_token(PDO $pdo, string $articleId): string
{
    ce_ensure_review_schema($pdo);

    $token = ce_generate_review_token();
    $params = [
        'id' => $articleId,
        'token' => $token,
    ];

    $sets = ['review_token = :token'];
    if (ce_table_has_column($pdo, 'articles', 'review_sent_at')) {
        $sets[] = 'review_sent_at = NOW()';
    }

    $sql = 'UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $pdo->prepare($sql)->execute($params);

    return $token;
}

function ce_review_email(?PDO $pdo = null, ?string $clientId = null): string
{
    if ($pdo instanceof PDO) {
        require_once __DIR__ . '/client-context.php';
        $fromClient = ce_client_reviewer_email($pdo, $clientId);
        if ($fromClient !== null && $fromClient !== '') {
            return $fromClient;
        }
    }

    ce_load_env();
    $email = trim((string) (getenv('CE_REVIEW_EMAIL') ?: ''));

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($email === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $email = trim((string) ($config['review_email'] ?? ''));
        }
    }

    return $email !== '' ? $email : 'erik@igisnext.com';
}

/** @return array{ok: bool, error?: string, httpCode?: int, reviewerEmail?: string} */
function ce_trigger_ce04_human_webhook(
    string $slotId,
    string $articleId,
    string $reviewToken,
    array $overrides = [],
): array {
    ce_load_env();
    $url = getenv('N8N_CE04_HUMAN_WEBHOOK_URL') ?: '';

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($url === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $url = (string) ($config['ce04_human_webhook_url'] ?? '');
        }
    }

    if ($url === '') {
        return ['ok' => false, 'error' => 'N8N_CE04_HUMAN_WEBHOOK_URL is niet geconfigureerd'];
    }

    require_once __DIR__ . '/article-repository.php';
    $pdo = ce_db();
    ce_consolidate_slot_articles($pdo, $slotId);
    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        return ['ok' => false, 'error' => 'Artikel niet gevonden'];
    }
    $clientId = isset($row['client_id']) ? (string) $row['client_id'] : null;
    $reviewerEmail = ce_review_email($pdo, $clientId);

    $content = ce_article_body_with_hashtags($row, ce_article_body_text($row));
    $headline = ce_article_headline($row);
    if (isset($overrides['headline']) && trim((string) $overrides['headline']) !== '') {
        $headline = trim((string) $overrides['headline']);
    }
    if (isset($overrides['content']) && trim((string) $overrides['content']) !== '') {
        $bodyOverride = trim((string) $overrides['content']);
        $tagsOverride = trim((string) ($overrides['hashtags'] ?? ''));
        $content = $tagsOverride !== '' && !str_contains($bodyOverride, $tagsOverride)
            ? $bodyOverride . "\n\n" . $tagsOverride
            : $bodyOverride;
    }

    $previewBody = isset($overrides['content']) && trim((string) $overrides['content']) !== ''
        ? trim((string) $overrides['content'])
        : ce_article_body_text($row);
    $mailPreview = ce_content_word_preview($previewBody, 40);

    $resubmitted = !empty($overrides['resubmitted']);
    $imageRatio = ce_resolve_article_image_ratio($row);
    $square = trim((string) ($row['image_url_square'] ?? ''));
    $landscape = trim((string) ($row['image_url_landscape'] ?? ''));
    $imageUrl = $imageRatio === 'landscape'
        ? ($landscape !== '' ? $landscape : $square)
        : ($square !== '' ? $square : $landscape);

    $payload = json_encode([
        'slot_id' => $slotId,
        'article_id' => $articleId,
        'review_token' => $reviewToken,
        'review_url' => ce_review_page_url($reviewToken),
        'reviewer_email' => $reviewerEmail,
        'to' => $reviewerEmail,
        'headline' => $headline,
        'rationale' => (string) ($row['rationale'] ?? ''),
        'content' => $content,
        'body' => $content,
        'mail_content' => $content,
        'content_preview' => $mailPreview,
        'mail_preview' => $mailPreview,
        'open_review_label' => 'Open review',
        'content_human' => ce_article_body_text($row),
        'hashtags' => trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? '')),
        'image_url_square' => $square !== '' ? $square : null,
        'image_url_landscape' => $landscape !== '' ? $landscape : null,
        'image_url' => $imageUrl !== '' ? $imageUrl : null,
        'image_ratio' => $imageRatio,
        'resubmitted' => $resubmitted,
    ], JSON_THROW_ON_ERROR);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        $httpCode = (int) $matches[1];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'httpCode' => $httpCode, 'reviewerEmail' => $reviewerEmail];
    }

    $snippet = is_string($response) ? trim(substr($response, 0, 200)) : '';

    return [
        'ok' => false,
        'httpCode' => $httpCode,
        'reviewerEmail' => $reviewerEmail,
        'error' => $snippet !== '' ? $snippet : 'Human review webhook mislukt (HTTP ' . $httpCode . ')',
    ];
}
