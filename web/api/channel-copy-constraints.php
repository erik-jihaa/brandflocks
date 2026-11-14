<?php

declare(strict_types=1);

/**
 * Per-kanaal schrijfinstructies (addendum §3) en harde grenzen (tekens, hashtags).
 *
 * @return array<string, string>
 */
function ce_channel_copy_constraint_catalog(): array
{
  return [
    'blog|artikel' => 'Beantwoord één duidelijke lezersvraag en geef het kernantwoord vroeg. Werk de uitleg uit met beschrijvende tussenkoppen en relevante onderbouwing. Gebruik zoektermen natuurlijk. Sluit eventueel af met een inhoudelijk passende vervolgstap.',
    'linkedin|post' => 'Open met een professioneel relevante observatie, stelling of situatie. Werk één kerninzicht uit met concrete onderbouwing, zodat de post zelfstandig waarde biedt. Schrijf in samenhangende, goed leesbare alinea\'s. Een CTA is optioneel. Maximaal 3.000 tekens; gebruik 0–3 relevante hashtags.',
    'linkedin|artikel' => 'Verdiep één professioneel relevant onderwerp vanuit een duidelijk eigen perspectief. Gebruik een informatieve titel, tussenkoppen en concrete argumenten of voorbeelden. Werk toe naar een onderbouwde conclusie. Richtlengte: 500–1.000 woorden; langer als de inhoud dat vraagt.',
    'linkedin|promotie' => 'Leid de post af van het artikel: behoud de hoofdgedachte, feiten en nuance. Open met de inhoudelijke aanleiding en geef het kerninzicht kort weer. Maak duidelijk welke verdieping het artikel biedt en eindig met een leesuitnodiging. Richtlengte: 80–150 woorden; maximaal 3.000 tekens; 0–3 relevante hashtags.',
    'x|post' => 'Formuleer één direct begrijpelijke gedachte. Zet het standpunt of nieuws voorop en voeg alleen noodzakelijke context toe. Gebruik eventueel één korte CTA. Maximaal 280 tekens inclusief linkruimte; 0–2 relevante hashtags.',
    'instagram|bijschrift' => 'Laat de tekst betekenis toevoegen aan het beeld of de video. Begin met de kern of een herkenbare situatie. Gebruik een concrete, toegankelijke formulering. Kies eventueel één passende actie, zoals bewaren of een profielkoppeling bezoeken. Richtlengte: 50–150 woorden; maximaal 2.200 tekens en 5 hashtags.',
    'instagram|carrousel' => 'Formuleer op de eerste slide een duidelijke inhoudelijke belofte. Werk die stapsgewijs uit met één punt per slide. Houd de tekst per slide kort en zelfstandig begrijpelijk. Eindig met de conclusie of één passende CTA.',
    'facebook|post' => 'Begin met het nieuws of een herkenbare situatie en maak de betekenis voor de lezer concreet. Schrijf toegankelijk en zet noodzakelijke praktische informatie direct in de tekst. Gebruik eventueel één duidelijke CTA. Richtlengte: 50–150 woorden; 0–2 relevante hashtags.',
    'tiktok|videoscript' => 'Begin het script direct met het probleem, resultaat of de ontdekking. Behandel één onderwerp in natuurlijke spreektaal en schrijf naar een concrete uitkomst toe. Gebruik in het bijschrift een korte, duidelijke omschrijving van het onderwerp. Bijschrift: 20–60 woorden en 2–3 relevante hashtags.',
    'youtube|videoscript' => 'Laat de titel en opening dezelfde concrete belofte doen. Bouw het script op in logische stappen en vermijd een lange introductie. Benoem in de eerste twee zinnen van de beschrijving onderwerp en opbrengst. Titel maximaal 100 tekens; beschrijving maximaal 5.000 tekens; 0–3 hashtags.',
    'newsletter|editie' => 'Geef de editie één centrale inhoudelijke belofte. Laat onderwerpregel en previewtekst elkaar aanvullen. Schrijf direct aan de lezer en werk toe naar één primaire vervolgstap. Richtlengte: 300–700 woorden; geen hashtags.',
    'threads|post' => 'Deel één heldere observatie of gedachte in een natuurlijke, directe stijl. Geef voldoende context om de tekst zelfstandig te begrijpen. Een CTA is optioneel. Maximaal 500 tekens; maximaal 1 onderwerptag.',
    'bluesky|post' => 'Formuleer één compact inzicht met noodzakelijke context. Geef bij een bronlink kort aan wat de bron inhoudelijk toevoegt. Vermijd een uitgebreide promotionele afsluiting. Maximaal 300 tekens; 0–2 relevante hashtags.',
  ];
}

/** @return array<string, string> platform|textvorm → leeslabel */
function ce_channel_copy_constraint_labels(): array
{
  return [
    'blog|artikel' => 'Website / artikel',
    'linkedin|post' => 'LinkedIn / zelfstandige post',
    'linkedin|artikel' => 'LinkedIn / artikel',
    'linkedin|promotie' => 'LinkedIn / promotiepost bij artikel',
    'x|post' => 'X / post',
    'instagram|bijschrift' => 'Instagram / bijschrift',
    'instagram|carrousel' => 'Instagram / carrouseltekst',
    'facebook|post' => 'Facebook / post',
    'tiktok|videoscript' => 'TikTok / videoscript en bijschrift',
    'youtube|videoscript' => 'YouTube / videoscript, titel en beschrijving',
    'newsletter|editie' => 'Nieuwsbrief',
    'threads|post' => 'Threads / post',
    'bluesky|post' => 'Bluesky / post',
  ];
}

/**
 * Harde grenzen (addendum §3, §7 punt 5) — geen AI.
 *
 * @return array<string, array{
 *   max_combined_chars?: int|null,
 *   max_body_chars?: int|null,
 *   max_headline_chars?: int|null,
 *   max_hashtags?: int|null
 * }>
 */
function ce_channel_hard_limits_catalog(): array
{
  return [
    'blog|artikel' => ['max_hashtags' => 0],
    'linkedin|post' => ['max_body_chars' => 3000, 'max_hashtags' => 3],
    'linkedin|promotie' => ['max_body_chars' => 3000, 'max_hashtags' => 3],
    'x|post' => ['max_combined_chars' => 280, 'max_hashtags' => 2],
    'instagram|bijschrift' => ['max_body_chars' => 2200, 'max_hashtags' => 5],
    'facebook|post' => ['max_hashtags' => 2],
    'tiktok|videoscript' => ['max_hashtags' => 3],
    'youtube|videoscript' => ['max_headline_chars' => 100, 'max_body_chars' => 5000, 'max_hashtags' => 3],
    'newsletter|editie' => ['max_hashtags' => 0],
    'threads|post' => ['max_combined_chars' => 500, 'max_hashtags' => 1],
    'bluesky|post' => ['max_combined_chars' => 300, 'max_hashtags' => 2],
  ];
}

/** @return list<string> DB-sleutels (gekozen tekstvorm) per platform. */
function ce_textvorm_options_for_platform(string $platformDb): array
{
    $p = strtolower(trim($platformDb));

    return match ($p) {
        'blog' => ['artikel'],
        'linkedin' => ['post', 'artikel', 'promotie'],
        'instagram' => ['bijschrift', 'carrousel'],
        'x', 'twitter', 'threads', 'bluesky', 'facebook' => ['post'],
        'tiktok', 'youtube' => ['videoscript'],
        'newsletter' => ['editie'],
        default => ['post'],
    };
}

function ce_validate_textvorm_for_platform(string $platformDb, string $textvorm): string
{
    $norm = ce_normalize_textvorm($textvorm);
    $allowed = ce_textvorm_options_for_platform($platformDb);

    return in_array($norm, $allowed, true)
        ? $norm
        : ce_default_textvorm_for_platform($platformDb);
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

function ce_channel_constraint_key(string $platformDb, string $textvorm): string
{
  return strtolower(trim($platformDb)) . '|' . ce_normalize_textvorm($textvorm);
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
  if (!isset($catalog[$key])) {
    $fallbackKey = $platform . '|' . ce_default_textvorm_for_platform($platform);
    if (!isset($catalog[$fallbackKey])) {
      return '';
    }
    $key = $fallbackKey;
    $form = ce_normalize_textvorm(ce_default_textvorm_for_platform($platform));
  }
  $labels = ce_channel_copy_constraint_labels();
  $label = $labels[$key] ?? ($platform . ' / ' . $form);

  return 'Kanaaleis (' . $label . '): ' . $catalog[$key];
}

function ce_count_hashtag_tokens(string $raw): int
{
  $raw = trim($raw);
  if ($raw === '') {
    return 0;
  }
  if (preg_match_all('/#[\p{L}\p{N}_]+/u', $raw, $matches) && $matches[0] !== []) {
    return count($matches[0]);
  }

  return count(array_filter(preg_split('/\s+/u', $raw) ?: [], static fn (string $t): bool => trim($t) !== ''));
}

function ce_channel_post_combined_text(string $headline, string $body, string $hashtags): string
{
  $parts = array_filter([trim($headline), trim($body), trim($hashtags)], static fn (string $p): bool => $p !== '');

  return implode("\n\n", $parts);
}

/**
 * @return list<string> Klanttaal — geen veldnamen of HBA-termen.
 */
function ce_channel_hard_validation_messages(
  string $platformDb,
  ?string $textvorm,
  string $headline,
  string $body,
  string $hashtags,
): array {
  $platform = strtolower(trim($platformDb));
  if ($platform === '') {
    return [];
  }
  $form = ce_normalize_textvorm($textvorm ?? ce_default_textvorm_for_platform($platform));
  $key = ce_channel_constraint_key($platform, $form);
  $limits = ce_channel_hard_limits_catalog()[$key]
    ?? ce_channel_hard_limits_catalog()[$platform . '|' . ce_default_textvorm_for_platform($platform)]
    ?? null;
  if ($limits === null) {
    return [];
  }

  $messages = [];
  $headline = trim($headline);
  $body = trim($body);
  $hashtags = trim($hashtags);
  $combined = ce_channel_post_combined_text($headline, $body, $hashtags);

  if (isset($limits['max_headline_chars']) && $limits['max_headline_chars'] !== null) {
    $len = mb_strlen($headline);
    $max = (int) $limits['max_headline_chars'];
    if ($len > $max) {
      $messages[] = "Je titel is langer dan wat voor dit kanaal past (maximaal {$max} tekens).";
    }
  }
  if (isset($limits['max_body_chars']) && $limits['max_body_chars'] !== null) {
    $len = mb_strlen($body);
    $max = (int) $limits['max_body_chars'];
    if ($len > $max) {
      $messages[] = "Je tekst is langer dan wat voor dit kanaal past (maximaal {$max} tekens).";
    }
  }
  if (isset($limits['max_combined_chars']) && $limits['max_combined_chars'] !== null) {
    $len = mb_strlen($combined);
    $max = (int) $limits['max_combined_chars'];
    if ($len > $max) {
      $messages[] = "Je post is langer dan wat voor dit kanaal past (maximaal {$max} tekens).";
    }
  }
  if (isset($limits['max_hashtags']) && $limits['max_hashtags'] !== null) {
    $count = ce_count_hashtag_tokens($hashtags);
    $max = (int) $limits['max_hashtags'];
    if ($count > $max) {
      if ($max === 0) {
        $messages[] = 'Voor dit kanaal horen geen hashtags bij de post.';
      } else {
        $messages[] = "Je gebruikt meer hashtags dan voor dit kanaal bedoeld zijn (maximaal {$max}).";
      }
    }
  }

  return $messages;
}

function ce_channel_hard_validation_error_message(array $messages): string
{
  if ($messages === []) {
    return '';
  }

  return implode(' ', $messages);
}

/** @return list<string> */
function ce_channel_hard_validation_for_article_row(
  array $row,
  ?string $headlineOverride = null,
  ?string $bodyOverride = null,
  ?string $hashtagsOverride = null,
): array {
  require_once __DIR__ . '/article-repository.php';

  $platform = strtolower(trim((string) ($row['platform_final'] ?? $row['platform_suggested'] ?? 'linkedin')));
  $textvorm = trim((string) ($row['content_textvorm'] ?? ''));
  if ($textvorm === '') {
    $textvorm = ce_default_textvorm_for_platform($platform);
  } else {
    $textvorm = ce_normalize_textvorm($textvorm);
  }

  $headline = $headlineOverride ?? ce_article_headline($row);
  $body = $bodyOverride ?? ce_article_body_text($row);
  $hashtags = $hashtagsOverride ?? trim((string) ($row['hashtags_final'] ?? $row['hashtags_suggested'] ?? ''));

  return ce_channel_hard_validation_messages($platform, $textvorm, $headline, $body, $hashtags);
}

/**
 * Korte klanttaal-reminders (addendum §3 punt 2) — niet blokkerend.
 *
 * @return array<string, list<string>>
 */
function ce_channel_soft_reminders_catalog(): array
{
  return [
    'blog|artikel' => ['Werk toe naar één duidelijke lezersvraag en kernantwoord vroeg in de tekst.'],
    'linkedin|post' => ['Eén kerninzicht; maximaal 3 hashtags en 3.000 tekens. Een oproep tot actie is optioneel.'],
    'linkedin|artikel' => ['Denk aan 500–1.000 woorden met tussenkoppen en een onderbouwde conclusie.'],
    'linkedin|promotie' => ['Houd de post kort (richting 80–150 woorden) en nodig uit tot het artikel.'],
    'x|post' => ['Eén gedachte, maximaal 280 tekens en maximaal 2 hashtags.'],
    'instagram|bijschrift' => ['Richting 50–150 woorden; maximaal 5 hashtags bij het bijschrift.'],
    'instagram|carrousel' => ['Eerste slide = belofte; één punt per slide, kort per slide.'],
    'facebook|post' => ['Richting 50–150 woorden; maximaal 2 hashtags.'],
    'tiktok|videoscript' => ['Bijschrift richting 20–60 woorden; 2–3 hashtags.'],
    'youtube|videoscript' => ['Titel maximaal 100 tekens; beschrijving maximaal 5.000 tekens.'],
    'newsletter|editie' => ['Richting 300–700 woorden; geen hashtags.'],
    'threads|post' => ['Maximaal 500 tekens en maximaal 1 onderwerptag.'],
    'bluesky|post' => ['Maximaal 300 tekens en maximaal 2 hashtags.'],
  ];
}

/**
 * @return array<string, array{min_words?: int, max_words?: int}>
 */
function ce_channel_soft_word_guidance_catalog(): array
{
  return [
    'linkedin|promotie' => ['min_words' => 80, 'max_words' => 150],
    'instagram|bijschrift' => ['min_words' => 50, 'max_words' => 150],
    'facebook|post' => ['min_words' => 50, 'max_words' => 150],
    'tiktok|videoscript' => ['min_words' => 20, 'max_words' => 60],
    'newsletter|editie' => ['min_words' => 300, 'max_words' => 700],
    'linkedin|artikel' => ['min_words' => 500, 'max_words' => 1000],
  ];
}

function ce_channel_count_words(string $text): int
{
  $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
  if ($text === '') {
    return 0;
  }

  return count(preg_split('/\s+/u', $text) ?: []);
}

/** @return list<string> */
function ce_channel_soft_reminders_for_platform(string $platformDb, ?string $textvorm): array
{
  $platform = strtolower(trim($platformDb));
  if ($platform === '') {
    return [];
  }
  $form = ce_normalize_textvorm($textvorm ?? ce_default_textvorm_for_platform($platform));
  $key = ce_channel_constraint_key($platform, $form);
  $catalog = ce_channel_soft_reminders_catalog();

  return $catalog[$key]
    ?? $catalog[$platform . '|' . ce_default_textvorm_for_platform($platform)]
    ?? [];
}

/** @return list<string> */
function ce_channel_soft_word_count_messages(string $platformDb, ?string $textvorm, string $body): array
{
  $body = trim($body);
  if ($body === '') {
    return [];
  }
  $platform = strtolower(trim($platformDb));
  $form = ce_normalize_textvorm($textvorm ?? ce_default_textvorm_for_platform($platform));
  $key = ce_channel_constraint_key($platform, $form);
  $guide = ce_channel_soft_word_guidance_catalog()[$key] ?? null;
  if ($guide === null) {
    return [];
  }
  $words = ce_channel_count_words($body);
  $messages = [];
  if (isset($guide['min_words']) && $words > 0 && $words < (int) $guide['min_words']) {
    $messages[] = 'De tekst is nog kort voor dit kanaal (richting '
      . (int) $guide['min_words'] . ' woorden).';
  }
  if (isset($guide['max_words']) && $words > (int) $guide['max_words']) {
    $messages[] = 'De tekst is lang voor dit kanaal (richting maximaal '
      . (int) $guide['max_words'] . ' woorden).';
  }

  return $messages;
}

function ce_channel_copy_modus_cta_hint(PDO $pdo, string $slotId, string $text): ?string
{
  $text = mb_strtolower(trim($text));
  if ($text === '' || $slotId === '') {
    return null;
  }
  require_once __DIR__ . '/copy-modus.php';
  $resolved = ce_resolve_copy_modus_for_slot($pdo, $slotId);
  if (($resolved['modus'] ?? '') !== CE_COPY_MODUS_MERK) {
    return null;
  }
  if (!preg_match(
    '/\b(koop nu|bestel|registreer|meld je aan|schrijf je in|gratis proef|korting|actie|nu bestellen|plan een demo|boek een)\b/u',
    $text,
  )) {
    return null;
  }

  return 'Je copy-modus is gericht op merk en bewijs — vermijd een harde verkoop- of actietaal in deze post.';
}

/**
 * Lichte kanaalcheck op postvoorstel (addendum §3.2) — nooit blokkerend.
 *
 * @return list<string>
 */
function ce_channel_soft_hints_for_proposal(
  string $platformDb,
  ?string $textvorm,
  string $headline = '',
  string $body = '',
  string $hashtags = '',
  ?PDO $pdo = null,
  ?string $slotId = null,
): array {
  $hints = ce_channel_soft_reminders_for_platform($platformDb, $textvorm);
  $bodyForWords = trim($body);
  if ($bodyForWords === '') {
    $bodyForWords = trim($headline);
  }
  foreach (ce_channel_soft_word_count_messages($platformDb, $textvorm, $bodyForWords) as $line) {
    $hints[] = $line;
  }
  $hasText = ce_channel_post_combined_text($headline, $body, $hashtags) !== '';
  if ($pdo !== null && $slotId !== null && $hasText) {
    $cta = ce_channel_copy_modus_cta_hint(
      $pdo,
      $slotId,
      ce_channel_post_combined_text($headline, $body, $hashtags),
    );
    if ($cta !== null) {
      $hints[] = $cta;
    }
  }

  $unique = [];
  foreach ($hints as $line) {
    $line = trim($line);
    if ($line !== '' && !in_array($line, $unique, true)) {
      $unique[] = $line;
    }
  }

  return $unique;
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

/** Korte UI-label (deel na " / " in ce_channel_copy_constraint_labels). */
function ce_channel_textvorm_ui_label(string $platformDb, string $textvorm): string
{
  $platform = strtolower(trim($platformDb));
  $form = ce_normalize_textvorm($textvorm);
  $key = $platform . '|' . $form;
  $full = ce_channel_copy_constraint_labels()[$key] ?? '';
  if ($full === '') {
    return $form;
  }
  if (str_contains($full, ' / ')) {
    return trim(substr($full, (int) strpos($full, ' / ') + 3));
  }

  return $full;
}

/**
 * Publieke catalogus voor de app (addendum §3) — één bron in PHP.
 *
 * @return array{
 *   textvormByPlatform: array<string, list<array{value: string, label: string}>>,
 *   defaultTextvormByPlatform: array<string, string>,
 *   hardLimits: array<string, array<string, int|null>>
 * }
 */
function ce_channel_constraints_public_payload(): array
{
  $platforms = [
    'blog',
    'linkedin',
    'x',
    'instagram',
    'facebook',
    'tiktok',
    'youtube',
    'newsletter',
    'threads',
    'bluesky',
  ];
  $textvormByPlatform = [];
  $defaultTextvormByPlatform = [];
  foreach ($platforms as $platform) {
    $textvormByPlatform[$platform] = array_map(
      static fn (string $value): array => [
        'value' => $value,
        'label' => ce_channel_textvorm_ui_label($platform, $value),
      ],
      ce_textvorm_options_for_platform($platform),
    );
    $defaultTextvormByPlatform[$platform] = ce_default_textvorm_for_platform($platform);
  }

  return [
    'textvormByPlatform' => $textvormByPlatform,
    'defaultTextvormByPlatform' => $defaultTextvormByPlatform,
    'hardLimits' => ce_channel_hard_limits_catalog(),
  ];
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
