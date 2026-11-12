<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

function ce_nav_items(): array
{
    return [
        'home'     => ['label' => 'Home',     'href' => ce_url('index.php')],
        'strategy' => ['label' => 'BrandElements', 'href' => ce_url('strategy.php')],
        'sources'  => ['label' => 'Sources',  'href' => ce_url('sources.php')],
        'agenda'   => ['label' => 'Agenda',   'href' => ce_url('agenda.php')],
        'article'  => ['label' => 'Article',  'href' => ce_url('article.php')],
        'research' => ['label' => 'Research', 'href' => ce_url('research.php')],
        'theme'    => ['label' => 'Theme',    'href' => ce_url('theme.php')],
        'archive'  => ['label' => 'Archive',  'href' => ce_url('archive.php')],
        'report'   => ['label' => 'Report',   'href' => ce_url('report.php')],
        'fastlane' => ['label' => 'Fastlane', 'href' => ce_url('fastlane.php')],
        'account'  => ['label' => 'Account',  'href' => ce_url('account.php')],
    ];
}

function ce_render_layout_start(string $title, string $activeNav = 'agenda', ?string $intro = null, array $options = []): void
{
    $navItems = ce_nav_items();
    $extraCss = $options['extra_css'] ?? [];
    $hideHeader = $options['hide_header'] ?? false;
    ?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> · Brandflocks</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= ce_url('assets/css/app.css') ?>">
<?php foreach ($extraCss as $css): ?>
  <link rel="stylesheet" href="<?= ce_url($css) ?>">
<?php endforeach; ?>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar" aria-label="Hoofdnavigatie">
      <div class="sidebar__logo">
        <a href="<?= ce_url() ?>">
          <img src="<?= ce_url('svg/iGiSNext_Blue-main.svg') ?>" alt="Brandflocks" class="platform-logo" width="180" height="32">
        </a>
      </div>

      <nav class="sidebar__nav" aria-label="Hoofdmenu">
        <?php foreach ($navItems as $key => $item): ?>
          <a
            class="nav-item<?= $key === $activeNav ? ' is-active' : '' ?>"
            href="<?= htmlspecialchars($item['href']) ?>"
            <?= $key === $activeNav ? 'aria-current="page"' : '' ?>
          ><?= htmlspecialchars($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar__footer">
        <a class="nav-item" href="<?= ce_url('support.php') ?>">Support</a>
        <a class="nav-item" href="<?= ce_url('logout.php') ?>">Logout</a>
      </div>
    </aside>

    <main class="main<?= $hideHeader ? ' main--flush' : '' ?>">
<?php if (!$hideHeader): ?>
      <header class="page-header">
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <?php if ($intro !== null): ?>
          <p class="page-intro"><?= htmlspecialchars($intro) ?></p>
        <?php endif; ?>
      </header>
<?php endif; ?>
<?php
}

function ce_render_layout_end(array $options = []): void
{
    $extraJs = $options['extra_js'] ?? [];
    ?>
    </main>
  </div>
<?php foreach ($extraJs as $js): ?>
  <script src="<?= ce_url($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
