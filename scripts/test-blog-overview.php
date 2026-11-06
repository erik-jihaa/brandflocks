<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/web/api/blog-overview-repository.php';

$failures = 0;

function ce_blog_test_assert(bool $ok, string $message): void
{
    global $failures;
    if ($ok) {
        echo "OK  {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL {$message}\n";
}

$jihaa = <<<'HTML'
<html><body>
<nav><a href="/">Home</a><a href="/about">Over ons</a></nav>
<div class="blogs-grid">
  <a href="groene-keuken.html" class="blog-post">
    <h2 class="blog-title">Zo maak je een groene keuken zonder plastic</h2>
    <p class="blog-excerpt">Drie stappen die wél blijven hangen bij de klant.</p>
  </a>
  <a href="seizoen-oogst.html" class="blog-post">
    <h2 class="blog-title">Seizoensoogst: wat je deze week kunt bewaren</h2>
    <p class="blog-excerpt">Korte teaser over voorraad en smaak.</p>
  </a>
</div>
<footer><a href="/privacy">Privacy</a></footer>
</body></html>
HTML;

$wp = <<<'HTML'
<html><body>
<header><a href="/blog/">Blog</a><a href="/contact">Contact</a></header>
<article class="post">
  <h2 class="entry-title"><a href="/blog/waarom-merken-verdwijnen/">Waarom merken verdwijnen als ze alleen posten</a></h2>
  <div class="entry-summary"><p>Een merk zonder kernboodschap is ruis.</p></div>
</article>
<article class="post">
  <h2 class="entry-title"><a href="/blog/doelgroep-eerst/">Begin bij de doelgroep, niet bij het kanaal</a></h2>
  <p>Wat zij willen zien bepaalt de pijler.</p>
</article>
<a href="/tag/marketing/">marketing</a>
<a href="/category/nieuws/">nieuws</a>
</body></html>
HTML;

$jihaaItems = ce_blog_extract_overview_candidates($jihaa, 'https://jihaa.nl/blogs.html');
ce_blog_test_assert(count($jihaaItems) === 2, 'JiHaa-overzicht vindt 2 posts');
ce_blog_test_assert(
    ($jihaaItems[0]['title'] ?? '') === 'Zo maak je een groene keuken zonder plastic'
        || ($jihaaItems[1]['title'] ?? '') === 'Zo maak je een groene keuken zonder plastic',
    'JiHaa-titel komt uit de kaart, niet uit de slug',
);
$jihaaUrls = array_map(static fn (array $row): string => $row['url'], $jihaaItems);
ce_blog_test_assert(
    in_array('https://jihaa.nl/groene-keuken.html', $jihaaUrls, true),
    'Relatieve JiHaa-link wordt opgelost',
);

$wpItems = ce_blog_extract_overview_candidates($wp, 'https://voorbeeld.nl/blog/');
$wpUrls = array_map(static fn (array $row): string => $row['url'], $wpItems);
ce_blog_test_assert(count($wpItems) === 2, 'WordPress-overzicht vindt 2 posts');
ce_blog_test_assert(
    in_array('https://voorbeeld.nl/blog/waarom-merken-verdwijnen/', $wpUrls, true),
    'WordPress-artikel-URL blijft behouden',
);
ce_blog_test_assert(
    !in_array('https://voorbeeld.nl/tag/marketing/', $wpUrls, true),
    'Tag-links worden genegeerd',
);
ce_blog_test_assert(
    !in_array('https://voorbeeld.nl/contact', $wpUrls, true),
    'Contact-link wordt genegeerd',
);

echo $failures === 0 ? "\nAlle blogoverzicht-tests geslaagd.\n" : "\n{$failures} test(s) gefaald.\n";
exit($failures === 0 ? 0 : 1);
