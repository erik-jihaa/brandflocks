# HBA v1.10 — bouwrapport (bouwvolgorde 16 oktober)

**Datum:** 17 oktober 2026  
**Voor:** Erik (intern) · **Rogier:** A6-screenshots + status pilot  
**Branch:** `hba-ronde-4-v1.9` · **Laatste commit:** `b51be78`  
**Omgeving:** alleen **staging** (`https://staging.igisnext.com/`) — geen productie  
**Pilot freeze:** scoring prompt **`v1.10.8`** · model **`claude-sonnet-4-6`**

**Bron:** `briefing/cursor-bouwvolgorde-besluiten-16-oktober.md`  
**Koppeldocument (7.1–7.7):** `docs/hba-ijkset-koppeling-v0.3.md`  
**Levend log:** `docs/BUILD-NOTES.md`

---

## 1. Samenvatting

| Onderdeel | Status |
| --- | --- |
| Bouwvolgorde stap **0–6** (code + docs) | **Afgerond** en op staging gedeployed |
| Koppeldocument **v0.3** (7.1–7.7) | **Ingevuld** — enige bron |
| Stap **5** metingen (5×19 + v0.3-ijkrun op v1.10.8) | **Gedraaid** — resultaten onder §4 |
| Stap **4** A6-screenshots | **Gemaakt** (CF_Staging) — §5 + map `docs/a6-screenshots-staging-2026-10-17/` |
| Rogier **goedkeuring A6** | **Open** — wacht op zijn vinkjes per rij |
| Pilot **go-live** (valkuil + stabiliteit) | **Nog niet** — zie §4 en §6 |

De bouwvolgorde uit Rogiers besluiten van 16 oktober is daarmee **technisch doorlopen**. Wat overblijft is vooral **kalibratie/productbesluit** (ijkrun-fouten, hoofdoordeel-sync, T13/Duinroos 7) en **Rogiers akkoord op klanttaal**.

---

## 2. Uitgevoerde stappen (commits)

Eén commit per stap (plus BUILD-NOTES-hash commits waar van toepassing).

| Stap | Commit | Inhoud |
| --- | --- | --- |
| 0 | `e8dc302` | Ijkset **v0.3**; `verwacht_meetlat` + judge meetlat/oordeel |
| 1 | `059184d` | Architectuurpoort: check altijd; `tegenkracht_ontbreekt` / `regel_ontbreekt`; geen harde gate |
| 2 | `92b2fc2` | Hoofdoordeel-volgorde; label **Positiebreuk**; prompt **v1.10.7** → freeze **v1.10.8** |
| 3 | `f69fe82` | Verbodslijst architectuur (suggesties + dimensie-toelichting) |
| 4 | `0fbf67e` | Klanttaal §6; contentrollen NL; Remove→Verwijderen (normalisatie) |
| 5 | `2548e29` | Pilot **v1.10.8**; runplan 5×19 + v0.3-ijkrun |
| 6 | `d8276ee` | **Geen bouw:** T13 vs Duinroos 7 — `docs/hba-t13-vs-duinroos-7.md` |
| doc | `2b574e2` | Koppeldocument **v0.3**, besluiten **7.1–7.7** |

Detail per stap: `docs/hba-v1.10-stap1-architectuurpoort.md` … `stap5-pilotruns.md`, `hba-t13-vs-duinroos-7.md`.

---

## 3. Wat op staging staat

- Frontend + API met bovenstaande wijzigingen (deploy na commits; smoke **6/6** bij laatste deploy).
- Ijkset: `web/data/HBA_ijkset_v0_3.json` (46 gevallen).
- Scoring: `CE_HBA_PROMPT_VERSION = v1.10.8` (niet wijzigen tijdens pilot zonder afspraak).

**Niet in deze ronde (bewust):** branche-afleider fase 2 (§2), compressie-runner (C02/C03/C06), V01 in batch (CE-03 apart).

---

## 4. Metingen op pilot v1.10.8 (stap 5)

### 4.1 v0.3-ijkrun (nulmeting)

| | |
| --- | --- |
| **Artifact** | `docs/hba-ijkrun-nulmeting-v1.10.8.json`, `…-samenvatting.md`, `…-log` |
| **Run** | 2026-10-17 ~28 min staging |

| Uitslag (46) | Aantal |
| --- | ---: |
| goed | 7 |
| fout | 26 |
| review | 4 |
| niet gedraaid | 9 |

*Niet gedraaid:* 8× compressie (C01–C08) + V01.

**Valkuil (18) — drie getallen:**

| | Aantal | IDs (indicatief) |
| --- | ---: | --- |
| Niet gedraaid | 4 | V01, C02, C03, C06 |
| Gedraaid, goed | 2 | T09, T12 |
| Gedraaid, fout | 10 | o.a. A09–A13, A15, T04, T05, T13, T14 |
| Gedraaid, review | 2 | S04, S05 |

A15 **draait** nu (geen harde poort); in deze run **fout** t.o.v. verwachting — geen mapping-fix zonder productbesluit (zie koppeldoc 7.6/7.7, nu ingevuld).

*Niet vergelijken* met v0.2-nulmeting op v1.10.6 (andere poort, prompt, hoofdoordeel).

### 4.2 5×19 stabiliteit (testmerken)

| | |
| --- | --- |
| **Artifact** | `docs/hba-testmerken-stability-v1.10.8-5runs.json` |

| Uitslag | Aantal |
| --- | ---: |
| stable | 11 |
| instabiel | 3 |
| review | 3 |
| fout | 2 |

**Stap-2 criterium:** `hoofdoordeel_gecorrigeerd` **18,9%** — doel **≤ 5%** **niet gehaald** (prompt vs PHP-filter nog uit elkaar).

**Controlepunten (Rogier):** T10 → `categorietaal`; T13 vs Duinroos post 7 → zelfde oordeel — zie `docs/hba-t13-vs-duinroos-7.md` (narratief verschilt; geen normalisatie gebouwd).

---

## 5. A6 — klanttaal-screenshots (voor Rogier)

Rogier vroeg screenshots van **alle schermen die een pilotklant ziet**, om de rijen in `docs/a6-klanttaal-inventaris-voorstel.md` in één keer goed te keuren.

| | |
| --- | --- |
| **Tenant staging** | **CF_Staging** (koffiebranderij-testmerk) |
| **Datum shots** | 17 oktober 2026 |
| **Index** | `docs/a6-screenshots-staging-2026-10-17/00-LEESMIJ.md` |

### 5.1 Checklist ↔ screenshot

| # | Scherm | Bestand | Opmerking voor Rogier |
| --- | --- | --- | --- |
| 1 | Overzicht | `01-overzicht.png` | Geen archetype-%; NL navigatie |
| 2 | Merkarchitectuur | `02-merkarchitectuur.png` | Nog “Je persoonlijkheid: Maker”; upload noemt HBA; Save/Rewrite EN |
| 3 | Merkelementen | `03-merkelementen.png` | Link **Merkarchitectuur**; geen CE-03 |
| 4 | Thema’s | `04-themas.png` | Week 42 **zonder** LLM-voorstellen → advieszin *Past dit onderwerp bij je merk?* **niet zichtbaar** |
| 5 | Onderzoek | `05-onderzoek.png` | **Advies voor je onderzoek** wel zichtbaar |
| 6 | Artikel (meta) | `06-artikel-meta.png` | Zijpaneel Merkarchitectuur / aandachtspunten |
| 7 | Terugkoppeling | `07-feedback-terugkoppeling.png` | Geen open CE-04-regels → knop **Verwijderen** niet in beeld |
| 8 | Vindbaarheid | `08-vindbaarheid.png` | Geen Content Engine / SerpAPI; nog “+ Add question” EN |
| 9 | Planning | `09-planning.png` | NL copy Rapport; Save/Undo EN |
| 10 | Rapport | `10-rapport.png` | **Rapport** in nav; geen “Report” |
| 11 | Agenda | `11-agenda-signalen.png` | Lijstweergave; link “kies een theme” (EN) |

### 5.2 Screenshots (volledige pagina)

#### 1 — Overzicht

![Overzicht — staging CF_Staging](a6-screenshots-staging-2026-10-17/01-overzicht.png)

#### 2 — Merkarchitectuur

![Merkarchitectuur](a6-screenshots-staging-2026-10-17/02-merkarchitectuur.png)

#### 3 — Merkelementen

![Merkelementen](a6-screenshots-staging-2026-10-17/03-merkelementen.png)

#### 4 — Thema’s

![Thema's week 42](a6-screenshots-staging-2026-10-17/04-themas.png)

#### 5 — Onderzoek

![Onderzoek](a6-screenshots-staging-2026-10-17/05-onderzoek.png)

#### 6 — Artikel (meta / Details)

![Artikel met zijpaneel](a6-screenshots-staging-2026-10-17/06-artikel-meta.png)

#### 7 — Terugkoppeling (feedback)

![Terugkoppeling](a6-screenshots-staging-2026-10-17/07-feedback-terugkoppeling.png)

#### 8 — Vindbaarheid

![Vindbaarheid](a6-screenshots-staging-2026-10-17/08-vindbaarheid.png)

#### 9 — Planning

![Planning](a6-screenshots-staging-2026-10-17/09-planning.png)

#### 10 — Rapport

![Rapport](a6-screenshots-staging-2026-10-17/10-rapport.png)

#### 11 — Agenda (lijst)

![Agenda lijstweergave](a6-screenshots-staging-2026-10-17/11-agenda-signalen.png)

### 5.3 Wat we van Rogier vragen (A6)

1. Akkoord per rij in `docs/a6-klanttaal-inventaris-voorstel.md` (vinkjes).
2. Of **“Je persoonlijkheid: Maker”** acceptabel is vs alleen merkdoel / geen archetype-label.
3. Of rest-EN op deze shots vóór pilot moet (Save, Add question, Enter title, …) — lijst in `00-LEESMIJ.md`.

Optioneel later: screenshot **04** opnieuw na **Genereer thema-voorstellen**; **07** op artikel met open CE-04-regels (archief HBA-tests) voor knop **Verwijderen**.

---

## 6. Open na dit rapport

| Onderwerp | Actie |
| --- | --- |
| **A6** | Rogier beoordelen (§5) |
| **T13 / Duinroos 7** | Rogier reactie op `docs/hba-t13-vs-duinroos-7.md` — geen bouw zonder besluit |
| **hoofdoordeel_gecorrigeerd** | 18,9% — prompt/PHP-sync; geen prompt-wijziging zonder afspraak (pilot freeze) |
| **Ijkrun fouten** | Inhoudelijke kalibratie / verwachtingen v0.3 — geen seed-fix |
| **Stap-5-artefacten** | JSON/logs v1.10.8 in repo committen indien gewenst |
| **V01, compressie** | Aparte routes; niet in batch |
| **Productie** | Expliciete ja Erik + Rogier |

---

## 7. Bijlagen (bestanden)

| Document | Rol |
| --- | --- |
| `docs/hba-ijkset-koppeling-v0.3.md` | Koppeling ijkset ↔ app; 7.1–7.7 |
| `docs/hba-ijkrun-nulmeting-v1.10.8-samenvatting.md` | Ijkrun telling |
| `docs/hba-testmerken-stability-v1.10.8-5runs.json` | 5×19 |
| `docs/hba-t13-vs-duinroos-7.md` | Stap 6 onderzoek |
| `docs/hba-v1.10-stap4-klanttaal.md` | A6-specificatie |
| `docs/a6-klanttaal-inventaris-voorstel.md` | Rijen voor Rogier |
| `docs/a6-screenshots-staging-2026-10-17/` | PNG’s + `00-LEESMIJ.md` |

*Einde bouwrapport.*
