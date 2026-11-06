# HBA-testmerken — stabiliteit (v1.12)

Gegenereerd: 2026-10-19 17:41:32 UTC
Promptversie: v1.12
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 13 · **instabiel:** 3 · **fout:** 3 · **review:** 0 · **niet beoordeeld:** 0

`category_fallback` in laatste run: 7 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 95 scores)

Flags op functionele onderdelen (doel 0): **0**

`constraint_breach` behouden na verificatie: **15** · verworpen door verificatie: **0** · vervallen zonder uitleg: **0**

Ruwe JSON: `hba-testmerken-stability-v1.12-5runs.json` (zelfde map)

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 1.0 |
| Brandpunt Koffie | Categorietaal | 3/5 | tegenkracht, categorietaal, categorietaal, categorietaal, tegenkracht | 0.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Brandpunt Koffie | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Brandpunt Koffie | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 0/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 1.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 0/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | 2/5 | op_merk, op_merk, tegenkracht, tegenkracht, op_merk | 3.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 5/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Branchetaal uit de verboden logica | 0/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Neutraal / grensgeval | 4/5 | neutraal, neutraal, neutraal, tegenkracht, neutraal | 0.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |

### Instabiele posts (met citaten uit afwijkende runs)

- **Brandpunt Koffie — Categorietaal:** 3 goed runs; hoofdoordeel: ['tegenkracht', 'categorietaal', 'categorietaal', 'categorietaal', 'tegenkracht']
  - Run 1: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt.»; proof_gap: «Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt.»; foe_vervaging: «Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt.»; foe_vervaging_uitleg: «De zin presenteert koffie als een generiek kwaliteitsproduct zonder herkomst, branddatum of smaakuitleg, waarmee de gewoonte om koffie als inwisselbaar en smakeloos te behandelen onbewust wordt bevestigd in plaats van bestreden.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt.»; proof_gap: «versgebrande koffie van topkwaliteit»; foe_vervaging: «Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt. Kom langs!»; foe_vervaging_uitleg: «De zin presenteert koffie als een generiek kwaliteitsproduct zonder herkomst, branddatum of uitleg over smaak — daarmee neemt hij de logica over van koffie als inwisselbaar product waarbij smaak er niet toe doet.»
- **Duinroos Thuiszorg — Archetype zonder bewijs:** 0 goed runs; hoofdoordeel: ['tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht']
  - Run 1: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap', 'category_fallback']; citaten: foe_vervaging: «Zo zien wij zorg.»; foe_vervaging_uitleg: «Deze zin presenteert een emotioneel gebaar (bloemen geven) als de kern van goede zorg, waarmee de post de redenering overneemt dat zorg draait om warmte en betrokkenheid van de zorgverlener — precies de logica die het zorgrooster rechtvaardigt en de cliënt als ontvanger van aandacht neerzet in plaats van als baas over zijn eigen dag.»; proof_gap: «Zo zien wij zorg.»; category_fallback: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.»
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'category_fallback', 'proof_gap']; citaten: foe_vervaging: «Zo zien wij zorg.»; foe_vervaging_uitleg: «Deze zin presenteert een emotioneel gebaar (bloemen geven) als de kern van goede zorg, waarmee de nadruk op de relatie tussen zorgverlener en cliënt als gevoel wordt gelegd — precies de redenering die het zorgrooster vervangt door menselijke warmte als maatstaf, in plaats van de cliënt baas te laten zijn over zijn eigen dag.»; category_fallback: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.»; proof_gap: «Zo zien wij zorg.»
  - Run 3: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'category_fallback', 'proof_gap']; citaten: foe_vervaging: «Zo zien wij zorg.»; foe_vervaging_uitleg: «Deze zin presenteert een emotioneel gebaar (bloemen geven) als de kern van goede zorg, waarmee de nadruk op de relatie tussen zorgverlener en cliënt als gevoel wordt gelegd — precies de redenering die de tegenkracht in stand houdt, omdat ze het rooster en de structuur buiten beeld laat.»; category_fallback: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.»; proof_gap: «Zo zien wij zorg.»
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap']; citaten: foe_vervaging: «Zo zien wij zorg.»; foe_vervaging_uitleg: «Deze zin presenteert een emotioneel gebaar (bloemen geven) als de kern van zorg, waarmee de post de logica overneemt van de tegenkracht: zorg als gevoel en gebaar in plaats van als structurele keuze waarbij de dag van de cliënt de planning bepaalt.»; proof_gap: «Zo zien wij zorg.»; proof_gap_uitleg: «De post claimt een merkvisie op zorg, maar de enige onderbouwing is een anekdote over bloemen — een gebaar dat niets zegt over de werkwijze, de teamgrootte of de planning samen met de cliënt.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'category_fallback', 'proof_gap']; citaten: foe_vervaging: «Zo zien wij zorg.»; foe_vervaging_uitleg: «Deze zin presenteert een emotioneel gebaar (bloemen geven) als de kern van zorg, waarmee de nadruk op de relatie tussen zorgverlener en cliënt als gevoel wordt gelegd — precies de redenering van de tegenkracht, die de mens ondergeschikt maakt aan een systeem zonder dat het merk laat zien hoe het de dag van de cliënt leidend maakt.»; proof_gap: «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster.»; category_fallback: «Zo zien wij zorg.»
- **Duinroos Thuiszorg — Categorietaal:** 0 goed runs; hoofdoordeel: ['tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht']
  - Run 1: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging', 'proof_gap']; citaten: category_fallback: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging_uitleg: «De claim 'warme, persoonlijke zorg van betrokken professionals' presenteert betrokkenheid en warmte als onderscheid, terwijl de tegenkracht juist is dat zorgverleners wél betrokken willen zijn maar gevangen zitten in een rooster dat de klok boven de mens stelt — de post neemt de belofte over zonder de structurele oorzaak te benoemen of te doorbreken.»; proof_gap: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; proof_gap: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging_uitleg: «De zin presenteert 'betrokken professionals' als onderscheid, terwijl de tegenkracht juist het systeem is dat zorgverleners aan een rooster bindt en daarmee de mens achter de klok plaatst — de post neemt de belofte van betrokkenheid over zonder die te onderscheiden van het roostergeleide systeem.»
  - Run 3: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging', 'proof_gap']; citaten: category_fallback: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging_uitleg: «De zin presenteert 'betrokken professionals' als onderscheid, terwijl de tegenkracht juist het zorgrooster is dat de klok boven de mens stelt — de zin neemt de belofte van persoonlijke betrokkenheid over zonder te laten zien hoe de planning bij Duinroos anders werkt.»; proof_gap: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'proof_gap', 'foe_vervaging']; citaten: category_fallback: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; proof_gap: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging_uitleg: «De zin presenteert 'betrokken professionals' als onderscheid, maar neemt daarmee de redenering over van het zorgrooster-denken: de professional staat centraal, niet de dag en het tempo van de cliënt.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['category_fallback', 'foe_vervaging', 'proof_gap']; citaten: category_fallback: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»; foe_vervaging_uitleg: «De zin presenteert 'persoonlijke zorg' als belofte zonder te laten zien hoe die tot stand komt, waarmee de indruk ontstaat dat betrokkenheid van de professional voldoende is — precies de redenering die het zorgrooster in stand houdt: de mens past zich aan het systeem aan, niet andersom.»; proof_gap: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»
- **Duinroos Thuiszorg — Kernbewering zonder bewijs (V0 v1.9):** 2 goed runs; hoofdoordeel: ['op_merk', 'op_merk', 'tegenkracht', 'tegenkracht', 'op_merk']
  - Run 1: oordeel **fout**, hoofdoordeel op_merk, flags []; citaten: —
  - Run 2: oordeel **fout**, hoofdoordeel op_merk, flags []; citaten: —
  - Run 5: oordeel **fout**, hoofdoordeel op_merk, flags []; citaten: —
- **Stelwerk — Branchetaal uit de verboden logica:** 0 goed runs; hoofdoordeel: ['tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht', 'tegenkracht']
  - Run 1: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap']; citaten: foe_vervaging: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «De zin presenteert 'onafhankelijk' als verkoopargument zonder de commissieregel te noemen, waardoor de post de verkooplogica overneemt die het merk juist bestrijdt: een claim die een systeem (of adviseur) verkoopt op wat hij zegt te zijn, in plaats van op wat hij aantoonbaar doet.»; proof_gap: «Wij zijn volledig onafhankelijk.»
  - Run 2: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap']; citaten: foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «Deze zin verkoopt het merk op populariteit en keuzegedrag van anderen — precies de verkooplogica die een systeem verkoopt op wat het kan (of hoe populair het is) in plaats van op wat het bedrijf nodig heeft.»; proof_gap: «Wij zijn volledig onafhankelijk.»; proof_gap_uitleg: «De claim 'volledig onafhankelijk' wordt niet onderbouwd door de tekst zelf; de commissieregel of een concrete handeling die die onafhankelijkheid bewijst, ontbreekt volledig.»
  - Run 3: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap']; citaten: foe_vervaging: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «Deze zin verkoopt het merk op populariteit en keuzegedrag van anderen — precies de verkooplogica (kies op wat anderen doen, niet op wat jouw bedrijf nodig heeft) die de tegenkracht van dit merk vormt.»; proof_gap: «Wij zijn volledig onafhankelijk.»; proof_gap_uitleg: «De claim 'volledig onafhankelijk' wordt niet onderbouwd door de tekst zelf; de commissieregel, het ontbreken van resellercontracten of een andere concrete handeling ontbreekt volledig.»
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap', 'category_fallback']; citaten: foe_vervaging: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «De zin verkoopt het merk op een eigenschap ('onafhankelijk') zonder die te verankeren in de commissieregel, waardoor de redenering van de verkooplogica — kies op basis van een claim, niet op basis van bewijs — ongewijzigd blijft staan.»; proof_gap: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; category_fallback: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»
  - Run 5: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'proof_gap']; citaten: foe_vervaging: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»; foe_vervaging_uitleg: «De zin verkoopt het merk op een eigenschap ('onafhankelijk') zonder te verklaren wat die eigenschap kost of inhoudt, waarmee de post precies de verkooplogica overneemt die het merk bestrijdt: een systeem — hier het merk zelf — verkopen op wat het claimt te zijn in plaats van op wat het bedrijf nodig heeft.»; proof_gap: «Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»
- **Stelwerk — Neutraal / grensgeval:** 4 goed runs; hoofdoordeel: ['neutraal', 'neutraal', 'neutraal', 'tegenkracht', 'neutraal']
  - Run 4: oordeel **fout**, hoofdoordeel tegenkracht, flags ['foe_vervaging', 'category_fallback']; citaten: foe_vervaging: «AI in de maakindustrie, wat kun je ermee?»; foe_vervaging_uitleg: «De vraag 'wat kun je ermee?' framt AI als een set mogelijkheden en features om te verkennen, wat de redenering overneemt van de verkooplogica die een systeem verkoopt op wat het kan in plaats van op wat het bedrijf nodig heeft.»; category_fallback: «Webinar volgende week: AI in de maakindustrie, wat kun je ermee?»

### V0 — self_review en hbaHint

- **Brandpunt Koffie:** self_review=gevuld · hint=gevuld
- **Stelwerk:** self_review=gevuld · hint=leeg
- **Duinroos Thuiszorg:** self_review=gevuld · hint=gevuld

### Klaar-criteria (alle runs)

- [x] Post 3 Brandpunt Koffie
- [ ] Post 2 Brandpunt Koffie
- [x] Post 1 Brandpunt Koffie
- [x] Post 4 Brandpunt Koffie
- [x] Post 3 Stelwerk
- [x] Post 2 Stelwerk
- [x] Post 1 Stelwerk
- [ ] Post 4 Stelwerk
- [x] Post 3 Duinroos Thuiszorg
- [ ] Post 2 Duinroos Thuiszorg
- [x] Post 1 Duinroos Thuiszorg
- [x] Post 4 Duinroos Thuiszorg
- [x] Brandpunt post 5
- [x] Duinroos post 6
- [ ] BC-spreiding ≤ 1 (alle posts)
- [x] hoofdoordeel_gecorrigeerd ≤ 5%
- [x] Flags op functionele onderdelen = 0
- [ ] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **7/19** posts.
