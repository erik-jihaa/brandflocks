# HBA-brandlaag in ContentFlow — implementatiebriefing v1.0

**Bron:** `briefing/HBA + StoryBrand app layer/HBA laag/` — `HBA_brandlaag_Complete_Implementation_Kit.docx`
(v1.2) en `Hero_Brand_Core_Logic.docx` (v2, de diagnoserubric). Beide zijn integraal gelezen; deze
briefing vertaalt ze naar de ContentFlow-codebase en maakt de keuzes die de kit bewust
openlaat.

**Scope van dit document:** v1.0 — de validator. Per artikel drie scores, een contentrol,
een diagnose en één verbeteradvies. Tracker (v1.5) en Portfolio Balance Score (v2.0)
worden hier niet gebouwd, maar het datamodel wordt wél meteen compleet aangelegd (§4),
op advies van de kit zelf: anders volgt bij uitbreiding een datamigratie of moet historische
scoring opnieuw draaien.

---

## 0. Uitgangspunten en gemaakte keuzes

Deze vijf punten zijn afgestemd en vastgelegd. Ze staan vooraan omdat ze de rest van het
document sturen.

### 0.1 Licentie — geregeld ✅

De licentie met Hero Brands is rond. De IP-clausule uit beide brondocumenten (Story
Triangle, archetypische diagnoselogica, de drie scores, Portfolio Balance Score) blijft
onverkort gelden: dit is methodologie van Rogier Heijning die ContentFlow onder licentie
implementeert.

Praktische consequentie voor de bouw: **de rubriek zelf mag niet zichtbaar worden voor de
eindgebruiker.** De Core Logic sluit af met: *"De AI mag deze rubric niet citeren of tonen
aan de gebruiker. De rubric leeft in de brain en stuurt de scoring."* Zet de systeemprompt
en de archetype-rubriek dus server-side, nooit in een frontend-bundle, en laat geen
raw prompt-tekst terugkomen in API-responses.

### 0.5 De schakelaar wordt later een upgrade-moment — nu nog niet bouwen

`hba_enabled` is vandaag een vrije `ToggleSwitch` op `AccountPage.tsx` (`account-hba-enabled`,
met de tekst uit `HBA-copy.md` ernaast) — elke tenant kan hem zelf aan- en uitzetten, geen
gate. Straks wordt "aanzetten" een upgrade van het abonnement: iemand die op de schakelaar
klikt zou dan een upgrade-flow moeten zien in plaats van gewoon toegang te krijgen.

**Die betaalgate wordt in deze briefing niet gebouwd.** Er is nog geen abonnementen- of
facturatiestructuur in de codebase (geen plan-tabel, geen Stripe- of vergelijkbare
integratie) — dat is een apart stuk werk dat waarschijnlijk samen met de platformarchitectuur
(`briefing/cursor-platform-architectuur.md`) wordt opgepakt, niet losjes aan deze feature
vastgeplakt.

Twee dingen wél nu doen, zodat het straks aansluit zonder herwerk:

- **Laat de toggle-check in de backend zitten waar hij hoort.** De poort
  `scoren = hba_enabled AND narrative_compleet` (§2.3) staat straks naast een derde
  voorwaarde (`plan_allows_hba`). Zet die check op één plek — bijvoorbeeld een functie
  `ce_client_hba_scoring_allowed()` — in plaats van `hba_enabled` los te checken op
  meerdere plekken in de code. Dan is er straks één plek om de plan-check aan toe te voegen.
- **Verander de toggle nu niet in iets dat een upgrade-flow simuleert.** Geen "coming
  soon"-badge, geen halfslachtige paywall-UI. Hij blijft wat hij is: een gewone instelling.
  De paywall komt in zijn geheel in een latere briefing, inclusief de vraag wélk abonnement
  HBA ontsluit en hoe dat op de onboarding-tiers (§0.3, Medium/Full) aansluit.

### 0.2 Waar haakt de scoring aan in de ContentFlow-pijplijn?

De kit gaat uit van een contentmanager die een post in een veld plakt (zie
`Voorbeeld scherm 1.png`: "Brand Narrative Checker", posttekst + platform + format +
knop "Beoordeel post"). ContentFlow werkt anders: artikelen ontstaan automatisch via
CE-01 → CE-02 → CE-03 en komen bij de redacteur binnen als concept.

Drie mogelijke aanhaakpunten, met de afweging:

| Optie | Wanneer scoren | Voordeel | Nadeel |
|---|---|---|---|
| **A. In CE-03**, als extra node na het schrijven | automatisch, bij elk artikel | score staat er al als de redacteur opent | extra LLM-call per artikel, ook bij artikelen die nooit gepubliceerd worden; n8n-workflow moet aangepast |
| **B. In de PHP-API**, bij het openen van het artikel | lazy, bij eerste review | alleen betalen voor wat bekeken wordt | eerste keer openen is traag (LLM-call in de request) |
| **C. Knop in `ArticlePage`**, expliciet door de redacteur | op verzoek | volledige controle, geen verrassingskosten, sluit aan op het kit-ontwerp | wordt vergeten; score ontbreekt in de tracker-aggregatie later |

**Besluit: A, asynchroon en achteraf.** Niet in de request-cyclus (B is traag) en niet
handmatig (C loopt leeg, en v1.5 heeft juist volledige dekking nodig om te kunnen
aggregeren). Concreet: CE-03 blijft inhoudelijk ongewijzigd, maar `POST /api/hba/score`
wordt na het opslaan van het artikel aangeroepen door een extra n8n-node aan het eind van
CE-03 — dat past bij hoe de rest van de pijplijn werkt.

Bouw de endpoint wel zo dat een cron over ongescoorde artikelen óók kan werken: neem
`article_id` als enige verplichte input en maak de call idempotent (bij een bestaande score
voor hetzelfde artikel + dezelfde narrative-versie: niet opnieuw scoren, tenzij
`force: true`). Dat is je vangnet als de n8n-node een keer faalt, en het voorkomt dubbele
kosten bij een retry.

De handmatige knop uit optie C komt er **ook** — als "Beoordeel opnieuw" na een
redactieslag, precies zoals `Voorbeeld scherm 2.png` laat zien. Die zet `force: true`. Dat
is goedkoop als de endpoint er toch al is.

### 0.3 De HBA-schakelaar wisselt de identiteitsvelden om — besluit

**Vastgelegd: er komt géén apart BrandNarrative-scherm.** De StyleGuide-pagina houdt één
identiteitsblok, en de `hba_enabled`-schakelaar bepaalt welke velden daarin staan:

| `hba_enabled` | Identiteitsblok op StyleGuide |
|---|---|
| **uit** | zoals nu: BrandValues (`brand_goal`) — archetype/adversary verborgen |
| **aan** | de veertien HBA-velden (Story Triangle + bewijs, grenzen, taal, voorbeelden, contextprofiel) |

Wat bij beide standen ongewijzigd blijft: Schrijfwijzer, ToneStyles en EditorStyles. Dat
zijn schrijfinstructies, geen merkarchitectuur — en de Core Logic verbiedt uitdrukkelijk
om tone of voice als architectuurtoets te gebruiken (§2.2).

Dit is de nettere oplossing dan een tweede scherm: één plek waar de identiteit van het merk
staat, die simpelweg dieper wordt als de HBA-laag aanstaat. Het maakt ook meteen zichtbaar
wat de laag waard is — je ziet de meetlat groeien van drie velden naar veertien.

Drie gevolgen die in de bouw geregeld moeten worden:

**a. Bestaande tenants met `hba_enabled = 1` hebben straks twaalf lege velden.** Dat is
geen bug maar de bedoeling: de compleetheidsindicator (§6.1) toont "3 van 14 ingevuld" en
de scoring draait pas als het compleet is. Geen migratiescript dat velden gokt — de Core
Logic verbiedt zelf invullen vanuit categoriekennis expliciet.

**b. De n8n-workflows mogen niet breken.** `clients.archetype`, `adversary` en `brand_goal`
worden gelezen door CE-01, CE-01b, CE-03, CE-07 en de campagne-workflow. Los dit op met
**write-through**: bij het opslaan van de BrandNarrative worden `archetype` → `clients.archetype`,
`foe` → `clients.adversary` en `doel` → `clients.brand_goal` meegeschreven. De workflows
blijven dan volledig ongewijzigd en zien gewoon rijkere waarden. Dit is de belangrijkste
technische randvoorwaarde van deze aanpak — zonder write-through valt de contentproductie stil.

**c. Terugschakelen mag niets wissen.** Zet `hba_enabled` weer uit, dan verdwijnen de
veertien velden uit beeld maar blijven ze in `brand_narratives` staan. `StrategyPage.tsx`
doet dit vandaag al voor archetype/adversary (bij een save met HBA uit worden de opgeslagen
waarden opnieuw meegestuurd zodat ze niet gewist worden) — trek diezelfde lijn door.

**Wie vult ze in?** De twee brondocumenten spreken elkaar half tegen: de Implementation Kit
zegt "ingevuld door Rogier of een HBA-strateeg, contentmanagers read-only", de losse
`Short guide voor klant/Werkinstructie HBA.docx` is juist geschreven als zelf-invulinstructie.
Beide moeten kunnen (rol-gating in §6.1), maar begin bij de eerste tenants met de begeleide
route. Een HBA-score op een half-doordachte narrative is erger dan geen score: hij oogt
objectief en is het niet.

Koppel dit aan de onboarding-tiers uit `briefing/onboarding-master.md`: de HBA-laag hoort
bij **Medium en Full**, niet bij Light. Light is een demo in een presentatiesetting — daar is
geen tijd voor veertien architectuurvelden, en `narrative_incomplete` als eerste indruk is
dodelijk.

### 0.4 De vier worked examples worden opgevraagd

De Complete Kit verwijst in hoofdstuk 7 naar vier uitgewerkte scoringvoorbeelden
(Maker/bewijs, Wijze/activatie, Verzorger/service, narrative_incomplete) in "de v1.0-kit
hoofdstuk 6". Die zitten niet in de map; ze worden bij Rogier opgevraagd.

**Planningsconsequentie:** ze zijn nodig vóór stap 6 van de bouwvolgorde (§8), niet eerder.
Stap 1 t/m 5 kan dus gewoon starten. Testcase 10 ("voorbeelden als kalibratie-anker") kan
zonder die voorbeelden niet zuiver worden afgerond — plan hem daarom als laatste.

Ook de losse v1.5-, v2.0- en implementatieplan-specificaties waar hoofdstuk 1 naar verwijst
ontbreken. Dat is voor v1.0 niet relevant: de Complete Kit bevat de systeemprompt, alle
schemas, het datamodel, de formules en dertien testcases, en is daarmee zelfdragend. Vraag
ze pas op als v1.5 aan de beurt is.

---

## 1. Wat de laag doet (samenvatting voor wie snel wil bouwen)

Per artikel gaat er één LLM-call uit met (a) de complete BrandNarrative van de tenant en
(b) de artikeltekst. Terug komt strikte JSON met:

- `contentrol` — één van acht rollen (positionering, bewijs, educatie, activatie, community,
  service, employer_culture, reactief) plus motivatie. Bepaalt de meetlat: een service-post
  hóeft niet hoog te scoren op Brand Contribution.
- `brand_contribution` 0-10, opgebouwd uit zes subdimensies van elk 0-5: `archetype_fit`,
  `doel_fit`, `tegenkracht_fit`, `spanning`, `mechanisme_proof`, `distinctiviteit`.
- `brand_consistency` 0-10 — blijft de content binnen de architectuur.
- `architecture_risk` 0-10 (**laag = goed**) met flags uit een vaste set van zes:
  `category_fallback`, `archetype_drift`, `proof_gap`, `constraint_breach`,
  `opportunistische_trendcontent`, `foe_vervaging`.
- `long_term_value` / `short_term_value`, beide 0-5 (Binet & Field) — nu ongebruikt, voedt
  straks v1.5/v2.0. Meteen opslaan.
- `diagnose` (2-4 zinnen, gewone taal) en `verbeteradvies` (één concrete aanwijzing, geen lijst).

De kernregel eronder, letterlijk uit de Core Logic: *identiteit (archetype) bepaalt welk
doel logisch is; het doel maakt een specifieke Foe noodzakelijk; de Foe maakt zichtbaar
waarom juist dit merk nodig is.* Ontbreekt die keten, dan is er geen positionering maar
uitleg.

---

## 2. Wat ContentFlow al heeft — en wat botst

| HBA-veld | Bestaat in CE? | Kolom | Actie |
|---|---|---|---|
| `archetype` | ja | `clients.archetype` TEXT, vrij tekstveld | **botst** — zie hieronder |
| `foe` | ja, als `adversary` | `clients.adversary` TEXT | hergebruiken, veldnaam in de API mappen |
| `doel` | deels | `clients.brand_goal` TEXT | **let op label** — heet in de UI "BrandValues", DTO-key `brandstory` |
| `spanning` | nee | — | nieuw |
| `mechanisme` | nee | — | nieuw |
| `geloofwaardigheidstest` | nee | — | nieuw |
| `bewijsvelden` | nee | — | nieuw (JSON array) |
| `verboden_logica` | nee | — | nieuw (JSON array) |
| `tone_of_behaviour` | deels | `clients.tone_of_voice` / `writing_guide` | **niet hergebruiken** — zie §2.2 |
| `woorden_die_passen` / `_niet_passen` | nee | — | nieuw (JSON arrays, min. 3 elk) |
| `goede_voorbeelden` / `zwakke_voorbeelden` | nee | — | nieuw (exact 3 elk) |
| `contextprofiel` | nee | — | nieuw (enum van 6) |

### 2.1 Archetype: vrij tekstveld wordt enum

`clients.archetype` is nu vrije tekst. Het HBA-schema eist een enum van precies twaalf:
Leider, Magiër, Wijze, Kampioen, Ontdekker, Maker, Rebel, Entertainer, Beminnaar,
Verzorger, Buur, Optimist. De Core Logic is expliciet dat de AI bij een archetype buiten
deze twaalf níet zelf mag invullen vanuit Jungiaanse of marketingkennis, maar
`narrative_incomplete` moet melden.

Migreer niet automatisch. Zet het nieuwe enum-veld op `brand_narratives.archetype` (§4) en
laat `clients.archetype` staan zoals het is — dat voedt CE-01/CE-03/CE-07 en de bestaande
angle-logica, en dat wil je niet breken. Bij het invullen van de narrative toont de UI de
huidige vrije tekst als hint ("nu ingevuld: …") zodat de strateeg bewust kiest.

### 2.2 `tone_of_behaviour` is níet de schrijfwijzer

Verleidelijk om `writing_guide` te hergebruiken, maar de Core Logic verbiedt exact deze
vergissing: "tone of voice (als hoofdcriterium) → archetypegedrag in taal. Tone of voice is
een gevolg, geen toets." De schrijfwijzer in ContentFlow is een instructie *voor het
schrijven*; `tone_of_behaviour` is een beschrijving van *hoe het archetype zich gedraagt*,
die de AI gebruikt om te toetsen. Apart veld, geen prefill vanuit `writing_guide`.

### 2.3 `hba_enabled` bestaat al — en betekent nu iets anders

`clients.hba_enabled` (TINYINT, default 0) bestaat, wordt gelezen via
`ce_client_hba_enabled()` in `web/api/account-repository.php` en gate't vandaag:

- of Archetype/Adversary zichtbaar zijn op de StyleGuide-pagina (`StrategyPage.tsx`);
- of de Angle-selector zichtbaar is (`ArticleMetaSidebar.tsx`);
- of `angle` wordt gecoerced naar `general` (`mappers.php`, `tone-styles.php`,
  `fastlane-repository.php`);
- wat `ce_client_styleguide_ready()` als "compleet" beschouwt;
- of `angleMix`/`strategyTrend` op Home getoond worden.

Dit is dus een bestaande, functionele vlag. Conform het besluit in §0.3 wordt hij
**uitgebreid, niet vervangen**: alle bovenstaande effecten blijven exact zoals ze zijn, en
er komen er twee bij:

1. het identiteitsblok op de StyleGuide toont de veertien HBA-velden in plaats van de
   huidige drie (§0.3, §6.1);
2. de scoringlaag draait — maar alleen als de narrative compleet is.

**Geen tweede vlag.** In een eerdere opzet stelde ik een aparte `hba_scoring_enabled` voor;
die is overbodig geworden nu de schakelaar de velden wisselt. De natuurlijke poort is:

```
scoren = hba_enabled AND brand_narrative_is_complete(client_id)
```

Dat is één begrijpelijke regel, en de compleetheidscheck moest er toch al zijn (§5.1 stap 2).
Een tenant die de laag aanzet maar de velden niet invult, krijgt geen scores en geen
kosten — precies het gedrag dat je wilt.

`docs/BUILD-NOTES.md:207` stelt de huidige situatie correct vast: "volledige
BrandNarrative/HBA-kit […] nog niet geïmplementeerd (alleen toggle + veld-gating)". Werk
die regel bij zodra dit gebouwd is.

### 2.4 Waar de artikeltekst vandaan komt

Er is geen `client_id` op `articles` — die loopt via `content_slots.client_id` (zie
`ce_client_id_for_slot()` in `web/api/article-repository.php`). De effectieve body is
`content_human` als die gevuld is, anders `content_ai` (`ce_format_article_row()` in
`mappers.php`); de kop via `ce_article_headline()`. Gebruik die bestaande helpers, bouw
geen eigen resolutie.

Er bestaat nog **geen** per-artikel score-kolom of -tabel. `findability` en
`llm_visibility_checks` zitten op client-niveau, `performance_reports.pct_on_strategy` op
periode-niveau. Geen botsing dus — maar noem de nieuwe velden consequent `hba_*` om
verwarring met findability te voorkomen.

### 2.5 Naast `self_review`, niet erin

CE-03 doet al een gestructureerde Claude-call per artikel: `STEP_D_JSON_BODY` in
`scripts/generate-ce03-workflow.py`, met een Anthropic *tool* `self_review` en
`tool_choice: {type:"tool", name:"self_review"}`, schema
`{weakest_claim, unsupported_assumption, missed_counterpoint}`. Dat schrijft naar
`articles.self_review` (JSON), wordt gelezen via `ce_parse_self_review()` en gerenderd in
`ArticleSelfReviewSection.tsx`.

Dat is *inhoudelijke* zelfcontrole tegen het research-materiaal. HBA is *architecturale*
toetsing tegen het merk. Ze overlappen niet en moeten gescheiden blijven — maar het
tool-use-patroon uit `STEP_D_JSON_BODY` is precies de manier waarop je betrouwbare JSON uit
Claude krijgt. **Kopieer dat patroon** (§5.2); het is de enige plek in de codebase waar
structured output al werkt.

---

## 3. Systeemprompt

De kit levert een production-ready systeemprompt (hoofdstuk 4). **Neem hem letterlijk over**
— niet parafraseren, niet inkorten. Hij bevat een harde verbodslijst waarvan de Core Logic
zegt dat overtreding de output ongeldig maakt:

| Verboden term | Gebruik in plaats daarvan |
|---|---|
| "Hero's transformation" | klantuitkomst / doel concreet maken |
| "de klant als hero" | wat wordt mogelijk voor de klant |
| "guide" | merkrol / archetypegedrag |
| "storytelling" (algemeen) | Story Triangle, archetype, Foe, mechanisme |
| "probleem-oplossing" | beweging van Foe naar Goal via het mechanisme |
| "on brand" / "off brand" | Brand Contribution / Consistency / Risk |
| "brand purpose" / "brand mission" | doel dat alleen door dit merk bereikbaar is |
| "tone of voice" als hoofdcriterium | archetypegedrag in taal |
| "authentiek" / "echt" / "puur" | consistent met identiteit, doel en Foe |

Bouw hier een **server-side pre-filter** op: draai na de LLM-call een simpele
case-insensitive scan over `diagnose` en `verbeteradvies`. Bij een treffer: log het,
en vraag één keer opnieuw met een expliciete correctie-instructie. Slaagt dat ook niet,
sla de score op maar markeer hem voor review. De kit noemt deze filter expliciet als
server-side check bij `POST /api/hba/score`, en testcase 1 eist 100% schone output over
20 posts.

**Modelkeuze.** De kit adviseert Opus- of Sonnet-klasse en waarschuwt: *"kleinere modellen
vallen sneller terug op StoryBrand-taal en glijden over de verbodslijst heen."* Neem hier
dus **niet** de Haiku-route uit de LinkedIn-scantool. Gebruik `claude-sonnet-4-6` —
hetzelfde model dat CE-03 en de LLM-zichtbaarheidscheck al gebruiken. Maak het
configureerbaar en leg het per score vast in `hba_scores.model_used` + `prompt_version`,
zodat je bij een promptwijziging kunt terugzien welke scores met welke versie zijn gemaakt.

**Nederlands.** De prompt vraagt om diagnose "in gewone Nederlandse taal (HBO-niveau)".
Neem de taalkwaliteitsinstructie over die in `linkedin-analyse/api/narrative-analyzer.php`
is toegevoegd (geen verzonnen woorden zoals "mensgerichteerd", geen letterlijk vertaalde
Engelse termen) — dezelfde fout treedt hier anders ook op.

---

## 4. Datamodel

Volg `scripts/migrations/README.md`: `.sql` in `scripts/migrations/` is de bron van
waarheid, toegepast via `./scripts/run-migration.sh`, geregistreerd in `schema_migrations`,
daarna `./scripts/export-production-schema.sh` in dezelfde commit. Productie is MariaDB —
dus `CREATE TABLE IF NOT EXISTS` en `ADD COLUMN IF NOT EXISTS` voor idempotentie. De
`ce_ensure_*()`-vangnetten komen daar bovenop, niet in plaats daarvan.

**Leg alle zeven tabellen uit de kit meteen aan**, ook al gebruikt v1.0 er maar drie. De
kit adviseert dit expliciet, en de kosten zijn nul: lege tabellen doen niets.

Bestand: `scripts/migrations/add_hba_brandlaag.sql`. Neem het datamodel over uit hoofdstuk 8
van de kit — hier alleen de ContentFlow-specifieke afwijkingen:

- **UUID's** → `CHAR(36)`, conform de rest van de codebase (`articles.id`, `content_slots.id`).
- **`brand_narratives.client_id`** → FK naar `clients.id`. Row-level isolatie via `client_id`
  net als elke andere tabel.
- **`content_posts` niet aanmaken.** De kit definieert die tabel omdat hij een standalone
  product beschrijft. ContentFlow heeft `articles` al. Laat `hba_scores` verwijzen naar
  `articles.id` via `post_id CHAR(36)`, en haal platform/format/metrics uit wat er al is.
  Bewaar de Content-Weight-componenten (`cw_*`) wél — die horen bij de score, niet bij het
  artikel. Zet ze als kolommen op `hba_scores` met default `1.000`.
- **Versionering van de narrative is niet optioneel.** `brand_narratives` is append-only:
  `version INT` + `is_current BOOLEAN`, en `hba_scores.brand_narrative_id` verwijst naar de
  versie die actief was tijdens het scoren. Anders veranderen oude scores retroactief van
  betekenis zodra de strateeg de Foe aanscherpt. De kit hamert hier terecht op.
- **`raw_llm_response JSON`** meteen opslaan. Bij kalibratie (v1.5) heb je de originele
  output nodig, ook nadat een mens hem gecorrigeerd heeft.
- **Write-through naar `clients`** (§0.3b). Bij elke nieuwe `brand_narratives`-versie worden
  `archetype`, `foe` en `doel` gekopieerd naar `clients.archetype`, `clients.adversary` en
  `clients.brand_goal`, zodat CE-01/CE-01b/CE-03/CE-07 en de campagne-workflow ongewijzigd
  blijven werken. Doe dit in dezelfde transactie als de insert — half doorgevoerd is hier
  erger dan helemaal niet.

Auto-heal: volg `ce_ensure_tenant_activity_schema()` in
`web/api/tenant-activity-repository.php` — `static $ensured`-guard, `ce_table_exists()`-check,
dan `$pdo->exec(file_get_contents(... migrations/add_hba_brandlaag.sql))` in try/catch. Dat
hergebruikt het migratiebestand zelf en voorkomt dat schema en vangnet uit elkaar lopen.

---

## 5. Backend

### 5.1 Endpoints

v1.0 heeft er één echt nieuw endpoint nodig (de andere vier uit kit-hoofdstuk 14 horen bij
v1.5/v2.0):

```
POST /api/hba/score   body: { article_id, force?: bool }  →  score of narrative_incomplete
```

Nieuw bestand `web/api/hba-repository.php` met de logica, `web/api/hba.php` als router —
zelfde opzet als `strategy.php` → `client-repository.php`. Registreren in `web/api/index.php`.

**De narrative-velden gaan níet via een eigen endpoint.** Omdat het identiteitsblok
onderdeel is van de StyleGuide (§0.3), lopen ze mee in de bestaande
`GET/PATCH /api/strategy` → `ce_strategy_dto()` / `ce_patch_strategy()` in
`web/api/client-repository.php`. Eén scherm, één opslagknop, één endpoint. Breid het
strategy-DTO uit met een `brandNarrative`-object en laat `ce_patch_strategy()` bij een
gewijzigde narrative een nieuwe `brand_narratives`-versie aanmaken plus de write-through
uitvoeren (§4).

Let op het bestaande gedrag in `ce_patch_strategy()`: bij een save met HBA uit worden de
opgeslagen archetype/adversary opnieuw meegestuurd zodat ze niet gewist worden. Trek die
lijn door naar alle veertien velden (§0.3c).

`POST /api/hba/score` doet, in deze volgorde:

1. Resolve `client_id` via `ce_client_id_for_slot()`.
2. Haal de huidige `brand_narratives`-rij op. **Valideer server-side tegen het schema
   vóór de LLM-call** — alle veertien velden gevuld, arrays met de juiste minimum-lengte,
   archetype in de enum. Ontbreekt er iets: retourneer direct `narrative_incomplete` met
   de exacte veldnamen in `missing_or_inconsistent`, zonder LLM-call. Dat scheelt kosten en
   maakt de foutmelding preciezer dan wat het model zou teruggeven.
3. Haal de artikeltekst via de bestaande helpers (§2.4).
4. LLM-call (§5.2).
5. Verbodstaal-filter (§3).
6. Sla de score op in `hba_scores`, met de `cw_*`-kolommen op hun schema-default `1.000`
   (zie kader hieronder — er is in v1.0 niets te berekenen).

> **`content_weight` in v1.0: gewoon `1.000`, niet berekenen.** De formule staat niet in
> kit-hoofdstuk 8 (dat is puur het datamodel) maar in **hoofdstuk 10.1** ("Weegformule en
> targets", bij de Portfolio Balance Score): `base_visibility_weight = metrics_impressions
> / median(metrics_impressions in window)`, met een fallback op `reach` en anders `1.0`, en
> pas daarna vermenigvuldigd met de vier `cw_*`-componenten en geclamped op 0,25–4,0.
>
> Die formule vereist een **window van meerdere posts** om een mediaan tegen af te zetten —
> dat window bestaat pas als de tracker-aggregatie (v1.5, §9) draait. Op het moment dat één
> los artikel gescoord wordt, is er niets om tegen te middelen; `base_visibility_weight`
> zou dan altijd `1.0` zijn, dus reken hem niet uit. De vier component-kolommen
> (`cw_channel_weight`, `cw_lifespan_weight`, `cw_strategic_placement_weight`,
> `cw_paid_support_weight`) blijven in v1.0 op hun schema-default staan — `ContentWeightInput`
> is in kit-hoofdstuk 13 zelf al gelabeld als **v1.5/v2.0-component**, niet v1.0. Er is dus
> ook nog geen UI nodig om ze handmatig te wijzigen.
>
> Sla de kolommen wél nu al mee in elke `hba_scores`-rij (§4) — dat is waarom het complete
> datamodel er vanaf dag één staat. Zodra v1.5 gebouwd wordt, kan de aggregatie-engine de
> echte `base_visibility_weight` met terugwerkende kracht berekenen over de dan bestaande
> posts, zonder dat v1.0-scores opnieuw hoeven te draaien.

### 5.2 De LLM-call

Kopieer het tool-use-patroon uit `STEP_D_JSON_BODY` in
`scripts/generate-ce03-workflow.py`: definieer een Anthropic tool met het volledige
output-schema uit kit-hoofdstuk 6 en forceer hem met
`tool_choice: {type: "tool", name: "hba_score"}`. Dat is betrouwbaarder dan "geef alleen
JSON terug" in de prompt en het werkt al aantoonbaar in deze codebase.

Voor de HTTP-kant: `ce_llm_visibility_http_post()` in `web/api/llm-repository.php` is een
bruikbare curl-wrapper (`{ok, status, body, error}`), en `ce_llm_visibility_api_keys()`
laadt de sleutels via `ce_load_env()` met fallback naar `web/includes/ce.local.php`.
Hergebruik beide — geen derde manier om een API-key te laden erbij (er zijn er nu al twee,
zie `ce_support_chat_api_key()`).

Zet `temperature` laag. Testcase 4 eist dat drie identieke calls binnen ±0,5 blijven met
identieke contentrol en consistente risk_flags.

### 5.3 Kosten

Eén Sonnet-call per artikel met een volle BrandNarrative (veertien velden, waarvan zes
arrays) plus artikeltekst: reken op 3-6k input-tokens en ~600 output-tokens. Dat is een
factor 10 duurder dan de Haiku-call in de LinkedIn-scantool.

Sluit aan op wat er al is: `tenant-cost-repository.php` rekent per tenant per maand.
Voeg HBA-scoring daar als eigen post toe, zodat de kosten zichtbaar zijn in het bestaande
kostenoverzicht in plaats van ergens ongemerkt op te lopen. Sla per score de
token-aantallen op.

---

## 6. Frontend

### 6.1 Identiteitsblok op de StyleGuide

Conform §0.3: geen nieuw scherm. `StrategyPage.tsx` houdt zijn huidige opbouw en het
identiteitsblok bovenin wisselt van inhoud op basis van `hbaEnabled`.

De pagina rendert vandaag, in volgorde: HBA-blok (alleen bij `hbaEnabled`) → BrandValues →
Schrijfwijzer → `ToneStylesEditor` → `EditorStylesEditor` → Save/Undo. Die volgorde blijft;
alleen het eerste blok wordt vervangen:

```
hbaEnabled === false  →  BrandValues (brandstory)                    [ongewijzigd]
hbaEnabled === true   →  <BrandNarrativeFields />  — 14 velden, 4 groepen
```

Groepeer de veertien velden in vier `StrategySection`-blokken, anders wordt het een muur:

| Groep | Velden |
|---|---|
| **Story Triangle** | archetype, doel, foe, spanning |
| **Mechanisme & bewijs** | mechanisme, bewijsvelden, geloofwaardigheidstest |
| **Grenzen & taal** | verboden_logica, tone_of_behaviour, woorden_die_passen, woorden_die_niet_passen |
| **Kalibratie** | goede_voorbeelden (3), zwakke_voorbeelden (3), contextprofiel |

Zet de Story Triangle bovenaan en de kalibratievoorbeelden onderaan: dat is ook de volgorde
waarin de `Werkinstructie HBA.docx` de invuller meeneemt, en de eerste vier velden zijn de
enige die causaal uit elkaar moeten volgen.

Volg verder de bestaande conventies van `StrategyPage.tsx`: `GridShell` met
`maxWidth: GRID2_REVIEW_MAIN_WIDTH`, `StrategyField` / `StrategySection` uit
`components/ui/Field.tsx`, opslaan met `PillButton` + `useSaveNotice()`, en het
`saved`/`draft`-statepaar met een `dirty`-`useMemo`. Eén opslagknop voor de hele pagina —
geen apart opslaan per groep.

Afwijkingen die deze velden nodig hebben:

- **Archetype** is een dropdown met twaalf opties, geen tekstveld. Toon per optie de
  archetypische Foe uit de Core Logic als hint — dat helpt de invuller enorm, want de
  meeste mensen kiezen een archetype op gevoel en vullen daarna een Foe in die er niet uit
  volgt.
- **Array-velden** (`bewijsvelden`, `verboden_logica`, `woorden_die_passen`,
  `woorden_die_niet_passen`) hebben een chip/tag-invoer nodig. `ToneStylesEditor` is het
  dichtstbijzijnde bestaande patroon voor herhaalde items — bekijk of dat te hergebruiken is
  voordat je iets nieuws bouwt.
- **`goede_voorbeelden` / `zwakke_voorbeelden`** zijn exact drie tekstvelden elk. Niet
  minder, niet meer — het schema eist `minItems: 3, maxItems: 3`.
- **Rol-gating.** De kit wil read-only voor de contentmanager. Er is nog geen rollenmodel
  in CE; het dichtstbijzijnde is de platform-admin-context. Voor v1.0: bewerkbaar voor wie
  ook de account-instellingen mag wijzigen, read-only voor de rest, met de regel uit de kit
  zichtbaar in de UI ("wijzigen verloopt via de strateeg"). Het volledige rollenmodel komt
  bij het platform-dashboard (`briefing/cursor-platform-architectuur.md`).
- **Compleetheids-indicator** bovenaan: X van 14 velden ingevuld, en wat er ontbreekt. Zolang
  hij niet compleet is, scoort de laag niet — maak dat zichtbaar in plaats van de gebruiker
  daar bij het eerste artikel achter te laten komen.

### 6.2 Score-weergave in `ArticlePage`

Volg `Voorbeeld scherm 2.png`. Nieuw component `ArticleHbaSection.tsx`, direct naast
`ArticleSelfReviewSection.tsx` — die twee horen visueel bij elkaar en zijn inhoudelijk
verschillend (§2.5), dus geef de HBA-sectie een eigen kop die dat duidelijk maakt.

Opbouw van boven naar beneden:

1. **Contentrol-regel**: "Beoordeeld als: bewijs — concrete klantcase met operationeel
   detail", met een override-dropdown ("wijzig rol"). De override slaat op in
   `articles`/`hba_scores` en telt mee bij een herbeoordeling.
2. **Drie scorevakken naast elkaar** — Brand Contribution, Brand Consistency, Architecture
   Risk, elk /10 met een balk. **Geen samengesteld eindcijfer op postniveau**; dat komt pas
   bij v2.0 op portfolio-niveau, en de kit is daar expliciet over.
   Bij Architecture Risk: het label "laag = goed" erbij en het aantal flags. Anders leest
   iedereen 2.0/10 als een slechte score. Gebruik hier het bolletjes-systeem uit de
   LinkedIn-scan (groen/rood/wit, `li_render_sentiment_dot`-equivalent) — maar let op de
   omkering bij Risk.
3. **Diagnose**, 2-4 zinnen, met uitklap naar de zes subdimensies.
4. **Eén verbeteradvies** in een eigen blok met kopieerknop.
5. Knop "Beoordeel opnieuw" (§0.2).

Bij `narrative_incomplete`: rode banner met de ontbrekende velden en een link naar de
StyleGuide-pagina. Geen scores tonen, ook niet op nul.

---

## 7. Testen

De kit levert dertien testcases (hoofdstuk 16). Zes daarvan gelden voor v1.0; neem ze
letterlijk over als testplan:

| # | Test | Slagingscriterium |
|---|---|---|
| 1 | Methodische zuiverheid | 20 posts scoren, geen enkele verboden term in de output — 100% schoon |
| 2 | Narrative-validatie | incomplete narrative → `narrative_incomplete` + `scores: null`; complete → `scored` |
| 3 | Contentrol-meetlat | zelfde post als "positionering" vs "service" → bij service lager op Contribution, vergelijkbaar op Consistency |
| 4 | Determinisme | drie identieke calls → scores ±0,5, identieke contentrol, consistente flags |
| 9 | Verplichte velden | laat `geloofwaardigheidstest` leeg → `narrative_incomplete` met exact dat veld |
| 10 | Voorbeelden als anker | post die lijkt op een `zwak_voorbeeld` → lagere Contribution, diagnose benoemt het generieke patroon zonder het voorbeeld te citeren |

Testcase 10 is de belangrijkste en tegelijk de reden dat de worked examples uit §0.4 nodig
zijn: hij toetst of de AI de voorbeelden werkelijk als kalibratie-anker gebruikt in plaats
van als tekst om te kopiëren.

---

## 8. Bouwvolgorde

1. **Migratie + datamodel** (§4) — alle zeven tabellen, versionering + write-through werkend.
2. **Identiteitsblok op de StyleGuide** (§6.1) + uitbreiding van `GET/PATCH /api/strategy`
   (§5.1). Vullen moet kunnen vóór er iets te scoren valt. Vul hier meteen één echte tenant
   mee (iQ Coaches of een eigen merk) — zonder ingevulde narrative kun je niets testen.
   **Controleer direct na deze stap of CE-01 en CE-03 nog draaien** — dat is het moment
   waarop de write-through zich bewijst of niet.
3. **Scoring-engine** (§5.2) met de prompt uit de kit en de verbodstaal-filter. Test
   handmatig via een script (zie `linkedin-analyse/scripts/test-narrative.php` als vorm)
   voordat er UI omheen komt.
4. **Score-weergave in ArticlePage** (§6.2) + handmatige herbeoordeel-knop.
5. **Automatische aanhaking** (§0.2) — n8n-node aan het eind van CE-03, idempotent.
6. **Testcases 1-4, 9, 10** draaien (10 vereist de worked examples uit §0.4). Pas daarna
   aanzetten bij een tenant.

Stap 1 t/m 4 is een werkende validator. Stap 5 maakt hem volledig, stap 6 maakt hem
betrouwbaar.

Daarna pas v1.5 (tracker, kalibratie) — en die is inhoudelijk pas zinvol vanaf twintig
gescoorde posts per tenant, want daaronder is de aggregatie volgens de kit
`preliminary` en mag hij niet eens getoond worden.

---

## 9. Wat bewust níet in deze fase zit

- Tracker met de vier views (v1.5) — heeft ≥20 gescoorde posts nodig.
- Portfolio Balance Score, erosion alerts, managementdashboard (v2.0).
- Kalibratieflow met menselijke HBA-reviewers (v1.5) — vraagt beschikbaarheid van een
  gecertificeerde strateeg.
- De StoryBrand-vertaallaag uit `briefing/HBA + StoryBrand app layer/StoryBrand laag/`.
  Dat is een aparte module met een eigen kit; de Core Logic waarschuwt uitdrukkelijk dat
  StoryBrand-taal nooit ongemerkt de HBA-diagnose mag binnenkomen. Eerst deze laag zuiver
  werkend, dan pas die.
- Sectorbenchmark — vereist 5+ tenants met hetzelfde contextprofiel.
- **Betaalgate op `hba_enabled`** (§0.5) — de toggle blijft voorlopig vrij voor elke tenant.
  Upgrade-flow, plan-koppeling en de vraag welk abonnement HBA ontsluit volgen in een
  aparte briefing, vermoedelijk samen met de platformarchitectuur.
