# HBA-testmerken — stabiliteit (v1.7)

Gegenereerd: 2026-10-10 17:50:04 UTC
Promptversie: v1.7
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 9 · **instabiel:** 3 · **fout:** 2 · **review:** 3

`category_fallback` in laatste run: 10 posts · `hoofdoordeel_gecorrigeerd`: 25 keer

Ruwe JSON: `hba-testmerken-stability-v1.7-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Brandpunt Koffie | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 0/5 | categorietaal, categorietaal, op_merk, op_merk, op_merk | 0.0 |
| Brandpunt Koffie | Neutraal / grensgeval | 0/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.5 |
| Brandpunt Koffie | Op merk | 3/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 3/5 | categorietaal, tegenkracht, categorietaal, categorietaal, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 5/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 0/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.5 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.5 |
| Stelwerk | Branchetaal uit de verboden logica | 4/5 | categorietaal, categorietaal, categorietaal, tegenkracht, categorietaal | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 0/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 1.0 |
| Stelwerk | Neutraal / grensgeval | 0/5 | categorietaal, categorietaal, tegenkracht, categorietaal, tegenkracht | 0.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.5 |

### Instabiele posts (met citaten uit afwijkende runs)

- **Brandpunt Koffie — Neutraal / grensgeval:** 0 goed runs; hoofdoordeel: ['categorietaal', 'categorietaal', 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 1: oordeel **fout**, hoofdoordeel categorietaal, flags ['category_fallback']; citaten: category_fallback: «Vijf tips om je koffiezetapparaat te ontkalken.»
  - Run 2: oordeel **fout**, hoofdoordeel categorietaal, flags ['category_fallback']; citaten: category_fallback: «Vijf tips om je koffiezetapparaat te ontkalken.»
  - Run 3: oordeel **fout**, hoofdoordeel categorietaal, flags ['category_fallback']; citaten: category_fallback: «Vijf tips om je koffiezetapparaat te ontkalken.»
  - Run 4: oordeel **fout**, hoofdoordeel categorietaal, flags ['category_fallback']; citaten: category_fallback: «Vijf tips om je koffiezetapparaat te ontkalken.»
  - Run 5: oordeel **fout**, hoofdoordeel categorietaal, flags ['category_fallback']; citaten: category_fallback: «Vijf tips om je koffiezetapparaat te ontkalken.»
- **Brandpunt Koffie — Op merk:** 3 goed runs; hoofdoordeel: ['op_merk', 'op_merk', 'op_merk', 'op_merk', 'op_merk']
  - Run 1: oordeel **fout**, hoofdoordeel op_merk, flags ['proof_gap']; citaten: proof_gap: «Over twee weken in de winkel, zodra hij is uitgerust.»
  - Run 3: oordeel **fout**, hoofdoordeel op_merk, flags ['proof_gap']; citaten: proof_gap: «Over twee weken in de winkel, zodra hij is uitgerust.»
- **Duinroos Thuiszorg — Archetype zonder bewijs:** 3 goed runs; hoofdoordeel: ['categorietaal', 'tegenkracht', 'categorietaal', 'categorietaal', 'tegenkracht']
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging']; citaten: category_fallback: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.»; foe_vervaging: «Zo zien wij zorg.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging']; citaten: category_fallback: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.»; foe_vervaging: «Zo zien wij zorg.»
- **Stelwerk — Branchetaal uit de verboden logica:** 4 goed runs; hoofdoordeel: ['categorietaal', 'categorietaal', 'categorietaal', 'tegenkracht', 'categorietaal']
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Wij zijn volledig onafhankelijk.»; proof_gap: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»
- **Stelwerk — Categorietaal:** 0 goed runs; hoofdoordeel: ['tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht']
  - Run 1: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; foe_vervaging: «Neem contact op voor een vrijblijvend gesprek.»
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging', 'proof_gap']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; foe_vervaging: «Neem contact op voor een vrijblijvend gesprek.»; proof_gap: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»
  - Run 3: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; foe_vervaging: «Neem contact op voor een vrijblijvend gesprek.»
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; foe_vervaging: «Neem contact op voor een vrijblijvend gesprek.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging']; citaten: category_fallback: «Wij zijn uw betrouwbare partner voor ERP-implementaties.»; foe_vervaging: «Neem contact op voor een vrijblijvend gesprek.»

### Klaar-criteria (alle runs)

- [x] Post 3 Brandpunt Koffie
- [x] Post 2 Brandpunt Koffie
- [x] Post 1 Brandpunt Koffie
- [x] Post 3 Stelwerk
- [ ] Post 2 Stelwerk
- [x] Post 1 Stelwerk
- [x] Post 3 Duinroos Thuiszorg
- [x] Post 2 Duinroos Thuiszorg
- [x] Post 1 Duinroos Thuiszorg
- [x] Brandpunt post 5
- [x] Duinroos post 6
- [x] BC-spreiding ≤ 1 (alle posts)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **10/17** posts.
