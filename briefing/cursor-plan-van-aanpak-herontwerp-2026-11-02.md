# Plan van aanpak — herontwerp Brandflocks (vanaf voorstel-herontwerp-2026-10-31)

**Datum:** 2026-11-02
**Bronnen:** `briefing/hba-input/voorstel-herontwerp-2026-10-31.docx` (Rogier, bindende rationale), het klikmodel (bindende UI-specificatie, zie Rogiers mail), `Hero_Brand_Core_Logic_v2_3.docx`, `Branchebibliotheek-voor-Erik-2nov.zip`, en de al aanwezige `HBA_bouwaddendum_check_en_afleider.docx` + `HBA_Lees_dit_eerst_addendum.docx` (augustus-traject, nog gewoon in de repo — zie onder).

## 0. Eerst dit teruggeven aan Rogier

**De twee augustus-documenten die hij zoekt, heeft Erik nog gewoon liggen** — hoeven niet opnieuw gestuurd te worden:
- `briefing/hba-input/HBA_bouwaddendum_check_en_afleider.docx`
- `briefing/hba-input/HBA_Lees_dit_eerst_addendum.docx`
- (bonus: er is ook al een eerdere uitgepakte tekstversie in `briefing/hba-kit-extract/`, inclusief een systeemprompt-bestand `04-systeemprompt.txt` en een datamodel `08-datamodel.txt` uit dat traject — kan helpen om te zien wat er destijds al technisch is aangesloten.)

**De vier technische checkpunten uit het voorstel zijn al (deels) te beantwoorden door in de code te kijken** — hieronder per punt, zodat dit niet nog een ronde heen-en-weer met Rogier kost:

| Checkpunt | Antwoord |
|---|---|
| Zijn merkbank en feitenbank technisch gescheiden? | **Ja.** `merk_vragen` en `merk_feiten` zijn al losse tabellen (`web/migrations/add_campagne_ronde6_bank.sql`), met een eigen schema per tabel. Geen migratiewerk nodig, het ontwerp klopt al met de techniek. |
| Heeft een post al een vaste koppeling met zijn campagne? | **Ja.** `content_slots.campaign_id` bestaat al als kolom met index (`web/migrations/add_campaigns_table.sql`). Agenda groeperen op campagne is dus een query/weergave-vraagstuk, geen datamodel-vraagstuk. |
| Leunt er al iets op de Doelen-pagina? | **Ja, en dit is het enige echte risico van de vier.** `objectives`/`objectives-repository.php` wordt gebruikt door `fastlane-repository.php` (Snel artikel) én door `tone-styles.php` (`ce_objective_write_directive_lines()` — het doel stuurt mee in de schrijfinstructie/toon). Doelen simpelweg uit de navigatie halen is dus niet risicovrij: de onderliggende data en de koppeling naar de schrijfinstructie moeten blijven bestaan of expliciet verhuizen naar het "Doel"-veld van "Wie je bent". **Actie:** vóór het verwijderen van de Doelen-pagina als nav-item, de velden `objective`/`objectiveId` die nu naar fastlane en tone-styles gaan, laten wijzen naar het Doel-veld van de merkarchitectuur in plaats van een los Doelen-record. Dit is een concrete bouwstap, geen open vraag meer. |
| Is de Snel-artikel-techniek herbruikbaar voor het nieuwe Artikel-startscherm? | Nog niet uitgezocht in deze sessie — wel bevestigd dat `fastlane-repository.php` bestaat en al door objectives/tone-styles heen loopt (zie boven), dus het aanknopingspunt is er. Verdere check (hoeveel van thema→bronnen→onderzoek→artikel letterlijk herbruikt kan worden) is onderdeel van de bouwstap zelf, geen blokkerende vraag vooraf. |

## 1. Wat voor soort traject dit is

Rogier is expliciet: **niet gefaseerd, in één keer bouwen** vanuit het complete klikmodel, met een normale review-/testronde achteraf. Dat is een bewuste koerswijziging t.o.v. eerdere rondes (die wél stapsgewijs gingen) — reden: het klikmodel was toen nog niet compleet en consistent, nu wel.

Dit botst met hoe we tot nu toe werkten (kleine, los te reviewen stappen per briefing). Voor dit traject is dat niet de juiste aanpak — maar "in één keer" betekent niet "zonder volgorde intern". Hieronder een interne volgorde die het risico beheersbaar houdt zonder het gefaseerd-opleveren-aan-Rogier opnieuw in te voeren.

## 2. Interne bouwvolgorde (voor Cursor, één doorlopend traject)

1. **Fundament eerst: datamodel- en structuurwijzigingen die alles onder zich hebben.**
   - Doelen-koppeling ombouwen (zie checkpunt boven) — vóór de Merkarchitectuur-pagina wordt herbouwd, anders werk je twee keer aan dezelfde velden.
   - Campagneslot = thema, niet artikel (uit de 30-oktober-lijst, al eerder als hoogste prioriteit aangemerkt) — dit ligt onder bijna elke andere wijziging in dit voorstel (het "Schrijf dit thema"-pad).
   - Hoofdregel/Subregels-splitsing op het datamodel van de merkarchitectuur.
   - Automatische Bronnen-map per Artikel/Campagne (nieuw mechanisme, maar simpel: hergebruikt bestaande "Map toevoegen").
2. **Merkarchitectuur-pagina + 8-stappenwizard + architectuurcheck.** Dit is het zwaartepunt qua inhoud (Hero Brand Core Logic v2.3, archetypen, markt-archetype, de twee augustus-systeemprompts). Bouw dit vóór Artikel/Campagne, omdat die twee er inhoudelijk van afhangen (copy-modus, merkcontrole).
3. **Branche-koppeling:** zoekveld tegen de volledige lijst van 102, met de gecureerd/automatisch-afgeleid-logica en het logboek van automatisch afgeleide branches. Kan parallel aan punt 2, want het is een los, goed afgebakend stuk (zie het voorstel se veld-voor-veld-tabel).
4. **Navigatie- en structuurwijzigingen:** Artikel/Campagne naast elkaar, Kennis/Publicatie-herindeling, Doelen weg, Archief → Alle artikelen, Instellingen uitgewerkt (incl. Planning erin).
5. **Artikel-flow** (startscherm met 3 scenario's, Onderzoek per thema, Artikel-detail met het vlakken-patroon, CTA-blok).
6. **Campagne-flow** (7 stappen inclusief Ritme-stap en campagnekleuren) — bouwt op 1-3.
7. **Agenda** (groeperen op campagne, campagnekleuren, legenda) en **Rapport** (CTA-koppeling naar spreidingsgrafiek, klik-door naar Artikel-detail).
8. **Help/FAQ** (10×10) — kan los en laatst, want hangt van niets anders af; inhoud moet nog door Rogier gecontroleerd worden (expliciet "nog te bevestigen" in het voorstel), dus bouw de structuur, laat de tekst-controle als los punt voor Rogier liggen.
9. **Doorlopende punten, overal tegelijk toepassen, niet als losse stap:** het vlakken-patroon, "je" i.p.v. "jullie", de Bewerken/Opslaan-bug (dubbele labels, waarden niet teruggezet) — dit zijn kwaliteitseisen die bij élke pagina uit 2-7 gelden, geen aparte sprint.

## 3. Wat expliciet open blijft (niet door Cursor te bouwen/invullen)

Drie dingen zijn met opzet placeholder en moeten dat blijven, met een duidelijk, niet-blokkerend signaal in plaats van een verzonnen waarde:
- De scoringsdrempels van de architectuurcheck.
- De contentscoring die content tegen de merkarchitectuur afmeet (Rapport/merkcontrole).
- Uitbreiding van de gecureerde branchebibliotheek zelf (alleen Rogiers eigen workflow, niet de AI).

**Instructie voor Cursor:** waar het klikmodel een waarde laat zien op deze drie punten, is dat een voorbeeld, geen eindwaarde — behandel het als open koppelpunt (bijv. een duidelijk gelabeld config-punt of threshold-variabele die nu een illustratieve default heeft maar expliciet als "nog te bevestigen door Rogier" gemarkeerd is in code/commentaar), niet als iets om zelf af te ronden.

## 4. Testplan-koppeling

Het testplan uit het voorstel (13 punten, zie document) kan gebruikt worden zoals het er staat — geen aanpassing nodig. Volg bij oplevering die 13 punten, met als enige toevoeging: markeer bij oplevering expliciet welke van de drie placeholder-punten (zie §3) nog openstaan, zodat dat niet per ongeluk als "klaar" wordt gelezen.

## 5. Risico's om bewust te accepteren (gegeven "niet gefaseerd")

- Eén grote, lange bouwronde betekent dat problemen pas bij de eindreview zichtbaar worden, niet tussentijds. Mitigatie: de interne volgorde in §2 is zo gekozen dat latere stappen op eerdere steunen — bij een probleem in stap 1-3 wordt dat snel duidelijk zodra stap 5-6 ermee gaat werken, niet pas helemaal aan het einde.
- De Doelen-afbouw (checkpunt boven) is het enige punt met een reëel regressierisico (bestaande Snel-artikel- en toon-functionaliteit) — dit expliciet als eerste bouwstap zetten, niet laatste, zodat een eventueel probleem vroeg zichtbaar is.

---
🤖 Generated with [Claude Code](https://claude.com/claude-code)
