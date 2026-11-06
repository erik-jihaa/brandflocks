# HBA ronde 6 — stap 9: pilotversie v1.11 en eindmeting

**Datum eindmeting:** 2026-10-18 (staging)  
**Branch:** `hba-ronde-6-pilotklaar`  
**Briefing:** `briefing/hba-input/hba-ronde-6-pilotklaar.md` (stap 9 + klaar-lijst)

---

## 1. Pilotversie (bevriezing)

| Veld | Waarde |
| --- | --- |
| **Versienummer** | **v1.11** (`CE_HBA_PROMPT_VERSION` in `web/api/hba-prompt.php`) |
| **Git-anker (stap 1–5)** | **`b9c8e03`** — *HBA ronde 6 stap 5: staging-screenshots A6 en CE-04-feedback zichtbaar* |
| **Eerste commit stap 1** | `a7f1213` (hoofdoordeel uit flags) |
| **Stap 6–8** | Meting en gereedschap op v1.11; **geen** wijziging aan scoringprompt of hoofdoordeel-logica (stap 7 = runner/ijkset; stap 8 = stroomtest-admin) |

### Bevriezingsregel (Rogier, antwoord op open vraag)

Komt uit stap 6, 7 of 8 een fout die **scoring** raakt, dan verandert de pilotversie en moet de **5 × 19** opnieuw. Een fout die alleen **weergave** raakt, niet.

**Status na stap 9:** v1.11 is **vastgelegd** als pilotversie; de **klaar-criteria** uit de briefing zijn **niet allemaal gehaald** (zie §3). Bevriezing in productiezin = pas na akkoord Rogier op de open punten.

---

## 2. Gedraaide eindmeting

| Meting | Wanneer | Artefact |
| --- | --- | --- |
| **5 × 19** testmerken | 2026-10-18 ~42 min | `hba-testmerken-stability-v1.11-5runs.json`, `hba-testmerken-stabiliteit.md`, log `hba-v1.11-stap9-5x19-run.log` |
| **Ijkrun** (uitsplitsing stap 6) | 2026-10-18 (hergebruik) | `hba-ijkrun-nulmeting-v1.11.json`, `hba-v1.11-stap6-ijkrun-uitsplitsing.md` |
| **CE-04** post 3 (regel-feedback) | 2026-10-18 | `hba-v1.11-stap9-ce04-post3.json` |
| **V0** (CE-03 + self_review + hints) | 2026-10-18 | `hba-v1.11-stap9-v0-testresultaat.json`, log `hba-v1.11-stap9-v0-run.log` |
| **Stroomtest Brandpunt** (stap 8) | 2026-10-18 | `hba-v1.11-stap8-stroomtest-brandpunt.md`, checkpoints + screenshots |

**Herhaalbaar:** `./scripts/run-hba-pilot-eindmeting-staging.sh` (5×19 + CE-04 + V0; ijkrun apart via stap-6-runner).

---

## 3. Resultaten vs klaar-criteria (pilot)

Legenda: ✅ gehaald · ⚠️ deels / akkoord Rogier nodig · ❌ niet gehaald.

| Criterium | Uitslag | Toelichting |
| --- | --- | --- |
| 5×19: ≥16 posts 5/5 goed | ❌ | **12/19** stable goed (doel ≥16). Zie `hba-testmerken-stabiliteit.md`. |
| Geen instabiele posts | ⚠️ | **1** instabiel (Stelwerk branchetaal 1/5). Daarnaast **7 posts 0/5 fout** (consistent verkeerd oordeel, geen spreiding tussen runs). |
| BC-spreiding ≤ 1 | ✅ | Alle posts ≤ 1 in 5×19-run. |
| Hoofdoordeel uit flags 100% | ✅ | `hoofdoordeel_gecorrigeerd`: **0%** (0/95 scores). |
| Hints/adviezen regel letterlijk | ⚠️ | Stap 2 meting gestopt na 0/5 week 41; V0-hints op staging 2026-10-18 wel regelconform in CE-04/V0-samples. |
| Geen verzonnen feiten (stap 3) | ⚠️ | Week 41 rewrite op staging; geen volledige 5×19-post opnieuw gemeten in stap 9. |
| Thema-advies zichtbaar (stap 4) | ✅ | Gebouwd + screenshots stap 4/5; V0 `themeConflict`/`themePremisse` gevuld voor verleidingsslots. |
| Klanttaal A6 + Rogier-akkoord | ⚠️ | Screenshots onder `docs/a6-screenshots-staging-2026-10-18/` (Rogier-pakket: `screenshots-a6/`); **goedkeuring Rogier** open. |
| Stroomtest gedraaid | ✅ | Stap 8 op tenant Brandpunt Koffie Stroomtest; afwijkingen t.o.v. verwachting gedocumenteerd. |
| Ijkrun uitgesplitst per as | ✅ | Stap 6: 26 fout / 6 goed / 5 review; valkuilen niet 100% (pilot OK volgens briefing). |

### 5×19 — kernproblemen (v1.11, staging)

- **Neutraal / grensgeval** (3 merken): 0/5 — model zet `category_fallback` op kop/opening → hoofdoordeel **categorietaal** i.p.v. verwacht **neutraal** (consistent, geen run-spreiding).
- **Brandpunt — Mechanisme markthygiëne:** 0/5 categorietaal (verwacht anders).
- **Duinroos — Kernbewering zonder bewijs (V0):** 0/5 mix op_merk/tegenkracht.
- **Stelwerk — Branchetaal:** 1/5 instabiel (één run tegenkracht).
- **Stelwerk — Samenwerking zonder bewijs:** 0/5 op_merk (verwacht review/fout).

Stap-1-specifieke spotchecks uit briefing (niet allemaal in huidige matrix-labels): o.a. Stelwerk post 5 categorietaal en Duinroos post 7 tegenkracht — zie rijen in stabiliteitstabel.

### Ijkrun v1.11 (hergebruik stap 6)

- **26 fout**, 6 goed, 5 review, 9 niet gedraaid.
- Primair per as: signaal 15, oordeel 8, meetlat 3.
- Detail: `hba-v1.11-stap6-ijkrun-uitsplitsing.md`.

### CE-04 post 3

- **3/3** merken: `regelFeedbackOk: true`, regel-feedback met **Verwijderen** en letterlijke regel in comment (Brandpunt, Duinroos, Stelwerk).
- JSON: `hba-v1.11-stap9-ce04-post3.json`.

### V0 (staging 2026-10-18)

| Merk | self_review | hbaHint | hbaScore |
| --- | --- | --- | --- |
| Brandpunt (Black Friday-slot) | ✅ themeConflict + premisse | ✅ invulvelden + CTA | op_merk, proof_gap |
| Stelwerk (ERP-partnerschap fastlane) | ✅ | ❌ hint-trigger: geen aanwijzing | op_merk |
| Duinroos (wachtlijst fastlane) | ✅ | ✅ invulvelden | **404** op `/api/hba/score` (article net aangemaakt?) |

CE-04-trigger in hetzelfde bestand: 3× `ok: true` voor regelbreuk-slots.

---

## 4. Conclusie voor Rogier

1. **Pilotversie = v1.11**, code-anker **`b9c8e03`** (stap 1–5); metingen stap 6–9 op dezelfde promptversie op staging.
2. **Eindmeting is compleet gedraaid**, maar de briefing **“Klaar voor de pilot”**-checkbox **5×19 ≥16** is **niet** gehaald (12/19). Hoofdoordeel-stabiliteit via code is wel OK (0% gecorrigeerd).
3. **Aanbevolen vervolg:** besluit over neutraal/grensgeval vs `category_fallback`-prioriteit; Rogier-review A6-screenshots + stroomtest; eventueel tweede 5×19-poging na scoring-fix (max 2 pogingen volgens briefing).

Geen staging-deploy en geen productie in deze stap tenzij expliciet **“ja staging”**.
