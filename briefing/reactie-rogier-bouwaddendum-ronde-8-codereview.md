# Reactie op bouwaddendum ronde 8 — codeonderzoek bij paragraaf 9

28 oktober 2026 (concept, door Erik te versturen). Antwoord op de zes vragen in paragraaf 9 van `bouwaddendum-ronde-8-strategieen-campagnes-geo-2026-10-27.md`, op basis van wat er echt in de code staat — niet een inschatting.

## 1. Ritmrol en fasecyclus

Geen aparte structuur — het is hetzelfde ding onder twee namen. `frontend/src/utils/rhythmRoles.ts` en `web/api/content-rhythm-repository.php` kennen drie vaste rollen (autoriteit/inzicht/product-of-dienst, klantlabels Positionering/Kennis delen/Bewijs), één per weekslot. In `themeVerdict.ts` staat zelfs een regel die het woord "fasecyclus" in LLM-output automatisch vervangt door "weekritme" voor de klant.

**Gevolg:** er is geen fasecyclus om de sjablonen op te bouwen. De sjablonen (met hun eigen fase-aandelen per campagne) hebben een eigen structuur nodig naast het weekritme, niet bovenop iets dat al bestaat.

## 2. Kanaalvarianten per artikel

Bestaan niet. `ArticlePage.tsx` heeft één `platform`-veld per post (radio: LinkedIn/Instagram/X/Facebook/Blog) — geen automatische variantgeneratie per kanaal.

**Gevolg:** "aantal posts per kanaal" is geen uitbreiding van iets bestaands. Planning moet N aparte postslots per kanaal aanmaken, niet één post met kanaalvarianten.

## 3. Thema-advies hergebruiken voor de check vooraf

Dit is dichterbij dan het leek. `ce_hba_v2_run_theme_verdicts()` in `web/api/hba-v2-repository.php` (regel 904) neemt een hele set thema's tegelijk, bouwt context uit de merkarchitectuur (archetype, doel, foe, regels, mechanisme, categorietaal), doet één LLM-call voor de hele batch, en levert per thema een oordeel plus één gedeeld `wat_ontbreekt`-advies voor de hele set.

**Gevolg:** dit is structureel al bijna de "check vooraf op het plan" uit paragraaf 5 — een batch-oordeel met één gezamenlijk advies. `ce_hba_plan_check` kan hier een variant van zijn, geen nieuwe prompt-architectuur vanaf nul.

## 4. Eigen thema invoeren

Bestaat al. `ThemePage.tsx` heeft een `customDraft` (titel, motivatie, bron-URL) die via `createTheme()` een gewoon thema-record met eigen ID wordt, en daarmee automatisch hetzelfde verdict-traject uit punt 3 doorloopt.

## 5. Bronnen: productpagina's en documenten

Grotendeels aanwezig. `sources-document-upload.php` + `source-document-repository.php` slaan een document op en tellen `documentChunkCount` — vergelijkbare plumbing als nodig voor sjabloon C (een stuk opknippen). De categorieën URL/RSS en Blogs bestaan en kunnen als productpagina-bron dienen.

**Gevolg:** een reële basis voor sjabloon A en C, geen nieuwe bouw vanaf nul. Wat er nog boven op moet: de content-extractie zelf — stellingen uit een document halen, kenmerken/bewijs uit een productpagina halen.

## 6. Vindbaarheid en de vragenbank

Bestaat, maar is veel dunner dan voorgesteld. `SearchQuestionsEditor.tsx` + `llmQuestions.ts`: een vast plafond van 5 GEO-vragen en 5 SEO-vragen per klant, vrije tekst, handmatig ingevoerd. Geen koppeling aan een pagina, geen gebruikshistorie, geen automatische generatie uit de website.

**Gevolg:** de voorgestelde vragenbank (10 vragen, met doelpagina/pagina_antwoord/geo_uitkomst/rotatie) is geen uitbreiding van dit scherm maar een forse verzwaring ervan.

## Open vraag: twee verwijzingen die niet bestaan

Het addendum verwijst twee keer naar stukken die niet in de codebase of de briefings staan:
- "stap A/B van het bouwpakket van de driftmeter";
- "prompt C in de GEO-specificatie".

Bestaan deze als apart document dat nog niet hierheen is gestuurd, of gaat het om werk dat nog gepland staat? Dat maakt verschil voor de omvang van fase 1: sjabloon A (productpagina lezen) en de vragenbank (vragen uit de branche halen) leunen er inhoudelijk op. Als die twee stukken zelf nog gebouwd moeten worden, is fase 1 groter dan de fasering suggereert.

## Samenvatting

Drie onderdelen (thema-advies, eigen thema, documentbronnen) zijn al zo goed als aanwezig en schelen bouwtijd. Twee (ritmrol/fasecyclus, kanaalvarianten) bestaan niet zoals het addendum veronderstelt en vragen gewoon nieuwe bouw. De vragenbank is een forse nieuwe toevoeging, geen uitbreiding. En de omvang van fase 1 hangt mede af van het antwoord op de open vraag hierboven.
