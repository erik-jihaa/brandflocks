<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth-layout.php';
require_once __DIR__ . '/api/password-reset-repository.php';

if (li_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$message = '';
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!li_csrf_verify()) {
        $error = 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.';
    } else {
        try {
            $pdo = li_pdo();
            $result = li_request_password_reset($pdo, $email);
            $message = (string) ($result['message'] ?? '');
        } catch (Throwable $e) {
            $error = 'Verzoek mislukt. Probeer het later opnieuw.';
        }
    }
}

li_auth_layout_start('LinkedIn Scan — wachtwoord vergeten');
?>
      <h1 class="page-title">Forgot password</h1>
      <form method="post" class="form-stack form-offset-header">
        <?= li_csrf_field() ?>
        <p class="auth-intro">
          Vul het e-mailadres in dat bij je account hoort. Je ontvangt een link om een nieuw wachtwoord in te stellen.
        </p>
        <?php if ($error !== ''): ?>
          <p class="notice-text notice-text-error" role="alert"><?= li_h($error) ?></p>
        <?php endif; ?>
        <?php if ($message !== ''): ?>
          <p class="notice-text notice-text-info" role="status"><?= li_h($message) ?></p>
        <?php endif; ?>
        <input
          class="input-field"
          type="email"
          id="forgot_email"
          name="email"
          placeholder="E-mail"
          aria-label="E-mail"
          value="<?= li_h($email) ?>"
          required
          autofocus
          autocomplete="email"
          <?= $message !== '' ? 'readonly' : '' ?>
        >
        <div class="actions">
          <button class="pill pill-primary" type="submit" <?= $message !== '' ? 'disabled' : '' ?>>
            <?= $message !== '' ? 'Reset-link verstuurd' : 'Verstuur reset-link' ?>
          </button>
          <a class="pill pill-secondary" href="index.php">Terug naar login</a>
        </div>
      </form>
<?php
li_auth_layout_end();
