# HBA ronde 6 — stap 8: stroomtest Brandpunt Koffie

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 8  
Branch: `hba-ronde-6-pilotklaar`  
Scoring: `CE_HBA_PROMPT_VERSION` (v1.11 op staging na deploy stap 5–7)

## Doel

1. Nieuwe tenant **Brandpunt Koffie Stroomtest** — onboarding poging 1 (geblokkeerd) en poging 2 (`meetlat_klaar` / meetlat actief).
2. 25 posts uit `Brandpunt_stroomtest_25.json` in **datumvolgorde**, scoren op v1.11.
3. Checkpoints na post **10, 15, 20, 21, 25**: Overzicht + Rapport (screenshot + ruwe JSON).
4. Drempels app vs Implementation Kit (Rogier) expliciet vergelijken.

## Gereedschap (code)

| Onderdeel | Pad |
| --- | --- |
| Repository | `web/api/hba-stroomtest-brandpunt-repository.php` |
| Admin API | `POST /api/admin/hba-stroomtest-brandpunt` (reset-token, geen productie) |
| Run-script | `scripts/run-hba-brandpunt-stroomtest-staging.sh` |

**Phases:** `ensure_client`, `onboarding_poging_1`, `onboarding_poging_2`, `import_posts`, `checkpoint`, `full_pipeline`.

Staging krijgt geen `briefing/` — het script stuurt `bundle` + `markthygiene` (uit `Brandpunt_Koffie.json`) mee in de POST-body.

### Onboarding via API vs wizard-UI

De admin-flow schrijft dezelfde antwoorden als in `brandpunt-onboarding.md` / JSON `onboarding.poging_*` naar `brand_narratives` en roept **`ce_hba_v2_run_architecture_check`** aan (poging 1: half; poging 2: volledig + `ce_hba_v2_mark_architecture_complete` bij actieve meetlat).

**Screenshots van wat de klant ziet** blijven handmatig in de app (wizard stap 6-voorstellen + check-scherm), na login op de stroomtest-tenant. Leg naast screenshots de ruwe check-JSON vast (`onboarding-poging-*.json` uit het run-script).

## Drempels — Kit vs app

| Kit (Rogier / content-25-posts) | App (huidige code) |
| --- | --- |
| Risicopatroon: **3 of 5** flags in laatste 10 posts | Geen aparte teller; **`architecture_risk_flags`** per post; signalen via **`ce_agenda_portfolio_signals`** (o.a. `declining_line`, `skewed_distribution`) |
| PBS-daling **8** (licht) / **15** (zwaar) t.o.v. basislijn | **`erosionAlert`**: fase **C**, ≥3 maand-buckets, recent maandgemiddelde **< priorAvg − 10** (`ce_hba_v2_compute_pbs_metrics`) |
| Contentrol-aandeel **>50% / >65%** | **`skewed_distribution`**: **4 van laatste 6** postszelfde contentrol |
| Licht dalende trend (post 15) | **`declining_line`**: gem. merkbijdrage laatste 6 **<** vorige 6 (drempel **−0,05**) |

Checkpoint-JSON bevat `analysis.window` (hoofdoordeel-telling, flags per post-nr) en `appThresholdsNote` voor copy naar Rogier.

## Uitvoering staging

1. Deploy web (nieuwe API) — alleen na **ja staging**.
2. `./scripts/run-hba-brandpunt-stroomtest-staging.sh` → schrijft naar `docs/hba-brandpunt-stroomtest-staging-<datum>/`.
3. Optioneel alleen onboarding: `.../run-hba-brandpunt-stroomtest-staging.sh onboarding`.
4. Screenshots: `docs/hba-brandpunt-stroomtest-staging-<datum>/checkpoint-<n>-overzicht.png` en `-rapport.png`.

## Resultaten (staging 2026-10-18)

Zie **`docs/hba-brandpunt-stroomtest-staging-2026-10-18/samenvatting.md`** + checkpoint-JSON.

### Onboarding

| Poging | meetlat / totaaloordeel | guardrail | Opmerking |
| --- | --- | --- | --- |
| 1 | geblokkeerd / meetlat_te_zwak | nee | Signalen + hygiëne zoals verwacht |
| 2 | actief / meetlat_klaar | ja | Stroom gestart |

### Checkpoints

| Na post | Rogier verwacht (kort) | App: risk flags (window) | PBS / alerts | Match? |
| --- | --- | --- | --- | --- |
| 10 | 8× op merk, 0 flags | 5 flags (brede teller) | geen | gedeeltelijk — scorer strenger |
| 15 | 2 flags (12, 15) | 8 flags | geen | nee |
| 20 | 6–7 flags, zware alert | 10 flags; CT 8/10 | geen erosion/signaal | richting ok, alerts ontbreken |
| 21 | herstel, alerts blijven | 10 flags (21 extra op merk) | geen | alerts ontbreken |
| 25 | 4 flags, lichte alert | 6 flags | geen | gedeeltelijk |

**Status run:** gedraaid op staging; **screenshots UI** in `docs/hba-brandpunt-stroomtest-staging-2026-10-18/` (10× checkpoint + wizard-overzicht).
