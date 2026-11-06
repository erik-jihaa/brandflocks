<?php

declare(strict_types=1);

/** Rule-based oplossingsvoorstel bij event_log-regels (platform logboek). */
function ce_event_log_suggestion(array $event): ?string
{
    $name = strtolower(trim((string) ($event['event'] ?? '')));
    $category = strtolower(trim((string) ($event['category'] ?? '')));
    $message = strtolower(trim((string) ($event['message'] ?? '')));
    $context = is_array($event['context'] ?? null) ? $event['context'] : [];

    if ($name === 'mailbox_sync_failed' || ($category === 'mailbox' && str_contains($message, 'imap'))) {
        return 'Controleer IMAP-host, e-mailadres en app-wachtwoord in Sources → Mailbox. Draai daarna Mailbox-sync opnieuw via Platform → tenant.';
    }

    if ($name === 'wp_publish_failed' || (str_contains($name, 'publish') && str_contains($message, 'wordpress'))) {
        return 'Verifieer WordPress site-URL, gebruikersnaam en application password in Account/Integrations. Gebruik CMS-diagnose op de tenant-pagina.';
    }

    if ($name === 'joomla_publish_failed' || (str_contains($name, 'publish') && str_contains($message, 'joomla'))) {
        return 'Controleer Joomla API-token en site-URL in Integrations. Test via CMS-diagnose op de tenant-pagina.';
    }

    if ($name === 'linkedin_publish_failed' || ($category === 'publish' && str_contains($message, 'linkedin'))) {
        return 'Laat de klant LinkedIn opnieuw koppelen via Integrations. Controleer of het token nog geldig is (oauth_expired).';
    }

    if ($name === 'static_site_publish_failed') {
        return 'Controleer static-site FTP/SFTP-gegevens en pad in Account. Test publish handmatig vanuit het artikel.';
    }

    if ($name === 'oauth_expired' || $name === 'oauth_refresh_failed') {
        return 'LinkedIn OAuth opnieuw autoriseren: tenant → Integrations → LinkedIn opnieuw koppelen.';
    }

    if ($name === 'scraper_token_missing') {
        return 'Zet APIFY_API_TOKEN in server-config (.env / ce.local.php) en deploy opnieuw.';
    }

    if ($name === 'scraper_failed' || $name === 'scraper_budget_exceeded') {
        $sourceId = (string) ($context['sourceId'] ?? $context['source_id'] ?? '');
        $hint = $sourceId !== '' ? " Bron {$sourceId}:" : '';
        return "Controleer scraper-bron en Apify-token in Sources.{$hint} Draai Scraper-sync via Platform → tenant.";
    }

    if ($name === 'n8n_webhook_failed' || $name === 'n8n_execution_failed') {
        $workflow = (string) ($context['workflow'] ?? $context['webhook'] ?? '');
        $wf = $workflow !== '' ? " ({$workflow})" : '';
        return "Controleer n8n-workflow status en webhook-URL in ce.local.php{$wf}. Poll failures via admin/cron indien nodig.";
    }

    if ($name === 'migration_failed') {
        return 'Open Platform → Settings voor migratiestatus. Draai `./scripts/run-migration.sh production --all` of los SQL-fout handmatig op.';
    }

    if ($name === 'chat_escalated') {
        return 'Support-chat is doorgestuurd naar support@. Behandel via Service-tickets of importeer support-mails.';
    }

    if ($category === 'auth' && str_contains($message, 'destructive')) {
        return 'Geen actie — destructieve call geblokkeerd zonder bevestiging (verwacht gedrag).';
    }

    if (($event['severity'] ?? '') === 'error') {
        if (str_contains($message, '503') || str_contains($message, 'database')) {
            return 'Controleer DB-verbinding (db.local.php) en serverstatus. Bekijk /api/health.';
        }
        if ($message !== '') {
            return 'Los de melding hierboven op via de tenant-pagina (logboek). Draai indien nodig de betreffende sync opnieuw.';
        }
    }

    return null;
}

/** @param list<array<string, mixed>> $events @return list<array<string, mixed>> */
function ce_event_log_enrich(array $events): array
{
    return array_map(static function (array $event): array {
        $suggestion = ce_event_log_suggestion($event);
        if ($suggestion !== null) {
            $event['suggestion'] = $suggestion;
        }

        return $event;
    }, $events);
}
