# LinkedIn Scan-tool — merkverhaal & consistentie toevoegen

**Vervolg op `briefing/cursor-linkedin-scan-tool.md`.** Dat document (§ 3.3, slot)
schreef bewust: *"de merk-inhoudelijke beoordeling kan een geautomatiseerde scan
niet betrouwbaar bepalen — dat is de waarde die jij in het gesprek toevoegt."*
Die aanname wordt hier bijgesteld, met reden.

## 0. Waarom dit alsnog wel moet — en waarom het eerder terecht twijfelachtig leek

ContentFlow's kernstelling is niet "post meer" of "post regelmatiger" — het is:
**een merk dat consistent en herkenbaar hetzelfde verhaal vertelt, wordt beter
opgepikt door zoekmachines én door LLM's (ChatGPT, Perplexity, Gemini) die om
aanbevelingen gevraagd worden.** Dat is precies waarom CE al een
LLM-zichtbaarheidsrapportage heeft (`web/api/llm-repository.php`) en waarom
`performance_reports` een aparte kolom `engagement_on_strategy` heeft naast
`engagement_general` (§3 hieronder) — het onderscheid tussen *wat scoort* en *wat
het merk bevestigt* zit al in het fundament van het product.

Wat destijds terecht werd afgewezen was een **kwalitatief oordeel** ("is dit een
goede boodschap?", "past dit archetype bij hun markt?") — dat is inderdaad
onbetrouwbaar te automatiseren en vraagt om jouw blik in het gesprek. Wat nu wél
betrouwbaar te automatiseren is, is iets anders: **lezen wat een bedrijf zélf
herhaaldelijk beweert te zijn/bieden, en objectief vaststellen of ze dat consistent
herhalen of telkens iets anders zeggen.** Dat is geen mening, het is tekstanalyse —
net zoals de rest van de tool (stiltes, ritme) geen mening is maar telling.

Kortom: niet *"is hun boodschap goed"*, maar *"hebben ze überhaupt één boodschap,
of wisselt het per post"*. Dat laatste is prima automatiseerbaar en is precies wat
de klant nieuwsgierig maakt naar het gesprek.

---

## 1. Wat er wordt toegevoegd

Eén Claude-call die de posttitels/-teksten van de scan leest en teruggeeft:

| Veld | Betekenis |
|---|---|
| `core_message` | 1-2 zinnen: waar claimt het bedrijf zelf voor te staan / wat te bieden — in hun eigen woorden, niet geïnterpreteerd |
| `recurring_themes` | 3-6 kernwoorden/thema's die terugkomen (bijv. "AI-implementatie", "mensen eerst", "gedragsverandering") |
| `consistency_label` | `hoog` / `gemiddeld` / `laag` — hoe consequent de posts dezelfde boodschap uitdragen |
| `consistency_reasoning` | 1-2 zinnen onderbouwing, met een concreet voorbeeld uit de posts |
| `inconsistency_examples` | 0-3 korte voorbeelden waar de boodschap afwijkt (leeg als consistency hoog is) |

Dit was al voorzien als schakelbare optie in `includes/config.local.php.example`
(`ai_summary_enabled`, `anthropic_api_key`, comment "§3.4") — die is er dus
niet voor niets. Dit briefing werkt die optie volledig uit.

---

## 2. Waar het in de code komt

### 2.1 Nieuwe functie, geen nieuw bestand nodig qua omvang — wel apart houden

Nieuw bestand `linkedin-analyse/api/narrative-analyzer.php`, naast
`analyzer.php` (die blijft puur kwantitatief/regelgebaseerd — geen netwerkcalls
daarin, dat is een bewuste scheiding die al bestaat).

```php
/**
 * @param list<array{text: string}> $posts  — gededupliceerde posts uit li_analyze_posts()
 * @return array{
 *   core_message: string,
 *   recurring_themes: list<string>,
 *   consistency_label: 'hoog'|'gemiddeld'|'laag',
 *   consistency_reasoning: string,
 *   inconsistency_examples: list<string>,
 *   input_tokens: int|null,
 *   output_tokens: int|null,
 *   cost_usd: float|null,
 * }|null   // null = uitgeschakeld, mislukt, of te weinig posts
 */
function li_analyze_narrative(array $posts, string $companyName): ?array
```

Retourneert `null` bij elke storing (geen API-key, call mislukt, timeout, JSON
niet parseerbaar, minder dan 4 posts met tekst). **Dit mag de rest van de scan
nooit breken** — zelfde principe als de rest van de tool: de kwantitatieve
rapportage moet altijd blijven werken, ook zonder AI-laag.

### 2.2 Aanroepen vanuit `scan-repository.php`

In `li_scan_run_and_store()`, direct na regel 192 (`$analysis =
li_analyze_posts(...)`) en vóór het opslaan:

```php
$metrics = $analysis['metrics'];

if ((bool) li_config('ai_summary_enabled', false)) {
    $narrative = li_analyze_narrative($analysis['posts'], $analysis['company_name']);
    if ($narrative !== null) {
        $metrics['narrative'] = $narrative;
    }
}
```

`$analysis['posts']` bestaat al (de gededupliceerde lijst met `text`-veld) — geen
nieuwe dataverzameling nodig, puur hergebruik van wat er al ligt.

Sla `narrative` gewoon mee in de bestaande `metrics` JSON-kolom. **Geen nieuwe
tabel nodig** — alleen een klein velduitbreiding op wat al JSON is.

### 2.3 Eén kleine schema-wijziging: kosten zichtbaar houden

`li_scan_daily_limits()` telt nu alleen Apify-kosten (`cost_usd`). Met een tweede
kostenbron moet die daglimiet blijven kloppen. Voeg toe:

```sql
ALTER TABLE scans ADD COLUMN narrative_cost_usd DECIMAL(10,4) NULL AFTER cost_usd;
```

En pas de daglimiet-query aan naar
`COALESCE(cost_usd,0) + COALESCE(narrative_cost_usd,0)`. Sla `narrative_cost_usd`
apart op (niet optellen bij `cost_usd`) zodat je in de tabel kunt blijven zien
welk deel Apify was en welk deel Claude — scheelt uitzoekwerk als de kosten ooit
uit de pas lopen (zie de USD/EUR-slordigheid die al eerder in de CE-codebase zat;
hou dit schoon vanaf het begin).

---

## 3. De Claude-call zelf

### 3.1 Welk patroon hergebruiken

CE heeft al een direct-PHP-naar-Claude-aanroep: `ce_llm_visibility_ask_model()`
in `web/api/llm-repository.php` — `curl` naar
`https://api.anthropic.com/v1/messages`, headers `x-api-key` +
`anthropic-version: 2023-06-01`. Kopieer dat curl-skelet (net als bij de
Apify-helpers: dupliceren, niet importeren — deze tool blijft losstaand).

Twee verschillen met dat bestaande patroon, bewust:

1. **Wel een `system`-veld gebruiken** (top-level parameter van de Messages
   API, geen aparte message-role) — voor betrouwbare JSON-output heb je een
   scherpe instructie nodig. Het bestaande LLM-zichtbaarheid-patroon gebruikt
   geen system-prompt omdat het daar niet nodig was (simpele vraag/antwoord).
2. **Model: Haiku, niet Sonnet.** De zichtbaarheidscheck gebruikt Sonnet omdat
   die een externe vraag moet beantwoorden. Hier lees je alleen tekst die je al
   hebt en geef je gestructureerde output terug — dat kan Haiku prima, en de
   tool moet expliciet goedkoop blijven (§4 van het hoofdbriefing). Gebruik
   `claude-haiku-4-5-20251001` als default, configureerbaar via
   `anthropic_model` in `config.local.php`.

### 3.2 Prompt

```
System:
Je analyseert LinkedIn-posts van een bedrijf. Je geeft ALLEEN geldige JSON terug,
geen uitleg erbuiten, geen markdown-codeblok. Structuur:
{
  "core_message": string (max 2 zinnen, in het Nederlands, wat het bedrijf zelf
    beweert te bieden/waar te staan — parafraseer hun eigen taal, verzin niets),
  "recurring_themes": string[] (3-6 items, korte kernwoorden/thema's die
    meerdere keren terugkomen),
  "consistency_label": "hoog" | "gemiddeld" | "laag" (hoe consequent dezelfde
    kernboodschap terugkomt over alle posts heen — niet of de boodschap goed is,
    alleen of hij consistent is),
  "consistency_reasoning": string (max 2 zinnen, met een concreet voorbeeld),
  "inconsistency_examples": string[] (0-3 korte voorbeelden van afwijkende
    boodschappen; leeg als consistency_label "hoog" is)
}
Baseer je uitsluitend op de aangeleverde posts. Als de posts te weinig
gemeenschappelijke thematiek hebben om iets te concluderen, geef dat eerlijk
weer in consistency_reasoning in plaats van iets te verzinnen.

User:
Bedrijf: {companyName}
Posts (chronologisch):
1. {tekst, max 400 tekens}
2. {tekst, max 400 tekens}
...
```

Voed maximaal de posts die er al zijn (hard begrensd op 30 door `max_posts` in
de hoofdbriefing) en knip elke tekst af op 400 tekens — dat is ruim genoeg om de
kernboodschap te lezen en houdt de tokenkosten voorspelbaar en laag.

### 3.3 Output parsen — robuust, niet optimistisch

Claude houdt zich meestal aan "alleen JSON", maar niet gegarandeerd. Strip een
eventueel ` ```json ... ``` `-codeblok voor je `json_decode()` aanroept. Valideer
dat alle verplichte velden aanwezig zijn en `consistency_label` een van de drie
toegestane waarden is — bij twijfel: `null` teruggeven (§2.1), niet half-gevulde
data opslaan.

### 3.4 Kosten

Haiku-pricing is laag genoeg dat zelfs zonder exacte token-accounting de
schatting "~$0,001–0,003 per scan" klopt (30 posts × 400 tekens ≈ 4-5k
input-tokens, output ~300 tokens). Bereken en sla toch `narrative_cost_usd` op
uit de `usage.input_tokens`/`usage.output_tokens` in de Claude-response ×
een kleine prijsconstante in de code (met een commentaarregel dat dit
handmatig bijgewerkt moet worden als Anthropic de prijzen wijzigt) — dat is
dezelfde discipline als bij de Apify-kosten en voorkomt dat het budget-plafond
in § 2.3 een dode letter wordt.

---

## 4. Het rapport: nieuwe sectie + aanpassing hook

### 4.1 Nieuwe sectie "Wat ze over zichzelf vertellen"

Plaats **na** "Wat opvalt" en **vóór** "Waar ContentFlow bij kan helpen" — de
volgorde blijft dus: hook → cijfers → wat opvalt (kwantitatief) → wat ze over
zichzelf vertellen (kwalitatief) → waar ContentFlow bij kan helpen → tot slot.

Alleen renderen als `$metrics['narrative']` bestaat (niet null) — anders sla je
de sectie stilzwijgend over, exact zoals `li_render_observations_html()` nu al
`''` teruggeeft bij een lege lijst.

Gebruik voor de consistentie-regel hetzelfde gevulde bolletje als in "De
cijfers" (`li_render_sentiment_dot()` — hergebruiken, niet opnieuw bouwen):
`hoog` → `good` (groen), `laag` → `bad` (rood), `gemiddeld` → `neutral` (wit).

Voorbeeldopbouw:

```
Wat ze over zichzelf vertellen

Kernboodschap: "{core_message}"
Terugkerende thema's: {theme1}, {theme2}, {theme3}

● Consistentie: {laag/gemiddeld/hoog}
{consistency_reasoning}
```

Voeg bij `laag`/`gemiddeld` een derde regel toe met 1-2
`inconsistency_examples`, kort en feitelijk ("bijv. de ene post noemt X als
kernbelofte, de volgende Y") — zelfde toon-regel als de rest van het rapport
(§5.3 hoofdbriefing): beschrijvend, niet belerend.

### 4.2 Hook aanpassen: merkconsistentie krijgt voorrang

`li_render_hook_html()` in `report-renderer.php` kiest nu de meest opvallende
kwantitatieve stat (beste post vs. gemiddelde). Als de narrative-analyse
beschikbaar is, geef **een lage consistentie-score voorrang boven de
engagement-hook** — dat is de meest onderscheidende vondst die deze tool kan
doen (geen concurrerende tool leest dit), en het is letterlijk de reden waarom
iemand met ContentFlow zou praten.

Voorstel nieuwe prioriteitsvolgorde in `li_render_hook_html($metrics)`:

1. `consistency_label === 'laag'` → hook op basis van `core_message` +
   `consistency_reasoning`, bijv.: *"{Bedrijf} post over uiteenlopende
   onderwerpen — er is geen vaste rode draad te herkennen in wat jullie
   beweren te bieden. Dat merkt niet alleen een lezer, dat merkt een
   zoekmachine of AI-assistent ook."*
2. bestaande piek-vs-gemiddelde hook (bestPost ≥2× avg)
3. bestaande fallbacks (one_way / no_rhythm / low_engagement / generieke
   observatie)

Geef `li_render_hook_html()` dus het volledige `$metrics`-array door (dat
gebeurt al) en laat de functie zelf `$metrics['narrative']` uitlezen — geen
functiehandtekening-wijziging nodig.

### 4.3 Nieuw, vast tekstblok in "Waar ContentFlow bij kan helpen"

Voeg — los van de bestaande `li_benefits_from_observations()`-mapping — altijd
een blok toe wanneer narrative-analyse beschikbaar is, want dit is de kern van
wat ContentFlow uniek oplost:

> **Herkenbaar voor mens én machine.** Een kernboodschap die in elke post
> terugkomt maakt niet alleen voor lezers duidelijk waar jullie voor staan —
> het helpt zoekmachines en AI-modellen zoals ChatGPT ook om jullie merk
> consistent en juist te herkennen en aan te bevelen. ContentFlow toetst elk
> artikel vooraf aan één vastgelegd merkprofiel, zodat die consistentie niet
> van het toeval afhangt.

Pas de laatste zin licht aan op basis van `consistency_label` (bij `hoog`:
erken dat het al goed staat en framen als "borgen wat werkt" in plaats van
"oplossen van een probleem").

---

## 5. Config-uitbreiding

`config.local.php.example` krijgt twee extra sleutels naast de al bestaande
`anthropic_api_key` / `ai_summary_enabled`:

```php
'anthropic_model' => 'claude-haiku-4-5-20251001',
'narrative_max_cost_usd_per_scan' => 0.02,   // hard plafond, call wordt overgeslagen als schatting eroverheen gaat
```

---

## 6. Testcase

Draai de GrowthElements-testcase (§8 hoofdbriefing) opnieuw met
`ai_summary_enabled = true`. Verwacht op basis van de eerdere handmatige
analyse (zie `briefing/GrowthElements-social-media-scan.docx`):

- `core_message` in de richting van "AI-implementatie is een
  gedragsverandering, niet alleen techniek — mensen eerst"
- `consistency_label`: waarschijnlijk `gemiddeld` — de Synthwave-post en de
  onderzoek-onderbouwde posts zijn consistent, de generieke "welkom nieuwe
  collega"-post (hoog engagement, weinig merkbevestiging — zie eerdere
  analyse) trekt het gemiddelde omlaag
- Hook zou bij deze testcase dus de nieuwe merkconsistentie-variant moeten
  tonen in plaats van de piek-vs-gemiddelde hook, tenzij `consistency_label`
  op `hoog` uitkomt — valideer dit expliciet, want het is precies het scenario
  waar §4.2 om draait.

Wijkt de uitkomst sterk af (bijv. `core_message` verzint iets dat niet in de
posts staat), dan is de prompt te los — verstrak `system` met een expliciete
"verzin niets, parafraseer alleen" herhaling en test opnieuw.
