# Cursor-briefing — betere artikelen, betere feedback en bronnencoaching

**Eigenaar:** Erik. **Datum:** 1 oktober 2026.  
**Review (zelfde dag):** zie § *Status na 1 okt* onderaan — Aandachtspunten-UI aangescherpt; stap 1 gestart met 2 staging-tenants.  
**Vervangt:** het model-gedeelte van "To-do 1 oktober 2026 — tekstkwaliteit + inhoudelijkere HBA" in `docs/BUILD-NOTES.md`. De doelen blijven; de volgorde verandert.  
**Model:** per stap aangegeven. Kort: Composer 2.5 voor uitvoerend werk (stap 1, 2 en 5), Opus 5 voor ontwerpwerk aan prompts en datastructuur (stap 3 en 4).

## Waarom deze volgorde

Generieke artikelen komen vooral uit dunne input, niet uit een te zwak model. Een voorbeeld van staging:

- **Onderzoek:** het item "De meester-gezelrelatie als tegenvoorbeeld" staat als **"Eigen aanzet — geen bron, wel een richting"** en kreeg toch het oordeel **versterkt**.
- **Artikel:** opent met precies die bewering als vaststaand feit: "In middeleeuwse gilden verloor een meester zijn status als hij zijn kennis niet doorgaf."
- **Aandachtspunten:** de zelfcontrole vangt het daarna wel ("Zwakste claim … reliability=low"), maar pas achteraf, en met een technische term in de klanttekst.

De ketting thema → onderzoek → schrijven laat dus een richting zonder bron doorlopen tot een feitelijke claim. Dat lossen we eerst op, en pas daarna kijken we naar zwaardere modellen.

**Regel voor deze hele briefing:** verander steeds één ding tegelijk en meet tegen de nulmeting (stap 1). Geen prompts en modellen tegelijk omzetten.

---

## Stap 1 — Testset en nulmeting (eerst, vóór elke wijziging)

**Bouwmodel: Composer 2.5.** Bestaande pijplijn draaien, resultaten vastleggen en een document maken. Er valt niets te ontwerpen.  
**Document:** `docs/testset-artikelen.md` (concept slots staan klaar).

- Kies 6 vaste testslots op staging. **Huidige staging (1 okt):** alleen **iGiSNext** + **Axiom Strategy Group** (2e tenant vandaag). Doornvogel / “weinig bronnen” / “eigen documenten” staan er nog niet — start met deze twee; vul later aan zonder de 6 ids te wisselen na freeze.
- Bevries per slot het thema en de research-items. Dat kan door ze te kopiëren naar een testtenant of door de ids vast te leggen in `docs/testset-artikelen.md`.
- Draai de huidige CE-03 op alle 6. Bewaar per slot de tekst, de aandachtspunten (`self_review`), de HBA-score en het gebruikte model.
- Maak in hetzelfde document een beoordelingsblad. Erik en Rogier scoren elke versie **blind** (zonder te weten welke versie het is) op 1–5 per vraag:
  - Is er één duidelijke bewering?
  - Is die onderbouwd met iets concreets?
  - Is het herkenbaar dit merk?
  - Is het natuurlijk Nederlands?
  - Zou ik dit posten?
- Elke volgende stap wordt opnieuw gedraaid op dezelfde 6 slots en naast de nulmeting gelegd.

**Nu (1 okt avond):** testset = **2× Axiom** + **4× iGiSNext** — zie `docs/testset-artikelen.md`. **Morgen:** freeze + nulmeting, daarna tekstverbetering volgens deze briefing (stap 2+).

---

## Stap 2 — Bronnencoaching in Thema, Onderzoek en Artikel

**Bouwmodel: Composer 2.5.** De onderdelen bestaan al (`ce_create_source`, de probe, de inspiratie- en claim-evidence-calls); dit is koppelen en UI. Let op één lastig punt: van een artikel-URL naar de juiste site of feed. Laat Composer daar eerst de bestaande probe-functie lezen en niet zelf een URL-parser schrijven.

Bronnen zijn de grondstof. Het systeem moet de klant actief helpen zijn bronnen uit te breiden, op de plek waar hij merkt dat er iets ontbreekt.

### 2a. Onderzoek en Thema: "Nieuwe bron"

**Een research-item met een URL die niet uit de bronnen van de tenant komt.**
- Toon een label **"Nieuwe bron"** met de korte uitleg "Deze site staat nog niet in je bronnen."
- Zet er de actie **"Voeg toe aan bronnen"** bij.
- Toevoegen gaat via het bestaande `ce_create_source()` (`sources-repository.php:496`).
- Een artikel-URL is geen goede bron. Voeg de site of feed toe, niet het losse artikel. Gebruik de bestaande probe (`ce_sources_inspiration_probe_suggestion`) om een feed of site-URL en de categorie te bepalen.
- Laat vóór toevoegen zien wat er precies wordt toegevoegd ("ftm.nl — Blogs"). Toevoegen gebeurt na één klik, zonder de pagina te verlaten.
- Het label verdwijnt als de site al in de bronnen staat. Vergelijk met de genormaliseerde URL-set uit `ce_sources_inspiration_existing_url_set`.

**Een "Eigen aanzet"-item (geen bron)** (`ResearchReviewRow.tsx:85`).
- Zet onder "Eigen aanzet — geen bron, wel een richting." de actie **"Zoek een bron hiervoor"**.
- Dat is hetzelfde gedrag als bij de aandachtspunten van het artikel: gerichte bewijs-zoektocht via `/articles/{id}/claim-evidence`, of het slot-equivalent, met "Accepteer voor dit artikel".
- Zo wordt een richting zonder bron al in het onderzoek onderbouwd, niet pas achteraf in het artikel.

**Thema (optioneel, alleen als 2a werkt):** onder een thema-voorstel een inklapbaar blok "Bronnen die dit thema sterker maken". Gebruik de bestaande inspiratie-call (`ce_sources_inspiration_build_claim_prompt`), met het thema als claim-context, en dezelfde knop "Voeg toe aan bronnen".

### 2b. Artikel: na "Zoek een bron hiervoor" → "Voeg bron toe"

**Deels gedaan (1 okt, naast deze briefing):** Aandachtspunten-UI werkt voor onderbouwen *in dit artikel*: radiokeuze in één roze kader (`PinkChoice`), **Verwerk in artikel** (`POST …/substantiate-claim`), **Formuleer voorzichtiger**, blauwe diff alleen op de *laatste* correctie. Zie § Status.

**Nog te bouwen (kern van 2b):**

Nu toont `ArticleSelfReviewSection.tsx` bronsuggesties met vooral een link naar `/sources`. Vervang/vul aan:

- Per suggestie een knop **"Voeg bron toe"**, rechtstreeks via `ce_create_source()`. De categorie en de URL komen uit de suggestie zelf; die is al gevalideerd en geprobed.
- Na toevoegen: de knop wordt "Toegevoegd" (niet meer klikbaar), met de regel "Wordt meegenomen bij je volgende onderzoek." Gebruik `text-ce-beige-text`.
- Staat de URL al in de bronnen, toon dan direct "Staat al in je bronnen".
- De link naar `/sources` mag blijven als tweede optie ("Bekijk al je bronnen").

**Klaar als:** vanuit Onderzoek en vanuit het artikel kan een bron met één klik worden toegevoegd. Die bron verschijnt op `/sources`, en een tweede klik op dezelfde site geeft "Staat al in je bronnen".

---

## Stap 3 — Thema als standpunt, onderzoek als bewijs

**Bouwmodel: Opus 5**, eerst in Plan-modus. Dit raakt drie lagen tegelijk: de CE-01/CE-02-generatoren (n8n), de opslag van thema's en research-items, en de UI. Het plan moet laten zien welke nieuwe velden waar komen, hoe oude items zonder die velden blijven werken, en hoe CE-02 `bewijs_nodig` gebruikt. Erik keurt het plan goed; daarna mag Composer 2.5 het uitvoerende deel doen.

**Draaimodel (in de pijplijn):** ongewijzigd laten. Eerst meten wat de nieuwe structuur doet.

**Thema (CE-01).** Elk thema-voorstel levert naast de titel drie dingen:
- `stelling`: wat het merk beweert, in één zin;
- `tegen`: welk gangbaar idee het tegenspreekt;
- `bewijs_nodig`: wat voor onderbouwing het artikel nodig heeft.

Toon de stelling in de UI onder de titel. CE-02 krijgt `bewijs_nodig` mee als zoekopdracht.

**Onderzoek (CE-02).** *Besloten (Erik, 1 okt):* een eigen aanzet zonder bron mag een HBA-oordeel krijgen, ook "versterkt". Het oordeel zegt iets over de richting voor het merk, niet over de onderbouwing. Daarom regelt stap 4 dat zo'n aanzet in de tekst als standpunt wordt geschreven, niet als feit.

 Elk research-item levert:
- de `bewering` die het ondersteunt;
- het concrete `bewijs` (feit, cijfer, citaat of voorbeeld);
- de `bron`;
- de bestaande `reliability`.

Een item zonder bron heeft `reliability = low` en het type `richting`.

## Stap 4 — Schrijven vanuit een plan (CE-03)

**Bouwmodel: Opus 5.** Dit is promptontwerp. De koppeling plan → research-id → tekst en de regel voor zwakke bronnen moeten in de prompt precies goed staan, anders schrijft het model er omheen. Laat Opus na het bouwen zelf twee testslots draaien en de uitkomst kritisch bekijken vóór de volledige testset.

**Draaimodel:** ongewijzigd (Sonnet 4.6), zodat de winst van het plan los te meten is van de winst van een ander model.

- **Stap A (rationale) levert een schrijfplan:** één kernbewering, en twee of drie argumenten, elk met het id van het research-item waar het op leunt.
- **Stap B schrijft alleen vanuit dat plan.** Regel in de prompt: geen feitelijke beweringen die niet in het plan of in de research staan.
- **Regel voor zwakke input:** een argument dat leunt op een item met `reliability = low` of type `richting`, schrijf je als standpunt of observatie van het merk ("wij zien…", "wat ons opvalt…"), nooit als vaststaand feit. Dit voorkomt precies het gilden-voorbeeld.
- **Aandachtspunten (`self_review`) in klanttaal.** Geen veldnamen of technische waarden zoals "reliability=low". Schrijf "de bron hiervoor is zwak" of "hier is geen bron voor". Zoek de self-review-prompt in de CE-03-generator en pas hem daar aan.
- **Het GRENZEN-blok en het NL-calque-blok blijven** zoals ze zijn.

Draai na stap 3 en na stap 4 elk apart de testset.

## Stap 5 — Modellen, pas nadat stap 2–4 gemeten zijn

**Bouwmodel: Composer 2.5.** Model-ids en een env-vlag omzetten. De GPT-route is een nieuwe helper naast de bestaande Anthropic-call; geef Composer daarvoor `ce_hba_v2_call_json` als voorbeeld van hoe een call hoort te lopen (timeouts, JSON, foutafhandeling).

**Draaimodellen om te proberen:**
- **Schrijven (CE-03 Stap A en B):** Opus 5.5 tegen de huidige Sonnet 4.6.
- **Eindredactie:** eerst het huidige Anthropic-model met de strakke opdracht; daarna GPT als vergelijking.
- **Aandachtspunten (`self_review`):** laat een **ander** model controleren dan het model dat schreef. Een model vindt de zwakke plekken in andermans tekst beter dan in zijn eigen tekst. Schrijft Opus, laat dan Sonnet de aandachtspunten maken, of omgekeerd.

- **Schrijven op Opus 5.5:** zet in CE-03 alleen het model van Stap A en B om. Laat de rest van de keten gelijk en draai de testset. Leg kosten en duur per run vast.
- **Eindredactie (Re-edit):**
  - Maak eerst de opdracht strak met het huidige model: "alleen redigeren, geen nieuwe beweringen, tone of voice behouden".
  - Na Re-edit gaat de tekst **opnieuw langs het GRENZEN-blok** (briefing 13, §2.2).
  - GPT als redacteur is een **aparte proef**, en pas nadat Erik heeft bevestigd dat klantcontent naar OpenAI mag (verwerkersovereenkomst, toezeggingen aan klanten).
  - Bouw de GPT-route achter een env-vlag, zodat beide redacteuren op de testset vergeleken kunnen worden.

## Niet in deze briefing — eerst langs Rogier

Deze punten zijn methodiek en daarmee Rogiers terrein:
- wijzigingen in `hba-v2-prompts.php`;
- een ander model voor het HBA-oordeel bij thema, onderzoek en artikel-hints (`HBA_MODEL_*`);

Een ander HBA-model verschuift ook de oordelen in zijn ijkset. Erik legt deze punten bij Rogier neer; Cursor raakt ze in deze ronde niet aan.

## Acceptatie

- De nulmeting op 6 slots staat vast in `docs/testset-artikelen.md`, met beoordelingsblad.
- Een bron toevoegen kan met één klik vanuit Onderzoek ("Nieuwe bron", en "Zoek een bron hiervoor" bij een eigen aanzet) en vanuit het artikel ("Voeg bron toe"). Een site die al in de bronnen staat, wordt herkend.
- Thema-voorstellen tonen een stelling. Research-items hebben bewering, bewijs en bron.
- In de testset staat geen enkele bewering uit een `richting`- of `low`-item meer als feit in het artikel.
- Er staan geen technische termen meer in de aandachtspunten.
- Per stap ligt er een vergelijking met de nulmeting. Erik beslist per stap of die blijft.
- Één commit per stap. Niet deployen naar productie.

---

## Status na 1 okt 2026 — review t.o.v. deze briefing

### Wat vandaag niét was: stap 1–5 van deze briefing

Er is **geen** nulmeting gedraaid, **geen** één-klik “Voeg bron toe”, **geen** CE-01/02/03-structuurwijziging, **geen** modelwissel. Het werk van vandaag zat op het **bestaande Aandachtspunten-pad** (`cursor-aandachtspunten-onderbouwing.md`) — redacteur-tools ná CE-03, niet de ketting die de briefing wil verbeteren.

### Wat wél klaar is (meenemen, niet opnieuw bouwen)

| Onderdeel | Status | Relatie tot briefing |
|-----------|--------|----------------------|
| Drie acties Aandachtspunten | ✅ | Voorwaarde voor 2b; softenen + claim-evidence bestonden al |
| Radiokeuze + **Verwerk in artikel** (`substantiate-claim`) | ✅ 1 okt | **Niet** in originele 2b; nuttig voor “onderbouw dít stuk” |
| Blauwe diff alleen laatste correctie | ✅ 1 okt | UX; meet niet mee in nulmeting |
| `PinkChoice` — 1 roze kader, radio onderaan, geen kader-in-kader | ✅ 1 okt + design system | Herbruikbaar voor 2a/2b-keuzelijsten |
| Staging 2e tenant **Axiom Strategy Group** | ✅ | Stap 1: naast iGiSNext; briefing-doel “4 tenant-types” nog niet |

### Wat de briefing nog eist (volgorde ongewijzigd)

1. **Stap 1** — slots vast (2 Axiom + 4 iGiS) → **morgen** freeze → CE-03/baseline → blind score (`docs/testset-artikelen.md`).
2. **Stap 2a** — Onderzoek/Thema: Nieuwe bron + Voeg toe; Eigen aanzet → Zoek bron.
3. **Stap 2b rest** — per suggestie **Voeg bron toe** via `ce_create_source` (niet alleen Verwerk in artikel).
4. **Stap 3–4** — thema/onderzoek-velden + schrijfplan (Opus, plan eerst).
5. **Stap 5** — modellen, pas na meting.

### Gevolg voor Cursor

- Stap 2b **niet** opnieuw ontwerpen: alleen de ontbrekende **bronnenpool**-actie toevoegen; substantiate/softenen laten staan.
- UI-patronen voor keuzes: `PinkChoice` gebruiken.
- Nulmeting **vóór** 2a/2b-rest, zoals de briefing voorschrijft — ook al is de redacteur-UI vandaag al beter.
