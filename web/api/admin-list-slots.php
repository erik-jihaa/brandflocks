<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/schema-helper.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();

$clientId = trim((string) ($_GET['client_id'] ?? ''));
if ($clientId === '' && ($_GET['list_clients'] ?? '') === '1') {
    $cols = ['id', 'name', 'contact_email', 'publish_mode'];
    if (ce_table_has_column($pdo, 'clients', 'wordpress_site_url')) {
        $cols[] = 'wordpress_site_url';
    }
    $rows = $pdo->query('SELECT ' . implode(', ', $cols) . ' FROM clients ORDER BY name ASC')->fetchAll() ?: [];
    ce_json_response(['ok' => true, 'clients' => $rows]);
}

$search = trim((string) ($_GET['search'] ?? ''));
if ($clientId === '' && $search !== '') {
    $stmt = $pdo->prepare(
        "SELECT a.id AS article_id, a.slot_id, a.status, a.headline, a.headline_human,
                c.id AS client_id, c.name AS client_name
         FROM articles a
         LEFT JOIN content_slots s ON s.id = a.slot_id
         LEFT JOIN clients c ON c.id = s.client_id
         WHERE a.headline LIKE :q OR a.headline_human LIKE :q
         LIMIT 20"
    );
    $stmt->execute(['q' => '%' . $search . '%']);
    ce_json_response(['ok' => true, 'matches' => $stmt->fetchAll() ?: []]);
}

if (!preg_match('/^[0-9a-f-]{36}$/i', $clientId)) {
    ce_json_error('Invalid client_id', 400);
}

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));

$slotCols = ['id', 'theme_id', 'title', 'phase_key', 'status', 'created_at', 'updated_at'];
$slotCols = array_values(array_filter($slotCols, fn ($c) => ce_table_has_column($pdo, 'content_slots', $c)));

$stmt = $pdo->prepare(
    'SELECT ' . implode(', ', $slotCols) . " FROM content_slots WHERE client_id = :client_id
     ORDER BY created_at DESC LIMIT $limit"
);
$stmt->execute(['client_id' => $clientId]);
$slots = $stmt->fetchAll() ?: [];

$articleCols = ['id', 'slot_id', 'status', 'headline', 'headline_human', 'content_ai', 'content_human', 'created_at', 'updated_at', 'image_url_square', 'image_url_landscape', 'wordpress_post_id'];
$articleCols = array_values(array_filter($articleCols, fn ($c) => ce_table_has_column($pdo, 'articles', $c)));

$result = [];
foreach ($slots as $slot) {
    $slotId = (string) ($slot['id'] ?? '');
    $article = null;
    if ($slotId !== '') {
        $aStmt = $pdo->prepare(
            'SELECT ' . implode(', ', $articleCols) . ' FROM articles WHERE slot_id = :slot_id ORDER BY id DESC LIMIT 1'
        );
        $aStmt->execute(['slot_id' => $slotId]);
        $article = $aStmt->fetch() ?: null;
        if ($article) {
            foreach (['content_ai', 'content_human'] as $c) {
                if (isset($article[$c]) && is_string($article[$c])) {
                    $article[$c . '_len'] = strlen($article[$c]);
                    $article[$c] = substr($article[$c], 0, 200);
                }
            }
        }
    }

    $result[] = [
        'slot' => $slot,
        'article' => $article,
    ];
}

$clientRow = null;
if (ce_table_exists($pdo, 'clients')) {
    $cStmt = $pdo->prepare('SELECT id, name, publish_mode, planning_horizon_weeks, posts_per_week FROM clients WHERE id = :id LIMIT 1');
    $cStmt->execute(['id' => $clientId]);
    $clientRow = $cStmt->fetch() ?: null;
}

$themes = [];
if (ce_table_exists($pdo, 'themes')) {
    $tStmt = $pdo->prepare(
        'SELECT id, title, cycle_week, phase_key, status, feedback, created_at FROM themes
         WHERE client_id = :client_id ORDER BY cycle_week DESC, created_at DESC LIMIT 30'
    );
    $tStmt->execute(['client_id' => $clientId]);
    $themes = $tStmt->fetchAll() ?: [];
}

$sourcesCount = null;
if (ce_table_exists($pdo, 'sources')) {
    $sStmt = $pdo->prepare("SELECT COUNT(*) FROM sources WHERE client_id = :client_id AND COALESCE(active, 1) = 1 AND category <> 'asana'");
    $sStmt->execute(['client_id' => $clientId]);
    $sourcesCount = (int) $sStmt->fetchColumn();
}

$clientImages = [];
if (ce_table_exists($pdo, 'client_images')) {
    $iStmt = $pdo->prepare('SELECT id, filename, url, alt_text, tags, uploaded_at FROM client_images WHERE client_id = :client_id ORDER BY uploaded_at DESC LIMIT 20');
    $iStmt->execute(['client_id' => $clientId]);
    $clientImages = $iStmt->fetchAll() ?: [];
}

ce_json_response([
    'ok' => true,
    'client' => $clientRow,
    'activeSourcesCount' => $sourcesCount,
    'count' => count($result),
    'slots' => $result,
    'themes' => $themes,
    'clientImages' => $clientImages,
]);
