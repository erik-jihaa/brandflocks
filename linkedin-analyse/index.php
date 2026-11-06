<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$loginError = '';
$loginUnavailable = li_app_users() === [];
$resetSuccess = isset($_GET['reset']) && (string) $_GET['reset'] === '1';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($loginUnavailable) {
        $loginError = 'Inloggen tijdelijk niet mogelijk.';
    } elseif (!li_csrf_verify()) {
        $loginError = 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $loginEmail = trim((string) ($_POST['login_email'] ?? ''));
        $loginPassword = (string) ($_POST['login_password'] ?? '');
        $ip = li_client_ip();
        try {
            $pdoLogin = li_pdo();
            if (li_login_is_blocked($pdoLogin, $loginEmail, $ip)) {
                $loginError = 'Te veel pogingen, probeer het later opnieuw.';
            } elseif (li_attempt_login($loginEmail, $loginPassword)) {
                li_login_clear_failures($pdoLogin, strtolower($loginEmail));
                header('Location: index.php');
                exit;
            } else {
                li_login_record_failure($pdoLogin, $loginEmail, $ip);
                $loginError = 'Onjuist e-mailadres of wachtwoord.';
            }
        } catch (Throwable) {
            $loginError = 'Inloggen tijdelijk niet mogelijk.';
        }
    }
}

if (!li_is_logged_in()) {
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LinkedIn Scan — login</title>
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
    </div>
    <div class="card login-box">
      <h1 class="page-title">LinkedIn Scan</h1>
      <?php if ($resetSuccess): ?>
        <p class="notice-text notice-text-info" role="status">Je wachtwoord is gewijzigd. Log in met je nieuwe wachtwoord.</p>
      <?php endif; ?>
      <?php if ($loginUnavailable): ?>
        <p class="notice-text notice-text-error" role="alert">Inloggen tijdelijk niet mogelijk.</p>
      <?php elseif ($loginError !== ''): ?>
        <p class="notice-text notice-text-error" role="alert"><?= li_h($loginError) ?></p>
      <?php endif; ?>
      <?php if (!$loginUnavailable): ?>
      <form method="post" class="form-stack form-offset-header">
        <?= li_csrf_field() ?>
        <input
          class="input-field"
          type="email"
          id="login_email"
          name="login_email"
          placeholder="E-mail"
          aria-label="E-mail"
          required
          autofocus
          autocomplete="username"
        >
        <input
          class="input-field"
          type="password"
          id="login_password"
          name="login_password"
          placeholder="Wachtwoord"
          aria-label="Wachtwoord"
          required
          autocomplete="current-password"
        >
        <div class="actions">
          <button class="pill pill-primary" type="submit">Inloggen</button>
          <a class="pill pill-secondary" href="forgot-password.php">Forgot password</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
    <?php
    exit;
}

li_require_auth();

$deleteError = '';
$deleteSuccess = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_scan_id'])) {
    if (!li_csrf_verify()) {
        $deleteError = 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $deleteId = trim((string) $_POST['delete_scan_id']);
        if ($deleteId !== '') {
            try {
                $pdoDelete = li_pdo();
                if (li_scan_delete_by_id($pdoDelete, $deleteId)) {
                    header('Location: index.php?deleted=1');
                    exit;
                }
                $deleteError = 'Scan niet gevonden of al verwijderd.';
            } catch (Throwable $e) {
                $deleteError = 'Verwijderen mislukt.';
            }
        }
    }
}

$estimateEur = null;
$dbError = null;
$recentScans = [];
try {
    $pdo = li_pdo();
    $estimateEur = li_scan_estimate_cost_eur($pdo, (float) li_config('usd_to_eur', 0.92));
    $recentScans = li_scan_list_recent($pdo, 40);
} catch (Throwable $e) {
    $dbError = 'Database niet bereikbaar — controleer config.local.php en sql/schema.sql.';
}

$prefillUrl = trim((string) ($_GET['url'] ?? ''));
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LinkedIn Scan</title>
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

    <?php if ($deleteSuccess): ?>
      <p class="notice-text notice-text-success notice-fade" role="status" aria-live="polite">Scan verwijderd.</p>
    <?php endif; ?>
    <?php if ($deleteError !== ''): ?>
      <p class="notice-text notice-text-error notice-fade" role="alert"><?= li_h($deleteError) ?></p>
    <?php endif; ?>

    <?php if ($dbError !== null): ?>
      <p class="notice-text notice-text-error" role="alert"><?= li_h($dbError) ?></p>
    <?php endif; ?>

    <div class="card">
      <form method="post" action="scan.php" class="form-stack" id="form-scan-start">
        <?= li_csrf_field() ?>
        <input
          class="input-field"
          type="url"
          id="company_url"
          name="company_url"
          required
          placeholder="https://www.linkedin.com/company/bedrijfsnaam/ of …/school/naam/"
          aria-label="LinkedIn bedrijfs- of schoolpagina-URL"
          value="<?= li_h($prefillUrl) ?>"
        >
        <?= li_render_pulldown(
            'branche_code',
            li_branche_pulldown_options(),
            '',
            'Branche (optioneel)',
            ['aria-label' => 'Branche'],
        ) ?>
        <p class="hint">
          Helpt clichés in deze markt te herkennen bij het beoordelen van de kernboodschap — optioneel.
        </p>
        <textarea
          class="input-field textarea-field"
          id="company_description"
          name="company_description"
          rows="3"
          maxlength="2000"
          placeholder="Wat doet dit bedrijf? (kort, in eigen woorden)"
          aria-label="Wat doet dit bedrijf? (kort, in eigen woorden)"
        ></textarea>
        <?= li_render_pulldown(
            'contextprofiel',
            li_context_profile_pulldown_options(),
            '',
            'Contextprofiel (optioneel)',
            ['aria-label' => 'Contextprofiel'],
        ) ?>
        <p class="hint">
          Bedrijfs- (<code>/company/</code>) en schoolpagina&apos;s (<code>/school/</code>).
          Maximaal <?= (int) li_config('max_posts', 30) ?> posts per scan.
          <?php if ($estimateEur !== null): ?>
            Geschatte kosten: circa € <?= number_format($estimateEur, 2, ',', '.') ?>.
          <?php endif; ?>
        </p>
        <div class="actions">
          <button class="pill pill-primary" type="submit" id="btn-start-scan" <?= $dbError !== null ? 'disabled' : '' ?>>
            <span class="pill-label">Scan starten</span>
          </button>
          <label class="ce-checkbox-option">
            <input type="checkbox" name="force_refresh" value="1" class="ce-visually-hidden">
            <span class="ce-radio-indicator" aria-hidden="true"></span>
            <span>Opnieuw ophalen (negeer cache)</span>
          </label>
        </div>
      </form>
    </div>

    <?php if ($dbError === null && $recentScans !== []): ?>
      <section class="list-section">
        <h2 class="list-section-title">Eerdere scans</h2>
        <div class="list-section-body">
          <div class="list-section-stack">
            <?php foreach ($recentScans as $scan): ?>
              <?php
                $scanId = (string) ($scan['id'] ?? '');
                $name = trim((string) ($scan['company_name'] ?? ''));
                if ($name === '') {
                    $name = (string) ($scan['company_slug'] ?? 'Onbekend');
                }
                $createdAt = substr((string) ($scan['created_at'] ?? ''), 0, 10);
                $dateLabel = $createdAt !== '' ? li_format_dutch_date($createdAt) : '—';
                $reportOpened = li_scan_report_was_opened($scan);
                $reportStatusClass = $reportOpened ? 'report-status-open' : 'report-status-closed';
              ?>
              <div class="list-row">
                <span class="list-row-title">
                  <a class="row-title-link row-title-link-client" href="scan.php?id=<?= li_h($scanId) ?>"><?= li_h($name) ?></a>
                </span>
                <div class="list-row-actions">
                  <span class="status-label status-label-muted"><?= li_h($dateLabel) ?></span>
                  <span class="action-sep" aria-hidden="true">·</span>
                  <span class="status-label <?= li_h($reportStatusClass) ?>" title="<?= $reportOpened ? 'Rapport geopend' : 'Rapport nog niet geopend' ?>">Rapport</span>
                  <span class="action-sep" aria-hidden="true">|</span>
                  <a class="action-link" href="scan.php?id=<?= li_h($scanId) ?>">View</a>
                  <span class="action-sep" aria-hidden="true">|</span>
                  <form method="post" class="action-delete-form">
                    <?= li_csrf_field() ?>
                    <input type="hidden" name="delete_scan_id" value="<?= li_h($scanId) ?>">
                    <button type="button" class="action-link action-link-delete" data-delete-confirm>Delete</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
  <script src="assets/pulldown.js"></script>
  <script src="assets/delete-confirm.js"></script>
  <script>
    (function () {
      var form = document.getElementById('form-scan-start');
      var btn = document.getElementById('btn-start-scan');
      if (!form || !btn) return;

      form.addEventListener('submit', function () {
        if (btn.disabled || btn.classList.contains('is-busy')) return;
        btn.disabled = true;
        btn.classList.add('is-busy');
        btn.setAttribute('aria-busy', 'true');
        var label = btn.querySelector('.pill-label');
        if (label) {
          label.innerHTML = 'Bezig met scannen<span class="pill-thinking-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>';
        }
      });
    })();
  </script>
  <?php if ($deleteSuccess || $deleteError !== ''): ?>
  <script>
    (function () {
      if (window.location.search.indexOf('deleted=1') !== -1) {
        var url = new URL(window.location.href);
        url.searchParams.delete('deleted');
        window.history.replaceState({}, '', url.pathname + url.search);
      }
      document.querySelectorAll('.notice-fade').forEach(function (el) {
        window.setTimeout(function () { el.classList.add('is-fading'); }, 5000);
        window.setTimeout(function () { el.remove(); }, 5500);
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>
