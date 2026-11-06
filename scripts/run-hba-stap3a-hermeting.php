#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lokaal: stap D hermeting tegen staging-DB (geen deploy). Schrijft docs/hba-v1.11-stap3a-merkbewering-hermeting.json
 * Gebruik: source scripts/ce-env.sh staging && php scripts/run-hba-stap3a-hermeting.php
 */

$root = dirname(__DIR__);

$envFile = getenv('CE_ENV_FILE') ?: $root . '/.env.staging';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

require_once $root . '/web/api/bootstrap.php';
require_once $root . '/web/api/ce03-self-review-repository.php';

$articleId = '9e73e3fd-caee-11f1-84cf-9c6b00b7cd02';
$baselinePath = $root . '/docs/hba-v1.11-stap3-geen-verzonnen-feiten.json';
$outPath = $root . '/docs/hba-v1.11-stap3a-merkbewering-hermeting.json';

if (!is_readable($baselinePath)) {
    fwrite(STDERR, "Baseline ontbreekt: {$baselinePath}\n");
    exit(1);
}

$baseline = json_decode((string) file_get_contents($baselinePath), true);
if (!is_array($baseline)) {
    fwrite(STDERR, "Ongeldige baseline JSON\n");
    exit(1);
}

$oldRows = $baseline['selfReview']['merkBeweringen'] ?? [];
if (!is_array($oldRows)) {
    $oldRows = [];
}

$pdo = ce_db();
$result = ce_ce03_rerun_step_d_for_article($pdo, $articleId, true);
if (!$result['ok']) {
    fwrite(STDERR, ($result['error'] ?? 'mislukt') . "\n");
    exit(1);
}

$newParsed = is_array($result['selfReviewParsed'] ?? null) ? $result['selfReviewParsed'] : [];
$newRows = $newParsed['merkBeweringen'] ?? [];

/** @param list<array<string, mixed>> $oldRows @param list<array<string, mixed>> $newRows */
function hba_stap3a_compare(array $oldRows, array $newRows): array
{
    $norm = static fn (string $s): string => preg_replace('/\s+/u', ' ', trim($s)) ?? trim($s);
    $newBy = [];
    foreach ($newRows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $newBy[$norm((string) ($r['citaat'] ?? ''))] = $r;
    }
    $used = [];
    $out = [];
    foreach ($oldRows as $old) {
        if (!is_array($old)) {
            continue;
        }
        $c = $norm((string) ($old['citaat'] ?? ''));
        $neu = $newBy[$c] ?? null;
        if ($neu === null) {
            foreach ($newBy as $nc => $nr) {
                if (str_contains($c, $nc) || str_contains($nc, $c)) {
                    $neu = $nr;
                    break;
                }
            }
        }
        $entry = [
            'citaat' => $old['citaat'] ?? '',
            'bronOud' => $old['bron'] ?? null,
            'bronNieuw' => null,
            'steuntOp' => null,
            'status' => 'verwijderd_uit_lijst',
            'reden' => '',
        ];
        if (is_array($neu)) {
            $nk = $norm((string) ($neu['citaat'] ?? ''));
            $used[$nk] = true;
            $entry['bronNieuw'] = $neu['bron'] ?? null;
            $entry['steuntOp'] = $neu['steuntOp'] ?? null;
            if (($entry['bronOud'] ?? '') === ($entry['bronNieuw'] ?? '')) {
                $entry['status'] = 'ongewijzigd';
            } else {
                $entry['status'] = 'label_gewijzigd';
                if (($entry['bronOud'] ?? '') === 'geen_bron' && ($entry['bronNieuw'] ?? '') === 'bewijsveld') {
                    $entry['reden'] = 'Vervolgzin op proefochtend; bron uit voorgaande bewijsveld-bewering (context).';
                } elseif (str_contains(strtolower($c), 'geen reden voor')) {
                    $entry['reden'] = 'Retoriek/standpunt — geen merkbewering (scope).';
                } else {
                    $entry['reden'] = 'Nieuwe definitie merkbewering / context.';
                }
            }
        } elseif (str_contains(strtolower($c), 'geen reden voor')) {
            $entry['reden'] = 'Valt buiten merkbewering (standpunt, geen merkfeit).';
        } elseif (str_contains(strtolower($c), 'leg je uit') || str_contains(strtolower($c), 'leggen wij uit')) {
            $entry['reden'] = 'Vervolg op proefochtend — niet meer als losse geen_bron.';
        }
        $out[] = $entry;
    }
    foreach ($newRows as $neu) {
        if (!is_array($neu)) {
            continue;
        }
        $nk = $norm((string) ($neu['citaat'] ?? ''));
        if (isset($used[$nk])) {
            continue;
        }
        $out[] = [
            'citaat' => $neu['citaat'] ?? '',
            'bronOud' => null,
            'bronNieuw' => $neu['bron'] ?? null,
            'steuntOp' => $neu['steuntOp'] ?? null,
            'status' => 'nieuw_in_lijst',
            'reden' => 'Nieuwe merkbewering na hermeting.',
        ];
    }

    return $out;
}

$unsourced = 0;
foreach ($newRows as $r) {
    if (is_array($r) && ($r['bron'] ?? '') === 'geen_bron') {
        $unsourced++;
    }
}

$report = [
    'generatedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
    'articleId' => $articleId,
    'baselineFile' => 'docs/hba-v1.11-stap3-geen-verzonnen-feiten.json',
    'textFixAppliedInPrompt' => true,
    'merkBeweringenVergelijking' => hba_stap3a_compare($oldRows, $newRows),
    'merkBeweringenNieuw' => $newRows,
    'unsourcedCount' => $unsourced,
    'ok' => $unsourced === 0,
    'selfReviewNieuw' => $newParsed,
];

file_put_contents(
    $outPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
    LOCK_EX
);

fwrite(STDOUT, "Geschreven: {$outPath}\n");
fwrite(STDOUT, sprintf("ok=%s unsourced=%d beweringen=%d\n", $report['ok'] ? 'true' : 'false', $unsourced, count($newRows)));

exit($report['ok'] ? 0 : 2);
