# HBA-testmerken — stabiliteit (v1.10.2)

Gegenereerd: 2026-10-15 15:15:11 UTC
Promptversie: v1.10.2
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 13 · **instabiel:** 2 · **fout:** 1 · **review:** 3

`category_fallback` in laatste run: 7 posts · `hoofdoordeel_gecorrigeerd`: 7 keer (7.4% van 95 scores)

Flags op functionele onderdelen (doel 0): **0**

`constraint_breach` behouden na verificatie: **15** · verworpen door verificatie: **0** · vervallen zonder uitleg: **0**

Ruwe JSON: `hba-testmerken-stability-v1.10.2-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Brandpunt Koffie | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 1.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Brandpunt Koffie | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Brandpunt Koffie | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 4/5 | categorietaal, categorietaal, tegenkracht, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 4/5 | categorietaal, tegenkracht, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | 0/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 5/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 1.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Stelwerk | Branchetaal uit de verboden logica | 0/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |

### Instabiele posts (met citaten uit afwijkende runs)

- **Duinroos Thuiszorg — Archetype zonder bewijs:** 4 goed runs; hoofdoordeel: ['categorietaal', 'categorietaal', 'tegenkracht', 'categorietaal', 'categorietaal']
  - Run 3: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Zo zien wij zorg.»; proof_gap: «Zo zien wij zorg.»; foe_vervaging: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.»; foe_vervaging_uitleg: «De post definieert zorg als een warm gebaar (bloemen geven), waarmee de nadruk op emotionele betrokkenheid komt te liggen in plaats van op de structurele keuze om de dag van de cliënt leidend te maken boven het rooster.»
- **Duinroos Thuiszorg — Categorietaal:** 4 goed runs; hoofdoordeel: ['categorietaal', 'tegenkracht', 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; proof_gap: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging_uitleg: «De zin presenteert 'persoonlijke zorg' als belofte zonder te verankeren in de viergrens of het vaste team, waardoor de indruk ontstaat dat persoonlijke aandacht een vanzelfsprekend kenmerk van de organisatie is — precies de redenering die de tegenkracht (het rooster dat de klok boven de mens stelt) niet weerlegt maar omzeilt.»
- **Stelwerk — Branchetaal uit de verboden logica:** 0 goed runs; hoofdoordeel: ['tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht']
  - Run 1: oordeel **fout**, hoofdoordeel tegenkracht, flags ['proof_gap', 'category_fallback', 'foe_vervaging']; citaten: proof_gap: «Wij zijn volledig onafhankelijk.»; category_fallback: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «De zin verkoopt het merk op populariteit en aantrekkingskracht — precies de verkooplogica die het merk bestrijdt: een keuze die wordt gemotiveerd door wat anderen doen in plaats van door wat het bedrijf zelf nodig heeft.»
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['proof_gap', 'category_fallback', 'foe_vervaging']; citaten: proof_gap: «Wij zijn volledig onafhankelijk.»; category_fallback: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «Deze zin verkoopt het merk op populariteit en aantrekkingskracht — precies de verkooplogica die het merk bestrijdt: een keuze die wordt gemotiveerd door wat anderen doen in plaats van door wat het bedrijf zelf nodig heeft.»
  - Run 3: oordeel **fout**, hoofdoordeel tegenkracht, flags ['proof_gap', 'category_fallback', 'foe_vervaging']; citaten: proof_gap: «Wij zijn volledig onafhankelijk.»; category_fallback: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «De zin verkoopt het merk op populariteit en aantrekkingskracht — precies de verkooplogica die het merk bestrijdt: een keuze die wordt gemotiveerd door wat anderen doen in plaats van door wat het bedrijf zelf nodig heeft.»
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['proof_gap', 'category_fallback', 'foe_vervaging']; citaten: proof_gap: «Wij zijn volledig onafhankelijk.»; category_fallback: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «Deze zin verkoopt het merk op populariteit en aantrekkingskracht — precies de verkooplogica die het merk bestrijdt: een systeem (of adviseur) kiezen op wat het claimt, niet op wat het bedrijf nodig heeft.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['proof_gap', 'category_fallback', 'foe_vervaging']; citaten: proof_gap: «Wij zijn volledig onafhankelijk.»; category_fallback: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «Deze zin verkoopt het merk op populariteit en aantrekkingskracht — precies de verkooplogica die het merk bestrijdt: een keuze laten drijven op wat een aanbieder claimt, niet op wat het bedrijf nodig heeft.»

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
- [ ] Post 2 Duinroos Thuiszorg
- [x] Post 1 Duinroos Thuiszorg
- [x] Post 4 Duinroos Thuiszorg
- [x] Brandpunt post 5
- [x] Duinroos post 6
- [x] BC-spreiding ≤ 1 (alle posts)
- [ ] hoofdoordeel_gecorrigeerd ≤ 5%
- [x] Flags op functionele onderdelen = 0
- [x] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **7/19** posts.
