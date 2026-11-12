<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(404);
    exit;
}

if (!function_exists('li_h')) {
    function li_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function li_report_logo_url(): string
{
    $base = rtrim((string) li_config('base_url', li_base_url()), '/');
    $configured = (string) li_config('report_logo_url', '');
    if ($configured !== '') {
        return $configured;
    }

    return $base . '/assets/iGiSNext_Blue.png';
}

/** Inline PNG voor e-mail — iGiSNext_Blue.png (transparant, geen rand). */
function li_report_logo_img_html(): string
{
    $path = dirname(__DIR__) . '/assets/iGiSNext_Blue.png';
    if (is_readable($path)) {
        $bytes = file_get_contents($path);
        if (is_string($bytes) && $bytes !== '') {
            $b64 = base64_encode($bytes);
            // 945×179 bron → 180px breed
            return '<img src="data:image/png;base64,' . $b64 . '" alt="Brandflocks" width="180" height="34" style="display:block;border:0;max-width:180px;height:auto;" />';
        }
    }

    return '<span style="font-family:Arial,Helvetica,sans-serif;font-size:28px;font-weight:bold;line-height:1;">'
        . '<span style="color:#033DFF;">iGiS</span><span style="color:#52FFA8;">Next</span></span>';
}

function li_report_public_url(string $reportToken): string
{
    $token = trim($reportToken);
    if ($token === '') {
        return '';
    }

    return rtrim(li_base_url(), '/') . '/report.php?t=' . rawurlencode($token);
}

/** @param array<string, mixed> $metrics @return array<string, mixed> */
function li_metrics_apply_repeatable_best_post(array $metrics): array
{
    $narrative = is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null;
    if ($narrative === null) {
        return $metrics;
    }

    $repeatIdx = $narrative['repeatable_best_post_index'] ?? null;
    if (!is_int($repeatIdx) && !is_numeric($repeatIdx)) {
        return $metrics;
    }
    $repeatIdx = (int) $repeatIdx;
    if ($repeatIdx <= 0) {
        return $metrics;
    }

    $topPosts = is_array($metrics['top_posts'] ?? null) ? $metrics['top_posts'] : [];
    if (!isset($topPosts[$repeatIdx]) || !is_array($topPosts[$repeatIdx])) {
        return $metrics;
    }

    $reasoning = trim((string) ($narrative['repeatable_best_post_reasoning'] ?? ''));
    if ($reasoning === '') {
        return $metrics;
    }

    $bestPost = is_array($metrics['best_post'] ?? null) ? $metrics['best_post'] : ($topPosts[0] ?? null);
    $altPost = $topPosts[$repeatIdx];
    $bestReactions = is_array($bestPost) ? (int) ($bestPost['reactions'] ?? 0) : 0;
    $altReactions = (int) ($altPost['reactions'] ?? 0);
    $altText = trim((string) ($altPost['text'] ?? ''));
    $excluded = trim((string) ($narrative['repeatable_best_post_excluded_reason'] ?? ''));
    $reinforces = (string) ($narrative['repeatable_best_post_reinforces_positioning'] ?? '');

    $excludedClause = $excluded !== '' ? rtrim($excluded, '. ') : 'Het was een eenmalig moment';
    $bestRank = (int) ($bestPost['rank'] ?? ($topPosts[0]['rank'] ?? 1));
    $altRank = (int) ($altPost['rank'] ?? ($repeatIdx + 1));
    $bestDate = li_format_dutch_date((string) ($bestPost['posted_at'] ?? ''));
    $altDate = li_format_dutch_date((string) ($altPost['posted_at'] ?? ''));
    $newBody = 'Post ' . $bestRank . ' op reacties'
        . ($bestDate !== '—' ? ' (' . $bestDate . ')' : '')
        . ' (' . $bestReactions . ' reacties) was niet herhaalbaar: '
        . lcfirst($excludedClause) . '. Herhaalbaarder is post ' . $altRank
        . ($altDate !== '—' ? ' (' . $altDate . ')' : '')
        . ' (' . $altReactions . ' reacties): "'
        . $altText . '" — ' . $reasoning;

    if ($reinforces === 'ja') {
        $newBody .= ' Dit concept versterkt meteen ook de eigen positionering. Dat is een sterk punt.';
    } elseif ($reinforces === 'nee') {
        $newBody .= ' Let wel: dit concept is op zichzelf nog generiek — herhaalbaar, maar het '
            . 'zegt nog niet wat het merk onderscheidt. Dat is precies waar Brandflocks bij helpt.';
    }

    $benefits = is_array($metrics['benefits'] ?? null) ? $metrics['benefits'] : [];
    foreach ($benefits as $i => $benefit) {
        if (is_array($benefit) && ($benefit['id'] ?? '') === 'peak_unused') {
            $benefits[$i]['body'] = $newBody;
            break;
        }
    }
    $metrics['benefits'] = $benefits;

    return $metrics;
}

/** @param array<string, mixed> $metrics @return array<string, mixed> */
function li_metrics_for_render(array $metrics, ?string $scanReferenceYmd = null): array
{
    $metrics = li_metrics_apply_repeatable_best_post($metrics);
    if ($scanReferenceYmd !== null && $scanReferenceYmd !== '') {
        $metrics = li_metrics_at_scan_reference($metrics, $scanReferenceYmd);
    }

    return $metrics;
}

/** @param array<string, mixed> $metrics @return array<string, mixed> */
function li_metrics_at_scan_reference(array $metrics, string $scanReferenceYmd): array
{
    $periodEnd = isset($metrics['period_end']) ? substr((string) $metrics['period_end'], 0, 10) : null;
    $daysAtScan = li_days_between_ymd($periodEnd, $scanReferenceYmd);
    if ($daysAtScan !== null) {
        $metrics['days_since_last_post_at_scan'] = $daysAtScan;
    }

    if (!is_array($metrics['observations'] ?? null)) {
        return $metrics;
    }

    foreach ($metrics['observations'] as $idx => $obs) {
        if (!is_array($obs) || ($obs['id'] ?? '') !== 'stale_page') {
            continue;
        }
        if ($daysAtScan === null) {
            break;
        }
        $metrics['observations'][$idx]['body'] = 'Op het moment van de scan was de laatste post '
            . $daysAtScan . ' dagen oud — een pagina die zo lang stilligt, bouwt geen herkenning op.';
        break;
    }

    return $metrics;
}

/** Eerste regel of korte onderwerpomschrijving voor postverwijzingen. */
function li_post_engagement_title(string $text): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return '';
    }

    $firstLine = trim(strtok($text, "\n") ?: $text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($firstLine) > 72) {
            return rtrim(mb_substr($firstLine, 0, 72)) . '…';
        }
    } elseif (strlen($firstLine) > 72) {
        return rtrim(substr($firstLine, 0, 72)) . '…';
    }

    return $firstLine;
}

/** @param array<string, mixed> $post */
function li_post_ref_label(array $post, int $rank): string
{
    $date = li_format_dutch_date((string) ($post['posted_at'] ?? ''));
    $topic = li_post_engagement_title((string) ($post['text'] ?? ''));
    if ($date !== '—' && $topic !== '') {
        return 'de post van ' . $date . ' over ' . $topic;
    }

    return 'post ' . $rank . ' op reacties';
}

/**
 * Vervang losse "Post N"-verwijzingen door datum + onderwerp (Doornvogel §4).
 *
 * @param list<array<string, mixed>> $topPosts
 */
function li_narrative_polish_post_refs(string $text, array $topPosts): string
{
    if ($text === '' || $topPosts === []) {
        return $text;
    }

    $text = (string) preg_replace('/\bpost\s+0\b/ui', 'post 1 op reacties', $text);

    foreach ($topPosts as $index => $post) {
        if (!is_array($post)) {
            continue;
        }
        $rank = (int) ($post['rank'] ?? ($index + 1));
        $label = li_post_ref_label($post, $rank);
        $text = (string) preg_replace(
            '/\b[Pp]ost\s+' . $rank . '\b(?!\s*\()/u',
            $label,
            $text,
        );
    }

    return $text;
}

function li_render_email_pill_link_html(string $url, string $label): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 0;">'
        . '<tr><td>'
        . '<a href="' . li_h($url) . '" style="display:inline-block;padding:12px 30px;background-color:#52FFA8;'
        . 'color:#033DFF;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:600;'
        . 'line-height:1;text-decoration:none;border-radius:20px;border:2px solid #52FFA8;">'
        . li_h($label)
        . '</a></td></tr></table>';
}

/**
 * Mini-lijst: posts gerangschikt op reacties (#1 = meeste reacties).
 *
 * @param array<string, mixed> $metrics
 */
function li_render_top_posts_mini_list_html(array $metrics): string
{
    $topPosts = is_array($metrics['top_posts'] ?? null) ? $metrics['top_posts'] : [];
    if ($topPosts === []) {
        return '';
    }

    $items = '';
    foreach ($topPosts as $index => $post) {
        if (!is_array($post)) {
            continue;
        }
        $rank = (int) ($post['rank'] ?? ($index + 1));
        $reactions = (int) ($post['reactions'] ?? 0);
        $date = li_format_dutch_date((string) ($post['posted_at'] ?? ''));
        $snippet = li_h((string) ($post['text'] ?? ''));
        $meta = $date !== '—' ? $date . ' · ' . $reactions . ' reacties' : $reactions . ' reacties';

        $items .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #CFC6B8;vertical-align:top;width:56px;">'
            . '<span style="display:inline-block;min-width:44px;padding:4px 8px;background-color:#033DFF;'
            . 'color:#52FFA8;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;'
            . 'line-height:1.2;border-radius:12px;text-align:center;">#' . $rank . '</span>'
            . '</td>'
            . '<td style="padding:10px 0 10px 12px;border-bottom:1px solid #CFC6B8;vertical-align:top;">'
            . '<p style="margin:0 0 4px;color:#6B5A45;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.35;">'
            . li_h($meta)
            . '</p>'
            . '<p style="margin:0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . '&ldquo;' . $snippet . '&rdquo;'
            . '</p>'
            . '</td>'
            . '</tr>';
    }

    return '<tr><td style="padding:20px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 8px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">'
        . 'Posts op reacties</p>'
        . '<p style="margin:0 0 12px;color:#6B5A45;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;">'
        . 'Post #1 heeft de meeste reacties. Verderop in dit rapport verwijzen we naar deze nummering.'
        . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">'
        . $items
        . '</table></td></tr>';
}

/**
 * Outreach-mail: intro, scores, tijdlijn, cijfers en observaties in de mail zelf.
 * Vanaf «Posts op reacties» alleen online (report.php) — zie li_render_report_body_rows().
 *
 * @param array<string, mixed> $metrics
 */
function li_render_outreach_email_html(
    array $metrics,
    string $reportUrl,
    ?string $scanDate = null,
    ?string $scanReferenceYmd = null,
): string {
    $metrics = li_metrics_for_render($metrics, $scanReferenceYmd);
    $companyName = (string) ($metrics['company_name'] ?? 'LinkedIn-pagina');
    $dateLabel = $scanDate ?? li_format_dutch_date(date('Y-m-d'));
    $logoImg = li_report_logo_img_html();

    return li_render_email_shell(
        $logoImg,
        $companyName,
        $dateLabel,
        li_render_report_body_rows($metrics, true, $reportUrl, $scanReferenceYmd),
    );
}

/**
 * Volledig rapport (gehoste pagina / detail) — cijfers, analyse, visualisatie.
 *
 * @param array<string, mixed> $metrics
 */
function li_render_full_report_html(
    array $metrics,
    ?string $scanDate = null,
    ?string $scanReferenceYmd = null,
): string {
    $metrics = li_metrics_for_render($metrics, $scanReferenceYmd);
    $companyName = (string) ($metrics['company_name'] ?? 'LinkedIn-pagina');
    $logoImg = li_report_logo_img_html();
    $dateLabel = $scanDate ?? li_format_dutch_date(date('Y-m-d'));

    return li_render_email_shell(
        $logoImg,
        $companyName,
        $dateLabel,
        li_render_report_body_rows($metrics, false, '', $scanReferenceYmd),
    );
}

/**
 * Gedeelde rapport-body (table rows). Outreach stopt vóór «Posts op reacties».
 *
 * @param array<string, mixed> $metrics — al door li_metrics_for_render()
 */
function li_render_report_body_rows(
    array $metrics,
    bool $forEmail,
    string $reportUrl = '',
    ?string $scanReferenceYmd = null,
): string {
    $period = li_format_period(
        isset($metrics['period_start']) ? (string) $metrics['period_start'] : null,
        isset($metrics['period_end']) ? (string) $metrics['period_end'] : null,
    );

    $postsCount = (int) ($metrics['posts_count'] ?? 0);
    $postsPerWeekRaw = $metrics['posts_per_week'] ?? null;
    $postsPerWeek = is_numeric($postsPerWeekRaw)
        ? $postsPerWeekRaw
        : 'te weinig posts voor een ritme';
    $silences = $metrics['silences_days'] ?? [];
    $silenceLabel = is_array($silences) && $silences !== []
        ? implode(', ', array_map(static fn ($d): string => (string) $d, $silences)) . ' dagen'
        : '—';

    $avgReactions = $metrics['avg_reactions'] ?? '—';
    $medianReactions = $metrics['median_reactions'] ?? '—';
    $noComments = (int) ($metrics['posts_without_comments'] ?? 0);
    $followers = $metrics['followers'] ?? null;
    $followersLabel = is_numeric($followers) ? number_format((int) $followers, 0, ',', '.') : '—';
    if (is_numeric($metrics['days_since_last_post_at_scan'] ?? null)) {
        $daysSinceLast = (int) $metrics['days_since_last_post_at_scan'];
    } elseif ($scanReferenceYmd !== null && $scanReferenceYmd !== '') {
        $daysSinceLast = li_days_between_ymd(
            isset($metrics['period_end']) ? (string) $metrics['period_end'] : null,
            $scanReferenceYmd,
        );
    } else {
        $daysSinceLast = is_numeric($metrics['days_since_last_post'] ?? null)
            ? (int) $metrics['days_since_last_post']
            : li_days_since_ymd(isset($metrics['period_end']) ? (string) $metrics['period_end'] : null);
    }
    $lastPostLabel = $daysSinceLast !== null
        ? ($scanReferenceYmd !== null && $scanReferenceYmd !== ''
            ? 'op het moment van de scan was de laatste post ' . $daysSinceLast . ' dagen oud'
            : $daysSinceLast . ' dagen geleden')
        : '—';

    $formatLabel = li_format_counts_label(is_array($metrics['format_counts'] ?? null) ? $metrics['format_counts'] : []);
    $hashtagLabel = li_hashtags_label(is_array($metrics['hashtags'] ?? null) ? $metrics['hashtags'] : []);
    $languageLabel = li_languages_label(is_array($metrics['languages'] ?? null) ? $metrics['languages'] : []);

    $bestPost = is_array($metrics['best_post'] ?? null) ? $metrics['best_post'] : null;
    $bestLabel = $bestPost !== null
        ? (int) ($bestPost['reactions'] ?? 0) . ' reacties — “' . li_h((string) ($bestPost['text'] ?? '')) . '”'
        : '—';

    $rows = [
        ['Periode', $period],
        ['Aantal posts', (string) $postsCount],
        ['Ritme', is_numeric($postsPerWeek) ? $postsPerWeek . ' posts/week' : (string) $postsPerWeek],
        ['Grootste stiltes', $silenceLabel],
        ['Laatste post', $lastPostLabel],
        ['Gemiddelde reacties', (string) $avgReactions . ' (mediaan ' . $medianReactions . ')'],
        ['Posts zonder opmerkingen', $noComments . ' van ' . $postsCount],
        ['Volgers (pagina)', $followersLabel],
        ['Formats', $formatLabel],
        ['Beste post', $bestLabel],
        ['Hashtags', $hashtagLabel],
        ['Taal', $languageLabel],
    ];

    $sentiments = li_row_sentiments($metrics);

    $tableRows = '';
    foreach ($rows as [$label, $value]) {
        $tableRows .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;width:42%;">'
            . li_render_sentiment_dot($sentiments[$label] ?? 'neutral')
            . li_h($label)
            . '</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;">'
            . $value
            . '</td>'
            . '</tr>';
    }

    $observationsHtml = li_render_observations_html(is_array($metrics['observations'] ?? null) ? $metrics['observations'] : []);
    $introHtml = li_render_intro_html($metrics);
    $scoreSummaryHtml = li_render_score_summary_html(is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null);
    $timelineHtml = li_render_activity_timeline_html($metrics);

    $head = $introHtml
        . $scoreSummaryHtml
        . $timelineHtml
        . '<tr><td style="padding:4px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 12px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">De cijfers</p>'
        . '<p style="margin:0 0 8px;color:#6B5A45;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;">'
        . 'Op basis van de openbare LinkedIn-posts over de periode ' . li_h($period) . ' — een momentopname, geen eindoordeel.'
        . '</p>'
        . li_render_sentiment_legend_html()
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">'
        . $tableRows
        . '</table>'
        . '</td></tr>'
        . $observationsHtml;

    if ($forEmail) {
        $companyName = (string) ($metrics['company_name'] ?? 'LinkedIn-pagina');

        return $head . li_render_report_email_online_teaser_html($reportUrl, $companyName);
    }

    $topPostsMiniListHtml = li_render_top_posts_mini_list_html($metrics);
    $narrativeHtml = li_render_narrative_html(
        is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null,
        is_array($metrics['top_posts'] ?? null) ? $metrics['top_posts'] : [],
        li_scan_normalize_company_description($metrics['company_description'] ?? null),
    );

    $benefits = is_array($metrics['benefits'] ?? null) ? $metrics['benefits'] : [];
    $narrativeBenefit = li_narrative_benefit_block(is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null);
    if ($narrativeBenefit !== null) {
        $benefits[] = $narrativeBenefit;
    }
    $benefitsHtml = li_render_benefits_html($benefits);

    return $head
        . $topPostsMiniListHtml
        . $narrativeHtml
        . $benefitsHtml
        . '<tr><td style="padding:24px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 8px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">Tot slot</p>'
        . '<p style="margin:0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
        . 'Deze scan laat zien waar de kans ligt. Concreet doet Brandflocks dit vervolgens: elke post wordt vóór publicatie getoetst aan één vastgelegd merkprofiel, met een aanwijzing als een post terugzakt naar iets wat elk ander merk ook had kunnen zeggen. Hoe dat er voor dit merk uitziet, bespreken we graag in een kort gesprek.'
        . '</p>'
        . '</td></tr>';
}

/** Afsluiting outreach-mail: vanaf «Posts op reacties» alleen via publieke link. */
function li_render_report_email_online_teaser_html(string $reportUrl, string $companyName): string
{
    $reportUrl = trim($reportUrl);
    $reportLink = $reportUrl !== ''
        ? li_render_email_pill_link_html($reportUrl, 'Bekijk de volledige Social Media Scan')
        : '';

    $cta = '<p style="margin:16px 0 0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;">'
        . 'Zal ik in drie stappen laten zien hoe ' . li_h($companyName)
        . ' deze kernboodschap in toekomstige posts kan laten terugkomen?</p>';

    return '<tr><td style="padding:20px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 8px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">'
        . 'Verder in het volledige rapport</p>'
        . '<p style="margin:0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;">'
        . 'In het online rapport staan ook reacties per post, het merkverhaal, concrete vervolgstappen '
        . 'en onze slotconclusie.'
        . '</p>'
        . $reportLink
        . $cta
        . '</td></tr>';
}

/** Backward compat — volledig rapport (gebruik outreach-mail voor koude e-mail). */
function li_render_report_email(array $metrics, ?string $scanDate = null): string
{
    return li_render_full_report_html($metrics, $scanDate);
}

function li_render_email_shell(string $logoImg, string $companyName, string $dateLabel, string $bodyRows): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#EAE6DC" style="margin:0;padding:0;width:100%;background-color:#EAE6DC;border-collapse:collapse;">'
        . '<tr><td align="center" bgcolor="#EAE6DC" style="margin:0;padding:24px 16px;background-color:#EAE6DC;">'
        . '<table role="presentation" width="500" cellpadding="0" cellspacing="0" border="0" bgcolor="#EAE6DC" style="max-width:500px;width:100%;background-color:#EAE6DC;border-collapse:collapse;">'
        . '<tr><td bgcolor="#EAE6DC" style="padding:0 8px 20px;background-color:#EAE6DC;">'
        . $logoImg
        . '</td></tr>'
        . '<tr><td style="padding:0 8px 16px;background-color:#EAE6DC;">'
        . '<p style="margin:0;color:#033DFF;font-family:Arial,Helvetica,sans-serif;font-size:23px;line-height:1.25;">Social Media Scan — '
        . li_h($companyName) . ' op LinkedIn</p>'
        . '<p style="margin:8px 0 0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
        . li_h($dateLabel) . ' · Brandflocks</p>'
        . '</td></tr>'
        . $bodyRows
        . '</table></td></tr></table>';
}

/**
 * Visuele tijdlijn: postmomenten vs. stiltes (§5 Gemini — stoplicht/grafiek i.p.v. alleen tekst).
 *
 * @param array<string, mixed> $metrics
 */
function li_render_activity_timeline_html(array $metrics): string
{
    $points = is_array($metrics['activity_points'] ?? null) ? $metrics['activity_points'] : [];
    if (count($points) < 2) {
        return '';
    }

    $periodStart = (string) ($metrics['period_start'] ?? '');
    $periodEnd = (string) ($metrics['period_end'] ?? '');
    if ($periodStart === '' || $periodEnd === '') {
        return '';
    }

    try {
        $start = new DateTimeImmutable($periodStart);
        $end = new DateTimeImmutable($periodEnd);
    } catch (Throwable) {
        return '';
    }

    $totalDays = max(1, (int) $start->diff($end)->days + 1);
    $segments = min(24, max(8, (int) ceil($totalDays / 7)));
    $segmentDays = $totalDays / $segments;

    $bars = '';
    $maxReactions = 1;
    foreach ($points as $point) {
        if (is_array($point)) {
            $maxReactions = max($maxReactions, (int) ($point['reactions'] ?? 0));
        }
    }

    for ($i = 0; $i < $segments; $i++) {
        $segStart = $start->modify('+' . (int) floor($i * $segmentDays) . ' days');
        $segEnd = $start->modify('+' . (int) floor(($i + 1) * $segmentDays) . ' days');
        $postsInSeg = 0;
        $segReactions = 0;
        foreach ($points as $point) {
            if (!is_array($point)) {
                continue;
            }
            $date = (string) ($point['date'] ?? '');
            if ($date === '') {
                continue;
            }
            try {
                $postDate = new DateTimeImmutable($date);
            } catch (Throwable) {
                continue;
            }
            if ($postDate >= $segStart && $postDate < $segEnd) {
                $postsInSeg++;
                $segReactions += (int) ($point['reactions'] ?? 0);
            }
        }

        if ($postsInSeg === 0) {
            $fill = '#E8DED0';
            $height = 8;
            $title = 'Geen post';
        } else {
            $ratio = $segReactions / max(1, $postsInSeg * $maxReactions);
            $height = (int) round(12 + 28 * min(1.0, $ratio));
            $fill = $ratio >= 0.6 ? '#1A7A4C' : ($ratio >= 0.3 ? '#033DFF' : '#C17156');
            $title = $postsInSeg . ' post(s)';
        }

        $bars .= '<td style="padding:0 1px;vertical-align:bottom;width:' . (int) floor(100 / $segments) . '%;" title="' . li_h($title) . '">'
            . '<div style="height:' . $height . 'px;background-color:' . $fill . ';border-radius:2px 2px 0 0;"></div>'
            . '</td>';
    }

    $silences = is_array($metrics['silences_days'] ?? null) ? $metrics['silences_days'] : [];
    $maxSilence = $silences !== [] ? (int) max($silences) : 0;
    $silenceNote = $maxSilence > 21
        ? li_render_sentiment_dot('bad') . ' Langste stilte: ' . $maxSilence . ' dagen'
        : li_render_sentiment_dot('good') . ' Geen lange stiltes (&gt;21 dagen)';

    return '<tr><td style="padding:0 8px 16px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 8px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">Postritme in de periode</p>'
        . '<p style="margin:0 0 10px;color:#6B5A45;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;">'
        . 'Groen = actieve/weerklank-periode · blauw = matig · beige = stilte · rood = lage respons'
        . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;height:44px;">'
        . '<tr>' . $bars . '</tr>'
        . '</table>'
        . '<p style="margin:10px 0 0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;">'
        . $silenceNote
        . '</p>'
        . '</td></tr>';
}

/**
 * Openingsblok bovenaan de mail — dit moet verkopen, niet alleen informeren.
 * Opbouw is Hook → Insight → Pivot (advies GPT-review + Gemini-second-opinion, aug 2026):
 * (1) de op-maat-gemaakte case-line eerst — het merk-specifieke ego-boost + pijnpunt-cijfer
 * uit hun eigen scan, dat is de haak die maakt dat iemand doorleest; (2) pas daarna de
 * korte Brandflocks-uitleg (wat Brandflocks doet, en waarom onderscheid ook voor zoek- en
 * AI-systemen helpt) als pivot naar de rest van de mail. Volgorde in de mail als geheel: hook/case-line →
 * korte pitch → score-samenvatting → cijfers/analyse → waar Brandflocks bij kan helpen.
 *
 * Eerder stond de generieke pitch vooraan en de case-line pas als derde alinea — dat
 * begroef de sterkste, meest persoonlijke zin van de hele mail onder twee alinea's
 * algemeenheden. Nu leest de ontvanger eerst iets over zíchzelf, pas daarna over
 * Brandflocks.
 *
 * De pitch zelf opent met de twee-delige USP (unieke positionering vinden + die
 * consistent communiceren) in plaats van eerst uit te leggen waarom consistentie alleen
 * niet volstaat. Consistentie is hier het MIDDEL (hoe je de positionering overeind
 * houdt), niet de belofte zelf. HeroBrand-laag wordt zonder voorwaarde beschreven — geen
 * "optioneel voor wie nóg scherper wil", het is een vast onderdeel, geen schakelbare
 * feature. Zelfde formulering als de sales-pitch (briefing/pitch-contentflow.md), zodat
 * scan-mail en salesgesprek dezelfde taal spreken.
 *
 * @param array<string, mixed> $metrics
 */
function li_render_intro_html(array $metrics): string
{
    $caseLine = li_render_case_line($metrics);

    $pitch = 'Brandflocks helpt merken twee dingen goed doen: een unieke positionering vinden, en die '
        . 'daarna consistent communiceren in alles wat ze posten. De meeste merken missen één van de '
        . 'twee — ze weten niet precies wat hun onderscheid is, of ze weten het wel en verwateren het '
        . 'toch in de uitvoering.';

    // Was: "Dat onderscheid is wat AI beloont" — te stellig als causale claim (feedback op
    // Doornvogel-scan, aug 2026). Herschreven naar een voorzichtiger, verdedigbare formulering.
    $pitchHba = 'Ook voor zoek- en AI-systemen helpt een duidelijke, onderscheidende positionering om beter te '
        . 'begrijpen waar een merk voor staat. Daarom checkt Brandflocks bij elke post niet alleen &ldquo;gaat '
        . 'dit over ons merk&rdquo;, maar of hij de unieke positie van het merk draagt, of terugzakt naar iets wat iedereen '
        . 'had kunnen zeggen.';

    return '<tr><td style="padding:0 8px 22px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;">'
        . $caseLine
        . '</p>'
        . '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;">'
        . $pitch
        . '</p>'
        . '<p style="margin:0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;">'
        . $pitchHba
        . '</p>'
        . '</td></tr>';
}

/**
 * Score-samenvatting, direct ná de intro-pitch en vóór "De cijfers" — de intro belooft
 * letterlijk "Brandflocks doet twee dingen: positionering vinden + consistent
 * communiceren", dus die twee dingen horen als score bovenaan te staan, niet alleen
 * los in het "Het verhaal in de posts"-blok verderop (waar iemand die niet
 * doorscrollt ze nooit ziet). "De cijfers" blijft puur kwantitatief (engagement,
 * ritme) — dit blok is bewust apart, want het zijn kwalitatieve oordelen, geen tellingen.
 *
 * Toont niets als er geen narrative-analyse is (oudere scans, of ai_summary_enabled
 * uit) — zelfde null-safety-patroon als de rest van deze renderer.
 *
 * @param array<string, mixed>|null $narrative
 */
function li_render_score_summary_html(?array $narrative): string
{
    if ($narrative === null) {
        return '';
    }

    $distinctLabel = trim((string) ($narrative['distinctiveness_label'] ?? ''));
    $consistencyLabel = trim((string) ($narrative['consistency_label'] ?? ''));
    $alignmentLabel = trim((string) ($narrative['description_alignment_label'] ?? ''));

    if ($distinctLabel === '' && $consistencyLabel === '' && $alignmentLabel === '') {
        return '';
    }

    $labelText = static fn (string $v): string => match ($v) {
        'ja' => 'Ja',
        'nee' => 'Nee',
        'deels' => 'Deels',
        'gedeeltelijk' => 'Gedeeltelijk',
        'hoog' => 'Hoog',
        'gemiddeld' => 'Gemiddeld',
        'laag' => 'Laag',
        default => '—',
    };

    // Titel telt mee met wat er daadwerkelijk getoond wordt: description_alignment is een derde,
    // optioneel criterium (alleen als er een company_description is) — "twee dingen" klopt dan
    // niet meer, want er staan dan drie rijen (feedback op Doornvogel-scan, aug 2026).
    $criteriaCount = ($alignmentLabel !== '' ? 1 : 0) + ($distinctLabel !== '' ? 1 : 0) + ($consistencyLabel !== '' ? 1 : 0);
    $sectionTitle = match ($criteriaCount) {
        3 => 'De drie criteria waar dit op beoordeeld is',
        1 => 'Waar dit op beoordeeld is',
        default => 'De twee dingen waar dit op beoordeeld is',
    };

    $rows = '';
    if ($alignmentLabel !== '') {
        $sentiment = match ($alignmentLabel) {
            'ja' => 'good',
            'nee' => 'bad',
            default => 'neutral',
        };
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;width:60%;">'
            . li_render_sentiment_dot($sentiment) . 'Posts tonen wat het bedrijf doet</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;">'
            . li_h($labelText($alignmentLabel)) . '</td></tr>';
    }
    if ($distinctLabel !== '') {
        $sentiment = match ($distinctLabel) {
            'ja' => 'good',
            'nee' => 'bad',
            default => 'neutral',
        };
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;width:60%;">'
            . li_render_sentiment_dot($sentiment) . 'Unieke positionering (t.o.v. branche)</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;">'
            . li_h($labelText($distinctLabel)) . '</td></tr>';
    }
    if ($consistencyLabel !== '') {
        $sentiment = match ($consistencyLabel) {
            'hoog' => 'good',
            'laag' => 'bad',
            default => 'neutral',
        };
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;width:60%;">'
            . li_render_sentiment_dot($sentiment) . 'Consistent gecommuniceerd</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #CFC6B8;color:#2E2014;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;vertical-align:top;">'
            . li_h($labelText($consistencyLabel)) . '</td></tr>';
    }

    return '<tr><td style="padding:0 8px 16px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 12px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">'
        . li_h($sectionTitle) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">'
        . $rows
        . '</table></td></tr>';
}

/**
 * De op-maat-gemaakte tweede alinea: past de pitch hierboven concreet toe op deze
 * pagina. Gebruikt bij voorkeur de merkverhaal-analyse (§ narrative) — dat is het
 * meest onderscheidende en meest Brandflocks-relevante wat deze scan kan laten
 * zien. Zonder narrative-analyse (uitgeschakeld/mislukt) valt terug op de sterkste
 * kwantitatieve observatie, met een expliciete Brandflocks-brug aan het eind zodat
 * de mail nooit eindigt in kale cijfers zonder verkoophaak.
 *
 * @param array<string, mixed> $metrics
 */
function li_render_case_line(array $metrics): string
{
    $companyName = (string) ($metrics['company_name'] ?? 'deze pagina');
    $bestPost = is_array($metrics['best_post'] ?? null) ? $metrics['best_post'] : null;
    $avgReactions = is_numeric($metrics['avg_reactions'] ?? null) ? (float) $metrics['avg_reactions'] : null;
    $postsPerWeek = is_numeric($metrics['posts_per_week'] ?? null) ? (float) $metrics['posts_per_week'] : null;
    $silences = is_array($metrics['silences_days'] ?? null) ? $metrics['silences_days'] : [];
    $maxSilence = $silences !== [] ? (int) max($silences) : null;
    $observations = is_array($metrics['observations'] ?? null) ? $metrics['observations'] : [];
    $narrative = is_array($metrics['narrative'] ?? null) ? $metrics['narrative'] : null;

    $obsById = [];
    foreach ($observations as $obs) {
        if (is_array($obs) && isset($obs['id'])) {
            $obsById[(string) $obs['id']] = $obs;
        }
    }

    $reachPhrase = null;
    if ($postsPerWeek !== null && $maxSilence !== null) {
        $rhythmLabel = rtrim(rtrim(number_format($postsPerWeek, 1, ',', '.'), '0'), ',');
        $reachPhrase = 'gemiddeld ' . $rhythmLabel . ' posts per week, met stiltes tot ' . $maxSilence . ' dagen';
    }

    $coreMessage = $narrative !== null ? trim((string) ($narrative['core_message'] ?? '')) : '';
    $label = $narrative !== null ? (string) ($narrative['consistency_label'] ?? '') : '';
    $brancheCode = li_branche_code_for_scan($metrics['branche_code'] ?? null);
    $distinctLabel = $narrative !== null ? (string) ($narrative['distinctiveness_label'] ?? '') : '';
    $alignmentLabel = $narrative !== null ? (string) ($narrative['description_alignment_label'] ?? '') : '';
    $matchedCliches = [];
    if ($narrative !== null) {
        foreach (($narrative['matched_cliches'] ?? []) as $cliche) {
            $cliche = trim((string) $cliche);
            if ($cliche !== '') {
                $matchedCliches[] = $cliche;
            }
        }
    }
    $firstCliche = $matchedCliches[0] ?? '';

    if ($alignmentLabel === 'nee') {
        return 'Bij ' . li_h($companyName) . ' komt in de posts niet concreet terug waar het bedrijf voor staat. '
            . 'Lezers én AI-modellen moeten dat er zelf uithalen — en nu lukt dat nauwelijks.';
    }

    if ($alignmentLabel === 'deels') {
        return 'Bij ' . li_h($companyName) . ' komt de eigen positionering in een deel van de posts duidelijk terug, in andere niet. '
            . 'Daardoor blijft voor lezers onvoldoende duidelijk waarin ' . li_h($companyName) . ' zich onderscheidt.';
    }

    if ($brancheCode !== null && $distinctLabel !== '' && $coreMessage !== '') {
        if ($distinctLabel === 'nee' && $firstCliche !== '') {
            $consistent = $label === 'hoog'
                ? 'komt het verhaal consistent terug — maar het is ook precies wat de rest van de branche zegt'
                : 'overlapt de kernboodschap met wat de rest van de branche zegt';

            return 'Bij ' . li_h($companyName) . ' ' . $consistent
                . ': “' . li_h($firstCliche) . '”. Dat is niet zozeer een kwestie van de posts zelf, als van '
                . 'positionering, en dat is precies waar Brandflocks bij helpt: hieronder staat waar het '
                . 'overlapt en waar de ruimte zit om zich te onderscheiden.';
        }

        if ($distinctLabel === 'ja') {
            $line = 'Bij ' . li_h($companyName) . ' is geen overlap te vinden met wat de rest van de branche claimt — “'
                . li_h($coreMessage) . '” is eigen taal, geen branche-cliché. Dat is een sterk punt. '
                . 'Het probleem zit dus niet in wát ze te zeggen hebben.';

            // Synthese: onderscheidend verhaal (distinctiveness) + hoe vaak/hoe stabiel het verteld
            // wordt (consistency + ritme) horen in één zin samen te komen, niet los van elkaar — dat
            // is de scherpste combinatie die deze scan kan laten zien (feedback GPT-review, aug 2026).
            //
            // Pijnpunt-keuze: een concrete stilte (aantal dagen) is feitelijker en minder
            // betwistbaar dan een kwalitatief oordeel als "de boodschap wisselt" — dus die krijgt
            // voorrang als hij er is. Voorbeeld uit het advies: "we noticed a 106-day gap between
            // posts" i.p.v. een uitspraak over engagement of frequentie an sich (data-selectie-
            // advies, aug 2026). Drempel van 21 dagen sluit aan bij de bestaande 'no_rhythm'-observatie.
            if ($maxSilence !== null && $maxSilence > 21) {
                $line .= ' Wat we wél zien: een stilte van ' . $maxSilence . ' dagen tussen twee posts. Een '
                    . 'onderscheidend verhaal dat zo lang stilligt, bouwt geen herkenning op — niet bij lezers, '
                    . 'niet bij AI-modellen.';
            } elseif ($label === 'laag' || $label === 'gemiddeld') {
                $line .= ' Het zit erin hoe vaak en hoe eenduidig dat verhaal terugkomt: nu wisselt de '
                    . 'boodschap nog per post, waardoor de herkenning niet opbouwt die dit verhaal '
                    . 'verdient.';
            } elseif ($reachPhrase !== null) {
                $line .= ' Het zit in hoe vaak: ' . $reachPhrase . '. Dezelfde scherpe positionering, '
                    . 'alleen vaker verteld — dat is wat nu ontbreekt.';
            } else {
                $line .= ' Waar het nu blijft liggen, staat hieronder.';
            }

            return $line;
        }

        if ($distinctLabel === 'gedeeltelijk') {
            $quote = $firstCliche !== '' ? $firstCliche : $coreMessage;

            return 'Bij ' . li_h($companyName) . ' is de kernboodschap deels eigen taal, deels iets wat vaker klinkt in deze '
                . 'branche: “' . li_h($quote) . '”. De helft van een onderscheidende positionering hebben ze dus '
                . 'al te pakken; de andere helft is precies waar Brandflocks om draait: elke post automatisch '
                . 'toetsen aan één vastgelegd merkprofiel, zodat de posts het onderscheidende deel versterken in '
                . 'plaats van het generieke deel.';
        }
    }

    if ($label === 'hoog' && $coreMessage !== '') {
        $line = 'Bij ' . li_h($companyName) . ' staat dat verhaal er al: “' . li_h($coreMessage) . '” — en dat komt '
            . 'consistent terug (verderop in deze scan de details). Dat is een sterk punt. Het probleem zit dus niet in wát ze te zeggen hebben.';
        $line .= $reachPhrase !== null
            ? ' Het zit in hoe vaak: ' . $reachPhrase . '. Hetzelfde sterke verhaal, alleen vaker verteld — dat is wat nu ontbreekt.'
            : ' Waar het nu blijft liggen, staat hieronder.';

        return $line;
    }

    if ($label === 'laag' && $coreMessage !== '') {
        return 'Bij ' . li_h($companyName) . ' wisselt de boodschap per post — daardoor is een vaste rode draad lastig '
            . 'te herkennen, voor lezers én voor AI-modellen. Dat is niet zozeer een kwestie van hoeveel er '
            . 'gepost wordt, als van herkenning, en dat is precies waar Brandflocks bij helpt: hieronder staat wat er wél '
            . 'consequent terugkomt — en waar het uiteenloopt.';
    }

    if ($label !== '' && $coreMessage !== '') {
        return 'Bij ' . li_h($companyName) . ' is de kern van het verhaal te herkennen — “' . li_h($coreMessage) . '” '
            . '— maar niet elke post draagt hem even scherp uit. De helft van een consistent merk hebben ze '
            . 'dus al te pakken; de andere helft is precies waar Brandflocks om draait: elke post automatisch '
            . 'toetsen aan één vastgelegd merkprofiel, zodat lezers, zoekmachines én AI-modellen steeds hetzelfde '
            . 'beeld van het merk krijgen.';
    }

    // Geen (bruikbare) narrative-analyse — terugvallen op de sterkste kwantitatieve observatie.
    if ($bestPost !== null && $avgReactions !== null && $avgReactions > 0
        && ((int) ($bestPost['reactions'] ?? 0)) >= 2 * $avgReactions
    ) {
        $ratioRaw = ((int) $bestPost['reactions']) / $avgReactions;
        $ratio = rtrim(rtrim(number_format($ratioRaw, 1, ',', '.'), '0'), ',');

        $line = 'Eén post van ' . li_h($companyName) . ' haalde <strong>'
            . (int) $bestPost['reactions'] . ' reacties</strong> — ruim ' . $ratio
            . 'x het gemiddelde van ' . $avgReactions . '.';

        $line .= isset($obsById['recent_decline'])
            ? ' Dat momentum is niet vastgehouden: de meest recente posts scoren juist duidelijk lager. Dat is precies waar Brandflocks bij helpt: hieronder staat waar het verschil zit.'
            : ' Dat is geen toeval — het laat zien waar op dit moment het meeste potentieel ligt. Hieronder staat waarom.';

        return $line;
    }

    if (isset($obsById['one_way'])) {
        return li_h((string) ($obsById['one_way']['body'] ?? ''))
            . ' Dat is precies waar Brandflocks bij helpt — hieronder staat wat dat over de pagina zegt.';
    }

    if (isset($obsById['no_rhythm'])) {
        return li_h((string) ($obsById['no_rhythm']['body'] ?? ''))
            . ' Dat is precies waar Brandflocks bij helpt — hieronder de volledige scan.';
    }

    if (isset($obsById['low_engagement'])) {
        return li_h((string) ($obsById['low_engagement']['body'] ?? ''))
            . ' Dat is precies waar Brandflocks bij helpt — hieronder staat waar dat vandaan komt.';
    }

    if ($observations !== [] && is_array($observations[0] ?? null)) {
        return li_h((string) ($observations[0]['body'] ?? ''))
            . ' Hieronder de volledige scan, inclusief waar Brandflocks bij kan helpen.';
    }

    return 'We hebben de openbare LinkedIn-posts van ' . li_h($companyName)
        . ' bekeken — de cijfers hieronder laten een aantal duidelijke patronen zien.';
}

/**
 * Sentiment per rij in de cijfertabel — gebruikt om er een gevuld bolletje voor te
 * zetten (groen = goed, rood = niet goed, wit = neutraal/informatief). Hergebruikt
 * dezelfde drempelwaarden als li_generate_observations() in analyzer.php, zodat het
 * bolletje nooit iets anders zegt dan de tekst in "Wat opvalt".
 *
 * @param array<string, mixed> $metrics
 *
 * @return array<string, string> label (zoals gebruikt in $rows) => 'good'|'bad'|'neutral'
 */
function li_row_sentiments(array $metrics): array
{
    $observations = is_array($metrics['observations'] ?? null) ? $metrics['observations'] : [];
    $obsById = [];
    foreach ($observations as $obs) {
        if (is_array($obs) && isset($obs['id'])) {
            $obsById[(string) $obs['id']] = true;
        }
    }

    $postsPerWeek = is_numeric($metrics['posts_per_week'] ?? null) ? (float) $metrics['posts_per_week'] : null;
    $silences = is_array($metrics['silences_days'] ?? null) ? $metrics['silences_days'] : [];
    $avgReactions = is_numeric($metrics['avg_reactions'] ?? null) ? (float) $metrics['avg_reactions'] : null;
    $postsCount = (int) ($metrics['posts_count'] ?? 0);
    $noComments = (int) ($metrics['posts_without_comments'] ?? 0);
    $followers = is_numeric($metrics['followers'] ?? null) ? (int) $metrics['followers'] : null;
    $formatCounts = is_array($metrics['format_counts'] ?? null) ? $metrics['format_counts'] : [];
    if (is_numeric($metrics['days_since_last_post_at_scan'] ?? null)) {
        $daysSinceLast = (int) $metrics['days_since_last_post_at_scan'];
    } else {
        $daysSinceLast = is_numeric($metrics['days_since_last_post'] ?? null)
            ? (int) $metrics['days_since_last_post']
            : li_days_since_ymd(isset($metrics['period_end']) ? (string) $metrics['period_end'] : null);
    }

    $noRhythm = isset($obsById['no_rhythm']);

    $ritme = 'neutral';
    if ($noRhythm) {
        $ritme = 'bad';
    } elseif ($postsPerWeek !== null && $postsPerWeek >= 2) {
        $ritme = 'good';
    }

    $stiltes = 'neutral';
    if ($noRhythm) {
        $stiltes = 'bad';
    } elseif ($silences !== [] && max($silences) <= 14) {
        $stiltes = 'good';
    }

    $reacties = 'neutral';
    if (isset($obsById['low_engagement'])) {
        $reacties = 'bad';
    } elseif ($followers !== null && $followers > 0 && $avgReactions !== null && $avgReactions >= $followers * 0.15) {
        $reacties = 'good';
    }

    $comments = 'neutral';
    if (isset($obsById['one_way'])) {
        $comments = 'bad';
    } elseif ($postsCount > 0 && ($noComments / $postsCount) <= 0.3) {
        $comments = 'good';
    }

    $formats = 'neutral';
    if (isset($obsById['low_format_variety'])) {
        $formats = 'bad';
    } elseif (count($formatCounts) >= 3) {
        $formats = 'good';
    }

    $beste = isset($obsById['peak_unused']) ? 'good' : 'neutral';

    $lastPost = 'neutral';
    if ($daysSinceLast !== null && $daysSinceLast > 30) {
        $lastPost = 'bad';
    } elseif ($daysSinceLast !== null && $daysSinceLast <= 14) {
        $lastPost = 'good';
    }

    return [
        'Periode' => 'neutral',
        'Aantal posts' => 'neutral',
        'Ritme' => $ritme,
        'Grootste stiltes' => $stiltes,
        'Gemiddelde reacties' => $reacties,
        'Posts zonder opmerkingen' => $comments,
        'Laatste post' => $lastPost,
        'Volgers (pagina)' => 'neutral',
        'Formats' => $formats,
        'Beste post' => $beste,
        'Hashtags' => 'neutral',
        'Taal' => 'neutral',
    ];
}

/** Legenda onder «De cijfers» — één regel per bolletje (e-mailclients). */
function li_render_sentiment_legend_html(): string
{
    $cellStyle = 'padding:0 0 6px;color:#6B5A45;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;vertical-align:middle;';
    $lastCellStyle = 'padding:0 0 12px;color:#6B5A45;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;vertical-align:middle;';
    $rows = [
        ['good', 'goed', $cellStyle],
        ['bad', 'kan beter', $cellStyle],
        ['neutral', 'neutraal', $lastCellStyle],
    ];
    $items = '';
    foreach ($rows as [$sentiment, $label, $style]) {
        $items .= '<tr><td style="' . $style . '">'
            . li_render_sentiment_dot($sentiment) . li_h($label)
            . '</td></tr>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">'
        . $items
        . '</table>';
}

/**
 * Gevuld bolletje (9×9px) als lijst-marker — geen radio-outline.
 * good = groen (#1A7A4C), bad = rood, neutral = iGiS blauw (#033DFF).
 */
function li_render_sentiment_dot(string $sentiment): string
{
    $fill = match ($sentiment) {
        'good' => '#1A7A4C',
        'bad' => '#C17156',
        default => '#033DFF',
    };

    return '<span style="display:inline-block;width:9px;height:9px;min-width:9px;'
        . 'border-radius:50%;background-color:' . $fill . ';border:0;'
        . 'margin-right:8px;vertical-align:middle;line-height:9px;position:relative;top:-1px;" aria-hidden="true">&nbsp;</span>';
}

/** Volledige HTML voor klembord — beige body, geen witte rand (§5.2 briefing). */
function li_render_report_clipboard_html(string $fragment): string
{
    return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background-color:#EAE6DC;" bgcolor="#EAE6DC">'
        . '<!--StartFragment-->' . $fragment . '<!--EndFragment-->'
        . '</body></html>';
}

/** @param array<string, mixed> $metrics */
function li_render_outreach_plaintext(
    array $metrics,
    string $reportUrl,
    ?string $scanDate = null,
    ?string $scanReferenceYmd = null,
): string {
    $metrics = li_metrics_for_render($metrics, $scanReferenceYmd);
    $companyName = (string) ($metrics['company_name'] ?? 'LinkedIn-pagina');
    $dateLabel = $scanDate ?? li_format_dutch_date(date('Y-m-d'));
    $hook = strip_tags(str_replace(['&ldquo;', '&rdquo;', '&nbsp;'], ['"', '"', ' '], li_render_case_line($metrics)));

    $lines = [
        "Social Media Scan — {$companyName} op LinkedIn",
        "{$dateLabel} · Brandflocks",
        '',
        $hook,
    ];
    if (trim($reportUrl) !== '') {
        $lines[] = '';
        $lines[] = 'Volledig rapport online: ' . $reportUrl;
    }
    $lines[] = '';
    $lines[] = 'Zal ik in drie stappen laten zien hoe ' . $companyName . ' de kernboodschap in toekomstige posts kan laten terugkomen?';

    return implode("\n", $lines);
}

/** @param array<string, mixed> $metrics */
function li_render_report_plaintext(array $metrics, ?string $scanDate = null): string
{
    $companyName = (string) ($metrics['company_name'] ?? 'LinkedIn-pagina');
    $dateLabel = $scanDate ?? li_format_dutch_date(date('Y-m-d'));
    $period = li_format_period(
        isset($metrics['period_start']) ? (string) $metrics['period_start'] : null,
        isset($metrics['period_end']) ? (string) $metrics['period_end'] : null,
    );

    return "Social Media Scan — {$companyName} op LinkedIn\n"
        . "{$dateLabel} · Brandflocks\n\n"
        . "Periode: {$period}\n"
        . 'Posts: ' . (int) ($metrics['posts_count'] ?? 0) . "\n\n"
        . '(Open de HTML-bijlage of plak de opgemaakte versie uit de scan-tool.)';
}

function li_format_counts_label(array $counts): string
{
    if ($counts === []) {
        return '—';
    }

    $labels = [
        'image' => 'beeld',
        'article' => 'artikel',
        'video' => 'video',
        'document' => 'document',
        'none' => 'tekst',
    ];

    $parts = [];
    foreach ($counts as $type => $count) {
        $label = $labels[$type] ?? $type;
        $parts[] = $count . '× ' . li_h($label);
    }

    return implode(', ', $parts);
}

/** @param list<array{tag: string, count: int}> $tags */
function li_hashtags_label(array $tags): string
{
    if ($tags === []) {
        return 'geen hashtags gevonden';
    }

    $parts = [];
    foreach ($tags as $row) {
        $parts[] = '#' . li_h((string) ($row['tag'] ?? '')) . ' (' . (int) ($row['count'] ?? 0) . '×)';
    }

    return implode(', ', $parts);
}

/** @param list<array{code: string, count: int, pct: int}> $langs */
function li_languages_label(array $langs): string
{
    if ($langs === []) {
        return '—';
    }

    $parts = [];
    foreach ($langs as $row) {
        $parts[] = li_h((string) ($row['code'] ?? '')) . ' (' . (int) ($row['pct'] ?? 0) . '%)';
    }

    return implode(', ', $parts);
}

/** @param list<array{title: string, body: string}> $observations */
function li_render_observations_html(array $observations): string
{
    if ($observations === []) {
        return '';
    }

    $items = '';
    foreach ($observations as $obs) {
        $items .= '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . '<strong>' . li_h((string) ($obs['title'] ?? '')) . '.</strong> '
            . li_h((string) ($obs['body'] ?? ''))
            . '</p>';
    }

    return '<tr><td style="padding:20px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 12px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">Wat opvalt</p>'
        . $items
        . '</td></tr>';
}

/** Interne bedrijfsnotitie nooit letterlijk in prospecttekst. */
function li_strip_internal_company_note(string $text, ?string $note): string
{
    $note = trim((string) $note);
    $noteLen = function_exists('mb_strlen') ? mb_strlen($note) : strlen($note);
    if ($note === '' || $text === '' || $noteLen < 12) {
        return $text;
    }

    $variants = [
        '“' . $note . '”',
        '"' . $note . '"',
        '„' . $note . '"',
        $note,
    ];
    $stripped = str_replace($variants, 'de eigen positionering', $text);
    if (!is_string($stripped)) {
        return $text;
    }

    if (preg_match('/^(de eigen positionering)([\s,.!?;:—-]|$)/u', $stripped)) {
        $stripped = 'De eigen positionering' . substr($stripped, strlen('de eigen positionering'));
    }

    return $stripped;
}

/** @param array<string, mixed>|null $narrative @param list<array<string, mixed>> $topPosts */
function li_render_narrative_html(?array $narrative, array $topPosts = [], ?string $companyDescription = null): string
{
    if ($narrative === null) {
        return '';
    }

    $coreMessage = trim((string) ($narrative['core_message'] ?? ''));
    $reasoning = trim((string) ($narrative['consistency_reasoning'] ?? ''));
    if ($coreMessage === '' || $reasoning === '') {
        return '';
    }

    $reasoning = li_strip_internal_company_note(li_narrative_polish_post_refs($reasoning, $topPosts), $companyDescription);

    $themes = [];
    foreach (($narrative['recurring_themes'] ?? []) as $theme) {
        $theme = trim((string) $theme);
        if ($theme !== '') {
            $themes[] = li_h($theme);
        }
    }
    $themesLabel = $themes !== [] ? implode(', ', $themes) : '—';

    $label = (string) ($narrative['consistency_label'] ?? 'gemiddeld');
    $sentiment = match ($label) {
        'hoog' => 'good',
        'laag' => 'bad',
        default => 'neutral',
    };

    $distinctLabel = (string) ($narrative['distinctiveness_label'] ?? '');
    $distinctReasoning = trim((string) ($narrative['distinctiveness_reasoning'] ?? ''));
    $distinctReasoning = li_strip_internal_company_note(li_narrative_polish_post_refs($distinctReasoning, $topPosts), $companyDescription);
    $alignmentLabel = (string) ($narrative['description_alignment_label'] ?? '');
    $alignmentReasoning = trim((string) ($narrative['description_alignment_reasoning'] ?? ''));
    $alignmentReasoning = li_strip_internal_company_note(li_narrative_polish_post_refs($alignmentReasoning, $topPosts), $companyDescription);
    $alignmentBlock = '';
    if ($alignmentLabel !== '' && $alignmentReasoning !== '') {
        $alignmentSentiment = match ($alignmentLabel) {
            'ja' => 'good',
            'nee' => 'bad',
            default => 'neutral',
        };
        $alignmentBlock = '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . li_render_sentiment_dot($alignmentSentiment)
            . '<strong>Wat de posts over het bedrijf vertellen:</strong> ' . li_h($alignmentLabel) . '</p>'
            . '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . li_h($alignmentReasoning) . '</p>';
    }
    $distinctBlock = '';
    if ($distinctLabel !== '' && $distinctReasoning !== '') {
        $distinctSentiment = match ($distinctLabel) {
            'ja' => 'good',
            'nee' => 'bad',
            default => 'neutral',
        };
        $distinctBlock = '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . li_render_sentiment_dot($distinctSentiment)
            . '<strong>Onderscheidend vermogen:</strong> ' . li_h($distinctLabel) . '</p>'
            . '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . li_h($distinctReasoning) . '</p>';

        $matchedCliches = [];
        foreach (($narrative['matched_cliches'] ?? []) as $cliche) {
            $cliche = trim((string) $cliche);
            if ($cliche !== '') {
                $matchedCliches[] = li_h($cliche);
            }
        }
        if ($matchedCliches !== []) {
            $quoted = array_map(static fn (string $item): string => '“' . $item . '”', $matchedCliches);
            $distinctBlock .= '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
                . 'Deze kernboodschap raakt bekende patronen in de branche: '
                . implode(', ', $quoted)
                . '.</p>';
        }
    }

    $body = '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
        . '<strong>Kernboodschap:</strong> “' . li_h($coreMessage) . '”</p>'
        . '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
        . '<strong>Terugkerende thema&apos;s:</strong> ' . $themesLabel . '</p>'
        . $alignmentBlock
        . $distinctBlock
        . '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
        . li_render_sentiment_dot($sentiment)
        . '<strong>Consistentie:</strong> ' . li_h($label) . '</p>'
        . '<p style="margin:0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
        . li_h($reasoning) . '</p>';

    if (in_array($label, ['laag', 'gemiddeld'], true)) {
        $examples = [];
        foreach (($narrative['inconsistency_examples'] ?? []) as $example) {
            $example = trim((string) $example);
            if ($example !== '') {
                // Losse voorbeelden worden hierna met een spatie aan elkaar geplakt, dus zonder
                // afsluitend leesteken loopt de ene zin door in de andere ("...kernboodschap Post 3
                // is..."). Zorg dat elk voorbeeld eindigt op een leesteken vóórdat het gejoined wordt
                // (feedback op Doornvogel-scan, aug 2026).
                if (!preg_match('/[.!?]$/', $example)) {
                    $example .= '.';
                }
                $examples[] = li_h(li_narrative_polish_post_refs($example, $topPosts));
            }
        }
        if ($examples !== []) {
            $shown = array_slice($examples, 0, 2);
            $body .= '<p style="margin:10px 0 0;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
                . implode(' ', $shown)
                . '</p>';
        }
    }

    return '<tr><td style="padding:20px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 12px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">Het verhaal in de posts</p>'
        . $body
        . '</td></tr>';
}

/** @param array<string, mixed>|null $narrative @return array{title: string, body: string}|null */
function li_narrative_benefit_block(?array $narrative): ?array
{
    if ($narrative === null) {
        return null;
    }

    $label = (string) ($narrative['consistency_label'] ?? '');

    // Was: "...herkennen en aanbevelen" — een claim over ChatGPT die specifieker klinkt dan we kunnen
    // hardmaken. Teruggebracht naar de voorzichtigere, verdedigbare versie (feedback op
    // Doornvogel-scan, aug 2026).
    if ($label === 'hoog') {
        return [
            'title' => 'Herkenbaar voor mens én machine',
            'body' => 'De kernboodschap komt al consequent terug in de posts. Een consistente kernboodschap helpt '
                . 'mensen en digitale systemen beter begrijpen waar het merk voor staat. Brandflocks toetst elke post '
                . 'vooraf aan één vastgelegd merkprofiel, zodat die consistentie geborgd blijft terwijl het bedrijf groeit.',
        ];
    }

    return [
        'title' => 'Herkenbaar voor mens én machine',
        'body' => 'Een consistente kernboodschap helpt mensen en digitale systemen beter begrijpen waar het merk voor '
            . 'staat. Brandflocks toetst elke post vooraf aan één vastgelegd merkprofiel, zodat die consistentie niet '
            . 'van het toeval afhangt.',
    ];
}

/** @param list<array{title: string, body: string}> $benefits */
function li_render_benefits_html(array $benefits): string
{
    if ($benefits === []) {
        return '';
    }

    $items = '';
    foreach ($benefits as $benefit) {
        $items .= '<p style="margin:0 0 10px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.35;">'
            . '<strong>' . li_h((string) ($benefit['title'] ?? '')) . '.</strong> '
            . li_h((string) ($benefit['body'] ?? ''))
            . '</p>';
    }

    return '<tr><td style="padding:20px 8px 8px;background-color:#EAE6DC;">'
        . '<p style="margin:0 0 12px;color:#2E2014;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.35;font-weight:bold;">Waar Brandflocks bij kan helpen</p>'
        . $items
        . '</td></tr>';
}

/**
 * Onderwerpregel — moet nieuwsgierig maken, niet aankondigen dat er een rapport
 * aankomt. "Social Media Scan — {bedrijf}" is precies het soort neutrale titel
 * die in een inbox wegvalt. Deze haakt in op de kernstelling uit de intro
 * (§ li_render_intro_html): een onderscheidende positionering valt op, een
 * gemiddelde niet. Bewust geen "AI beloont..."-claim meer in de onderwerpregel
 * — zelfde reden als de tekstuele afzwakking in li_render_intro_html (feedback
 * op Doornvogel-scan, aug 2026).
 */
function li_mailto_subject(string $companyName): string
{
    return 'Valt ' . $companyName . ' op, of klinkt het als de rest van de branche?';
}
