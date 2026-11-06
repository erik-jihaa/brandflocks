# HBA-testmerken — resultaat

Gegenereerd: 2026-10-21 09:01:07 UTC
Omgeving: staging
Promptversie: v1.13
Modellen (scores): claude-sonnet-4-6

*Testmerken op staging kunnen guardrail PASS geforceerd hebben voor scoring.*

## Meting v1.13 (na S2)

Vóór-kolommen: nulmeting v1.3 uit `docs/hba-testmerken-matrix-v1.3.json`.

Samenvatting: **18 goed**, **0 fout**, **0 review**, **1 zonder score**.

| Merk | Post | Verwacht | BC | Distinct. | Hoofdoordeel | Contentrol | Flags (werkelijk) | Oordeel | Toelichting | Vóór: BC | Vóór: flags | Vóór: oordeel |
| --- | --- | --- | ---: | ---: | --- | --- | --- | --- | --- | ---: | --- | --- |
| Brandpunt Koffie | Op merk | hoge brand_contribution, geen risk-flags (—; hoog) | 7 | 4 | op_merk | bewijs | — | **goed** | — | 7 | — | goed |
| Brandpunt Koffie | Categorietaal | lage distinctiviteit, flag category_fallback (category_fallback; laag) | 1 | 0 | categorietaal | positionering | category_fallback, proof_gap | **goed** | — | 1 | category_fallback, proof_gap, constraint_breach | goed |
| Brandpunt Koffie | Breekt de regel | flag constraint_breach: breekt de zes-wekenregel (constraint_breach; laag) | 0 | 0 | regelbreuk | activatie | constraint_breach, foe_vervaging | **goed** | — | 0 | constraint_breach, opportunistische_trendcontent, foe_vervaging | goed |
| Brandpunt Koffie | Neutraal / grensgeval | contentrol educatie of service, neutraal, lage bijdrage aan de architectuur (—; neutraal) | 1 | 0 | neutraal | service | category_fallback | **goed** | — | 0.5 | category_fallback, foe_vervaging | goed |
| Brandpunt Koffie | Verdedigt de regel | hoge brand_contribution, geen constraint_breach: noemt het verboden onderwerp… (—; hoog) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: op_merk) | — | — | — |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | categorietaal of lage distinctiviteit: de branddatum op de zak is gangbaar bi… (—; laag) | 4 | 2 | categorietaal | bewijs | category_fallback | **goed** | — | — | — | — |
| Duinroos Thuiszorg | Op merk | hoge brand_contribution, geen risk-flags (—; hoog) | 8 | 4 | op_merk | positionering | — | **goed** | — | 6.5 | proof_gap | fout |
| Duinroos Thuiszorg | Categorietaal | lage distinctiviteit, flag category_fallback; Verzorger zonder bewijs is hygiëne (category_fallback; laag) | 1 | 1 | categorietaal | positionering | category_fallback, proof_gap | **goed** | — | 0.5 | category_fallback, proof_gap, foe_vervaging | goed |
| Duinroos Thuiszorg | Breekt de regel | flag constraint_breach: meer dan vier zorgverleners per cliënt (constraint_breach; laag) | 1 | 0 | regelbreuk | positionering | constraint_breach, foe_vervaging | **goed** | — | 0 | constraint_breach, foe_vervaging, proof_gap | goed |
| Duinroos Thuiszorg | Neutraal / grensgeval | contentrol employer_culture; neutraal hoofdoordeel (werving zonder vier-zorgv… (—; neutraal) | 1 | 0 | neutraal | employer_culture | — | **goed** | — | 0.5 | category_fallback, proof_gap, foe_vervaging | review |
| Duinroos Thuiszorg | Archetype zonder bewijs | distinctiviteit hoogstens 2, flag category_fallback: warm en concreet, maar z… (category_fallback; laag) | 2 | 1 | categorietaal | positionering | category_fallback, proof_gap | **goed** | — | — | — | — |
| Duinroos Thuiszorg | Logica van de tegenkracht | flag foe_vervaging, geen constraint_breach: efficiëntie als prestatie, maar g… (foe_vervaging; laag) | 2 | 1 | tegenkracht | positionering | foe_vervaging, proof_gap | **goed** | — | — | — | — |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | op_merk, proof_gap op de kop (niet onderbouwd resultaat); de tekst keert zich… (proof_gap; neutraal) | 5 | 4 | op_merk | bewijs | proof_gap | **goed** | — | — | — | — |
| Stelwerk | Op merk | hoge brand_contribution, geen risk-flags (—; hoog) | 8 | 4 | op_merk | positionering | proof_gap | **goed** | — | 7.5 | — | goed |
| Stelwerk | Categorietaal | lage distinctiviteit, flag category_fallback (category_fallback; laag) | 1 | 1 | categorietaal | positionering | category_fallback, proof_gap | **goed** | — | 0.5 | foe_vervaging, constraint_breach, category_fallback | goed |
| Stelwerk | Breekt de regel | flag constraint_breach: breekt de commissieregel (constraint_breach; laag) | 0 | 0 | regelbreuk | positionering | constraint_breach | **goed** | — | 0 | constraint_breach, foe_vervaging, archetype_drift | goed |
| Stelwerk | Neutraal / grensgeval | contentrol educatie; neutraal hoofdoordeel, lage bijdrage aan de architectuur (—; neutraal) | 1 | 0 | neutraal | educatie | opportunistische_trendcontent | **goed** | — | 1 | opportunistische_trendcontent, archetype_drift, proof_gap | review |
| Stelwerk | Branchetaal uit de verboden logica | categorietaal met category_fallback en proof_gap op "volledig onafhankelijk";… (category_fallback, proof_gap; laag) | 2 | 0 | categorietaal | positionering | category_fallback, proof_gap | **goed** | — | — | — | — |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | proof_gap op de samenwerkings- of ervaringszin, merkbijdrage hoogstens 6 (proof_gap; neutraal) | 6 | 3 | op_merk | positionering | proof_gap | **goed** | — | — | — | — |

### Reviewlijst (geen auto goed/fout)

_Geen._

---

## S4 — Onderzoek (geen bouw)

Datum: 2026-10-09. Alleen bevindingen; geen codewijzigingen zonder akkoord Rogier/Erik.

### Duinroos — merkarchetype = branchematch (Verzorger)

- **Testmerk:** archetype *Verzorger* (Duinroos Thuiszorg).
- **Branche bij import:** `zorg_welzijn` uit seed (`markt_archetype` = Verzorger).
- **Architectuurcheck:** vergelijkt scherpte/uniciteit tegen branche-hygiëne en categorietaal; er is **geen aparte code** die bij “hero = markt-archetype” automatisch een zwaardere bewijslast oplegt in `ce_hba_score_*` of `hba-prompt.php`.
- **Hygiëne-pipeline:** `hygiene-repository.php` kent branche-hygiëne; dat is generiek, geen Verzorger-dubbelregel.
- **Conclusie:** Rogiers verwachting (“zwaardere bewijslast als merk- en categorie-archetype samenvallen”) is **niet expliciet geïmplementeerd**. Alleen impliciet via lage distinctiviteit / category_fallback als de post op hygiëne lijkt.

### Vertaling testmerken → app (platform & volume)

- **JSON:** `website_blog`, `newsletter`, `target_post_volume_per_month`.
- **Import:** `ce_hba_testpost_platform_db()` mapt `website_blog` en `newsletter` → `blog`; onbekend → `blog`.
- **Scoring:** baseline/HBA-score voor testposts gebruikt in de praktijk **`linkedin`** als platform op het artikel (ook Instagram/Facebook-posts), omdat nulmeting via `linkedin_posts` loopt.
- **Volume/ritme:** provisioning zet `posts_per_week` (default 1) en `ce01_weekly_schedule = 0`; maandvolume uit JSON wordt **niet** overgenomen.
- **Impact op score:** platform beïnvloedt prompt (`ce_hba_build_system_prompt`); blog-post (Brandpunt post 4) wordt als LinkedIn gescoord → **mogelijk andere meetlat** dan bedoeld in JSON. Noteer bij interpretatie post 4 Brandpunt.

### Constraint_breach zichtbaar voor klant (briefing §7.3)

- **Backend:** flag `constraint_breach` in `architecture_risk_flags` (HBA outputschema).
- **Frontend:** `ArticleHbaSection` + `hbaScoreDisplay.ts` — `constraint_breach` heeft vaste klantzin (“Je overschrijdt hier een grens die je zelf hebt gezet.”) en valt onder `buildHbaFinding` / gerankte flags.
- **Conclusie:** **ja**, mits de scorer de flag zet en het artikelpaneel HBA-score toont (guardrail PASS).
- **Na S2:** zelfcontrole (`self_review.regelbreuk`) en CE-04 (`regelbreuk` → feedbackregel) zijn gebouwd; stap 4 op staging (2026-10-09) gaf nog **geen** expliciete regelzin in `article_feedback` — zie protocol stap 4.

---

# HBA-testmerken — stabiliteit (v1.12)

Gegenereerd: 2026-10-19 17:41:32 UTC
Promptversie: v1.12
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 13 · **instabiel:** 3 · **fout:** 3 · **review:** 0 · **niet beoordeeld:** 0

`category_fallback` in laatste run: 7 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 95 scores)

Flags op functionele onderdelen (doel 0): **0**

`constraint_breach` behouden na verificatie: **15** · verworpen door verificatie: **0** · vervallen zonder uitleg: **0**

Ruwe JSON: `hba-testmerken-stability-v1.12-5runs.json`

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

---

## Testprotocol stap 3–4

**Omgeving:** staging, 2026-10-09  
**HBA-score prompt:** `v1.5` (`CE_HBA_PROMPT_VERSION`)  
**CE-04 / CE-03 schrijfmodel:** `claude-sonnet-4-6` (n8n)

### Stap 3 — CE-03 verleiding

| Merk | Thema (exact) | Slot/article-id | Tekst breekt regel? | Zelfcontrole meldt regelbreuk? | HBA-score BC | Distinct. | Flags | Opmerking |
| --- | --- | --- | --- | --- | ---: | ---: | --- | --- |
| Brandpunt Koffie | Black Friday-actie | — | — | — | — | — | — | **Niet uitgevoerd:** geen content-bronnen op testtenants → CE-02 vult geen research → CE-03 vereist ≥3 bruikbare research-items. Thema aanmaken zonder bronnen/research heeft geen zin. |
| Stelwerk | Nieuw partnerschap met een ERP-leverancier | — | — | — | — | — | — | Idem. |
| Duinroos Thuiszorg | Wachtlijst in één maand weggewerkt | — | — | — | — | — | — | Idem. |

**Voor Erik vóór stap 3:** per testmerk minimaal 1–2 echte Sources (RSS/scraper) + CE-02, **of** handmatig ≥3 research-items op het thema-slot.

**Kanttekening:** zelfde model schrijft en beoordeelt (stap 3) → noteer model-id + promptversie.

**S3 (GRENZEN via API):** geïmplementeerd en op staging — CE-03 haalt `grenzen_block` / `write_directive` / NL-blok op via `POST /api/ce03/slot-meta` als de webhook alleen `slot_id` stuurt. Zie `docs/hba-testmerken-ce03-s3.md`.

### Stap 4 — CE-04 review (post 3 = *Breekt de regel*)

CE-04 getriggerd via `ce04-review-staging` met bestaande nulmeting-posts (geen nieuwe thema’s).

| Merk | Article-id / slot | AI-review noemt regelbreuk? | Citaat review | Overeenkomst met HBA `constraint_breach`? |
| --- | --- | --- | --- | --- |
| Brandpunt Koffie | `a3cb7f24-…` / `3633e7a3-cb92-4030-aabf-85ba9004c072` | **Nee** (alleen fallback “Geen specifieke feedback.”) | — | **Ja** — HBA v1.5: BC 0, flags `constraint_breach` |
| Stelwerk | `429e6128-…` / `9a294736-4097-4ea4-82f7-e5a190fe8488` | **Nee** (zelfde fallback) | — | **Ja** — BC 0, `constraint_breach`, `foe_vervaging` |
| Duinroos Thuiszorg | `0d0b0b90-…` / `eb743077-0eaf-405e-8222-ae65d4097a55` | **Nee** (zelfde fallback) | — | **Ja** — BC 0.5, `constraint_breach`, `foe_vervaging` |

**Conclusie stap 4:** HBA-scorer herkent regelbreuk op post 3; **CE-04 review** (S2-prompt met `regelbreuk`-veld) leverde in deze run **geen** expliciete regelzin (“Dit stuk gaat in tegen je regel dat …”). Follow-up: opnieuw testen na prompt-tuning of andere run; execution-logs in n8n controleren of `regelbreuk` in tool-output stond.

### Vragen voor Rogier (Erik legt voor — niet gebouwd)

1. **Merkcijfer:** positioneringsscore deelt door 5 terwijl HBA 0–10 gebruikt — bedoeling of /10?
2. **Drempels flags:** wanneer `category_fallback` / `proof_gap` — op welke distinctiviteit of BC?
3. **Duinroos:** gewenste “zwaardere bewijslast” als merk-archetype = branche-archetype (S4: nu niet expliciet in code).
4. **Grensgevallen:** extra testposts gewenst?
5. **Stabiliteit:** is `proof_gap` op post 1 (*Op merk*) acceptabel of te streng na S2-definitie?

---

# HBA-testmerken — stabiliteit (v1.13)

Gegenereerd: 2026-10-22 10:24:21 UTC
Promptversie: v1.13
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 19 · **instabiel:** 0 · **fout:** 0 · **review:** 0 · **niet beoordeeld:** 0

`category_fallback` in laatste run: 8 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 95 scores)

Flags op functionele onderdelen (doel 0): **0**

`constraint_breach` behouden na verificatie: **15** · verworpen door verificatie: **1** · vervallen zonder uitleg: **0**

Ruwe JSON: `hba-testmerken-stability-v1.13-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Brandpunt Koffie | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 1.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Brandpunt Koffie | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Brandpunt Koffie | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 5/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Branchetaal uit de verboden logica | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 1.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |

### V0 — self_review en hbaHint

- **Brandpunt Koffie:** self_review=gevuld · hint=gevuld
- **Stelwerk:** self_review=gevuld · hint=leeg
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
- [x] Flags op functionele onderdelen = 0
- [ ] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **8/19** posts.
