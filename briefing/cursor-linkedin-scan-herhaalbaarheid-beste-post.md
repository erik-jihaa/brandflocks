# LinkedIn Scan-tool — "Herhaal wat werkt" moet de inhoud begrijpen, niet alleen het cijfer

**Status:** geïmplementeerd (aug 2026).
**Raakt:** `linkedin-analyse/api/analyzer.php`, `linkedin-analyse/api/narrative-analyzer.php`,
`linkedin-analyse/api/scan-repository.php`, `linkedin-analyse/api/report-renderer.php`.
**Aanleiding:** live scan van Doornvogel — beste post is een terugblik op 25 jaar Doornvogel (68
reacties: *"Hoe poseer je op een foto voor een 25-jarig jubileum? Uhhh… Niet. Je duikt het archief
in..."*). De tool adviseert vervolgens "Herhaal wat werkt" op basis van die post. Dat is raar
advies — een 25-jarig jubileum is per definitie eenmalig.

---

## 1. De bug — een cijfer, geen concept

`li_generate_observations()` in `analyzer.php` (regel ~655) kijkt alleen naar het reactiecijfer:

```php
if ($bestPost !== null && $medianReactions > 0 && ($bestPost['reactions'] ?? 0) >= 2 * $medianReactions) {
    $obs[] = ['id' => 'peak_unused', 'title' => 'Onbenutte piek', ...];
}
```

en `li_benefits_from_observations()` vertaalt dat automatisch naar het advies **"Herhaal wat
werkt"**. Nergens wordt gekeken wát die post inhoudelijk is. Een jubileumterugblik, een overlijden,
een award, een personeelswissel — stuk voor stuk kunnen ze de sterkste post van het jaar zijn en
stuk voor stuk zijn ze niet herhaalbaar. Dit is puur rekenwerk, geen contentbegrip — en
contentbegrip is precies wat een LLM wél kan en een reactiecijfer niet.

## 2. De fix — geef de narrative-call de top-N posts, laat het model kiezen

De narrative-analyzer (`narrative-analyzer.php`) leest de posts al voor de consistentie- en
distinctiveness-analyse. Breid dezelfde call uit (geen nieuwe call, geen noemenswaardige extra
kosten) met een derde taak: **welke van de best-presterende posts is een concept dat je
structureel kan herhalen?**

### 2.1 `analyzer.php` — top-N meegeven, niet alleen de nummer 1

Er bestaat al een gesorteerde lijst (`$sorted`, regel ~433) waar `$bestPost` het eerste element van
is, en `weakestPosts` op dezelfde manier de onderkant pakt. Bouw er symmetrisch een bovenkant bij:

```php
$topPosts = [];
foreach (array_slice($sorted, 0, min(5, count($sorted))) as $p) {
    $topPosts[] = [
        'reactions' => (int) ($p['reactions'] ?? 0),
        'text' => li_truncate_text((string) ($p['text'] ?? ''), 300),
        'posted_at' => substr((string) ($p['posted_at'] ?? ''), 0, 10),
    ];
}
```

Voeg `'top_posts' => $topPosts` toe aan de return-array van `li_analyze_posts()`, naast het
bestaande `'best_post'`.

### 2.2 `narrative-analyzer.php` — nieuw promptblok, altijd aan

In tegenstelling tot distinctiveness/description-alignment (die alleen draaien als branche/omschrijving
zijn ingevuld) hoort deze check **altijd** mee te draaien zodra er een `best_post` is — dit is geen
optionele verrijking, het repareert een fout in de basisfunctionaliteit die er al is.

Nieuwe functieparameter `array $topPosts = []`. In de user-message:

```
Best presterende posts (op reacties, hoog naar laag):
1. [68 reacties, 2026-06-12] "Hoe poseer je op een foto voor een 25-jarig jubileum? Uhhh… Niet..."
2. [41 reacties, 2026-03-04] "..."
3. [29 reacties, ...] "..."
```

Nieuw promptblok `LI_NARRATIVE_REPEATABILITY_PROMPT`:

```
Hierboven staan de best presterende posts, gesorteerd op reacties. Beoordeel per post of het
concept STRUCTUREEL HERHAALBAAR is — een thema, format of insteek die dit bedrijf regelmatig kan
terug laten komen — of dat het een EENMALIGE gebeurtenis is die toevallig veel reacties kreeg
(jubileum, verjaardag van het bedrijf, award, overlijden, personeelswissel, unieke aankondiging,
eenmalige samenwerking).

Kies de best presterende post die WEL herhaalbaar is. Dat hoeft niet nummer 1 te zijn.

Voeg toe aan de JSON:
  "repeatable_best_post_index": integer (0-gebaseerde index in de meegegeven lijst van de
    gekozen — herhaalbare — post),
  "repeatable_best_post_reasoning": string (max 2 zinnen: waarom is dit concept herhaalbaar —
    noem het onderliggende format/thema, niet alleen "goede post"),
  "repeatable_best_post_excluded_reason": string|null (alleen invullen als index NIET 0 is —
    waarom de sterkste post is overgeslagen, bijv. "eenmalig 25-jarig jubileum")

Als geen enkele van de meegegeven posts herhaalbaar is, zet index op 0 en leg dat eerlijk uit in
repeatable_best_post_reasoning — verzin geen herhaalbaarheid die er niet is.
```

Parse en valideer net als de andere velden (index moet binnen de lengte van de meegegeven lijst
vallen, anders hele resultaat weggooien net als nu bij ontbrekende verplichte velden).

**Kosten:** klein — een paar extra regels input (de top-5 posts, die al deels in de hoofdlijst
zitten) en een klein stukje output. Geen tweede call. Zet dit gewoon in de bestaande
`max_tokens`-ophoging mee, geen nieuwe drempel nodig.

### 2.3 `scan-repository.php` — doorgeven

`li_scan_run_narrative()` geeft nu `$analysis['posts']` door; geef ook `$analysis['top_posts'] ??
[]` door aan `li_analyze_narrative()`.

## 3. `report-renderer.php` — de tekst overschrijven, niet toevoegen

Twee plekken tonen nu ongefilterd "Herhaal wat werkt": de `peak_unused`-observatie (in "Wat
opvalt") en de gelijknamige benefit (in "Waar ContentFlow bij kan helpen"). Beide zijn nu **statisch**
gegenereerd in `analyzer.php`, vóór de narrative-call — die moeten dus ná de narrative-call
overschreven worden als er een bruikbaar `repeatable_best_post_index` is dat NIET op de #1-post
wijst.

**Voorwaarde voor deze stap:** geef benefits ook een `id` mee (nu alleen `title`/`body`) zodat je
ze net zo betrouwbaar kan matchen als observaties. Kleine aanpassing aan
`li_benefits_from_observations()`: `$benefits[] = ['id' => $id, 'title' => ..., 'body' => ...]`.

In `li_render_report_email()`, vóór `li_render_observations_html()`/de benefits-opbouw, als
`$metrics['narrative']['repeatable_best_post_index'] ?? null` gezet is én niet 0:

```php
$repeatIdx = $metrics['narrative']['repeatable_best_post_index'] ?? null;
if ($repeatIdx !== null && $repeatIdx > 0 && isset($metrics['top_posts'][$repeatIdx])) {
    $altPost = $metrics['top_posts'][$repeatIdx];
    $excluded = trim((string) ($metrics['narrative']['repeatable_best_post_excluded_reason'] ?? ''));
    $reasoning = trim((string) ($metrics['narrative']['repeatable_best_post_reasoning'] ?? ''));

    $newBody = 'De sterkste post (' . $bestPost['reactions'] . ' reacties) is '
        . ($excluded !== '' ? lcfirst($excluded) : 'een eenmalig moment') . ' — niet iets om te '
        . 'herhalen. Herhaalbaarder is dit concept (' . $altPost['reactions'] . ' reacties): "'
        . li_h($altPost['text']) . '" — ' . $reasoning;

    // vervang de 'peak_unused'-observatie en de 'peak_unused'-benefit door $newBody
}
```

(Exacte implementatie — array_map over `$observations`/`$benefits` om het item met `id ===
'peak_unused'` te vinden en te vervangen — aan Cursor, dit is de inhoud, niet de PHP-vorm.)

Als `repeatable_best_post_index === 0` (de #1-post is zelf al herhaalbaar) of er is geen
narrative-data: **niets wijzigen**, bestaande tekst blijft staan. Dat dekt zowel het
happy-path (advies was toch al goed) als de backward-compatibility (oudere scans, AI uit).

## 4. Fallback zonder AI — iets voorzichtiger, geen belofte doen

Zonder narrative-analyse (uitgeschakeld of <4 posts) kán de tool niet weten of iets herhaalbaar is
— dat vereist contentbegrip. Verzacht daarom ook de **kale** (niet-LLM) formulering in
`li_benefits_from_observations()`'s `peak_unused`-tekst iets, zodat hij nooit een garantie klinkt:

> Was: "De best presterende post laat zien waar jullie publiek op reageert. Meer in die richting
> is vaak effectiever dan willekeurig variëren."
>
> Wordt: "De best presterende post laat zien waar jullie publiek op reageert — check wel of dat een
> concept is dat je kan herhalen, of een eenmalig moment (jubileum, award, unieke gebeurtenis)."

Kleine tekstwijziging, kan los en meteen — dit voorkomt hetzelfde soort verkeerd advies in de
gevallen waar de LLM-check niet beschikbaar is.

## 5. Testen

1. Doornvogel-achtige casus (fixture of echte herscan): top-post is een jubileum/eenmalige
   gebeurtenis, tweede of derde post is een herhaalbaar concept (bijv. een terugkerende
   vraag-aan-klanten-post) → verwacht `repeatable_best_post_index` wijst NIET naar 0, en de mail
   toont de aangepaste tekst met de juiste alternatieve post geciteerd.
2. Casus waar de #1-post zelf al herhaalbaar is (bijv. een terugkerend "tip van de week"-format) →
   `repeatable_best_post_index === 0`, geen tekstwijziging.
3. Casus waar geen enkele top-post herhaalbaar is → index 0, met een eerlijke reasoning die dat
   toegeeft — geen verzonnen herhaalbaarheid.
4. AI uitgeschakeld → oude, nu iets voorzichtigere tekst (§4), geen crash op ontbrekende
   `top_posts`/narrative-velden.
5. Kosten-check: geen noemenswaardige toename t.o.v. de al opgehoogde `max_tokens` uit eerdere
   uitbreidingen.

## 6. Update — herhaalbaar is niet genoeg, moet ook de positionering versterken

Erik's vraag na oplevering: checkt de prompt ook of de gekozen herhaalbare post de **unieke
positionering** versterkt, niet alleen of hij structureel herhaalbaar is? Antwoord was nee — de
prompt koos puur op herhaalbaarheid, een repeatable-maar-generiek concept (bijv. "team viert een
willekeurige mijlpaal") kon zo als beste advies naar boven komen, zonder ooit getoetst te zijn aan
`core_message`.

**Al gefixt (code-niveau):**

- Promptregel toegevoegd: bij meerdere herhaalbare kandidaten, kies degene die het sterkst
  aansluit bij `core_message` — herhaalbaar én inhoudsloos is een zwakker advies dan herhaalbaar én
  onderscheidend.
- Nieuw (optioneel, backward-compatible) veld: `repeatable_best_post_reinforces_positioning`:
  `"ja" | "nee" | "onduidelijk"`. Optioneel geparsed — ontbreekt het veld, dan wordt de rest van
  het resultaat niet weggegooid (anders dan de verplichte velden).
- `report-renderer.php` gebruikt dit veld om de mail-tekst aan te scherpen: bij `"ja"` een
  toegevoegde zin dat het concept meteen ook de positionering versterkt ("dubbele winst"); bij
  `"nee"` een expliciete waarschuwing dat het concept op zichzelf nog generiek is — en een directe
  brug naar ContentFlow.

## 7. Acceptatiecriteria

- `top_posts` (top 5) zit in de analyzer-output naast `best_post`.
- Narrative-call krijgt de top-posts altijd mee (niet gated achter branche/omschrijving) en levert
  `repeatable_best_post_index`/`_reasoning`/`_excluded_reason`.
- "Wat opvalt" en "Waar ContentFlow bij kan helpen" tonen de herziene tekst zodra de #1-post niet
  herhaalbaar is; ongewijzigd als dat wel zo is of als narrative-data ontbreekt.
- De niet-AI-fallback-tekst voor `peak_unused` is verzacht (§4), geen kale garantie meer.
- Geen tweede Claude-call, geen nieuwe kostendrempel nodig.
