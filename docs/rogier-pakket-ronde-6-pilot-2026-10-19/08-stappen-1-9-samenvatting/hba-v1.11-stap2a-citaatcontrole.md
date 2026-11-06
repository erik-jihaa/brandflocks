# HBA ronde 6 — stap 2a: citaatcontrole op de hint

Datum: 2026-10-18  
Bron: `briefing/cursor-stap2a-citaatcontrole-hints.md`  
Branch: `hba-ronde-6-pilotklaar`  
Hintprompt: **niet aangeraakt**

## Keuze placeholders

Tekst tussen `[…]` verdwijnt uit de lopende tekst (`passage_na`, `cta_na`, `headline_na`, `aanwijzing`) en gaat naar `invulvelden` — dezelfde expliciete invulvraag die de UI al naast de passage toont. Niet afkeuren als ze verplaatst zijn; wél afkeuren als ze daarna nog in de lopende tekst staan.

## Wat erin zit

| Controle | Actie |
| --- | --- |
| Citaat in `aanwijzing` / `regel_overtreden` / `passage_voor` | Moet letterlijk in de artikeltekst (genormaliseerd). Anders 1 retry, daarna geen hint. |
| Aanspreekvorm | Nieuw veld `brand_narratives.aanspreekvorm` (`je`/`u`). Brandpunt-testmerk = `je`. Passage die `u` gebruikt bij `je` wordt afgekeurd. Leeg veld = geen check. |
| Placeholder | Verplaatst naar invulvraag, niet in de lopende tekst. |

Afgekeurde pogingen: `event_log` `hint_citaatcontrole` met fragment + reden.

## Bestanden

- `web/api/hba-hint-verificatie.php`
- `web/api/hba-v2-repository.php` — check vóór INSERT
- `web/migrations/add_aanspreekvorm.sql`
- `scripts/run-hba-stap2-hints-staging.py` — `quoteNotInSource`, `wrongAddress`, `placeholder`
- `scripts/test-hba-hint-verificatie-v1.11.php`

## Klaar-criteria (lokaal)

- [x] Citaatcontrole + retry + log
- [x] Aanspreekvorm-veld + hint volgt het
- [x] Placeholders uit lopende tekst
- [x] Detector uitgebreid
- [x] `php scripts/test-hba-hint-verificatie-v1.11.php`

## Staging (18 okt 2026)

- Deploy stap **2a** (`5e93c4c`) + admin `hba-aanspreekvorm-patch`; smoke **6/6**, bundle `index-Bp3dVuZR.js`.
- Brandpunt-client `54aeb554-…`: **aanspreekvorm = je**.
- 5× hints: `docs/hba-v1.11-stap2a-citaatcontrole.json`

| Target | Score |
|--------|-------|
| V0 Brandpunt | **5/5** |
| V0 Stelwerk | **5/5** |
| V0 Duinroos | **5/5** |
| Brandpunt week 41 | **1/5** |

Week 41: **4×** hint met “nadert” / verzonnen citaat in `aanwijzing` (detector faalt); **`passage_na` wél letterlijk**. **1×** geen hint na citaatcontrole (`Geen aanwijzing ontvangen`). PHP blokkeert dus soms, maar unquoted “nadert” in de aanwijzing kan nog door — vervolg: strengere check of stap 3 (artikel herschrijven).
