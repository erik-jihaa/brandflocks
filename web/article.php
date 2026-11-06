<?php

declare(strict_types=1);

require __DIR__ . '/includes/layout.php';

/** @var array<string, mixed> $article */
$article = require __DIR__ . '/includes/article-mock.php';

ce_render_layout_start('Week 14', 'article', null, [
    'extra_css'   => ['assets/css/article.css'],
    'hide_header' => true,
]);

$arrow = ce_url('assets/svg/arrow.svg');

/* Hulpfunctie: radio-groep renderen */
function ce_radio(string $name, array $opts, string $selected): void
{
    echo '<fieldset class="radio-list"><legend>' . htmlspecialchars($name) . '</legend>';
    foreach ($opts as $val) {
        $id  = htmlspecialchars($name . '_' . $val);
        $chk = $val === $selected ? ' checked' : '';
        echo '<label class="radio-opt" for="' . $id . '">'
           . '<input type="radio" name="' . htmlspecialchars($name) . '" id="' . $id . '" value="' . htmlspecialchars($val) . '"' . $chk . '>'
           . '<span class="radio-opt__bullet" aria-hidden="true"></span>'
           . '<span>' . htmlspecialchars($val) . '</span>'
           . '</label>';
    }
    echo '</fieldset>';
}
?>

<article class="article-page">

  <h1 class="page-title"><?= htmlspecialchars($article['week_label']) ?></h1>

  <div class="article-layout">

    <!-- ============================================================
         META-KOLOM — Rationale, Tone, Angle, Image ratio, Platform
         ============================================================ -->
    <aside class="article-meta" aria-label="Strategie en keuzes">

      <details class="acc" open>
        <summary>
          <span>Rationale</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="13" height="13">
        </summary>
        <div class="acc__body acc__body--text">
          <?= nl2br(htmlspecialchars($article['rationale'])) ?>
        </div>
      </details>

      <details class="acc" open>
        <summary>
          <span>Tone</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="13" height="13">
        </summary>
        <div class="acc__body">
          <?php ce_radio('tone', $article['tone']['options'], $article['tone']['selected']); ?>
        </div>
      </details>

      <details class="acc" open>
        <summary>
          <span>Angle</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="13" height="13">
        </summary>
        <div class="acc__body">
          <?php ce_radio('angle', $article['angle']['options'], $article['angle']['selected']); ?>
        </div>
      </details>

      <details class="acc" open>
        <summary>
          <span>Image ratio</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="13" height="13">
        </summary>
        <div class="acc__body">
          <?php ce_radio('image_ratio', $article['image_ratio']['options'], $article['image_ratio']['selected']); ?>
        </div>
      </details>

      <details class="acc" open>
        <summary>
          <span>Platform</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="13" height="13">
        </summary>
        <div class="acc__body">
          <?php ce_radio('platform', $article['platform']['options'], $article['platform']['selected']); ?>
        </div>
      </details>

    </aside><!-- /article-meta -->

    <!-- ============================================================
         ARTIKEL-CONTENT
         ============================================================ -->
    <div class="article-main">

      <!-- Gesloten accordion bovenaan (feedback, leeg) -->
      <details class="acc acc--top">
        <summary>
          <span>Feedback</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="13" height="13">
        </summary>
        <div class="acc__body acc__body--text">
          Nog geen feedback ontvangen.
        </div>
      </details>

      <!-- Titel = bewerkbaar veld (PDF: "Title = Post name 1") -->
      <div class="title-field">
        <label class="title-field__label" for="headline">Title =</label>
        <input
          class="title-field__input"
          type="text"
          id="headline"
          name="headline"
          value="<?= htmlspecialchars($article['headline']) ?>">
      </div>

      <!-- Artikel-body -->
      <textarea
        class="article-body"
        id="content"
        name="content"
        aria-label="Artikel"><?= htmlspecialchars($article['content']) ?></textarea>

      <!-- Hashtags -->
      <input
        class="hashtags-field"
        type="text"
        id="hashtags"
        name="hashtags"
        value="<?= htmlspecialchars($article['hashtags']) ?>"
        aria-label="Hashtags">

      <!-- Source article (gesloten accordion) -->
      <details class="acc acc--source">
        <summary>
          <span>Source article</span>
          <img class="acc__arrow" src="<?= $arrow ?>" alt="" width="11" height="11">
        </summary>
        <div class="acc__body">
          <a href="<?= htmlspecialchars($article['source_url']) ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars($article['source_url']) ?>
          </a>
        </div>
      </details>

      <!-- Actie-knoppen -->
      <div class="article-actions">
        <button type="button" class="btn btn--main">Submit for approval</button>
        <button type="button" class="btn btn--second">Save</button>
      </div>

      <!-- Upload-sectie (PDF: "Upload image" + "1080x1080  1200x644") -->
      <div class="article-uploads">
        <span class="article-uploads__heading">Upload image</span>
        <div class="upload-grid">
          <div class="upload-box upload-box--square">
            <span class="upload-box__size">1080×1080</span>
            <label class="upload-box__area">
              <input type="file" accept="image/*">
              <span>+</span>
            </label>
          </div>
          <div class="upload-box upload-box--landscape">
            <span class="upload-box__size">1200×644</span>
            <label class="upload-box__area">
              <input type="file" accept="image/*">
              <span>+</span>
            </label>
          </div>
        </div>
      </div>

    </div><!-- /article-main -->

  </div><!-- /article-layout -->

</article>
<?php
ce_render_layout_end();
