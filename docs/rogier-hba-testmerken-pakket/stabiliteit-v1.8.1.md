# HBA-testmerken — stabiliteit (v1.8.1)

Gegenereerd: 2026-10-12 10:55:36 UTC
Promptversie: v1.8.1
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 12 · **instabiel:** 2 · **fout:** 0 · **review:** 3

`category_fallback` in laatste run: 6 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 85 scores)

Flags op functionele onderdelen (doel 0): **0**

Ruwe JSON: `hba-testmerken-stability-v1.8.1-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Brandpunt Koffie | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Brandpunt Koffie | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 1.0 |
| Brandpunt Koffie | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 3/5 | tegenkracht, tegenkracht, regelbreuk, tegenkracht, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 0/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.5 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Branchetaal uit de verboden logica | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 3/5 | regelbreuk, categorietaal, regelbreuk, categorietaal, categorietaal | 0.5 |
| Stelwerk | Neutraal / grensgeval | 0/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.5 |

### Instabiele posts (met citaten uit afwijkende runs)

- **Duinroos Thuiszorg — Logica van de tegenkracht:** 3 goed runs; hoofdoordeel: ['tegenkracht', 'tegenkracht', 'regelbreuk', 'tegenkracht', 'regelbreuk']
  - Run 3: oordeel **fout**, hoofdoordeel regelbreuk, flags ['foe_vervaging', 'constraint_breach']; citaten: foe_vervaging: «Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.»; foe_vervaging_uitleg: «Deze zin presenteert routeoptimalisatie als merkprestatie, waarmee de logica van het zorgrooster — de klok en de planning bepalen de dag — wordt overgenomen in plaats van bestreden.»; constraint_breach: «Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.»
  - Run 5: oordeel **fout**, hoofdoordeel regelbreuk, flags ['foe_vervaging', 'constraint_breach']; citaten: foe_vervaging: «Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.»; foe_vervaging_uitleg: «Deze zin stelt routeoptimalisatie en tijdwinst centraal als resultaat, waarmee de logica van het zorgrooster dat de klok boven de mens stelt wordt overgenomen in plaats van bestreden.»; constraint_breach: «Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.»
- **Stelwerk — Categorietaal:** 3 goed runs; hoofdoordeel: ['regelbreuk', 'categorietaal', 'regelbreuk', 'categorietaal', 'categorietaal']
  - Run 1: oordeel **fout**, hoofdoordeel regelbreuk, flags ['category_fallback', 'proof_gap', 'constraint_breach']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; proof_gap: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; constraint_breach: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»
  - Run 3: oordeel **fout**, hoofdoordeel regelbreuk, flags ['category_fallback', 'proof_gap', 'constraint_breach']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; proof_gap: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; constraint_breach: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»

### Klaar-criteria (alle runs)

- [x] Post 3 Brandpunt Koffie
- [x] Post 2 Brandpunt Koffie
- [x] Post 1 Brandpunt Koffie
- [x] Post 4 Brandpunt Koffie
- [x] Post 3 Stelwerk
- [ ] Post 2 Stelwerk
- [x] Post 1 Stelwerk
- [x] Post 4 Stelwerk
- [x] Post 3 Duinroos Thuiszorg
- [x] Post 2 Duinroos Thuiszorg
- [x] Post 1 Duinroos Thuiszorg
- [x] Post 4 Duinroos Thuiszorg
- [x] Brandpunt post 5
- [ ] Duinroos post 6
- [x] BC-spreiding ≤ 1 (alle posts)
- [x] hoofdoordeel_gecorrigeerd ≤ 5%
- [x] Flags op functionele onderdelen = 0
- [x] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **6/17** posts.
