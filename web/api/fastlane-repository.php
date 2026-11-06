<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/client-repository.php';
require_once __DIR__ . '/sources-repository.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/slot-repository.php';
require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/tone-styles.php';

/** @param array<string, mixed> $body */
function ce_create_fastlane(PDO $pdo, array $body): array
{
    $title = trim((string) ($body['title'] ?? $body['theme'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Theme of the article is verplicht');
    }
    if (function_exists('mb_substr')) {
        $title = mb_substr($title, 0, 250);
    } elseif (strlen($title) > 250) {
        $title = substr($title, 0, 250);
    }

    $extraThoughts = trim((string) ($body['extraThoughts'] ?? $body['extra_thoughts'] ?? $body['notes'] ?? ''));
    $format = trim((string) ($body['format'] ?? ''));
    if ($format !== '') {
        $formatLine = 'Format: ' . $format;
        $extraThoughts = $extraThoughts !== '' ? $formatLine . "\n" . $extraThoughts : $formatLine;
    }
    $rawUrls = $body['researchUrls'] ?? $body['research_urls'] ?? [];
    if (!is_array($rawUrls)) {
        $rawUrls = [];
    }

    $urls = [];
    foreach ($rawUrls as $url) {
        $u = trim((string) $url);
        if ($u === '') {
            continue;
        }
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }
        if (!filter_var($u, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Ongeldige research-URL: ' . $u);
        }
        $urls[] = $u;
        if (count($urls) >= 3) {
            break;
        }
    }

    if (!ce_table_exists($pdo, 'themes') || !ce_table_exists($pdo, 'content_slots')) {
        throw new RuntimeException('themes of content_slots-tabel ontbreekt');
    }

    $explicitClient = trim((string) ($body['clientId'] ?? $body['client_id'] ?? ''));
    $clientId = ce_resolve_client_id($pdo, $explicitClient !== '' ? $explicitClient : null);
    if ($clientId === null) {
        throw new RuntimeException('Geen actieve klant gevonden');
    }

    $slotOrigin = trim((string) ($body['origin'] ?? 'fastlane'));
    if ($slotOrigin === '') {
        $slotOrigin = 'fastlane';
    }

    $themeId = ce_uuid_v4();
    $slotId = ce_uuid_v4();
    $articleId = ce_uuid_v4();
    $scheduledWeek = (int) ($body['scheduledWeek'] ?? $body['scheduled_week'] ?? 0);
    $scheduledDate = trim((string) ($body['scheduledDate'] ?? $body['scheduled_date'] ?? ''));
    // Campagne-week 1 = start week; week N = start + N-1 ISO-weken.
    if ($scheduledDate !== '') {
        $phaseKey = ce_phase_key_from_date($scheduledDate) ?? (string) ce_week_start();
    } elseif ($scheduledWeek > 0) {
        $phaseKey = (string) (ce_week_start() + $scheduledWeek - 1);
    } else {
        $phaseKey = (string) ce_week_start();
    }

    $hook = trim((string) ($body['hook'] ?? ''));
    if ($hook !== '') {
        $hookLine = 'Hook: ' . $hook;
        $extraThoughts = $extraThoughts !== '' ? $hookLine . "\n" . $extraThoughts : $hookLine;
    }

    $briefing = trim((string) ($body['rationale'] ?? $body['briefing'] ?? ''));
    $rationaleParts = [];
    if ($hook !== '') {
        $rationaleParts[] = $hook;
    }
    if ($briefing !== '') {
        $rationaleParts[] = $briefing;
    }
    $rationaleCombined = implode("\n\n", $rationaleParts);

    $angle = trim((string) ($body['angle'] ?? ''));
    if ($angle === '') {
        $angle = 'archetype';
    }

    $meta = [
        'tone' => trim((string) ($body['tone'] ?? '')),
        'angle' => $angle,
        'editor' => trim((string) ($body['editor'] ?? '')),
        'imageRatio' => trim((string) ($body['imageRatio'] ?? $body['image_ratio'] ?? '')),
        'platform' => trim((string) ($body['platform'] ?? '')),
        'rationale' => $rationaleCombined,
        'tags' => $body['tags'] ?? [],
        'objective' => trim((string) ($body['objective'] ?? $body['objectiveId'] ?? $body['objective_id'] ?? '')),
    ];

    require_once __DIR__ . '/objectives-repository.php';
    ce_ensure_objectives_schema($pdo);
    $objectiveId = (string) ($meta['objective'] ?? '');
    if ($objectiveId !== '') {
        $objCheck = $pdo->prepare(
            'SELECT id FROM objectives WHERE id = :id AND client_id = :client_id LIMIT 1'
        );
        $objCheck->execute(['id' => $objectiveId, 'client_id' => $clientId]);
        if (!$objCheck->fetchColumn()) {
            $objectiveId = '';
            $meta['objective'] = '';
        }
    }

    // Fastlane UI: eerst research; expliciet writeImmediately → direct CE-03.
    $writeImmediately = filter_var($body['writeImmediately'] ?? $body['write_immediately'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $pdo->beginTransaction();

    try {
        ce_insert_fastlane_theme(
            $pdo,
            $themeId,
            $clientId,
            $title,
            $rationaleCombined,
            $meta,
            $objectiveId !== '' ? $objectiveId : null,
        );
        $origin = $slotOrigin;
        ce_insert_fastlane_slot(
            $pdo,
            $slotId,
            $clientId,
            $themeId,
            $title,
            $extraThoughts,
            $phaseKey,
            $origin,
            $writeImmediately
                ? ['proposed', 'planned', 'open', 'draft']
                : ['open', 'planned', 'research_done'],
            $objectiveId !== '' ? $objectiveId : null,
        );
        ce_insert_fastlane_research($pdo, $themeId, $urls, $extraThoughts, $slotId);
        if ($writeImmediately) {
            ce_insert_fastlane_article($pdo, $articleId, $slotId, $title, $meta);
        }
        ce_mark_theme_converted($pdo, $themeId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $researchHydration = ce_hydrate_manual_fastlane_research($pdo, $slotId);

    $webhook = ['ok' => false, 'webhookTriggered' => false];
    $ce02 = ['ok' => false, 'webhookTriggered' => false];
    if ($writeImmediately) {
        $ce03Meta = ce_ce03_webhook_meta_from_slot($pdo, $slotId);
        $webhook = ce_trigger_ce03_write($slotId, true, $ce03Meta);
    } else {
        require_once __DIR__ . '/theme-repository.php';
        $ce02 = ce_trigger_ce02_research($themeId, $slotId, true);
    }

    return [
        'ok' => true,
        'themeId' => $themeId,
        'slotId' => $slotId,
        'articleId' => $writeImmediately ? $articleId : null,
        'title' => $title,
        'phase' => $writeImmediately ? 'write' : 'research',
        'researchUrlCount' => count($urls),
        'researchHydration' => $researchHydration,
        'hasExtraThoughts' => $extraThoughts !== '',
        'webhookTriggered' => ($webhook['webhookTriggered'] ?? false) === true,
        'ce02Triggered' => ($ce02['ok'] ?? false) === true,
        'ce02WebhookAcknowledged' => ($ce02['webhookTriggered'] ?? false) === true,
        'webhookResult' => $webhook['response'] ?? null,
    ];
}

/** Verwijder lege artikel-stubs vóór CE-03 (voorkomt dubbele rijen / lege Article-pagina). */
function ce_clear_empty_slot_articles(PDO $pdo, string $slotId): void
{
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }

    $conditions = ['slot_id = :slot_id'];
    if (ce_table_has_column($pdo, 'articles', 'content_ai')) {
        $conditions[] = "(content_ai IS NULL OR TRIM(content_ai) = '')";
    }
    if (ce_table_has_column($pdo, 'articles', 'content_human')) {
        $conditions[] = "(content_human IS NULL OR TRIM(content_human) = '')";
    }

    $sql = 'DELETE FROM articles WHERE ' . implode(' AND ', $conditions);
    $pdo->prepare($sql)->execute(['slot_id' => $slotId]);
}

/** @param array{tone?: string, angle?: string, imageRatio?: string, platform?: string, rationale?: string, tags?: list<string>|string} $meta */
function ce_insert_fastlane_article(
    PDO $pdo,
    string $articleId,
    string $slotId,
    string $title,
    array $meta,
): void {
    if (!ce_table_exists($pdo, 'articles')) {
        return;
    }

    ce_ensure_headline_human_column($pdo);

    $cols = ['id', 'slot_id'];
    $vals = [':id', ':slot_id'];
    $params = [
        'id' => $articleId,
        'slot_id' => $slotId,
    ];

    if (ce_table_has_column($pdo, 'articles', 'headline')) {
        $cols[] = 'headline';
        $vals[] = ':headline';
        $params['headline'] = $title;
    }

    $rationale = trim((string) ($meta['rationale'] ?? ''));
    if ($rationale !== '' && ce_table_has_column($pdo, 'articles', 'rationale')) {
        $cols[] = 'rationale';
        $vals[] = ':rationale';
        $params['rationale'] = $rationale;
    }

    $tone = trim((string) ($meta['tone'] ?? ''));
    if ($tone !== '' && ce_table_has_column($pdo, 'articles', 'tone_final')) {
        $cols[] = 'tone_final';
        $vals[] = ':tone_final';
        $params['tone_final'] = ce_tone_to_db($tone);
    }

    $angle = trim((string) ($meta['angle'] ?? ''));
    if ($angle !== '' && ce_table_has_column($pdo, 'articles', 'angle_final')) {
        $cols[] = 'angle_final';
        $vals[] = ':angle_final';
        $params['angle_final'] = ce_angle_to_ui($angle);
    }

    ce_ensure_article_editor_columns($pdo);
    $editor = trim((string) ($meta['editor'] ?? ''));
    if ($editor !== '' && ce_table_has_column($pdo, 'articles', 'editor_final')) {
        $cols[] = 'editor_final';
        $vals[] = ':editor_final';
        $params['editor_final'] = $editor;
    }

    $platform = trim((string) ($meta['platform'] ?? ''));
    if ($platform !== '' && ce_table_has_column($pdo, 'articles', 'platform_final')) {
        $cols[] = 'platform_final';
        $vals[] = ':platform_final';
        $params['platform_final'] = ce_platform_to_db($platform);
    }

    $imageRatio = trim((string) ($meta['imageRatio'] ?? ''));
    if ($imageRatio !== '' && ce_table_has_column($pdo, 'articles', 'image_ratio_final')) {
        $cols[] = 'image_ratio_final';
        $vals[] = ':image_ratio_final';
        $params['image_ratio_final'] = ce_image_ratio_to_ui($imageRatio);
    }

    $tagsRaw = $meta['tags'] ?? [];
    $tagList = is_array($tagsRaw)
        ? $tagsRaw
        : (preg_split('/\s+/', trim((string) $tagsRaw)) ?: []);
    $hashtags = [];
    foreach ($tagList as $tag) {
        $t = trim((string) $tag);
        if ($t === '') {
            continue;
        }
        $hashtags[] = str_starts_with($t, '#') ? $t : '#' . $t;
    }
    $hashtagStr = implode(' ', $hashtags);
    if ($hashtagStr !== '' && ce_table_has_column($pdo, 'articles', 'hashtags_final')) {
        $cols[] = 'hashtags_final';
        $vals[] = ':hashtags_final';
        $params['hashtags_final'] = $hashtagStr;
    }

    if (ce_table_has_column($pdo, 'articles', 'status')) {
        $cols[] = 'status';
        $vals[] = ':status';
        $params['status'] = ce_pick_status_value($pdo, 'articles', ['draft', 'written', 'new']);
    }

    $objectiveId = trim((string) ($meta['objective'] ?? ''));
    if ($objectiveId !== '' && ce_table_has_column($pdo, 'articles', 'objective_final')) {
        $cols[] = 'objective_final';
        $vals[] = ':objective_final';
        $params['objective_final'] = $objectiveId;
    }

    $sql = 'INSERT INTO articles (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $pdo->prepare($sql)->execute($params);
}

/** @param array{tone?: string, angle?: string, imageRatio?: string, platform?: string, rationale?: string, tags?: list<string>|string, objective?: string}|null $pendingMeta */
function ce_insert_fastlane_theme(
    PDO $pdo,
    string $themeId,
    string $clientId,
    string $title,
    string $rationale = '',
    ?array $pendingMeta = null,
    ?string $objectiveId = null,
): void {
    $cols = ['id', 'client_id', 'title'];
    $vals = [':id', ':client_id', ':title'];
    $params = [
        'id' => $themeId,
        'client_id' => $clientId,
        'title' => $title,
    ];

    if ($rationale !== '' && ce_table_has_column($pdo, 'themes', 'rationale')) {
        $cols[] = 'rationale';
        $vals[] = ':rationale';
        $params['rationale'] = $rationale;
    }

    if ($pendingMeta !== null && ce_table_has_column($pdo, 'themes', 'motivation')) {
        $cols[] = 'motivation';
        $vals[] = ':motivation';
        $params['motivation'] = json_encode(['ceFastlaneMeta' => $pendingMeta], JSON_THROW_ON_ERROR);
    }

    if ($objectiveId !== null && $objectiveId !== '' && ce_table_has_column($pdo, 'themes', 'objective_id')) {
        $cols[] = 'objective_id';
        $vals[] = ':objective_id';
        $params['objective_id'] = $objectiveId;
    }

    if (ce_table_has_column($pdo, 'themes', 'status')) {
        $cols[] = 'status';
        $vals[] = ':status';
        $params['status'] = ce_pick_status_value($pdo, 'themes', ['proposed', 'draft']);
    }
    if (ce_table_has_column($pdo, 'themes', 'feedback')) {
        $cols[] = 'feedback';
        $vals[] = ':feedback';
        $params['feedback'] = 'none';
    }

    $sql = 'INSERT INTO themes (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $pdo->prepare($sql)->execute($params);
}

/** @param list<string> $urls */
function ce_insert_fastlane_research(PDO $pdo, string $themeId, array $urls, string $extraThoughts, string $slotId = ''): void
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return;
    }

    $idx = 1;
    foreach ($urls as $url) {
        ce_insert_fastlane_research_row($pdo, $themeId, ce_fastlane_research_title($url, $idx), $url, null, $slotId);
        $idx++;
    }

    if ($extraThoughts !== '') {
        ce_insert_fastlane_research_row($pdo, $themeId, 'Extra thoughts', null, $extraThoughts, $slotId);
    }
}

function ce_insert_fastlane_research_row(
    PDO $pdo,
    string $themeId,
    string $title,
    ?string $url,
    ?string $snippet,
    string $slotId = '',
): void {
    $cols = ['id', 'theme_id', 'title'];
    $vals = [':id', ':theme_id', ':title'];
    $params = [
        'id' => ce_uuid_v4(),
        'theme_id' => $themeId,
        'title' => $title,
    ];

    if ($slotId !== '' && ce_table_has_column($pdo, 'research_items', 'slot_id')) {
        $cols[] = 'slot_id';
        $vals[] = ':slot_id';
        $params['slot_id'] = $slotId;
    }

    if (ce_table_has_column($pdo, 'research_items', 'source_type')) {
        $cols[] = 'source_type';
        $vals[] = ':source_type';
        $params['source_type'] = 'manual_fastlane';
    }
    if (ce_table_has_column($pdo, 'research_items', 'url')) {
        $cols[] = 'url';
        $vals[] = ':url';
        $params['url'] = $url;
    }
    if (ce_table_has_column($pdo, 'research_items', 'snippet')) {
        $cols[] = 'snippet';
        $vals[] = ':snippet';
        $params['snippet'] = $snippet ?? '';
    }
    if (ce_table_has_column($pdo, 'research_items', 'item_rationale')) {
        $cols[] = 'item_rationale';
        $vals[] = ':item_rationale';
        $params['item_rationale'] = '';
    }
    if (ce_table_has_column($pdo, 'research_items', 'feedback')) {
        $cols[] = 'feedback';
        $vals[] = ':feedback';
        $params['feedback'] = 'none';
    }

    $sql = 'INSERT INTO research_items (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $pdo->prepare($sql)->execute($params);
}

function ce_fastlane_research_title(string $url, int $index): string
{
    $host = parse_url($url, PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
        return 'Research: ' . $host;
    }

    return 'Research URL ' . $index;
}

/**
 * Vul lege manual_fastlane-items aan door opgegeven URL's op te halen (V0 / Snel artikel).
 *
 * @return array{ok: bool, updated: int, failed: list<string>, usableCount: int}
 */
function ce_hydrate_manual_fastlane_research(PDO $pdo, string $slotId): array
{
    require_once __DIR__ . '/research-quality.php';
    require_once __DIR__ . '/sources-repository.php';
    require_once __DIR__ . '/hygiene-scrape-repository.php';

    $slotId = trim($slotId);
    if ($slotId === '' || !ce_table_exists($pdo, 'research_items')) {
        return ['ok' => false, 'updated' => 0, 'failed' => [], 'usableCount' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT id, title, url, snippet FROM research_items
         WHERE slot_id = :slot_id
           AND COALESCE(source_type, '') = 'manual_fastlane'
           AND url IS NOT NULL AND TRIM(url) <> ''"
    );
    $stmt->execute(['slot_id' => $slotId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $updated = 0;
    $failed = [];
    $update = $pdo->prepare(
        'UPDATE research_items SET title = :title, snippet = :snippet WHERE id = :id'
    );

    foreach ($rows as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        if ($id === '' || $url === '') {
            continue;
        }

        $title = trim((string) ($row['title'] ?? ''));
        $snippet = trim((string) ($row['snippet'] ?? ''));
        if (
            ce_research_title_is_usable($title)
            && ce_research_snippet_is_usable($snippet)
            && !str_starts_with(strtolower($title), 'research:')
        ) {
            continue;
        }

        $page = ce_hygiene_fetch_page_text($url);
        $text = trim((string) ($page['text'] ?? ''));
        if (!$page['ok'] || !ce_research_snippet_is_usable($text)) {
            $fetch = ce_http_fetch_url($url, 15);
            $body = is_string($fetch['body'] ?? null) ? $fetch['body'] : '';
            $meta = $body !== '' ? ce_parse_webpage_metadata($body, $url) : null;
            if ($meta !== null) {
                $title = (string) ($meta['title'] ?? $title);
                $snippet = (string) ($meta['snippet'] ?? '');
            }
            if (!ce_research_snippet_is_usable($snippet) && $body !== '') {
                $text = ce_hygiene_html_to_text($body);
                $snippet = mb_substr($text, 0, 500);
            }
        } else {
            $fetch = ce_http_fetch_url($url, 15);
            $body = is_string($fetch['body'] ?? null) ? $fetch['body'] : '';
            $meta = $body !== '' ? ce_parse_webpage_metadata($body, $url) : null;
            if ($meta !== null && ce_research_title_is_usable($meta['title'] ?? null)) {
                $title = (string) $meta['title'];
            }
            $snippet = mb_substr($text, 0, 500);
        }

        if (!ce_research_title_is_usable($title)) {
            $failed[] = $url;
            continue;
        }
        if (!ce_research_snippet_is_usable($snippet)) {
            $failed[] = $url;
            continue;
        }
        if (preg_match('/^access denied$/i', trim($title)) || preg_match('/^access denied$/i', trim(mb_substr($snippet, 0, 40)))) {
            $failed[] = $url;
            continue;
        }

        $update->execute([
            'id' => $id,
            'title' => mb_substr($title, 0, 500),
            'snippet' => mb_substr($snippet, 0, 2000),
        ]);
        ++$updated;
    }

    return [
        'ok' => true,
        'updated' => $updated,
        'failed' => $failed,
        'usableCount' => ce_count_usable_research_for_slot($pdo, $slotId),
    ];
}

/** @param list<string> $statusCandidates */
function ce_insert_fastlane_slot(
    PDO $pdo,
    string $slotId,
    string $clientId,
    string $themeId,
    string $title,
    string $extraThoughts,
    string $phaseKey,
    string $origin = 'fastlane',
    array $statusCandidates = ['proposed', 'planned', 'open', 'draft'],
    ?string $objectiveId = null,
    ?string $rhythmRole = null,
): void {
    $cols = ['id', 'client_id', 'theme_id', 'theme'];
    $vals = [':id', ':client_id', ':theme_id', ':theme'];
    $params = [
        'id' => $slotId,
        'client_id' => $clientId,
        'theme_id' => $themeId,
        'theme' => $title,
    ];

    if (ce_table_has_column($pdo, 'content_slots', 'status')) {
        $cols[] = 'status';
        $vals[] = ':status';
        $params['status'] = ce_pick_status_value($pdo, 'content_slots', $statusCandidates);
    }
    if (ce_table_has_column($pdo, 'content_slots', 'phase_key')) {
        $cols[] = 'phase_key';
        $vals[] = ':phase_key';
        $params['phase_key'] = $phaseKey;
    }
    if (ce_table_has_column($pdo, 'content_slots', 'goal')) {
        $cols[] = 'goal';
        $vals[] = ':goal';
        $params['goal'] = $title;
    }
    if (ce_table_has_column($pdo, 'content_slots', 'input_human')) {
        $cols[] = 'input_human';
        $vals[] = ':input_human';
        $params['input_human'] = $extraThoughts;
    }
    if (ce_table_has_column($pdo, 'content_slots', 'origin')) {
        $cols[] = 'origin';
        $vals[] = ':origin';
        $params['origin'] = $origin !== '' ? $origin : 'fastlane';
    }
    if ($objectiveId !== null && $objectiveId !== '' && ce_table_has_column($pdo, 'content_slots', 'objective_id')) {
        $cols[] = 'objective_id';
        $vals[] = ':objective_id';
        $params['objective_id'] = $objectiveId;
    }
    if ($rhythmRole !== null && $rhythmRole !== '' && ce_table_has_column($pdo, 'content_slots', 'rhythm_role')) {
        $cols[] = 'rhythm_role';
        $vals[] = ':rhythm_role';
        $params['rhythm_role'] = $rhythmRole;
    }

    $sql = 'INSERT INTO content_slots (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $pdo->prepare($sql)->execute($params);
}

function ce_mark_theme_converted(PDO $pdo, string $themeId): void
{
    if (!ce_table_has_column($pdo, 'themes', 'status')) {
        return;
    }

    $status = ce_pick_status_value($pdo, 'themes', ['converted_to_slot', 'submitted', 'proposed']);
    $pdo->prepare('UPDATE themes SET status = :status WHERE id = :id')
        ->execute(['status' => $status, 'id' => $themeId]);
}

function ce_slot_eligible_for_write_after_research(PDO $pdo, array $slot): bool
{
    if (ce_is_fastlane_research_slot($pdo, $slot)) {
        return true;
    }

    return trim((string) ($slot['theme_id'] ?? '')) !== '';
}

/** @return array<string, mixed> */
function ce_prepare_fastlane_write_after_research(PDO $pdo, string $slotId, ?string $clientIdOverride = null): array
{
    if (!ce_table_exists($pdo, 'content_slots')) {
        return ['articleWriteTriggered' => false, 'reason' => 'no_slots_table'];
    }

    require_once __DIR__ . '/theme-repository.php';
    ce_backfill_research_slot_ids($pdo, $slotId);

    $slot = ce_fetch_fastlane_slot_row($pdo, $slotId);
    if (!$slot) {
        return ['articleWriteTriggered' => false, 'reason' => 'slot_not_found'];
    }

    if (!ce_slot_eligible_for_write_after_research($pdo, $slot)) {
        return ['articleWriteTriggered' => false, 'reason' => 'not_eligible'];
    }

    require_once __DIR__ . '/editor-questions.php';
    $clientId = trim((string) ($clientIdOverride ?? $slot['client_id'] ?? ''));
    $scopedClientId = $clientId !== '' ? $clientId : null;
    $existingRow = ce_fetch_article_by_slot($pdo, $slotId, $scopedClientId);
    if ($existingRow !== null) {
        $existingContent = trim((string) ($existingRow['content_human'] ?? ''));
        if ($existingContent === '') {
            $existingContent = trim((string) ($existingRow['content_ai'] ?? ''));
        }
        if (ce_article_has_real_body($existingContent)) {
            return ['articleWriteTriggered' => false, 'reason' => 'article_exists'];
        }
    } else {
        ce_clear_empty_slot_articles($pdo, $slotId);
    }

    $themeId = trim((string) ($slot['theme_id'] ?? ''));
    $title = trim((string) ($slot['theme'] ?? ''));
    $meta = ce_fetch_fastlane_pending_meta($pdo, $themeId) ?? [];

    if ($title === '' && ce_table_exists($pdo, 'themes') && $themeId !== '') {
        $t = $pdo->prepare('SELECT title, rationale FROM themes WHERE id = :id LIMIT 1');
        $t->execute(['id' => $themeId]);
        $themeRow = $t->fetch();
        if ($themeRow) {
            $title = trim((string) ($themeRow['title'] ?? ''));
            if (($meta['rationale'] ?? '') === '' && trim((string) ($themeRow['rationale'] ?? '')) !== '') {
                $meta['rationale'] = (string) $themeRow['rationale'];
            }
        }
    }

    if ($title === '') {
        return ['articleWriteTriggered' => false, 'reason' => 'missing_title'];
    }

    require_once __DIR__ . '/research-quality.php';
    $researchReady = ce_research_slot_ready_for_write($pdo, $slotId);
    if (($researchReady['ok'] ?? false) !== true) {
        return [
            'articleWriteTriggered' => false,
            'reason' => 'insufficient_research',
            'message' => (string) ($researchReady['message'] ?? ce_research_insufficient_message()),
            'usableResearchCount' => (int) ($researchReady['usableCount'] ?? 0),
        ];
    }

    if ($existingRow === null) {
        ce_ensure_article_stub_for_slot($pdo, $slotId);
    }

    $ce03Meta = ce_ce03_webhook_meta_from_slot($pdo, $slotId);
    $webhook = ce_trigger_ce03_write($slotId, true, $ce03Meta);

    if (ce_table_has_column($pdo, 'articles', 'updated_at')) {
        $pdo->prepare('UPDATE articles SET updated_at = NOW() WHERE slot_id = :slot_id')
            ->execute(['slot_id' => $slotId]);
    }

    return [
        'articleWriteTriggered' => true,
        'articleId' => null,
        'webhookTriggered' => ($webhook['webhookTriggered'] ?? false) === true,
        'webhookResult' => $webhook['response'] ?? null,
    ];
}

/**
 * Lege artikel-stub voor research-first slots (afbeelding/meta vóór CE-03).
 *
 * @return array<string, mixed>
 */
function ce_ensure_article_stub_for_slot(PDO $pdo, string $slotId): array
{
    $slot = ce_fetch_fastlane_slot_row($pdo, $slotId);
    if ($slot === null) {
        throw new RuntimeException('Slot niet gevonden');
    }

    $clientId = trim((string) ($slot['client_id'] ?? ''));
    $existing = ce_fetch_article_by_slot($pdo, $slotId, $clientId !== '' ? $clientId : null);
    if ($existing !== null) {
        return $existing;
    }

    $themeId = trim((string) ($slot['theme_id'] ?? ''));
    $title = trim((string) ($slot['theme'] ?? ''));
    $meta = $themeId !== '' ? (ce_fetch_fastlane_pending_meta($pdo, $themeId) ?? []) : [];

    if ($title === '' && ce_table_exists($pdo, 'themes') && $themeId !== '') {
        $t = $pdo->prepare('SELECT title, rationale FROM themes WHERE id = :id LIMIT 1');
        $t->execute(['id' => $themeId]);
        $themeRow = $t->fetch();
        if ($themeRow) {
            $title = trim((string) ($themeRow['title'] ?? ''));
            if (($meta['rationale'] ?? '') === '' && trim((string) ($themeRow['rationale'] ?? '')) !== '') {
                $meta['rationale'] = (string) $themeRow['rationale'];
            }
        }
    }

    if ($title === '') {
        throw new RuntimeException('Slot heeft geen titel voor artikel-stub');
    }

    $articleId = ce_uuid_v4();
    ce_insert_fastlane_article($pdo, $articleId, $slotId, $title, $meta);

    $row = ce_fetch_article_by_slot($pdo, $slotId, $clientId !== '' ? $clientId : null);
    if ($row === null) {
        throw new RuntimeException('Artikel stub kon niet worden aangemaakt');
    }

    return $row;
}

/** @return array<string, mixed> */
function ce_trigger_ce03_write(string $slotId, bool $fireAndForget = true, ?array $meta = null): array
{
    $webhook = trim((string) (getenv('N8N_CE03_WEBHOOK_URL') ?: ''));
    $local = dirname(__DIR__) . '/includes/ce.local.php';
    if ($webhook === '' && is_readable($local)) {
        $config = require $local;
        if (is_array($config)) {
            $webhook = trim((string) ($config['ce03_webhook_url'] ?? ''));
        }
    }

    if ($webhook === '') {
        return ['ok' => false, 'error' => 'N8N_CE03_WEBHOOK_URL is niet geconfigureerd'];
    }

    $payload = ['slot_id' => $slotId];
    if (is_array($meta)) {
        foreach ([
            'write_directive',
            'tone_final',
            'angle_final',
            'platform_final',
            'platform_intended',
            'platform_label',
            'platform_form_guidelines',
            'grenzen_block',
            'nl_writing_style_block',
            'image_ratio_final',
            'objective_id',
            'objective_label',
            'objective_description',
            'objective_writing_style',
            'objectives_catalog',
        ] as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $value = trim((string) $meta[$key]);
            if ($value !== '') {
                $payload[$key] = $value;
            }
        }
    }

    require_once __DIR__ . '/publish-repository.php';
    require_once __DIR__ . '/publish-settings-repository.php';
    require_once __DIR__ . '/content-reset.php';
    require_once __DIR__ . '/review-repository.php';

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'timeout' => $fireAndForget ? 8 : 25,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($webhook, false, $ctx);
    $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $started = is_array($decoded) && (
        ($decoded['ok'] ?? false) === true
        || ($decoded['message'] ?? '') === 'Workflow was started'
    );

    return [
        'ok' => $fireAndForget ? ($raw !== false || $started) : $started,
        'webhookTriggered' => true,
        'response' => is_array($decoded) ? $decoded : ['raw' => $raw],
    ];
}

/**
 * Stub met titel maar zonder CE-03-body — toon "schrijven"-status en hervat indien nodig.
 *
 * @return array{writingPending: bool, resume?: array<string, mixed>}|null
 */
function ce_maybe_resume_article_write(PDO $pdo, string $slotId, array $articleRow): ?array
{
    require_once __DIR__ . '/editor-questions.php';
    require_once __DIR__ . '/slot-repository.php';
    require_once __DIR__ . '/research-quality.php';

    if (ce_article_has_real_body(ce_article_body_text($articleRow))) {
        return null;
    }

    $slot = ce_fetch_fastlane_slot_row($pdo, $slotId);
    if ($slot === null || !ce_slot_eligible_for_write_after_research($pdo, $slot)) {
        return null;
    }

    $researchReady = ce_research_slot_ready_for_write($pdo, $slotId);
    if (($researchReady['ok'] ?? false) !== true) {
        return null;
    }

    $slotStatus = ce_status_to_ui((string) ($slot['status'] ?? ''));
    if (!in_array($slotStatus, ['research_done', 'draft', 'themed'], true)) {
        return null;
    }

    $updatedAt = strtotime((string) ($articleRow['updated_at'] ?? ''));
    if ($updatedAt === false) {
        $updatedAt = strtotime((string) ($articleRow['created_at'] ?? ''));
    }
    if ($updatedAt === false) {
        $updatedAt = strtotime((string) ($slot['updated_at'] ?? ''));
    }
    $ageSeconds = $updatedAt !== false ? max(0, time() - $updatedAt) : 0;

    if ($ageSeconds < 90) {
        return [
            'writingPending' => true,
            'resume' => ['debounced' => true, 'ageSeconds' => $ageSeconds],
        ];
    }

    return [
        'writingPending' => true,
        'resume' => ce_prepare_fastlane_write_after_research($pdo, $slotId),
    ];
}
