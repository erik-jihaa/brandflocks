# HBA-testmerken — resultaat

Gegenereerd: 2026-10-13 11:29:35 UTC
Omgeving: staging
Promptversie: v1.9
Modellen (scores): —

*Let op: scores zijn niet (allemaal) gemaakt met de huidige promptversie v1.9 — herimporteer de testposts om opnieuw te scoren. Testmerken op staging kunnen guardrail PASS geforceerd hebben voor scoring.*

## Meting v1.9 (na S2)

Vóór-kolommen: nulmeting v1.3 uit `docs/hba-testmerken-matrix-v1.3.json`.

Samenvatting: **0 goed**, **0 fout**, **3 review**, **14 zonder score**.

| Merk | Post | Verwacht | BC | Distinct. | Hoofdoordeel | Contentrol | Flags (werkelijk) | Oordeel | Toelichting | Vóór: BC | Vóór: flags | Vóór: oordeel |
| --- | --- | --- | ---: | ---: | --- | --- | --- | --- | --- | ---: | --- | --- |
| Brandpunt Koffie | Op merk | hoge brand_contribution, geen risk-flags (—; hoog) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: op_merk) | 7 | — | goed |
| Brandpunt Koffie | Categorietaal | lage distinctiviteit, flag category_fallback (category_fallback; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: categorietaal); Ontbreekt verwachte flag: category_fallback | 1 | category_fallback, proof_gap, constraint_breach | goed |
| Brandpunt Koffie | Breekt de regel | flag constraint_breach: breekt de zes-wekenregel (constraint_breach; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: regelbreuk); Ontbreekt verwachte flag: constraint_breach | 0 | constraint_breach, opportunistische_trendcontent, foe_vervaging | goed |
| Brandpunt Koffie | Neutraal / grensgeval | contentrol educatie of service, neutraal, lage bijdrage aan de architectuur (—; neutraal) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: neutraal) | 0.5 | category_fallback, foe_vervaging | goed |
| Brandpunt Koffie | Verdedigt de regel | hoge brand_contribution, geen constraint_breach: noemt het verboden onderwerp… (—; hoog) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: op_merk) | — | — | — |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | categorietaal of lage distinctiviteit: de branddatum op de zak is gangbaar bi… (—; laag) | — | — | — | — | — | **review** | Ambigue verwachting — handmatige beoordeling | — | — | — |
| Duinroos Thuiszorg | Op merk | hoge brand_contribution, geen risk-flags (—; hoog) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: op_merk) | 6.5 | proof_gap | fout |
| Duinroos Thuiszorg | Categorietaal | lage distinctiviteit, flag category_fallback; Verzorger zonder bewijs is hygiëne (category_fallback; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: categorietaal); Ontbreekt verwachte flag: category_fallback | 0.5 | category_fallback, proof_gap, foe_vervaging | goed |
| Duinroos Thuiszorg | Breekt de regel | flag constraint_breach: meer dan vier zorgverleners per cliënt (constraint_breach; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: regelbreuk); Ontbreekt verwachte flag: constraint_breach | 0 | constraint_breach, foe_vervaging, proof_gap | goed |
| Duinroos Thuiszorg | Neutraal / grensgeval | contentrol employer_culture; neutraal tenzij de teamregel erin staat (—; neutraal) | — | — | — | — | — | **review** | Ambigue verwachting — handmatige beoordeling | 0.5 | category_fallback, proof_gap, foe_vervaging | review |
| Duinroos Thuiszorg | Archetype zonder bewijs | distinctiviteit hoogstens 2, flag category_fallback: warm en concreet, maar z… (category_fallback; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: categorietaal); Ontbreekt verwachte flag: category_fallback | — | — | — |
| Duinroos Thuiszorg | Logica van de tegenkracht | flag foe_vervaging, geen constraint_breach: efficiëntie als prestatie, maar g… (foe_vervaging; laag) | — | — | — | — | — | **fout** | Ontbreekt verwachte flag: foe_vervaging | — | — | — |
| Stelwerk | Op merk | hoge brand_contribution, geen risk-flags (—; hoog) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: op_merk) | 7.5 | — | goed |
| Stelwerk | Categorietaal | lage distinctiviteit, flag category_fallback (category_fallback; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: categorietaal); Ontbreekt verwachte flag: category_fallback | 0.5 | foe_vervaging, constraint_breach, category_fallback | goed |
| Stelwerk | Breekt de regel | flag constraint_breach: breekt de commissieregel (constraint_breach; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: regelbreuk); Ontbreekt verwachte flag: constraint_breach | 0 | constraint_breach, foe_vervaging, archetype_drift | goed |
| Stelwerk | Neutraal / grensgeval | mogelijk flag opportunistische_trendcontent, tenzij gekoppeld aan doel of teg… (opportunistische_trendcontent; neutraal) | — | — | — | — | — | **review** | Ambigue verwachting — handmatige beoordeling | 1 | opportunistische_trendcontent, archetype_drift, proof_gap | review |
| Stelwerk | Branchetaal uit de verboden logica | lage distinctiviteit, flag category_fallback, geen constraint_breach: onafhan… (category_fallback; laag) | — | — | — | — | — | **fout** | hoofdoordeel ontbreekt (verwacht: categorietaal); Ontbreekt verwachte flag: category_fallback | — | — | — |

### Reviewlijst (geen auto goed/fout)

- **Brandpunt Koffie — Mechanisme dat markthygiëne is:** categorietaal of lage distinctiviteit: de branddatum op de zak is gangbaar bij specialty-branders; de zes-wekenregel staat er niet in
- **Stelwerk — Neutraal / grensgeval:** mogelijk flag opportunistische_trendcontent, tenzij gekoppeld aan doel of tegenkracht
- **Duinroos Thuiszorg — Neutraal / grensgeval:** contentrol employer_culture; neutraal tenzij de teamregel erin staat

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

# HBA-testmerken — stabiliteit (v1.9)

Gegenereerd: 2026-10-13 11:51:09 UTC
Promptversie: v1.9
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 0 · **instabiel:** 14 · **fout:** 0 · **review:** 3

`category_fallback` in laatste run: 5 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 68 scores)

Flags op functionele onderdelen (doel 0): **0**

Ruwe JSON: `hba-testmerken-stability-v1.9-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 4/5 | regelbreuk, —, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Brandpunt Koffie | Categorietaal | 4/5 | categorietaal, —, categorietaal, categorietaal, categorietaal | 0.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 0/5 | op_merk, —, op_merk, op_merk, op_merk | 0.5 |
| Brandpunt Koffie | Neutraal / grensgeval | 4/5 | neutraal, —, neutraal, neutraal, neutraal | 1.0 |
| Brandpunt Koffie | Op merk | 4/5 | op_merk, —, op_merk, op_merk, op_merk | 0.5 |
| Brandpunt Koffie | Verdedigt de regel | 4/5 | op_merk, —, op_merk, op_merk, op_merk | 0.5 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 4/5 | categorietaal, —, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Breekt de regel | 4/5 | regelbreuk, —, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Duinroos Thuiszorg | Categorietaal | 4/5 | categorietaal, —, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 4/5 | tegenkracht, —, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 3/5 | neutraal, —, neutraal, neutraal, neutraal | 0.5 |
| Duinroos Thuiszorg | Op merk | 4/5 | op_merk, —, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Branchetaal uit de verboden logica | 4/5 | categorietaal, —, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Breekt de regel | 4/5 | regelbreuk, —, regelbreuk, regelbreuk, regelbreuk | 0.0 |
| Stelwerk | Categorietaal | 4/5 | categorietaal, —, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Neutraal / grensgeval | 3/5 | neutraal, —, neutraal, neutraal, neutraal | 0.0 |
| Stelwerk | Op merk | 4/5 | op_merk, —, op_merk, op_merk, op_merk | 0.0 |

### Instabiele posts (met citaten uit afwijkende runs)

- **Brandpunt Koffie — Breekt de regel:** 4 goed runs; hoofdoordeel: ['regelbreuk', None, 'regelbreuk', 'regelbreuk', 'regelbreuk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Brandpunt Koffie — Categorietaal:** 4 goed runs; hoofdoordeel: ['categorietaal', None, 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Brandpunt Koffie — Neutraal / grensgeval:** 4 goed runs; hoofdoordeel: ['neutraal', None, 'neutraal', 'neutraal', 'neutraal']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Brandpunt Koffie — Op merk:** 4 goed runs; hoofdoordeel: ['op_merk', None, 'op_merk', 'op_merk', 'op_merk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Brandpunt Koffie — Verdedigt de regel:** 4 goed runs; hoofdoordeel: ['op_merk', None, 'op_merk', 'op_merk', 'op_merk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Duinroos Thuiszorg — Archetype zonder bewijs:** 4 goed runs; hoofdoordeel: ['categorietaal', None, 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Duinroos Thuiszorg — Breekt de regel:** 4 goed runs; hoofdoordeel: ['regelbreuk', None, 'regelbreuk', 'regelbreuk', 'regelbreuk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Duinroos Thuiszorg — Categorietaal:** 4 goed runs; hoofdoordeel: ['categorietaal', None, 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Duinroos Thuiszorg — Logica van de tegenkracht:** 4 goed runs; hoofdoordeel: ['tegenkracht', None, 'tegenkracht', 'tegenkracht', 'tegenkracht']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Duinroos Thuiszorg — Op merk:** 4 goed runs; hoofdoordeel: ['op_merk', None, 'op_merk', 'op_merk', 'op_merk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Stelwerk — Branchetaal uit de verboden logica:** 4 goed runs; hoofdoordeel: ['categorietaal', None, 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Stelwerk — Breekt de regel:** 4 goed runs; hoofdoordeel: ['regelbreuk', None, 'regelbreuk', 'regelbreuk', 'regelbreuk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Stelwerk — Categorietaal:** 4 goed runs; hoofdoordeel: ['categorietaal', None, 'categorietaal', 'categorietaal', 'categorietaal']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —
- **Stelwerk — Op merk:** 4 goed runs; hoofdoordeel: ['op_merk', None, 'op_merk', 'op_merk', 'op_merk']
  - Run 2: oordeel **fout**, hoofdoordeel None, flags []; citaten: —

### V0 — self_review en hbaHint

- **Brandpunt Koffie:** self_review=gevuld · hint=gevuld
- **Stelwerk:** self_review=gevuld · hint=gevuld
- **Duinroos Thuiszorg:** self_review=gevuld · hint=gevuld

### Klaar-criteria (alle runs)

- [ ] Post 3 Brandpunt Koffie
- [ ] Post 2 Brandpunt Koffie
- [ ] Post 1 Brandpunt Koffie
- [ ] Post 4 Brandpunt Koffie
- [ ] Post 3 Stelwerk
- [ ] Post 2 Stelwerk
- [ ] Post 1 Stelwerk
- [ ] Post 4 Stelwerk
- [ ] Post 3 Duinroos Thuiszorg
- [ ] Post 2 Duinroos Thuiszorg
- [ ] Post 1 Duinroos Thuiszorg
- [ ] Post 4 Duinroos Thuiszorg
- [ ] Brandpunt post 5
- [ ] Duinroos post 6
- [x] BC-spreiding ≤ 1 (alle posts)
- [x] hoofdoordeel_gecorrigeerd ≤ 5%
- [x] Flags op functionele onderdelen = 0
- [x] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **5/17** posts.

---

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

---

# HBA-testmerken — stabiliteit (v1.10)

Gegenereerd: 2026-10-13 18:59:10 UTC
Promptversie: v1.10
Runs per post: 5
Modellen: claude-sonnet-4-6

**Stable goed (5/5):** 16 · **instabiel:** 0 · **fout:** 0 · **review:** 3

`category_fallback` in laatste run: 6 posts · `hoofdoordeel_gecorrigeerd`: 0 keer (0.0% van 95 scores)

Flags op functionele onderdelen (doel 0): **0**

`constraint_breach` behouden na verificatie: **15** · verworpen door verificatie: **0** · vervallen zonder uitleg: **0**

Ruwe JSON: `hba-testmerken-stability-v1.10-5runs.json`

| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |
| --- | --- | ---: | --- | ---: |
| Brandpunt Koffie | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 1.0 |
| Brandpunt Koffie | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 1.0 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Brandpunt Koffie | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 1.0 |
| Brandpunt Koffie | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Brandpunt Koffie | Verdedigt de regel | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Duinroos Thuiszorg | Archetype zonder bewijs | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 1.0 |
| Duinroos Thuiszorg | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 1.0 |
| Duinroos Thuiszorg | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Duinroos Thuiszorg | Logica van de tegenkracht | 5/5 | tegenkracht, tegenkracht, tegenkracht, tegenkracht, tegenkracht | 0.0 |
| Duinroos Thuiszorg | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Duinroos Thuiszorg | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 1.0 |
| Stelwerk | Branchetaal uit de verboden logica | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Breekt de regel | 5/5 | regelbreuk, regelbreuk, regelbreuk, regelbreuk, regelbreuk | 1.0 |
| Stelwerk | Categorietaal | 5/5 | categorietaal, categorietaal, categorietaal, categorietaal, categorietaal | 0.0 |
| Stelwerk | Neutraal / grensgeval | 5/5 | neutraal, neutraal, neutraal, neutraal, neutraal | 0.0 |
| Stelwerk | Op merk | 5/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | 0/5 | op_merk, op_merk, op_merk, op_merk, op_merk | 0.0 |

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
- [x] Flags op functionele onderdelen = 0
- [x] Brandpunt post 6 (review)

Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. Na R2 laatste run: **6/19** posts.
