<?php

declare(strict_types=1);

/**
 * R0 — CE-04 opnieuw voor HBA-testpost 3 (*Breekt de regel*) per testmerk.
 * GET met X-CE-Reset-Token. Optioneel ?wait=45 om daarna feedback te lezen.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/content-reset.php';
require_once __DIR__ . '/article-repository.php';
require_once __DIR__ . '/feedback-repository.php';
require_once __DIR__ . '/hba-testmerken-repository.php';
require_once __DIR__ . '/hba-hint-verificatie.php';
require_once __DIR__ . '/hba-repository.php';

if (!ce_reset_token_valid()) {
    ce_json_error('Unauthorized', 401);
}

$pdo = ce_db();
$wait = max(0, min(120, (int) ($_GET['wait'] ?? 0)));
$pollOnly = (string) ($_GET['pollOnly'] ?? '') === '1';
$dryRun = (string) ($_GET['dry'] ?? '') === '1';
if ($wait > 0 && !$pollOnly) {
    @set_time_limit(max(300, $wait + 180));
}

$targets = ce_hba_testmerken_collect_slot_targets($pdo);
$post3 = array_values(array_filter(
    $targets,
    static fn (array $t): bool => (int) ($t['scoreIndex'] ?? 0) === 3,
));

if ($post3 === []) {
    ce_json_error('Geen HBA-testpost 3 gevonden (import testposts?)', 404);
}

$entries = [];
foreach ($post3 as $t) {
    $articleId = trim((string) ($t['articleId'] ?? ''));
    if ($articleId === '') {
        $entries[] = [
            'merk' => $t['merk'] ?? '',
            'ok' => false,
            'error' => 'Geen article_id',
        ];
        continue;
    }

    $slotStmt = $pdo->prepare('SELECT slot_id FROM articles WHERE id = :id LIMIT 1');
    $slotStmt->execute(['id' => $articleId]);
    $slotId = trim((string) ($slotStmt->fetchColumn() ?: ''));
    if ($slotId === '') {
        $entries[] = [
            'merk' => $t['merk'] ?? '',
            'ok' => false,
            'error' => 'Geen slot_id',
        ];
        continue;
    }

    if ($dryRun) {
        $entries[] = [
            'merk' => $t['merk'] ?? '',
            'label' => $t['label'] ?? '',
            'slotId' => $slotId,
            'articleId' => $articleId,
            'dryRun' => true,
        ];
        continue;
    }

    $entries[] = [
        'merk' => $t['merk'] ?? '',
        'label' => $t['label'] ?? '',
        'slotId' => $slotId,
        'articleId' => $articleId,
    ];
}

if (!$dryRun && !$pollOnly && $entries !== []) {
    foreach ($entries as $i => $entry) {
        if (isset($entry['error'])) {
            continue;
        }
        $entries[$i]['ce04'] = ce_trigger_ce04_webhook((string) $entry['slotId']);
    }
}

$pollable = [];
if ($wait > 0 || $pollOnly) {
    if ($pollOnly) {
        $wait = max($wait, 1);
    }
    foreach ($entries as $i => $entry) {
        if (isset($entry['error'])) {
            continue;
        }
        if (!$pollOnly && empty($entry['ce04']['ok'])) {
            continue;
        }
        $pollable[] = $i;
    }
    if ($pollable !== []) {
        @set_time_limit(max(300, $wait + 180));
        $deadline = time() + $wait;
        while ($pollable !== [] && time() <= $deadline) {
            foreach ($pollable as $k => $idx) {
                $slotId = (string) $entries[$idx]['slotId'];
                $feedback = ce_fetch_article_feedback($pdo, $slotId);
                $hasRegelRow = false;
                foreach ($feedback as $fb) {
                    $comment = (string) ($fb['comment'] ?? '');
                    if (ce_ce04_comment_is_regelbreuk($comment)) {
                        $hasRegelRow = true;
                        break;
                    }
                }
                if (!$hasRegelRow) {
                    continue;
                }
                unset($pollable[$k]);
                $regelHits = [];
                $parseErrors = [];
                foreach ($feedback as $fb) {
                    $comment = (string) ($fb['comment'] ?? '');
                    if (ce_ce04_comment_is_regelbreuk($comment)) {
                        $regelHits[] = $fb;
                    }
                    if (str_contains($comment, '[CE-04] Review mislukt') || str_contains($comment, 'tool-output niet gelezen')) {
                        $parseErrors[] = $fb;
                    }
                }
                $clientStmt = $pdo->prepare(
                    'SELECT s.client_id FROM articles a
                     JOIN content_slots s ON s.id = a.slot_id
                     WHERE a.id = :id LIMIT 1'
                );
                $clientStmt->execute(['id' => (string) $entries[$idx]['articleId']]);
                $clientId = trim((string) ($clientStmt->fetchColumn() ?: ''));
                $jullieRegelsVeld = '';
                if ($clientId !== '') {
                    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
                    $jullieRegelsVeld = trim((string) ($narrative['geloofwaardigheidstest'] ?? ''));
                }
                $entries[$idx]['feedbackCount'] = count($feedback);
                $entries[$idx]['regelFeedback'] = $regelHits;
                $regelOk = false;
                if ($regelHits !== [] && $jullieRegelsVeld !== '') {
                    $first = $regelHits[0];
                    $regelOk = ce_hba_ce04_regel_feedback_ok(
                        (string) ($first['comment'] ?? ''),
                        (string) ($first['excerpt'] ?? ''),
                        $jullieRegelsVeld,
                    );
                }
                $entries[$idx]['regelFeedbackOk'] = $regelOk;
                $entries[$idx]['ce04ParseErrors'] = $parseErrors;
                $entries[$idx]['feedbackSample'] = array_slice($feedback, 0, 3);
            }
            $pollable = array_values($pollable);
            if ($pollable !== [] && time() <= $deadline) {
                sleep(min(5, max(1, $deadline - time())));
            }
        }
    }
}

$results = $entries;

ce_json_response([
    'ok' => true,
    'promptVersion' => defined('CE_HBA_PROMPT_VERSION') ? CE_HBA_PROMPT_VERSION : null,
    'waitSeconds' => $wait,
    'results' => $results,
]);
