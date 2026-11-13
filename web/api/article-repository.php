<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-context.php';

function ce_ensure_headline_human_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (ce_table_has_column($pdo, 'articles', 'headline_human')) {
        return;
    }

    try {
        $pdo->exec('ALTER TABLE articles ADD COLUMN headline_human VARCHAR(500) NULL AFTER headline');
        unset($GLOBALS['ce_table_columns']['articles']);
    } catch (Throwable) {
        // Kolom bestaat al of geen ALTER-rechten — val terug op headline.
    }

    ce_repair_corrupted_headlines($pdo);
}

function ce_ensure_content_human_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'articles') || ce_table_has_column($pdo, 'articles', 'content_human')) {
        return;
    }

    try {
        $pdo->exec('ALTER TABLE articles ADD COLUMN content_human MEDIUMTEXT NULL AFTER content_ai');
        unset($GLOBALS['ce_table_columns']['articles']);
    } catch (Throwable) {
        // Kolom bestaat al of geen ALTER-rechten.
    }
}

function ce_ensure_self_review_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!ce_table_exists($pdo, 'articles') || ce_table_has_column($pdo, 'articles', 'self_review')) {
        return;
    }

    try {
        $pdo->exec('ALTER TABLE articles ADD COLUMN self_review JSON NULL AFTER hashtags_suggested');
        unset($GLOBALS['ce_table_columns']['articles']);
    } catch (Throwable) {
        // Kolom bestaat al of geen ALTER-rechten.
    }
}

/** Herstel AI-baseline die per ongeluk overschreven was (test-titel in headline-kolom). */
function ce_repair_corrupted_headlines(PDO $pdo): void
{
    if (!ce_table_has_column($pdo, 'articles', 'headline_human')) {
        return;
    }

    $stmt = $pdo->prepare(
        <<<'SQL'
        UPDATE articles
        SET headline = :restored,
            headline_human = NULL
        WHERE LOWER(TRIM(headline)) = 'pending test'
          AND (headline_human IS NULL OR TRIM(headline_human) = '')
          AND content_ai LIKE :content_prefix
        SQL
    );
    $stmt->execute([
        'restored' => 'Waarom trage besluitvorming bedrijven geld kost',
        'content_prefix' => 'Je kent het gevoel.%',
    ]);
}

function ce_article_order_sql(PDO $pdo, string $alias = ''): string
{
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    if (ce_table_has_column($pdo, 'articles', 'updated_at')) {
        return "{$prefix}updated_at DESC, {$prefix}id DESC";
    }

    if (ce_table_has_column($pdo, 'articles', 'created_at')) {
        return "{$prefix}created_at DESC, {$prefix}id DESC";
    }

    return "{$prefix}id DESC";
}

/** Redacteur-versie heeft voorrang op AI-concept. */
function ce_article_body_text(array $row): string
{
    $human = trim((string) ($row['content_human'] ?? ''));
    if ($human !== '') {
        return $human;
    }

    return trim((string) ($row['content_ai'] ?? ''));
}

/** Voeg hashtags toe voor mail/review-weergave. */
function ce_article_body_with_hashtags(array $row, string $body): string
{
    $hashtags = trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? ''));
    if ($hashtags === '' || str_contains($body, $hashtags)) {
        return $body;
    }

    return $body . "\n\n" . $hashtags;
}

/** Eerste N woorden voor review-mail preview. */
function ce_content_word_preview(string $text, int $words = 40): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if ($text === '' || $words <= 0) {
        return '';
    }

    $parts = preg_split('/\s+/u', $text, $words + 1) ?: [];
    if (count($parts) <= $words) {
        return $text;
    }

    return implode(' ', array_slice($parts, 0, $words)) . '…';
}

/** Samenvoegen dubbele artikel-rows per slot (stub + CE-03). */
function ce_reassign_article_row_dependencies(PDO $pdo, string $fromId, string $toId): void
{
    if ($fromId === '' || $toId === '' || $fromId === $toId) {
        return;
    }

    if (ce_table_exists($pdo, 'hba_article_hints')) {
        $pdo->prepare('UPDATE hba_article_hints SET article_id = :to_id WHERE article_id = :from_id')
            ->execute(['to_id' => $toId, 'from_id' => $fromId]);
    }
    if (ce_table_exists($pdo, 'hba_scores')) {
        $pdo->prepare('UPDATE hba_scores SET post_id = :to_id WHERE post_id = :from_id')
            ->execute(['to_id' => $toId, 'from_id' => $fromId]);
    }
}

function ce_consolidate_slot_articles(PDO $pdo, string $slotId): void
{
    if ($slotId === '' || !ce_table_exists($pdo, 'articles')) {
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM articles WHERE slot_id = :slot_id');
    $stmt->execute(['slot_id' => $slotId]);
    $rows = $stmt->fetchAll();
    if (count($rows) <= 1) {
        return;
    }

    require_once __DIR__ . '/editor-questions.php';

    $primary = null;
    $bestScore = -1;
    $bestWords = -1;
    foreach ($rows as $row) {
        $score = 0;
        $body = ce_strip_editor_questions_block(trim((string) ($row['content_human'] ?? '')));
        if ($body === '') {
            $body = ce_strip_editor_questions_block(trim((string) ($row['content_ai'] ?? '')));
        }
        $words = $body !== '' ? str_word_count($body) : 0;
        if (ce_article_has_real_body((string) ($row['content_ai'] ?? ''))) {
            $score += 100;
        }
        if (trim((string) ($row['content_human'] ?? '')) !== '') {
            $score += 8;
        }
        if (trim((string) ($row['review_token'] ?? '')) !== '') {
            $score += 16;
        }
        if (trim((string) ($row['image_url_square'] ?? '')) !== '') {
            $score += 2;
        }
        if (trim((string) ($row['image_url_landscape'] ?? '')) !== '') {
            $score += 2;
        }
        if ($score > $bestScore || ($score === $bestScore && $words > $bestWords)) {
            $bestScore = $score;
            $bestWords = $words;
            $primary = $row;
        }
    }

    if (!$primary) {
        return;
    }

    $mergeCols = [
        'image_url_square',
        'image_url_landscape',
        'headline_human',
        'tone_final',
        'angle_final',
        'platform_final',
        'image_ratio_final',
        'hashtags_final',
        'rationale',
    ];
    $patch = [];
    foreach ($mergeCols as $col) {
        if (!ce_table_has_column($pdo, 'articles', $col)) {
            continue;
        }
        if (trim((string) ($primary[$col] ?? '')) !== '') {
            continue;
        }
        foreach ($rows as $row) {
            if ((string) $row['id'] === (string) $primary['id']) {
                continue;
            }
            $value = trim((string) ($row[$col] ?? ''));
            if ($value !== '') {
                $patch[$col] = $value;
                break;
            }
        }
    }

    if ($patch !== []) {
        $sets = [];
        $params = ['id' => $primary['id']];
        foreach ($patch as $col => $value) {
            $sets[] = "{$col} = :{$col}";
            $params[$col] = $value;
        }
        $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    if (
        ce_table_has_column($pdo, 'articles', 'self_review')
        && trim((string) ($primary['self_review'] ?? '')) === ''
    ) {
        foreach ($rows as $row) {
            if ((string) $row['id'] === (string) ($primary['id'] ?? '')) {
                continue;
            }
            $sr = trim((string) ($row['self_review'] ?? ''));
            if ($sr !== '') {
                $pdo->prepare('UPDATE articles SET self_review = :sr WHERE id = :id')
                    ->execute(['sr' => $sr, 'id' => $primary['id']]);
                break;
            }
        }
    }

    $primaryId = (string) ($primary['id'] ?? '');
    foreach ($rows as $row) {
        if ((string) $row['id'] === $primaryId) {
            continue;
        }
        $noToken = trim((string) ($row['review_token'] ?? '')) === '';
        if (!$noToken) {
            continue;
        }
        ce_reassign_article_row_dependencies($pdo, (string) ($row['id'] ?? ''), $primaryId);
        $pdo->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $row['id']]);
    }
}

/**
 * Primair artikel voor een slot (zelfde keuze als Article-UI: eerst rij met inhoud).
 *
 * @return array<string, mixed>|null
 */
function ce_fetch_primary_article_row_for_slot(PDO $pdo, string $slotId): ?array
{
    if ($slotId === '' || !ce_table_exists($pdo, 'articles')) {
        return null;
    }

    ce_ensure_headline_human_column($pdo);
    ce_ensure_content_human_column($pdo);
    ce_ensure_self_review_column($pdo);
    ce_consolidate_slot_articles($pdo, $slotId);
    $order = ce_article_order_sql($pdo);
    $contentPref = ce_table_has_column($pdo, 'articles', 'content_ai')
        ? "(CASE WHEN content_ai IS NOT NULL AND TRIM(content_ai) <> '' THEN 0 ELSE 1 END), "
        : '';

    $stmt = $pdo->prepare(
        <<<SQL
        SELECT *
        FROM articles
        WHERE slot_id = :slot_id
        ORDER BY {$contentPref}{$order}
        LIMIT 1
        SQL
    );
    $stmt->execute(['slot_id' => $slotId]);
    $article = $stmt->fetch();

    return $article ?: null;
}

/** @return array<string, mixed>|null */
function ce_fetch_article_by_slot(PDO $pdo, string $slotId, ?string $clientId = null): ?array
{
    // Eerst eigendom vaststellen: consolideren verwijdert dubbele artikelen en mag
    // nooit draaien voor een slot van een andere tenant.
    if (!ce_slot_belongs_to_client($pdo, $slotId, $clientId)) {
        return null;
    }

    $article = ce_fetch_primary_article_row_for_slot($pdo, $slotId);
    if (!$article) {
        return null;
    }

    $slotStmt = $pdo->prepare(
        'SELECT phase_key, status, theme, client_id FROM content_slots WHERE id = :id LIMIT 1'
    );
    $slotStmt->execute(['id' => $slotId]);
    $slot = $slotStmt->fetch() ?: [];

    require_once __DIR__ . '/theme-repository.php';
    $article = ce_sync_article_editor_from_theme($pdo, $article, $slotId);

    return ce_hydrate_slot_article_images($pdo, array_merge($article, [
        'phase_key' => $slot['phase_key'] ?? null,
        'slot_status' => $slot['status'] ?? null,
        'theme' => $slot['theme'] ?? null,
        'client_id' => $slot['client_id'] ?? null,
    ]));
}

/** @return array<string, mixed>|null */
function ce_fetch_hydrated_article_by_id(PDO $pdo, string $articleId, ?string $clientId = null): ?array
{
    if (!ce_table_exists($pdo, 'articles')) {
        return null;
    }

    ce_ensure_headline_human_column($pdo);
    ce_ensure_content_human_column($pdo);
    ce_ensure_self_review_column($pdo);

    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $articleId]);
    $article = $stmt->fetch();
    if (!$article) {
        return null;
    }

    $slotId = trim((string) ($article['slot_id'] ?? ''));
    if ($slotId === '' || !ce_slot_belongs_to_client($pdo, $slotId, $clientId)) {
        return null;
    }

    $slotStmt = $pdo->prepare(
        'SELECT phase_key, status, theme, client_id FROM content_slots WHERE id = :id LIMIT 1'
    );
    $slotStmt->execute(['id' => $slotId]);
    $slot = $slotStmt->fetch() ?: [];

    require_once __DIR__ . '/theme-repository.php';
    $article = ce_sync_article_editor_from_theme($pdo, $article, $slotId);

    return ce_hydrate_slot_article_images($pdo, array_merge($article, [
        'phase_key' => $slot['phase_key'] ?? null,
        'slot_status' => $slot['status'] ?? null,
        'theme' => $slot['theme'] ?? null,
        'client_id' => $slot['client_id'] ?? null,
    ]));
}

/**
 * GET /articles/{uuid} — uuid is content_slots.id of articles.id (V5 klantpreview-links).
 *
 * @return array<string, mixed>|null
 */
function ce_fetch_article_for_api(PDO $pdo, string $uuid, ?string $clientId = null): ?array
{
    require_once __DIR__ . '/slot-repository.php';
    if (ce_slot_exists($pdo, $uuid)) {
        return ce_fetch_article_by_slot($pdo, $uuid, $clientId);
    }

    return ce_fetch_hydrated_article_by_id($pdo, $uuid, $clientId);
}

/** Voor muterende /articles/{uuid}/… routes: uuid → slot_id. */
function ce_resolve_article_route_slot_id(PDO $pdo, string $uuid, ?string $clientId = null): ?string
{
    require_once __DIR__ . '/slot-repository.php';
    if (ce_slot_exists($pdo, $uuid)) {
        return ce_slot_belongs_to_client($pdo, $uuid, $clientId) ? $uuid : null;
    }

    $row = ce_fetch_hydrated_article_by_id($pdo, $uuid, $clientId);
    if (!$row) {
        return null;
    }

    $slotId = trim((string) ($row['slot_id'] ?? ''));

    return $slotId !== '' ? $slotId : null;
}

/** CE-03 kan een nieuw artikel-row schrijven — afbeeldingen van stub/sibling overnemen. */
function ce_hydrate_slot_article_images(PDO $pdo, array $article): array
{
    $slotId = (string) ($article['slot_id'] ?? '');
    if ($slotId === '' || !ce_table_exists($pdo, 'articles')) {
        return $article;
    }

    $square = trim((string) ($article['image_url_square'] ?? ''));
    $landscape = trim((string) ($article['image_url_landscape'] ?? ''));
    $needsSquare = $square === '' && ce_table_has_column($pdo, 'articles', 'image_url_square');
    $needsLandscape = $landscape === '' && ce_table_has_column($pdo, 'articles', 'image_url_landscape');
    if (!$needsSquare && !$needsLandscape) {
        return $article;
    }

    $cols = [];
    if (ce_table_has_column($pdo, 'articles', 'image_url_square')) {
        $cols[] = 'image_url_square';
    }
    if (ce_table_has_column($pdo, 'articles', 'image_url_landscape')) {
        $cols[] = 'image_url_landscape';
    }
    if ($cols === []) {
        return $article;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . implode(', ', $cols) . ' FROM articles WHERE slot_id = :slot_id'
    );
    $stmt->execute(['slot_id' => $slotId]);

    $patch = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        if ($square === '' && trim((string) ($row['image_url_square'] ?? '')) !== '') {
            $square = trim((string) $row['image_url_square']);
            $patch['image_url_square'] = $square;
        }
        if ($landscape === '' && trim((string) ($row['image_url_landscape'] ?? '')) !== '') {
            $landscape = trim((string) $row['image_url_landscape']);
            $patch['image_url_landscape'] = $landscape;
        }
    }

    // CE-03 kan de rij mét image-URL verwijderd hebben — bestand op schijf is dan leidend.
    if ($square === '') {
        $found = ce_find_slot_image_file($slotId, 'square');
        if ($found !== null) {
            $patch['image_url_square'] = $found;
        }
    }
    if ($landscape === '') {
        $found = ce_find_slot_image_file($slotId, 'landscape');
        if ($found !== null) {
            $patch['image_url_landscape'] = $found;
        }
    }

    if ($patch === []) {
        require_once __DIR__ . '/test-image.php';
        return ce_apply_test_images_if_needed($pdo, $article, true);
    }

    $article = array_merge($article, $patch);

    $articleId = (string) ($article['id'] ?? '');
    if ($articleId !== '') {
        $sets = [];
        $params = ['id' => $articleId];
        foreach ($patch as $column => $value) {
            $sets[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        $pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    require_once __DIR__ . '/test-image.php';

    return ce_apply_test_images_if_needed($pdo, $article, true);
}

/** Upload-bestand voor een slot terugvinden op schijf (slot-gebaseerde bestandsnaam). */
function ce_find_slot_image_file(string $slotId, string $variant): ?string
{
    $dir = dirname(__DIR__) . '/uploads/articles';
    foreach (['webp', 'png', 'jpg'] as $ext) {
        $filename = $slotId . '-' . $variant . '.' . $ext;
        if (is_file($dir . '/' . $filename)) {
            return '/uploads/articles/' . $filename;
        }
    }
    return null;
}

/** @return array<string, mixed> */
function ce_apply_article_patch(PDO $pdo, string $articleId, array $body): array
{
    ce_ensure_headline_human_column($pdo);
    ce_ensure_content_human_column($pdo);
    ce_ensure_article_editor_columns($pdo);
    require_once __DIR__ . '/channel-copy-constraints.php';
    ce_ensure_article_textvorm_column($pdo);
    require_once __DIR__ . '/hba-repository.php';
    ce_ensure_hba_schema($pdo);

    // Body niet overschrijven zolang er open review-edits zijn (feedback-flow).
    if (array_key_exists('content', $body)) {
        require_once __DIR__ . '/review-edits-repository.php';
        $slotStmt = $pdo->prepare('SELECT slot_id FROM articles WHERE id = :id LIMIT 1');
        $slotStmt->execute(['id' => $articleId]);
        $slotId = (string) ($slotStmt->fetchColumn() ?: '');
        if ($slotId !== '' && ce_fetch_review_edits($pdo, $slotId, 'pending') !== []) {
            unset($body['content']);
        }
    }

    $fields = [];
    $params = ['id' => $articleId];

    $headlineColumn = ce_table_has_column($pdo, 'articles', 'headline_human')
        ? 'headline_human'
        : 'headline';

    $map = [
        'headline' => ['column' => $headlineColumn, 'cast' => 'string'],
        'content' => ['column' => 'content_human', 'cast' => 'string'],
        'hashtags' => ['column' => 'hashtags_final', 'cast' => 'string'],
        'tone' => ['column' => 'tone_final', 'cast' => 'tone'],
        'angle' => ['column' => 'angle_final', 'cast' => 'angle'],
        'editor' => ['column' => 'editor_final', 'cast' => 'string'],
        'imageRatio' => ['column' => 'image_ratio_final', 'cast' => 'image_ratio'],
        'platform' => ['column' => 'platform_final', 'cast' => 'platform'],
        'contentTextvorm' => ['column' => 'content_textvorm', 'cast' => 'textvorm'],
        'hbaContentrolOverride' => ['column' => 'hba_contentrol_override', 'cast' => 'contentrol'],
    ];

    foreach ($map as $key => $spec) {
        if (!array_key_exists($key, $body)) {
            continue;
        }
        if (!ce_table_has_column($pdo, 'articles', $spec['column'])) {
            continue;
        }

        $param = $spec['column'];
        $value = (string) $body[$key];
        if ($spec['cast'] === 'tone') {
            $value = ce_tone_to_db($value);
        } elseif ($spec['cast'] === 'platform') {
            $value = ce_platform_to_db($value);
        } elseif ($spec['cast'] === 'textvorm') {
            $platformUi = (string) ($body['platform'] ?? '');
            if ($platformUi === '') {
                $stmt = $pdo->prepare(
                    'SELECT platform_final, platform_suggested FROM articles WHERE id = :id LIMIT 1',
                );
                $stmt->execute(['id' => $articleId]);
                $platformRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $platformUi = ce_platform_to_ui($platformRow['platform_final'] ?? $platformRow['platform_suggested'] ?? null);
            }
            $value = ce_validate_textvorm_for_platform(ce_platform_to_db($platformUi), $value);
        } elseif ($spec['cast'] === 'angle') {
            $value = ce_angle_to_ui($value);
        } elseif ($spec['cast'] === 'image_ratio') {
            $value = ce_image_ratio_to_ui($value);
        } elseif ($spec['cast'] === 'contentrol') {
            $value = trim($value);
            if ($value === '') {
                $fields[] = "{$spec['column']} = NULL";
                continue;
            }
        }

        $fields[] = "{$spec['column']} = :$param";
        $params[$param] = $value;
    }

    if ($fields !== []) {
        $sql = 'UPDATE articles SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }

    if (array_key_exists('objective', $body) || array_key_exists('objectiveId', $body)) {
        require_once __DIR__ . '/objectives-repository.php';
        $objectiveId = trim((string) ($body['objective'] ?? $body['objectiveId'] ?? ''));
        if ($objectiveId !== '') {
            $meta = $pdo->prepare(
                'SELECT a.slot_id, s.client_id
                 FROM articles a
                 LEFT JOIN content_slots s ON s.id = a.slot_id
                 WHERE a.id = :id
                 LIMIT 1'
            );
            $meta->execute(['id' => $articleId]);
            $metaRow = $meta->fetch() ?: [];
            $slotId = trim((string) ($metaRow['slot_id'] ?? ''));
            $clientId = trim((string) ($metaRow['client_id'] ?? ''));
            if ($clientId === '') {
                $clientId = (string) (ce_default_client_id($pdo) ?? '');
            }
            if ($clientId !== '') {
                ce_set_article_objective($pdo, $articleId, $slotId, $objectiveId, $clientId);
            }
        }
    }

    return $params;
}

/**
 * Start CE-03 herschrijf nadat artikel-meta is opgeslagen.
 *
 * @return array{ok: bool, error?: string, http?: int}
 */
function ce_start_article_ce03_rewrite(PDO $pdo, string $slotId, array $row): array
{
    require_once __DIR__ . '/review-edits-repository.php';
    require_once __DIR__ . '/fastlane-repository.php';
    require_once __DIR__ . '/tone-styles.php';

    $status = strtolower(trim((string) ($row['status'] ?? 'draft')));
    if (!in_array($status, ['draft', 'feedback'], true)) {
        return [
            'ok' => false,
            'http' => 409,
            'error' => 'Rewrite is alleen mogelijk voor concepten in redactie.',
        ];
    }

    if (ce_fetch_review_edits($pdo, $slotId, 'pending') !== []) {
        return [
            'ok' => false,
            'http' => 409,
            'error' => 'Los eerst open review-wijzigingen op voordat je herschrijft.',
        ];
    }

    $articleId = trim((string) ($row['id'] ?? ''));
    if ($status === 'feedback' && $articleId !== '') {
        $pdo->prepare("UPDATE articles SET status = 'draft' WHERE id = :id")->execute(['id' => $articleId]);
    }

    $ce03Meta = ce_ce03_webhook_meta_from_slot($pdo, $slotId);
    $trigger = ce_trigger_ce03_write($slotId, true, $ce03Meta);
    if (($trigger['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'http' => 503,
            'error' => (string) ($trigger['error'] ?? 'CE-03 kon niet worden gestart.'),
        ];
    }

    return ['ok' => true];
}

/** @return array{ok: bool, error?: string, httpCode?: int} */
function ce_trigger_ce04_webhook(string $slotId): array
{
    ce_load_env();
    $url = getenv('N8N_CE04_WEBHOOK_URL') ?: '';

    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($url === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $url = (string) ($config['ce04_webhook_url'] ?? '');
        }
    }

    if ($url === '') {
        return ['ok' => false, 'error' => 'N8N_CE04_WEBHOOK_URL is niet geconfigureerd'];
    }

    $payload = json_encode(['slot_id' => $slotId], JSON_THROW_ON_ERROR);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 120,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        $httpCode = (int) $matches[1];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'httpCode' => $httpCode];
    }

    $snippet = is_string($response) ? trim(substr($response, 0, 200)) : '';

    return [
        'ok' => false,
        'httpCode' => $httpCode,
        'error' => $snippet !== '' ? $snippet : 'AI review webhook mislukt (HTTP ' . $httpCode . ')',
    ];
}

function ce_ensure_article_image_columns(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }

    $columns = [
        'image_url_square' => 'VARCHAR(500) NULL',
        'image_url_landscape' => 'VARCHAR(500) NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'articles', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE articles ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
            // Kolom bestaat al of geen ALTER-rechten.
        }
    }
}

function ce_ensure_article_image_source_column(PDO $pdo): void
{
    ce_ensure_article_image_columns($pdo);
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }
    $columns = [
        'image_source' => 'VARCHAR(20) NULL',
    ];
    foreach ($columns as $column => $definition) {
        if (ce_table_has_column($pdo, 'articles', $column)) {
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE articles ADD COLUMN $column $definition");
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
        }
    }
}

/** @return array<string, mixed> */
function ce_upload_article_image(PDO $pdo, string $slotId, string $variant): array
{
    require_once __DIR__ . '/mappers.php';
    ce_ensure_article_image_columns($pdo);

    $variant = strtolower(trim($variant));
    if (!in_array($variant, ['square', 'landscape'], true)) {
        throw new InvalidArgumentException('variant moet square of landscape zijn');
    }

    require_once __DIR__ . '/fastlane-repository.php';

    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        $row = ce_ensure_article_stub_for_slot($pdo, $slotId);
    }

    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        throw new InvalidArgumentException('Geen afbeelding ontvangen');
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Upload mislukt');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Afbeelding mag maximaal 5 MB zijn');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $ext = match ($mime) {
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        default => null,
    };
    if ($ext === null) {
        throw new InvalidArgumentException('Alleen PNG, JPG of WEBP toegestaan');
    }

    $dir = dirname(__DIR__) . '/uploads/articles';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Uploadmap kon niet worden aangemaakt');
    }

    // Slot-gebaseerde bestandsnaam: overleeft het vervangen/verwijderen van article-rows (CE-03).
    $filename = $slotId . '-' . $variant . '.' . $ext;
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Afbeelding opslaan mislukt');
    }

    ce_ensure_article_image_source_column($pdo);

    $column = $variant === 'landscape' ? 'image_url_landscape' : 'image_url_square';
    $url = '/uploads/articles/' . $filename;
    $sql = "UPDATE articles SET $column = :url";
    if (ce_table_has_column($pdo, 'articles', 'image_source')) {
        $sql .= ', image_source = :src';
    }
    $sql .= ' WHERE slot_id = :slot_id';
    $params = ['url' => $url, 'slot_id' => $slotId];
    if (ce_table_has_column($pdo, 'articles', 'image_source')) {
        $params['src'] = 'upload';
    }
    $pdo->prepare($sql)->execute($params);

    if (ce_table_has_column($pdo, 'articles', 'image_ratio_final')) {
        $pdo->prepare(
            'UPDATE articles SET image_ratio_final = :ratio WHERE slot_id = :slot_id',
        )->execute(['ratio' => $variant, 'slot_id' => $slotId]);
    }

    $updated = ce_fetch_article_by_slot($pdo, $slotId);

    return [
        'ok' => true,
        'variant' => $variant,
        'imageUrlSquare' => $updated['image_url_square'] ?? null,
        'imageUrlLandscape' => $updated['image_url_landscape'] ?? null,
    ];
}

/** @return array<string, mixed> */
function ce_attach_gallery_image_to_article(PDO $pdo, string $slotId, string $variant, string $galleryImageId): array
{
    require_once __DIR__ . '/mappers.php';
    require_once __DIR__ . '/client-images-repository.php';
    require_once __DIR__ . '/publish-settings-repository.php';
    ce_ensure_article_image_columns($pdo);

    $variant = strtolower(trim($variant));
    if (!in_array($variant, ['square', 'landscape'], true)) {
        throw new InvalidArgumentException('variant moet square of landscape zijn');
    }

    $galleryImageId = trim($galleryImageId);
    if ($galleryImageId === '') {
        throw new InvalidArgumentException('galleryImageId ontbreekt');
    }

    if (!ce_slot_belongs_to_client($pdo, $slotId)) {
        throw new InvalidArgumentException('Artikel niet gevonden');
    }

    $clientId = ce_client_id_for_slot($pdo, $slotId);
    if ($clientId === null || $clientId === '') {
        throw new InvalidArgumentException('Geen client voor dit artikel');
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM client_images WHERE id = :id AND client_id = :client_id LIMIT 1'
    );
    $stmt->execute(['id' => $galleryImageId, 'client_id' => $clientId]);
    $image = $stmt->fetch();
    if (!$image) {
        throw new InvalidArgumentException('Gallery-beeld niet gevonden');
    }

    $url = trim((string) ($image['url'] ?? ''));
    if ($url === '') {
        throw new InvalidArgumentException('Gallery-beeld heeft geen URL');
    }

    ce_ensure_article_image_source_column($pdo);

    $column = $variant === 'landscape' ? 'image_url_landscape' : 'image_url_square';
    $sql = "UPDATE articles SET {$column} = :url";
    if (ce_table_has_column($pdo, 'articles', 'image_source')) {
        $sql .= ', image_source = :src';
    }
    $sql .= ' WHERE slot_id = :slot_id';
    $params = ['url' => $url, 'slot_id' => $slotId];
    if (ce_table_has_column($pdo, 'articles', 'image_source')) {
        $params['src'] = 'gallery';
    }
    $pdo->prepare($sql)->execute($params);

    if (ce_table_has_column($pdo, 'articles', 'image_ratio_final')) {
        $pdo->prepare(
            'UPDATE articles SET image_ratio_final = :ratio WHERE slot_id = :slot_id',
        )->execute(['ratio' => $variant, 'slot_id' => $slotId]);
    }

    $updated = ce_fetch_article_by_slot($pdo, $slotId);

    return [
        'ok' => true,
        'variant' => $variant,
        'imageUrlSquare' => $updated['image_url_square'] ?? null,
        'imageUrlLandscape' => $updated['image_url_landscape'] ?? null,
    ];
}

/** @return array<string, mixed> */
function ce_delete_article_image(PDO $pdo, string $slotId, string $variant): array
{
    require_once __DIR__ . '/mappers.php';
    ce_ensure_article_image_columns($pdo);

    $variant = strtolower(trim($variant));
    if (!in_array($variant, ['square', 'landscape'], true)) {
        throw new InvalidArgumentException('variant moet square of landscape zijn');
    }

    if (!ce_slot_belongs_to_client($pdo, $slotId)) {
        throw new InvalidArgumentException('Artikel niet gevonden');
    }

    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if (!$row) {
        throw new InvalidArgumentException('Artikel niet gevonden');
    }

    $column = $variant === 'landscape' ? 'image_url_landscape' : 'image_url_square';
    $url = trim((string) ($row[$column] ?? ''));
    if ($url !== '' && str_starts_with($url, '/uploads/articles/')) {
        $path = dirname(__DIR__) . $url;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $pdo->prepare("UPDATE articles SET {$column} = NULL WHERE slot_id = :slot_id")->execute([
        'slot_id' => $slotId,
    ]);

    $updated = ce_fetch_article_by_slot($pdo, $slotId);

    return [
        'ok' => true,
        'variant' => $variant,
        'imageUrlSquare' => $updated['image_url_square'] ?? null,
        'imageUrlLandscape' => $updated['image_url_landscape'] ?? null,
    ];
}

function ce_reedit_strip_trailing_hashtags(string $content): string
{
    $stripped = preg_replace('/\n+(?:#[\p{L}0-9_]+(?:\s+#[\p{L}0-9_]+)*)\s*$/u', '', rtrim($content));

    return rtrim((string) ($stripped ?? $content));
}

/**
 * Verwerk redacteur-aantekeningen in de copy tot een nieuwe versie (geen CE-03).
 *
 * @param array<string, mixed> $body
 * @param array<string, mixed> $row
 * @return array{ok: bool, headline?: string, content?: string, error?: string}
 */
function ce_reedit_article_from_notes(PDO $pdo, string $slotId, array $row, array $body): array
{
    require_once __DIR__ . '/editor-questions.php';
    require_once __DIR__ . '/tone-styles.php';
    require_once __DIR__ . '/hba-v2-repository.php';

    $headline = trim((string) ($body['headline'] ?? ''));
    if ($headline === '') {
        $headline = ce_article_headline($row);
    }
    $content = trim((string) ($body['content'] ?? ''));
    if ($content === '') {
        $content = ce_article_body_text($row);
    }
    $content = ce_reedit_strip_trailing_hashtags($content);
    if (ce_strip_editor_questions_block($content) === '' && $headline === '') {
        return ['ok' => false, 'error' => 'Geen tekst om te bewerken'];
    }

    $clientId = trim((string) ($row['client_id'] ?? ''));
    if ($clientId === '') {
        $slotStmt = $pdo->prepare('SELECT client_id FROM content_slots WHERE id = :id LIMIT 1');
        $slotStmt->execute(['id' => $slotId]);
        $clientId = trim((string) ($slotStmt->fetchColumn() ?: ''));
    }

    $writingGuide = '';
    if ($clientId !== '') {
        $guideStmt = $pdo->prepare('SELECT writing_guide FROM clients WHERE id = :id LIMIT 1');
        $guideStmt->execute(['id' => $clientId]);
        $writingGuide = trim((string) ($guideStmt->fetchColumn() ?: ''));
    }

    $writeDirective = '';
    if ($clientId !== '') {
        $writeDirective = ce_build_article_write_directive(
            $pdo,
            $clientId,
            isset($body['tone']) ? (string) $body['tone'] : ce_tone_to_ui($row['tone_final'] ?? $row['tone_suggested'] ?? null),
            isset($body['angle']) ? (string) $body['angle'] : ce_angle_to_ui($row['angle_final'] ?? $row['angle_suggested'] ?? null),
            isset($body['platform']) ? (string) $body['platform'] : ce_platform_to_ui($row['platform_final'] ?? $row['platform_suggested'] ?? null),
            null,
            isset($body['editor']) ? (string) $body['editor'] : (string) ($row['editor_final'] ?? $row['editor_suggested'] ?? ''),
            $slotId,
            $row,
        );
    }

    $hbaBlock = '';
    if ($clientId !== '' && ce_hba_v2_architecture_ready($pdo, $clientId)) {
        $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
        $hbaLines = array_filter([
            $ctx['archetype'] !== '' ? 'Persoonlijkheid: ' . $ctx['archetype'] : '',
            $ctx['doel'] !== '' ? 'Doel: ' . $ctx['doel'] : '',
            $ctx['foe'] !== '' ? 'Tegenkracht: ' . $ctx['foe'] : '',
            $ctx['distinctiviteit'] !== '' ? 'Anders: ' . $ctx['distinctiviteit'] : '',
            $ctx['wat_bewust_niet'] !== '' ? 'Bewust niet: ' . $ctx['wat_bewust_niet'] : '',
            $ctx['regels'] !== '' ? 'Regels: ' . $ctx['regels'] : '',
        ]);
        if ($hbaLines !== []) {
            $hbaBlock = implode("\n", $hbaLines);
        }
    }

    $system = <<<'PROMPT'
Je bent co-auteur in Brandflocks. De redacteur heeft aantekeningen IN de concepttekst gezet. Die aantekeningen zijn opdrachten, geen copy. Verwerk ze tot een nieuwe, publiceerbare versie van DEZE tekst.

Aantekeningen herken je als (ook zonder haken):
- instructies tussen haakjes of blokhaken, bv. [kort dit], (te zakelijk)
- losse zinnen die over de tekst gaan in plaats van tot de tekst te behoren
- opdrachten zoals "Hier een andere intro", "Niet vanuit ik-perspectief", "maak korter", "te algemeen"
- regels die beginnen met NB, NOTE, TODO, LET OP, REDACTEUR
- antwoorden achter pijltjes (→) of onder een redacteur-blok
- "vervang X door Y"

Regels:
- Voer élke aantekening uit. Laat er geen liggen.
- Verwijder daarna alle aantekeningen uit de eindtekst — ze mogen niet als zin in het artikel blijven staan.
- Bewerk dit concept. Verzin geen nieuw artikel. Bewijs, feiten en kernboodschap blijven.
- Geen markdown, geen hashtags, geen REDACTEUR-blok.
- Houd schrijfwijzer, tone-regels en merkarchitectuur aan als die zijn meegegeven.
- Als er geen aantekeningen zijn: schaaf alleen duidelijk ruwe plekken bij, blijf dicht bij de bestaande tekst.

Antwoord uitsluitend met JSON:
{"headline":"...","content":"..."}
PROMPT;

    $userParts = [
        "HEADLINE:\n" . ($headline !== '' ? $headline : '(geen)'),
        "BODY:\n" . ($content !== '' ? $content : '(geen)'),
    ];
    if ($writeDirective !== '') {
        $userParts[] = "SCHRIJFREGELS:\n" . $writeDirective;
    }
    if ($writingGuide !== '') {
        $userParts[] = "SCHRIJFWIJZER:\n" . $writingGuide;
    }
    if ($hbaBlock !== '') {
        $userParts[] = "MERKARCHITECTUUR:\n" . $hbaBlock;
    }

    $llm = ce_hba_v2_call_json($system, implode("\n\n", $userParts), 0.3, 8192);
    if (!$llm['ok'] || !is_array($llm['data'] ?? null)) {
        return ['ok' => false, 'error' => $llm['error'] ?? 'Re-edit mislukt'];
    }

    $nextHeadline = trim((string) ($llm['data']['headline'] ?? ''));
    $nextContent = ce_reedit_strip_trailing_hashtags(
        ce_strip_editor_questions_block(trim((string) ($llm['data']['content'] ?? ''))),
    );
    if ($nextHeadline === '' && $nextContent === '') {
        return ['ok' => false, 'error' => 'Geen nieuwe tekst ontvangen'];
    }
    if ($nextHeadline === '') {
        $nextHeadline = $headline;
    }
    if ($nextContent === '') {
        $nextContent = ce_strip_editor_questions_block($content);
    }

    return [
        'ok' => true,
        'headline' => $nextHeadline,
        'content' => $nextContent,
    ];
}

const CE_ADHOC_RESEARCH_MAX_PER_SLOT = 9;

function ce_count_adhoc_research_for_slot(PDO $pdo, string $slotId): int
{
    if ($slotId === '' || !ce_table_exists($pdo, 'research_items')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM research_items WHERE slot_id = :slot_id AND source_type = 'adhoc_web'"
    );
    $stmt->execute(['slot_id' => $slotId]);

    return (int) $stmt->fetchColumn();
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $body
 * @return array{ok: bool, content?: string, error?: string}
 */
function ce_soften_claim_in_article(PDO $pdo, string $slotId, array $row, array $body): array
{
    require_once __DIR__ . '/hba-v2-repository.php';

    $claim = trim((string) ($body['claim'] ?? ''));
    $content = trim((string) ($body['content'] ?? ce_article_body_text($row)));
    if ($claim === '' || $content === '') {
        return ['ok' => false, 'error' => 'Claim en artikeltekst zijn verplicht'];
    }

    $clientId = trim((string) ($row['client_id'] ?? ''));
    $regels = '';
    if ($clientId !== '') {
        $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
        $regels = trim((string) ($ctx['regels'] ?? ''));
    }

    $system = <<<'PROMPT'
Je bent redacteur. Herschrijf één zin uit een artikel zodat causale of gezondheidsclaims verdwijnen of zachter worden — zonder de rest van het artikel te wijzigen.

Regels:
- Behoud toon en lengte ongeveer gelijk.
- Verwijder implicaties van gezondheidseffecten tenzij ze al voorzichtig geformuleerd waren.
- Geen markdown, geen uitleg buiten JSON.
- original = exacte passage uit HUIDIGE ARTIKELTEKST die je vervangt (kopieer letterlijk).
- replacement = de zachtere herschrijving van die passage.

Antwoord uitsluitend met JSON: {"original":"...","replacement":"..."}
PROMPT;

    $user = "CLAIM / ZIN:\n{$claim}\n\nVOLLEDIG ARTIKEL (alleen ter context):\n{$content}";
    if ($regels !== '') {
        $user .= "\n\nMERKREGELS:\n{$regels}";
    }

    $llm = ce_hba_v2_call_json($system, $user, 0.2);
    if (($llm['ok'] ?? false) !== true) {
        return ['ok' => false, 'error' => (string) ($llm['error'] ?? 'Herschrijven mislukt')];
    }

    $data = is_array($llm['data'] ?? null) ? $llm['data'] : [];
    $replacement = trim((string) ($data['replacement'] ?? ''));
    $original = trim((string) ($data['original'] ?? ''));
    if ($replacement === '') {
        return ['ok' => false, 'error' => 'Geen herschreven zin ontvangen'];
    }

    $nextContent = ce_replace_article_passage($content, $claim, $original, $replacement);
    if ($nextContent === $content) {
        return ['ok' => false, 'error' => 'Kon de zin niet vinden in de artikeltekst'];
    }

    return ['ok' => true, 'content' => $nextContent];
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $body
 * @return array{ok: bool, content?: string, error?: string}
 */
function ce_substantiate_claim_in_article(PDO $pdo, string $slotId, array $row, array $body): array
{
    require_once __DIR__ . '/hba-v2-repository.php';

    $claim = trim((string) ($body['claim'] ?? ''));
    $content = trim((string) ($body['content'] ?? ce_article_body_text($row)));
    $title = trim((string) ($body['sourceTitle'] ?? $body['title'] ?? ''));
    $url = trim((string) ($body['sourceUrl'] ?? $body['url'] ?? ''));
    $summary = trim((string) ($body['sourceSummary'] ?? $body['quote'] ?? $body['wat'] ?? $body['description'] ?? ''));
    if ($claim === '' || $content === '') {
        return ['ok' => false, 'error' => 'Claim en artikeltekst zijn verplicht'];
    }
    if ($title === '' && $summary === '') {
        return ['ok' => false, 'error' => 'Kies een bron met titel of toelichting'];
    }

    $clientId = trim((string) ($row['client_id'] ?? ''));
    $regels = '';
    if ($clientId !== '') {
        $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
        $regels = trim((string) ($ctx['regels'] ?? ''));
    }

    $system = <<<'PROMPT'
Je bent redacteur. Onderbouw één passage in het artikel met de meegeleverde bron — minimaal ingrijpen, rest ongewijzigd.

Regels:
- Zoek de zin(nen) die het dichtst bij CLAIM liggen; pas alleen dat stuk aan (max. 1–3 zinnen).
- Noem de bron natuurlijk (organisatie/titel); geen URL in lopende tekst.
- Geen nieuwe feiten buiten wat BRON-toelichting/citaat toelaat; geen verzonnen cijfers.
- Behoud toon, taal (NL) en structuur van het artikel.
- Geen markdown.
- original = EXACTE kopie van 1–3 aaneengesloten zinnen uit HUIDIGE ARTIKELTEKST (letterlijk, inclusief leestekens).
- replacement = diezelfde passage, herschreven mét bronvermelding.

Antwoord uitsluitend JSON: {"original":"...","replacement":"..."}
PROMPT;

    $user = "CLAIM / AANNAME IN HET ARTIKEL:\n{$claim}\n\nBRON:\nTitel: {$title}\nURL (alleen ter context): {$url}\nToelichting / citaat:\n{$summary}\n\nHUIDIGE ARTIKELTEKST:\n{$content}";
    if ($regels !== '') {
        $user .= "\n\nMERKREGELS:\n{$regels}";
    }

    $llm = ce_hba_v2_call_json($system, $user, 0.25, 4096);
    if (($llm['ok'] ?? false) !== true) {
        return ['ok' => false, 'error' => (string) ($llm['error'] ?? 'Verwerken mislukt')];
    }

    $data = is_array($llm['data'] ?? null) ? $llm['data'] : [];
    $replacement = trim((string) ($data['replacement'] ?? ''));
    $original = trim((string) ($data['original'] ?? ''));

    if ($replacement !== '') {
        $nextContent = ce_replace_article_passage($content, $claim, $original, $replacement);
        if ($nextContent !== $content) {
            return [
                'ok' => true,
                'content' => $nextContent,
                'replacement' => $replacement,
                'original' => $original,
            ];
        }
    }

    // Fallback: volledige tekst terugschrijven als de exacte passage niet matcht.
    $systemFull = <<<'PROMPT'
Je bent redacteur. Onderbouw de CLAIM in het artikel met de BRON. Lever de VOLLEDIGE artikeltekst terug.

Regels:
- Pas alleen de passage rond de claim aan (1–3 zinnen); rest letterlijk gelijk.
- Noem de bron natuurlijk; geen URL in lopende tekst; geen verzonnen feiten.
- Geen markdown. Antwoord uitsluitend JSON:
  {"content":"...volledige artikeltekst...","replacement":"...alleen de gewijzigde passage..."}
PROMPT;

    $llmFull = ce_hba_v2_call_json($systemFull, $user, 0.25, 8192);
    if (($llmFull['ok'] ?? false) !== true) {
        return ['ok' => false, 'error' => (string) ($llmFull['error'] ?? 'Verwerken mislukt')];
    }

    $dataFull = is_array($llmFull['data'] ?? null) ? $llmFull['data'] : [];
    $fullContent = trim((string) ($dataFull['content'] ?? ''));
    $replacementFull = trim((string) ($dataFull['replacement'] ?? $replacement));
    if ($fullContent === '' || $fullContent === $content) {
        return ['ok' => false, 'error' => 'Kon de passage niet verwerken in de artikeltekst'];
    }

    return [
        'ok' => true,
        'content' => $fullContent,
        'replacement' => $replacementFull !== '' ? $replacementFull : $fullContent,
        'original' => $original,
    ];
}

/**
 * Vervang een passage in artikeltekst: exacte original, genormaliseerde whitespace, claim, fuzzy.
 */
function ce_replace_article_passage(string $content, string $claim, string $original, string $replacement): string
{
    if ($original !== '') {
        if (str_contains($content, $original)) {
            return (string) preg_replace('/' . preg_quote($original, '/') . '/u', $replacement, $content, 1);
        }
        $resolved = ce_find_passage_in_content($content, $original);
        if ($resolved !== null) {
            return (string) preg_replace('/' . preg_quote($resolved, '/') . '/u', $replacement, $content, 1);
        }
    }
    if ($claim !== '' && str_contains($content, $claim)) {
        return (string) preg_replace('/' . preg_quote($claim, '/') . '/u', $replacement, $content, 1);
    }
    if ($claim !== '') {
        $next = preg_replace(
            '/[^\n]{0,160}' . preg_quote($claim, '/') . '[^\n]{0,160}/u',
            $replacement,
            $content,
            1,
        );
        if (is_string($next) && $next !== $content) {
            return $next;
        }
    }

    return $content;
}

/** Zoek een bijna-exacte passage ondanks whitespace-/quote-verschillen. */
function ce_find_passage_in_content(string $content, string $needle): ?string
{
    $needle = trim($needle);
    if ($needle === '' || $needle === $content) {
        return null;
    }
    if (str_contains($content, $needle)) {
        return $needle;
    }

    $normalize = static function (string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
    };

    $normContent = $normalize($content);
    $normNeedle = $normalize($needle);
    if ($normNeedle !== '' && str_contains($normContent, $normNeedle)) {
        // Map terug naar originele content via eerste ~40 tekens.
        $anchor = mb_substr($normNeedle, 0, 40);
        $pos = mb_strpos($content, $anchor);
        if ($pos === false) {
            $pos = mb_strpos($normalize($content), $anchor);
        }
        if ($pos !== false) {
            $slice = mb_substr($content, (int) $pos, mb_strlen($needle) + 40);
            // Trim slice tot einde van genormaliseerde needle-lengte bij benadering.
            return rtrim($slice);
        }
    }

    // Kortere ankers: eerste zin van de needle.
    if (preg_match('/^(.+?[.!?])(\s|$)/u', $needle, $m)) {
        $first = trim($m[1]);
        if (mb_strlen($first) >= 24 && str_contains($content, $first)) {
            return $first;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $body
 * @return array{ok: bool, results?: list<array<string, string>>, error?: string}
 */
function ce_search_claim_evidence(PDO $pdo, string $slotId, array $body): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'slot', $slotId);

    require_once __DIR__ . '/hba-v2-repository.php';
    require_once __DIR__ . '/sources-repository.php';

    $claim = trim((string) ($body['claim'] ?? ''));
    if ($claim === '') {
        return ['ok' => false, 'error' => 'Claim ontbreekt'];
    }

    if (ce_count_adhoc_research_for_slot($pdo, $slotId) >= CE_ADHOC_RESEARCH_MAX_PER_SLOT) {
        return ['ok' => false, 'error' => 'Maximum aantal ad-hoc bronnen voor dit artikel bereikt'];
    }

    $system = <<<'PROMPT'
Je helpt een redacteur een specifieke claim te onderbouwen. Zoek maximaal 3 publieke bronnen (voorkeur: onderzoek, vakmedia, overheidsites).

Antwoord uitsluitend met JSON:
{
  "results": [
    {
      "title": "...",
      "source": "...",
      "url": "https://...",
      "published": "2024 of onbekend",
      "quote": "letterlijk citaat of korte passage die de claim raakt",
      "reliability": "low|medium|high",
      "stance": "bevestigt|nuanceert|spreekt_tegen"
    }
  ]
}

Gebruik alleen URLs die waarschijnlijk bestaan. quote moet concreet zijn, geen samenvatting zonder tekst.
PROMPT;

    $llm = ce_hba_v2_call_json($system, 'CLAIM IN HET ARTIKEL:' . "\n" . $claim, 0.3);
    if (($llm['ok'] ?? false) !== true) {
        return ['ok' => false, 'error' => (string) ($llm['error'] ?? 'Zoeken mislukt')];
    }

    $data = is_array($llm['data'] ?? null) ? $llm['data'] : [];
    $raw = $data['results'] ?? [];
    if (!is_array($raw)) {
        return ['ok' => false, 'error' => 'Geen zoekresultaten ontvangen'];
    }

    $results = [];
    foreach ($raw as $item) {
        if (!is_array($item) || count($results) >= 3) {
            continue;
        }
        $url = trim((string) ($item['url'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $quote = trim((string) ($item['quote'] ?? ''));
        if ($url === '' || $title === '' || $quote === '') {
            continue;
        }
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }

        $probe = ce_probe_webpage_url($url);
        if (($probe['ok'] ?? false) !== true) {
            continue;
        }

        $reliability = trim((string) ($item['reliability'] ?? 'medium'));
        if (!in_array($reliability, ['low', 'medium', 'high'], true)) {
            $reliability = 'medium';
        }
        $stance = trim((string) ($item['stance'] ?? 'bevestigt'));
        if (!in_array($stance, ['bevestigt', 'nuanceert', 'spreekt_tegen'], true)) {
            $stance = 'bevestigt';
        }

        $results[] = [
            'title' => $title,
            'source' => trim((string) ($item['source'] ?? '')),
            'url' => $url,
            'published' => trim((string) ($item['published'] ?? '')),
            'quote' => $quote,
            'reliability' => $reliability,
            'stance' => $stance,
        ];
    }

    if ($results === []) {
        return ['ok' => false, 'error' => 'Geen bereikbare bronnen gevonden voor deze claim'];
    }

    return ['ok' => true, 'results' => $results];
}

/**
 * @param array<string, mixed> $body
 * @return array{ok: bool, item?: array<string, mixed>, error?: string}
 */
function ce_accept_adhoc_research_item(PDO $pdo, string $slotId, array $body): array
{
    require_once __DIR__ . '/client-context.php';
    ce_require_resource_access($pdo, 'slot', $slotId);

    require_once __DIR__ . '/theme-repository.php';
    require_once __DIR__ . '/uuid-helper.php';
    require_once __DIR__ . '/research-quality.php';

    ce_ensure_research_items_schema($pdo);

    if (ce_count_adhoc_research_for_slot($pdo, $slotId) >= CE_ADHOC_RESEARCH_MAX_PER_SLOT) {
        return ['ok' => false, 'error' => 'Maximum aantal ad-hoc bronnen voor dit artikel bereikt'];
    }

    $title = trim((string) ($body['title'] ?? ''));
    $url = trim((string) ($body['url'] ?? ''));
    $quote = trim((string) ($body['quote'] ?? ''));
    if ($title === '' || $url === '' || $quote === '') {
        return ['ok' => false, 'error' => 'Titel, URL en citaat zijn verplicht'];
    }

    $slotStmt = $pdo->prepare('SELECT theme_id FROM content_slots WHERE id = :id LIMIT 1');
    $slotStmt->execute(['id' => $slotId]);
    $themeId = trim((string) ($slotStmt->fetchColumn() ?: ''));

    $reliability = trim((string) ($body['reliability'] ?? 'medium'));
    if (!in_array($reliability, ['low', 'medium', 'high'], true)) {
        $reliability = 'medium';
    }
    $stance = trim((string) ($body['stance'] ?? 'bevestigt'));
    if (!in_array($stance, ['bevestigt', 'nuanceert', 'spreekt_tegen'], true)) {
        $stance = 'bevestigt';
    }

    $snippet = mb_substr($quote, 0, 500);
    if (mb_strlen($snippet) < CE_RESEARCH_MIN_SNIPPET_LEN) {
        $published = trim((string) ($body['published'] ?? ''));
        $source = trim((string) ($body['source'] ?? ''));
        $snippet = trim($quote . ($source !== '' ? ' — ' . $source : '') . ($published !== '' ? ' (' . $published . ')' : ''));
        $snippet = mb_substr($snippet, 0, 500);
    }

    if (!ce_research_snippet_is_usable($snippet)) {
        return ['ok' => false, 'error' => 'Citaat is te kort voor research (minimaal 80 tekens)'];
    }

    $id = ce_uuid_v4();
    $stmt = $pdo->prepare(
        'INSERT INTO research_items (id, theme_id, slot_id, source_id, source_type, title, snippet, url, item_rationale, invalshoek, stance, reliability, feedback)
         VALUES (:id, NULLIF(:theme_id, \'\'), :slot_id, NULL, \'adhoc_web\', :title, :snippet, :url, :item_rationale, NULL, :stance, :reliability, \'none\')'
    );
    $stmt->execute([
        'id' => $id,
        'theme_id' => $themeId,
        'slot_id' => $slotId,
        'title' => $title,
        'snippet' => $snippet,
        'url' => $url,
        'item_rationale' => 'Ad-hoc onderbouwing — door redacteur geaccepteerd.',
        'stance' => $stance,
        'reliability' => $reliability,
    ]);

    $fetch = $pdo->prepare('SELECT * FROM research_items WHERE id = :id LIMIT 1');
    $fetch->execute(['id' => $id]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return ['ok' => false, 'error' => 'Item kon niet worden opgeslagen'];
    }

    return ['ok' => true, 'item' => ce_research_item_dto($row)];
}
