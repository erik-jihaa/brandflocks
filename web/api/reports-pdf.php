<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/report-repository.php';
require_once __DIR__ . '/report-pdf-builder.php';

$reportId = trim((string) ($_GET['report_id'] ?? ''));
if ($reportId === '') {
    http_response_code(400);
    echo 'report_id ontbreekt';
    exit;
}

$pdo = ce_db();

try {
    $data = ce_fetch_report($pdo, $reportId);
} catch (RuntimeException) {
    http_response_code(404);
    echo 'Report niet gevonden';
    exit;
}

$report = $data['report'];
$previewHtml = isset($_GET['format']) && (string) $_GET['format'] === 'html';

if ($previewHtml) {
    header('Content-Type: text/html; charset=utf-8');
    echo ce_report_pdf_html($report);
    exit;
}

try {
    $pdf = ce_report_pdf_render($report);
} catch (Throwable $e) {
    http_response_code(500);
    $message = 'PDF genereren mislukt';
    if (getenv('CE_DEBUG') === '1') {
        $message .= ': ' . $e->getMessage();
    }
    echo $message;
    exit;
}

$filename = ce_report_pdf_filename($report);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf;
