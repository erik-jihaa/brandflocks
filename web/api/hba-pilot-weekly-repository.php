<?php

declare(strict_types=1);

require_once __DIR__ . '/hba-prompt.php';
require_once __DIR__ . '/hba-testmerken-repository.php';
require_once __DIR__ . '/event-log-repository.php';
require_once __DIR__ . '/mappers.php';

/**
 * ISO-week (2026-W43) of huidige week in Europe/Amsterdam.
 *
 * @return array{week: string, from: string, to: string}
 */
function ce_pilot_week_bounds(?string $weekParam): array
{
    $tz = new DateTimeZone('Europe/Amsterdam');
    if ($weekParam !== null && $weekParam !== '' && preg_match('/^(\d{4})-W(\d{2})$/', $weekParam, $m)) {
        $dt = new DateTimeImmutable('now', $tz);
        $dt = $dt->setISODate((int) $m[1], (int) $m[2])->setTime(0, 0, 0);
    } else {
        $dt = (new DateTimeImmutable('now', $tz))->modify('monday this week')->setTime(0, 0, 0);
    }
    $from = $dt;
    $to = $dt->modify('+7 days');
    $weekLabel = $from->format('o') . '-W' . $from->format('W');

    return [
        'week' => $weekLabel,
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $to->format('Y-m-d H:i:s'),
    ];
}

/** @return array<string, mixed> */
/** @return list<string> */
function ce_hba_pilot_article_title_sql_columns(PDO $pdo): array
{
    $cols = ['a.headline'];
    foreach (['headline_human', 'headline_final'] as $column) {
        if (ce_table_has_column($pdo, 'articles', $column)) {
            $cols[] = 'a.' . $column;
        }
    }

    return $cols;
}

/** @return array<string, mixed> */
function ce_hba_pilot_decode_raw(mixed $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    return is_array($raw) ? $raw : [];
}

function ce_hba_pilot_score_uitleg(array $raw, string $hoofdoordeel): array
{
    $citaten = is_array($raw['flag_citaten'] ?? null) ? $raw['flag_citaten'] : [];
    $citaat = '';
    $uitleg = trim((string) ($raw['diagnose'] ?? ''));

    if ($hoofdoordeel === 'tegenkracht') {
        $citaat = trim((string) ($citaten['foe_vervaging'] ?? ''));
        $flagUitleg = trim((string) ($raw['foe_vervaging_uitleg'] ?? ''));
        if ($flagUitleg !== '') {
            $uitleg = $flagUitleg;
        }
    } elseif ($hoofdoordeel === 'regelbreuk') {
        $citaat = trim((string) ($citaten['constraint_breach'] ?? ''));
        $flagUitleg = trim((string) ($raw['constraint_breach_uitleg'] ?? ''));
        if ($flagUitleg !== '') {
            $uitleg = $flagUitleg;
        }
    }

    return ['citaat' => $citaat, 'uitleg' => $uitleg];
}

/**
 * @param list<string>|null $clientNames null = drie HBA-testmerken
 * @return array<string, mixed>
 */
function ce_hba_pilot_weekly_report_payload(PDO $pdo, ?string $weekParam = null, ?array $clientNames = null): array
{
    ce_ensure_hba_schema($pdo);
    ce_ensure_hba_v2_schema($pdo);
    ce_ensure_event_log_schema($pdo);

    $bounds = ce_pilot_week_bounds($weekParam);
    $names = $clientNames ?? ce_hba_testmerk_client_names();

    $clients = [];
    foreach ($names as $name) {
        $stmt = $pdo->prepare('SELECT id, name FROM clients WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $clients[(string) $row['id']] = (string) $row['name'];
        }
    }

    $reports = [];
    foreach ($clients as $clientId => $clientName) {
        $hoofdoordeelCounts = array_fill_keys(CE_HBA_HOOFDOORDEEL_VALUES, 0);
        $kritiek = [];
        $geenBron = [];
        $ce04Verwijderd = [];
        $negeerPerHoofdoordeel = array_fill_keys(CE_HBA_HOOFDOORDEEL_VALUES, 0);
        $negeerOverig = 0;
        $negeerKritiek = 0;

        if (ce_table_exists($pdo, 'hba_scores')) {
            $titleCols = implode(', ', ce_hba_pilot_article_title_sql_columns($pdo));
            $scoreStmt = $pdo->prepare(
                "SELECT hs.raw_llm_response, hs.scored_at, hs.diagnose,
                        {$titleCols}, a.id AS article_id, cs.id AS slot_id
                 FROM hba_scores hs
                 INNER JOIN articles a ON a.id = hs.post_id
                 INNER JOIN content_slots cs ON cs.id = a.slot_id
                 WHERE cs.client_id = :client_id
                   AND hs.scored_at >= :from_dt
                   AND hs.scored_at < :to_dt
                 ORDER BY hs.scored_at ASC"
            );
            $scoreStmt->execute([
                'client_id' => $clientId,
                'from_dt' => $bounds['from'],
                'to_dt' => $bounds['to'],
            ]);
            while ($row = $scoreStmt->fetch(PDO::FETCH_ASSOC)) {
                $raw = ce_hba_pilot_decode_raw($row['raw_llm_response'] ?? null);
                $meta = ce_hba_hoofdoordeel_from_raw($raw);
                $hoofd = trim((string) ($meta['hoofdoordeel'] ?? ''));
                if ($hoofd === '' || !isset($hoofdoordeelCounts[$hoofd])) {
                    continue;
                }
                $hoofdoordeelCounts[$hoofd]++;
                if (!in_array($hoofd, ['tegenkracht', 'regelbreuk'], true)) {
                    continue;
                }
                $u = ce_hba_pilot_score_uitleg($raw, $hoofd);
                $title = ce_article_headline($row);
                if ($title === '') {
                    $title = '(zonder titel)';
                }
                $kritiek[] = [
                    'hoofdoordeel' => $hoofd,
                    'postTitel' => $title,
                    'slotId' => (string) ($row['slot_id'] ?? ''),
                    'articleId' => (string) ($row['article_id'] ?? ''),
                    'scoredAt' => (string) ($row['scored_at'] ?? ''),
                    'citaat' => $u['citaat'],
                    'uitleg' => $u['uitleg'],
                ];
            }
        }

        if (ce_table_exists($pdo, 'event_log')) {
            $evStmt = $pdo->prepare(
                "SELECT message, context, created_at
                 FROM event_log
                 WHERE client_id = :client_id
                   AND event = 'geen_bron_klant_bevestigd'
                   AND created_at >= :from_dt
                   AND created_at < :to_dt
                 ORDER BY created_at ASC",
            );
            $evStmt->execute([
                'client_id' => $clientId,
                'from_dt' => $bounds['from'],
                'to_dt' => $bounds['to'],
            ]);
            while ($ev = $evStmt->fetch(PDO::FETCH_ASSOC)) {
                $ctx = $ev['context'] ?? '';
                if (is_string($ctx)) {
                    $decoded = json_decode($ctx, true);
                    $ctx = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($ctx)) {
                    $ctx = [];
                }
                $geenBron[] = [
                    'at' => (string) ($ev['created_at'] ?? ''),
                    'citaat' => (string) ($ctx['citaat'] ?? ''),
                    'bronType' => (string) ($ctx['bron_type'] ?? ''),
                    'bronWaarde' => (string) ($ctx['bron_waarde'] ?? ''),
                    'slotId' => (string) ($ctx['slotId'] ?? ''),
                ];
            }

            $negeerStmt = $pdo->prepare(
                "SELECT context, created_at
                 FROM event_log
                 WHERE client_id = :client_id
                   AND event = 'hba_hint_genegeerd'
                   AND created_at >= :from_dt
                   AND created_at < :to_dt
                 ORDER BY created_at ASC",
            );
            $negeerStmt->execute([
                'client_id' => $clientId,
                'from_dt' => $bounds['from'],
                'to_dt' => $bounds['to'],
            ]);
            while ($ev = $negeerStmt->fetch(PDO::FETCH_ASSOC)) {
                $ctx = $ev['context'] ?? '';
                if (is_string($ctx)) {
                    $decoded = json_decode($ctx, true);
                    $ctx = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($ctx)) {
                    $ctx = [];
                }
                $hoofd = trim((string) ($ctx['hoofdoordeel'] ?? ''));
                if ($hoofd !== '' && isset($negeerPerHoofdoordeel[$hoofd])) {
                    $negeerPerHoofdoordeel[$hoofd]++;
                } else {
                    $negeerOverig++;
                }
                if (!empty($ctx['kritiek'])) {
                    $negeerKritiek++;
                }
            }
        }

        if (ce_table_exists($pdo, 'hba_article_hints')
            && ce_table_has_column($pdo, 'hba_article_hints', 'genegeerd')) {
            $hintTitleCols = implode(', ', ce_hba_pilot_article_title_sql_columns($pdo));
            $hintStmt = $pdo->prepare(
                "SELECT h.aanwijzing, h.regel_overtreden, h.judged_at, {$hintTitleCols}, cs.id AS slot_id
                 FROM hba_article_hints h
                 INNER JOIN articles a ON a.id = h.article_id
                 INNER JOIN content_slots cs ON cs.id = a.slot_id
                 WHERE cs.client_id = :client_id
                   AND h.genegeerd = 1
                   AND h.regel_overtreden IS NOT NULL
                   AND LENGTH(TRIM(h.regel_overtreden)) > 0
                   AND h.judged_at >= :from_dt
                   AND h.judged_at < :to_dt
                 ORDER BY h.judged_at ASC"
            );
            $hintStmt->execute([
                'client_id' => $clientId,
                'from_dt' => $bounds['from'],
                'to_dt' => $bounds['to'],
            ]);
            while ($hint = $hintStmt->fetch(PDO::FETCH_ASSOC)) {
                $title = ce_article_headline($hint);
                $ce04Verwijderd[] = [
                    'at' => (string) ($hint['judged_at'] ?? ''),
                    'postTitel' => $title !== '' ? $title : '(zonder titel)',
                    'slotId' => (string) ($hint['slot_id'] ?? ''),
                    'regel' => trim((string) ($hint['regel_overtreden'] ?? '')),
                    'aanwijzing' => trim((string) ($hint['aanwijzing'] ?? '')),
                ];
            }
        }

        $reports[] = [
            'clientId' => $clientId,
            'clientName' => $clientName,
            'hoofdoordeelCounts' => $hoofdoordeelCounts,
            'kritiekeScores' => $kritiek,
            'geenBronBevestigd' => $geenBron,
            'ce04OpmerkingVerwijderd' => $ce04Verwijderd,
            'negeerPerHoofdoordeel' => $negeerPerHoofdoordeel,
            'negeerOverig' => $negeerOverig,
            'negeerKritiek' => $negeerKritiek,
        ];
    }

    return [
        'ok' => true,
        'week' => $bounds['week'],
        'from' => $bounds['from'],
        'to' => $bounds['to'],
        'timezone' => 'Europe/Amsterdam',
        'clients' => $reports,
    ];
}

/** @param array<string, mixed> $payload */
function ce_hba_pilot_weekly_report_markdown(array $payload): string
{
    $lines = [];
    $lines[] = '# Pilot — weekoverzicht HBA';
    $lines[] = '';
    $lines[] = 'Week **' . ($payload['week'] ?? '') . '** (' . ($payload['from'] ?? '') . ' – ' . ($payload['to'] ?? '') . ', ' . ($payload['timezone'] ?? '') . ')';
    $lines[] = '';

    foreach ($payload['clients'] ?? [] as $client) {
        $lines[] = '## ' . ($client['clientName'] ?? 'Klant');
        $lines[] = '';
        $lines[] = '### Scores per hoofdoordeel';
        $lines[] = '';
        $lines[] = '| Hoofdoordeel | Aantal |';
        $lines[] = '| --- | ---: |';
        foreach ($client['hoofdoordeelCounts'] ?? [] as $key => $count) {
            if ((int) $count === 0) {
                continue;
            }
            $lines[] = '| ' . $key . ' | ' . (int) $count . ' |';
        }
        $lines[] = '';

        $kritiek = $client['kritiekeScores'] ?? [];
        $lines[] = '### Positiebreuk / regelbreuk';
        $lines[] = '';
        if ($kritiek === []) {
            $lines[] = '_Geen scores in deze week._';
        } else {
            foreach ($kritiek as $item) {
                $lines[] = '- **' . ($item['postTitel'] ?? '') . '** (`' . ($item['hoofdoordeel'] ?? '') . '`, ' . ($item['scoredAt'] ?? '') . ')';
                if (($item['citaat'] ?? '') !== '') {
                    $lines[] = '  - Citaat: ' . $item['citaat'];
                }
                if (($item['uitleg'] ?? '') !== '') {
                    $lines[] = '  - Uitleg: ' . $item['uitleg'];
                }
            }
        }
        $lines[] = '';

        $geenBron = $client['geenBronBevestigd'] ?? [];
        $lines[] = '### Geen-bron overschreven door klant';
        $lines[] = '';
        if ($geenBron === []) {
            $lines[] = '_Geen._';
        } else {
            foreach ($geenBron as $item) {
                $lines[] = '- ' . ($item['at'] ?? '') . ' — ' . ($item['citaat'] ?? '');
                $lines[] = '  - Bron: **' . ($item['bronType'] ?? '') . '** — ' . ($item['bronWaarde'] ?? '');
            }
        }
        $lines[] = '';

        $negeer = $client['negeerPerHoofdoordeel'] ?? [];
        $lines[] = '### Negeer op positioneringshints (deze week)';
        $lines[] = '';
        $lines[] = 'Kritiek (tegenkracht/regelbreuk, met pilotmail): **' . (int) ($client['negeerKritiek'] ?? 0) . '**';
        $lines[] = '';
        $lines[] = '| Hoofdoordeel post | Aantal Negeer |';
        $lines[] = '| --- | ---: |';
        $negeerAny = false;
        foreach ($negeer as $key => $count) {
            if ((int) $count === 0) {
                continue;
            }
            $negeerAny = true;
            $lines[] = '| ' . $key . ' | ' . (int) $count . ' |';
        }
        if ((int) ($client['negeerOverig'] ?? 0) > 0) {
            $negeerAny = true;
            $lines[] = '| (onbekend/leeg) | ' . (int) $client['negeerOverig'] . ' |';
        }
        if (!$negeerAny) {
            $lines[] = '| — | 0 |';
        }
        $lines[] = '';

        $ce04 = $client['ce04OpmerkingVerwijderd'] ?? [];
        $lines[] = '### CE-04-opmerking genegeerd/verwijderd';
        $lines[] = '';
        if ($ce04 === []) {
            $lines[] = '_Geen._';
        } else {
            foreach ($ce04 as $item) {
                $lines[] = '- **' . ($item['postTitel'] ?? '') . '** (' . ($item['at'] ?? '') . ')';
                if (($item['regel'] ?? '') !== '') {
                    $lines[] = '  - Regel: ' . $item['regel'];
                }
                if (($item['aanwijzing'] ?? '') !== '') {
                    $lines[] = '  - Opmerking: ' . mb_substr((string) $item['aanwijzing'], 0, 400);
                }
            }
        }
        $lines[] = '';
    }

    return implode("\n", $lines) . "\n";
}
