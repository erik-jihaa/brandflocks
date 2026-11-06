# Cursor-briefing — HBA-laag beter laten oordelen op de drie testmerken

**Eigenaar:** Erik. **Datum:** 9 oktober 2026.
**Testmateriaal:** `briefing/hba-input/HBA_testmerken/` — Brandpunt Koffie, Stelwerk, Duinroos Thuiszorg. Elk merk heeft vier testposts met een verwachte uitkomst. Goedgekeurd door Rogier, niet voor publicatie.
**Doel:** de app moet bij deze drie merken het juiste oordeel geven, en dat oordeel moet ook terugkomen op de plekken waar de klant feedback krijgt: de zelfcontrole, de review en de aanwijzing bij het schrijven. Niet alleen in de score achteraf.

## Wat er al is — niet opnieuw bouwen

Een eerdere analyse (in een losse Claude-chat zonder toegang tot de code) stelde voor zes velden toe te voegen. **Die bestaan al.** `brand_narratives` heeft spanning, mechanisme, geloofwaardigheidstest, verboden_logica, bewijsvelden en contextprofiel. De scoreprompt (`hba-prompt.php:53-109`) gebruikt ze allemaal, en het outputschema kent de flags `category_fallback`, `constraint_breach` en `opportunistische_trendcontent` (~221-225).

Ook al gebouwd, maar nog niet gecommit:
- `scripts/provision-hba-testmerken-staging.sh`
- `scripts/import-hba-testposts-staging.sh`
- `web/api/hba-testmerken-repository.php`
- `web/api/admin-provision-hba-testmerk.php`
- `web/api/admin-import-hba-testposts.php`

**Stap 0:** commit deze vijf bestanden plus `briefing/hba-input/HBA_testmerken/` als eerste, in een eigen commit.

## Waar het oordeel nu tekortschiet

Uit de code (9 oktober):

1. **Er is geen vergelijking tussen verwacht en werkelijk.** De verwachting per testpost ("flag constraint_breach: breekt de commissieregel") wordt alleen als vrije tekst in `archive_note` gezet (`hba-testmerken-repository.php` ~455). Niemand ziet in één oogopslag of de app het goed deed.
2. **De regel wordt alleen achteraf getoetst.** De score achteraf kijkt naar de geloofwaardigheidstest, maar de rest van de keten niet:
   - **Zelfcontrole (CE-03, `self_review`):** het schema kent alleen `weakest_claim`, `unsupported_assumption` en `missed_counterpoint` (`generate-ce03-workflow.py` ~66-78). Een regelbreuk wordt dus nooit als aandachtspunt gemeld.
   - **AI-review (CE-04):** de workflow (`../JSON/CE-04 (Review & Publish).json`) bevat de merkregels helemaal niet.
3. **De wekelijkse geplande run schrijft zonder merkregels.** De CE-01-cascade start CE-03 met alleen `{ slot_id }` (`generate-ce01-workflow.py` ~735). Het GRENZEN-blok (met de regels en de verboden logica) gaat alleen mee als het schrijven vanuit de app wordt gestart. Automatisch geschreven artikelen kennen de regel dus niet.
4. **Het merkcijfer lijkt verkeerd geschaald.** `ce_hba_v2_post_positioning_score()` (`hba-v2-repository.php` ~1997) deelt door 5, terwijl de scores van 0 tot 10 lopen (`hba-prompt.php` ~199-215). Een gemiddelde van 5 geeft al 100. **Niet aanpassen zonder bevestiging van Rogier** (zie onderaan); het raakt wel hoe de testmerken in het overzicht uitkomen.

## Wat te bouwen

### S1. Verwachte uitkomst gestructureerd en een resultatentabel — Composer 2.5

- Zet bij de import per testpost een gestructureerde verwachting naast de vrije tekst:
  - `verwacht_flags` (lijst, bijvoorbeeld `["constraint_breach"]`);
  - `verwacht_niveau` (`hoog` / `laag` / `neutraal`);
  - de vrije tekst `verwacht`.

  Leid dat af uit het veld `verwacht` in de JSON. Lukt dat voor een post niet eenduidig, zet hem dan op de lijst voor Erik in plaats van te raden.
- Maak een admin-weergave (Platform, alleen admin) of een script dat per testmerk een tabel oplevert:

  | Merk | Post | Verwacht | Werkelijk: score, distinctiviteit, contentrol, flags | Goed / fout |

  Een post is **goed** als:
  - alle verwachte flags aanwezig zijn;
  - post 2 of 3 niet hoog scoort;
  - post 1 geen risicoflags heeft.

  Schrijf de uitkomst ook naar `docs/hba-testmerken-resultaat.md`, met datum, model-id en promptversie.
- Draai de tabel nu, **vóór** S2–S4, als nulmeting. Leg die vast.

### S2. Regelbreuk in de zelfcontrole en in de review — Opus 5 (promptwerk)

- **Zelfcontrole (CE-03 `self_review`):** voeg het veld `regelbreuk` toe aan het schema. Het bevat de regel (uit geloofwaardigheidstest of verboden_logica) die de tekst breekt, met het citaat uit de tekst, of `null`. Geef de zelfcontrole daarvoor de geloofwaardigheidstest en de verboden logica mee. In de UI (`ArticleSelfReviewSection.tsx`) verschijnt het als bovenste aandachtspunt, in klanttaal: "Dit stuk gaat in tegen je regel dat …". Geen veldnamen in de tekst.
- **AI-review (CE-04):** geef de review dezelfde twee velden mee en laat hem een regelbreuk expliciet benoemen. Pas de generator aan (bestaat er geen generator voor CE-04, meld dat dan en beschrijf de wijziging in de workflow-JSON).
- **Schrijfaanwijzing tijdens het schrijven (HBA-hint):** controleer of `regels` uit `ce_hba_v2_architecture_context()` in de hint-prompt terechtkomt. Zo niet, voeg het toe.
- **Eén formulering van de regelcheck.** Gebruik in score, zelfcontrole en review dezelfde omschrijving van wat een regelbreuk is. Zet die op één plek (een PHP-constante of `prompt_fragments.py`) en hergebruik hem, zodat de drie niet uit elkaar lopen.

### S3. Merkregels ook bij de geplande run — Composer 2.5

Laat CE-03 het GRENZEN-blok, de schrijfopdracht en het calque-blok zelf ophalen als ze ontbreken in de aanroep. Doe dat via een bestaand of nieuw API-endpoint per `slot_id`, met dezelfde opbouw als de app nu meestuurt. Dan maakt het niet meer uit wie CE-03 start.

**Klaar als:** een artikel dat door de geplande CE-01-run wordt geschreven, aantoonbaar het GRENZEN-blok in zijn prompt had. Leg één run vast in de n8n-log.

### S4. Controleren, niet bouwen

Meld de uitkomst van elk punt aan Erik:
- **Duinroos:** het archetype van het merk (Verzorger) is gelijk aan dat van de branche. Rogier verwacht hier een zwaardere bewijslast. Zoek of de scoring of de architectuurcheck dat nu doet, bijvoorbeeld via `branche_library` (markt-archetype). Beschrijf wat je vindt; bouw niets zonder akkoord van Rogier.
- **Vertaling van de testmerken:** de JSON gebruikt `website_blog`, `newsletter` en een volume per maand. Wij hebben vijf platforms en posts per week. Controleer hoe `ce_hba_testpost_platform_db()` en de provisioning dat vertalen, en of dat de score kan beïnvloeden.
- **Constraint_breach zichtbaar voor de klant:** volgens briefing 13 §7.3 hoort een geschonden regel als bevinding in het artikelpaneel te staan. Controleer of dat zo is.

## Testprotocol na S1–S3

Draai elke stap in deze volgorde en leg vast in `docs/hba-testmerken-resultaat.md`:

1. **De 12 testposts opnieuw scoren** en de tabel uit S1 vergelijken met de nulmeting. Alleen post 2 en 3 zijn echt diagnostisch; noteer elke afwijking met score en reden.
2. **Twee keer scoren.** Draai de 12 posts een tweede keer om te zien hoe stabiel het oordeel is. Een flag die de ene keer wel en de andere keer niet komt, is een bevinding voor Rogier.
3. **CE-03 verleiden.** Maak per merk één thema dat de regel uitlokt:
   - Brandpunt: "Black Friday-actie";
   - Stelwerk: "Nieuw partnerschap met een ERP-leverancier";
   - Duinroos: "Wachtlijst in één maand weggewerkt".

   Laat CE-03 schrijven. Leg vast:
   - of de tekst de regel breekt;
   - of de zelfcontrole het meldt;
   - welke score en flags de scorer geeft.
4. **CE-04:** dien post 3 van elk merk in als artikel en kijk of de AI-review de regelbreuk benoemt.

**Model in de pijplijn:** verander tijdens deze ronde het schrijfmodel en het HBA-model **niet**. Noteer bij elke run de model-ids en `CE_HBA_PROMPT_VERSION`. Let op: als hetzelfde model schrijft en beoordeelt, kijkt het naar zijn eigen werk. Noteer dat bij stap 3 als kanttekening; het is geen reden om nu van model te wisselen.

## Vragen voor Rogier — Erik legt ze voor, niet bouwen

1. **Merkcijfer:** de positioneringsscore deelt door 5 terwijl de scores 0–10 lopen. Is dat de bedoeling, of moet het door 10?
2. **Drempels:** wanneer moet een flag vallen? Bijvoorbeeld: `category_fallback` bij een distinctiviteit onder welke waarde?
3. **Duinroos:** hoe moet de zwaardere bewijslast eruitzien als het archetype samenvalt met de branche?
4. **Grensgevallen:** heeft hij extra testposts die minder voor de hand liggen? Twaalf posts zijn een controle op werking, geen validatie.

## Afronden

- `php -l`, `py_compile` en `npm run build`.
- Eén commit per S-punt.
- Deploy van S2 en S3 naar staging pas na Eriks "ja". **Niet naar productie** in deze ronde.
- Een kort verslag aan Erik: de nulmetingtabel, de tabel na S1–S3, en de bevindingen uit S4.
