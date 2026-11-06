<?php

declare(strict_types=1);

/** @return list<string> */
function ce_self_review_geen_bron_bron_types(): array
{
    return ['merkarchitectuur', 'bewijsveld', 'url', 'document'];
}

function ce_self_review_klant_bevestiging_valid(mixed $item): bool
{
    if (!is_array($item)) {
        return false;
    }
    if (($item['bron'] ?? '') !== 'geen_bron') {
        return false;
    }
    $conf = $item['klant_bevestiging'] ?? $item['klantBevestiging'] ?? null;
    if (!is_array($conf)) {
        return false;
    }
    $type = strtolower(trim((string) ($conf['type'] ?? $conf['bron_type'] ?? '')));
    $waarde = trim((string) ($conf['waarde'] ?? $conf['bron_waarde'] ?? ''));
    if ($waarde === '') {
        return false;
    }

    return in_array($type, ce_self_review_geen_bron_bron_types(), true);
}

/** @param array<string, mixed> $parsed */
function ce_self_review_unconfirmed_geen_bron_count(array $parsed): int
{
    $n = 0;
    foreach ($parsed['merkBeweringen'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['bron'] ?? '') === 'geen_bron' && !ce_self_review_klant_bevestiging_valid($item)) {
            $n++;
        }
    }

    return $n;
}

/**
 * @return array{ok: bool, error?: string, selfReview?: array<string, mixed>}
 */
function ce_self_review_confirm_geen_bron(
    PDO $pdo,
    string $slotId,
    string $citaat,
    string $bronType,
    string $bronWaarde,
): array {
    require_once __DIR__ . '/article-repository.php';
    require_once __DIR__ . '/hba-repository.php';
    require_once __DIR__ . '/mappers.php';

    $row = ce_fetch_article_by_slot($pdo, $slotId);
    if ($row === null) {
        return ['ok' => false, 'error' => 'article_not_found'];
    }

    $raw = $row['self_review'] ?? null;
    if (!is_string($raw) || trim($raw) === '') {
        return ['ok' => false, 'error' => 'no_self_review'];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'invalid_self_review'];
    }

    $list = $decoded['merk_beweringen'] ?? $decoded['merkBeweringen'] ?? null;
    if (!is_array($list)) {
        return ['ok' => false, 'error' => 'no_merk_beweringen'];
    }

    $citaatNorm = ce_hba_normalize_text_for_citation_match($citaat);
    $found = false;
    foreach ($list as &$item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['bron'] ?? '') !== 'geen_bron') {
            continue;
        }
        $itemCitaat = trim((string) ($item['citaat'] ?? ''));
        if ($itemCitaat === '' || ce_hba_normalize_text_for_citation_match($itemCitaat) !== $citaatNorm) {
            continue;
        }
        $type = strtolower(trim($bronType));
        if (!in_array($type, ce_self_review_geen_bron_bron_types(), true)) {
            return ['ok' => false, 'error' => 'invalid_bron_type'];
        }
        $waarde = trim($bronWaarde);
        if ($waarde === '') {
            return ['ok' => false, 'error' => 'empty_bron_waarde'];
        }
        $item['klant_bevestiging'] = [
            'type' => $type,
            'waarde' => $waarde,
            'bevestigd_op' => gmdate('c'),
        ];
        $found = true;
        break;
    }
    unset($item);

    if (!$found) {
        return ['ok' => false, 'error' => 'geen_bron_citaat_not_found'];
    }

    $decoded['merk_beweringen'] = $list;
    unset($decoded['merkBeweringen']);
    $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return ['ok' => false, 'error' => 'encode_failed'];
    }

    $articleId = (string) ($row['id'] ?? '');
    $stmt = $pdo->prepare('UPDATE articles SET self_review = :sr WHERE id = :id');
    $stmt->execute(['sr' => $encoded, 'id' => $articleId]);

    ce_log_event($pdo, 'info', 'article', 'geen_bron_klant_bevestigd', 'Klant bevestigde bron voor gemarkeerde zin', [
        'slotId' => $slotId,
        'articleId' => $articleId,
        'citaat' => mb_substr($citaat, 0, 200),
        'bron_type' => $type,
        'bron_waarde' => mb_substr($waarde, 0, 500),
    ]);

    return ['ok' => true, 'selfReview' => ce_parse_self_review($encoded)];
}
