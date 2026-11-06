# HBA v1.12 — stap 5 eindmeting (5×19, poging 2/2)

**Datum:** 2026-10-19 (staging)  
**Prompt:** v1.12 · **Model:** claude-sonnet-4-6  
**Duur run:** ~42 min

## Besluit (briefing Rogier)

| Criterium | Doel | Uitkomst |
| --- | --- | --- |
| Stable 5/5 | ≥ 16 / 19 | **13 / 19** |
| Uitzonderingen | Stelwerk post 5, Duinroos post 7 | Post 5 **5/5** · Post 7 **2/5** (instabiel, verwacht) |

**Pilotdrempel niet gehaald.** Geen derde 5×19, geen prompt-tuning — ruwe output naar Rogier.

## Stable (13)

Brandpunt: Breekt regel, markthygiëne, neutraal, op merk, verdedigt regel · Duinroos: breekt regel, logica tegenkracht, neutraal, op merk · Stelwerk: breekt regel, categorietaal, op merk, samenwerking (post 5), neutraal *niet* (4/5)

## Niet stable (6)

| Merk | Post | Goed | Verwacht | Patroon |
| --- | --- | ---: | --- | --- |
| Brandpunt | Categorietaal | 3/5 | categorietaal | 2× tegenkracht (foe op versgebrand-claim) |
| Duinroos | Archetype zonder bewijs | 0/5 | op_merk | 5× tegenkracht («Zo zien wij zorg») |
| Duinroos | Categorietaal | 0/5 | categorietaal | 5× tegenkracht |
| Duinroos | Kernbewering (post 7) | 2/5 | tegenkracht | 3× op_merk, 2× tegenkracht |
| Stelwerk | Branchetaal verboden logica | 0/5 | categorietaal | 5× tegenkracht |
| Stelwerk | Neutraal / grensgeval | 4/5 | neutraal | 1× tegenkracht (webinar-AI-vraag) |

## Artefacten

- `docs/hba-testmerken-stabiliteit.md`
- `docs/hba-testmerken-stability-v1.12-5runs.json`
- `docs/hba-v1.12-stap5-5x19-run.log`

## Logging stap 1b

In deze export stonden `kern_categorietaal` / `merkbewering` in de matrix-snapshot **null** (matrix-GET exporteerde ze nog niet). Fix in `ce_hba_testmerken_matrix_payload` + stability-script **na** deze run; voor Rogier: hoofdoordeel + flags + citaten in JSON zijn wel gevuld.

## Trede-2 / merkbewering (spotcheck)

- **Stelwerk post 5 (samenvwerking):** na v1.12 **5/5 op_merk** — geen open punt meer t.o.v. v1.11.
- **Duinroos post 7:** blijft instabiel; 3 runs `op_merk` i.p.v. `tegenkracht`.

Neutrale posts (merkbewering=nee → trede 5): apart uit raw op staging halen bij volgende matrix-GET met logging-fix.
