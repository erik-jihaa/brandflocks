<?php

declare(strict_types=1);

require_once __DIR__ . '/mappers.php';
require_once __DIR__ . '/account-repository.php';
require_once __DIR__ . '/nl-writing-style.php';

/** @return array<string, string> db-key => NL kanaalnaam voor de prompt */
function ce_ce03_platform_labels(): array
{
    return [
        'linkedin' => 'LinkedIn',
        'blog' => 'blog',
        'instagram' => 'Instagram',
        'x' => 'X',
        'facebook' => 'Facebook',
    ];
}

/** Drie regels vorm per platform — geen volledige stijlgids. */
function ce_ce03_platform_form_guidelines(string $platformDb): string
{
    $key = strtolower(trim($platformDb));

    return match ($key) {
        'blog' => "Vorm blog:\n"
            . "- Lengte circa 800–1200 woorden; meerdere alinea's met tussenkoppen.\n"
            . "- Tussenkoppen als platte regel — geen markdown (# of ##).\n"
            . "- Opbouw: hook, context, argument, concreet voorbeeld, afsluiting.\n"
            . "- Geen LinkedIn-fragmenten of emoji-bait.",
        'linkedin' => "Vorm LinkedIn:\n"
            . "- Lengte circa 120–220 woorden; korte alinea's (1–3 zinnen).\n"
            . "- Eerste regel is de hook; optioneel één vraag onderaan — geen clickbait.\n"
            . "- Geen tussenkoppen en geen markdown-koppen (geen regels met ##).\n"
            . "- Wel witregels tussen alinea's.",
        'instagram' => "Vorm Instagram:\n"
            . "- Lengte circa 80–150 woorden; scanbaar in korte alinea's.\n"
            . "- Eerste zin pakt aandacht; maximaal één duidelijke CTA.\n"
            . "- Geen lange uitlegblokken of blog-tussenkoppen.",
        'x' => "Vorm X:\n"
            . "- Lengte circa 40–120 woorden; één kernpunt per post.\n"
            . "- Scherp en direct; geen blog-opbouw of tussenkoppen.\n"
            . "- Geen hashtag-spam.",
        'facebook' => "Vorm Facebook:\n"
            . "- Lengte circa 150–300 woorden; informele alinea's.\n"
            . "- Persoonlijk of community-gericht; één duidelijke boodschap.\n"
            . "- Geen corporate whitepaper-toon.",
        default => "Vorm {$key}:\n"
            . "- Lengte en opbouw passend bij het kanaal.\n"
            . "- Menselijk en concreet.\n"
            . "- Geen copy-paste van andere kanalen.",
    };
}

/**
 * Platform vóór stap B: article platform_final → platform_suggested → client default.
 */
function ce_ce03_resolve_platform_intended_db(PDO $pdo, array $articleRow, string $clientId): string
{
    foreach (['platform_final', 'platform_suggested'] as $col) {
        $raw = trim((string) ($articleRow[$col] ?? ''));
        if ($raw !== '') {
            return ce_platform_to_db(ce_platform_to_ui($raw));
        }
    }

    if ($clientId !== '' && ce_table_exists($pdo, 'clients')) {
        $stmt = $pdo->prepare('SELECT default_platform FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clientId]);
        $default = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($default !== '') {
            return ce_platform_to_db(ce_platform_to_ui($default));
        }
    }

    return 'linkedin';
}

/**
 * GRENZEN-blok voor CE-03 stap B — bij actieve meetlat (of complete narrative).
 */
function ce_ce03_grenzen_verboden_list(array $narrative): array
{
    $verboden = ce_hba_decode_json_list($narrative['verboden_logica'] ?? []);
    if ($verboden !== []) {
        return $verboden;
    }
    $wat = trim((string) ($narrative['wat_bewust_niet'] ?? ''));
    if ($wat !== '') {
        return [$wat];
    }

    return [];
}

function ce_ce03_grenzen_fields_usable(array $narrative): bool
{
    if (trim((string) ($narrative['mechanisme'] ?? '')) === '') {
        return false;
    }
    if (trim((string) ($narrative['tone_of_behaviour'] ?? '')) === '') {
        return false;
    }
    if (ce_ce03_grenzen_verboden_list($narrative) === []) {
        return false;
    }

    return true;
}

function ce_ce03_build_grenzen_block(PDO $pdo, string $clientId): string
{
    if ($clientId === '') {
        return '';
    }

    require_once __DIR__ . '/hba-repository.php';
    require_once __DIR__ . '/hba-identity-gate.php';

    $meetlatActive = ce_hba_active_guardrail($pdo, $clientId) !== null;
    if (!$meetlatActive && !ce_brand_narrative_is_complete($pdo, $clientId)) {
        return '';
    }

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative || !ce_ce03_grenzen_fields_usable($narrative)) {
        return '';
    }

    $lines = ['GRENZEN:'];
    $mechanisme = trim((string) ($narrative['mechanisme'] ?? ''));
    $lines[] = 'Mechanisme (redenering waar de tekst langs moet lopen): ' . $mechanisme;

    $verboden = ce_ce03_grenzen_verboden_list($narrative);
    $lines[] = 'Verboden logica (nooit deze redeneringen): ' . implode('; ', $verboden);

    $woordenNiet = ce_hba_decode_json_list($narrative['woorden_die_niet_passen'] ?? []);
    if ($woordenNiet !== []) {
        $lines[] = 'Woorden die niet passen (nooit gebruiken): ' . implode(', ', $woordenNiet);
    }

    $tone = trim((string) ($narrative['tone_of_behaviour'] ?? ''));
    $lines[] = 'Tone of behaviour: ' . $tone;

    $kern = ce_hba_decode_json_list($narrative['kernformuleringen'] ?? []);
    if ($kern !== []) {
        $lines[] = 'Kernformuleringen (verwerk er één waar het natuurlijk past — eigen woorden of letterlijk; niet forceren):';
        foreach ($kern as $i => $line) {
            $lines[] = ($i + 1) . '. ' . $line;
        }
    }

    return count($lines) > 1 ? implode("\n", $lines) : '';
}

function ce_ce03_aanspreekvorm_instruction(string $vorm): string
{
    require_once __DIR__ . '/hba-hint-verificatie.php';
    $normalized = ce_hba_normalize_aanspreekvorm($vorm);
    if ($normalized === 'u') {
        return 'AANSPREEKVORM (verplicht): spreek de lezer aan met u/uw. Gebruik geen je, jij, jou of jouw.';
    }
    if ($normalized === 'je') {
        return 'AANSPREEKVORM (verplicht): spreek de lezer aan met je/jij/jou/jouw. Gebruik geen u, uw of uwe.';
    }

    return '';
}

function ce_ce03_build_aanspreekvorm_block(PDO $pdo, string $clientId): string
{
    if ($clientId === '') {
        return '';
    }

    require_once __DIR__ . '/hba-repository.php';
    require_once __DIR__ . '/hba-hint-verificatie.php';

    $narrative = ce_fetch_current_brand_narrative($pdo, $clientId);
    if (!$narrative) {
        return '';
    }

    return ce_ce03_aanspreekvorm_instruction((string) ($narrative['aanspreekvorm'] ?? ''));
}

/** @return array<string, string> */
function ce_ce03_platform_meta(PDO $pdo, array $articleRow, string $clientId): array
{
    $platformDb = ce_ce03_resolve_platform_intended_db($pdo, $articleRow, $clientId);
    $labels = ce_ce03_platform_labels();
    $aanspreekvormBlock = ce_ce03_build_aanspreekvorm_block($pdo, $clientId);

    require_once __DIR__ . '/hba-repository.php';
    require_once __DIR__ . '/hba-hint-verificatie.php';
    $narrative = $clientId !== '' ? ce_fetch_current_brand_narrative($pdo, $clientId) : null;
    $aanspreekvorm = $narrative
        ? ce_hba_normalize_aanspreekvorm((string) ($narrative['aanspreekvorm'] ?? ''))
        : '';

    return [
        'platform_intended' => $platformDb,
        'platform_label' => $labels[$platformDb] ?? ucfirst($platformDb),
        'platform_form_guidelines' => ce_ce03_platform_form_guidelines($platformDb),
        'grenzen_block' => ce_ce03_build_grenzen_block($pdo, $clientId),
        'nl_writing_style_block' => ce_nl_avoid_calques_block(),
        'aanspreekvorm' => $aanspreekvorm,
        'aanspreekvorm_block' => $aanspreekvormBlock,
    ];
}
