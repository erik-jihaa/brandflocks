# Brandpunt stroomtest — staging run 2026-10-18

**Tenant:** Brandpunt Koffie Stroomtest  
**clientId:** `9d414129-29ab-ee9c-95c7-dc5452f6a65f`  
**Login:** staging-testtenant (inlog via Erik indien je de UI wilt nalopen)  
**Prompt:** v1.11 (staging)

## Onboarding (API, zelfde antwoorden als wizard)

| Poging | meetlatStatus | totaaloordeel | guardrail | Rogier-check |
| --- | --- | --- | --- | --- |
| 1 | geblokkeerd | meetlat_te_zwak | nee | Hygiëne scherpte (`zitInHygienefactoren`), `tegenkrachtOntbreekt` + `regelOntbreekt` met advies — geen kale gate-melding |
| 2 | actief | meetlat_klaar | ja | Stroomtest mag door |

Ruwe JSON: `onboarding-poging-1.json`, `onboarding-poging-2.json`. **Wizard-screenshots:** nog handmatig in UI.

## Checkpoints (ruwe JSON + analyse)

| Na post | Rogier (kort) | App hoofdoordeel in window | App: posts met architecture-flag | PBS erosion | Portfolio signaal |
| --- | --- | --- | --- | --- | --- |
| 10 | 8× op merk, 2× neutraal, 0 flags | op_merk 7, neutraal 1, categorietaal 2 | 5 (o.a. 1,2,6,8,10) | nee | geen |
| 15 | 2 flags (12, 15) | op_merk 3, categorietaal 6, neutraal 1 | 8 | nee | geen |
| 20 | 6–7 flags, zware alert, CT 4/10 | categorietaal 8, tegenkracht 1, regelbreuk 1 | 10 | nee | geen |
| 21 | herstel, alerts blijven | +1× op_merk (21) | 10 (zelfde als 20) | nee | geen |
| 25 | 4 flags, lichte alert | op_merk 4, categorietaal 4, TKR 1, regelbreuk 1 | 6 | nee | geen |

Bestanden: `checkpoint-10.json` … `checkpoint-25.json`.

### Interpretatie t.o.v. Rogier

1. **“Risicoflag” in JSON** = elke niet-lege `architecture_risk_flags` (o.a. `category_fallback`, `proof_gap`). Rogier telt waarschijnlijk **patroon-posts** (hoofdoordeel ≠ op merk / specifieke flags), niet elke flag op een op-merk-post (voorbeeld: post 2 is “Op merk” maar heeft `category_fallback`).
2. **Basislijn post 10:** scorer is strenger dan verwacht (post 1 = categorietaal, meerdere flags in fase A).
3. **Afglijden post 20:** richting klopt (veel categorietaal + regelbreuk 20), maar **geen** zware PBS-/portfolio-alert — app-drempels (zie stap-8-doc) wijken af van Implementation Kit (3/5, −8/−15).
4. **Post 6** was eerst zonder score (gateway-timeout batch); **`rescore_missing`** heeft nr 6 alsnog gescoord.

## Screenshots (UI)

| Bestand | Moment |
| --- | --- |
| `checkpoint-10-overzicht.png` / `-rapport.png` | Na post 10 (Merkcijfer “Op koers”; rapport 10 posts) |
| `checkpoint-15-overzicht.png` / `-rapport.png` | Na post 15 (“Aandacht nodig ↓”; rapport 15 posts, samenvatting zwakker) |
| `checkpoint-20-overzicht.png` / `-rapport.png` | Na post 20 (zelfde trend; rapport 20 posts) |
| `checkpoint-21-overzicht.png` / `-rapport.png` | Na post 21 (herstelpost; rapport 21 posts) |
| `checkpoint-25-overzicht.png` / `-rapport.png` | Na post 25 (rapport toont **21** posts in engagement-blok — 25 scores in DB; mogelijk telverschil LinkedIn-import) |
| `onboarding-wizard-merkarchitectuur-voltooid.png` | Merkarchitectuur overzicht na poging 2 ( geen apart scherm poging 1 ) |

## Run-notities

- Eén HTTP-request voor 10+ scores → **~90s gateway timeout**; productie-run = **1 post per request** (zie `run-full.log` / stepwise python in chat).
- `full_pipeline` phase blijft voor kleine omgevingen; staging gebruikt stepwise.
