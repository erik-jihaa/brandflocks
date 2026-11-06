<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? ''));
if ($token === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Rapport niet gevonden.\n";
    exit;
}

try {
    $pdo = li_pdo();
    $row = li_scan_find_by_report_token($pdo, $token);
    if ($row === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Rapport niet gevonden.\n";
        exit;
    }

    // Nooit Claude vanaf de publieke link — alleen opgeslagen metrics (briefing A4).
    $metrics = li_scan_metrics_from_row($pdo, $row, false, false);
    if ($metrics === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Rapport niet beschikbaar.\n";
        exit;
    }

    li_scan_record_report_open($pdo, (string) ($row['id'] ?? ''));

    $companyName = (string) ($metrics['company_name'] ?? $row['company_name'] ?? 'LinkedIn-pagina');
    $scanReferenceYmd = substr((string) ($row['created_at'] ?? ''), 0, 10);
    $scanDate = li_format_dutch_date($scanReferenceYmd);
    $reportHtml = li_render_full_report_html($metrics, $scanDate, $scanReferenceYmd);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fout bij laden van rapport.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= li_h('Scan — ' . $companyName) ?></title>
  <?php li_head_favicon(); ?>
  <link rel="stylesheet" href="assets/app.css">
  <style>
    body { background: #EAE6DC; }
    .report-public-wrap { max-width: 500px; margin: 0 auto; padding: 24px 16px 48px; }
    .report-public-note { font-size: 14px; color: #6B5A45; margin: 0 0 16px; }
  </style>
</head>
<body>
  <div class="report-public-wrap">
    <p class="report-public-note">Volledig scanrapport — alleen via deze link bereikbaar.</p>
    <?= $reportHtml ?>
  </div>
</body>
</html>
