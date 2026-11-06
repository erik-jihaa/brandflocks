# HBA ijkset v0.3 — koppeling naar de app

**Status:** besluiten **7.1–7.7 ingevuld** (Rogier, 16 oktober 2026). Dit is het **enige** koppeldocument.  
**Ijkset:** `web/data/HBA_ijkset_v0_3.json` (46 gevallen).  
**Pilotversie:** ronde 6 maakt **v1.11** (hoofdoordeel uit flags). v1.10.8 is historisch; freeze pas ná ronde-6-criteria. Model **claude-sonnet-4-6**.  
**Nulmeting v0.3:** `docs/hba-ijkrun-nulmeting-v1.10.8.json` (17 okt 2026). Historisch: v0.2-nulmeting op v1.10.6 — andere poort, andere prompt; niet als dezelfde meting vergelijken.

---

## Doel

De ijkset meet in **0–100-banden** en **oordeellabels** die de app (deels) anders benoemt. Dit document legt vast:

1. hoe numerieke banden zich verhouden tot app-scores;
2. hoe ijk-oordelen mappen naar **hoofdoordeel**, **merkbijdrage** en **flags**;
3. hoe ijk-**contentrollen** mappen naar de scorer-enum;
4. hoe **signaalnamen** hangen aan app-velden;
5. welke **API-route** per `soort` — incl. **V01**;
6. de **twee assen** bij architectuur: `verwacht_oordeel` en `verwacht_meetlat`.

---

## Overzicht gevallen en routes

| `soort` | Aantal | Valkuil | Route | Ijkrun |
| --- | ---: | ---: | --- | --- |
| `architectuurcheck` | 18 | o.a. A09–A13, A15 | `ce_hba_v2_run_architecture_check()` | Ja (altijd oordeel; meetlat apart) |
| `tekstscore` | 14 | T04, T05, T09, T12–T14 | `ce_hba_score_*` → `hba_scores` | Ja |
| `schrijfadvies` | 5 | S04, S05 | `ce_hba_v2_run_article_hint()` | Ja |
| `verleidingsthema` | 1 | V01 | `admin-hba-v0-verleiding.php` + CE-03 | Apart (niet in batch) |
| `compressietest` | 8 | C02, C03, C06 | **Geen runner** | Nee — *niet gedraaid* |

**Draaibaar in de batch:** 37 van 46 (38 minus V01). C01–C08 + V01 rapporteren als *niet gedraaid*.

---

## 1. Scoreschaal (0–100 ijkset ↔ app)

### Wat de ijkset gebruikt

Per geval (behalve V01/S01–S05 zonder band): `score_min` en `score_max` op schaal **0–100**.

### Wat de app teruggeeft (tekstscore)

| Veld | Schaal | Bron |
| --- | --- | --- |
| `brand_contribution.score` (merkbijdrage) | 0–10 | tool `hba_score` → `hba_scores.brand_contribution_score` |
| `brand_consistency.score` | 0–10 | idem |
| `architecture_risk.score` | 0–10 (laag = goed) | idem + `architecture_risk_flags` |
| Positionering per post | 0–100 | `ce_hba_v2_post_positioning_score()` |

```text
base = ((merkbijdrage + consistentie) / 2) / 10 × 100
aftrek = (architecture_risk / 10) × 20
positionering = clamp(base − aftrek, 0, 100)
```

**Besluit 7.1 (ingevuld):** **C beslist** (hoofdoordeel, flags, BC-cap = goed/fout). **B waarschuwing:** `positionering` buiten `[score_min, score_max]` is geen automatische fail.

### Architectuurcheck (A01–A18)

Geen 0–100-getal in het product.

| App `totaaloordeel` | Betekenis |
| --- | --- |
| `meetlat_klaar` | Geen dimensie op `aanscherpen` |
| `meetlat_bruikbaar_met_waarschuwing` | Precies één dimensie `aanscherpen` |
| `meetlat_te_zwak` | Twee of meer dimensies `aanscherpen` |

**Twee assen (v0.3):** `verwacht_oordeel` (inhoud) en `verwacht_meetlat` (`actief` / `geblokkeerd`). Dat is geen tegenstrijdigheid: A04/A05/A07/A08/A14 zijn `aanscherpen` én `geblokkeerd`. Mechanisch: sterk → meetlat `actief`; aanscherpen/zwak → `geblokkeerd`.

**Besluit 7.2 (ingevuld):** geen numerieke band bij architectuurcheck — alleen `totaaloordeel`, dimensies en signalen. Judge toetst daarnaast `meetlat_status` ↔ `verwacht_meetlat`.

Technisch veld “meetlat aan”: **`meetlat_status`** (`actief` / `geblokkeerd`) uit de architectuurpoort — niet de oude harde gate.

---

## 2. Oordeellabels (tekstscore)

App-**hoofdoordeel:** `regelbreuk` | `tegenkracht` | `categorietaal` | `op_merk` | `neutraal`  
**Klantlabel** (alleen presentatie): tegenkracht → **Positiebreuk**. Intern blijft `tegenkracht`.

**Volgorde (Rogier §3, in prompt + PHP):** regelbreuk → tegenkracht (foe op kop/opening) → categorietaal → op_merk/neutraal. Tekst die zich *tegen* de tegenkracht keert is geen tegenkracht.

| Ijk `verwacht_oordeel` | Hoofdoordeel | Merkbijdrage / flags | Voorbeeld |
| --- | --- | --- | --- |
| `sterk` | `op_merk` | Geen kern-risicoflag; BC hoog | T01, T02 |
| `neutraal` | `neutraal` | Lage bijdrage ok; geen alarm | T03, T11 |
| `zwak` | zie drie vormen hieronder | BC ≤ 5 | T05, T13, T14 |
| `middelmatig` | `categorietaal` | `category_fallback`; deels onderscheid | T10 |
| `regelbreuk` | `regelbreuk` | `constraint_breach` + uitleg | T04, T09 |
| `tegenkracht` | `tegenkracht` | Foe-logica, geen regelovertreding | T12 |

**Ijk-oordeel `zwak` (v0.3, Rogier §3):**

| Vorm | Hoofdoordeel | Flag | Voorbeeld |
| --- | --- | --- | --- |
| Categorietaal | `categorietaal` | `category_fallback` | T05 |
| Positiebreuk | `tegenkracht` | `foe_vervaging` op de kop | T13 |
| Op merk, zonder bewijs | `op_merk` | `proof_gap` op kop/opening | T14 |

Ijk **regelbreuk** = alleen echte regelovertreding. Tegenspraak met positionering → **tegenkracht**, niet regelbreuk (T12).  
`risico_flag op de kernbewering` = **`proof_gap` of `foe_vervaging`** met citaat uit kop of openingszin.

**T13 vs Duinroos post 7:** dezelfde tekst, ander narratief (acht ijkvelden vs veertien testmerkvelden). Onderzoek: `docs/hba-t13-vs-duinroos-7.md`. Mapper niet gelijkgetrokken.

---

## 3. Oordeellabels (architectuurcheck & compressie)

| Ijk `verwacht_oordeel` | App |
| --- | --- |
| `sterk` | Dimensies overwegend `sterk`/`voldoende`; `totaaloordeel` ≠ `meetlat_te_zwak` |
| `aanscherpen` | Mag `meetlat_bruikbaar_met_waarschuwing` |
| `zwak` | Meerdere zwakke dimensies / geen onderscheid |

### Compressietest (C01–C08)

| Ijk label | Bedoeling |
| --- | --- |
| `positionerend` | Behoudt onderscheid |
| `opgelost` | Generiek geworden |
| `grensgeval` | Twijfel |

**Besluit 7.4 (ingevuld):** buiten de nulmeting; route erna; **geen go-live** zonder C02, C03 en C06.

---

## 4. Contentrollen

Scorer-enum: `positionering` | `bewijs` | `educatie` | `activatie` | `community` | `service` | `employer_culture` | `reactief`

**Klantlabels (§6):** educatie → Kennis delen; activatie → Oproep tot actie; employer_culture → Team en cultuur; reactief → Reactie op nieuws. Community en Service blijven.

| Ijk | App | Opmerking |
| --- | --- | --- |
| `positionering` | `positionering` | 1:1 |
| `bewijs` | `bewijs` | 1:1 |
| `kennis` | `educatie` | Zelfde intentie |
| `service` | `service` | 1:1 |
| `engagement` (eigen mensen, T03) | **`employer_culture`** | Teamuitje |
| `engagement` (volgers/klanten) | **`community`** | |
| `verkoop` | **`activatie`** | |
| `aankondiging` | **`activatie`** | |
| `reactief` | **`reactief`** | Alleen nieuws van buiten |

**Besluit 7.3 (ingevuld):** vuistregel = wat de post de lezer laat **doen of zien**. In v0.3 staan de signalen al in app-namen.

---

## 5. Signaalnamen ↔ app-velden

### 5.1 Tekstscore — flags & kern

| Signaal (ijk) | App / judge |
| --- | --- |
| `risico_flag op de kernbewering` | `proof_gap` of `foe_vervaging` + citaat kop/opening |
| `geen risico_flag op de kop` | Afwezigheid daarvan op die zin |
| `proof_gap of foe_vervaging op de kop '…'` | T13; flag + citaat |
| `proof_gap op '…'` | T14 |
| `foe_vervaging` | Flag + uitleg |
| `risico_flag_tegenspraak_met_regel` | `constraint_breach` |
| `risico_flag_tegenspraak_met_wat_anders` | `foe_vervaging` / hoofdoordeel `tegenkracht` |
| `categorietaal_gedetecteerd` | `category_fallback` |
| `hoofdoordeel regelbreuk` (verboden) | Mag niet zonder `constraint_breach` |

### 5.2 Tekstscore — gedrag & advies

| Signaal | App / judge |
| --- | --- |
| `draagt_regel_uit` | `op_merk`, geen breach |
| `lage_bijdrage_zonder_alarm` | `neutraal` + lage BC |
| `hygienefactor_gedetecteerd` | Lage distinctiviteit / categorie; geen automatische zwak |
| `advies_koppelt_aan_merkstandpunt` | `verbeteradvies` |
| `deels_onderscheid_herkend` | Diagnose noemt cliché én differentiator (T10) |

### 5.3 Architectuurcheck

| Signaal | App-veld / afleiding |
| --- | --- |
| `onderscheid_herkend` | `scherpte_en_uniciteit` niet “iedereen kan dit zeggen” |
| `hygienefactor_gedetecteerd` | `scherpte_en_uniciteit.zit_in_hygienefactoren` |
| `strawman_gedetecteerd` | `scherpte_en_uniciteit.strawman_gedetecteerd` |
| `regel_met_commerciele_prijs_herkend` | `regels_oordeel` + toelichting |
| `regel_draagt_bewijslast` | A18 |
| `tegenkracht_ontbreekt` / `regel_ontbreekt` | Bevinding op veldniveau (poort, 7.6); dimensie op `aanscherpen` |
| `gebruikt methodische term in klantoutput` | Verbodslijst op dimensie-toelichting + suggesties (stap 3) |

### 5.4 Schrijfadvies (S01–S05)

| Signaal | App |
| --- | --- |
| `precies_een_aanwijzing` | Eén hint-record |
| invulveld met beschrijving | `[…]` + `invulvelden[]` |
| `voorbeeld uit bewijsveld` | `bewijsveld_bron` ≠ null |
| `herschrijving bevat een aantal…` (verboden) | Geen nieuwe feiten |

**Besluit 7.5 (ingevuld):** plaatsing van het invulveld (`passage_na` vs `cta_na`) **telt niet mee**. S05 toetst: de hint vraagt om een feit en verzint er geen. Wel vastleggen in welk veld het stond.

### 5.5 Verleidingsthema V01

Route: `admin-hba-v0-verleiding.php` → CE-03 → `content_ai` + `self_review`. Meet de **schrijver**, niet de scorer. Batch-ijkrun: *niet gedraaid*.

---

## 6. Metadata ijkrun

| Veld | Waarde |
| --- | --- |
| `ijkset_versie` | `0.3` |
| `ijkgeval_id` | A01 … V01 |
| `prompt_version` | `CE_HBA_PROMPT_VERSION` (pilot **v1.10.8**) |
| `model` | o.a. `hba_scores.model_used` |
| `oordeel_status` / `meetlat_status` | Twee assen bij architectuur |

**Valkuilscore = drie getallen:** goed · fout · niet gedraaid.  
Niet wegmoffelen: **V01, C02, C03, C06** (en historisch A15 tot de poort draaide). A15 draait sinds stap 1 mee.

**Nulmeting v0.3 (v1.10.8):** 7 goed · 26 fout · 4 review · 9 niet gedraaid. Valkuil: 2 goed · 10 fout · 2 review · 4 niet gedraaid. Zie `docs/hba-ijkrun-nulmeting-v1.10.8-samenvatting.md`.

---

## 7. Besluiten 7.1–7.7 (allemaal ingevuld)

Bron: Rogier, 16 oktober 2026 (`briefing/hba-input/Voor Erik 2026-10-16/hba-besluiten-en-nulmeting-2026-10-16.md`). Geen open vakjes.

### 7.1 Numerieke meetlat tekstscore

**Besluit:** C beslist (hoofdoordeel, flags, BC-cap); positionering buiten de band is een **waarschuwing**, geen fout.

### 7.2 Architectuurcheck: numerieke band

**Besluit:** geen getal. Alleen `totaaloordeel`, dimensies en signalen. In v0.3 erbij: `verwacht_meetlat`.

### 7.3 Contentrollen

**Besluit:** T03 teamuitje = `employer_culture`; engagement met volgers/klanten = `community`; verkoop en aankondiging = `activatie`. Vuistregel: wat de post de lezer laat doen of zien. v0.3-signalen gebruiken app-namen.

### 7.4 Compressietest

**Besluit:** buiten de nulmeting; route erna; geen go-live zonder C02, C03 en C06.

### 7.5 Plaatsing invulveld

**Besluit:** telt niet mee. S05: hint vraagt om een feit en verzint er geen. Wel vastleggen in welk veld het invulveld staat.

### 7.6 Merk zonder tegenkracht (architectuurpoort)

**Besluit (niet de oude A/B-keuze weigeren vs alleen-zwak):**

- Check **altijd**; leeg veld = bevinding (`tegenkracht_ontbreekt` / `regel_ontbreekt`), geen reden om de check over te slaan.
- Poort blokkeert **content en meetlat**, niet het oordeel. Status `actief` alleen als alle velden gevuld zijn én elke dimensie minstens voldoende (`meetlat_klaar`). Anders `geblokkeerd` + oordeel + advies.
- Geen kale melding “vul eerst tegenkracht in”.
- A15, A09, A12: inhoudelijk oordeel + `geblokkeerd`. A01–A03, A17, A18: `actief` (klaar-lijst stap 1).

Gebouwd in stap 1 (`059184d`). A15 telt sindsdien als **gedraaid**.

### 7.7 `regel_is_wettelijke_plicht`

**Besluit:** geen apart signaal. Wettelijke plicht = uiterste hygiënefactor. A15 toetsen op `hygienefactor_gedetecteerd` op `regels`; redenering mag de wettelijke plicht noemen.

Branche-afleider fase 2 (wettelijke normen in niet-gecureerde branches) is **niet** pre-pilot; geen branchelijst hardcoden om A15 groen te krijgen.

---

## 8. Gerelateerde code

| Onderdeel | Pad |
| --- | --- |
| Scoring prompt + enum + klantlabel | `web/api/hba-prompt.php` |
| Merkbijdrage-cap / finalize | `web/api/hba-repository.php` |
| Positionering 0–100 | `web/api/hba-v2-repository.php` |
| Architectuurcheck + poort | `web/api/hba-architectuur-check-repository.php`, `hba-architecture-poort.php` |
| Verbodslijst | `web/api/hba-architecture-verbodslijst.php` |
| Ijkrun | `web/api/hba-ijkrun-repository.php` |
| Hints | `ce_hba_v2_run_article_hint()` |
| V0 | `web/api/admin-hba-v0-verleiding.php` |
| Testmerken-judge | `web/api/hba-testmerken-repository.php` |

---

## 9. Wat dit document niet is

- Geen tweede besluitenlijst. Open punten die de koppeling niet zijn (A6-akkoord Rogier, V01-run, compressie-route) staan in `docs/hba-v1.10-bouwrapport-2026-10-17.md` / `docs/BUILD-NOTES.md`, niet hier als open vakjes.
- Geen promptwijziging: pilot **v1.10.8** blijft bevroren tot een afgesproken moment.
