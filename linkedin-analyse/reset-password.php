<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth-layout.php';
require_once __DIR__ . '/api/password-reset-repository.php';

if (li_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$email = '';
$loadingError = '';
$submitted = false;

if ($token === '') {
    $loadingError = 'Geen reset-link.';
} else {
    try {
        $pdo = li_pdo();
        $valid = li_validate_password_reset_token($pdo, $token);
        if ($valid['ok']) {
            $email = (string) ($valid['email'] ?? '');
        } else {
            $loadingError = (string) ($valid['error'] ?? 'Link ongeldig of verlopen');
        }
    } catch (Throwable) {
        $loadingError = 'Link controleren mislukt.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loadingError === '' && $token !== '') {
    if (!li_csrf_verify()) {
        $error = 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $passwordRetype = (string) ($_POST['password_retype'] ?? '');

        try {
            $pdo = li_pdo();
            $result = li_complete_password_reset($pdo, $token, $password, $passwordRetype);
            if ($result['ok']) {
                header('Location: index.php?reset=1');
                exit;
            }
            $error = (string) ($result['error'] ?? 'Wachtwoord instellen mislukt');
        } catch (Throwable) {
            $error = 'Wachtwoord instellen mislukt.';
        }
    }
}

li_auth_layout_start('LinkedIn Scan — nieuw wachtwoord');
?>
      <h1 class="page-title">Nieuw wachtwoord</h1>

      <?php if ($loadingError !== ''): ?>
        <div class="form-stack form-offset-header">
          <p class="notice-text notice-text-error" role="alert"><?= li_h($loadingError) ?></p>
          <p class="auth-intro">
            <a class="text-link" href="forgot-password.php">Nieuwe reset-link aanvragen</a>
          </p>
        </div>
      <?php else: ?>
        <form method="post" class="form-stack form-offset-header">
          <?= li_csrf_field() ?>
          <input type="hidden" name="token" value="<?= li_h($token) ?>">
          <?php if ($email !== ''): ?>
            <p class="auth-intro">
              Nieuw wachtwoord voor <strong><?= li_h($email) ?></strong>
            </p>
          <?php endif; ?>
          <?php if ($error !== ''): ?>
            <p class="notice-text notice-text-error" role="alert"><?= li_h($error) ?></p>
          <?php endif; ?>
          <input
            class="input-field"
            type="password"
            id="password"
            name="password"
            placeholder="Nieuw wachtwoord"
            aria-label="Nieuw wachtwoord"
            required
            autofocus
            autocomplete="new-password"
            minlength="8"
          >
          <input
            class="input-field"
            type="password"
            id="password_retype"
            name="password_retype"
            placeholder="Herhaal wachtwoord"
            aria-label="Herhaal wachtwoord"
            required
            autocomplete="new-password"
            minlength="8"
          >
          <div class="actions">
            <button class="pill pill-primary" type="submit">Wachtwoord opslaan</button>
          </div>
        </form>
      <?php endif; ?>
<?php
li_auth_layout_end();
