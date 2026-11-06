<?php

declare(strict_types=1);

/** @param array<string, mixed> $report */
function ce_report_pdf_filename(array $report): string
{
    $start = preg_replace('/[^0-9-]/', '', (string) ($report['periodStart'] ?? '')) ?: 'start';
    $end = preg_replace('/[^0-9-]/', '', (string) ($report['periodEnd'] ?? '')) ?: 'end';

    return sprintf('Content-Engine-Report-%s-%s.pdf', $start, $end);
}

/** @param array<string, mixed> $report */
function ce_report_pdf_html(array $report): string
{
    $esc = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $daily = is_array($report['dailyBreakdown'] ?? null) ? $report['dailyBreakdown'] : [];
    $websiteAnalytics = is_array($report['websiteAnalytics'] ?? null) ? $report['websiteAnalytics'] : [];
    $gaTotals = is_array($websiteAnalytics['totals'] ?? null) ? $websiteAnalytics['totals'] : [];
    $gaTopPages = is_array($websiteAnalytics['topPages'] ?? null) ? $websiteAnalytics['topPages'] : [];
    $gaSourceMedium = is_array($websiteAnalytics['sourceMedium'] ?? null) ? $websiteAnalytics['sourceMedium'] : [];
    $gaWeekly = is_array($websiteAnalytics['weekly'] ?? null) ? $websiteAnalytics['weekly'] : [];
    $linkedIn = is_array($report['linkedInEngagement'] ?? null) ? $report['linkedInEngagement'] : [];
    $linkedInTotals = is_array($linkedIn['totals'] ?? null) ? $linkedIn['totals'] : [];
    $linkedInWeekly = is_array($linkedIn['weekly'] ?? null) ? $linkedIn['weekly'] : [];
    $angle = is_array($report['angleDistribution'] ?? null) ? $report['angleDistribution'] : [];
    $tone = is_array($report['toneDistribution'] ?? null) ? $report['toneDistribution'] : [];
    $best = is_array($report['bestPerformingPost'] ?? null) ? $report['bestPerformingPost'] : null;

    $scope = (string) ($report['scope'] ?? 'general');
    $scopeLabel = $scope === 'article' ? 'Article' : 'General';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title><?= $esc($report['title'] ?? 'Report') ?></title>
  <style>
    @page { margin: 28px 32px; }
    body {
      font-family: DejaVu Sans, sans-serif;
      color: #2e2014;
      font-size: 11px;
      line-height: 1.45;
    }
    h1 {
      color: #033dff;
      font-size: 22px;
      margin: 0 0 6px;
    }
    h2 {
      font-size: 13px;
      margin: 22px 0 8px;
      color: #2e2014;
    }
    p { margin: 0 0 8px; }
    .meta { color: #ada695; margin-bottom: 16px; }
    .metrics {
      width: 100%;
      border-collapse: separate;
      border-spacing: 8px 8px;
      margin: 0 0 4px;
    }
    .metrics td {
      background: #f7eef3;
      border-radius: 12px;
      padding: 10px 12px;
      vertical-align: top;
      width: 33%;
    }
    .metric-label {
      display: block;
      color: #ada695;
      font-size: 10px;
      margin-bottom: 4px;
    }
    .metric-value {
      display: block;
      color: #033dff;
      font-size: 18px;
      font-weight: bold;
    }
    table.data {
      width: 100%;
      border-collapse: collapse;
      margin-top: 4px;
    }
    table.data th,
    table.data td {
      border-bottom: 1px solid #eae6dc;
      padding: 6px 8px;
      text-align: left;
      font-size: 10px;
    }
    table.data th {
      color: #ada695;
      font-weight: bold;
    }
    ul.dist {
      margin: 0;
      padding-left: 16px;
    }
    ul.dist li { margin-bottom: 4px; }
    .insight {
      background: #f7eef3;
      border: 1px solid #ada695;
      border-radius: 12px;
      padding: 12px 14px;
      white-space: pre-wrap;
    }
    .highlight {
      background: #f7eef3;
      border-radius: 12px;
      padding: 10px 12px;
      margin-top: 4px;
    }
  </style>
</head>
<body>
  <h1><?= $esc($report['title'] ?? 'Report') ?></h1>
  <p class="meta">
    Periode: <?= $esc($report['periodStart'] ?? '') ?> – <?= $esc($report['periodEnd'] ?? '') ?>
    · Scope: <?= $esc($scopeLabel) ?>
    · Gegenereerd: <?= $esc($report['generatedAt'] ?? '') ?>
  </p>

  <table class="metrics">
    <tr>
      <td>
        <span class="metric-label">Articles strategy</span>
        <span class="metric-value"><?= $esc((string) ($report['pctOnStrategy'] ?? 0)) ?>%</span>
      </td>
      <td>
        <span class="metric-label">Articles generic</span>
        <span class="metric-value"><?= $esc((string) ($report['pctGeneral'] ?? 0)) ?>%</span>
      </td>
      <td>
        <span class="metric-label">Engagement diff</span>
        <span class="metric-value"><?= $esc((string) ($report['engagementDiffPct'] ?? 0)) ?>%</span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="metric-label">Engagement on-strategy</span>
        <span class="metric-value"><?= $esc((string) ($report['engagementOnStrategy'] ?? 0)) ?></span>
      </td>
      <td>
        <span class="metric-label">Engagement general</span>
        <span class="metric-value"><?= $esc((string) ($report['engagementGeneral'] ?? 0)) ?></span>
      </td>
      <td>
        <span class="metric-label">Published articles</span>
        <span class="metric-value"><?= $esc((string) ($report['articleCountTotal'] ?? 0)) ?></span>
      </td>
    </tr>
  </table>

  <?php if (($websiteAnalytics['hasAnalytics'] ?? false) && $gaTotals !== []): ?>
  <h2>Website analytics (GA4)</h2>
  <table class="metrics">
    <tr>
      <td>
        <span class="metric-label">Active users</span>
        <span class="metric-value"><?= $esc((string) ($gaTotals['activeUsers'] ?? 0)) ?></span>
      </td>
      <td>
        <span class="metric-label">New users</span>
        <span class="metric-value"><?= $esc((string) ($gaTotals['newUsers'] ?? 0)) ?></span>
      </td>
      <td>
        <span class="metric-label">Sessions</span>
        <span class="metric-value"><?= $esc((string) ($gaTotals['sessions'] ?? 0)) ?></span>
      </td>
    </tr>
    <tr>
      <td colspan="3">
        <span class="metric-label">Page views</span>
        <span class="metric-value"><?= $esc((string) ($gaTotals['screenPageViews'] ?? 0)) ?></span>
      </td>
    </tr>
  </table>

  <?php if ($gaTopPages !== []): ?>
  <h2>Top pages</h2>
  <table class="data">
    <thead>
      <tr>
        <th>Page</th>
        <th>Views</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gaTopPages as $row): ?>
        <?php if (!is_array($row)) {
            continue;
        } ?>
      <tr>
        <td><?= $esc($row['pageTitle'] ?? '') ?></td>
        <td><?= $esc((string) ($row['views'] ?? 0)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($gaSourceMedium !== []): ?>
  <h2>Active users by source / medium</h2>
  <table class="data">
    <thead>
      <tr>
        <th>Source / medium</th>
        <th>Active users</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gaSourceMedium as $row): ?>
        <?php if (!is_array($row)) {
            continue;
        } ?>
      <tr>
        <td><?= $esc($row['label'] ?? '') ?></td>
        <td><?= $esc((string) ($row['activeUsers'] ?? 0)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($gaWeekly !== []): ?>
  <h2>Website traffic per week</h2>
  <table class="data">
    <thead>
      <tr>
        <th>Week</th>
        <th>Page views</th>
        <th>Sessions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gaWeekly as $row): ?>
        <?php if (!is_array($row)) {
            continue;
        } ?>
      <tr>
        <td><?= $esc($row['weekLabel'] ?? '') ?></td>
        <td><?= $esc((string) ($row['websiteViews'] ?? 0)) ?></td>
        <td><?= $esc((string) ($row['sessions'] ?? 0)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (($linkedIn['hasSource'] ?? false) && $linkedInTotals !== []): ?>
  <h2>LinkedIn engagement (eigen posts)</h2>
  <p>
    <?= $esc((string) ($linkedInTotals['posts'] ?? 0)) ?> posts —
    <?= $esc((string) ($linkedInTotals['reactions'] ?? 0)) ?> reacties,
    <?= $esc((string) ($linkedInTotals['likes'] ?? 0)) ?> likes,
    <?= $esc((string) ($linkedInTotals['comments'] ?? 0)) ?> comments
  </p>
  <?php if ($linkedInWeekly !== []): ?>
  <table class="data">
    <thead>
      <tr>
        <th>Week</th>
        <th>Posts</th>
        <th>Reacties</th>
        <th>Likes</th>
        <th>Comments</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($linkedInWeekly as $row): ?>
        <?php if (!is_array($row)) {
            continue;
        } ?>
      <tr>
        <td><?= $esc($row['weekLabel'] ?? '') ?></td>
        <td><?= $esc((string) ($row['posts'] ?? 0)) ?></td>
        <td><?= $esc((string) ($row['reactions'] ?? 0)) ?></td>
        <td><?= $esc((string) ($row['likes'] ?? 0)) ?></td>
        <td><?= $esc((string) ($row['comments'] ?? 0)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($daily !== []): ?>
  <h2>Daily breakdown</h2>
  <table class="data">
    <thead>
      <tr>
        <th>Date</th>
        <th>S views</th>
        <th>S likes</th>
        <th>S comments</th>
        <th>G views</th>
        <th>G likes</th>
        <th>G comments</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($daily as $row): ?>
        <?php if (!is_array($row)) {
            continue;
        } ?>
      <tr>
        <td><?= $esc($row['publish_date'] ?? $row['publishDate'] ?? '') ?></td>
        <td><?= $esc($row['strategy_views'] ?? $row['strategyViews'] ?? 0) ?></td>
        <td><?= $esc($row['strategy_likes'] ?? $row['strategyLikes'] ?? 0) ?></td>
        <td><?= $esc($row['strategy_comments'] ?? $row['strategyComments'] ?? 0) ?></td>
        <td><?= $esc($row['general_views'] ?? $row['generalViews'] ?? 0) ?></td>
        <td><?= $esc($row['general_likes'] ?? $row['generalLikes'] ?? 0) ?></td>
        <td><?= $esc($row['general_comments'] ?? $row['generalComments'] ?? 0) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($angle !== []): ?>
  <h2>Angle distribution</h2>
  <ul class="dist">
    <?php foreach ($angle as $key => $val): ?>
      <li><?= $esc($key) ?>: <?= $esc($val) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($tone !== []): ?>
  <h2>Tone distribution</h2>
  <ul class="dist">
    <?php foreach ($tone as $key => $val): ?>
      <li><?= $esc($key) ?>: <?= $esc($val) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($best !== null): ?>
  <h2>Best performing article</h2>
  <div class="highlight"><?= $esc($best['headline'] ?? '—') ?></div>
  <?php endif; ?>

  <?php if (!empty($report['narrative'])): ?>
  <h2>Insight</h2>
  <div class="insight"><?= nl2br($esc($report['narrative'])) ?></div>
  <?php endif; ?>
</body>
</html>
    <?php

    return (string) ob_get_clean();
}

/** @param array<string, mixed> $report */
function ce_report_pdf_render(array $report): string
{
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml(ce_report_pdf_html($report));
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
