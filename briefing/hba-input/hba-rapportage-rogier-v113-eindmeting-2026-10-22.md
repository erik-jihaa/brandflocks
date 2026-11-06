# Rapportage aan Rogier — v1.13 eindmeting en pilotcriteria

**Datum:** 22 oktober 2026  
**Branch:** `hba-ronde-6-pilotklaar` (commit `701006f`, gepusht)  
**Reactie op:** `hba-reactie-ronde-7b-2026-10-21.md` §3 (5×19, replay, drie criteria)

---

Rogier,

Hier is de complete 5×19 op **v1.13** (staging, **claude-sonnet-4-6**), plus spoor **1b** (offline replay). A6 ronde 2 bleef zoals in je reactie van 21 oktober: akkoord; het bijgewerkte pakket bevat dezelfde A6-bewijzen plus de v1.13-levering hieronder.

## 1. Pilotcriteria — alle drie gehaald

| # | Criterium | Drempel | Uitkomst |
| --- | --- | --- | --- |
| 1 | Posts **5/5 goed** (hoofdoordeel t.o.v. verwachting) | ≥ 16 / 19 | **19 / 19** |
| 2 | Vier categorietaal-posts: **nooit** `tegenkracht` in een run | 0× | **0×** (15 runs per post = 60 scores) |
| 3 | Spreiding **merkbijdrage** per post | ≤ 1 | **0 posts** met spreiding > 1 |

**Onze conclusie:** de gerichte reparatie `foe_vervaging` (v1.13) doet wat je in het stopbericht van 20 oktober bedoelde: branchetaal en vergelijkbare zinnen landen niet meer als Positiebreuk. Op basis van deze meting is de **technische drempel voor pilotstart** gehaald. Het formele pilotbesluit blijft bij jou en Erik.

Technische kwaliteit van de run:

- 95 scores, **0** `hoofdoordeel_gecorrigeerd`, **100%** `hoofdoordeel_volgt_uit_flags`
- `merkbewering` en `kern_categorietaal` staan **in elke run** in de export (geen backfill)

## 2. Vier categorietaal-posts (15 runs elk)

| Merk | Post | Verwacht | Hoofdoordeel in 5×5 | `tegenkracht` |
| --- | --- | --- | --- | --- |
| Brandpunt Koffie | Categorietaal | categorietaal | 5/5 `categorietaal` | **nee** |
| Duinroos Thuiszorg | Categorietaal | categorietaal | 5/5 `categorietaal` | **nee** |
| Duinroos Thuiszorg | Archetype zonder bewijs | categorietaal | 5/5 `categorietaal` | **nee** |
| Stelwerk | Branchetaal uit de verboden logica | categorietaal | 5/5 `categorietaal` | **nee** |

Typische flags op die posts: `category_fallback` + `proof_gap` (geen `foe_vervaging` op de kop/openingszin die tot trede 2 leidt).

## 3. Spoor 1b — replay met en zonder `&& !kern_categorietaal`

Script: `scripts/hba-v1.13-tegenkracht-diagnose.php`  
Rapport: `docs/hba-v1.13-tegenkracht-diagnose.md`

Op de **zelfde 95 scores** als de live run:

| Variant | Stable 5/5 goed |
| --- | ---: |
| Opgeslagen in run (live v1.13) | **19 / 19** |
| Offline replay (huidige code, zonder `&& !kernCat` in trede 2) | **19 / 19** |
| Offline replay **met** `&& !kern_categorietaal` terug in trede 2 | **19 / 19** |

**Interpretatie voor jouw besluit 1b:** op v1.13-data maakt het terugzetten van de voorwaarde **geen verschil** meer voor stable 5/5: live, replay nu en replay legacy vallen samen op 19/19. Dat sluit aan bij je keuze om eerst de flag te repareren in plaats van de voorwaarde terug te draaien. We hebben **trede 2 niet gewijzigd** sinds je stopbericht; geen prompttuning na deze run.

## 4. Vergelijking v1.12 → v1.13 (zelfde testset)

| Meting | Stable goed | Pilot? |
| --- | ---: | --- |
| v1.12, poging 2/2 | 13 / 19 | Nee |
| **v1.13, poging 3 (22 okt)** | **19 / 19** | Drempel ja |

Eerdere v1.13-pogingen (tegoed 20 okt, HTTP 500 21 okt) tellen niet mee als pilotmeting.

## 5. Wat zit in het pakket

**Zip:** `docs/rogier-pakket-ronde-7-2026-10-20.zip` (22 okt)

| Bestand | Inhoud |
| --- | --- |
| `08-v1.13-stap5-5x19-eindmeting.md` | Korte eindmeting |
| `09-ruwe-json-v1.13-5runs.json` | Ruwe 5×19 |
| `10-tegenkracht-diagnose-replay-v1.13.md` | Replay 1b |
| `11-stabiliteit-tabel-v1.13.md` | Tabel per post |
| `12-rapportage-v113-eindmeting-2026-10-22.md` | Deze tekst (kopie) |
| `a6/*` | Ongewijzigd t.o.v. je akkoord 21 okt (CE-04 poll 3/3, agenda, hashtags) |

Repo (zelfde inhoud): `docs/hba-testmerken-stability-v1.13-5runs.json`, log `docs/hba-v1.13-stap5-5x19-run-attempt3.log`.

## 6. CE-04 (ter referentie)

Scoring-flow ongewijzigd; CE-04 gebruikt alleen **Jullie regels** voor regelcitaat + `regelFeedbackOk`. Staging-poll post 3: **3/3** ok (Duinroos = vier-zorgverlenersregel). Geen productiedeploy.

## 7. Nog open (zoals afgesproken)

| Onderwerp | Status |
| --- | --- |
| **Fable-slotrun** (vier voorwaarden stopbericht §4) | Nog niet gedraaid — **na** pilotbesluit |
| A6 backlog (EN labels zijpaneel, kalender 1280 px, ik-vorm afkappen) | Backlog, blokkeert pilot niet |
| Valkuil-prioriteit + flagtelling Implementation Kit | Bijlagen in pakket, wachten op jouw prioriteit |

---

**Vraag aan jou:** bevestig je **pilotstart** op v1.13 (staging/productiepad zoals jullie afspreken), en wil je **`&& !kern_categorietaal` in trede 2** gegeven de replay **19/19 / 19/19 / 19/19** expliciet laten vervallen of alsnog terugzetten?

Erik kan de begeleidende mail aanvullen; de artefacten en cijfers hierboven zijn compleet.
