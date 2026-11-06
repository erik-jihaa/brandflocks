# HBA-testmerken — stabiliteit (v1.9)

Gegenereerd: 2026-10-13 12:24:00 UTC
Promptversie: v1.9
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 16 · **instabiel:** 0 · **fout:** 0 · **review:** 1

`category_fallback` in laatste run: 5 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 85 scores)

Flags op functionele onderdelen (doel 0): **3**

`constraint_breach` behouden na verificatie: **15** · verworpen door verificatie: **0** · vervallen zonder uitleg: **0**

Ruwe JSON: `hba-testmerken-stability-v1.9-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Brandpunt Koffie | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.5 |
| Brandpunt Koffie | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 1.0 |
| Brandpunt Koffie | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.5 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 5/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 1.0 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.5 |
| Stelwerk | Branchetaal uit de verboden logica | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.5 |
| Stelwerk | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |

### Flags op functionele onderdelen

- **Stelwerk — Categorietaal** (category_fallback): «Wij zijn uw betrouwbare partner voor ERP-implementaties. Neem contact op voor een vrijblijvend gesprek.»
- **Stelwerk — Categorietaal** (category_fallback): «Wij zijn uw betrouwbare partner voor ERP-implementaties. Neem contact op voor een vrijblijvend gesprek.»
- **Stelwerk — Categorietaal** (category_fallback): «Wij zijn uw betrouwbare partner voor ERP-implementaties. Neem contact op voor een vrijblijvend gesprek.»

### V0 — self_review en hbaHint

- **Brandpunt Koffie:** self_review=gevuld · hint=gevuld
- **Stelwerk:** self_review=gevuld · hint=gevuld
- **Duinroos Thuiszorg:** self_review=gevuld · hint=gevuld

### Klaar-criteria (alle runs)

- [x] Post 3 Brandpunt Koffie
- [x] Post 2 Brandpunt Koffie
- [x] Post 1 Brandpunt Koffie
- [x] Post 4 Brandpunt Koffie
- [x] Post 3 Stelwerk
- [x] Post 2 Stelwerk
- [x] Post 1 Stelwerk
- [x] Post 4 Stelwerk
- [x] Post 3 Duinroos Thuiszorg
- [x] Post 2 Duinroos Thuiszorg
- [x] Post 1 Duinroos Thuiszorg
- [x] Post 4 Duinroos Thuiszorg
- [x] Brandpunt post 5
- [x] Duinroos post 6
- [x] BC-spreiding ≤ 1 (alle posts)
- [x] hoofdoordeel_gecorrigeerd ≤ 5%
- [ ] Flags op functionele onderdelen = 0
- [x] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **5/17** posts.
