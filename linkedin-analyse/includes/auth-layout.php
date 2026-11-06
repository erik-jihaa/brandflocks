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

function li_auth_layout_start(string $documentTitle): void
{
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= li_h($documentTitle) ?></title>
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
    <?php
}

function li_auth_layout_end(): void
{
    ?>
    </div>
  </div>
</body>
</html>
    <?php
}
