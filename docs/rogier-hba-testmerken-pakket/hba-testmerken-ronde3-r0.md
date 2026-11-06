# HBA testmerken — ronde 3 R0 (afronding ronde 2)

**Datum:** 10 oktober 2026  
**Promptversie na ronde 3:** `v1.7`

## 1. Stap 0 — CE-04

**Oorzaak:** Build Feedback Rows las tool-input alleen als `content[0].input`; Anthropic levert `tool_use` met `input` op dat block.

**Herstel:** robuuste parser in `scripts/generate-ce04-workflow.py` + workflow opnieuw gegenereerd. Zie `docs/hba-testmerken-ce04-stap0.md`.

**Staging opnieuw testen:** `./scripts/run-hba-r0-ce04-post3-staging.sh [wait_seconds]` → `GET /api/admin/hba-ce04-post3?wait=45` (reset-token, na deploy). Optioneel `?dry=1` voor slot-ids. Resultaat hieronder plakken na run.

| Merk | CE-04 OK | Regel in feedback? | Notitie |
| --- | --- | --- | --- |
| Brandpunt | Ja (webhook 200) | **Nee** | `[CE-04] Review mislukt: tool-output niet gelezen` — zie `docs/hba-v0-testresultaat.md` (12 okt, na n8n push) |
| Stelwerk | Ja | **Nee** | idem |
| Duinroos | Ja | **Nee** | idem |

## 2. T2 — `foe_vervaging`

Geen wezenlijk verschil tussen v1.5 (alleen enum) en ronde-2 FLAGS-tekst; sluit aan op “tegenkracht-redenering overnemen”. Zie `docs/hba-testmerken-ronde2-verslag.md`.

## 3. T4 — wizardvoorstel

Zelfde archetype = markt-archetype: narrative pas af wanneer geloofwaardigheidstest + meetbaar openbaar bewijsveld. Raakt o.a. `ce_brand_narrative_completeness`, HBA identity gate, merkarchitectuur-wizard. Niet gebouwd. Zie ronde-2 verslag.

## 4. T5 — merkcijfer /10

Aangepast: `ce_hba_v2_post_positioning_score()` deelt door **10** (BC, consistency, risk-aftrek). Niet gewijzigd: `ce_hba_v2_round_pbs_display()` (afronding naar stappen van 5).

**Merkcijfer vóór/na op staging:** niet numeriek vastgelegd in ronde 2 — invullen na expliciete meting op de drie testmerken.

## 5. Schaal `distinctiviteit`

In de scoreprompt is **distinctiviteit een subdimensie van brand contribution**, schaal **0–5** (`bc_distinctiviteit` / tool `dimensions.distinctiviteit`). Het totaal **brand_contribution** loopt **0–10**. Daarom lijken sterke posts “niet boven 3–4” op distinctiviteit — dat is normaal op een 0–5-schaal, geen 0–10.

## 6. Duinroos post 6 — `constraint_breach` run A

In `docs/hba-testmerken-resultaat.md` (run A→B): post 6 had in run A **`constraint_breach`** naast `foe_vervaging` en `category_fallback`; in run B viel `constraint_breach` weg.

**Citaat in opgeslagen LLM-data:** niet meer beschikbaar in de gecommitte matrix-JSON (alleen flags). Vermoedelijke zin uit de post: *“Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.”* — efficiëntie-aspect, geen regelbreuk volgens verwachting.

## 7. R6 — stabiliteit draaien (hosting)

De admin-endpoint `hba-testmerken-stability` scoort alle posts in één HTTP-request en loopt daarmee tegen de **~90s connection timeout** van staging aan. **Werkende aanpak:** `scripts/run-hba-testmerken-stability-runs.py` (via `./scripts/run-hba-testmerken-matrix.sh`, standaard **5 runs**) — één score-request per post, daarna matrix-snapshot per run. `flag_citaten` worden vanaf v1.7 in `raw_llm_response` bewaard voor citaten in instabiele runs.
