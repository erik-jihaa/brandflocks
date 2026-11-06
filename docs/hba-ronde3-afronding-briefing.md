# HBA ronde 3 — afronding volledige briefing

**Opdracht:** `briefing/hba-input/cursor-opdracht-ronde-3-volledig.md`  
**Datum status:** 10 oktober 2026  
**Prompt:** `v1.7` · **Model scores:** `claude-sonnet-4-6`

Legenda: ✅ afgerond · ⚠️ deels / wacht op Erik · 📋 alleen melding (conform opdracht) · ❌ klaar-criterium niet gehaald

---

## Deel 1 — R0 t/m R6

| Stap | Status | Bewijs / opmerking |
| --- | --- | --- |
| **R0** CE-04 oorzaak + herstel | ✅ / ⚠️ | Parser-fix CE-04: `docs/hba-testmerken-ce04-stap0.md`, `generate-ce04-workflow.py`. **Hertest post 3:** `./scripts/run-hba-r0-ce04-post3-staging.sh` → `GET /api/admin/hba-ce04-post3` (na deploy). |
| **R0** T2 foe_vervaging | ✅ | `docs/hba-testmerken-ronde3-r0.md` |
| **R0** T4 wizard | 📋 | Niet gebouwd; ronde-2 verslag |
| **R0** T5 merkcijfer /10 | ✅ | `ce_hba_v2_post_positioning_score()` /10 |
| **R0** distinctiviteit schaal | ✅ | Subdimensie 0–5; BC 0–10 — `ronde3-r0.md` |
| **R0** Duinroos post 6 citaat run A | ✅ | Vermoedelijke zin gedocumenteerd; niet in JSON |
| **R1** Matrix zonder post-1-vergelijking | ✅ | `hba-testmerken-repository.php` |
| **R2** Categorietaal + branddatum retail | ✅ | `hba-flags-definitie.txt`, branche hygiëne |
| **R3** Hoofdoordeel + PHP-validatie | ✅ | prompts + matrix kolommen |
| **R4** 17 posts + JSON | ✅ | `HBA_testmerken/`, import staging |
| **R5** Klantpreview `?hbaKlant=1` | ✅ / ⚠️ | `ArticleHbaSection.tsx`. **Screenshots:** `./scripts/list-hba-r5-klantpreview-urls.sh` → Erik handmatig |
| **R6** 5 runs + stabiliteit | ✅ | `run-hba-testmerken-matrix.sh`, 10 okt 17:50 UTC |
| **Testprotocol 5×17** | ✅ | `hba-testmerken-stabiliteit.md`, JSON v1.7 |
| **Klaar-criteria** | ❌ | **Stelwerk post 2** niet 5/5; zie `hba-kalibratie-beslispunten-roger-v1.8.md` (geen zelf-tuning) |

**category_fallback:** 10/17 laatste run (was 13/16 v1.6). **hoofdoordeel_gecorrigeerd:** 25.

---

## Deel 2 — A1 t/m A9, D1 t/m D4

| Stap | Status | Bewijs / opmerking |
| --- | --- | --- |
| **A1** Merkrapport + HBA | ✅ | CE-07, `report-repository.php`; rapporten 10 okt staging |
| **A2** Merkarchitectuur provisioning | ✅ | `hba-testmerken-repository.php`; herprovision script |
| **A3** Fastlane + CE-02 | ✅ code / ⚠️ test | `generate-ce02-workflow.py`. **Hertest Black Friday-slots + verleidingstest:** Erik op staging |
| **A4** Thema zonder HBA-testtitels | ✅ | CE-01 SQL exclude baseline + `NOT LIKE HBA-test:%` |
| **A5** Ritmrol-prefix | ✅ | CE-01 `withRhythmPrefix` |
| **A6** Klanttaal | ✅ inventaris / ⚠️ UI | `docs/a6-klanttaal-inventaris-voorstel.md`. **Verbergen testnotities:** `ce_redact_archive_note_for_tenant()` — overige UI wacht op Rogier |
| **A7** B2B-tone provisioning | ✅ | consument → `b to c` eerst; rapport-B2B deels platform/tone |
| **A8** Twee contentrollen | 📋 | `docs/hba-a8-contentrollen-relatie.md` |
| **A9** Alertniveau | 📋 | Niet gebouwd in Instellingen |
| **D1** Duinroos rapport | ✅ | Meegenomen in A1-rapportprompt |
| **D2** Thema zwaardere bewijslast | ✅ | `hba-v2-prompts.php`, week 41 Duinroos vernieuwd |
| **D3** Advies vooraf | 📋 | Referentie ijkset — werkt goed |
| **D4** Ritme 5/1 | ✅ fix | HBA-test/baseline posts tellen niet meer mee in ritmemeter (`home-repository.php`) |

---

## Afronden (briefing §397)

| Check | Status |
| --- | --- |
| `php -l` / `py_compile` / `npm run build` | ⚠️ bij deploy-run |
| Verslag aan Erik | **dit document** + `hba-ronde3-deel2-verslag.md` |
| Rogier-pakket | `docs/rogier-hba-testmerken-pakket/` + `./scripts/build-rogier-hba-pakket.sh` |
| v1.8 prompt | **Wacht op Rogier** — `hba-kalibratie-beslispunten-roger-v1.8.md` |

---

## Nog handmatig (Erik)

1. **Deploy** staging (home D4, archive A6, admin CE-04 endpoint) als nog niet live.
2. **R0:** `./scripts/run-hba-r0-ce04-post3-staging.sh 60` — feedbackregel in verslag plakken.
3. **R5:** screenshots 17 URLs uit `list-hba-r5-klantpreview-urls.sh`.
4. **A3:** Black Friday fastlane hertest + CE-03 verleiding (3 merken).
5. **A2:** screenshots Merkarchitectuur (3 testmerken) indien nog niet in map.
6. **Rogier:** beslispunten v1.8 — daarna pas prompt ronde 4.

---

## Scripts (repo)

| Script | Doel |
| --- | --- |
| `./scripts/run-hba-testmerken-matrix.sh` | 5×17 stabiliteit |
| `./scripts/run-hba-r0-ce04-post3-staging.sh` | CE-04 post 3 |
| `./scripts/list-hba-r5-klantpreview-urls.sh` | R5 URL-lijst |
| `./scripts/build-rogier-hba-pakket.sh` | Pakket Rogier |
