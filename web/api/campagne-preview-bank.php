<?php

declare(strict_types=1);

const CE_CAMPAGNE_BANK_VRAAG_MAX = 3;
const CE_CAMPAGNE_BANK_FEIT_MAX = 3;
const CE_CAMPAGNE_ANKER_PREFIX_VRAAG = 'Anker (vraag):';
const CE_CAMPAGNE_ANKER_PREFIX_FEIT = 'Anker (feit):';

/** @var list<string> */
const CE_CAMPAGNE_MERK_ONDERDELEN = [
    'wat_je_doet',
    'waarom',
    'waar_je_tegen_bent',
    'hoe_je_werkt',
    'waar_je_nee_tegen_zegt',
];

/** Vragen: oriënteren / vergelijken / kiezen / help. */
const CE_CAMPAGNE_VRAAG_FASES = ['aankondiging', 'vraag', 'antwoord', 'actie'];

/** Feiten: hoe het werkt / bewijs / waarom. */
const CE_CAMPAGNE_FEIT_FASES = ['waarom', 'hoe_werkt', 'bewijs', 'voorbeeld'];

function ce_campagne_bank_stable_id(string $clientId, string $kind, string $key): string
{
    $hex = md5($clientId . '|' . $kind . '|' . $key);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

function ce_campagne_merk_onderdeel_label(string $key): string
{
    return match ($key) {
        'wat_je_doet' => 'wat je doet',
        'waarom' => 'waarom je bestaat',
        'waar_je_tegen_bent' => 'waar je tegen bent',
        'hoe_je_werkt' => 'hoe je werkt',
        'waar_je_nee_tegen_zegt' => 'waar je nee tegen zegt',
        default => str_replace('_', ' ', $key),
    };
}

function ce_campagne_normalize_merk_onderdeel(?string $raw): ?string
{
    $k = strtolower(trim((string) $raw));
    if ($k === '' || $k === 'null') {
        return null;
    }

    return in_array($k, CE_CAMPAGNE_MERK_ONDERDELEN, true) ? $k : null;
}

/**
 * @param list<mixed> $raw
 * @return list<string>
 */
function ce_campagne_normalize_merk_onderdelen(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $item) {
        $k = ce_campagne_normalize_merk_onderdeel(is_string($item) ? $item : null);
        if ($k !== null && !in_array($k, $out, true)) {
            $out[] = $k;
        }
    }

    return $out;
}

function ce_ensure_campagne_bank_schema(PDO $pdo): void
{
    $migration = dirname(__DIR__) . '/migrations/add_campagne_ronde6_bank.sql';
    if (!is_readable($migration)) {
        $migration = dirname(__DIR__, 2) . '/scripts/migrations/add_campagne_ronde6_bank.sql';
    }
    if (is_readable($migration)) {
        try {
            $pdo->exec((string) file_get_contents($migration));
        } catch (Throwable) {
        }
    }

    $alters = [
        ['campaigns', 'merk_onderdelen_json', 'ALTER TABLE campaigns ADD COLUMN merk_onderdelen_json JSON NULL'],
        ['campaigns', 'feit_anchor_ids', 'ALTER TABLE campaigns ADD COLUMN feit_anchor_ids JSON NULL'],
        ['campaigns', 'vraag_anchor_ids', 'ALTER TABLE campaigns ADD COLUMN vraag_anchor_ids JSON NULL'],
        ['content_slots', 'campaign_merk_onderdeel', 'ALTER TABLE content_slots ADD COLUMN campaign_merk_onderdeel VARCHAR(64) NULL'],
    ];
    foreach ($alters as [$table, $column, $sql]) {
        if (function_exists('ce_table_exists') && ce_table_exists($pdo, $table) && !ce_table_has_column($pdo, $table, $column)) {
            try {
                $pdo->exec($sql);
                unset($GLOBALS['ce_table_columns'][$table]);
            } catch (Throwable) {
            }
        }
    }
}

/** @return list<array<string, mixed>> */
function ce_campagne_bank_list_vragen(PDO $pdo, string $clientId): array
{
    ce_ensure_campagne_bank_schema($pdo);
    if (!ce_table_exists($pdo, 'merk_vragen')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM merk_vragen WHERE client_id = :cid AND actief = 1 ORDER BY created_at ASC, vraag ASC'
    );
    $stmt->execute(['cid' => $clientId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[] = [
            'id' => (string) $row['id'],
            'vraag' => (string) ($row['vraag'] ?? ''),
            'bron' => (string) ($row['bron'] ?? 'bureau'),
            'doelpagina' => trim((string) ($row['doelpagina'] ?? '')) ?: null,
            'paginaAntwoord' => (string) ($row['pagina_antwoord'] ?? 'onbekend'),
            'actief' => true,
        ];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function ce_campagne_bank_list_feiten(PDO $pdo, string $clientId): array
{
    ce_ensure_campagne_bank_schema($pdo);
    if (!ce_table_exists($pdo, 'merk_feiten')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM merk_feiten WHERE client_id = :cid AND actief = 1 ORDER BY created_at ASC, formulering ASC'
    );
    $stmt->execute(['cid' => $clientId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[] = [
            'id' => (string) $row['id'],
            'formulering' => (string) ($row['formulering'] ?? ''),
            'bronUrl' => trim((string) ($row['bron_url'] ?? '')) ?: null,
            'merkOnderdeel' => ce_campagne_normalize_merk_onderdeel((string) ($row['merk_onderdeel'] ?? '')),
            'bevestigdDoorBureau' => ((int) ($row['bevestigd_door_bureau'] ?? 1)) === 1,
            'actief' => true,
        ];
    }

    return $out;
}

function ce_campagne_bank_upsert_vraag(PDO $pdo, string $clientId, string $id, string $vraag, string $bron = 'bureau'): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO merk_vragen (id, client_id, vraag, bron, actief)
         VALUES (:id, :cid, :vraag, :bron, 1)
         ON DUPLICATE KEY UPDATE vraag = VALUES(vraag), actief = 1'
    );
    $stmt->execute(['id' => $id, 'cid' => $clientId, 'vraag' => $vraag, 'bron' => $bron]);
}

function ce_campagne_bank_deactivate_vraag(PDO $pdo, string $clientId, string $id): void
{
    $stmt = $pdo->prepare(
        'UPDATE merk_vragen SET actief = 0 WHERE id = :id AND client_id = :cid'
    );
    $stmt->execute(['id' => $id, 'cid' => $clientId]);
}

function ce_campagne_bank_deactivate_feit(PDO $pdo, string $clientId, string $id): void
{
    $stmt = $pdo->prepare(
        'UPDATE merk_feiten SET actief = 0 WHERE id = :id AND client_id = :cid'
    );
    $stmt->execute(['id' => $id, 'cid' => $clientId]);
}

/**
 * @param array<string, mixed> $body
 * @return array{vragen: list<array<string, mixed>>, feiten: list<array<string, mixed>>}
 */
function ce_campagne_bank_save(PDO $pdo, string $clientId, array $body): array
{
    require_once __DIR__ . '/uuid-helper.php';
    ce_ensure_campagne_bank_schema($pdo);

    foreach ($body['removedVraagIds'] ?? $body['removed_vraag_ids'] ?? [] as $rawId) {
        $id = trim((string) $rawId);
        if ($id !== '') {
            ce_campagne_bank_deactivate_vraag($pdo, $clientId, $id);
        }
    }
    foreach ($body['removedFeitIds'] ?? $body['removed_feit_ids'] ?? [] as $rawId) {
        $id = trim((string) $rawId);
        if ($id !== '') {
            ce_campagne_bank_deactivate_feit($pdo, $clientId, $id);
        }
    }

    $idMap = [];
    foreach ($body['vragen'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $vraag = trim((string) ($row['vraag'] ?? ''));
        if ($vraag === '') {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '' || str_starts_with($id, 'new-') || $id === 'draft-new' || str_starts_with($id, 'draft-')) {
            $id = ce_uuid();
        }
        $clientRowId = trim((string) ($row['clientRowId'] ?? $row['client_row_id'] ?? ''));
        if ($clientRowId !== '') {
            $idMap[$clientRowId] = $id;
        }
        ce_campagne_bank_upsert_vraag($pdo, $clientId, $id, $vraag, (string) ($row['bron'] ?? 'bureau'));
    }

    foreach ($body['feiten'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $formulering = trim((string) ($row['formulering'] ?? ''));
        if ($formulering === '') {
            continue;
        }
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '' || str_starts_with($id, 'new-') || $id === 'draft-new' || str_starts_with($id, 'draft-')) {
            $id = ce_uuid();
        }
        $clientRowId = trim((string) ($row['clientRowId'] ?? $row['client_row_id'] ?? ''));
        if ($clientRowId !== '') {
            $idMap[$clientRowId] = $id;
        }
        $onderdeel = isset($row['merkOnderdeel']) || isset($row['merk_onderdeel'])
            ? trim((string) ($row['merkOnderdeel'] ?? $row['merk_onderdeel'] ?? ''))
            : null;
        if ($onderdeel === '') {
            $onderdeel = null;
        }
        $bronUrl = isset($row['bronUrl']) || isset($row['bron_url'])
            ? trim((string) ($row['bronUrl'] ?? $row['bron_url'] ?? ''))
            : null;
        if ($bronUrl === '') {
            $bronUrl = null;
        }
        ce_campagne_bank_upsert_feit($pdo, $clientId, $id, $formulering, $bronUrl, $onderdeel);
    }

    return [
        'vragen' => ce_campagne_bank_list_vragen($pdo, $clientId),
        'feiten' => ce_campagne_bank_list_feiten($pdo, $clientId),
        'idMap' => $idMap,
    ];
}

function ce_campagne_bank_upsert_feit(
    PDO $pdo,
    string $clientId,
    string $id,
    string $formulering,
    ?string $bronUrl,
    ?string $merkOnderdeel,
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO merk_feiten (id, client_id, formulering, bron_url, merk_onderdeel, bevestigd_door_bureau, actief)
         VALUES (:id, :cid, :formulering, :bron, :onderdeel, 1, 1)
         ON DUPLICATE KEY UPDATE formulering = VALUES(formulering), bron_url = VALUES(bron_url),
           merk_onderdeel = VALUES(merk_onderdeel), actief = 1, bevestigd_door_bureau = 1'
    );
    $stmt->execute([
        'id' => $id,
        'cid' => $clientId,
        'formulering' => $formulering,
        'bron' => $bronUrl,
        'onderdeel' => $merkOnderdeel,
    ]);
}

/**
 * 10 vragen + 10 feiten voor Brandpunt (addendum §8). Feiten letterlijk van de productpagina-fixture.
 *
 * @return array{vragen: list<string>, feiten: list<string>}
 */
function ce_campagne_bank_seed_brandpunt(PDO $pdo, string $clientId): array
{
    ce_ensure_campagne_bank_schema($pdo);
    $fixtureUrl = '/fixtures/brandpunt-huila-blend.html';
    $vragen = [
        'Wat gebeurt er met koffie die ouder is dan zes weken?',
        'Waarom staat de branddatum op elke zak?',
        'Van welke boer komt deze koffie?',
        'Hoe vers is de koffie als ik hem koop?',
        'Waarom geen voordeelpak of kilozak?',
        'Wanneer wordt deze koffie gebrand?',
        'Waar kan ik de koffie proeven?',
        'Wat smaakt een Huila-blend naar?',
        'Waarom branden jullie in kleine partijen?',
        'Wat als ik koffie alleen drink om wakker te worden?',
    ];
    $feiten = [
        ['Op elke zak staan de branddatum en de boer.', 'hoe_je_werkt'],
        ['Deze blend komt van drie vaste afnemers in Huila, Colombia.', 'wat_je_doet'],
        ['We branden in kleine partijen per herkomst.', 'hoe_je_werkt'],
        ['Wij verkopen geen koffie die langer dan zes weken geleden is gebrand, ook niet met korting.', 'waar_je_nee_tegen_zegt'],
        ['Wat over is, gaat naar de buurtvoedselbank of wordt weggegooid.', 'waar_je_nee_tegen_zegt'],
        ['Vorige maand gooiden we 14 kilo koffie weg.', 'hoe_je_werkt'],
        ['Die was zeven weken oud. Nog prima te drinken, maar niet meer wat de boer erin heeft gelegd.', 'waarom'],
        ['Deze Huila proef je het best zwart: eerst rood fruit, dan iets van cacao.', 'wat_je_doet'],
        ['Proefochtend elke eerste donderdag van de maand, in de winkel in Utrecht.', 'hoe_je_werkt'],
        ['250 gram: 12,50 euro. Geen voordeelpak: een kilo die je over twee maanden opdrinkt, smaakt aan het eind niet meer naar wat je kocht.', 'waar_je_nee_tegen_zegt'],
    ];

    $vraagIds = [];
    foreach ($vragen as $i => $vraag) {
        $id = ce_campagne_bank_stable_id($clientId, 'vraag', (string) $i);
        ce_campagne_bank_upsert_vraag($pdo, $clientId, $id, $vraag, 'app');
        $vraagIds[] = $id;
    }
    $feitIds = [];
    foreach ($feiten as $i => [$formulering, $onderdeel]) {
        $id = ce_campagne_bank_stable_id($clientId, 'feit', (string) $i);
        ce_campagne_bank_upsert_feit($pdo, $clientId, $id, $formulering, $fixtureUrl, $onderdeel);
        $feitIds[] = $id;
    }

    return ['vragen' => $vraagIds, 'feiten' => $feitIds];
}

function ce_campagne_strip_bank_anker(string $opdracht): string
{
    $stripped = preg_replace(
        '/(?:\n\n)?(?:' . preg_quote(CE_CAMPAGNE_ANKER_PREFIX_VRAAG, '/')
        . '|' . preg_quote(CE_CAMPAGNE_ANKER_PREFIX_FEIT, '/')
        . '|' . preg_quote('Anker (GEO):', '/')
        . ').*$/s',
        '',
        $opdracht,
    );

    return trim((string) $stripped);
}

function ce_campagne_opdracht_with_bank_anker(string $opdracht, string $soort, string $tekst): string
{
    $base = ce_campagne_strip_bank_anker($opdracht);
    $t = trim($tekst);
    if ($t === '') {
        return $base;
    }
    $prefix = $soort === 'feit' ? CE_CAMPAGNE_ANKER_PREFIX_FEIT : CE_CAMPAGNE_ANKER_PREFIX_VRAAG;
    $line = $prefix . ' ' . $t;

    return $base !== '' ? $base . "\n\n" . $line : $line;
}

/**
 * @param list<array<string, mixed>> $slots
 * @param list<array{id: string, vraag: string}> $vragen
 * @param list<array{id: string, formulering: string}> $feiten
 * @return list<array{slotId: string, soort: string, id: string, tekst: string}>
 */
function ce_campagne_preview_allocate_bank_ankers(array $slots, array $vragen, array $feiten, bool $forceAll = false): array
{
    $n = count($slots);
    if ($n === 0 || ($vragen === [] && $feiten === [])) {
        return [];
    }

    $sorted = $slots;
    usort(
        $sorted,
        static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['publishDate'] ?? ''), (string) ($b['publishDate'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($a['slotIndex'] ?? 0)) <=> ((int) ($b['slotIndex'] ?? 0));
        },
    );

    $target = $forceAll ? $n : (int) round($n / 3);
    if ($target < 1) {
        $target = 1;
    }
    if ($target > $n) {
        $target = $n;
    }

    $vraagPool = [];
    $feitPool = [];
    $rest = [];
    foreach ($sorted as $slot) {
        $fase = strtolower(trim((string) ($slot['faseKey'] ?? '')));
        if ($vragen !== [] && in_array($fase, CE_CAMPAGNE_VRAAG_FASES, true)) {
            $vraagPool[] = $slot;
        } elseif ($feiten !== [] && in_array($fase, CE_CAMPAGNE_FEIT_FASES, true)) {
            $feitPool[] = $slot;
        } else {
            $rest[] = $slot;
        }
    }

    $pickSlots = [];
    $seen = [];
    $add = static function (array $slot) use (&$pickSlots, &$seen): void {
        $id = (string) ($slot['slotId'] ?? $slot['id'] ?? '');
        if ($id === '' || isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;
        $pickSlots[] = $slot;
    };

    $half = (int) ceil($target / 2);
    foreach (array_slice($vraagPool, 0, $half) as $slot) {
        $add($slot);
    }
    foreach (array_slice($feitPool, 0, $target) as $slot) {
        if (count($pickSlots) >= $target) {
            break;
        }
        $add($slot);
    }
    foreach (array_merge($vraagPool, $feitPool, $rest, $sorted) as $slot) {
        if (count($pickSlots) >= $target) {
            break;
        }
        $add($slot);
    }

    $out = [];
    $vraagI = 0;
    $feitI = 0;
    $usedFactWeekChannel = [];
    foreach ($pickSlots as $i => $slot) {
        $slotId = (string) ($slot['slotId'] ?? $slot['id'] ?? '');
        $fase = strtolower(trim((string) ($slot['faseKey'] ?? '')));
        $preferFeit = $feiten !== [] && in_array($fase, CE_CAMPAGNE_FEIT_FASES, true);
        $preferVraag = $vragen !== [] && in_array($fase, CE_CAMPAGNE_VRAAG_FASES, true);
        $useFeit = $preferFeit || (!$preferVraag && $feiten !== [] && ($i % 2 === 1 || $vragen === []));
        if ($useFeit && $feiten !== []) {
            $feit = $feiten[$feitI % count($feiten)];
            $week = '';
            $date = trim((string) ($slot['publishDate'] ?? ''));
            if ($date !== '') {
                try {
                    $week = (new DateTimeImmutable($date))->format('o-W');
                } catch (Throwable) {
                    $week = substr($date, 0, 7);
                }
            }
            $channel = strtolower((string) ($slot['platformKey'] ?? $slot['platform'] ?? ''));
            $factKey = ($feit['id'] ?? '') . '|' . $week . '|' . $channel;
            $tries = 0;
            while (isset($usedFactWeekChannel[$factKey]) && $tries < count($feiten)) {
                ++$feitI;
                $feit = $feiten[$feitI % count($feiten)];
                $factKey = ($feit['id'] ?? '') . '|' . $week . '|' . $channel;
                ++$tries;
            }
            if (isset($usedFactWeekChannel[$factKey]) && $vragen !== []) {
                $useFeit = false;
            } else {
                $usedFactWeekChannel[$factKey] = true;
                $out[] = [
                    'slotId' => $slotId,
                    'soort' => 'feit',
                    'id' => (string) ($feit['id'] ?? ''),
                    'tekst' => (string) ($feit['formulering'] ?? ''),
                ];
                ++$feitI;
                continue;
            }
        }
        if ($vragen === []) {
            continue;
        }
        $vraag = $vragen[$vraagI % count($vragen)];
        $out[] = [
            'slotId' => $slotId,
            'soort' => 'vraag',
            'id' => (string) ($vraag['id'] ?? ''),
            'tekst' => (string) ($vraag['vraag'] ?? ''),
        ];
        ++$vraagI;
    }

    return $out;
}

/**
 * @param list<mixed> $vraagIds
 * @param list<mixed> $feitIds
 */
function ce_campagne_preview_set_bank_ankers(
    PDO $pdo,
    string $campaignId,
    ?string $clientId,
    array $vraagIds,
    array $feitIds,
    bool $forceAll = false,
): void {
    ce_ensure_campagne_bank_schema($pdo);
    $detail = ce_campagne_preview_fetch($pdo, $campaignId, $clientId);
    if ($detail === null) {
        throw new RuntimeException('Campagne niet gevonden');
    }

    $cleanVragen = [];
    foreach ($vraagIds as $id) {
        $t = trim((string) $id);
        if ($t !== '' && !in_array($t, $cleanVragen, true)) {
            $cleanVragen[] = $t;
        }
    }
    $cleanFeiten = [];
    foreach ($feitIds as $id) {
        $t = trim((string) $id);
        if ($t !== '' && !in_array($t, $cleanFeiten, true)) {
            $cleanFeiten[] = $t;
        }
    }
    if (count($cleanVragen) > CE_CAMPAGNE_BANK_VRAAG_MAX) {
        throw new InvalidArgumentException('Kies 1 tot ' . CE_CAMPAGNE_BANK_VRAAG_MAX . ' vragen als anker');
    }
    if (count($cleanFeiten) > CE_CAMPAGNE_BANK_FEIT_MAX) {
        throw new InvalidArgumentException('Kies 1 tot ' . CE_CAMPAGNE_BANK_FEIT_MAX . ' feiten als anker');
    }

    $vragenById = [];
    foreach (ce_campagne_bank_list_vragen($pdo, (string) $clientId) as $q) {
        $vragenById[(string) $q['id']] = ['id' => (string) $q['id'], 'vraag' => (string) $q['vraag']];
    }
    $feitenById = [];
    foreach (ce_campagne_bank_list_feiten($pdo, (string) $clientId) as $f) {
        $feitenById[(string) $f['id']] = ['id' => (string) $f['id'], 'formulering' => (string) $f['formulering']];
    }

    $vragen = [];
    foreach ($cleanVragen as $id) {
        if (!isset($vragenById[$id])) {
            throw new InvalidArgumentException('Vraag niet gevonden of niet actief: ' . $id);
        }
        $vragen[] = $vragenById[$id];
    }
    $feiten = [];
    foreach ($cleanFeiten as $id) {
        if (!isset($feitenById[$id])) {
            throw new InvalidArgumentException('Feit niet gevonden of niet actief: ' . $id);
        }
        $feiten[] = $feitenById[$id];
    }

    if (ce_table_has_column($pdo, 'campaigns', 'vraag_anchor_ids')) {
        $scope = ce_client_scope_sql($pdo, 'campaigns', 'c', $clientId);
        $sql = 'UPDATE campaigns c SET vraag_anchor_ids = :vragen, feit_anchor_ids = :feiten WHERE c.id = :id' . $scope['sql'];
        $pdo->prepare($sql)->execute(array_merge([
            'id' => $campaignId,
            'vragen' => json_encode($cleanVragen, JSON_THROW_ON_ERROR),
            'feiten' => json_encode($cleanFeiten, JSON_THROW_ON_ERROR),
        ], $scope['params']));
    }

    $slots = is_array($detail['slots'] ?? null) ? $detail['slots'] : [];
    $assignments = ce_campagne_preview_allocate_bank_ankers($slots, $vragen, $feiten, $forceAll);
    $bySlot = [];
    foreach ($assignments as $item) {
        $bySlot[$item['slotId']] = $item;
    }

    $hasAnkerCol = ce_table_has_column($pdo, 'content_slots', 'campaign_anker');
    $update = $hasAnkerCol
        ? $pdo->prepare('UPDATE content_slots SET campaign_opdracht = :opdracht, campaign_anker = :anker WHERE id = :id')
        : $pdo->prepare('UPDATE content_slots SET campaign_opdracht = :opdracht WHERE id = :id');
    $updateArticle = ce_table_has_column($pdo, 'articles', 'rationale')
        ? $pdo->prepare('UPDATE articles SET rationale = :rationale WHERE slot_id = :slot_id')
        : null;

    foreach ($slots as $slot) {
        $slotId = (string) ($slot['slotId'] ?? '');
        if ($slotId === '') {
            continue;
        }
        $base = ce_campagne_strip_bank_anker((string) ($slot['opdracht'] ?? ''));
        $assignment = $bySlot[$slotId] ?? null;
        $opdracht = $base;
        $ankerJson = null;
        if ($assignment !== null) {
            $opdracht = ce_campagne_opdracht_with_bank_anker(
                $base,
                (string) $assignment['soort'],
                (string) $assignment['tekst'],
            );
            $ankerJson = json_encode([
                'soort' => $assignment['soort'],
                'id' => $assignment['id'],
                'vraag' => $assignment['soort'] === 'vraag' ? $assignment['tekst'] : '',
                'formulering' => $assignment['soort'] === 'feit' ? $assignment['tekst'] : '',
                'tekst' => $assignment['tekst'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $params = ['id' => $slotId, 'opdracht' => $opdracht !== '' ? $opdracht : null];
        if ($hasAnkerCol) {
            $params['anker'] = $ankerJson;
        }
        $update->execute($params);
        if ($updateArticle !== null) {
            $updateArticle->execute(['slot_id' => $slotId, 'rationale' => $opdracht !== '' ? $opdracht : null]);
        }
    }
}
