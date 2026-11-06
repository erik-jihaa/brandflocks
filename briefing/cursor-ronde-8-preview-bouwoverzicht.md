# Bouwoverzicht — Ronde 8 preview (P1–P5)

**Datum:** 27 oktober 2026  
**Leidend:** `briefing/hba-input/Voor Erik 2026-10-27/ronde-8-previewscope-2026-10-27.md`  
**Volledige visie:** `briefing/hba-input/Voor Erik 2026-10-27/bouwaddendum-ronde-8-strategieen-campagnes-geo-2026-10-27.md`  
**Codeonderzoek:** `briefing/reactie-rogier-bouwaddendum-ronde-8-codereview.md`  

**Doel:** bureau-previews — campagnes denken, merk-check op plan, lichte GEO-ankers. **Geen** v1.13-scoring wijzigen.

---

## 1. Scope preview vs later

| Preview (bouwen) | Later (na previews) |
|------------------|---------------------|
| P1 Campagne + N slots/agenda | Sjablonen C–F, feitenbank 10+10, Hero/Hub/Help |
| P2 Sjablonen A + B | Campagneverslag, GEO voor/na |
| P3 Productpagina-extract (eigen prompt) | Driftmeter stap A/B + GEO prompt C (één keer, gedeeld) |
| P4 Plan-check (batch) + 2 rekenregels | Volledige `ce_hba_plan_check` uit addendum §5 |
| P5 1–3 bestaande GEO-vragen als anker | Vragenbank met rotatie, doelpagina, signalen |

**Scoring:** `CE_HBA_PROMPT_VERSION` v1.13 + `ce_hba_resolve_hoofdoordeel` **ongewijzigd**. Campagne-metadata op posts only.

---

## 2. Stappen × model (preview)

| Stap | Wat (kort) | Model | Env / pad | LLM? |
|------|------------|-------|-----------|------|
| **P1** | Campagne + kanalen × aantal → **N slots** in agenda (1 platform/slot) | **—** | DB + PHP planner; geen Anthropic | **Nee** |
| **P2** | Sjablonen **A** (lancering) + **B** (serie); fase-% verdelen over posts | **—** | Config/JSON + planner | **Nee** |
| **P3** | Product-URL → extract JSON (`wat`, `anders`, `bewijs`, `ontbreekt`) | **`claude-sonnet-4-6`** | `HBA_MODEL_CAMPAGNE_PRODUCT_EXTRACT` | **Ja**, ~1× per campagne |
| **P4a** | Plan-check batch (signalen + advies) | **`claude-sonnet-4-6`** | `HBA_MODEL_CAMPAGNE_PLAN_CHECK`, prompt `plan_check_v0.1` | **Ja**, ~1× per plan |
| **P4b** | Balans merk/activatie + regelbreuk-product | **—** | PHP na P4a | **Nee** |
| **P5** | 1–3 bestaande **GEO-vragen** (Vindbaarheid) als anker, ~⅓ posts | **—** | `llm_questions` (kind `geo`) | **Nee** |
| *(bestaand)* | Thema/research-oordeel | **`claude-sonnet-4-6`** | `HBA_MODEL_THEMA_VERDICT` (+ research) | Ja, ongewijzigd |
| *(bestaand)* | Post-score v1.13 | **`claude-sonnet-4-6`** | `HBA_MODEL_CONTENT_SCORE` / `hba-prompt.php` | **Bevroren** |
| *(bestaand)* | CE-03 schrijven | **Sonnet** (huidig n8n) | n8n CE-03; Opus = aparte beslissing | Ja, per post |
| *(optioneel)* | CE-04 review | **Sonnet** (typisch) | n8n | Preview niet verplicht |

**Volgorde bouwen:** P1 → P2 → P3 → P4 → P5 → UI-assistent light.

---

## 3. P1 — start (eerste sprint)

**Doel:** een campagne opslaan en **per kanaal N `content_slots`** aanmaken met vaste `platform` — zichtbaar in agenda. Geen sjablonen, geen LLM.

| # | Taak | Opmerking |
|---|------|-----------|
| 1 | Migratie: `campaigns` uitbreiden (Rogier: `sjabloon`, `doel`, `periode`, `kanalen` JSON, `status` concept/bevestigd/…) | Bestaand: `scripts/migrations/add_campaigns_table.sql` + `end_date` |
| 2 | Slot-generator: input `{ linkedin: 8, instagram: 12, … }` + start/eind → N rijen `content_slots` + `campaign_id` | Geen kanaalvarianten — 1 slot = 1 platform |
| 3 | API: `POST/GET/PATCH` campagne preview (tenant-scoped) | Parallel aan legacy `/campaign` n8n-flow; documenteer verschil |
| 4 | Agenda: slots tonen gegroepeerd op campagne/periode | UI kan minimaal (lijst ok voor sprint 1) |
| 5 | Smoke: 1 campagne Brandpunt → 20 slots in DB | `php scripts/campagne-preview-p1-smoke.php` (DB vereist) |

**Status P1 (27 okt):** branch `ronde-8-preview` — migratie `add_campagne_preview_p1.sql`, API `GET/POST /api/campagne-preview`, `GET/PATCH /api/campagne-preview/:id`, agenda-velden `campaignId` / `platform`. Staging na UI-assistent.

**Klaar als P1:** campagne + 20 slots (8 LI + 12 IG) in staging DB/agenda, zonder fase/opdracht (dat is P2+).

---

## 3b. P2 — sjablonen A/B + fases (27 okt)

| # | Taak | Status |
|---|------|--------|
| 1 | Config `lancering` (6 fases, addendum-aandelen) + `serie` (vraag/antwoord/voorbeeld) | `campagne-preview-templates.php` |
| 2 | Planner: fase-% → postvolgorde; serie `schedule.weekly` + weekday | `ce_campagne_preview_allocate_phases`, `build_slot_schedule` |
| 3 | DB: `phase_plan_json`, `campaign_fase/opdracht/rol` op slots | `add_campagne_preview_p2.sql` |
| 4 | API: `GET /api/campagne-preview/templates`; POST `templateKey`, `phasePlan`, `schedule`; PATCH `phasePlan` + `reapplyPhases` | |
| 5 | Agenda: `campaignFaseLabel`, `campaignOpdracht` | |
| 6 | Selftest | `php scripts/campagne-preview-p2-selftest.php` |

**POST voorbeeld serie (6× dinsdag LI):** `templateKey: "serie"`, `channels: { linkedin: 6 }`, `schedule: { mode: "weekly", weekday: 2 }`.

**Fase weglaten:** `phasePlan: { disabledKeys: ["actie"] }` + PATCH `reapplyPhases: true`.

---

## 3c. P3 — productpagina-extract (27 okt)

| # | Taak | Pad |
|---|------|-----|
| 1 | Pagina ophalen | `ce_hygiene_fetch_page_text` |
| 2 | LLM JSON-extract | `campagne-preview-product-extract.php`, prompt `product_extract_v0.2` + merkarchitectuur (categorietaal/hygiëne), `HBA_MODEL_CAMPAGNE_PRODUCT_EXTRACT` |
| 3 | Opslag op campagne | `product_extract_json`, meta kolommen |
| 4 | Opdracht per slot verrijken | `ce_campagne_preview_apply_product_extract_to_slots` |
| 5 | API | POST create met `productPageUrl` (lancering) of `POST /api/campagne-preview/:id/product-extract` |
| 6 | Selftest (geen LLM) | `php scripts/campagne-preview-p3-selftest.php` |
| 7 | Staging-accept Brandpunt | `only=extract` (los van plan-check, host-timeout ~90s) |

**Status P3 (27 okt):** fixture `web/fixtures/brandpunt-huila-blend.html` live; lokale read via `ce_campagne_product_extract_local_fixture_path` (geen HTTP naar eigen host). Accept `only=extract` **groen**: 20 slots, `productExtractOk`, opdracht bevat `Product (van pagina):`. URL via `HTTP_HOST` (staging `getenv('CE_PUBLIC_BASE_URL')` is leeg).

---

## 3d. P4 — plan-check (27 okt)

| # | Taak | Pad |
|---|------|-----|
| 1 | LLM-batch per plan | `campagne-preview-plan-check.php`, prompt `plan_check_v0.1`, `HBA_MODEL_CAMPAGNE_PLAN_CHECK` |
| 2 | PHP balans merkbouw/activatie | < 60% signaal; lancering mag tijdelijk afwijken |
| 3 | PHP regelbreuk (korting × merkregel) | o.a. Brandpunt: korting op koffie die al weken ligt |
| 4 | PHP `ontbreekt` van P3 | signaal `productpagina` |
| 5 | API | `POST /api/campagne-preview/:id/plan-check` — PHP-regels blijven staan als LLM faalt |
| 6 | Selftest | `php scripts/campagne-preview-p4-selftest.php` |

---

## 3e. P5 — GEO-ankers (27 okt)

| # | Taak | Pad |
|---|------|-----|
| 1 | Kies 1–3 bestaande GEO-vragen | `GET /api/campagne-preview/geo-questions` |
| 2 | ~⅓ posts krijgt anker in opdracht | `ce_campagne_preview_allocate_geo_ankers` (geen rotatie) |
| 3 | Opslag | `geo_anchor_ids` op campagne, `campaign_anker` op slot |
| 4 | API | `POST /api/campagne-preview/:id/ankers` of PATCH `geoQuestionIds` |
| 5 | CE-03 | `campagne_id`, `campagne_fase`, `campagne_opdracht`, `campagne_anker` in webhook-meta |
| 6 | Selftest | `php scripts/campagne-preview-p5-selftest.php` |

---

## 3f. UI-assistent light (27 okt)

| # | Taak | Pad |
|---|------|-----|
| 1 | Route `/campagnes` (+ redirect `/campagne`) + nav **Campagnes** onder Publicatie | `App.tsx`, `SidebarNav.tsx` (`NavKey` `campagne`) |
| 2 | Wizard stappen 1–8 (A/B only) | `CampagnePage.tsx` — Grid #2, bestaande Field/Pill/Pulldown |
| 3 | API-client | `api/campagnePreview.ts` → P1–P5 endpoints |
| 4 | Stap 8: POST create → plan-check → PATCH `bevestigd` → agenda | product-URL optioneel (lancering); GEO 0–3; fases weglaten |
| 5 | Geen C–F, geen scoring, geen feitenbank | Preview-scope |

**Stappen in de UI:** 1 Soort A/B · 2 Onderwerp (+ product-URL bij lancering) · 3 Doel · 4 Merkonderdelen (uit fases) + GEO-ankers · 5 Periode (+ weekdag bij serie) · 6 Kanalen × aantal · 7 Opbouw (fases weglaten) · 8 Plan + check + bevestigen.

**Klaar als UI:** bureau kan een Brandpunt-lancering of 6×-dinsdag-serie aanmaken zonder de API handmatig te posten.

**Status (27 okt):** UI live op staging. Brandpunt-acceptatie: lancering 20 + GEO-ankers + productpagina-extract (fixture Huila-blend); korting regelbreuk; serie 6× dinsdag. Agenda toont campagneperiode. Nav/titel **Campagnes**. Productie niet gedeployed.

---

## 4. Bouwvolgorde (aanbevolen)

```
P1 datamodel + slots → P2 fases/sjablonen A/B → P3 URL-extract → P4 plan-check → P5 ankers → UI assistent (stappen 1–8 light) → acceptatietests Brandpunt
```

Parallel veilig: P5 (alleen lezen `llm_questions`) terwijl P1 loopt.

---

## 5. P1–P5 → code en werk

| ID | Rogier | Bestaande haak | Nieuwe bouw |
|----|--------|----------------|-------------|
| **P1** | Campagne-entiteit, kanalen × aantal, slots in agenda | `campaigns` + `content_slots.campaign_id` (legacy **Campaign planner** — andere UX, geen sjablonen A/B); weekritme `content-rhythm-repository.php` | **Nieuw:** `campagne`-model uit addendum §6 (sjabloon, periode, kanalen[], status) óf uitbreiden `campaigns` + generator die **per kanaal N slots** maakt (`platform` per slot, geen varianten) |
| **P2** | Sjablonen A (lancering) + B (serie), vaste fase-% | Geen fasecyclus (`rhythmRoles.ts` = weekritme, ≠ campagnefases) | JSON/config fases A/B; planner verdeelt posts over periode; fases verschuiven/weglaten |
| **P3** | Product-URL → `wat/anders/bewijs/ontbreekt` | URL-bron in Sources; `source-document-repository.php` (upload) | **Nieuwe** API + prompt (previewscope); fetch paginatekst → JSON; geen driftmeter A/B |
| **P4** | Batch-check plan + balans + regelbreuk-product | `ce_hba_v2_run_theme_verdicts()` ~904 in `hba-v2-repository.php`; prompts `hba-v2-prompts.php` | **`ce_hba_plan_check`** (eigen `prompt_version`, bv. `plan_check_v0.1`); input = geplande posts + fase + opdracht; 2 **PHP**-regels naast LLM |
| **P5** | 1–3 GEO-vragen uit Vindbaarheid | `SearchQuestionsEditor.tsx`, `llm-repository.php` (`question_kind=geo`, max **5** vragen) | Campagne kiest IDs; ~⅓ posts krijgt vraag in `opdracht` naar CE-03; **geen** LLM |
| **✓** | Eigen thema | `ThemePage.tsx` `customDraft` + `createTheme()` | Geen |

**CE-03 / Article:** bestaande schrijf-flow; campagne-post krijgt extra velden (`campagne_id`, `fase`, `opdracht`, `anker`) in webhook-meta (`ce_ce03_webhook_meta_from_slot` uitbreiden).

**Agenda/kalender:** campagneperiode in bestaande agenda (weekregel + fase/kanaal per slot). Geen 1280px-herontwerp (C3 backlog blijft apart).

---

## 6. Gap-lijst (codereview × previewscope)

| # | Codereview (§9) | Previewscope | Actie |
|---|-----------------|--------------|--------|
| 1 | Geen fasecyclus = weekritme | P2 eigen fases | **Nieuw** naast ritme, niet hernoemen |
| 2 | Geen kanaalvarianten | P1 N slots | **Bevestigd** — 1 platform/slot |
| 3 | Theme verdict ≈ plan-check | P4 | **Variant** van `ce_hba_v2_run_theme_verdicts`, andere input/prompt |
| 4 | `customDraft` ok | — | **Klaar** |
| 5 | Bronnen URL/doc ok; extractie ontbreekt | P3 eigen prompt | **Niet** driftmeter A/B in preview |
| 6 | Vindbaarheid = 5 handmatige GEO | P5 alleen kiezen | **Geen** bank/rotatie in preview |
| 7 | Stap A/B + prompt C ontbraken | Nu in zip | **Uitgesteld** tot na preview |
| 8 | Legacy Campaign planner | P1 | **Besluit Erik:** hergebruiken tabel/UI vs nieuwe “Campagnes”-flow Rogier — functioneel overlap, UX verschilt |

**Besluit:** UI-naam **Campagnes** (addendum §9 vraag 7) — nav, paginatitel, route `/campagnes`.

---

## 7. Modeladvies (detail)

**Regels**

1. **v1.13 contentscore** (`CE_HBA_FN_CONTENT_SCORE`, `hba-prompt.php`): blijft **`claude-sonnet-4-6`** — geen model- of promptwijziging voor pilot.
2. **Nieuwe prompts** krijgen **eigen** `prompt_version` + env-sleutel in `hba-model-config.php` (zelfde patroon als `HBA_MODEL_THEMA_VERDICT`).
3. **Geen Fable** in productiepad (`ce_hba_model_reject_fable`).
4. **CE-03 (n8n)** voor preview: **niet verplicht** wijzigen; huidige workflow = meestal Sonnet. Upgrade naar Opus (BUILD-NOTES) is **aparte beslissing** (kosten/latency), geen onderdeel van ronde-8 preview-scope.

| Taak | Functie / pad | Aanbevolen model | Env (voorstel) | Toelichting |
|------|----------------|------------------|----------------|-------------|
| **P3 Productpagina-extract** | Nieuwe PHP-call, JSON-schema | **`claude-sonnet-4-6`** | `HBA_MODEL_CAMPAGNE_PRODUCT_EXTRACT` | Structured extract, geen scoring; goedkoop genoeg per campagne |
| **P4 Plan-check (LLM-deel)** | `ce_hba_plan_check` batch | **`claude-sonnet-4-6`** (start) | `HBA_MODEL_CAMPAGNE_PLAN_CHECK` | Zelfde architectuur als theme verdict; bij twijfel op staging **1× Opus** vergelijken — **niet** contentscore |
| **P4 Rekenregels** | PHP na LLM | — | — | Balans merk/activatie, regelbreuk-signaal — **geen** model |
| **P5 Ankers** | SELECT uit `llm_questions` | — | — | Geen model |
| **Thema/research tijdens campagne** | Bestaand HBA v2 | **`claude-sonnet-4-6`** (default) | `HBA_MODEL_THEMA_VERDICT` / research env | Ongewijzigd t.o.v. pilot |
| **Post-score na schrijven** | v1.13 | **`claude-sonnet-4-6`** | `HBA_MODEL_CONTENT_SCORE` | **Bevroren** |
| **CE-03 schrijven** | n8n CE-03 | **Sonnet (huidig)** of Opus (upgrade) | n8n node / `CE03_*` indien geïntroduceerd | Buiten scoring; beïnvloedt draftkwaliteit |
| **CE-04 review** | n8n | Sonnet (typisch) | — | Optioneel in kosten; preview niet vereist |
| **Later: driftmeter A/B, GEO C** | brandidentitydrift.com | Sonnet (spec) | aparte config per scan | **Niet** in CE-preview; ChatGPT **alleen** GEO antwoordstap (geo-spec) |

**Kostenindicatie preview-campagne (20 posts):** P3 ~1 call, P4 ~1 batch-call, CE-03 ~20 calls — dominant blijft **CE-03** (~€0,10/post Claude-schatting uit `tenant-cost-repository.php`); plan-check + extract << schrijfkosten.

**Implementatie**

- Voeg in `ce_hba_function_model_env_map()` toe: `CE_HBA_FN_CAMPAGNE_PRODUCT_EXTRACT`, `CE_HBA_FN_CAMPAGNE_PLAN_CHECK`.
- Log `model` + `prompt_version` op campagne/plan-check resultaat (zoals `hba_theme_verdicts`).
- Documenteer in `.env.staging.example` na implementatie.

---

## 8. Acceptatie (previewscope)

1. Brandpunt **lancering blend**: 4 weken, LinkedIn 8, Instagram 12 → plan met fase, kanaal, opdracht, anker + plan-check output.  
2. **Zelfde** met regelbreuk-korting → check signaleert regelbreuk.  
3. **Serie** 6× dinsdag LinkedIn (sjabloon B).  
4. Replay v1.13 na elke merge die **scoring** zou raken: `php scripts/hba-v1.13-tegenkracht-diagnose.php` → **19/19**.

---

## 9. Buiten scope (niet starten in preview-sprint)

- Oppakbaarheidsscore addendum (23 okt)  
- Feitenbank 10+10, sjablonen C–F, campagneverslag, GEO-module koppeling  
- Fable, productie-deploy zonder expliciet ja  
- Wijzigingen aan `hba-prompt.php` v1.13  

---

## 10. Documenten in repo

| Pad |
|-----|
| `briefing/hba-input/Voor Erik 2026-10-27/` (uitgepakte zip + `00-LEESMIJ.md`) |
| `briefing/cursor-ronde-8-preview-bouwoverzicht.md` (dit bestand) |
| `briefing/reactie-rogier-bouwaddendum-ronde-8-codereview.md` |
| `briefing/hba-input/bouwaddendum-ronde-8-strategieen-campagnes-geo-2026-10-27.md` (kopie buiten zip) |

**Start:** branch `ronde-8-preview` (voorstel) → **P1** (§3); geen deploy staging zonder expliciet ja.
