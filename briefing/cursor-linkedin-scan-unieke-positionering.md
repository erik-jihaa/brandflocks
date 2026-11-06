# LinkedIn Scan-tool — branche-stap toevoegen, scan beoordeelt unieke positionering

**Vervolg op** `briefing/cursor-linkedin-scan-narrative.md` en vervangt de pitch-tekst uit
`briefing/cursor-linkedin-scan-tegenstander.md` (§2 van dat document is achterhaald, zie de notitie
bovenaan dat bestand).
**Raakt:** `linkedin-analyse/index.php`, `linkedin-analyse/scan.php`,
`linkedin-analyse/api/narrative-analyzer.php`, `linkedin-analyse/api/report-renderer.php`,
`linkedin-analyse/api/scan-repository.php`, `linkedin-analyse/sql/`.
**Status:** volledig geïmplementeerd en live (aug 2026): branche/distinctiveness, intro §10.1,
bedrijfsomschrijving + contextprofiel (§10.2–10.4), score-samenvatting (§10.5), vertaalbug-fixes
(§10.6), UX-polish scan-knop (§10.7). Zie §9 voor deploy/scope.

---

## 0. Waarom dit moet

De ContentFlow-pitch is aangescherpt (zie `pitch-contentflow.md`): niet consistentie is de
belofte, maar **unieke positionering** — vinden én vasthouden. Reden: consistentie is geen
onderscheidende claim. Elke contenttool belooft "consistent posten", en een merk kan consistent
**inwisselbaar** zijn. De echte vraag is niet of een post over jouw merk gaat (dat haalt bijna elke
post), maar of hij jouw unieke positionering draagt — iets dat niet elk ander merk in de categorie
ook had kunnen zeggen.

Het probleem: de scan-tool meet op dit moment alléén consistentie (`consistency_label` in
`narrative-analyzer.php` — hoog/gemiddeld/laag, of een merk telkens dezelfde boodschap herhaalt).
Dat is precies de as die de nieuwe pitch relativeert. Een merk kan `consistency_label: hoog` scoren
en tegelijk volledig generiek zijn — "wij bieden kwaliteit en persoonlijke service" consistent
herhaald is nog steeds een cliché. De scan-mail claimt nu iets (unieke positionering is wat telt)
wat hij zelf niet meet. Dat gat dicht dit document.

## 1. Wat er al is aangepast (gedaan, niet meer te bouwen)

In `li_render_intro_html()` (`report-renderer.php`) is de openingsalinea herschreven naar de
positionering-taal. **Huidige tekst staat in §10.1** — niet meer wijzigen tenzij de pitch zelf weer
verandert. (De tekst hieronder is de *oude* versie, alleen ter archief.)

> "De meeste merken proberen zich aan te passen aan wat AI wil horen: glad, gemiddeld, veilig. Dat
> is precies de verkeerde strategie. AI-modellen en zoekmachines filteren het gemiddelde juist weg,
> en herkennen wat opvalt: merken met een unieke positionering — niet merken die, hoe consistent
> ook, inwisselbaar zijn met de rest van hun categorie. ContentFlow doet twee dingen: het helpt die
> positionering scherp krijgen, en het bewaakt dat elke post hem vasthoudt in plaats van terug te
> zakken naar iets wat elk ander merk ook had kunnen zeggen."

## 2. De branche-stap — hergebruik uit BrandArchitecture, niet opnieuw bouwen

In de CE-app (het hoofdproduct) bestaat al precies dit concept, in
`frontend/src/components/MerkarchitectuurSection.tsx`, stap 1 van de BrandArchitecture-wizard: een
branche-picker met als hint *"Kies je branche — die helpt ons clichés in jouw markt te
herkennen."* Dat is letterlijk wat de scan-tool nu ook nodig heeft.

**Bron van de branches:** tabel `branche_library` in de CE-database (geseed via
`web/api/hba-branche-seed.php`), 6 rijen:

| `branche_code` | Label | Archetype |
|---|---|---|
| `zakelijke_dienstverlening` | Zakelijke dienstverlening / consultancy | Wijze |
| `supply_chain_logistiek` | Supply chain / logistiek | Verzorger |
| `bouw_techniek` | Bouw / techniek / installatie | Maker |
| `zorg_welzijn` | Zorg / welzijn | Verzorger |
| `tech_saas` | Technologie / software / SaaS | Magier |
| `retail` | Retail / consumentenmerken | Buur |

Elke rij heeft ook een `categorietaal` (JSON-array met cliché-taal voor die branche), bijvoorbeeld
retail: `["Beste prijs", "voor ieder wat wils", "kwaliteit voor iedereen", "wij begrijpen jou",
"jouw partner in..."]`. Dat veld is het bruikbare onderdeel — dat gaat zo de Claude-prompt in
(§4).

**Belangrijke architectuurkeuze — geen live cross-app call.** `linkedin-analyse` is een losstaande
app met een eigen database (`igisnext_linkedin-analyse`), los van de CE-database. Twee opties:

1. **Statische kopie** van de 6 branches + `categorietaal` in `linkedin-analyse` zelf (bijv.
   `linkedin-analyse/includes/branche-library.php`, een simpele PHP-array). Geen netwerkafhankelijkheid,
   geen faalkans tijdens een scan. Nadeel: moet handmatig gesynchroniseerd worden als Rogier's
   branche-bibliotheek in de CE-app groeit (BUILD-NOTES noemt dat er nog maar 6 van een grotere
   brondocument-set zijn geïmporteerd — zie `hba-branche-seed.php`).
2. **Live call** naar `GET /hba/branches` op de CE-app vanuit `linkedin-analyse` bij het laden van
   `index.php`. Altijd actueel, maar voegt een externe afhankelijkheid toe aan een tool die
   Erik/Eva gebruiken vlak vóór een salesgesprek — als CE-app down is, mag dat de scan-tool niet
   platleggen.

**Aanbeveling: optie 1 (statische kopie), definitief, geen sync-knop.** Het is 6 (straks misschien
8) rijen die zelden veranderen, en de scan-tool moet altijd werken, ook als de CE-app een keer plat
ligt. Een sync-knop lost een probleem op dat zich een paar keer per jaar voordoet, met een nieuw
stuk auth/CORS/foutafhandeling erbij — niet de moeite waard voor deze frequentie. Zet er een
comment bij dat de bron `web/api/hba-branche-seed.php` in de CE-app is en dat een update daar hier
handmatig moet worden overgenomen (kopieer het array-blok, dat is letterlijk het hele werk).

**Branches die niet in de lijst passen (bijv. onderwijs).** Niet in de scan-tool zelf oplossen door
een branche te verzinnen — `branche_code` moet altijd een geldige `branche_library`-rij in de
CE-app blijven, anders lopen de twee kopieën uit elkaar. Twee dingen:

1. In de UI (§3) komt een expliciete **"Overig / geen goede match"**-optie die de
   distinctiveness-check overslaat (net als "geen branche gekozen") — beter een eerlijke
   "weet niet" dan een cliché-check tegen de verkeerde branche, die zou pure ruis opleveren.
2. Als onderwijs (of een andere ontbrekende branche) vaker voorkomt: dat is een aanvulling op
   `branche_library` in de CE-app zelf (met een eigen archetype en `categorietaal`, curatiewerk
   dat bij Rogier's methodiek hoort — zie `hba-branche-seed.php`'s comment over de grotere
   bronset die nog niet volledig geïmporteerd is), niet iets om ad hoc in de scan-tool bij te
   plakken. Erik beslist wanneer dat de moeite waard is; dan sync je de uitbreiding gewoon mee in
   de statische kopie (optie 1 hierboven).

## 3. UI — contextvelden op het scanformulier

Op `index.php`, bij het invoeren van de LinkedIn-URL (het scherm waar Erik/Eva een scan starten),
drie optionele contextvelden (BrandArchitecture-stap 1):

1. **Branche** — pulldown met de 6 opties uit §2 plus **"Overig / geen goede match"**
2. **Wat doet dit bedrijf? (kort, in eigen woorden)** — vrij tekstveld (`company_description`)
3. **Contextprofiel** — pulldown met 6 waarden uit `HBA_CONTEXT_PROFILES` (statische kopie in
   `includes/context-profiles.php`)

Branche-details:
- Alle drie optioneel — zonder invoer werkt de scan zoals voorheen (zie §4 en §10.2).
- Pulldown toont het menselijke label ("Retail / consumentenmerken"), opgeslagen wordt
  `branche_code` ("retail") — zelfde patroon als overal elders in de app.
- Hint-tekst branche, kort en functioneel: *"Helpt clichés in deze markt te herkennen bij het
  beoordelen van de kernboodschap — optioneel."*
- Sla `branche_code`, `company_description` en `contextprofiel` op bij de scan-rij (§5), niet los —
  een "Opnieuw ophalen" op een bestaande scan moet de eerder gekozen context automatisch hergebruiken,
  niet opnieuw vragen.
- **Context is achteraf aanpasbaar.** Op de detailpagina van een scan (`scan.php`) staat één formulier
  ("Context toepassen") met alle drie velden — niet alleen bij het aanmaken op `index.php`. Reden:
  precies de Codam-situatie (eerst "Overig", later blijkt een branche toch bruikbaar) en oudere
  scans van vóór deze feature. Een context-wijziging hoeft geen nieuwe Apify-scrape te triggeren —
  zie §5 voor het hergebruiken van `raw_data`.
- **Branche was eerder apart aanpasbaar** — nu samengevoegd in het scancontext-formulier op
  `scan.php` (POST `update_scan_context`).
- **Scan-historie** op `index.php` toont de gekozen branche naast de bestaande regel, bijv.
  "Codam College · retail · 4 posts" — of "geen branche" als die niet gezet is. Kleine toevoeging,
  maakt in één oogopslag duidelijk welke scans al een distinctiveness-verdict kunnen tonen.
- Zelfde CE-fieldstijl als de rest van de app (`Pulldown`/pink field-pattern uit
  `docs/igisnext-identity.md`) — geen losse styling verzinnen.

## 4. Narrative-analyzer — distinctiveness naast consistentie

Huidig schema van `li_analyze_narrative()` in `narrative-analyzer.php`: `core_message`,
`recurring_themes`, `consistency_label`, `consistency_reasoning`, `inconsistency_examples`. Dat
blijft ongewijzigd — dat is en blijft de consistentie-meting.

**Hygiënefactoren wél meenemen, maar als apart, zachter signaal.** `hba-branche-seed.php` heeft
naast `categorietaal` ook `hygienefactoren` per branche — check zelf het exacte veldformaat in dat
bestand (waarschijnlijk ook een JSON-array, niet geverifieerd in deze briefing). Neem ze mee in de
prompt, maar niet als hetzelfde soort signaal: `categorietaal` is letterlijke cliché-tekst
("wij begrijpen jou"), `hygienefactoren` zijn basisverwachtingen van de branche (dingen die
sowieso van je verwacht worden, geen unieke frase, maar ook geen onderscheid). Een merk dat alleen
hygiënefactoren claimt is net zo min uniek als een merk dat clichés herhaalt — dus beide tellen mee
richting "nee"/"gedeeltelijk" — maar de prompt moet ze apart labelen zodat
`distinctiveness_reasoning` het juiste woord gebruikt ("dit is een basisverwachting in de branche"
versus "dit is een cliché-frase die concurrenten ook gebruiken"). Geef dus twee losse lijsten mee
in de user-message, niet samengevoegd tot één.

**Nieuw, alleen als er een `branche_code` is meegegeven aan de functie:** geef de
`categorietaal`-clichélijst (en `hygienefactoren`-lijst) van die branche mee in de user-message, en
breid het schema uit met:

```json
{
  "distinctiveness_label": "ja" | "gedeeltelijk" | "nee",
  "distinctiveness_reasoning": "max 2 zinnen, concreet — welk cliché overlapt, of wat maakt het onderscheidend",
  "matched_cliches": ["0-3 items uit de meegegeven categorietaal-lijst die overlappen met hun boodschap; leeg als label 'ja' is"]
}
```

Aanvulling op `LI_NARRATIVE_SYSTEM_PROMPT`:

```
Als er een lijst met branche-clichés is meegegeven: beoordeel of de kernboodschap van dit bedrijf
zich onderscheidt van die clichés, of er juist in past.
  "ja"           — de kernboodschap zegt iets dat de meeste concurrenten in deze branche niet
                    zouden claimen; geen overlap met de meegegeven clichés
  "gedeeltelijk" — deels eigen taal, deels overlappend met een cliché uit de lijst
  "nee"          — de kernboodschap valt (bijna) letterlijk samen met één of meer clichés uit de
                    lijst; noem ze in matched_cliches
Wees hier net zo strikt als bij consistency: dit is geen compliment-generator. Een merk dat
"kwaliteit en persoonlijke service" claimt in een branche waar dat op de clichélijst staat, is
"nee" — ook als die claim consistent terugkomt.
```

De laatste zin is bewust scherp: dit voorkomt dat het model "gedeeltelijk" als beleefde
tussenweg kiest voor elk merk dat niet overduidelijk uniek is.

**Vrijwel geen extra kosten — geen reden om bestaande velden in te korten.** Dit zit in dezelfde
Claude-call als de consistentie-analyse (niet een tweede call). De toevoeging is klein: één
enum-label, 1-2 zinnen reasoning, maximaal 3 korte quotes. Verhoog `max_tokens` van 512 naar
ongeveer 700 en de kostenraming in `li_analyze_narrative()` (nu hardcoded op 300 outputtokens,
regel ~100) naar ongeveer 400 — bij Haiku-tarieven is dat een verwaarloosbare toename tegenover de
bestaande `narrative_max_cost_usd_per_scan`-drempel van $0,02. `core_message`/`recurring_themes`
hoeven niet ingekort te worden.

**Zonder branche_code:** functie werkt exact zoals nu, geen distinctiveness-velden in de output.
Bestaande scans zonder branche blijven gewoon renderen (§5, backward compatible).

## 5. Opslag

Kolommen op de bestaande `scans`-tabel:

```sql
ALTER TABLE scans ADD COLUMN branche_code VARCHAR(64) NULL;
ALTER TABLE scans ADD COLUMN company_description TEXT NULL;
ALTER TABLE scans ADD COLUMN contextprofiel VARCHAR(32) NULL;
```

Migratiebestanden: `linkedin-analyse/sql/add_branche_column.sql` en
`add_scan_context_fields.sql` — zelfde patroon als `add_narrative_cost.sql` en
`add_auth_tables.sql`. Productie: idempotent via `migrate.php?key=…` (sha256 van app-wachtwoord),
genoemd in `BUILD.md`'s migratiesectie.

De `distinctiveness_*`- en `description_alignment_*`-velden hoeven geen eigen kolom: ze landen in
dezelfde `metrics.narrative` JSON-kolom die er al is, naast `core_message`/`consistency_label`/etc.
`company_description` staat wél als kolom (grondwaarheid voor herberekening) én wordt in metrics
gekopieerd voor rendering (case-line).

**Context achteraf zetten/wijzigen op een bestaande scan (§3) — hergebruik het bestaande
raw_data-mechanisme.** `BUILD.md` noemt al: *"Oude scans zonder `metrics.narrative` krijgen bij
openen automatisch narrative uit `raw_data`"* (binnen de 90-dagen retentie, geen Apify-kosten).
Zelfde route hier: als branche, bedrijfsomschrijving of contextprofiel op een scan wordt gezet of
gewijzigd, herbereken alleen de narrative-analyse opnieuw vanaf het gecachte `raw_data` — geen
nieuwe Apify-run nodig (`li_scan_recompute_narrative()`).

## 6. Report-renderer — tonen in de mail

**Volgorde omgedraaid: Onderscheidend vermogen vóór Consistentie.** Distinctiveness is nu de
scherpere, nieuwere claim uit de pitch — dezelfde reden waarom hij ook in de case-line voorrang
krijgt (zie onder). Alleen tonen als `branche_code` gezet is; anders blijft alleen het
bestaande consistentie-blok staan, ongewijzigd.

In `li_render_narrative_html()`, vóór het bestaande consistentie-blok, als `distinctiveness_label`
aanwezig is:

```
Onderscheidend vermogen: {label}
{distinctiveness_reasoning}
```

met een sentimentbolletje (`li_render_sentiment_dot()`, zelfde patroon als consistentie):
`ja` → good, `nee` → bad, `gedeeltelijk` → neutral.

**`matched_cliches` — verdeel over case-line en narrative-blok, toon niet alles op één plek.** De
case-line (het openingsargument van de hele mail, § onder) krijgt precies **één** quote — de
scherpste, of gewoon de eerste uit de lijst. Het narrative-blok hieronder toont **alle** gevonden
matches (max 3, zoals het schema al begrenst) — dat is de onderbouwing die de case-line-quote
geloofwaardig maakt, geen selectief bewijs:

> Onderscheidend vermogen: nee
> Deze kernboodschap raakt bekende patronen in de branche: "wij begrijpen jou als geen ander",
> "kwaliteit voor iedereen".

Dat is confronterend maar precies het punt: het is geen mening, het zijn clichés uit een
vastgelegde lijst, letterlijk aangewezen.

### Case line — prioriteitsketen (description_alignment → distinctiveness → consistency)

**In `li_render_case_line()` geldt deze volgorde:**

1. `description_alignment` (nee/deels) — alleen als `company_description` is meegegeven (§10.2)
2. `distinctiveness` (nee/ja/gedeeltelijk) — zodra `branche_code` gezet is
3. `consistency` (hoog/laag/anders)
4. kwantitatieve fallback

Distinctiveness wint altijd vóór consistency zodra branche gezet is — ook als `consistency_label`
toevallig `hoog` is. Dat is geen samenloop om te vermijden, het ís het punt van deze uitbreiding:
een merk kan consistent én generiek zijn. Val terug op de volgende laag als het betreffende veld
niet beschikbaar is.

**Distinctiveness-templates** (na description_alignment-check), naar analogie van de bestaande
`hoog`/`laag`/tussenweg-opbouw:

**`nee`:**

> Bij {bedrijf} klopt het verhaal consistent — maar het is ook precies wat de rest van de branche
> zegt: "{eerste matched_cliche}". Dat is niet zozeer een contentprobleem als een
> positioneringsprobleem, en dat is precies waar ContentFlow bij helpt: hieronder zie je waar het
> overlapt en waar de ruimte zit om je te onderscheiden.

**`ja`:**

> Bij {bedrijf} is geen overlap te vinden met wat de rest van de branche claimt — "{core_message}"
> is hun eigen taal, geen branche-cliché. Dat is zeldzamer dan de meeste bedrijven denken. Het
> probleem zit dus niet in wát ze te zeggen hebben.

gevolgd door, indien `reachPhrase` beschikbaar is (zelfde variabele als de bestaande
`hoog`-tak al berekent):

> Het zit in hoe vaak: {reachPhrase}. Precies daar zit de winst — dezelfde scherpe positionering,
> vaker verteld.

of anders:

> Waar op dit moment de meeste winst zit, lees je hieronder.

**`gedeeltelijk`:**

> Bij {bedrijf} is de kernboodschap deels eigen taal, deels iets wat je vaker hoort in deze
> branche: "{eerste matched_cliche}". De helft van een onderscheidende positionering hebben ze dus
> al te pakken; de andere helft is precies waar ContentFlow om draait: elk artikel automatisch
> toetsen aan één vastgelegd merkprofiel, zodat de content het onderscheidende deel versterkt in
> plaats van het generieke deel.

## 7. Testen

1. Scan zonder branche gekozen → ongewijzigd gedrag, geen distinctiveness-blok in de mail.
2. Scan met branche `retail` op een merk waarvan de posts overduidelijk cliché-taal bevatten
   ("wij bieden kwaliteit voor iedereen") → verwacht `distinctiveness_label: nee` met de juiste
   cliché in `matched_cliches`.
3. Scan met branche op een merk met een scherpe, herkenbare eigen positionering → verwacht `ja`,
   `matched_cliches` leeg.
4. "Opnieuw ophalen" op een bestaande scan met een eerder gekozen branche → branche wordt
   hergebruikt, niet opnieuw gevraagd.
5. Kosten-check: `narrative_cost_usd` per scan blijft in dezelfde orde grootte als vóór deze
   wijziging (zelfde aantal calls, iets groter schema).

## 8. Acceptatiecriteria

- Branche-veld op het scanformulier, optioneel, 6 opties + "Overig / geen goede match", zelfde
  CE-fieldstijl, met hint-tekst.
- Gekozen branche wordt opgeslagen per scan en hergebruikt bij "Opnieuw ophalen"; branche is
  achteraf te zetten/wijzigen op de scan-detailpagina zonder nieuwe Apify-run.
- Branche zichtbaar in de scan-historie op `index.php`.
- Zonder branche (of "Overig"): scan werkt exact zoals nu, geen distinctiveness-verdict.
- Met branche: narrative-analyse levert `distinctiveness_label`/`distinctiveness_reasoning`/
  `matched_cliches`, met `categorietaal` én `hygienefactoren` als aparte, gelabelde signalen in de
  prompt.
- Onderscheidend-vermogen-blok staat vóór het consistentie-blok in de mail; case-line gebruikt
  distinctiveness altijd als opening zodra branche gezet is, met de drie templates uit §6.
- Eén quote uit `matched_cliches` in de case-line, alle (max 3) in het narrative-blok.
- Geen tweede Claude-call — distinctiveness zit in dezelfde call als consistentie; `max_tokens`
  en de kostenraming zijn opgehoogd, bestaande velden zijn niet ingekort.
- Bestaande scans zonder branche blijven ongewijzigd renderen.
- Bedrijfsomschrijving + contextprofiel optioneel op `index.php`; achteraf via "Context toepassen"
  op `scan.php`; `description_alignment_*` in narrative + case-line prioriteit (§10.2–10.4).

## 9. Deploy, scope en fasering

**Migratie productie.** Uitgevoerd aug 2026 via `migrate.php?key=…` na deploy
(`add_branche_column.sql` + `add_scan_context_fields.sql`). Handmatig alternatief: `mysql … <
linkedin-analyse/sql/…`, genoemd in `BUILD.md`'s migratiesectie.

**Scope: alleen de LinkedIn Scan-tool (Cursor-vraag 15).** Bevestigd — deze distinctiveness-check
gaat nu niet naar de CE BrandArchitecture-wizard of de HBA-scoring zelf. Dat zou een heel andere
briefing zijn (raakt `hba-prompt.php`'s PBS-scoring en `category_fallback`-logica, niet iets om
er even bij te doen). Als deze check in de scan-tool bewijst dat hij waarde toevoegt, is
"distinctiveness ook in de PBS-score" een aparte, latere beslissing.

**Fasering (Cursor-vraag 16) — twee stappen, niet in één keer.**

1. **Eerst:** §2-5 — branche-bibliotheek (statische kopie), UI-veld, opslag, en de
   narrative-analyzer-uitbreiding (schema + prompt). Dit levert de data op en is te testen
   los van de mail (§7, tests 1-3) — het risico zit in de prompt/schema-kant, niet in de opmaak.
2. **Daarna:** §6 — report-renderer (blokvolgorde, case-line templates, matched_cliches-verdeling).
   Presentatielaag over data die al bewezen goed werkt.

Niet omgekeerd: opmaak bouwen over een nog ongeteste prompt betekent tweemaal werk zodra de
prompt bijgesteld moet worden.

---

## 10. Update 19 aug — intro herschreven, vertaalbug, en twee extra invoervelden

### 10.1 Al gedaan (code-niveau, niet meer te bouwen)

**Vertaalbug gefixt.** De live tekst bevatte *"je kunt consistent midden in de pad zijn"* — een
kapotte poging tot een Engelse uitdrukking ("middle of the road"), en ook grammaticaal fout ("de
pad" moet sowieso "het pad" zijn). Vervangen door de simpele, correcte formulering: *"inwisselbaar
zijn met de rest van hun categorie"* — geen idioom nodig, gewoon direct zeggen wat er bedoeld
wordt. Check bij toekomstige teksten: geen Engelse uitdrukkingen woord-voor-woord vertalen, herschrijf
het idee in gewoon Nederlands.

**Intro herschreven — USP binnen de eerste twee zinnen, niet er pas na een alinea uitkomen.** Was:
een hele alinea uitleg over waaróm consistentie niet volstaat, vóórdat er iets over ContentFlow
zelf werd gezegd. Nu: eerste zin is direct de twee-delige USP. Consistentie is in de nieuwe tekst
het **middel** (hoe je positionering vasthoudt), niet de belofte zelf — dat onderscheid was eerder
al inhoudelijk juist, maar stond te laat in de tekst om als opening te werken. Huidige tekst in
`li_render_intro_html()`:

> "ContentFlow helpt merken twee dingen goed doen: een unieke positionering vinden, en die daarna
> consistent communiceren in alles wat ze posten. De meeste merken missen één van de twee — ze
> weten niet precies wat hun onderscheid is, of ze weten het wel en verwateren het toch in de
> uitvoering."
>
> "Dat onderscheid is wat AI beloont: modellen en zoekmachines filteren het gemiddelde weg en
> herkennen wat opvalt, niet merken die — hoe consistent ook — inwisselbaar zijn met de rest van
> hun categorie. De HeroBrand-laag is daarom het hart van ContentFlow: geen check op &ldquo;gaat
> dit over ons merk&rdquo;, maar beoordeling of elke post je unieke positie draagt, of terugzakt
> naar iets wat iedereen had kunnen zeggen."

**HeroBrand-laag: geverifieerd, geen switch aanwezig, niets te bouwen.** Doorzocht op
toggle/checkbox/"optioneel" rond HBA/HeroBrand in de hele `linkedin-analyse`-map — niets gevonden.
De laag wordt overal onvoorwaardelijk beschreven ("is daarom het hart van ContentFlow"), er is geen
UI-control die hem uit kan zetten. Dit was dus al in orde; geen wijziging nodig. Let er bij de
punten hieronder wel op dat er ook geen *nieuwe* HBA-schakelaar bij sluipt.

### 10.2 Al gedaan — twee extra invoervelden uit de BrandArchitecture-wizard

Screenshot van BrandArchitecture stap 1 laat drie invoeren zien. Branche (§2-3) was de eerste;
**bedrijfsomschrijving en contextprofiel zijn aug 2026 live** op `index.php` en achteraf op
`scan.php` (formulier "Context toepassen"). Ze lossen een concreet probleem op: de
narrative-analyse gokte puur op basis van LinkedIn-posts wat een bedrijf doet — Doornvogel
(loyalty/engagement-bureau) kwam terug als "strategische partner". Nu vertelt Erik/Eva het model
wat het bedrijf doet.

**Veld 1 — vrije tekst: bedrijfsomschrijving.** Zelfde functie als de tekstvraag boven "In welke
markt" in de wizard-screenshot ("Wie zijn jullie en in welke markt opereren jullie?"), maar dan
voor intern gebruik door Erik/Eva die een prospect scannen — dus geen uitnodigende
klant-formulering nodig, gewoon functioneel: **"Wat doet dit bedrijf? (kort, in eigen woorden)"**,
optioneel, vrij tekstveld op `index.php`. Voorbeeld invoer voor Doornvogel: *"Loyalty- en
engagement-bureau — bouwt klantenprogramma's voor merken."*

Opslag: `scans.company_description` (TEXT, NULL) en `scans.contextprofiel` (VARCHAR(32), NULL).
Migratie: `linkedin-analyse/sql/add_scan_context_fields.sql` + idempotent in `migrate.php`.
Statische kopie contextprofielen: `includes/context-profiles.php` (bron: `frontend/src/constants/hba.ts`).

**Twee verschillende rollen in de prompt — niet hetzelfde soort signaal:**

- `company_description` is **grondwaarheid**, geen extra meetsignaal. Meegegeven in de
  user-message; instructie in `LI_NARRATIVE_DESCRIPTION_PROMPT`. Outputvelden (alleen bij omschrijving):

  ```json
  {
    "description_alignment_label": "ja" | "deels" | "nee",
    "description_alignment_reasoning": "max 2 zinnen: laten de posts zien wat het bedrijf volgens de omschrijving daadwerkelijk doet, of blijft het generiek?"
  }
  ```

  Dit vangt precies de Doornvogel-situatie: een bedrijf waarvan de posts nooit concreet maken wat
  het merk uniek maakt, ook al is dat in werkelijkheid heel specifiek. Dat is een sterker signaal
  voor de case-line dan alleen consistentie of distinctiveness — "jullie noemen loyalty/engagement
  zelf bijna nooit" is confronterender én concreter dan "jullie zijn gemiddeld consistent".

- `contextprofiel` is **kalibratie**, geen los oordeel. Geen eigen output-veld — meegegeven via
  `LI_NARRATIVE_CONTEXT_PROMPT`; kalibreert de toon van `consistency_reasoning` (bijv. herpositionering
  als verklaring voor wisselende boodschappen, niet als onwil).

**Kosten:** nog steeds één Claude-call. `description_alignment_*` is klein (label + 1-2 zinnen) —
geen noemenswaardige toename op de al opgehoogde `max_tokens`/kostenraming uit §4.

**Beide optioneel, zelfde patroon als branche:** zonder invoer werkt de analyse zoals nu, zonder
de extra velden. Achteraf toe te voegen/wijzigen op de scan-detailpagina, zelfde
raw_data-hergebruik-mechanisme als branche (§5).

### 10.3 Al gedaan — description_alignment in case-line prioriteitsketen

Geïmplementeerd in `li_render_case_line()`. Als `description_alignment_label: nee` of `deels`
beschikbaar is, gaat die **vóór** distinctiveness — het scherpste, meest concrete signaal.
Volgorde: `description_alignment` (nee/deels) → `distinctiveness` → `consistency` → kwantitatief.

Voorbeeldtekst voor `description_alignment_label: nee`:

> Bij {bedrijf} lezen we in de posts niet concreet terug wat het bedrijf volgens de omschrijving
> daadwerkelijk doet — "{company_description}" komt in de eigen taal nauwelijks naar voren. Dat is
> geen contentprobleem maar een herkenbaarheidsprobleem: lezers én AI-modellen moeten dat er zelf
> uit kunnen halen, en nu kan dat niet.

### 10.4 Testen en acceptatiecriteria — afgerond (aanvulling op §7-8)

Geïmplementeerd en geverifieerd aug 2026:

- Scan zonder de twee nieuwe velden → ongewijzigd gedrag. ✅
- Scan met `company_description` op vaag bedrijf → `description_alignment_label: nee` of `deels`. ✅
- Scan met `contextprofiel: herpositionering` → `consistency_reasoning` kalibreert context. ✅
- Case-line-prioriteit: description_alignment (nee/deels) > distinctiveness > consistency > kwantitatief. ✅
- Beide velden achteraf te zetten op bestaande scan, zonder nieuwe Apify-run ("Context toepassen"). ✅

### 10.5 Score-samenvatting toegevoegd — direct na de intro, vóór "De cijfers"

Ontdekt gat: "De cijfers" (het eerste overzicht dat een lezer ziet) was 100% kwantitatief — ritme,
stiltes, reacties. `distinctiveness_label` en `consistency_label` stonden alleen in het
"Wat ze over zichzelf vertellen"-blok, ver onderin de mail. De intro belooft sinds §10.1 letterlijk
"ContentFlow doet twee dingen", maar die twee dingen waren nergens als score te zien vóór iemand
helemaal doorscrolt.

**Al gefixt (code-niveau):** `li_render_score_summary_html()` in `report-renderer.php`, direct na
de intro-pitch en vóór "De cijfers". Rijen (alleen als data bestaat):

- "Posts tonen wat het bedrijf doet" (`description_alignment_label`) — bij ingevulde omschrijving
- "Unieke positionering (t.o.v. branche)" (`distinctiveness_label`) — bij branche
- "Consistent gecommuniceerd" (`consistency_label`)

Zelfde sentimentbolletjes als de rest van de mail. Geen narrative-analyse → hele blok verdwijnt.

### 10.6 Al gedaan — Vertaalbug #2 — Engelse redeneer-parenthese in LLM-output

Live gezien op een scan: een `recurring_themes`-item (waarschijnlijk) kwam terug als
*"Merkidentiteit (implicit in 'samen vieren', 'samenwerking')"* — een Engelse citatiestijl
("implicit in ...") die door de bestaande TAALREGELS/FOUT→GOED-tabel niet werd gedekt (die richt
zich op marketingjargon als "engagement"/"impactful", niet op dit soort redeneer-connectief).

**Twee lagen gefixt, niet één — de regex-blocklist alleen is dweilen met de kraan open.**

1. **Prompt-niveau (bron van het probleem):** nieuwe regel in `LI_NARRATIVE_SYSTEM_PROMPT` —
   thema's zijn labels, geen redeneringen; onderbouwing hoort in `consistency_reasoning`, nooit
   tussen haakjes achter een thema. Plus een expliciet verbod op de constructie
   "(implicit/explicit in ...)" met dit exacte geval als nieuw FOUT→GOED-voorbeeld.
2. **Vangnet-niveau (`li_narrative_polish_dutch()`):** `implicit`/`explicit` toegevoegd aan de
   cognaat-correcties, plus een aparte regex die de hele Engelse redeneer-parenthese
   `(implicit/explicit in '...')` verwijdert als hij tóch doorglipt — vangt zowel de Engelse als de
   inmiddels vertaalde vorm.

Structurele les voor het vervolg: de FOUT→GOED-tabel dekt marketingjargon (woord-voor-woord
swaps), maar dit was een heel ander soort lek — een Engelse *zinsconstructie* (citatiestijl), geen
los woord. Blijft dit patroon terugkomen in andere vormen, dan is een losse regex per geval niet
houdbaar; dan is een tweede, korte "vertaal-check"-call (of een strengere `max_tokens`-cap die
gedrongen, spaarzamer taalgebruik afdwingt) een structurelere oplossing. Niet nu bouwen — wel in de
gaten houden als het na deze fix nog een keer opduikt in een andere vorm.

### 10.7 Al gedaan — UX-polish scan-detail en startknop (19 aug)

**Apple Mail-banner verwijderd.** De info-banner op `scan.php` ("Apple Mail: klik Kopieer voor
e-mail…") is weg — de knoppen "Kopieer voor e-mail" en "Download als HTML" blijven staan; de
copy-success-melding onder de knoppen volstaat.

**Scan starten-knop — grijs met animatie tijdens submit.** Op `index.php` schakelt de knop bij
submit naar beige/grijs (disabled-stijl: transparant, beige rand/tekst) met denk-animatie
("Bezig met scannen..."), niet groen blijven. CSS: `.pill-primary.is-busy` in `assets/app.css`.

**Scancontext-formulier op detailpagina.** Branche, bedrijfsomschrijving en contextprofiel staan in
één blok met knop "Context toepassen" (vervangt het aparte "Branche toepassen"-formulier).
