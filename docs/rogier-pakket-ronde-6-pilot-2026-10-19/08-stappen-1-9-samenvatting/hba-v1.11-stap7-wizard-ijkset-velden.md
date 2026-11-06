# HBA ronde 6 — stap 7: wizardvelden, ijkset en testmerken

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 7  
Branch: `hba-ronde-6-pilotklaar`

Geen wijziging aan `HBA_ijkset_v0_3.json` (Rogier v0.4).

## Runner (code)

- **`ce_hba_ijk_narrative_from_wizard_fields()`** — ijk-merkarchitectuur → `brand_narratives` via `ce_brand_narrative_from_request()`; velden zonder ijk-bron blijven leeg.
- **`ce_brand_narrative_enrich_scoring_fields()`** — zelfde afleiding als na Opslaan in de wizard (spanning, mechanisme, bewijsvelden uit ingevulde ijk-velden).
- Geen **`ce_hba_testmerk_enrich_narrative_fields()`** (geen generieke woorden/voorbeelden/stubs).
- **`ce_hba_ijk_prepare_client_for_case(..., ['allowIncompleteNarrative' => true])`** voor tekstscore — ijk hoeft geen volledige 14 scoringvelden te hebben.
- **`ce_hba_ijk_wizard_architecture_body_from_input()`** — architectuurcheck-body = wizard-API-shape; geen hardcoded toon/archetype.

Bestand: `web/api/hba-ijkrun-repository.php`.

## Ijkset v0.3 — merkarchitectuur (8 velden)

| Ijk-veld | Wizard / API | Klantvraag (wizard-stap) |
| --- | --- | --- |
| `merk` | (metadata check) | — |
| `branche` | `branche_code` (+ label in UI) | Wie jullie zijn en in welke markt |
| `wie` | `identity_intro` | Wie jullie zijn |
| `doel` | `doel` | Wat jullie willen bereiken |
| `wat_anders` | `distinctiviteit`, `mechanisme` | Wat maakt jullie anders |
| `niet_in_geloven` | `foe` | (tegenkracht; geen apart ijk-veld voor `wat_bewust_niet`) |
| `regels` | `geloofwaardigheidstest` / `regels[]` | Jullie regels |
| `bewijsvelden` | `bewijsvelden` | Optioneel in ijk; wizard vult via kern/regels |

## Wizard — extra velden (niet in ijk v0.3)

| Veld (API) | Label / stap | Verplicht voor merkcontrole (14 velden) |
| --- | --- | --- |
| `wat_bewust_niet` | Wat jullie nooit over jezelf willen lezen | Nee (wel wizard); leeg bij ijk |
| `verboden_logica` | Afgeleid uit `wat_bewust_niet` bij opslaan | Ja (min. 1) — leeg bij ijk |
| `kernformuleringen` | Hoe jullie werken (overview) | Nee voor 14-veld-check |
| `archetype` | Welke voelt als jullie (stap 6) | Ja — leeg bij ijk |
| `contextprofiel` | Context | Ja — leeg bij ijk |
| `tone_of_behaviour` | Jullie merkstem | Ja — leeg bij ijk |
| `spanning` | Afgeleid bij opslaan | Ja — afgeleid uit doel+foe |
| `woorden_die_passen` / `_niet_passen` | Grenzen & taal | Ja (min. 3) — leeg bij ijk |
| `goede_voorbeelden` / `zwakke_voorbeelden` | Kalibratie | Ja (3+3) — leeg bij ijk |
| `aanspreekvorm` | je/u | Nee |

De **14 scoringvelden** = `CE_HBA_NARRATIVE_FIELDS` in `web/api/hba-repository.php`.

## Testmerken (bundel JSON)

Velden in `brand_narrative` van o.a. `briefing/hba-input/HBA_testmerken/Duinroos_Thuiszorg.json`:

| Veld | Duinroos | Ijk T13/A18 (8 velden) |
| --- | --- | --- |
| `archetype` | Verzorger | — |
| `doel` | ✓ | ✓ |
| `foe` | actor + maatstaf | `niet_in_geloven` (stelling) |
| `spanning` | eigen zin | afgeleid (enrich) |
| `mechanisme` | kleine teams… | `wat_anders` |
| `geloofwaardigheidstest` | vier zorgverleners… | `regels` |
| `verboden_logica` | 4 regels incl. wachtlijst | — |
| `bewijsvelden` | 4 meetvelden | soms leeg in ijk |
| `tone_of_behaviour` | warm, concreet… | — |
| `goede_voorbeelden` | 3 cliëntdagen | — |
| `zwakke_voorbeelden` | 3 anti-voorbeelden | — |
| `contextprofiel` | volwassen | — |
| `identity_intro` / branche | in meta + intro | `wie` + `branche` |
| `default_platforms`, `target_post_volume_per_month` | ja | — |

Zie ook `docs/hba-t13-vs-duinroos-7.md` (v1.10.8 meting); na stap 7 meet T13 **dezelfde wizard-route** als een klant met alleen ijk-velden ingevuld.

## Klaar als

- [x] Vergelijkingstabel wizard / ijkset / testmerken (dit document).
- [x] Runner gebruikt wizardvelden; ontbrekende ijk-velden blijven leeg (geen testmerk-stubs).

**Optioneel na deploy staging:** korte ijkrun op T13 om verschil t.o.v. v1.11-stap6-run te zien (inhoudelijke shift verwacht).

Geen productie.
