<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';
require_once __DIR__ . '/uuid-helper.php';

const CE_TESTSET_BLIND_SCORERS = ['Erik', 'Rogier', 'Eva'];

const CE_TESTSET_BLIND_QUESTION_KEYS = [
    'q_clear_claim',
    'q_concrete',
    'q_brand',
    'q_dutch',
    'q_would_post',
];

function ce_ensure_testset_blind_scores_schema(PDO $pdo): void
{
    if (ce_table_exists($pdo, 'testset_blind_scores')) {
        return;
    }

    $migration = dirname(__DIR__, 2) . '/scripts/migrations/add_testset_blind_scores.sql';
    if (!is_readable($migration)) {
        $migration = dirname(__DIR__) . '/migrations/add_testset_blind_scores.sql';
    }
    if (!is_readable($migration)) {
        throw new RuntimeException('Migratie testset_blind_scores ontbreekt');
    }

    $sql = file_get_contents($migration);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Lege migratie testset_blind_scores');
    }

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    unset($GLOBALS['ce_table_columns']['testset_blind_scores']);
}

function ce_testset_blind_scorer_normalize(string $scorer): string
{
    $scorer = trim($scorer);
    foreach (CE_TESTSET_BLIND_SCORERS as $allowed) {
        if (strcasecmp($scorer, $allowed) === 0) {
            return $allowed;
        }
    }

    throw new InvalidArgumentException('Kies Erik, Rogier of Eva als scorer');
}

function ce_testset_blind_score_value(mixed $value): int
{
    $n = (int) $value;
    if ($n < 1 || $n > 5) {
        throw new InvalidArgumentException('Elke score moet tussen 1 en 5 liggen');
    }

    return $n;
}

/** @return list<array<string, mixed>> */
function ce_testset_blind_scores_list(PDO $pdo, string $stap): array
{
    ce_ensure_testset_blind_scores_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT stap, code, scorer, q_clear_claim, q_concrete, q_brand, q_dutch, q_would_post, notes, updated_at
         FROM testset_blind_scores
         WHERE stap = :stap
         ORDER BY code ASC, scorer ASC'
    );
    $stmt->execute(['stap' => $stap]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static function (array $row): array {
        return [
            'stap' => (string) ($row['stap'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'scorer' => (string) ($row['scorer'] ?? ''),
            'scores' => [
                'q_clear_claim' => (int) ($row['q_clear_claim'] ?? 0),
                'q_concrete' => (int) ($row['q_concrete'] ?? 0),
                'q_brand' => (int) ($row['q_brand'] ?? 0),
                'q_dutch' => (int) ($row['q_dutch'] ?? 0),
                'q_would_post' => (int) ($row['q_would_post'] ?? 0),
            ],
            'notes' => (string) ($row['notes'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }, $rows);
}

/** @param array<string, mixed> $body @return array<string, mixed> */
function ce_testset_blind_score_save(PDO $pdo, array $body): array
{
    ce_ensure_testset_blind_scores_schema($pdo);

    $stap = trim((string) ($body['stap'] ?? '1-nulmeting'));
    if ($stap === '') {
        $stap = '1-nulmeting';
    }
    $code = strtoupper(trim((string) ($body['code'] ?? '')));
    if (!preg_match('/^[A-Z0-9]{2}$/', $code)) {
        throw new InvalidArgumentException('Ongeldige code');
    }

    $scorer = ce_testset_blind_scorer_normalize((string) ($body['scorer'] ?? ''));
    $scoresIn = is_array($body['scores'] ?? null) ? $body['scores'] : $body;
    $notes = trim((string) ($body['notes'] ?? ''));

    $values = [];
    foreach (CE_TESTSET_BLIND_QUESTION_KEYS as $key) {
        $values[$key] = ce_testset_blind_score_value($scoresIn[$key] ?? null);
    }

    $existing = $pdo->prepare(
        'SELECT id FROM testset_blind_scores WHERE stap = :stap AND code = :code AND scorer = :scorer LIMIT 1'
    );
    $existing->execute(['stap' => $stap, 'code' => $code, 'scorer' => $scorer]);
    $id = (string) ($existing->fetchColumn() ?: '');
    if ($id === '') {
        $id = ce_uuid_v4();
        $pdo->prepare(
            'INSERT INTO testset_blind_scores (
                id, stap, code, scorer,
                q_clear_claim, q_concrete, q_brand, q_dutch, q_would_post, notes
            ) VALUES (
                :id, :stap, :code, :scorer,
                :q_clear_claim, :q_concrete, :q_brand, :q_dutch, :q_would_post, :notes
            )'
        )->execute([
            'id' => $id,
            'stap' => $stap,
            'code' => $code,
            'scorer' => $scorer,
            'notes' => $notes !== '' ? $notes : null,
        ] + $values);
    } else {
        $pdo->prepare(
            'UPDATE testset_blind_scores SET
                q_clear_claim = :q_clear_claim,
                q_concrete = :q_concrete,
                q_brand = :q_brand,
                q_dutch = :q_dutch,
                q_would_post = :q_would_post,
                notes = :notes
             WHERE id = :id'
        )->execute(['id' => $id, 'notes' => $notes !== '' ? $notes : null] + $values);
    }

    return [
        'ok' => true,
        'stap' => $stap,
        'code' => $code,
        'scorer' => $scorer,
        'scores' => $values,
        'notes' => $notes,
    ];
}
