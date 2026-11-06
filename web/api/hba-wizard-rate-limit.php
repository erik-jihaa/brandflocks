<?php

declare(strict_types=1);

const CE_HBA_WIZARD_DAILY_DOCUMENT_UPLOAD_LIMIT = 5;
const CE_HBA_WIZARD_DAILY_CHECK_LIMIT = 20;

/** Interne ijkrun-tenant — geen wizard-daglimiet. */
const CE_HBA_IJKRUN_SANDBOX_CLIENT_NAME = 'HBA Ijkrun sandbox';

function ce_hba_wizard_rate_limit_exempt(PDO $pdo, string $clientId): bool
{
    static $memo = [];
    if (isset($memo[$clientId])) {
        return $memo[$clientId];
    }
    if ($clientId === '') {
        return $memo[$clientId] = false;
    }
    $stmt = $pdo->prepare('SELECT TRIM(name) FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $name = (string) ($stmt->fetchColumn() ?: '');
    $memo[$clientId] = $name === CE_HBA_IJKRUN_SANDBOX_CLIENT_NAME;

    return $memo[$clientId];
}

function ce_hba_wizard_rate_limit_storage_dir(): string
{
    return dirname(__DIR__) . '/uploads/hba-wizard-rate-limits';
}

function ce_hba_wizard_rate_limit_path(string $clientId): string
{
    return ce_hba_wizard_rate_limit_storage_dir() . '/' . $clientId . '.json';
}

/** @return array{date: string, document_uploads: int, wizard_checks: int} */
function ce_hba_wizard_rate_limit_load(string $clientId): array
{
    $today = gmdate('Y-m-d');
    $defaults = [
        'date' => $today,
        'document_uploads' => 0,
        'wizard_checks' => 0,
    ];

    $path = ce_hba_wizard_rate_limit_path($clientId);
    if (!is_readable($path)) {
        return $defaults;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return $defaults;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || (string) ($data['date'] ?? '') !== $today) {
        return $defaults;
    }

    return [
        'date' => $today,
        'document_uploads' => max(0, (int) ($data['document_uploads'] ?? 0)),
        'wizard_checks' => max(0, (int) ($data['wizard_checks'] ?? 0)),
    ];
}

/** @param array{date: string, document_uploads: int, wizard_checks: int} $counters */
function ce_hba_wizard_rate_limit_save(string $clientId, array $counters): void
{
    $dir = ce_hba_wizard_rate_limit_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    file_put_contents(
        ce_hba_wizard_rate_limit_path($clientId),
        json_encode($counters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX,
    );
}

/** @return array{ok: true}|array{ok: false, error: string, code: string} */
function ce_hba_wizard_rate_limit_check(string $clientId, string $kind): array
{
    $counters = ce_hba_wizard_rate_limit_load($clientId);

    if ($kind === 'document_upload') {
        if ($counters['document_uploads'] >= CE_HBA_WIZARD_DAILY_DOCUMENT_UPLOAD_LIMIT) {
            return [
                'ok' => false,
                'code' => 'rate_limit',
                'error' => 'Daglimiet bereikt: max. '
                    . CE_HBA_WIZARD_DAILY_DOCUMENT_UPLOAD_LIMIT
                    . ' document-uploads per dag. Probeer morgen opnieuw.',
            ];
        }

        return ['ok' => true];
    }

    if ($kind === 'wizard_check') {
        if ($counters['wizard_checks'] >= CE_HBA_WIZARD_DAILY_CHECK_LIMIT) {
            return [
                'ok' => false,
                'code' => 'rate_limit',
                'error' => 'Daglimiet bereikt: max. '
                    . CE_HBA_WIZARD_DAILY_CHECK_LIMIT
                    . ' wizard-checks per dag. Probeer morgen opnieuw.',
            ];
        }

        return ['ok' => true];
    }

    return ['ok' => true];
}

function ce_hba_wizard_rate_limit_increment(string $clientId, string $kind): void
{
    $counters = ce_hba_wizard_rate_limit_load($clientId);

    if ($kind === 'document_upload') {
        $counters['document_uploads']++;
    } elseif ($kind === 'wizard_check') {
        $counters['wizard_checks']++;
    } else {
        return;
    }

    ce_hba_wizard_rate_limit_save($clientId, $counters);
}
