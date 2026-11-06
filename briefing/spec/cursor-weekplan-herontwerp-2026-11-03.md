# Weekplan herontwerp Brandflocks — briefing voor Cursor

**Datum:** dinsdag 3 november 2026
**Doel deze week:** het volledige herontwerp bouwen, testen en vrijdag uploaden naar het Brandflocks-domein. Daarna (niet deze week): de definitieve Fable-run op de def-server.
**Branch:** `ronde-8-preview` (huidige werkbranch). Maak voor deze week een eigen branch `herontwerp-2026-11` vanaf hier, zodat staging en de oude stand terug te vinden blijven.

---

## 1. Big picture

### Wat we bouwen
Een complete herinrichting van de app volgens Rogiers klikmodel. **De functionaliteit verandert bijna niet; de vorm, navigatie en flow wel.** Waar het klikmodel en de bestaande app verschillen, is het klikmodel leidend.

### Bindende bronnen (in deze volgorde van gezag)
| Bron | Pad | Rol |
|---|---|---|
| Klikmodel (HTML) | `briefing/spec/klikmodel/klikmodel-brandflocks-2026-11-02.html` | **Bindende UI-specificatie.** Elke pagina, elk veld, elke tekst, elke interactie. Letterlijk overnemen — niet "geïnspireerd door". Open het in de browser én lees de HTML-bron voor exacte teksten. |
| Voorstel herontwerp | `briefing/spec/hba-input/voorstel-herontwerp-2026-10-31.docx` | Rationale per keuze, de veld-voor-veld branchekoppeling, het testplan (13 punten). Lees bij twijfel over *waarom*. |
| Hero Brand Core Logic v2.3 | `briefing/spec/hba-input/dc2f9130-Hero_Brand_Core_Logic_v2_3.docx` | Kennisbron: 12 archetypen, Story Triangle, categorie-archetype-risico (§4), eisen aan een Regel (§9). Systeemprompt-context voor wizard stap 6 en de architectuurcheck. |
| Check- en afleider-addendum | `briefing/spec/hba-input/HBA_bouwaddendum_check_en_afleider.docx` + `HBA_Lees_dit_eerst_addendum.docx` (tekstversie: `briefing/spec/hba-kit-extract/`) | Kant-en-klare systeemprompts voor de architectuurcheck en de gefaseerde branche-afleider, plus acceptatietest ("loyaliteitsbranche"). |
| Branchebibliotheek | `briefing/spec/hba-input/Branchebibliotheek-voor-Erik-2nov.zip` | 20 gecureerde branches (JSON) + `branchelijst.csv` (alle 102) + `begrippen-en-schema.md` (bindend schema). |
| Campagne-aanpassingen 30 okt | `briefing/spec/hba-input/campagnes-aanpassingen-2026-10-30.md` + ons antwoord `briefing/spec/cursor-campagnes-aanpassingen-2026-10-30-antwoord.md` | Campagneslot = thema, Touch/Tell/Sell, stap-7-gedrag. |
| Drempels thema-onderbouwing | `briefing/spec/hba-input/drempels-thema-onderbouwing.md` | Aantal thema's = bruikbare bronfragmenten / 2 (max 6), waarschuwing bij 0–1, label "licht onderbouwd". |
| Plan van aanpak | `briefing/spec/cursor-plan-van-aanpak-herontwerp-2026-11-02.md` | Technische checks al gedaan (zie §2 hieronder). |

### Wat al vaststaat uit de code (niet opnieuw uitzoeken)
- **Merkbank en feitenbank zijn al gescheiden:** `merk_vragen` en `merk_feiten` (`web/migrations/add_campagne_ronde6_bank.sql`).
- **Post ↔ campagne-koppeling bestaat al:** `content_slots.campaign_id` met index.
- **Doelen heeft actieve afhankelijkheden:** `fastlane-repository.php` (Snel artikel), `tone-styles.php` (`ce_objective_write_directive_lines()` — doel stuurt de schrijfinstructie) **en bronnenmappen** (`source-folders-repository.php` koppelt een map aan een `objective_id`; `SourcesPage.tsx` toont het label). Het klikmodel kent geen doel-koppeling per map: bij de ombouw bepalen wat daarmee gebeurt (zie opleveringsnotitie, vraag aan Rogier). Doelen-pagina mag pas weg nadat deze koppeling is omgezet naar het Doel-veld van "Wie je bent".
- **Er is nog geen geautomatiseerde testset** (geen phpunit, geen vitest/playwright-config). Die bouwen we deze week mee — zie §4.

### Harde regels die de hele week gelden
1. **Pilotbevriezing v1.13 blijft staan.** Prompt v1.13, resolver `ce_hba_resolve_hoofdoordeel()` en model `claude-sonnet-4-6` voor artikelscoring worden niet aangeraakt. Na elke dag: replay van de 95 v1.13-scores moet nog 19/19 identieke hoofdoordelen geven (wordt een automatische test, zie §4).
2. **Beschermd eigendom van Rogier — niet verzinnen, niet aanvullen:**
   - scoringsdrempels van de architectuurcheck;
   - de contentscoring tegen de merkarchitectuur (Rapport/merkcontrole);
   - de inhoud van de gecureerde branchebibliotheek.
   Bouw het mechanisme (velden, UI, plek van de score), maar zet waarden als **expliciet gemarkeerd koppelpunt**: één config-bestand `web/api/hba-open-koppelpunten.php` met illustratieve defaults uit het klikmodel, elk met commentaar `// OPEN KOPPELPUNT — waarde volgt van Rogier, niet zelf invullen`. Geen "redelijk lijkende" waarden elders in de code.
3. **Verwijderen wat je vervangt.** Elke stap die een pagina of mechanisme vervangt, haalt de oude code in dezelfde commit weg (frontend-route, component, nav-item). Tabellen/kolommen níet droppen deze week — alleen code. **API-endpoints blijven staan** (`/themes`, `/research`, `/research-trigger`, `/themes-research-trigger`, `/sources*`, `/objectives` tot de ombouw klaar is): de n8n-flows CE-02 en CE-Sources Sync en de nieuwe Artikel-/Campagne-flow gebruiken ze. Alleen de pagina's veranderen, niet de API. Data-opruiming is een aparte ronde na livegang.
4. **"Je", nooit "jullie" of "u".** Geen Engelse resten, geen vaktaal of ID's in UI-tekst.
5. **Bewerken-patroon overal gelijk:** vlak groeit open, andere vlakken dimmen, Opslaan/Annuleren per vlak, Opslaan zet waarden zichtbaar terug, geen dubbele labels. Eén gedeelde component, niet per pagina opnieuw bouwen.
6. **Kleine commits, één onderwerp per commit,** met `Closes #N` waar er een GitHub-issue voor is.

---

## 2. Stappenplan per dag

Volgorde is zo gekozen dat latere stappen op eerdere steunen. Niet gefaseerd opleveren aan Rogier, wel intern in deze volgorde bouwen.

### Dinsdag — fundament + testset-basis

**Stap 0 — Testset-scaffolding (eerst, ±1 uur)** → zie §4.1. Zonder vangnet niet beginnen aan de rest.

**Stap 1 — Datamodel-fundament**
- 1a. Doelen-koppeling omzetten: `fastlane-repository.php` en `tone-styles.php` lezen het doel voortaan uit de merkarchitectuur ("Wie je bent" → Doel), niet uit `objectives`. Fallback: als er nog een oud objective-record is en het Doel-veld leeg is, het oude record gebruiken (geen dataverlies bij bestaande tenants).
- 1b. Campagneslot = thema (30-okt §1): een bevestigde campagne levert thema's op, geen artikelen; per thema status "nog te schrijven / artikel klaar"; datum, tijd en kanaal per thema wijzigbaar.
- 1c. Hoofdregel + subregels: merkarchitectuur krijgt `hoofdregel` (bestaande regel migreert hierheen) en een lijst `subregels`.
- 1d. Automatische Bronnen-map per Artikel en per Campagne (hergebruik "Map toevoegen"); inhoud: gekozen merkonderdelen, SEO/GEO-vragen, eigen links/uploads, per item verwijderbaar.
- 1e. Logboek automatisch afgeleide branches (naam zoals getypt, aantal keer, laatste datum) + CSV-export in de vorm van `branchelijst.csv`.

**Controlemoment D1 (einde dag):** testset groen (incl. v1.13-replay 19/19), migraties draaien schoon op een kopie van de staging-database, Snel artikel schrijft nog met de juiste toon voor een tenant met én zonder oud Doelen-record.

### Woensdag — merkarchitectuur, wizard, architectuurcheck, branche

**Stap 2 — Merkarchitectuur-pagina** volgens klikmodel: vlakken Wie je bent / Hoe je werkt / Taal / Voorbeelden voor de merkmeting / Strategiedocument; ondertitel "Gebaseerd op Hero Brand Core Logic…".

> **Eerst lezen, dan bouwen (stap 3–5):** de backend hiervoor bestaat al — `/hba/architecture-check`, `/hba/derive-branche`, `/hba/derive-architecture`, `/hba/branches`, `/hba/wizard-document`, tabel `branche_library`, `hba-branche-seed.php`, `hba-architectuur-check-repository.php`, frontend `api/hbaV2.ts`, `HbaArchitectureCheckPanel.tsx`, `IdentityCalibrationStep.tsx`. Stap 3–5 zijn dus aansluiten, uitbreiden en omzetten naar het klikmodel (hoofdregel/subregels, 102-brancheslijst, gecureerd/afgeleid-label, logboek) — niet opnieuw bouwen. Controleer ook welk model deze aanroepen nu gebruiken (`hba-model-config.php` / `hba-v2-prompts.php`) en wijzig dat niet stilzwijgend.

**Stap 3 — Twee routes naar "Wie je bent"**: "Vul structuur in" en "Beantwoord de vragen" (8-stappenwizard). Stap 6 van de wizard (archetype + tegenkracht voorstellen) gebruikt Core Logic v2.3 als systeemprompt-context en de input/output-spec uit `briefing/spec/hba-kit-extract/HBA_brandlaag_Merkarchitectuur_Wizard.txt`. Antwoord stap 3 wordt de eerste formulering van het mechanisme.

**Stap 4 — Architectuurcheck "Hoe sterk staat je architectuur?"**: draait bij elke opslag in Wie je bent (incl. subregel toevoegen/verwijderen). Systeemprompt uit het check-addendum. Nooit blokkeren; per signaal welk veld en waarom; "Laat een HBA-strateeg meekijken" altijd bereikbaar. Drempels via het koppelpunten-bestand (regel 2).

**Stap 5 — Branche-koppeling**: zoekveld met suggesties tegen alle 102 uit `branchelijst.csv`. Gecureerd (20) → velden volgens de tabel in het voorstel (markt_archetype.archetype, hygiëne-items voor de check, differentiatie_assen bewaren). Niet gecureerd (82) → gefaseerde branche-afleider live aanroepen, resultaat cachen per tenant+branche, loggen (1e). Label "(gecureerd)" / "(automatisch afgeleid)". Markt-archetype altijd zichtbaar naast Branche, ook in wizard stap 1 en 8.
- Importeer de bibliotheek via een script (`scripts/import-branchebibliotheek.php`) dat alléén de velden uit de koppeltabel inleest. Een nieuwe ZIP van Rogier moet later met hetzelfde script in te lezen zijn zonder codewijziging.

**Controlemoment D2:** acceptatietest "loyaliteitsbranche" uit het Lees-dit-eerst-addendum slaagt; testplan-punten 1–5 handmatig doorlopen op staging; Erik kijkt 15 minuten mee op de wizard (dit is het inhoudelijk zwaarste stuk — als dit niet klopt, schuift de rest).

### Donderdag — navigatie, Artikel, Campagne

**Stap 6 — Navigatie en structuur**: Overzicht (Rapport, Te doen) / Identiteit (Merkarchitectuur, Merkelementen) / Kennis (Bronnen, SEO/GEO, Beeldbank) / Publicatie (Artikel, Campagne, Agenda, Alle artikelen) / Instellingen (Mijn account, Koppelingen, Planning, Help) — navigatie exact volgens het klikmodel (Rapport staat daar onder Overzicht); Thema's en Onderzoek geen eigen nav-item meer; Snel artikel weg; Doelen weg (pas ná 1a); Archief → Alle artikelen; Planning naar Instellingen; Instellingen in vlakken; Koppelingen-status als tekst, geen kleur. Broodkruimel op elke stap.

**Stap 7 — Artikel-flow**: startscherm met drie scenario's (Eén / Meerdere / Ik heb het artikel al — upload én plakken); themavoorstellen volgens `drempels-thema-onderbouwing.md` (aantal = bruikbare bronfragmenten / 2, max 6; waarschuwing bij 0–1 met "Bronnen toevoegen" / "Toch doorgaan"; label "licht onderbouwd" zichtbaar bovenaan Onderzoek); Onderzoek per thema als kaarten; Artikel-detail met zijbalk-typen (AI-advies / Score / Aandachtspunten / Instellingen) en het CTA-blok (Ja/Nee + drie voorstellen + eigen tekst).

**Stap 8 — Campagne-flow (7 stappen)**: Touch/Tell/Sell (uitklap, direct door, statische merk-fit-hint), titel + kleur (vaste set van 4), vragen en feiten met schakelaars + eigen bronnen, posts per kanaal (veldbug 8→84 fixen, fout direct op de stap), periode, ritme-check tegen Instellingen → Planning (advies-mechanisme), "Klopt het plan?" (drie blokken, check vooraf akkoord/aanpassen met invulveld, advies volgen/niet volgen/zelf aanpassen, postvoorstel met Merkonderdeel + Anker, "Schrijf dit thema" → Onderzoek). Copy-modus Sell/Tell/Touch gaat mee naar de artikelmodus per thema. Touch-fases: Herkenning 40% / Anker-Inzicht 35% / Call-to-connect 25%.

**Controlemoment D3:** testplan-punten 6–8 groen; één Sell- en één Touch-campagne voor hetzelfde merk geven een ander balansbeeld; v1.13-replay nog 19/19.

### Vrijdag — Agenda, Rapport, Help, eindtest, upload

**Stap 9 — Agenda en Rapport**: Kalender + Lijst, "Groeperen op: Datum / Campagne", campagnekleuren + legenda; Rapport: label "SEO/GEO" i.p.v. "Vindbaarheid" — alleen het label; de interne scope-waarde `findability` blijft ongewijzigd, anders breken bestaande rapporten onder "Eerdere rapporten", CTA-keuze stuurt de spreidingsgrafiek, klik op stip → Artikel-detail.

**Stap 10 — Help**: 10 onderwerpen × 10 vragen (tekst letterlijk uit het klikmodel), uitklappen zonder verspringen, "Stel hier jouw vraag" los van de onboarding-assistent. FAQ-inhoud is nog niet door Rogier gecontroleerd — markeer dat in de opleveringsnotitie.

**Stap 11 — Eindtest + oplevering**: volledige testset groen + het complete testplan (13 punten) handmatig; opleveringsnotitie `docs/oplevering-herontwerp-2026-11.md` met: wat gebouwd is, welke koppelpunten open staan (regel 2), bekende afwijkingen van het klikmodel (moet leeg zijn of met reden).

**Stap 12 — Upload naar Brandfloks-domein**: pas als stap 11 groen is. Deploymentchecklist: aparte `.env` voor het nieuwe domein (geen staging-sleutels hergebruiken), migraties in volgorde draaien, de branchebibliotheek-import draaien, rooktest (inloggen, één artikel, één campagne) op het nieuwe domein. Secrets die onderweg in code of config gevonden worden: direct verwijderen en roteren (dit mag altijd, ook vóór Fable).

**Controlemoment D4 (Erik + daarna Rogier):** Erik doorloopt het testplan zelf op het nieuwe domein; pas daarna gaat de link naar Rogier.

### Als het niet past in de week
Schuif in deze volgorde naar volgende week, zonder de upload te blokkeren: (1) Help-FAQ-inhoud (structuur wel, tekst later), (2) Agenda groeperen op campagne, (3) Rapport CTA-grafiek. **Niet** schuiven: fundament, merkarchitectuur, architectuurcheck, campagneslot = thema — daar hangt de rest aan.

---

## 3. Welk model bij welke stap

Twee soorten modelkeuze — niet door elkaar halen.

### A. Model waarmee Cursor de code schrijft
| Werk | Model | Waarom |
|---|---|---|
| Stap 1 (datamodel, Doelen-ombouw, migraties), stap 4–5 (architectuurcheck, branche-afleider integreren), stap 8 (campagne stap 7: advies/signaal → datamodel) | **Zwaarste redeneermodel dat Cursor aanbiedt** (Opus-klasse) | Grootste kans op subtiele fouten met doorwerking; meerdere bestanden en bestaande afhankelijkheden tegelijk. |
| Stap 2, 3, 6, 7, 9 (pagina's letterlijk naar klikmodel bouwen) | **Sterk standaard codemodel** (Sonnet-klasse) | Veel, goed gespecificeerd UI-werk; snelheid telt, spec is exact. |
| Stap 10 (FAQ-tekst overnemen), opschonen van tekstresten, i18n-zoekwerk | **Snel/goedkoop model** (Haiku-klasse of Composer) | Mechanisch overnemen; geen ontwerpbeslissingen. |
| Testset schrijven (§4) | Sonnet-klasse; v1.13-replay-test en plan-check-test met Opus-klasse laten reviewen | Tests moeten de echte intentie vangen, niet de huidige bug. |

**Let op (les uit de 5×19-meting):** een codeermodel mag nooit zélf HBA-scores "uitrekenen" als vervanging van een echte API-call. Scores komen altijd uit de app/het script dat de Anthropic-API aanroept met het vastgelegde runtime-model.

### B. Runtime-modellen die de app aanroept
Alle runtime-modellen staan centraal in `web/api/hba-model-config.php`, niet verspreid in de code.
| Aanroep | Model | Status |
|---|---|---|
| Artikelscoring (v1.13 hoofdoordeel) | `claude-sonnet-4-6` | **Bevroren.** Niet wijzigen. |
| `ce_hba_plan_check` (campagne) | `claude-sonnet-4-6` | Vastleggen als eigen constante. |
| Architectuurcheck | `claude-sonnet-4-6` | Nieuw, eigen constante. |
| Wizard stap 6 (archetype + tegenkracht) | `claude-sonnet-4-6` | Nieuw, eigen constante. |
| Branche-afleider (3 fases) | `claude-sonnet-4-6` | Nieuw, eigen constante; resultaat cachen. |
| Themavoorstellen, CTA-voorstellen, invalshoeken "Meerdere" | huidig artikel-generatiemodel | Ongewijzigd. |

Eén runtime-model voor alle HBA-aanroepen houdt kosten en gedrag voorspelbaar en vergelijkbaar. Een wissel per aanroep pas na een eigen meting, niet deze week.

---

## 4. Geautomatiseerde testset

### 4.1 Opzet (dinsdag, stap 0)
- **Backend:** PHPUnit via Composer (`composer require --dev phpunit/phpunit`), map `tests/php/`, aparte testdatabase (`ce_test`), nooit tegen staging of productie.
- **Frontend unit:** Vitest in `frontend/` voor pure functies (bv. fase-verdeling, drempelberekening themavoorstellen).
- **End-to-end:** Playwright in `tests/e2e/`, tegen staging-URL via env-var, met een vaste testtenant ("Brandpunt" + "Duinroos").
- **Eén commando:** `scripts/test-all.sh` draait alles; exit-code ≠ 0 bij één falende test.
- **AI-aanroepen in tests:** standaard gemockt met vastgelegde antwoorden (fixtures), zodat tests snel, gratis en deterministisch zijn. Alleen de v1.13-replay en een handvol "live"-tests draaien tegen de echte API, achter een vlag `RUN_LIVE_AI=1`.

### 4.2 Wat er deze week in moet (prioriteit)
1. **v1.13-replay (live-vlag niet nodig):** de 95 opgeslagen v1.13-ruwe-outputs opnieuw door `ce_hba_resolve_hoofdoordeel()` → 19/19 identieke hoofdoordelen. Dit vervangt de handmatige replay en bewaakt de bevriezing bij elke wijziging.
2. **Campagne-plangenerator:** vaste invoer (sjabloon Lancering, LinkedIn 8 + Instagram 12, 4 weken) → 3/4/4/2/4/3 posts per fase, max 1 post per kanaal per dag, ankers ±1 op 3. Plus Touch-verdeling 40/35/25 en de 100-slots-cap.
3. **Drempels themavoorstellen:** tabel uit `drempels-thema-onderbouwing.md` als data-gedreven test (0,1,2,3,4,7,8,11,12,20 fragmenten → verwacht aantal) + label bij < 2 fragmenten.
4. **Doelen-ombouw (regressie):** schrijfinstructie uit `tone-styles.php` gelijk voor tenant met oud objective-record en voor tenant met alleen Doel-veld.
5. **`ce_hba_plan_check` (gemockt):** normaal plan → geen "Regel"-signaal; plan met korting bij merk dat nooit afprijst → wél "Regel" (addendum §8). Eén live-variant achter de vlag.
6. **Branche-opzoeking:** gecureerde branche → bron "gecureerd" en juiste markt-archetype uit JSON; onbekende branche → afleider aangeroepen (mock) + logboek +1.
7. **E2E per testplan-punt:** zodra een onderdeel af is, het bijbehorende punt uit Rogiers testplan (1–13) als Playwright-test. Minimaal deze week: punt 1 (Wie je bent opslaan zonder dubbele labels), 7 (volledige campagneflow), 12 (Bewerken-patroon op álle vlakken — loop over alle "Bewerken"-knoppen in de app).

### 4.3 Hoe de testset gebruikt wordt
- **Voor elke commit:** `scripts/test-all.sh` (zonder live-vlag). Rood = niet committen.
- **Bij elk controlemoment (D1–D4):** ook de live-tests (`RUN_LIVE_AI=1`).
- **Na livegang:** dezelfde testset is het vangnet voor de opruimronde (dode routes/kolommen) — niet daarvoor beginnen.

---

## 5. Controlemomenten samengevat

| Moment | Wie | Wat moet groen zijn | Bij rood |
|---|---|---|---|
| Elke commit | Cursor | `test-all.sh` | Niet doorbouwen, eerst fixen |
| D1 di | Cursor → Erik | Fundament, migraties, Doelen-regressie, v1.13 19/19 | Woensdag niet starten |
| D2 wo | Erik kijkt mee | Wizard + architectuurcheck + loyaliteitsbranche-test, testplan 1–5 | Wizard eerst goed, rest schuift |
| D3 do | Cursor → Erik | Artikel- en campagneflow, testplan 6–8, v1.13 19/19 | Agenda/Rapport naar volgende week |
| D4 vr | Erik, daarna Rogier | Volledig testplan op nieuw domein, opleveringsnotitie | Niet uploaden; staging blijft live |

**Bij elke afwijking van het klikmodel:** niet zelf kiezen. Noteer in `docs/oplevering-herontwerp-2026-11.md` onder "Vragen aan Rogier" en bouw de klikmodel-versie of laat het open.

---

## 6. Wat Cursor níet doet deze week
- Geen Fable-run (pas na livegang op het Brandfloks-domein).
- Geen wijziging aan v1.13-prompt, resolver of scoringsmodel.
- Geen eigen waarden voor drempels, contentscoring of branche-inhoud.
- Geen DB-tabellen of -kolommen droppen.
- Geen sjablonen C–F voor campagnes.

---
🤖 Generated with [Claude Code](https://claude.com/claude-code)
