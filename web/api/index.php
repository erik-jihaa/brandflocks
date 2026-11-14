<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

set_exception_handler(static function (Throwable $e): void {
    $message = $e->getMessage();
    if (getenv('CE_DEBUG') === '1') {
        $message .= ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
    ce_json_error($message, 500);
});

ce_handle_options();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/api#', '', $path) ?: '/';

require_once __DIR__ . '/auth-guard.php';
require_once __DIR__ . '/content-reset.php';

$skipAuth = ($path === '/admin/reset-content' || $path === '/admin/set-password' || $path === '/admin/set-platform-password' || $path === '/admin/mail-test' || $path === '/admin/agenda-repair' || $path === '/admin/mailbox-sync' || $path === '/admin/scraper-sync' || $path === '/admin/ce02-trigger' || $path === '/admin/theme-refill' || $path === '/admin/llm-visibility-sync' || $path === '/admin/create-client' || $path === '/admin/hba-article-hint-slot' || $path === '/admin/create-platform-user' || $path === '/admin/update-client' || $path === '/admin/export-tenant' || $path === '/admin/import-tenant' || $path === '/admin/reset-client-content' || $path === '/admin/delete-client' || $path === '/admin/delete-asana-sources' || $path === '/admin/wp-diagnose' || $path === '/admin/debug-research' || $path === '/admin/campagne-slot-titles-repair' || $path === '/admin/campagne-archive' || $path === '/admin/hygiene' || $path === '/admin/list-slots' || $path === '/admin/schema-export' || $path === '/admin/schema-migrations-init' || $path === '/admin/run-migration' || $path === '/admin/events' || $path === '/admin/prune-support-service' || $path === '/admin/import-support-emails' || $path === '/admin/poll-n8n-failures' || $path === '/admin/db-dump' || $path === '/admin/analytics-sync' || $path === '/admin/wordpress-db' || ($path === '/hba/score' && $method === 'POST' && ce_reset_token_valid()) || ($path === '/ce03/slot-meta' && $method === 'POST' && ce_reset_token_valid()))
    && in_array($method, ['GET', 'POST'], true)
    && ce_reset_token_valid();

if (preg_match('#^/platform/auth/(login|logout|session|forgot-password|reset-password)$#', $path) && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/platform-auth.php';
    exit;
}

if (preg_match('#^/sources/([0-9a-f-]{36})/scraper-run#i', $path, $scraperMatches)) {
    $_GET['source_id'] = $scraperMatches[1];
    if ($method === 'POST' && ce_reset_token_valid()) {
        require_once __DIR__ . '/sources-scraper-run.php';
        exit;
    }
    if ($method === 'POST') {
        ce_json_error('Unauthorized', 401);
    }
    ce_json_error('Method not allowed', 405);
}

if (preg_match('#^/sources/([0-9a-f-]{36})/blog-run#i', $path, $blogMatches)) {
    $_GET['source_id'] = $blogMatches[1];
    if ($method === 'POST' && ce_reset_token_valid()) {
        require_once __DIR__ . '/sources-blog-run.php';
        exit;
    }
    if ($method === 'POST') {
        ce_json_error('Unauthorized', 401);
    }
    ce_json_error('Method not allowed', 405);
}

if (preg_match('#^/sources/([0-9a-f-]{36})/document-run#i', $path, $documentRunMatches)) {
    $_GET['source_id'] = $documentRunMatches[1];
    if ($method === 'POST' && ce_reset_token_valid()) {
        require_once __DIR__ . '/sources-document-run.php';
        exit;
    }
    if ($method === 'POST') {
        ce_json_error('Unauthorized', 401);
    }
    ce_json_error('Method not allowed', 405);
}

if (preg_match('#^/sources/([0-9a-f-]{36})/drive-run#i', $path, $driveRunMatches)) {
    $_GET['source_id'] = $driveRunMatches[1];
    if ($method === 'POST' && ce_reset_token_valid()) {
        require_once __DIR__ . '/sources-drive-run.php';
        exit;
    }
    if ($method === 'POST') {
        ce_json_error('Unauthorized', 401);
    }
    ce_json_error('Method not allowed', 405);
}

if (preg_match('#^/sources/([0-9a-f-]{36})/document-upload#i', $path, $documentUploadMatches) && $method === 'POST') {
    $_GET['source_id'] = $documentUploadMatches[1];
    require_once __DIR__ . '/sources-document-upload.php';
    exit;
}

if ($path === '/linkedin/sync' && $method === 'POST') {
    require_once __DIR__ . '/linkedin-sync.php';
    exit;
}

if ($path === '/source-folders' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/source-folders.php';
    exit;
}

if (preg_match('#^/source-folders/([0-9a-f-]{36})$#i', $path, $folderMatches)) {
    $_GET['folder_id'] = $folderMatches[1];
    require_once __DIR__ . '/source-folders-item.php';
    exit;
}

if ($path === '/sources/sync' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/sources-sync.php';
    exit;
}

if (str_starts_with($path, '/platform')) {
    require_once __DIR__ . '/platform-guard.php';
require_once __DIR__ . '/platform-repository.php';
require_once __DIR__ . '/platform-actions-repository.php';
require_once __DIR__ . '/event-log-suggestions.php';
    require_once __DIR__ . '/support-service-repository.php';
    require_once __DIR__ . '/platform-users-repository.php';
    require_once __DIR__ . '/event-log-repository.php';
    $platformPdo = ce_db();
    ce_require_platform_auth($platformPdo);

    if ($path === '/platform/tenants' && $method === 'GET') {
        ce_json_response(['ok' => true, 'tenants' => ce_platform_fetch_tenants_overview($platformPdo)]);
    }

    if ($path === '/platform/tenants' && $method === 'POST') {
        ce_require_platform_auth($platformPdo, 'admin');
        require_once __DIR__ . '/account-repository.php';
        try {
            ce_json_response(ce_platform_create_tenant($platformPdo, ce_read_json_body(), ce_resolve_platform_user_id()));
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 500);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})$#i', $path, $platformTenantMatches) && $method === 'GET') {
        try {
            ce_json_response(ce_platform_fetch_tenant_detail($platformPdo, $platformTenantMatches[1]));
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})$#i', $path, $platformTenantMatches) && $method === 'PATCH') {
        ce_require_platform_auth($platformPdo, 'admin');
        try {
            ce_json_response(ce_platform_update_tenant(
                $platformPdo,
                $platformTenantMatches[1],
                ce_read_json_body(),
                ce_resolve_platform_user_id(),
            ));
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 500);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/(wp-diagnose|cms-diagnose)$#i', $path, $platformActionMatches) && $method === 'GET') {
        try {
            ce_json_response(ce_wp_diagnose_for_client($platformPdo, $platformActionMatches[1]));
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/integrations/status$#i', $path, $platformActionMatches) && $method === 'GET') {
        require_once __DIR__ . '/integrations-status-repository.php';
        try {
            $result = ce_integrations_status_for_client($platformPdo, $platformActionMatches[1]);
            ce_json_response([
                'ok' => true,
                'integrations' => $result['integrations'],
                'summary' => $result['summary'],
                'publishMode' => $result['publishMode'],
            ]);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/theme-refill$#i', $path, $platformActionMatches) && $method === 'POST') {
        ce_require_platform_auth($platformPdo, 'admin');
        try {
            ce_json_response(ce_platform_theme_refill($platformPdo, $platformActionMatches[1], ce_read_json_body()));
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/mailbox-sync$#i', $path, $platformActionMatches) && $method === 'POST') {
        try {
            ce_json_response(ce_platform_mailbox_sync($platformPdo, $platformActionMatches[1]));
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/scraper-sync$#i', $path, $platformActionMatches) && $method === 'POST') {
        try {
            ce_json_response(ce_platform_scraper_sync($platformPdo, $platformActionMatches[1]));
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/delete$#i', $path, $platformDeleteMatches) && $method === 'POST') {
        ce_require_platform_auth($platformPdo, 'admin');
        require_once __DIR__ . '/content-reset.php';
        require_once __DIR__ . '/account-repository.php';
        ce_require_destructive_confirmation('delete-client');
        try {
            ce_json_response(
                ce_platform_delete_tenant(
                    $platformPdo,
                    $platformDeleteMatches[1],
                    ce_read_json_body(),
                    ce_resolve_platform_user_id(),
                ),
            );
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (preg_match('#^/platform/tenants/([0-9a-f-]{36})/service/tickets$#i', $path, $platformServiceMatches) && $method === 'POST') {
        ce_require_platform_auth($platformPdo, 'admin');
        try {
            $ticket = ce_support_create_ticket(
                $platformPdo,
                $platformServiceMatches[1],
                ce_read_json_body(),
                ce_resolve_platform_user_id(),
            );
            ce_json_response(['ok' => true, 'ticket' => $ticket]);
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (
        preg_match('#^/platform/tenants/([0-9a-f-]{36})/service/tickets/([0-9a-f-]{36})$#i', $path, $platformServiceMatches)
        && $method === 'PATCH'
    ) {
        ce_require_platform_auth($platformPdo, 'admin');
        try {
            $ticket = ce_support_update_ticket(
                $platformPdo,
                $platformServiceMatches[1],
                $platformServiceMatches[2],
                ce_read_json_body(),
            );
            ce_json_response(['ok' => true, 'ticket' => $ticket]);
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (
        preg_match('#^/platform/tenants/([0-9a-f-]{36})/service/tickets/([0-9a-f-]{36})/reply$#i', $path, $platformServiceMatches)
        && $method === 'POST'
    ) {
        ce_require_platform_auth($platformPdo, 'admin');
        try {
            $platformUser = ce_platform_current_user($platformPdo);
            ce_json_response(ce_support_reply_ticket(
                $platformPdo,
                $platformServiceMatches[1],
                $platformServiceMatches[2],
                ce_read_json_body(),
                ce_resolve_platform_user_id(),
                $platformUser,
            ));
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if (
        preg_match('#^/platform/tenants/([0-9a-f-]{36})/service/messages/(\d+)$#i', $path, $platformServiceMatches)
        && $method === 'DELETE'
    ) {
        ce_require_platform_auth($platformPdo, 'admin');
        try {
            ce_support_redact_chat_message(
                $platformPdo,
                $platformServiceMatches[1],
                (int) $platformServiceMatches[2],
            );
            ce_json_response(['ok' => true]);
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 404);
        }
    }

    if ($path === '/platform/events' && $method === 'GET') {
        $eventFilters = [
            'severity' => (string) ($_GET['severity'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'clientId' => (string) ($_GET['client_id'] ?? ''),
            'days' => (int) ($_GET['days'] ?? 7),
            'limit' => (int) ($_GET['limit'] ?? 100),
        ];
        $events = ce_event_log_enrich(ce_fetch_events($platformPdo, $eventFilters));
        ce_json_response(['ok' => true, 'count' => count($events), 'events' => $events]);
    }

    if ($path === '/platform/support/import-emails' && $method === 'POST') {
        ce_require_platform_auth($platformPdo, 'admin');
        require_once __DIR__ . '/support-mail-import-repository.php';
        try {
            ce_json_response(ce_support_import_emails($platformPdo, ce_resolve_platform_user_id()));
        } catch (RuntimeException $e) {
            ce_json_error($e->getMessage(), 400);
        }
    }

    if ($path === '/platform/status' && $method === 'GET') {
        ce_json_response(ce_platform_fetch_status($platformPdo));
    }

    if ($path === '/platform/costs' && $method === 'GET') {
        try {
            $month = trim((string) ($_GET['month'] ?? date('Y-m')));
            ce_json_response(ce_platform_fetch_costs($platformPdo, $month));
        } catch (InvalidArgumentException $e) {
            ce_json_error($e->getMessage(), 400);
        }
    }

    if (str_starts_with($path, '/platform/hygiene')) {
        require_once __DIR__ . '/platform-hygiene.php';
        ce_platform_hygiene_handle($platformPdo, $path, $method, ce_read_json_body());
        exit;
    }

    ce_json_error('Not found', 404);
}

if ($path === '/testset-nulmeting/scores' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/testset-nulmeting-scores.php';
    exit;
}

if (!ce_api_path_is_public($path) && !$skipAuth) {
    ce_require_auth(ce_db());
}

if ($path === '/channel-constraints' && $method === 'GET') {
    require_once __DIR__ . '/channel-constraints.php';
    exit;
}

if ($path === '/health' && $method === 'GET') {
    require_once __DIR__ . '/health.php';
    exit;
}

if ($path === '/home' && $method === 'GET') {
    require_once __DIR__ . '/home.php';
    exit;
}

if ($path === '/support' && $method === 'GET') {
    require_once __DIR__ . '/support.php';
    exit;
}

if ($path === '/support-chat/escalate' && $method === 'POST') {
    require_once __DIR__ . '/support-chat-escalate.php';
    exit;
}

if ($path === '/support-chat' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/support-chat.php';
    exit;
}

if ($path === '/tenant-costs' && $method === 'GET') {
    require_once __DIR__ . '/tenant-costs.php';
    exit;
}

if (preg_match('#^/review/([a-f0-9]{64})#i', $path, $matches)) {
    $_GET['review_token'] = $matches[1];
    require_once __DIR__ . '/review.php';
    exit;
}

if ($path === '/agenda' && $method === 'GET') {
    require_once __DIR__ . '/agenda.php';
    exit;
}

if (preg_match('#^/agenda/([0-9a-f-]{36})/schedule$#i', $path, $scheduleMatches) && in_array($method, ['POST', 'PATCH'], true)) {
    $_GET['slot_id'] = $scheduleMatches[1];
    require_once __DIR__ . '/agenda-schedule.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/publications$#i', $path, $pubMatches) && in_array($method, ['GET', 'POST', 'PATCH'], true)) {
    $_GET['article_id'] = $pubMatches[1];
    require_once __DIR__ . '/publications.php';
    exit;
}

if (preg_match('#^/agenda/([0-9a-f-]{36})/archive#i', $path, $matches)) {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/agenda-archive.php';
    exit;
}

if (preg_match('#^/agenda/([0-9a-f-]{36})/delete#i', $path, $matches) && $method === 'POST') {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/agenda-delete.php';
    exit;
}

if (preg_match('#^/agenda/([0-9a-f-]{36})#i', $path, $matches) && $method === 'DELETE') {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/agenda-delete.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/status#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-status.php';
    exit;
}

if ($path === '/strategy' && in_array($method, ['GET', 'PATCH'], true)) {
    require_once __DIR__ . '/strategy.php';
    exit;
}

if ($path === '/hba/score' && $method === 'POST') {
    require_once __DIR__ . '/hba.php';
    exit;
}

if ($path === '/ce03/slot-meta' && $method === 'POST') {
    require_once __DIR__ . '/ce03-slot-meta.php';
    exit;
}

if (
    $path === '/hba/branches'
    || $path === '/hba/derive-architecture'
    || $path === '/hba/derive-branche'
    || $path === '/hba/architecture-check'
    || $path === '/hba/wizard-document'
    || $path === '/hba/calibration-pairs'
    || $path === '/hba/theme-verdicts'
    || $path === '/hba/research-verdicts'
    || $path === '/hba/progress'
    || $path === '/hba/architecture/complete'
    || preg_match('#^/hba/article-hints/#', $path)
) {
    require_once __DIR__ . '/hba-v2.php';
    exit;
}

if ($path === '/content-rhythm' && in_array($method, ['GET', 'POST', 'PATCH'], true)) {
    require_once __DIR__ . '/content-rhythm.php';
    exit;
}

if ($path === '/campagne-preview/templates' && $method === 'GET') {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/campagne-preview-templates.php';
    require_once __DIR__ . '/hba-v2-repository.php';
    $payload = ce_campagne_preview_templates_payload();
    $payload['sellFitHint'] = null;
    try {
        $pdo = ce_db();
        $payload['sellFitHint'] = ce_campagne_sell_fit_hint($pdo, ce_default_client_id($pdo));
    } catch (Throwable) {
        $payload['sellFitHint'] = null;
    }
    ce_json_response($payload);
    exit;
}

if ($path === '/campagne-preview' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/campagne-preview.php';
    exit;
}

if (preg_match('#^/campagne-preview/([0-9a-f-]{36})/product-extract$#i', $path, $campagneExtractMatches) && $method === 'POST') {
    $_GET['campaign_id'] = $campagneExtractMatches[1];
    require_once __DIR__ . '/campagne-preview-product-extract-route.php';
    exit;
}

if (preg_match('#^/campagne-preview/([0-9a-f-]{36})/plan-check$#i', $path, $campagnePlanCheckMatches) && $method === 'POST') {
    $_GET['campaign_id'] = $campagnePlanCheckMatches[1];
    require_once __DIR__ . '/campagne-preview-plan-check-route.php';
    exit;
}

if ($path === '/campagne-preview/bank' && in_array($method, ['GET', 'PATCH'], true)) {
    require_once __DIR__ . '/campagne-preview-bank-route.php';
    exit;
}

if ($path === '/campagne-preview/geo-questions' && $method === 'GET') {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/client-context.php';
    require_once __DIR__ . '/llm-repository.php';
    $pdo = ce_db();
    ce_json_response([
        'questions' => ce_fetch_llm_questions($pdo, ce_default_client_id($pdo), 'geo'),
    ]);
    exit;
}

if (preg_match('#^/campagne-preview/([0-9a-f-]{36})/ankers$#i', $path, $campagneAnkerMatches) && $method === 'POST') {
    $_GET['campaign_id'] = $campagneAnkerMatches[1];
    require_once __DIR__ . '/campagne-preview-ankers-route.php';
    exit;
}

if (preg_match('#^/campagne-preview/([0-9a-f-]{36})$#i', $path, $campagnePreviewMatches)) {
    $_GET['campaign_id'] = $campagnePreviewMatches[1];
    if (in_array($method, ['GET', 'PATCH'], true)) {
        require_once __DIR__ . '/campagne-preview-item.php';
        exit;
    }
}

if ($path === '/objectives' && in_array($method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
    require_once __DIR__ . '/objectives.php';
    exit;
}

if (preg_match('#^/objectives/([0-9a-f-]{36})#i', $path, $matches)) {
    $_GET['objective_id'] = $matches[1];
    require_once __DIR__ . '/objectives-item.php';
    exit;
}

if ($path === '/llm-visibility' && $method === 'GET') {
    require_once __DIR__ . '/llm-visibility.php';
    exit;
}

if ($path === '/llm-questions' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/llm-questions.php';
    exit;
}

if (preg_match('#^/llm-questions/([0-9a-f-]{36})#i', $path, $matches)) {
    $_GET['question_id'] = $matches[1];
    require_once __DIR__ . '/llm-questions-item.php';
    exit;
}

if ($path === '/themes' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/themes.php';
    exit;
}

if (($path === '/themes/generate' || $path === '/themes/generate-now') && $method === 'POST') {
    require_once __DIR__ . '/themes-generate.php';
    exit;
}

if (preg_match('#^/themes/([0-9a-f-]{36})/submit#i', $path, $matches)) {
    $_GET['theme_id'] = $matches[1];
    require_once __DIR__ . '/themes-submit.php';
    exit;
}

if (preg_match('#^/themes/([0-9a-f-]{36})/research/trigger#i', $path, $matches)) {
    $_GET['theme_id'] = $matches[1];
    require_once __DIR__ . '/themes-research-trigger.php';
    exit;
}

if (preg_match('#^/themes/([0-9a-f-]{36})#i', $path, $matches)) {
    $_GET['theme_id'] = $matches[1];
    require_once __DIR__ . '/themes-item.php';
    exit;
}

if (preg_match('#^/research/([0-9a-f-]{36})/submit#i', $path, $matches)) {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/research-submit.php';
    exit;
}

if ($path === '/research/pending' && $method === 'GET') {
    require_once __DIR__ . '/research-pending.php';
    exit;
}

if (preg_match('#^/research/([0-9a-f-]{36})/trigger#i', $path, $matches)) {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/research-trigger.php';
    exit;
}

if (preg_match('#^/research/([0-9a-f-]{36})$#i', $path, $matches)) {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/research.php';
    exit;
}

if (preg_match('#^/research-items/([0-9a-f-]{36})$#i', $path, $matches)) {
    $_GET['research_id'] = $matches[1];
    require_once __DIR__ . '/research-item.php';
    exit;
}

if ($path === '/sources/drive/oauth/status' && $method === 'GET') {
    require_once __DIR__ . '/sources-drive-oauth-status.php';
    exit;
}

if ($path === '/sources/drive/oauth/start' && $method === 'GET') {
    require_once __DIR__ . '/sources-drive-oauth-start.php';
    exit;
}

if ($path === '/sources/analytics/oauth/status' && $method === 'GET') {
    require_once __DIR__ . '/sources-analytics-oauth-status.php';
    exit;
}

if ($path === '/sources/analytics/oauth/start' && $method === 'GET') {
    require_once __DIR__ . '/sources-analytics-oauth-start.php';
    exit;
}

if ($path === '/sources/analytics/oauth/callback' && $method === 'GET') {
    require_once __DIR__ . '/sources-analytics-oauth-callback.php';
    exit;
}

if ($path === '/sources/calendar/oauth/status' && $method === 'GET') {
    require_once __DIR__ . '/sources-calendar-oauth-status.php';
    exit;
}

if ($path === '/sources/calendar/oauth/start' && $method === 'GET') {
    require_once __DIR__ . '/sources-calendar-oauth-start.php';
    exit;
}

if ($path === '/sources/calendar/oauth/callback' && $method === 'GET') {
    require_once __DIR__ . '/sources-calendar-oauth-callback.php';
    exit;
}

if ($path === '/sources/calendar/list' && $method === 'GET') {
    require_once __DIR__ . '/sources-calendar-list.php';
    exit;
}

if ($path === '/account/linkedin/oauth/status' && $method === 'GET') {
    require_once __DIR__ . '/account-linkedin-oauth-status.php';
    exit;
}

if ($path === '/account/linkedin/oauth/start' && $method === 'GET') {
    require_once __DIR__ . '/account-linkedin-oauth-start.php';
    exit;
}

if ($path === '/account/linkedin/oauth/callback' && $method === 'GET') {
    require_once __DIR__ . '/account-linkedin-oauth-callback.php';
    exit;
}

if ($path === '/account/linkedin/oauth/disconnect' && $method === 'POST') {
    require_once __DIR__ . '/account-linkedin-oauth-disconnect.php';
    exit;
}

if ($path === '/sources/inspiration' && $method === 'POST') {
    require_once __DIR__ . '/sources-inspiration.php';
    exit;
}

if ($path === '/sources' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/sources.php';
    exit;
}

if (preg_match('#^/sources/([0-9a-f-]{36})/check#i', $path, $matches)) {
    $_GET['source_id'] = $matches[1];
    require_once __DIR__ . '/sources-check.php';
    exit;
}

if (preg_match('#^/sources/([0-9a-f-]{36})/newsletters(?:/([0-9a-f-]{36}))?$#i', $path, $matches)) {
    $_GET['source_id'] = $matches[1];
    $_GET['newsletter_id'] = $matches[2] ?? '';
    require_once __DIR__ . '/sources-newsletters.php';
    exit;
}

if (preg_match('#^/sources/([0-9a-f-]{36})#i', $path, $matches)) {
    $_GET['source_id'] = $matches[1];
    require_once __DIR__ . '/sources-item.php';
    exit;
}

if ($path === '/reports/articles' && $method === 'GET') {
    require_once __DIR__ . '/reports-articles.php';
    exit;
}

if (preg_match('#^/reports/([0-9a-f-]{36})/pdf#i', $path, $matches)) {
    $_GET['report_id'] = $matches[1];
    require_once __DIR__ . '/reports-pdf.php';
    exit;
}

if (preg_match('#^/reports/([0-9a-f-]{36})$#i', $path, $matches)) {
    $_GET['report_id'] = $matches[1];
    require_once __DIR__ . '/reports-item.php';
    exit;
}

if ($path === '/reports' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/reports.php';
    exit;
}

if ($path === '/fastlane' && $method === 'POST') {
    require_once __DIR__ . '/fastlane.php';
    exit;
}

if ($path === '/account/logo' && $method === 'POST') {
    require_once __DIR__ . '/account-logo.php';
    exit;
}

if ($path === '/account' && in_array($method, ['GET', 'PATCH'], true)) {
    require_once __DIR__ . '/account.php';
    exit;
}

if ($path === '/integrations/status' && $method === 'GET') {
    require_once __DIR__ . '/integrations-status.php';
    exit;
}

if ($path === '/integrations/google/disconnect' && $method === 'POST') {
    require_once __DIR__ . '/integrations-google-disconnect.php';
    exit;
}

if ($path === '/wordpress/test' && $method === 'POST') {
    require_once __DIR__ . '/wordpress-test.php';
    exit;
}

if ($path === '/joomla/test' && $method === 'POST') {
    require_once __DIR__ . '/joomla-test.php';
    exit;
}

if ($path === '/images' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/images.php';
    exit;
}

if (preg_match('#^/images/([0-9a-f-]{36})$#i', $path, $matches)) {
    $_GET['image_id'] = $matches[1];
    require_once __DIR__ . '/images-item.php';
    exit;
}

if ($path === '/clients' && $method === 'GET') {
    require_once __DIR__ . '/clients.php';
    exit;
}

if (preg_match('#^/auth/(login|logout|session|forgot-password|reset-password)$#', $path) && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/auth.php';
    exit;
}

if ($path === '/archive/check-url' && $method === 'POST') {
    require_once __DIR__ . '/archive-check-url.php';
    exit;
}

if ($path === '/archive' && $method === 'GET') {
    require_once __DIR__ . '/archive.php';
    exit;
}

if (preg_match('#^/archive/([0-9a-f-]{36})#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/archive-item.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/image#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-image.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/publish-wp#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-publish-wp.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/review-edits/([0-9a-f-]{36})#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    $_GET['edit_id'] = $matches[2];
    require_once __DIR__ . '/article-review-edit.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/feedback/([0-9a-f]{32}|[0-9a-f-]{36})$#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    $_GET['feedback_id'] = $matches[2];
    require_once __DIR__ . '/article-feedback.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/submit#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-submit.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/rewrite#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-rewrite.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/reedit#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-reedit.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/soften-claim#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-soften-claim.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/substantiate-claim#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-substantiate-claim.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/claim-evidence#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-claim-evidence.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})/self-review/confirm-geen-bron#i', $path, $matches)) {
    require_once __DIR__ . '/article-repository.php';
    $_GET['slot_id'] = ce_resolve_article_route_slot_id(ce_db(), $matches[1]) ?? $matches[1];
    require_once __DIR__ . '/articles-self-review-confirm-geen-bron.php';
    exit;
}

if (preg_match('#^/articles/([0-9a-f-]{36})$#i', $path, $matches)) {
    $_GET['slot_id'] = $matches[1];
    require_once __DIR__ . '/articles.php';
    exit;
}

if ($path === '/admin/reset-content' && $method === 'POST') {
    require_once __DIR__ . '/admin-reset-content.php';
    exit;
}

if ($path === '/admin/set-password' && $method === 'POST') {
    require_once __DIR__ . '/admin-set-password.php';
    exit;
}

if ($path === '/admin/set-platform-password' && $method === 'POST') {
    require_once __DIR__ . '/admin-set-platform-password.php';
    exit;
}


if ($path === '/admin/wp-diagnose' && $method === 'GET') {
    require_once __DIR__ . '/admin-wp-diagnose.php';
    exit;
}

if ($path === '/admin/campagne-slot-titles-repair' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-campagne-slot-titles-repair.php';
    exit;
}

if ($path === '/admin/campagne-archive' && $method === 'POST') {
    require_once __DIR__ . '/admin-campagne-archive.php';
    exit;
}

if ($path === '/admin/debug-research' && $method === 'GET') {
    require_once __DIR__ . '/admin-debug-research.php';
    exit;
}




if ($path === '/admin/hygiene' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-hygiene.php';
    exit;
}














if ($path === '/admin/hba-article-hint-slot' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-hba-article-hint-slot.php';
    exit;
}



if ($path === '/admin/list-slots' && $method === 'GET') {
    require_once __DIR__ . '/admin-list-slots.php';
    exit;
}



if ($path === '/admin/schema-export' && $method === 'GET') {
    require_once __DIR__ . '/admin-schema-export.php';
    exit;
}

if ($path === '/admin/schema-migrations-init' && $method === 'POST') {
    require_once __DIR__ . '/admin-schema-migrations-init.php';
    exit;
}

if ($path === '/admin/run-migration' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-run-migration.php';
    exit;
}



if ($path === '/admin/events' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-events.php';
    exit;
}

if ($path === '/admin/prune-support-service' && $method === 'POST') {
    require_once __DIR__ . '/admin-prune-support-service.php';
    exit;
}

if ($path === '/admin/import-support-emails' && $method === 'POST') {
    require_once __DIR__ . '/admin-import-support-emails.php';
    exit;
}

if ($path === '/admin/poll-n8n-failures' && $method === 'POST') {
    require_once __DIR__ . '/admin-poll-n8n-failures.php';
    exit;
}

if ($path === '/admin/db-dump' && $method === 'GET') {
    require_once __DIR__ . '/admin-db-dump.php';
    exit;
}

if ($path === '/admin/wordpress-db' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-wordpress-db.php';
    exit;
}

if ($path === '/admin/agenda-repair' && $method === 'POST') {
    require_once __DIR__ . '/admin-agenda-repair.php';
    exit;
}

if ($path === '/admin/theme-refill' && $method === 'POST') {
    require_once __DIR__ . '/admin-theme-refill.php';
    exit;
}


if ($path === '/admin/create-client' && $method === 'POST') {
    require_once __DIR__ . '/admin-create-client.php';
    exit;
}

if ($path === '/admin/create-platform-user' && $method === 'POST') {
    require_once __DIR__ . '/admin-create-platform-user.php';
    exit;
}

if ($path === '/admin/mail-test' && $method === 'POST') {
    require_once __DIR__ . '/admin-mail-test.php';
    exit;
}

if ($path === '/admin/update-client' && $method === 'POST') {
    require_once __DIR__ . '/admin-update-client.php';
    exit;
}

if ($path === '/admin/export-tenant' && $method === 'GET') {
    require_once __DIR__ . '/admin-export-tenant.php';
    exit;
}

if ($path === '/admin/import-tenant' && $method === 'POST') {
    require_once __DIR__ . '/admin-import-tenant.php';
    exit;
}


if ($path === '/admin/reset-client-content' && $method === 'POST') {
    require_once __DIR__ . '/admin-reset-client-content.php';
    exit;
}

if ($path === '/admin/delete-client' && $method === 'POST') {
    require_once __DIR__ . '/admin-delete-client.php';
    exit;
}

if ($path === '/admin/delete-asana-sources' && $method === 'POST') {
    require_once __DIR__ . '/admin-delete-asana-sources.php';
    exit;
}


if ($path === '/admin/mailbox-sync' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-mailbox-sync.php';
    exit;
}

if ($path === '/admin/analytics-sync' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-analytics-sync.php';
    exit;
}

if ($path === '/admin/scraper-sync' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-scraper-sync.php';
    exit;
}

if ($path === '/admin/llm-visibility-sync' && in_array($method, ['GET', 'POST'], true)) {
    require_once __DIR__ . '/admin-llm-visibility-sync.php';
    exit;
}

if ($path === '/admin/ce02-trigger' && $method === 'POST') {
    require_once __DIR__ . '/content-reset.php';
    require_once __DIR__ . '/auth-guard.php';
    require_once __DIR__ . '/theme-repository.php';
    require_once __DIR__ . '/client-context.php';
    if (!ce_reset_token_valid()) {
        ce_require_auth(ce_db());
    }
    $raw = file_get_contents('php://input');
    $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($body)) {
        $body = [];
    }
    $themeId = trim((string) ($body['themeId'] ?? $body['theme_id'] ?? ''));
    $slotId = trim((string) ($body['slotId'] ?? $body['slot_id'] ?? ''));
    ce_json_response(ce_trigger_ce02_research(
        $themeId !== '' ? $themeId : null,
        $slotId !== '' ? $slotId : null,
    ));
}

ce_json_error('Not found', 404);
