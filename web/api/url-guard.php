<?php

declare(strict_types=1);

/**
 * Guard voor URL's die een tenant zelf invoert (bronnen, scrapers, feeds).
 * Blokkeert alles wat naar het interne netwerk of de metadata-service wijst,
 * zodat een bron geen intern adres kan uitlezen (SSRF).
 */

/** Alleen publiek routeerbare adressen zijn toegestaan. */
function ce_ip_is_public(string $ip): bool
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
    ) !== false;
}

/**
 * Alle adressen waar deze hostnaam naar wijst.
 *
 * @return list<string>
 */
function ce_resolve_host_ips(string $host): array
{
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return [$host];
    }

    $ips = [];

    foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA] as $key => $type) {
        $records = @dns_get_record($host, $type);
        if (!is_array($records)) {
            continue;
        }
        foreach ($records as $record) {
            $ip = trim((string) ($record[$key === 'A' ? 'ip' : 'ipv6'] ?? ''));
            if ($ip !== '') {
                $ips[] = $ip;
            }
        }
    }

    if ($ips === []) {
        $resolved = gethostbyname($host);
        if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP) !== false) {
            $ips[] = $resolved;
        }
    }

    return array_values(array_unique($ips));
}

/**
 * Toetst één URL. Geeft de reden terug als hij geweigerd wordt.
 *
 * @return array{ok: bool, reason: string}
 */
function ce_url_is_fetchable(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['ok' => false, 'reason' => 'Lege URL'];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return ['ok' => false, 'reason' => 'Onleesbare URL'];
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'reason' => 'Alleen http en https zijn toegestaan'];
    }

    // Inloggegevens in de URL zijn een bekende manier om een parser om de tuin te leiden.
    if (isset($parts['user']) || isset($parts['pass'])) {
        return ['ok' => false, 'reason' => 'Inloggegevens in de URL zijn niet toegestaan'];
    }

    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    if ($host === '') {
        return ['ok' => false, 'reason' => 'URL zonder host'];
    }

    // IPv6 komt uit parse_url met blokhaken.
    $host = trim($host, '[]');

    $ips = ce_resolve_host_ips($host);
    if ($ips === []) {
        return ['ok' => false, 'reason' => 'Hostnaam is niet te herleiden'];
    }

    foreach ($ips as $ip) {
        if (!ce_ip_is_public($ip)) {
            return ['ok' => false, 'reason' => 'Intern adres is niet toegestaan'];
        }
    }

    return ['ok' => true, 'reason' => ''];
}

function ce_url_is_safe(string $url): bool
{
    return ce_url_is_fetchable($url)['ok'];
}

/** Maakt van een Location-header een absolute URL. */
function ce_url_resolve_redirect(string $base, string $location): string
{
    $location = trim($location);
    if ($location === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $location)) {
        return $location;
    }

    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }

    $dir = isset($parts['path']) ? rtrim(dirname($parts['path']), '/') : '';

    return $origin . $dir . '/' . $location;
}
