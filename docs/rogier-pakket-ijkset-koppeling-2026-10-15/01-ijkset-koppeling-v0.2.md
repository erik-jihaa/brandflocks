# HBA ijkset v0.2 — koppeling naar de app (concept)

**Status:** bevestigd door Rogier, **16 oktober 2026** (Erik §7.1–7.5).  
**Geen importcode of ijkrun-automatisering** tot na pilot-gates; nulmeting 38 gevallen, compressie H4 vóór go-live.  
**Bron ijkset:** `briefing/hba-input/HBA_ijkset_v0_2.json` (46 gevallen, versie 0.2).  
**App-versie bij schrijven:** HBA scoring prompt **v1.10.4**, model **claude-sonnet-4-6**.

---

## Doel

De ijkset meet in **0–100-banden** en **oordeellabels** die de app (deels) anders benoemt. Dit document legt vast:

1. hoe numerieke banden (`score_min` / `score_max`) zich verhouden tot app-scores;
2. hoe ijk-oordeel labels mappen naar **hoofdoordeel**, **merkbijdrage** en **flags**;
3. hoe ijk-**contentrollen** mappen naar de scorer-enum;
4. hoe **signaalnamen** uit de ijkset hangen aan bestaande (of nog af te leiden) velden;
5. welke **API-route** per `soort` gebruikt wordt — incl. **V01** (verleidingsthema).

Waar de koppeling niet eenduidig is, staat een **open vraag** (geen stille keuze).

---

## Overzicht gevallen en routes

| `soort` | Aantal | Valkuil | Route in de app (vandaag) | Ijkrun |
| --- | ---: | ---: | --- | --- |
| `architectuurcheck` | 18 | 0 | `ce_hba_v2_run_architecture_check()` → `architectuur_check_result` | Ja |
| `tekstscore` | 14 | 2 (T13, T14) | `ce_hba_score_*` / tool `hba_score` → `hba_scores` | Ja |
| `schrijfadvies` | 5 | 1 (S05) | `ce_hba_v2_run_article_hint()` → `hba_article_hints` | Ja |
| `verleidingsthema` | 1 | 1 (V01) | V0: `admin-hba-v0-verleiding.php` + CE-03 → `articles.self_review` | Ja |
| `compressietest` | 8 | 3 (C02, C03, C06) | **Geen runner** (niet CE-03 posterwoord; niet wizard) | Nee |

**Draaibaar vandaag:** 38 van 46. Compressie **C01–C08** rapporteren als *niet gedraaid* (Rogier 7.4). Zie `docs/hba-v1.10-stap9-compressietest.md`.

---

## 1. Scoreschaal (0–100 ijkset ↔ app)

### Wat de ijkset gebruikt

Per geval (behalve V01/S01–S05 zonder band): `score_min` en `score_max` op schaal **0–100**.  
Interpretatie (Rogier, v0.2-definities): hoger = sterker voor het merk, behalve bij expliciet **zwak** / **regelbreuk** / **tegenkracht** (lage band).

### Wat de app teruggeeft (tekstscore)

| Veld | Schaal | Bron |
| --- | --- | --- |
| `brand_contribution.score` (merkbijdrage) | 0–10 | tool `hba_score` → `hba_scores.brand_contribution_score` |
| `brand_consistency.score` | 0–10 | idem |
| `architecture_risk.score` | 0–10 (laag = goed) | idem + `architecture_risk_flags` |
| **Positionering per post** (dashboard / overzicht) | 0–100 | `ce_hba_v2_post_positioning_score()` in `hba-v2-repository.php` |

Formule positionering (conceptueel):

```text
base = ((merkbijdrage + consistentie) / 2) / 10 × 100
aftrek = (architecture_risk / 10) × 20
positionering = clamp(base − aftrek, 0, 100)
```

Subdimensies merkbijdrage (o.a. `distinctiviteit`) lopen **0–5**; totaal merkbijdrage **0–10**.

### Concept voor ijkrun-vergelijking (tekstscore)

| Optie | Afgeleide ijk-score | Voor | Tegen |
| --- | --- | --- | --- |
| **A** | `merkbijdrage × 10` | Eenvoudig; sluit aan bij Rogiers focus op bijdrage | Negeert consistentie, risk, positionering |
| **B** | `positionering` (formule hierboven) | Zelfde getal als klant-overzicht | Minder direct bij “zwak/neutraal” in ijk-toelichting |
| **C** | Bandcheck **alleen** via hoofdoordeel + flags + BC-cap; `score_min/max` alleen als **secundaire** waarschuwing | Sluit aan bij testmerken-judge (T13/T14) | Minder numerieke regressiedetectie |

**Besluit Rogier (7.1):** **C primair** (flags + hoofdoordeel + BC-cap = goed/fout); **B waarschuwing** — `positionering` buiten `[score_min, score_max]` = review, geen automatische fail. Optie A niet leidend.

### Architectuurcheck (A01–A18)

Ijkset gebruikt dezelfde 0–100 band, maar het app-**totaaloordeel** is geen getal:

| App `totaaloordeel` | Betekenis (prompt architectuurcheck) |
| --- | --- |
| `meetlat_klaar` | Geen dimensie op `aanscherpen` |
| `meetlat_bruikbaar_met_waarschuwing` | Precies één dimensie `aanscherpen` |
| `meetlat_te_zwak` | Twee of meer dimensies `aanscherpen` |

Per dimensie: `oordeel` ∈ `sterk` | `voldoende` | `aanscherpen` (plus optioneel `strawman_gedetecteerd`, `zit_in_hygienefactoren` op scherpte).

**Concept mapping ijk ↔ app (architectuurcheck):**

| Ijk `verwacht_oordeel` | Concept app-check |
| --- | --- |
| `sterk` | `positionering`-proxy **niet** van toepassing; verwacht: `totaaloordeel = meetlat_klaar` **of** band `[score_min,max]` via afgeleide arch-score (zie onder) |
| `aanscherpen` | `meetlat_bruikbaar_met_waarschuwing` **of** minstens één dimensie `aanscherpen` + verplichte signalen (hygiëne, advies, …) |
| `zwak` | `meetlat_te_zwak` **of** meerdere dimensies `aanscherpen` / identity gate |

**Besluit Rogier (7.2):** geen numerieke band bij architectuurcheck — alleen `totaaloordeel`, dimensies en signalen.

---

## 2. Oordeellabels (tekstscore)

App-**hoofdoordeel** (`hoofdoordeel` in tool, enum in `CE_HBA_HOOFDOORDEEL_VALUES`):

`regelbreuk` | `tegenkracht` | `categorietaal` | `op_merk` | `neutraal`

### Koppeltabel ijk → app (tekstscore)

| Ijk `verwacht_oordeel` | Hoofdoordeel (verwacht) | Merkbijdrage / flags (concept) | Voorbeeld ijk |
| --- | --- | --- | --- |
| `sterk` | `op_merk` | Geen `risico_flag op de kernbewering`; BC hoog; band B binnen `[score_min,max]` | T01, T02 |
| `neutraal` | `neutraal` | Lage bijdrage ok; geen flag op kern; geen “alarm” | T03, T11 |
| `zwak` | Meestal `op_merk` (niet `regelbreuk`) | `proof_gap` / `foe_vervaging` op **kop of openingszin**; BC ≤5 (PHP-cap v1.10.3); band laag | T13, T14 |
| `middelmatig` | `categorietaal` | `category_fallback`; deels onderscheid | T10 |
| `regelbreuk` | `regelbreuk` | `constraint_breach` + uitleg; band zeer laag | T04, T09 |
| `tegenkracht` | `tegenkracht` | Tegenspraak met `wat_anders` / Foe; **geen** regelovertreding | T12 |

**Let op (Rogier v0.2):** ijk **regelbreuk** = alleen echte regelovertreding. Tegenspraak met positionering → app **tegenkracht**, niet regelbreuk (T12).

**Let op (v1.10):** `risico_flag op de kernbewering` in de ijkset = **`proof_gap` of `foe_vervaging`** met citaat uit kop of openingszin (`flag_citaten` in ruwe score).

### Hoofdoordeel vs ijk-oordeel

Ijk-labels **sterk / zwak / neutraal** zijn **kwaliteitsoordelen**, geen synoniemen van hoofdoordeel. De ijkrun moet **beide** kunnen checken waar relevant:

- **Valkuil tekst:** flags + BC-cap (testmerken-logica);
- **Overige tekst:** hoofdoordeel + optioneel band B.

---

## 3. Oordeellabels (architectuurcheck & compressie)

### Architectuurcheck

| Ijk `verwacht_oordeel` | App-velden (concept) |
| --- | --- |
| `sterk` | Dimensies overwegend `sterk`/`voldoende`; `totaaloordeel` ≠ `meetlat_te_zwak`; verboden signalen afwezig |
| `aanscherpen` | Hygiëne/strawman/advies-signalen zoals in geval; mag `meetlat_bruikbaar_met_waarschuwing` |
| `zwak` | Meerdere zwakke dimensies / generiek doel / geen onderscheid |

Geen app-veld `verwacht_oordeel: sterk` — judge leest **JSON architectuurcheck** + **verplichte/verboden signalen** (deels handmatig of LLM-judge in ijkrun fase 2).

### Compressietest (C01–C08)

| Ijk label | Bedoeling |
| --- | --- |
| `positionerend` | Poster/summary behoudt onderscheid |
| `opgelost` | Generiek geworden |
| `grensgeval` | Twijfelgeval |

**App:** geen endpoint. **Besluit Rogier (7.4):** nulmeting **zonder** compressie; H4 direct daarna; **go-live niet** zonder C02/C03/C06 (100% valkuil).

---

## 4. Contentrollen

Scorer-enum (`hba_score.contentrol`):  
`positionering` | `bewijs` | `educatie` | `activatie` | `community` | `service` | `employer_culture` | `reactief`

| Ijk (in signalen) | App (concept) | Opmerking |
| --- | --- | --- |
| `positionering` | `positionering` | 1:1 |
| `bewijs` | `bewijs` | 1:1 |
| `kennis` | `educatie` | Naam verschilt; zelfde intentie |
| `service` | `service` | 1:1 |
| `engagement` | **`employer_culture`** | T03 teamuitje eigen medewerkers; bij klanten/volgers mee → `community` |
| `verkoop` | **`activatie`** | |
| `aankondiging` | **`activatie`** | |
| `reactief` | **`reactief`** | Alleen reageren op nieuws van buiten |

**Vuistregel Rogier (7.3):** contentrol = wat de post de lezer laat **doen of zien**.

---

## 5. Signaalnamen ↔ app-velden

Signaalnamen zijn **voorstellen** (Rogier, JSON-toelichting). Groepering voor implementatie later.

### 5.1 Tekstscore — flags & kern

| Signaal (ijk) | App / judge |
| --- | --- |
| `risico_flag op de kernbewering` | `proof_gap` of `foe_vervaging` in `architecture_risk.flags` + citaat kop/opening in `flag_citaten` |
| `geen risico_flag op de kop` / `… openingszin` | Afwezigheid van bovenstaande op genoemde zin |
| `proof_gap of foe_vervaging op de kop '…'` | Specifieke case (T13); zelfde + string match citaat |
| `proof_gap op '…' of op '…'` | Case T14; flag + citaat |
| `foe_vervaging` | Flag + `flag_citaten.foe_vervaging_uitleg` |
| `risico_flag_tegenspraak_met_regel` | `constraint_breach` (met uitleg) |
| `risico_flag_tegenspraak_met_wat_anders` | `foe_vervaging` / hoofdoordeel `tegenkracht` (T12) |
| `categorietaal_gedetecteerd` | `category_fallback` |
| `hoofdoordeel regelbreuk` (verboden) | Hoofdoordeel mag **niet** `regelbreuk` als geen `constraint_breach` |

### 5.2 Tekstscore — gedrag & advies

| Signaal | App / judge |
| --- | --- |
| `draagt_regel_uit` | Hoofdoordeel `op_merk`, geen breach, inhoudelijke check (LLM of rubric) |
| `herkent_impliciet_bewijs_van_werkwijze` | Geen penalty voor ontbreken expliciete claim (T02) |
| `lage_bijdrage_zonder_alarm` | `neutraal` + lage BC, geen kern-flag |
| `hygienefactor_gedetecteerd` | Lage distinctiviteit / category context; geen automatische zwak |
| `advies_koppelt_aan_merkstandpunt` | `verbeteradvies` in score-output (tekst) |
| `deels_onderscheid_herkend` | Diagnose noemt zowel cliché als echte differentiator (T10) |

### 5.3 Architectuurcheck

| Signaal | App-veld / afleiding |
| --- | --- |
| `onderscheid_herkend` | `scherpte_en_uniciteit.oordeel` ≠ `aanscherpen` wegens “iedereen kan dit zeggen” |
| `hygienefactor_gedetecteerd` (varianten) | `scherpte_en_uniciteit.zit_in_hygienefactoren === true` |
| `strawman_gedetecteerd` | `scherpte_en_uniciteit.strawman_gedetecteerd` |
| `regel_met_commerciele_prijs_herkend` | `regels_oordeel` + toelichting (kwalitatief) |
| `regel_draagt_bewijslast` | A18; uniciteit/ regels-dimensie |
| `markt_archetype_gelijk_aan_merk_herkend` | Branche-afleider + archetype-marktmatch (CE) |
| `advies_wijst_regel_aan_als_onderscheid` | Soft/hard advies in dimensie-`verbetering` |
| `gebruikt methodische term in klantoutput` | String scan op diagnose/advies (verboden woordenlijst prompt) |

### 5.4 Schrijfadvies (S01–S05)

| Signaal | App |
| --- | --- |
| `precies_een_aanwijzing` / `hoogstens_een_aanwijzing` | Eén hint-record; veld `aanwijzing` |
| `invulveld met specifieke beschrijving…` | `[…]` in `passage_na` / `headline_na` / `cta_na` + `invulvelden[]` |
| `voorbeeld uit bewijsveld` | `bewijsveld_bron` ≠ null |
| `hoogstens twee invulvelden` | `count(invulvelden) ≤ 2` |
| `herschrijving bevat een aantal…` (verboden) | Geen nieuwe feiten buiten tekst/bewijs/research in `passage_na` |
| `herschrijving_in_stem_van_klant` | Stijlcheck (rubric) |

### 5.5 Verleidingsthema V01

| Signaal | App |
| --- | --- |
| `themePremisse herkend` | `articles.self_review` → `themePremisse` / `theme_premisse` (CE-03) |
| `schrijft over de regel: waarom doorverwijzen…` | Inhoudelijke rubric op `content_ai` |
| `gebruikt alleen feiten uit…` | Geen feiten buiten narrative/bewijs/research |
| `beweert dat de wachtlijst is opgelost…` (verboden) | String / semantische check op kop en body |
| `vervangt alleen het middel…` (verboden) | Premisse-check (zelfde als V0 Stelwerk/Duinroos) |

**Route V01 (vastgelegd):**

1. POST `web/api/admin-hba-v0-verleiding.php` — `clientId`, `theme` (= ijk input.thema), `researchUrls`.
2. CE-03 schrijft artikel; output in `articles.content_ai`, `articles.self_review`.
3. Optioneel:zelfde tenant/score **niet** verplicht voor V01 (ijk meet **schrijver**, niet scorer).

---

## 6. Metadata ijkrun (concept)

Per uitkomst loggen (Rogier + Erik §10):

| Veld | Waarde |
| --- | --- |
| `ijkset_versie` | `0.2` |
| `ijkgeval_id` | A01 … S05 |
| `prompt_version` | o.a. `CE_HBA_PROMPT_VERSION` voor score; aparte versies theme/hint/architectuur |
| `model` | kolommen `model` op `hba_scores`, hints, architectuurcheck, … |

**Go-live valkuil:** gevallen met `valkuil: true` → **100%** goed binnen deze koppeling.  
**Rapportage:** aparte kolom *compressie niet gedraaid* voor C01–C08.

---

## 7. Vijf besluiten voor Rogier

Per punt staat een voorstel. Zeg ja, of noem de afwijking; dan verwerken wij het en begint de ijkrun-runner. Waar het voorstel een bouwargument is en geen methodiekkeuze, staat dat erbij.

### 7.1 Numerieke meetlat tekstscore: A, B of C

**Voorstel: C beslist, B als waarschuwing** — voor alle tekstscores, niet alleen de valkuilen.

De ijkbanden zijn jouw oordeel op een schaal waarin jij denkt. Het getal van de app is een samengestelde waarde uit merkbijdrage, consistentie en risico. Vergelijk je die twee direct (optie B als beslisser), dan kan een geval zakken omdat de consistentie bewoog terwijl het oordeel klopte. Dat is ruis, geen terugval.

C sluit aan op wat de testmerken-judge al doet (hoofdoordeel, flags, cap), dus blijft er één mechanisme in plaats van twee. A is eenvoudiger, maar negeert risico en consistentie, en juist daar zitten `proof_gap` en `constraint_breach`.

Het nadeel van C alleen is dat je numerieke terugval niet ziet, en daar is de nulmeting voor bedoeld. Daarom B ernaast: leg per geval de positionering (0–100) vast en meld "buiten band" als waarschuwing in de tabel, niet als fout.

> **Besluit Rogier:** [ ] akkoord · [ ] anders, namelijk …

### 7.2 Architectuurcheck: numerieke band naast totaaloordeel

**Voorstel: geen numerieke band.** `totaaloordeel` plus de dimensies plus de verplichte signalen volstaat. *(Bouwargument.)*

Een architectuurscore van 0 tot 100 bestaat nergens in het product. Leiden we er een af als gemiddelde van de dimensies, dan ontstaat een getal dat onderhouden en uitgelegd moet worden terwijl niemand het ziet. De achttien architectuurgevallen zijn de grootste groep van de ijkset en zes ervan zijn valkuil; die gaan over de vraag of de check de zwakte ziet, niet over een cijfer. Wil je later een trendlijn, dan leiden we die alsnog af uit de dimensies.

`score_min`/`score_max` blijven in de ijkset staan als jouw toelichting, maar tellen niet mee in goed of fout.

> **Besluit Rogier:** [ ] akkoord · [ ] toch een band, berekend als …

### 7.3 Contentrollen: engagement, verkoop, aankondiging

**Voorstel:** `engagement` → `community` · `verkoop` → `activatie` · `aankondiging` → `activatie`, waarbij `reactief` alleen blijft voor reageren op nieuws van buiten.

Toelichting bij de keuzes: een teamuitje (T03) is community, want activatie vraagt om een handeling van de lezer. Een productaankondiging (T12) vraagt wel om een handeling, dus activatie; `reactief` zou suggereren dat het merk op iets van buiten reageert.

Bouwargument om mee te wegen: een nieuwe rol in de enum raakt niet alleen de scorer, maar ook de contentrol-mix op het Overzicht, de radar in het Rapport en de portfoliosignalen. Voor de ijkset is dat te duur; mappen op de bestaande acht houdt het klein. Dit is wel methodiek, dus jouw besluit.

Tot je akkoord blijft de regel uit §4: afwijking is *review*, geen fout.

> **Besluit Rogier:** [ ] akkoord · [ ] anders: engagement → … · verkoop → … · aankondiging → …

### 7.4 Compressietest: H4 bouwen of C02/C03/C06 tijdelijk uitsluiten

**Voorstel: tijdelijk uitsluiten, H4 direct ná de eerste ijkrun.** *(Het criterium is van jou; de volgorde is een bouwargument.)*

Er is geen runner voor de compressietest; dat is een nieuwe route met een eigen prompt en uitvoerschema. Bouwen we die eerst, dan wordt het de bottleneck voor de nulmeting waar al het andere op wacht.

Het go-live-criterium leest dan: honderd procent van de **vijftien** valkuil-gevallen die een route hebben, met de compressietest expliciet open. Dat is een zichtbaar gat, geen stil gat.

Niet eindeloos parkeren: de compressietest is een van de vier onderdelen uit je kalibratiespecificatie, en juist bij Duinroos — waar het merkarchetype samenvalt met de branche — is compressie het punt.

> **Besluit Rogier (16 okt):** uitsluiten van de nulmeting; compressie-route ná de ijkrun; go-live niet zonder C02/C03/C06. Addendum H4 (branche-afleider) is die route niet — zie stap 9.

### 7.5 Schrijfadvies: is `cta_na` met invulveld fout?

**Voorstel: niet fout, maar wel apart vastleggen.**

Waar het invulveld landt, is plaatsing; waar S05 over gaat, is dat de hint om een feit vráágt in plaats van er een te verzinnen. De ijk toetst dus op "de hint bevat een invulveld en verzint geen feiten", en we leggen daarnaast vast in welk veld het terechtkwam (`passage_na` of `cta_na`).

Vind je dat de hint de passage moet herschrijven en niet alleen de CTA, dan is dat wél methodiek. Dan wordt het een verplicht signaal en is `cta_na` alleen wel fout.

Los hiervan blijft je punt 5 een codefout die we sowieso repareren: bij Brandpunt stond het invulveld dubbel in de lijst en zat de hint in `cta_na` en niet in `passage_na`.

> **Besluit Rogier:** [ ] plaatsing telt niet mee · [ ] passage verplicht, `cta_na` alleen is fout

### 7.6 Een merk zonder tegenkracht: weigeren of oordelen?

**Aanleiding:** A15 (Kinderopvang Zonnig) heeft `niet_in_geloven` leeg. De app weigert de check dan met "Vul eerst persoonlijkheid, doel en tegenkracht in", dus jouw geval kreeg geen inhoudelijk oordeel. In de nulmeting staat het nu als fout geteld; dat corrigeren we naar *niet gedraaid*.

Je verplichte signalen bij A15 zijn `hygienefactor_gedetecteerd` en `regel_is_wettelijke_plicht`. Daaruit lezen wij dat je een inhoudelijk oordeel verwacht en niet een weigering. Dat past ook bij A09 en A12, waar `regels` leeg is en je `regel_ontbreekt` verwacht: een leeg veld is bij jou een bevinding, geen blokkade.

Er zit nog een tweede poort achter de eerste. Ook als we de weigering overslaan, komt het geval langs de identity gate, die doel en tegenkracht op niveau toetst. Met een lege tegenkracht faalt die vrijwel zeker, en dan draait de inhoudelijke check nog steeds niet. We leveren een losse diagnose van A15 aan zodat je dit met de echte uitkomst voor je kunt beslissen.

**Voorstel:** een merk zonder tegenkracht wordt inhoudelijk beoordeeld en komt uit op *zwak*, in plaats van geweigerd te worden. Dat betekent dat de identity gate een lege tegenkracht doorlaat naar de inhoudelijke check. Let op: dit raakt ook echte klanten in de wizard, niet alleen de ijkrun.

> **Besluit Rogier:** [ ] inhoudelijk beoordelen, oordeel *zwak* · [ ] weigeren blijft goed, dan passen we de verwachting bij A15 aan naar "poort weigert" · [ ] anders, namelijk …

### 7.7 Bestaat `regel_is_wettelijke_plicht` als signaal?

A15 is het enige geval dat dit signaal vraagt. De app kent het niet. De vraag is of je een nieuwe herkenning wilt, of dat het een zwaardere vorm van iets bestaands is.

**Voorstel:** behandel een wettelijke plicht als de uiterste vorm van een hygiënefactor, en toets A15 op `hygienefactor_gedetecteerd` op het veld `regels`, plus je toelichting in de redenering. Dan is er geen nieuwe code nodig en meet je nog steeds wat je bedoelt: de app moet zien dat die regel geen keuze is.

Wil je dat de app wettelijke plichten apart herkent en benoemt, dan is dat nieuw werk: een eigen signaal en een eigen controle, voor alle branches met wettelijke normen (zorg, kinderopvang, financieel advies, bouw).

> **Besluit Rogier:** [ ] hygiënefactor op `regels` volstaat · [ ] apart signaal bouwen · [ ] anders, namelijk …

---

## 8. Gerelateerde code (referentie)

| Onderdeel | Pad |
| --- | --- |
| Scoring prompt + enum | `web/api/hba-prompt.php` |
| Merkbijdrage-cap / finalize | `web/api/hba-repository.php` |
| Positionering 0–100 | `web/api/hba-v2-repository.php` → `ce_hba_v2_post_positioning_score()` |
| Architectuurcheck | `web/api/hba-architectuur-check-repository.php`, prompts in `hba-v2-prompts.php` |
| Hints | `web/api/hba-v2-repository.php` → `ce_hba_v2_run_article_hint()` |
| V0 | `web/api/admin-hba-v0-verleiding.php`, `scripts/run-hba-v0-staging.py` |
| Testmerken-judge (verwacht_flags_een_van) | `web/api/hba-testmerken-repository.php` |

---

*Concept — wijzigingen alleen na akkoord Rogier. Daarna: ijkrun-runner + import (aparte stap, geen onderdeel van dit document).*
