<?php

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

ce_render_layout_start('Content Engine', 'home', null, ['hide_header' => true]);
?>
      <h1 class="app-title">Content Engine</h1>

      <p class="section-heading">Jouw content-werkplaats</p>

      <p>Schrijf, plan en publiceer artikelen vanuit één plek.
         De Content Engine koppelt strategie, research en redactie
         in een sluitende workflow — van Agenda tot Fastlane.</p>

      <p style="margin-top: 30px;">
        <a class="btn btn--main" href="<?= ce_url('agenda.php') ?>">Naar Agenda</a>
        <a class="btn btn--second" href="<?= ce_url('article.php') ?>" style="margin-left: 12px;">Artikel schrijven</a>
      </p>
<?php
ce_render_layout_end();
