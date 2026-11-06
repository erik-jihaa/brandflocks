# Cursor-briefing — HBA-laag v2: van observator naar meedenker

**Status:** in bouw — beslissingen §7 vastgelegd (aug 2026, Erik)
**Datum:** aug 2026
**Bron:** feedback Rogier Heijning (Hero Brands), aug 2026 — vier documenten + drie schermontwerpen
in `briefing/hba-input/`
**Vervangt gedeeltelijk:** `briefing/cursor-hba-brandlaag.md` (zie §1)

---

## 1. Documentvoorrang — lees dit eerst

Er liggen nu vijf HBA-documenten en ze spreken elkaar op punten tegen. Deze volgorde geldt:

| Prioriteit | Document | Waarvoor |
| --- | --- | --- |
| 1 | **Dit document** | de vertaling naar onze codebase; wint bij elk conflict |
| 2 | `hba-input/HBA_laag_Bouwkit_voor_AI_coder.docx` | de drie ingrijppunten + de twee systeemprompts (letterlijk overnemen) |
| 3 | `hba-input/HBA_brandlaag_Merkarchitectuur_Wizard.docx` | de acht stappen van de merkarchitectuur-flow + afleidingslogica |
| 4 | `hba-input/HBA_brandlaag_Complete_Implementation_Kit_v1_2.docx` | scoring, trackers, PBS — pas nodig bij ingrijppunt 3 |
| 5 | `briefing/cursor-hba-brandlaag.md` | **grotendeels achterhaald.** De analyse van botsingen met onze codebase (§2) blijft geldig; het UI-ontwerp en de plaatsing niet |

De systeemprompts uit document 2 zijn intellectueel eigendom van Hero Brands. Neem ze **letterlijk**
over, zonder ze te herformuleren of samen te vatten.

---

## 2. Wat er verandert, in één alinea

De HBA-laag die we bouwden kijkt achteraf en geeft een cijfer. Rogier's oordeel: dat komt te laat
en het laat de klant met een probleem zitten dat hij zonder strateeg niet kan oplossen. De laag
moet daarom **meedenken op drie momenten**: bij de themakeuze (vooraf, waar bijsturen nog gratis
is), tijdens het schrijven (één concrete aanwijzing met een "pas toe"-knop), en achteraf als stille
bewaker (één cijfer, geen dashboard). Daarnaast moet alle vaktaal uit de klant-UI — en die zit er
nu écht in.

**De toets die je langs elk scherm legt:** vraagt de app hier iets wat alleen een strateeg kan
beantwoorden, of toont hij een term die je alleen begrijpt als je de methode kent? Dan is de app op
dat punt kapot voor deze klant.

---

## 3. Wat we NIET slopen — lees dit vóór je begint

Rogier kent onze codebase niet en beschrijft op twee plekken een opruiming die werkende machinerie
zou vernietigen. Zijn richting klopt, zijn ingreep niet. Beide gevallen: **verbergen, niet
verwijderen.**

### 3.1 Tone / Angle / Objective zijn geen labels maar besturing

De bouwkit zegt: *"Vervang de huidige rechterkolom met Rationale / Tone / Angle / Objective door
deze ene aanwijzing."*

Die kolom is geen weergave. Wat er onder hangt:

- **Tone** en **Angle** worden gezet door CE-03 stap C en sturen de schrijfstijl (`tone_final`,
  `angle_final` in `articles`).
- **`image_ratio`** bepaalt de beeldverhouding bij publicatie, **`platform`** het kanaal.
- **Objectives** is een compleet live scherm (`/targets`) met een eigen catalogus, en
  `objective_label` + `objective_description` gaan mee in de schrijfprompt van CE-03 stap A.
- **Rationale** is de brug tussen CE-03 stap A en stap B — verwijder je die, dan schrijft stap B
  zonder strategische onderbouwing.

**Wat we wel doen:** de HBA-aanwijzing komt **bovenaan** de rechterkolom, prominent, zoals in
schermontwerp 2. Alles daaronder klapt in achter één regel `Details`. Standaard dicht. De klant
ziet de aanwijzing; de machinerie blijft draaien.

### 3.2 StyleGuide bevat meer dan de merkarchitectuur

Het bouwdocument zegt: *"Bouw dit als vervanging van het huidige StyleGuide-scherm."*

Maar `/styleguide` bevat ook BrandValues (`brand_goal`), de schrijfwijzer en `tone_styles`, en die
blijven zichtbaar **ook als HBA uit staat** (`clients.hba_enabled`, BUILD-NOTES § HBA uit).
Tenants zonder HBA-licentie hebben dat scherm nodig.

**Naamgeving — geen "wizard" in de nav.** "Wizard" is zelf een van de twaalf HBA-archetypen (de
Tovenaar). Die term als schermnaam gebruiken voor de invulflow botst met de content die erin staat
— een klant met dat archetype zou kunnen denken dat de link over zijn eigen merk gaat. We gebruiken
"wizard" daarom alleen nog als interne verwijzing naar het brondocument, nooit als UI- of route-naam.

**Wat we wel doen:** géén apart nav-item voor de invulflow. Het nav-item blijft **Merkarchitectuur**
op `/styleguide` (hernoemd, route mag blijven of verhuizen naar `/identity`). Die ene pagina kent
twee toestanden: is de architectuur nog leeg, dan opent automatisch de begeleide flow uit hoofdstuk
3 (acht stappen, één doorlopende sessie, eindigend op het bevestigingsscherm). Is ze al ingevuld,
dan toont de pagina het overzicht met een knop **Nog iets aanpassen** die dezelfde stappen opnieuw
opent. Eén scherm, geen los ding om te benoemen — dus geen naamgevingsprobleem meer. Bij
`hba_enabled = false` toont de pagina alleen de niet-HBA-velden en verschijnt de begeleide flow
niet.

---

## 4. Veldmapping — Rogier's namen naar ons schema

Rogier gebruikt eigen veldnamen. Dit is de vertaalsleutel; gebruik hem overal.

| Rogier | Ons schema | Status | UI-label (klant ziet) |
| --- | --- | --- | --- |
| `archetype` | `clients.archetype` | bestaat, wordt enum (12 HBA-archetypen) | Je persoonlijkheid |
| `foe` | `clients.adversary` | bestaat — **niet hernoemen in de DB** | Waar jullie tegen vechten |
| `doel` | `clients.brand_goal` | bestaat | Wat jullie willen bereiken |
| `tone_of_behaviour` | `clients.tone_of_voice` | bestaat | Jullie toon |
| `distinctiviteit` | — | **nieuw** | Wat maakt jullie anders |
| `wat_bewust_niet` | `brand_narratives.verboden_logica` (deels) | deels aanwezig | Wat jullie bewust niet doen |
| `regels` | `brand_narratives.geloofwaardigheidstest` | aanwezig, wordt meerdere rijen (max 5) | Jullie regels |
| `branche` / `categorietaal` | — | **nieuw**, eigen tabel | In welke markt |
| `goede_voorbeelden` / `zwakke_voorbeelden` | `brand_narratives.*` | aanwezig | Welke voelt als jullie |
| `contextprofiel` | `brand_narratives.contextprofiel` | aanwezig | Jullie situatie |

**Regel:** kolomnamen in de database veranderen we niet — `adversary` blijft `adversary`. Alleen de
UI-labels gaan naar mensentaal. Een rename raakt CE-01, CE-02, CE-03 en de exports, en levert
niets op.

**Naamgevingsconventie voor nieuwe tabellen:** Engels, in lijn met `hba_scores` en
`brand_narratives`. Rogier's `hba_thema_oordeel` wordt `hba_theme_verdicts`, zijn
`hba_artikel_aanwijzing` wordt `hba_article_hints`. De ENUM-waarden blijven Nederlands
(`versterkt` / `neutraal` / `verwatert`) omdat de LLM ze zo teruggeeft.

---

## 5. De vier werkpakketten

### WP1 — Vaktaal opruimen (begin hier)

Snelste winst, geen nieuwe logica, en het is aantoonbaar kapot: `BrandNarrativeFields.tsx` toont nu
letterlijk labels als **Bewijsvelden**, **Verboden logica**, **Woorden die passen (min. 3)** en
**Contextprofiel**. Dat is precies het lek dat Rogier signaleert.

| Nu in de UI | Wordt | Waar |
| --- | --- | --- |
| StyleGuide | **Merkarchitectuur** | nav (Identity) + paginatitel |
| Archetype | Je persoonlijkheid | `BrandNarrativeFields.tsx` |
| Adversary | Waar jullie tegen vechten | Overview, StyleGuide |
| Bewijsvelden | Waar blijkt dat uit | `BrandNarrativeFields.tsx` |
| Verboden logica | Wat jullie bewust niet doen | idem |
| Woorden die passen / niet passen | Woorden die bij jullie horen / niet | idem |
| Contextprofiel | Jullie situatie | idem |
| Objectives met percentages | achter `Details` | `/targets` |
| `write_directive`, `CE-03`, `reviewUrl` | weg uit alles wat de klant ziet | diverse |
| Onverwerkte Markdown (`###`, `##`) | renderen of strippen | alle tekstvelden |

De labels in de rechterkolom van Article (Tone, Angle, Objective) verdwijnen niet — die gaan achter
`Details`, zie §3.1.

**Vuistregel voor de rest:** is een label alleen te begrijpen door iemand die de methode kent, dan
hoort het niet in de klant-UI.

### WP2 — Merkarchitectuur vaststellen (de begeleide flow)

Acht stappen, één sessie, uit `HBA_brandlaag_Merkarchitectuur_Wizard.docx` hoofdstuk 3, geopend
vanuit het Merkarchitectuur-scherm zodra dat leeg is (zie §3.2 — geen los nav-item). Neem de
placeholder-teksten letterlijk over; ze zijn zorgvuldig geformuleerd en leggen de lat met een
sterk-versus-zwak voorbeeld.

Drie soorten velden, en dat onderscheid is de kern:

- **Direct vragen** (stap 1–5): wie je bent, wat je wilt bereiken, wat je anders maakt, wat je
  bewust niet doet, jullie regels.
- **Tool leidt af** (stap 6): archetype, foe en tone_of_behaviour worden voorgesteld op basis van
  stap 2–5 plus branche, met motivatie en twee alternatieven. **Nooit een leeg archetype-veld
  tonen.** Dit is technisch de enige echte toevoeging en tegelijk de belangrijkste.
- **Als keuze aanbieden** (stap 1 branche, stap 7 voorbeelden): aanwijzen is makkelijker dan
  formuleren.

Nieuw endpoint: `POST /api/hba/derive-architecture` — input/output-schema staat in het
wizard-document hoofdstuk 4. Nieuwe tabel `branche_bibliotheek` volgens hoofdstuk 5.3, met
`bron` = `afgeleid` of `gecureerd`.

Stap 5 (regels) is meerdere rijen met "voeg regel toe", maximaal 5. De commerciële lading — *"ook
al zou het geld opleveren"* — moet in de vraag blijven staan; zonder die lading krijg je slappe
regels, en regels zijn een **harde toets** in de scoring.

Eindig met het bevestigingsscherm (stap 8) plus het premium-bruggetje naar een HBA-strateeg.

#### WP2.1 — Stap 7 (voorbeelden): ja genereren, maar let op drie dingen

**Ja, de opties worden gegenereerd.** Dat is precies het punt van stap 7: aanwijzen is makkelijker
dan formuleren. Zes lege tekstvakken vragen om drie sterke en drie zwakke voorbeelden is voor deze
klant een onbeantwoordbare opdracht — en het is wat er nu staat (`BrandNarrativeFields.tsx`, velden
*Sterk voorbeeld 1–3* en *Zwak voorbeeld 1–3*). Genereer op basis van branche (stap 1) en het
zojuist bevestigde archetype (stap 6), zoals het wizard-document beschrijft.

**Let op 1 — het aantal klopt niet met het bouwdocument.** De backend eist **exact drie** goede en
**exact drie** zwakke voorbeelden (`hba-repository.php`: `count($items) !== 3` → validatiefout), en
het JSON-schema in de Complete Kit doet hetzelfde. Het wizard-document beschrijft stap 7 in het
enkelvoud: *"Kies de zin die het meest klinkt als jullie merk, en de zin die er juist naast zit."*
Eén paar levert 1 + 1, niet 3 + 3.

**Oplossing: drie rondes van paren.** Toon per ronde twee zinnen, klant kiest welke als hun merk
klinkt; de niet-gekozen zin wordt automatisch het zwakke voorbeeld van die ronde. Drie rondes →
exact 3 + 3, en de klant heeft drie keer één simpele keuze gemaakt in plaats van zes velden
ingevuld. Genereer de zes zinnen in één call, gevarieerd genoeg dat elke ronde een echte keuze is
(niet drie keer dezelfde tegenstelling).

**Let op 2 — pas op voor de gesloten lus.** Deze voorbeelden zijn geen decoratie: de scoringsprompt
gebruikt ze letterlijk als **kalibratie-anker** (*"Gebruik goede_voorbeelden en zwakke_voorbeelden
als kalibratie-ankers"*, Complete Kit hoofdstuk 4). Genereert het model de ankers én scoort het
daartegen, dan kalibreert het model tegen zijn eigen idee van goed — en de klantkeuze is dan het
enige echte signaal in de lus.

Twee dempers, allebei licht te bouwen:

- **Zaai met echt materiaal waar dat er is.** Heeft de tenant een LinkedIn-bron of website-bron
  ingesteld, gebruik dan eigen bestaande zinnen als kandidaat voor de "goede" kant in plaats van
  verzonnen zinnen. We hebben de scraper al staan. Een echte zin van het merk is een oneindig veel
  beter anker dan een gegenereerde.
- **Altijd een ontsnapping.** Onder elk paar een derde optie: *"Geen van beide — ik typ het zelf"*,
  die één vrij tekstveld opent. Klanten met een uitgesproken eigen taal moeten niet gedwongen
  worden te kiezen tussen twee zinnen die geen van beide kloppen.

**Let op 3 — de vrije velden verdwijnen niet.** De zes tekstvelden blijven bestaan als bewerkbare
weergave op het Merkarchitectuur-scherm (§3.2, toestand "al ingevuld"). De generatie is het
*invulpad*, niet de enige manier om eraan te komen. Wie later een voorbeeld wil aanscherpen, typt
het gewoon over.

Endpoint: dit kan als tak van `POST /api/hba/derive-architecture` of los als
`POST /api/hba/suggest-examples` met input branche + archetype + doel + foe. Sla op als de
bestaande `goede_voorbeelden` / `zwakke_voorbeelden` JSON-arrays — geen schemawijziging nodig.

### WP3 — Ingrijppunt 1: thema-advies (grootste winst)

Op `/theme`, nadat CE-01 de thema's heeft gegenereerd en vóór de klant kiest. Eén extra LLM-call
over de hele batch. Systeemprompt letterlijk uit de bouwkit hoofdstuk 3.2.

Per thema een rustig label (`versterkt` / `neutraal` / `verwatert`) met één zin toelichting, plus
bovenaan één regel `wat_ontbreekt` over de agenda als geheel. Zie schermontwerp 1: labels als
zachte pill, geen goed-fout-alarm, en onderaan de regel *"Je kiest zelf welk thema je pakt — dit is
advies, geen verplichting."*

**Dit is een aparte call, geen uitbreiding van de CE-01-prompt.** Twee redenen: CE-01 *genereert*
en deze laag *oordeelt* — dat door elkaar halen maakt beide slechter. En de HBA-prompt is
gelicentieerd IP dat scheidbaar moet blijven.

Wegschrijven naar `hba_theme_verdicts` (`theme_id`, `label`, `toelichting`, `judged_at`), zodat
ingrijppunt 3 er later over kan aggregeren.

### WP4 — Ingrijppunt 2: bijbuigen tijdens het schrijven

Op Article en Fastlane, wanneer een concept is gegenereerd of bewerkt. Systeemprompt letterlijk uit
de bouwkit hoofdstuk 4.2.

Eén aanwijzing, nooit een lijstje. Als er een herschreven passage is: knop **Pas toe** die de
passage in de editor vervangt, plus **Negeer**. Overtreedt het concept een eigen regel van het
merk, dan staat dat bovenaan als rustige maar duidelijke melding — dat heeft voorrang.

Wegschrijven naar `hba_article_hints` (`article_id`, `aanwijzing`, `regel_overtreden`, `toegepast`,
`judged_at`). Het veld `toegepast` is waardevol: het meet of de laag daadwerkelijk helpt.

Zie §7.2 — er staan straks drie adviespanelen naast één artikel en dat moet je oplossen voordat je
dit bouwt.

### WP5 — Ingrijppunt 3: stille bewaker (later)

Overview krijgt een compact blok, Report de uitgebreide versie. Berekening ongewijzigd uit de
Complete Kit v1.2 (PBS, trackers, erosion alerts); alleen de presentatie verandert: één cijfer
0–100 met pijl, een lijn over de weken, en alleen een melding als iets structureel wegzakt. Zie
schermontwerp 3.

Alle detailtrackers uit de Complete Kit — radar, Story Triangle, Contentrol Mix — mogen achter
`meer details`. Niet op Overview.

---

## 6. Bouwvolgorde

1. **WP1 — vaktaal opruimen.** Geen nieuwe logica, meteen merkbaar, maakt de rest lichter.
2. **WP3 — thema-advies.** Grootste positioneringswinst, één call, raakt weinig bestaande code.
3. **WP2 — merkarchitectuur vaststellen.** Meeste werk, maar WP3 en WP4 werken al op de bestaande velden.
4. **WP4 — bijbuigen bij het schrijven.** Pas nadat beslissing §7.2 genomen is.
5. **WP6 — opbouwfase op Overview** (§9). Vult het gat van tien weken en werkt op de data uit WP3
   en WP4. Klein werk, groot effect op het gevoel dat er iets gebeurt.
6. **WP5 — stille bewaker.** Pas zinvol vanaf 20 gescoorde posts.

Rogier zet de begeleide flow op plek 2. Ik draai WP2 en WP3 om: het thema-advies werkt al op
`archetype`, `adversary` en `brand_goal` die we vandaag al hebben, dus het levert waarde vóór de
merkarchitectuur-flow klaar is. Die flow maakt de input rijker, maar is er geen voorwaarde voor.

---

## 7. Beslissingen

### 7.0 Besluiten genomen (aug 2026, Erik)

| # | Onderwerp | Besluit |
| --- | --- | --- |
| 1 | Zonder HBA | Bestaande architectuur houden (StyleGuide, `/targets`, geen ingrijppunten) |
| 2 | HBA aan | **iGiSNext** (`016384af…`), **HeroBrands.nl** (`58cf95ac…`), **Come Into Bloom** (`63f84707…`) |
| 3 | Panelen | Eindproduct meteen: HBA-aanwijzing bovenaan, stap D ingeklapt, Editor Questions → Feedback — geen tussenstappen |
| 4 | Branche-bibliotheek | Rogier levert data-moat vandaag; wij bouwen tabel + import |
| 5 | Premium-bruggetje | `mailto:` volgens §7.5 — geen Calendly |
| 6 | Merkarchitectuur | **Aparte pagina** `/identity`, nav **Brand architecture** (EN), alleen zichtbaar bij `hba_enabled` |
| 7 | Route | `/identity` (redirect van `/styleguide` voor HBA-tenants) |
| 8 | Flow start | Geen archetype/tegenstander → flow direct openen |
| 9 | Archetype-migratie | Map bestaande vrije tekst naar dichtstbijzijnde HBA-archetype |
| 10 | Schema | Uit wizard-doc + SQL-migraties (`distinctiviteit`, `branche_bibliotheek`, …) |
| 11 | derive-architecture | Claude (model naar keuze bouwer), één call per stap, async “we stellen iets voor…” |
| 12 | Thema-advies zonder narrative | Blokkeren: “Vul eerst je merkarchitectuur in” |
| 13 | Ingrijppunt 2 | Bij elke CE-03-generatie; keuze testbaar op staging |
| 14 | Pas toe | Passage in editor + headline + CTA |
| 15 | Thema “gekozen” | Zodra op agenda |
| 16 | Lange tekst | Rich text (bold voor kopjes), geen ruwe Markdown tonen |
| 17 | FieldHints | Geen CE-codes; tone volgt support-schrijfdocument |
| 18 | `/targets` | Uit nav bij HBA aan → objectives op Merkarchitectuur (§7.6) |
| 19 | Score-UI | Meteen eindproduct (§9.4.2: score bij publiceren) |
| 20 | Backfill / ring | §9.4.1 + §9.4.2 |
| 21 | Designs Rogier | Schetsen; vormgeving uit CE design docs (`.cursor/rules/content-engine-design.mdc`) |

### 7.1 Wat gebeurt er bij tenants zonder HBA-licentie?

`clients.hba_enabled` bestaat al en betekent nu iets anders dan in de nieuwe opzet. Als de
begeleide flow het invoerpad wordt voor de merkarchitectuur, wat ziet een tenant met
`hba_enabled = false`? Voorstel: de flow verschijnt niet, `/styleguide` toont alleen BrandValues,
schrijfwijzer en tone_styles, en de drie ingrijppunten slaan we over. Dan is de HBA-laag netjes een
upgrade-moment.

### 7.2 Drie adviespanelen naast één artikel — welke blijft?

Na WP4 staan er drie dingen advies te geven bij hetzelfde artikel:

- **CE-03 stap D** — zelfcontrole tegen de research (feitelijke checks)
- **Editor Questions** — redactiecoach die verrassende vragen stelt
- **HBA-aanwijzing** — positionering scherpen

Dat is er één te veel, waarschijnlijk twee. Ze overlappen niet inhoudelijk maar wel in aandacht:
drie panelen die om reactie vragen, betekent dat de klant er nul leest. Mijn voorstel: HBA-aanwijzing
bovenaan en altijd zichtbaar, stap D eronder ingeklapt, Editor Questions naar de Feedback-pagina
waar de redacteur tijd heeft. Maar dit is een productbeslissing.

### 7.3 De eerste tien weken — opgelost in WP6

Was een openstaande vraag; is nu uitgewerkt als een eigen werkpakket. Zie **§9 — Opbouwfase**.

### 7.4 Van wie is de branche-bibliotheek?

Het wizard-document noemt de branche-bibliotheek expliciet een data-moat en eigendom van Hero Brands,
maar hij wordt opgebouwd in onze database, gevuld met AI-calls die wij betalen, verrijkt door onze
klanten. Dat moet in de licentieafspraak staan vóór we hem bouwen — niet erna.

Zelfde vraag, kleiner: de Complete Kit gaat uit van een HBA-strateeg die per klant de brand
narrative vaststelt en die de kalibratie-reviews doet (`calibration_reviews`, v1.5). De nieuwe
koers is self-service zónder strateeg. Die twee sluiten elkaar uit. Voorstel: kalibratie is een
intern Hero Brands-proces achter platform-admin, geen klantfeature, en valt buiten deze scope.

### 7.5 Het premium-bruggetje op stap 8 — concreet

Het wizard-document noemt het bruggetje alleen als idee: *"Wil je zeker weten dat dit klopt? Je
kunt een HBA-strateeg laten meekijken voordat je vastlegt."* Geen kanaal gespecificeerd. Wij hebben
geen Calendly- of leadformulier-integratie in de codebase; wel de vaste `mailto:`-conventie uit het
supportplan (`support@igisnext.com`, zie `SupportPage.tsx`, `SupportChatBox.tsx`).

**Voorstel: `mailto:`, niet Calendly.** Drie redenen. Het is de enige aanpak die zonder nieuwe
integratie te bouwen is. Een strateeg-sessie is geen zelfbedieningsafspraak zoals een demo — er
moet eerst gekeken worden of het past, en dat gaat via een gesprek, niet een geautomatiseerde
kalender. En het past bij de rest van de app: geen enkele andere upgrade- of contact-actie in
Content Engine gaat via een externe boekingstool.

**Niet alleen tekst zonder link.** Een regel zonder actie is geen bruggetje maar een mededeling —
de conversie zit in de klik. Maak het een knop, geen inline link (dit is de enige actie op het
bevestigingsscherm die naar buiten de app leidt, dus die mag opvallen).

**De mail moet zichzelf uitleggen — geen lege "ja, graag".** `mailto:` met vooringevulde subject en
body, opgebouwd uit de zojuist vastgelegde architectuur, zodat Rogier niet hoeft terug te vragen wie
dit is en waarover:

```
mailto:support@igisnext.com
  ?subject=HBA-strateeg%20gevraagd%20—%20{tenant_name}
  &body=Merkarchitectuur%20zojuist%20vastgelegd%20in%20Content%20Engine.%0D%0A
        Branche%3A%20{branche_label}%0D%0A
        Voorgesteld%20archetype%3A%20{archetype_voorstel}%0D%0A%0D%0A
        Wil je hier even naar laten kijken voordat we live gaan?
```

Knoptekst: **Laat een HBA-strateeg meekijken**. Onder de knop, klein: *"We nemen binnen één
werkdag contact op."* — zelfde belofte als in het supportplan, niet groter maken dan die kan
waarmaken.

**Wat hier niet in zit:** een gestructureerd leadformulier, tracking van hoeveel klanten hierop
klikken, of een status ("aangevraagd" / "ingepland") in de app. Dat eerste is met `mailto:` toch
niet te loggen; wil je dat weten, dan is de vervolgstap een lichte
`POST /api/hba/strategist-request` die het verzoek wegschrijft vóórdat de mailclient opent — géén
Calendly, wel een regel in een tabel die je later kunt tellen. Voorlopig is dat over-engineering
voor een knop die misschien tien keer per maand wordt ingedrukt.

### 7.6 Blijft `/targets` (Objectives) een eigen nav-item?

**Kort: nee, niet als HBA aanstaat. Wel als HBA uitstaat.**

Eerst het onderscheid dat de vraag beantwoordt. Objectives is geen weergave maar machinerie — het
voedt CE-01 (welke thema's), CE-01b, CE-03 stap A en C (`objective_label`, `objective_description`,
`writing_style`) en straks de Report-verdeling. Net als bij Tone en Angle geldt: **niet slopen.**
Maar de vraag is niet of het bestaat, de vraag is waar de *configuratie* woont, en die staat nu op
een plek die Rogier's toets niet doorstaat.

**Waarom het nav-item weg kan bij HBA aan:**

- Percentages per doelstelling instellen is strategenwerk. Een klant zonder strateeg gokt, en dat
  gegok stuurt vervolgens CE-01. Precies de situatie die Rogier "kapot voor deze klant" noemt.
- Het is eenmalige configuratie, geen plek waar je wekelijks komt. De nav is voor bestemmingen, niet
  voor instellingen.
- Met HBA aan is Merkarchitectuur al hét scherm dat zegt wie het merk is. De contentmix hoort
  daarbij.

**Waar het naartoe gaat — gesplitst naar gebruik, niet in één klap verplaatst:**

| Deel | Nu | Straks (HBA aan) |
| --- | --- | --- |
| Doelstellingen definiëren (label, beschrijving, `writing_style`) | `/targets` | sectie **Wat jullie willen bereiken** op Merkarchitectuur, uitgeklapt |
| De percentages | `/targets`, sliders | zelfde sectie, maar **voorgesteld** in plaats van gevraagd — zie hieronder |
| Objective kiezen bij één artikel | Article-metakolom (gepland) | blijft op Article, achter `Details` (§3.1) |
| Objective-verdeling terugzien | Report (gepland) | blijft op Report |

**Niet naar Article verplaatsen.** Article is waar je een objective *gebruikt*, niet waar je de mix
*bepaalt*. Die twee door elkaar halen is dezelfde fout als de percentages in de nav zetten.

**De percentages moeten voorgesteld worden, niet gevraagd.** Dit is exact het patroon uit stap 6 van
de merkarchitectuur-flow, toegepast op een veld dat vandaag een lege slider is: de tool stelt een
verdeling voor op basis van branche, archetype en contextprofiel, met een zin uitleg, en de klant
bevestigt of stuurt bij. Hergebruik `POST /api/hba/derive-architecture` of geef het een eigen
`derive`-tak. Een lege slider op 0% is voor deze klant een onbeantwoordbare vraag.

**Bij `hba_enabled = false` verandert er niets:** `/targets` blijft een eigen nav-item onder
Identity, met de sliders zoals ze nu zijn. Consistent met §7.1 — HBA is het upgrade-moment, en dit
is een van de dingen die de upgrade oplevert.

#### 7.6.1 De targetmix is géén invulveld — hij volgt uit het contextprofiel

**Beslist (aug 2026).** Er zijn geen twee concurrerende streefmixen, en de HBA-targetmix komt
nergens als percentage op het scherm. Ze meten verschillende dingen:

| | Wat het is | Wie bepaalt het | Waar zichtbaar |
| --- | --- | --- | --- |
| **Objectives** | operationele verdeling — welke thema's genereren we, met welke schrijfstijl | de klant | Merkarchitectuur (config), Article (keuze), Report (verdeling) |
| **Targetmix** | methodische toets — hoe gebalanceerd is de communicatiedruk die eruit komt | het contextprofiel, automatisch | nergens als invulveld; alleen als uitkomst in de PBS |

De klant kiest in de begeleide flow zijn **situatie** (`contextprofiel`, in de UI: *Jullie
situatie*) en de tool koppelt daar de bandbreedtes aan. Complete Kit 11.5 noemt dit expliciet laag 3
— *"gestuurde flexibiliteit: keuze uit methodisch verantwoorde profielen, geen vrije schuif."*
De drempelwaarden zelf zijn laag 2 en die raken we niet aan: wie daaraan draait, meet niet langer
de HBA.

**De koppeltabel, letterlijk uit Complete Kit 9.2.** Zet dit als constante in de backend, niet in
de database — het is methodiek, geen tenantdata. Bandbreedtes, geen harde quota.

| `contextprofiel` | brand_building | activation | proof_trust | service |
| --- | --- | --- | --- | --- |
| `nieuw_merk` | .60 – .70 | .15 – .25 | .10 – .20 | .00 – .05 |
| `herpositionering` | .60 – .70 | .15 – .25 | .10 – .20 | .00 – .05 |
| `groeiend` | .50 – .60 | .25 – .35 | .10 – .20 | .05 – .10 |
| `volwassen` | .45 – .55 | .30 – .40 | .10 – .15 | .05 – .10 |
| `tijdelijke_salesdruk` | .35 – .45 | .40 – .50 | .10 – .20 | .05 – .10 |
| `b2b_complex_sale` | .45 – .60 | .15 – .25 | .20 – .30 | .05 – .10 |

De `actual_mix` wordt berekend uit de vier drukformules in 9.2 (`brand_building_pressure` e.d.,
genormaliseerd), en `targetmix_fit` uit 10.1: 100 punten binnen de bandbreedte, daarbuiten
`max(0, 100 − afstand × 300)`, gewogen .35 / .30 / .25 / .10. Pure berekening, geen LLM.

**Voor de bouw betekent dit:**

- Géén targetmix-UI. Geen sliders, geen percentages, geen radar op Merkarchitectuur.
- `contextprofiel` is een keuzelijst met zes waarden in stap 1 of 8 van de flow, in mensentaal
  (*"Zijn jullie een nieuw merk, groeiend, gevestigd…"*) — de systeemwaarden blijven zoals
  hierboven.
- De bandbreedtes zijn een lookup-constante naast de PBS-berekening, versioneerbaar samen met
  `formula_version`.
- Wijzigt de klant later zijn contextprofiel, dan verschuift de meetlat — behandel dat als een
  narrative-versiewijziging (Complete Kit hoofdstuk 8, snapshot-versionering), zodat oude scores
  niet met terugwerkende kracht van betekenis veranderen.

**Eén afwijking van de kit, bewust:** 11.5 zegt dat het contextprofiel bij onboarding door de
strateeg wordt gezet, niet door de klant. In de self-service koers kiest de klant het zelf in de
begeleide flow. Dat is dezelfde beweging als bij archetype — een methodische keuze die de tool
begeleidt in plaats van uitbesteedt. Waard om even bij Rogier af te tikken, maar het blokkeert het
bouwen niet: de zes profielen blijven een gesloten lijst, dus de zuiverheid blijft intact.

---

## 8. Acceptatiecriteria

- Geen enkel label in de klant-UI is alleen te begrijpen met kennis van de methode. Controleer met
  een grep op: `archetype`, `adversary`, `Foe`, `verboden logica`, `bewijsvelden`, `contextprofiel`,
  `tone_of_behaviour`, `write_directive`, `CE-0`, `StyleGuide`.
- Geen onverwerkte Markdown (`###`, `**`) in tekst die de klant ziet.
- De merkarchitectuur-flow is in één sessie af te ronden en toont nooit een leeg archetype-veld.
- Nergens in de UI, routes of nav-labels staat het woord "wizard" — alleen toegestaan als interne
  verwijzing naar het brondocument (zie §3.2).
- Op `/theme` heeft elk voorgesteld thema een label plus één zin, en staat er één agendaregel boven.
- Op Article staat één HBA-aanwijzing bovenaan; Tone, Angle, Objective en Rationale bestaan nog en
  werken nog, maar staan achter `Details`.
- CE-01, CE-02 en CE-03 draaien ongewijzigd — de HBA-laag is additief, niet vervangend.
- `Pas toe` vervangt exact de passage uit `passage_voor` door `passage_na`, en zet `toegepast`.
- De twee systeemprompts uit de bouwkit staan woordelijk in de code, server-side, niet in een
  frontend bundle (zoals `hba-prompt.php` nu al doet).
- De opbouwfase (§9) toont vóór 20 posts nooit een getal op 100-schaal.
- De ring telt alleen gepubliceerde artikelen; een concept dat nooit live ging beweegt hem niet.
- Nergens in de klant-UI staat een targetmix-percentage of -slider; de mix volgt uit
  `contextprofiel` via de lookup uit §7.6.1.
- De onthullingsmelding bij 20 posts verschijnt precies één keer per tenant.

---

## 9. WP6 — Opbouwfase: het gat van tien weken

### 9.1 Het probleem, precies

De Portfolio Balance Score is pas `stable` vanaf 20 gescoorde posts. Bij twee posts per week duurt
dat tien weken. In precies die periode vraagt de klant zich af of hij ergens voor betaalt. Een leeg
blok op Overview is dan het slechtst mogelijke antwoord.

**De valkuil die we vermijden:** een tweede cijfer verzinnen dat op de PBS lijkt. Als de opbouwscore
in week 6 op 80 staat en de echte PBS komt in week 10 binnen op 54, dan voelt dat als bedrog — ook
al kloppen beide getallen. Je hebt de klant een cijfer leren lezen dat iets anders mat.

**Waarom het toch kan:** de per-post scoring werkt vanaf post 1. Alleen de *aggregatie* is
onbetrouwbaar onder de 20 (Complete Kit hoofdstuk 9, drempelregels). En ingrijppunt 1 en 2 leveren
vanaf dag één echte, opgeslagen data: themaoordelen en toegepaste aanwijzingen. Er is dus genoeg om
te tonen — het beantwoordt alleen een andere vraag.

| | Vraag | Vanaf wanneer |
| --- | --- | --- |
| **Opbouw** | *Werkt dit ding en gebeurt er iets?* | dag 1 |
| **PBS** | *Hoe scherp staat mijn merk?* | 20 posts |

### 9.2 Drie fasen op hetzelfde blok

Eén component op Overview, `BrandProgressCard`, dat drie gedaanten kent. De overgangen zijn
automatisch en gebaseerd op het aantal gescoorde posts in het venster.

**Fase A — Opbouw (0–9 posts). Geen cijfer.**

```
┌────────────────────────────────────────────────────────┐
│  Je merk wordt opgebouwd                               │
│                                                        │
│      ●●●●●●●○○○○○○○○○○○○○      7 van 20 artikelen     │
│                                                        │
│  Je merkarchitectuur staat.                            │
│  5 van de 7 gekozen thema's versterkten je positie.    │
│  Je paste 4 aanwijzingen toe.                          │
│                                                        │
│  Vanaf 20 artikelen zie je hier hoe scherp je merk     │
│  staat — en rekenen we deze weken met terug-           │
│  werkende kracht mee.                                  │
└────────────────────────────────────────────────────────┘
```

Geen percentage, geen 0–100, geen pijl. Een voortgangsring en drie tellers die feitelijk zijn en
vanzelf oplopen. De laatste regel is belangrijk: hij belooft iets concreets én neemt de angst weg
dat deze weken verloren tijd zijn.

**Fase B — Indicatief (10–19 posts). Cijfer met label.**

Volgt de Complete Kit hoofdstuk 10.2: toon de PBS met label `Indicatief`, **afgerond op vijftallen**.
De voortgangsring blijft eronder staan tot 20. De drie tellers verhuizen naar `meer details`.

**Fase C — Stabiel (20+ posts).** Schermontwerp 3 zoals Rogier het tekende: cijfer, pijl, lijn.

### 9.3 De drie tellers

Alle drie komen uit tabellen die WP3 en WP4 al aanleggen. Geen nieuwe LLM-calls, geen nieuwe
methodiek — dit is een view.

| Teller | Bron | Waarom deze |
| --- | --- | --- |
| **Merkarchitectuur staat** | `brand_narratives` compleet + flow afgerond | Het eerste dat af is, op dag 1. Meteen één ding groen |
| **X van Y gekozen thema's versterkten je positie** | `hba_theme_verdicts` waar het thema ook daadwerkelijk gekozen is, label = `versterkt` | Meet de keuze van de klant, niet die van de machine — dat is het eerlijkst |
| **Z aanwijzingen toegepast** | `hba_article_hints` waar `toegepast = true` | Het enige getal dat de samenwerking meet. Stijgt alleen als de klant iets doet |

Let op de tweede: tel alleen thema's die de klant **koos**, niet alle voorgestelde. Anders meet je
de generator in plaats van de samenwerking, en dan kan de teller stijgen zonder dat er iets gebeurt.

### 9.4 De backfill — het moment dat het klikt

Bij het passeren van 20 posts wordt de PBS over de **hele periode** doorgerekend, niet alleen
vooruit. `hba_scores` bestaat per post vanaf post 1, dus de maandwaarden over de opbouwweken zijn
berekenbaar. De lijn op Overview start dus gevuld.

Toon dat één keer expliciet:

> Je merk wordt nu gemeten. We hebben je eerste tien weken met terugwerkende kracht doorgerekend —
> je ziet meteen het verloop.

Dat is het moment waarop de opbouwfase zijn belofte inlost. Zonder backfill begint de lijn bij nul
en lijkt alles ervoor voor niets geweest.

#### 9.4.1 Automatisch — er is geen backfill-job

**"Backfill" is een woord over de presentatie, niet over de data.** Er valt technisch niets in te
halen: de aggregatie is een pure berekening over `hba_scores`, en die rijen bestaan al vanaf post 1
(Complete Kit hoofdstuk 9 — geen LLM-calls, alleen SQL). De maandwaarden over de opbouwweken zijn
op elk moment berekenbaar; we tónen ze alleen niet zolang de betrouwbaarheid `preliminary` is.

Dus: **geen cron, geen admin-actie, geen migratie.** Twee dingen volstaan:

1. **Het aggregatievenster staat op de volledige historie**, niet op "sinds de PBS stabiel werd".
   Default is 90 dagen (Complete Kit 14.2); voor de lijn op Overview gebruiken we het hele
   tenantverleden. Bereken on-demand — bij twee posts per week haal je de 500-postgrens uit de kit
   pas na vijf jaar.
2. **De fase-overgang gebeurt vanzelf**, omdat `phase` uit `GET /api/hba/progress/:client_id` een
   functie is van de telling. Het 20e artikel schuift de kaart van B naar C zonder dat iemand iets
   start.

De **eenmalige melding** heeft wél state nodig, anders zie je hem elke keer. Eén kolom:
`clients.hba_pbs_revealed_at TIMESTAMP NULL`. Leeg én fase C bereikt → toon de melding en zet de
timestamp. Daarna nooit meer. Geen aparte tabel, geen dismiss-knop — de melding verdwijnt bij de
volgende page load.

De dagelijkse cron die de Complete Kit al voorschrijft voor de PBS-drop alert (hoofdstuk 11.1)
schrijft `portfolio_scores` sowieso elke dag weg. Daar hoeft niets aan.

#### 9.4.2 "Gescoorde post" = gepubliceerd, niet elke `hba_scores`-rij

**Tel alleen artikelen die daadwerkelijk gepubliceerd zijn.** Een concept dat nooit de deur uit ging
heeft het merk nergens gepositioneerd; meetellen zou de teller optikken zonder dat er iets in de
wereld is gebeurd, en de PBS vervuilen met content die niemand zag. Content Weight leunt bovendien
op `impressions` en `reach` — die bestaan pas na publicatie (Complete Kit 15.3).

Concreet:

- **Telt mee:** artikelen met status `published`, inclusief Fastlane, inclusief LinkedIn-only, en
  inclusief later gearchiveerde posts — die zijn ooit echt verschenen.
- **Telt niet mee:** `draft`, `pending`, `feedback`, `approved`-maar-nog-niet-gepubliceerd, en
  verwijderde slots.

**Gevolg voor wanneer je scoort — dit is een gedragswijziging.** `ce_hba_score_article()` accepteert
vandaag elk `article_id`, ongeacht status. Verplaats de score-call naar het **publicatiemoment**
(na `ce_apply_ui_status(..., 'published')` in `publish-repository.php`), niet naar draft-save. Drie
voordelen: de telling klopt per definitie, je scoort geen concept dat nog vijf keer herschreven
wordt, en het scheelt LLM-kosten.

Dat botst niet met ingrijppunt 2: de **aanwijzing** tijdens het schrijven draait wél op concepten en
schrijft naar `hba_article_hints`. Twee verschillende dingen, twee verschillende tabellen, twee
verschillende momenten:

| | Wanneer | Tabel | Telt mee in de ring |
| --- | --- | --- | --- |
| Aanwijzing (ingrijppunt 2) | bij concept genereren/bewerken | `hba_article_hints` | nee — maar `toegepast` voedt teller 3 |
| Score (ingrijppunt 3) | bij publiceren | `hba_scores` | ja |

**Republish** levert geen tweede score op: score één keer, bij de eerste publicatie. Bij een
inhoudelijke herschrijving ná publicatie mag opnieuw gescoord worden met `force`, maar dan als
update van dezelfde rij — anders telt één artikel dubbel in de ring.

### 9.5 Randgevallen

- **Klant publiceert weken niets.** Toon geen verwijt maar een feit met een uitweg: *"Er zijn deze
  maand geen nieuwe artikelen bijgekomen — de meting staat stil."* Met een link naar Agenda.
- **HBA uit** (`hba_enabled = false`): geen blok, geen ring, niets. Zie §7.1.
- **Merkarchitectuur gewijzigd tijdens de opbouwfase.** De Complete Kit versioneert `hba_scores`
  tegen de actieve narrative (hoofdstuk 8). Bij een ingrijpende wijziging telt de ring door, maar
  zet een voetnoot: *"Je merkarchitectuur is aangepast in week 4 — de meting daarvoor gebruikte de
  oude versie."* Niet resetten; dat straft de klant voor het aanscherpen van zijn merk.
- **Fastlane-posts** tellen gewoon mee. Ze zijn even echt.

### 9.6 Wat we bewust niet doen

- **Geen tweede 0–100-cijfer vóór de PBS.** Eén schaal in de app, één betekenis.
- **Geen streaks, badges of confetti.** De klant is een ondernemer, geen taalapp-gebruiker. De
  toon uit het supportplan geldt hier onverkort: rustig en volwassen.
- **Geen voorspelling** van wat de PBS gaat worden. Dat is een belofte die je niet kunt waarmaken
  en die de eerste echte score bij voorbaat tot teleurstelling maakt.

### 9.7 Waar dit in de bouwvolgorde past

Na WP4, vóór WP5. De tellers hebben `hba_theme_verdicts` en `hba_article_hints` nodig, en die
komen uit WP3 en WP4. Fase A alleen is al genoeg om live te gaan — fase B en C volgen met WP5.

Eén klein endpoint: `GET /api/hba/progress/:client_id` → `{ scored_posts, phase, architecture_complete,
themes_chosen, themes_reinforcing, hints_applied }`.
