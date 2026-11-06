<?php

declare(strict_types=1);

require_once __DIR__ . '/event-log-repository.php';

/**
 * Poll n8n API for recent failed executions and write new ones to event_log.
 *
 * @return array{ok: bool, error?: string, fetched: int, logged: int, skipped: int}
 */
function ce_poll_n8n_failed_executions(PDO $pdo, int $limit = 50, int $lookbackHours = 48): array
{
    $apiUrl = rtrim(ce_config_value('N8N_API_URL', 'n8n_api_url'), '/');
    $apiKey = trim(ce_config_value('N8N_API_KEY', 'n8n_api_key'));
    if ($apiUrl === '' || $apiKey === '' || str_contains(strtolower($apiKey), 'plak')) {
        return ['ok' => false, 'error' => 'N8N_API_URL of N8N_API_KEY niet geconfigureerd', 'fetched' => 0, 'logged' => 0, 'skipped' => 0];
    }

    $limit = max(1, min(250, $limit));
    $endpoint = $apiUrl . '/api/v1/executions?status=error&limit=' . $limit;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nX-N8N-API-KEY: {$apiKey}\r\n",
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($endpoint, false, $context);
    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', (string) $http_response_header[0], $matches)) {
        $httpCode = (int) $matches[1];
    }
    if ($httpCode < 200 || $httpCode >= 300 || !is_string($response)) {
        return [
            'ok' => false,
            'error' => 'n8n API antwoordde met HTTP ' . $httpCode,
            'fetched' => 0,
            'logged' => 0,
            'skipped' => 0,
        ];
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return ['ok' => false, 'error' => 'Ongeldig n8n JSON-antwoord', 'fetched' => 0, 'logged' => 0, 'skipped' => 0];
    }

    $executions = $payload['data'] ?? [];
    if (!is_array($executions)) {
        $executions = [];
    }

    $cutoff = (new DateTimeImmutable("-{$lookbackHours} hours"));
    $logged = 0;
    $skipped = 0;

    foreach ($executions as $execution) {
        if (!is_array($execution)) {
            continue;
        }

        $executionId = (string) ($execution['id'] ?? '');
        if ($executionId === '') {
            continue;
        }

        $startedAt = trim((string) ($execution['startedAt'] ?? ''));
        if ($startedAt !== '') {
            try {
                $started = new DateTimeImmutable($startedAt);
                if ($started < $cutoff) {
                    ++$skipped;
                    continue;
                }
            } catch (Throwable) {
                // Onleesbare timestamp — toch loggen.
            }
        }

        if (ce_n8n_execution_already_logged($pdo, $executionId)) {
            ++$skipped;
            continue;
        }

        $workflowName = trim((string) ($execution['workflowName'] ?? ''));
        $workflowId = trim((string) ($execution['workflowId'] ?? ''));
        $message = $workflowName !== ''
            ? "Workflow mislukt: {$workflowName}"
            : 'n8n-executie mislukt';

        ce_log_event($pdo, 'error', 'n8n', 'n8n_execution_failed', $message, [
            'executionId' => $executionId,
            'workflowId' => $workflowId !== '' ? $workflowId : null,
            'workflowName' => $workflowName !== '' ? $workflowName : null,
            'startedAt' => $startedAt !== '' ? $startedAt : null,
            'stoppedAt' => trim((string) ($execution['stoppedAt'] ?? '')) ?: null,
            'mode' => trim((string) ($execution['mode'] ?? '')) ?: null,
        ]);
        ++$logged;
    }

    return [
        'ok' => true,
        'fetched' => count($executions),
        'logged' => $logged,
        'skipped' => $skipped,
    ];
}

function ce_n8n_execution_already_logged(PDO $pdo, string $executionId): bool
{
    ce_ensure_event_log_schema($pdo);
    if (!ce_table_exists($pdo, 'event_log')) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM event_log
         WHERE event = 'n8n_execution_failed'
           AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.executionId')) = :execution_id
         LIMIT 1"
    );
    $stmt->execute(['execution_id' => $executionId]);

    return (bool) $stmt->fetchColumn();
}
