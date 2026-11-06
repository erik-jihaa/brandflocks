<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/theme-repository.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ce_json_error('Method not allowed', 405);
}

$themeId = (string) ($_GET['theme_id'] ?? '');
if ($themeId === '') {
    ce_json_error('theme_id ontbreekt', 400);
}

$pdo = ce_db();

require_once __DIR__ . '/client-context.php';
ce_require_resource_access($pdo, 'theme', $themeId);

try {
    $stmt = $pdo->prepare('SELECT * FROM themes WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Theme niet gevonden');
    }

    $result = ce_trigger_ce02_research($themeId, null);
    if (($result['ok'] ?? false) !== true) {
        $message = is_string($result['error'] ?? null)
            ? $result['error']
            : 'CE-02 webhook mislukt';
        ce_json_error($message, 502);
    }

    $stmt->execute(['id' => $themeId]);
    $row = $stmt->fetch() ?: $row;

    ce_json_response(array_merge($result, [
        'themeId' => $themeId,
        'theme' => ce_theme_dto($pdo, $row),
    ]));
} catch (RuntimeException $e) {
    ce_json_error($e->getMessage(), 404);
}
