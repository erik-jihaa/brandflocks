# HBA v1.8 — verslag voor Rogier (staging)

**Datum:** 12 oktober 2026  
**Omgeving:** https://staging.igisnext.com/ (branch `feature/overview-report-15`)  
**Scoring-model:** `claude-sonnet-4-6` (ongewijzigd)  
**Prompt:** `v1.8` (V1–V4 + matrix); **`v1.8.1`** vanaf advies-prompt (V5.2–V5.4)  
**Opdracht:** `briefing/hba-input/hba-v1.8-antwoorden-rogier.md`

Dit document hoort bij het mapje **`docs/rogier-hba-testmerken-pakket/`** (zip: `docs/rogier-hba-testmerken-pakket-2026-10-12.zip`).

---

## 1. Executive summary

| Onderdeel | Status |
| --- | --- |
| **V1–V3** (prompt + PHP hoofdoordeel + matrix) | Op staging; 5×17 gedraaid op **v1.8** |
| **V0** (CE-04 + verleiding) | **CE-04 nog open**; verleiding deels (A3 gate, geen self review/hint) |
| **V4** (klanttaal + contentrol verbergen) | UI + API op staging; inventaris met ✓ |
| **V5** (klantpreview + advies) | **17/17 her-scored op v1.8.1** (12 okt); klantpreview toont nieuw advies |
| **Klaar-criteria matrix** | **v1.8.1 (5×17):** Stelwerk post 2 + Duinroos post 6 nog niet 5/5 (zie §3–§4) |

**Geen extra prompt-tuning** buiten v1.8-opdracht — open punt Stelwerk post 2 blijft beslispunt (zoals v1.7).

---

## 2. V0 — CE-04 en verleiding

Zie **`hba-v0-testresultaat.md`** + **`hba-v0-testresultaat.json`**.

| Test | Resultaat |
| --- | --- |
| **CE-04 post 3** (3 merken) | Webhook 200, feedback blijft *tool-output niet gelezen* — **geen regelzin in app** |
| **Verleiding fastlane** | Lege research → CE-03 geblokkeerd (A3); met bronnen schrijft CE-03 door; **self_review / HBA-hint leeg**; scorer achteraf wel scherp (regelbreuk) |

**Gate v1.8:** V0 CE-04 was expliciet vóór promptwerk; promptwerk is wel uitgevoerd op verzoek Erik — productiebesluit CE-04 blijft open.

---

## 3. Stabiliteit v1.7 → v1.8 (5×17)

| Metric | v1.7 (10 okt) | v1.8 (12 okt) |
| --- | ---: | ---: |
| Stable goed (5/5) | 9 / 17 | **12 / 17** |
| Instabiel | 3 | **2** |
| Fout | 2 | **0** |
| Review | 3 | **3** |
| `category_fallback` (laatste run) | 10 / 17 | **8 / 17** |
| `hoofdoordeel_gecorrigeerd` | 25 hits | **0** (0,0% van 85 scores) |
| Flags op functionele onderdelen | — | **0** |

**Bron v1.7:** `stabiliteit-v1.7-5runs.json` (pakket)  
**Bron v1.8:** `stabiliteit-v1.8-5runs.json` + `stabiliteit-v1.8.md` (pakket)  
**Bron v1.8.1:** `stabiliteit-v1.8.1-5runs.json` + `stabiliteit-v1.8.1.md` — **12 okt 2026, 5×17 op staging**

| Metric | v1.8 (12 okt) | v1.8.1 (12 okt, na advies-prompt) |
| --- | ---: | ---: |
| Stable goed (5/5) | 12 / 17 | **12 / 17** |
| Instabiel | 2 | **2** (Duinroos post 6, Stelwerk post 2) |
| Fout | 0 | **0** |
| Review | 3 | **3** |
| `hoofdoordeel_gecorrigeerd` | 0% | **0%** |

Stelwerk post 2 blijft **3/5** (runs 1+3: `regelbreuk` i.p.v. `categorietaal`). Duinroos post 6: **3/5** (runs 3+5: `regelbreuk` + `constraint_breach`).

### Klaar-criteria (v1.8 run)

- [x] Post 3, alle merken — `regelbreuk` 5/5  
- [ ] **Post 2, Stelwerk** — `categorietaal` **3/5** (runs 3+5: `regelbreuk` + `constraint_breach` op partnerzin)  
- [x] Post 2, Brandpunt + Duinroos  
- [x] Post 1, alle merken  
- [x] Post 4, alle merken (review-posts blijven review)  
- [x] Brandpunt post 5, Duinroos post 6  
- [x] BC-spreiding ≤ 1  
- [x] `hoofdoordeel_gecorrigeerd` ≤ 5%  
- [x] Flags op functionele onderdelen = 0  

### PHP-correctie hoofdoordeel (V1)

In v1.8-stabiliteit: **geen** `hoofdoordeel_gecorrigeerd` in de 5×17-run.  
V5.5 (Duinroos vacature/bloemen): klantzin volgt opgeslagen `hoofdoordeel` in score-DTO — na her-score controleren via klantpreview.

---

## 4. V4 — Klanttaal (A6) en contentrollen (A8)

| Item | Staging |
| --- | --- |
| **A6** | Zie `a6-klanttaal-inventaris-voorstel.md` (rijen met ✓); archief-testnotities `Verwacht:` verborgen voor **alle** tenantrollen |
| **A8** | HBA-contentrol verborgen voor ingelogde klant (API + home/report + artikel); planning-rol (ritme) blijft |

**Screenshots:** nog door Erik — checklist in §8.

---

## 5. V5 — Klantweergave

### 5.1 Preview-links (17 posts)

**`hba-r5-klantpreview-urls-staging.md`** — `/article/{articleId}?hbaKlant=1` (article-id, niet slot-id).  
Werkt ook voor gearchiveerde posts; query `hbaKlant=1` voorkomt redirect naar archief zonder HBA.

Hergenereren: `./scripts/list-hba-r5-klantpreview-urls.sh`

### 5.2 Verbeteradvies (v1.8.1)

Fragment: **`hba-verbeteradvies-definitie.txt`** (pakket + `web/api/prompts/`).

- Klanttaal, geen vaktermen in advies  
- `op_merk`: geen advies of max. één korte zin  
- Regel niet verkeerd parafraseren (D2)  
- Logging: `verbeteradvies_verboden_termen` in raw score + `event_log` (`hba` / `verbeteradvies_verboden_termen`)

### 5.3 Her-score v1.8.1 (12 okt, staging)

Batch: `./scripts/run-hba-testmerken-stability-runs.py … 1` — **17 posts**, ~6,5 min, model `claude-sonnet-4-6`.

| Check | Resultaat |
| --- | --- |
| `prompt_version` | **v1.8.1** op alle 17 |
| `event_log` `verbeteradvies_verboden_termen` | **0** tijdens run |
| Matrix (1×) | **13 goed**, **3 review**, **1 fout** |
| `hoofdoordeel_gecorrigeerd` | **0** |

**Enige fout (1× run, geen 5×):** Duinroos — *Logica van de tegenkracht* → hoofdoordeel `regelbreuk` + `constraint_breach` (verwacht `tegenkracht`). Steekproef klanttaal: open via `?hbaKlant=1` op die URL.

**5×17 v1.8.1** (zelfde dag, na bovenstaande batch): Stelwerk post 2 **3/5**, Duinroos post 6 **3/5** — zie **`stabiliteit-v1.8.1.md`**.

Ruwe data: **`hba-v181-rescore-staging.json`**, **`hba-testmerken-stability-v1.8.1-5runs.json`**, matrix: **`hba-testmerken-matrix-v1.8.1.json`**.

---

## 6. Wat Rogier kan beslissen

1. **Stelwerk post 2** — **3/5** op v1.8 én v1.8.1; beslispunt **`hba-kalibratie-beslispunten-roger-v1.8.md`**.  
2. **Duinroos post 6** — **3/5** (2 runs `regelbreuk`); kalibratie vs LLM-variance.  
3. **A6** — akkoord per regel inventaris vóór productie-copy.  
4. **V0 CE-04** — prioriteit vóór productie (n8n Build Feedback Rows).

---

## 7. Bestanden in het pakket

| Bestand | Inhoud |
| --- | --- |
| `hba-v1.8-verslag-roger.md` | Dit document |
| `stabiliteit-v1.8.md` / `stabiliteit-v1.8-5runs.json` | 5×17 v1.8 (prompt) |
| `stabiliteit-v1.8.1.md` / `stabiliteit-v1.8.1-5runs.json` | 5×17 v1.8.1 (advies) |
| `stabiliteit-v1.7-5runs.json` | Vergelijking v1.7 |
| `hba-v0-testresultaat.md` | CE-04 + verleiding |
| `hba-r5-klantpreview-urls-staging.md` | 17 URLs |
| `a6-klanttaal-inventaris-voorstel.md` | Klanttaal |
| `hba-a8-contentrollen-relatie.md` | Planning vs HBA-rol |
| `hba-kalibratie-beslispunten-roger-v1.8.md` | Beslispunten vóór v1.8 |
| `hba-verbeteradvies-definitie.txt` | Nieuw fragment v1.8.1 |
| `hba-v181-rescore-staging.json` | 1×17 her-score v1.8.1 |
| `hba-testmerken-matrix-v1.8.1.json` | Matrix na her-score |
| `hba-hoofdoordeel-definitie.txt`, flags, regelbreuk | Promptfragmenten |
| Test-JSON (3 merken) | Verwachtingen matrix |

Vernieuwen: `./scripts/build-rogier-hba-pakket.sh`

---

## 8. Screenshot-checklist (Erik → Rogier)

Niet in repo; handmatig op staging ingelogd als test-tenant.

- [ ] Merkarchitectuur — “Merkcontrole” i.p.v. HBA-scoring  
- [ ] Thema’s — “Waarom dit thema”, geen HBA-patroon in toelichting  
- [ ] Artikel — geen “Beoordeeld als” contentrol voor klant  
- [ ] **17×** klantpreview (`?hbaKlant=1`) — klantzin + tip (waar van toepassing)  
- [ ] Archief — geen `Verwacht:` testnotitie zichtbaar  
