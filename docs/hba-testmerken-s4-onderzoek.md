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
