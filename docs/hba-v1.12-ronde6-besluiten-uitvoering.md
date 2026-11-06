# HBA v1.12 — Rogier-besluiten 19 okt (uitvoering)

**Briefing:** `briefing/cursor-ronde-6-besluiten-rogier-uitvoeren.md`  
**Besluiten:** `briefing/hba-input/hba-besluiten-ronde-6-2026-10-19.md`  
**Prompt:** `v1.12` (`CE_HBA_PROMPT_VERSION`)

## Stap 1–4 (code)

| Onderdeel | Status |
| --- | --- |
| 1a Matrix `niet_beoordeeld` | `ce_hba_testpost_expectation_is_scored`; summary + stabiliteit-md |
| 1b Logging | `merkbewering`, `kern_categorietaal`, `hoofdoordeel_volgt_uit_flags` in raw + stability export |
| 2 Merkbewering + volgorde PHP | `ce_hba_resolve_hoofdoordeel` 5 treden |
| 3 Trede 2 | `&& !$kernCat` verwijderd |
| 4 Testset | Brandpunt markthygiëne + Stelwerk samenwerking: expliciete verwachting, geen `ambigu` |

**Lokaal:** `php scripts/test-hba-hoofdoordeel-v1.12.php`

**Neutrale posts (merkbewering=nee):** trede 5 kan `op_merk` opleveren — melden aan Rogier na 5×19, geen eigen fix.

## Stap 5 — 5×19 poging 2

**Uitkomst:** 13/19 stable — **pilotdrempel niet gehaald.** Zie `docs/hba-v1.12-stap5-5x19-eindmeting.md`.

Stelwerk post 5 is **5/5**; Duinroos post 7 **2/5** (bekende uitzondering). Geen extra run.

## Stap 6 — A6

Tone-labels, feedback Akkoord/Verwijderen, weekrange nl, ritme-meter, rapporttitel-filter (bestaand), hashtags in score + CE-03 prompt.

**Niet in deze ronde:** `geen_bron` override (besluit 2), flagtelling kit, GEO Vindbaarheid.
