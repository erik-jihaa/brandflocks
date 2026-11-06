<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

li_require_auth();

@set_time_limit(300);
@ini_set('max_execution_time', '300');

$scanId = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
// Opnieuw ophalen alleen via POST + CSRF (geen ?refresh=1).
$forceRefresh = $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['force_refresh'])
    && (string) $_POST['force_refresh'] === '1';
$showCachedNotice = isset($_GET['cached']) && (string) $_GET['cached'] === '1';
$showBrancheNotice = isset($_GET['branche']) && (string) $_GET['branche'] === '1';
$showContextNotice = isset($_GET['context']) && (string) $_GET['context'] === '1';
$error = '';
$notice = '';
$result = null;
$metrics = null;
$companyName = '';
$companyUrl = '';
$reportUrl = '';
$reportPublicUrl = '';
$reportLinkExpired = false;
$reportOpened = false;
$outreachHtml = '';
$fullReportHtml = '';
$isPending = false;
$currentBrancheRaw = '';
$currentCompanyDescription = '';
$currentContextProfiel = '';
$contextUpdateNotice = '';
$contextUpdateError = '';

try {
    $pdo = li_pdo();
    $cacheDays = (int) li_config('cache_days', 7);
    $retentionDays = (int) li_config('raw_data_retention_days', 90);
    li_scan_run_scheduled_maintenance($pdo, $retentionDays);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !li_csrf_verify()) {
        $error = 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_scan_context'])) {
        $updateScanId = trim((string) ($_POST['id'] ?? ''));
        $context = li_scan_normalize_context(
            trim((string) ($_POST['branche_code'] ?? '')),
            trim((string) ($_POST['company_description'] ?? '')),
            trim((string) ($_POST['contextprofiel'] ?? '')),
        );
        if ($updateScanId === '') {
            $contextUpdateError = 'Scan niet gevonden.';
        } else {
            $row = li_scan_find_by_id($pdo, $updateScanId);
            if ($row === null) {
                $contextUpdateError = 'Scan niet gevonden.';
            } else {
                $recompute = li_scan_recompute_narrative(
                    $pdo,
                    $row,
                    $context['branche_code'],
                    $context['company_description'],
                    $context['contextprofiel'],
                );
                if ($recompute['ok'] ?? false) {
                    header('Location: scan.php?id=' . urlencode($updateScanId) . '&context=1');
                    exit;
                }
                $contextUpdateError = (string) ($recompute['error'] ?? 'Scancontext bijwerken mislukt.');
            }
        }
        $scanId = $updateScanId;
    } elseif ($forceRefresh && $scanId !== '') {
        $row = li_scan_find_by_id($pdo, $scanId);
        if ($row === null) {
            $error = 'Scan niet gevonden.';
        } else {
            $slug = (string) ($row['company_slug'] ?? '');
            $url = (string) ($row['company_url'] ?? '');
            $ctx = li_scan_context_from_row($row);
            if ($slug === '') {
                $error = 'Scan heeft geen pagina-slug.';
            } else {
                $pageType = li_linkedin_page_type_from_url($url);
                $refreshResult = li_scan_run_and_store(
                    $pdo,
                    $url,
                    $slug,
                    $pageType,
                    true,
                    $cacheDays,
                    $ctx['branche_code'],
                    $ctx['company_description'],
                    $ctx['contextprofiel'],
                );
                if ($refreshResult['ok'] ?? false) {
                    header('Location: scan.php?id=' . urlencode((string) $refreshResult['scan_id']));
                    exit;
                }
                $error = (string) ($refreshResult['error'] ?? 'Opnieuw ophalen mislukt.');
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $companyUrl = trim((string) ($_POST['company_url'] ?? ''));
        $context = li_scan_normalize_context(
            trim((string) ($_POST['branche_code'] ?? '')),
            trim((string) ($_POST['company_description'] ?? '')),
            trim((string) ($_POST['contextprofiel'] ?? '')),
        );
        $page = li_linkedin_parse_page_url($companyUrl);
        if ($page === null) {
            $error = 'Voer een geldige LinkedIn bedrijfs- of schoolpagina in (linkedin.com/company/… of linkedin.com/school/…).';
        } else {
            $slug = $page['slug'];
            $canonicalUrl = $page['url'];
            $pageType = $page['type'];
            $bypassCache = isset($_POST['force_refresh']) && (string) $_POST['force_refresh'] === '1';
            $result = li_scan_run_and_store(
                $pdo,
                $canonicalUrl,
                $slug,
                $pageType,
                $bypassCache,
                $cacheDays,
                $context['branche_code'],
                $context['company_description'],
                $context['contextprofiel'],
            );
            if (!($result['ok'] ?? false)) {
                $error = (string) ($result['error'] ?? 'Scan mislukt.');
            } else {
                $redirect = 'scan.php?id=' . urlencode((string) $result['scan_id']);
                if (!empty($result['cached'])) {
                    $redirect .= '&cached=1';
                }
                header('Location: ' . $redirect);
                exit;
            }
        }
    } elseif ($scanId !== '') {
        $row = li_scan_find_by_id($pdo, $scanId);
        if ($row === null) {
            $error = 'Scan niet gevonden.';
        } elseif (($row['status'] ?? '') === 'running') {
            $poll = li_scan_poll_running($pdo, $row);
            if ($poll['ok'] ?? false) {
                if (!empty($poll['pending'])) {
                    $isPending = true;
                    $companyUrl = (string) ($row['company_url'] ?? '');
                    $companyName = (string) ($row['company_name'] ?? $row['company_slug'] ?? '');
                    $notice = 'Posts ophalen bij Apify — dit kan 1–3 minuten duren. De pagina ververs automatisch.';
                } else {
                    $row = li_scan_find_by_id($pdo, $scanId) ?? $row;
                }
            } else {
                $error = (string) ($poll['error'] ?? 'Scan mislukt.');
            }
        }

        if ($scanId !== '' && $error === '' && !$isPending) {
            if ($row === null) {
                $error = 'Scan niet gevonden.';
            } elseif (($row['status'] ?? '') === 'failed') {
                $error = (string) ($row['error_message'] ?? 'Scan mislukt.');
            } elseif (($row['status'] ?? '') === 'completed') {
            $currentBrancheRaw = (string) ($row['branche_code'] ?? '');
            $currentCompanyDescription = (string) ($row['company_description'] ?? '');
            $currentContextProfiel = (string) ($row['contextprofiel'] ?? '');
            $metrics = li_scan_metrics_from_row($pdo, $row);
            if ($metrics === null) {
                $error = 'Geen metrieken beschikbaar voor deze scan.';
            } else {
                $companyName = (string) ($metrics['company_name'] ?? $row['company_name'] ?? '');
                $companyUrl = (string) ($row['company_url'] ?? '');
                $scanDate = li_format_dutch_date(substr((string) ($row['created_at'] ?? ''), 0, 10));
                $scanReferenceYmd = substr((string) ($row['created_at'] ?? ''), 0, 10);
                $reportLinkExpired = li_scan_report_link_expired($row);
                $reportToken = $reportLinkExpired ? '' : li_scan_ensure_report_token($pdo, $scanId);
                $reportPublicUrl = li_report_public_url($reportToken);
                $reportOpened = li_scan_report_was_opened($row);
                $outreachHtml = li_render_outreach_email_html($metrics, $reportPublicUrl, $scanDate, $scanReferenceYmd);
                $fullReportHtml = li_render_full_report_html($metrics, $scanDate, $scanReferenceYmd);
                $clipboardHtml = li_render_report_clipboard_html($outreachHtml);
                $plainText = li_render_outreach_plaintext($metrics, $reportPublicUrl, $scanDate, $scanReferenceYmd);
                $result = [
                    'scan_id' => $scanId,
                    'cached' => false,
                    'cost_usd' => $row['cost_usd'] !== null ? (float) $row['cost_usd'] : null,
                    'cost_eur' => $row['cost_usd'] !== null
                        ? round((float) $row['cost_usd'] * (float) li_config('usd_to_eur', 0.92), 4)
                        : null,
                    'posts_count' => (int) ($row['posts_count'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
            }
        }
    } else {
        header('Location: index.php');
        exit;
    }
} catch (Throwable $e) {
    $error = 'Fout: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $companyName !== '' ? li_h('Scan — ' . $companyName) : 'LinkedIn Scan' ?></title>
  <?php li_head_favicon(); ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
  <div id="page-shell">
    <div class="brand-row">
      <?php li_brand_logo(); ?>
      <h1 class="page-title">LinkedIn Scan</h1>
      <a class="logout-link" href="index.php?logout=1">Uitloggen</a>
    </div>

    <p><a class="nav-back-link" href="index.php">← Nieuwe scan</a></p>

    <?php if ($showCachedNotice): ?>
      <div class="notice notice-info">
        Dit resultaat komt uit de cache (max. <?= (int) li_config('cache_days', 7) ?> dagen oud).
        Klik <em>Opnieuw ophalen</em> voor een verse Apify-run — dat kan 1–2 minuten duren.
      </div>
    <?php endif; ?>

    <?php if ($showBrancheNotice || $showContextNotice): ?>
      <div class="notice notice-info" role="status">Scancontext bijgewerkt — narratief opnieuw berekend (geen nieuwe Apify-run).</div>
    <?php endif; ?>

    <?php if ($contextUpdateError !== ''): ?>
      <p class="notice-text notice-text-error" role="alert"><?= li_h($contextUpdateError) ?></p>
    <?php endif; ?>

    <?php if ($notice !== ''): ?>
      <div class="notice notice-info" role="status"><?= li_h($notice) ?></div>
    <?php endif; ?>

    <?php if ($isPending): ?>
      <div class="notice notice-info" role="status">
        <strong>Bezig met ophalen…</strong> Laat dit tabblad open — de pagina ververs elke paar seconden tot het rapport klaar is.
      </div>
      <meta http-equiv="refresh" content="8">
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <p class="notice-text notice-text-error" role="alert"><?= li_h($error) ?></p>
    <?php endif; ?>

    <?php if ($metrics !== null && $outreachHtml !== ''): ?>
      <form method="post" action="scan.php" class="scan-context-form">
        <?= li_csrf_field() ?>
        <input type="hidden" name="id" value="<?= li_h($scanId) ?>">
        <input type="hidden" name="update_scan_context" value="1">
        <?= li_render_pulldown(
            'branche_code',
            li_branche_pulldown_options(),
            $currentBrancheRaw,
            'Branche (optioneel)',
            [
                'id' => 'scan_branche_code',
                'class' => 'cf-pulldown-narrow',
                'aria-label' => 'Branche voor deze scan',
            ],
        ) ?>
        <textarea
          class="input-field textarea-field cf-pulldown-narrow"
          id="scan_company_description"
          name="company_description"
          rows="3"
          maxlength="2000"
          placeholder="Wat doet dit bedrijf? (kort, in eigen woorden)"
          aria-label="Wat doet dit bedrijf? (kort, in eigen woorden)"
        ><?= li_h($currentCompanyDescription) ?></textarea>
        <?= li_render_pulldown(
            'contextprofiel',
            li_context_profile_pulldown_options(),
            $currentContextProfiel,
            'Contextprofiel (optioneel)',
            [
                'id' => 'scan_contextprofiel',
                'class' => 'cf-pulldown-narrow',
                'aria-label' => 'Contextprofiel voor deze scan',
            ],
        ) ?>
        <div class="scan-context-actions">
          <button type="submit" class="pill pill-secondary">Context toepassen</button>
        </div>
      </form>
      <p class="hint">Context wijzigen herberekent alleen het merkverhaal — geen nieuwe Apify-run.</p>

      <div class="meta-row">
        <?php if ($companyUrl !== ''): ?>
          <span><?= li_h($companyUrl) ?></span>
        <?php endif; ?>
        <?php if (is_array($result) && isset($result['posts_count'])): ?>
          <span><?= (int) $result['posts_count'] ?> posts</span>
        <?php endif; ?>
        <?php if (is_array($result) && !empty($result['created_at'])): ?>
          <span>Scan: <?= li_h(li_format_dutch_date(substr((string) $result['created_at'], 0, 10))) ?></span>
        <?php endif; ?>
        <?php if (is_array($result) && $result['cost_eur'] !== null): ?>
          <span>Kosten: € <?= number_format((float) $result['cost_eur'], 2, ',', '.') ?></span>
        <?php endif; ?>
      </div>

      <div class="report-actions">
        <button type="button" class="pill pill-primary" id="btn-copy-email">Kopieer outreach-mail</button>
        <?php if ($reportPublicUrl !== ''): ?>
          <button type="button" class="pill pill-secondary" id="btn-copy-link">Kopieer rapport-link</button>
        <?php endif; ?>
        <button type="button" class="pill pill-secondary" id="btn-download-html">Download outreach-mail</button>
        <button type="button" class="pill pill-secondary" id="btn-download-report">Download volledig rapport</button>
        <?php if ($scanId !== ''): ?>
          <form method="post" action="scan.php" class="refresh-form" id="form-refresh">
            <?= li_csrf_field() ?>
            <input type="hidden" name="id" value="<?= li_h($scanId) ?>">
            <input type="hidden" name="force_refresh" value="1">
            <button type="submit" class="pill pill-secondary" id="btn-refresh">Opnieuw ophalen</button>
          </form>
        <?php endif; ?>
      </div>
      <p class="notice-text notice-text-success" id="copy-status" role="status" aria-live="polite" hidden></p>

      <div id="report-preview">
        <h2>Outreach-mail</h2>
        <p class="hint">Plak in Mail: intro, scores, cijfers en observaties. Vanaf «Posts op reacties» alleen via de rapport-link online.</p>
        <div id="scan-report-email"><?= $outreachHtml ?></div>
      </div>
      <div id="full-report-preview">
        <h2>Volledig rapport</h2>
        <?php if ($reportLinkExpired): ?>
          <p class="hint">Rapportlink verlopen (bewaartermijn <?= (int) li_scan_metrics_retention_days() ?> dagen).</p>
        <?php elseif ($reportPublicUrl !== ''): ?>
          <p class="hint">Publieke link: <a href="<?= li_h($reportPublicUrl) ?>" target="_blank" rel="noopener"><?= li_h($reportPublicUrl) ?></a></p>
          <p class="report-open-tracker"><span class="<?= $reportOpened ? 'report-status-open' : 'report-status-closed' ?>">Rapport</span></p>
        <?php endif; ?>
        <div id="scan-report-full"><?= $fullReportHtml ?></div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($metrics !== null && $outreachHtml !== ''): ?>
  <script src="assets/pulldown.js"></script>
  <script>
    document.getElementById('form-refresh')?.addEventListener('submit', function () {
      const btn = document.getElementById('btn-refresh');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Bezig met ophalen…';
      }
    });
  </script>
  <script>
    (function () {
      const clipboardHtml = <?= json_encode($clipboardHtml ?? '', JSON_UNESCAPED_UNICODE) ?>;
      const fullReportHtml = <?= json_encode(li_render_report_clipboard_html($fullReportHtml ?? ''), JSON_UNESCAPED_UNICODE) ?>;
      const plainText = <?= json_encode($plainText ?? '', JSON_UNESCAPED_UNICODE) ?>;
      const reportPublicUrl = <?= json_encode($reportPublicUrl ?? '', JSON_UNESCAPED_UNICODE) ?>;
      const companySlug = <?= json_encode($companyName, JSON_UNESCAPED_UNICODE) ?>;

      let copyFadeTimer = null;

      function showCopyStatus(message) {
        const status = document.getElementById('copy-status');
        if (!status) return;
        status.textContent = message;
        status.hidden = false;
        status.classList.remove('is-fading');
        if (copyFadeTimer) {
          window.clearTimeout(copyFadeTimer);
        }
        copyFadeTimer = window.setTimeout(function () {
          status.classList.add('is-fading');
          window.setTimeout(function () {
            status.hidden = true;
            status.classList.remove('is-fading');
          }, 500);
        }, 5000);
      }

      function copyViaWebKit(html) {
        const container = document.createElement('div');
        container.contentEditable = 'true';
        container.style.position = 'fixed';
        container.style.left = '-9999px';
        container.style.top = '0';
        container.innerHTML = html;
        document.body.appendChild(container);
        const selection = window.getSelection();
        if (!selection) {
          document.body.removeChild(container);
          throw new Error('Geen selectie-API');
        }
        const range = document.createRange();
        range.selectNodeContents(container);
        selection.removeAllRanges();
        selection.addRange(range);
        const ok = document.execCommand('copy');
        selection.removeAllRanges();
        document.body.removeChild(container);
        if (!ok) {
          throw new Error('WebKit copy mislukt');
        }
      }

      const isAppleMailPlatform = /Mac|iPhone|iPad/i.test(navigator.userAgent || '');

      async function copyEmailHtml() {
        if (!clipboardHtml) {
          throw new Error('Geen rapport-HTML');
        }

        // Apple Mail (Safari/Chrome op Mac): DOM-selectie werkt betrouwbaarder dan ClipboardItem.
        if (isAppleMailPlatform) {
          copyViaWebKit(clipboardHtml);
          return;
        }

        if (navigator.clipboard && window.ClipboardItem) {
          await navigator.clipboard.write([
            new ClipboardItem({
              'text/html': new Blob([clipboardHtml], { type: 'text/html' }),
              'text/plain': new Blob([plainText || ''], { type: 'text/plain' }),
            }),
          ]);
          return;
        }

        copyViaWebKit(clipboardHtml);
      }

      document.getElementById('btn-copy-email')?.addEventListener('click', async function () {
        try {
          await copyEmailHtml();
          showCopyStatus('Outreach-mail gekopieerd. Open Mail, nieuw bericht, plak met ⌘V.');
        } catch (err) {
          alert('Kopiëren mislukt. Gebruik Download outreach-mail.');
        }
      });

      document.getElementById('btn-copy-link')?.addEventListener('click', async function () {
        if (!reportPublicUrl) return;
        try {
          if (navigator.clipboard) {
            await navigator.clipboard.writeText(reportPublicUrl);
          } else {
            throw new Error('Geen clipboard');
          }
          showCopyStatus('Rapport-link gekopieerd.');
        } catch (err) {
          alert('Link: ' + reportPublicUrl);
        }
      });

      document.getElementById('btn-download-html')?.addEventListener('click', function () {
        const blob = new Blob([clipboardHtml || ''], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'linkedin-outreach-' + (companySlug || 'mail').replace(/\s+/g, '-').toLowerCase() + '.html';
        a.click();
        URL.revokeObjectURL(url);
      });

      document.getElementById('btn-download-report')?.addEventListener('click', function () {
        const blob = new Blob([fullReportHtml || ''], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'linkedin-rapport-' + (companySlug || 'rapport').replace(/\s+/g, '-').toLowerCase() + '.html';
        a.click();
        URL.revokeObjectURL(url);
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>
