<?php

declare(strict_types=1);

/**
 * Per-kanaal schrijfinstructies (addendum §3) — key: platform_db|textvorm.
 *
 * @return array<string, string>
 */
function ce_channel_copy_constraint_catalog(): array
{
    return [
        'blog|artikel' => 'Website-artikel: beantwoord één duidelijke lezersvraag; kernantwoord vroeg; '
            . 'beschrijvende tussenkoppen; zoektermen natuurlijk; eventueel inhoudelijke vervolgstap.',
        'linkedin|post' => 'LinkedIn-post: open met professioneel relevante observatie of stelling; '
            . 'één kerninzicht met onderbouwing; 0–3 relevante hashtags; max. 3.000 tekens; CTA optioneel.',
        'linkedin|artikel' => 'LinkedIn-artikel: verdiep één onderwerp vanuit eigen perspectief; '
            . 'titel, tussenkoppen, concrete argumenten; 500–1.000 woorden (langer indien nodig).',
        'linkedin|promotie' => 'LinkedIn-promotie bij artikel: leid af van het artikel; behoud feiten en nuance; '
            . '80–150 woorden; leesuitnodiging; 0–3 hashtags.',
        'x|post' => 'X-post: één directe gedachte; standpunt voorop; eventueel korte CTA; max. 280 tekens; 0–2 hashtags.',
        'instagram|bijschrift' => 'Instagram-bijschrift: betekenis bij beeld; kern of herkenbare situatie; '
            . '50–150 woorden; max. 2.200 tekens; max. 5 hashtags.',
        'instagram|carrousel' => 'Instagram-carrousel: eerste slide = inhoudelijke belofte; één punt per slide; kort per slide.',
        'facebook|post' => 'Facebook-post: nieuws of herkenbare situatie; betekenis concreet; 50–150 woorden; 0–2 hashtags.',
        'tiktok|videoscript' => 'TikTok: script start met probleem/resultaat; één onderwerp in spreektaal; '
            . 'bijschrift 20–60 woorden; 2–3 hashtags.',
        'youtube|videoscript' => 'YouTube: titel en opening dezelfde belofte; logische stappen; beschrijving begin met opbrengst; '
            . 'titel max. 100 tekens; 0–3 hashtags.',
        'newsletter|editie' => 'Nieuwsbrief: één centrale belofte; onderwerp + preview vullen elkaar aan; 300–700 woorden; geen hashtags.',
        'threads|post' => 'Threads: één observatie; context genoeg om zelfstandig te begrijpen; max. 500 tekens; max. 1 tag.',
        'bluesky|post' => 'Bluesky: compact inzicht met context; max. 300 tekens; 0–2 hashtags.',
    ];
}

function ce_default_textvorm_for_platform(string $platformDb): string
{
    $p = strtolower(trim($platformDb));

    return match ($p) {
        'blog' => 'artikel',
        'linkedin' => 'post',
        'instagram' => 'bijschrift',
        'x', 'twitter' => 'post',
        'facebook' => 'post',
        'tiktok' => 'videoscript',
        'youtube' => 'videoscript',
        'newsletter' => 'editie',
        'threads' => 'post',
        'bluesky' => 'post',
        default => 'post',
    };
}

function ce_normalize_textvorm(string $raw): string
{
    $v = strtolower(trim($raw));
    $aliases = [
        'website' => 'artikel',
        'caption' => 'bijschrift',
        'carousel' => 'carrousel',
        'promo' => 'promotie',
        'promotiepost' => 'promotie',
    ];

    return $aliases[$v] ?? $v;
}

function ce_channel_copy_constraint_line(string $platformDb, ?string $textvorm = null): string
{
    $platform = strtolower(trim($platformDb));
    if ($platform === '') {
        return '';
    }
    $form = ce_normalize_textvorm($textvorm ?? ce_default_textvorm_for_platform($platform));
    $catalog = ce_channel_copy_constraint_catalog();
    $key = $platform . '|' . $form;
    if (isset($catalog[$key])) {
        return 'Kanaaleis (' . $platform . ' / ' . $form . '): ' . $catalog[$key];
    }
    $fallback = $catalog[$platform . '|post'] ?? $catalog['linkedin|post'] ?? '';

    return $fallback !== '' ? 'Kanaaleis: ' . $fallback : '';
}

function ce_ensure_article_textvorm_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked || !ce_table_exists($pdo, 'articles')) {
        $checked = true;

        return;
    }
    $checked = true;
    if (!ce_table_has_column($pdo, 'articles', 'content_textvorm')) {
        try {
            $pdo->exec('ALTER TABLE articles ADD COLUMN content_textvorm VARCHAR(32) NULL AFTER platform_final');
            unset($GLOBALS['ce_table_columns']['articles']);
        } catch (Throwable) {
            // bestaat al
        }
    }
}

function ce_resolve_article_textvorm(PDO $pdo, array $articleRow): string
{
    ce_ensure_article_textvorm_column($pdo);
    $stored = trim((string) ($articleRow['content_textvorm'] ?? ''));
    if ($stored !== '') {
        return ce_normalize_textvorm($stored);
    }
    require_once __DIR__ . '/ce03-meta-repository.php';
    $platformDb = ce_ce03_resolve_platform_intended_db(
        $pdo,
        $articleRow,
        trim((string) ($articleRow['client_id'] ?? '')),
    );

    return ce_default_textvorm_for_platform($platformDb);
}
