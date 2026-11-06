# Content Engine 1.0 — Launch-checklist

Gebruik dit document om systematisch te controleren of alle functies werken
en de getoonde data klopt. Loop de checklist van boven naar beneden door —
de volgorde volgt de normale redactionele workflow.

Omgeving: [https://ce.igisnext.com/](https://ce.igisnext.com/)
Test-klant: iGiSNext (client_id: 016384af-b26e-4415-aa9c-abc46b804798)

Laatste update: 29 juli 2026

**Afvinken:** items gebruiken markdown `- [x]` (gedaan) en `- [ ]` (open). Die vinkjes **blijven staan** in dit bestand (Dropbox/git) — alleen handmatig wijzigen; er is geen UI die ze reset.

---

## Pre-flight (vóór de walkthrough)

Loop dit blok **één keer** door vóór sectie 0 — scheelt tijd en voorkomt valse negatives.

### Omgeving en deploy

- [x] Productie bereikbaar: `GET /api/health` → ok
- [x] `CE_AUTH_DISABLED=0` in productie (login verplicht)
- [x] n8n-workflows recent gedeployed (minimaal CE-01, CE-02, CE-01b, **CE-03**, CE-04, CE-04.1bis, CE-07):
  - `./scripts/push-all-ce-n8n.sh` of per flow (`push-ce03-n8n.sh`, …)
- [x] `.env` / `ce.local.php`: webhooks, `CE_MAIL_FROM`, `WORDPRESS_*` ingevuld
- [x] Backup gemaakt: `./scripts/backup.sh` → `../ce-backup-20260717-173642.zip` (26M)

### Testdata op iGiSNext

- [x] **Strategy** ingevuld (archetype, adversary, schrijfwijzer)
- [ ] **Objective** — planning horizon + posts/week; optioneel objectives (config-UI live, editorial koppeling nog open — §3)
- [x] **Minimaal 1 bron** per relevant tab (RSS + optioneel Scraper/Trello)
- [x] **Agenda** deels gevuld via normale CE-flow (CE-01 → CE-02 → Theme Submit → CE-03):
  - 2 slots per week voor minstens 2–3 komende weken (`posts_per_week = 2` — instellen onder **Objective → Planning**)
  - Minstens **één slot per editorial status** om §8–11 te testen:
    - Draft (CE-03 gedraaid, nog niet ingediend)
    - Pending (`pending_review` na human submit)
    - Feedback (na ai-review of open review-edits)
    - Approved (klaar voor Publish WP)
- [x] **Geen mass-delete** op productie tijdens test — Delete per slot testen op één wegwerp-slot

### Handige referenties


| Document                        | Pad                                                      |
| ------------------------------- | -------------------------------------------------------- |
| Bouwstatus + changelog          | `docs/BUILD-NOTES.md`                                    |
| Objectives (briefing)           | `briefing/cursor-objectives.md`                          |
| Objectives migratie             | `scripts/migrations/add_objectives_table.sql`            |
| E2E human review                | `./scripts/test-ce04-human-review-flow.sh [slotId]`      |
| CE-03 handmatig triggeren       | `POST` naar `N8N_CE03_WEBHOOK_URL` met `{"slot_id":"…"}` |
| Admin herstel (alleen incident) | `POST /api/admin/agenda-repair` + `X-CE-Reset-Token`     |
| Theme-refill (horizon-weken)    | `POST /api/admin/theme-refill` + `X-CE-Reset-Token`      |


---

## 0. Toegang en account

- [x] Loginpagina toont iGiSNext-logo (platform-logo, niet tenant-logo)
- [x] Inloggen met correct wachtwoord werkt
- [x] Onjuist wachtwoord geeft foutmelding zonder pagina-crash
- [x] "Wachtwoord vergeten" stuurt mail naar contact_email
- [x] Reset-link in mail opent `/reset-password/:token`
- [x] Nieuw wachtwoord instellen werkt, token vervalt daarna
- [x] Uitloggen werkt; sessie is daarna ongeldig (niet terug via browser-back)

---

## 1. Account-scherm (`/account`)

- [x] Bedrijfsnaam, naam, rol-pulldown, contact-email, reviewer-email tonen correcte waarden uit DB
- [x] Logo-upload werkt en toont preview
- [x] Wachtwoord wijzigen werkt
- [x] Opslaan geeft bevestiging; herladen toont gewijzigde waarden
- [x] Rol-pulldown: Contact / Reviewer / Both — schakelt reviewer-emailveld correct in/uit
- [x] **Planning horizon** en **Posts per week** staan **niet** meer op Account — zie §3 Objective → Planning

---

## 2. Strategy-scherm (`/strategy`)

- [x] Archetype-veld toont De Wijze-tekst (of wat ingevuld is)
- [x] Adversary-veld toont de iGiSNext-tegenstander-tekst
- [x] Brandstory en Schrijfwijzer tonen correcte inhoud
- [x] Bewerken en opslaan werkt; herladen toont wijzigingen
- [x] "Use this content to write article rational" hint is zichtbaar

---

## 3. Objective-scherm (`/targets`)

Sidebar-label: **Objective** (route `/targets`). Doelstellingen + planning voor CE-01 horizon en weekquota.

### Planning (verhuisd van Account)

- [ ] Paginatitel **Objective**; navigatie onder Strategy
- [ ] **Planning horizon** (weken) toont waarde uit DB (`clients.planning_horizon_weeks`)
- [ ] **Post per week** toont waarde uit DB (`clients.posts_per_week`)
- [ ] **Checks per week** toont waarde uit DB (`clients.source_checks_per_week`); default 1
- [ ] Wijziging planning wordt automatisch opgeslagen (debounce) via `PATCH /api/account`
- [ ] n8n **CE-Sources Sync** (uurlijks) checkt alleen tenants waar interval verstreken is (`GET/POST /api/sources/sync`)
- [ ] Theme-weekquota en hint "Meer posts per week? … Objective → Planning" kloppen met ingestelde waarde

### Objectives (doelstellingen)

- [ ] Lege staat: knop **+ Add objective** zichtbaar
- [ ] Uitklapbare entry: **Aandeel % in totale media output** (slider), **Prompt instructie**, **Schrijfstijl** (pulldown uit Strategy tone_styles)
- [ ] **Save** slaat objective op (`PUT /api/objectives`) en planning mee
- [ ] **Undo** sluit editor zonder opslaan
- [ ] Ingeklapte rij toont `{label} — {percentage}%`; klik opent editor opnieuw
- [ ] Maximaal **6** objectives
- [ ] Totaalregel **Totaal: N%** alleen zichtbaar als totaal > 0%
- [ ] Totaal groen bij ≤100%; rood + "moet 100% zijn" bij >100%
- [ ] Label afgeleid van eerste regel prompt instructie (min. 3 tekens)
- [ ] Herladen toont opgeslagen objectives en planning

### Integratie editorial flow (nog niet in 1.0 — geen blocker voor config-test)

- [ ] Objective-label/badge op Theme-voorstellen (CE-01)
- [ ] Read-only objective in Research- en Article-meta
- [ ] CE-01/02/03 n8n: objectives + % in prompts; `themes` / `content_slots` / `articles` objective-kolommen
- [ ] Sources-mappen per objective + Algemeen (#33)

---

## 4. Sources-scherm (`/sources`)

- [x] Alle vijf tabbladen werken: URL/RSS, Scraper, Mailbox, Analythics, Trello
- [x] Bestaande bronnen tonen in de juiste tab
- [x] **Check-knop**: groene stip bij bereikbare bron, rode stip bij onbereikbare
- [x] **Scraper**: Budget-actie opent budgetinstelling; Confirm Delete verschijnt bij verwijderen
- [x] RSS-bron toevoegen: Submit werkt, bron verschijnt in lijst
- [x] Mailbox-bron toevoegen: velden Email, IMAP-host, Password; Check synchroniseert nieuwsbrieven
- [ ] **Mailbox provider-pulldown** (backlog): keuze hosting-type i.p.v. handmatige IMAP-host — zie `docs/BUILD-NOTES.md` → Sources-backlog
- [x] Trello-bron toevoegen: Submit werkt
- [ ] Onboarding-staat (0 bronnen): introkaart + "Add RSS feed"-knop zichtbaar

---

## 5. Home-scherm (`/`)

- [x] **To-do-kolom** (links) toont open taken uit pipeline (`todos`): Strategy, Sources, Theme, Research, Feedback, Draft, Pending, Publish, Engagement
- [x] To-do-links navigeren naar het juiste scherm
- [x] **Result-kolom** (rechts): posts/week, gepubliceerd deze week/maand, angle-mix, engagement, **bronnenmix** (`sourcesReview`)
- [ ] Pipeline-status / stats kloppen met werkelijke aantallen (themes, slots, articles)
- [ ] **nextAction** (één primaire stap) wijst naar de juiste volgende actie:
  - Geen strategy → Strategy
  - 0 bronnen → Sources
  - Theme backlog → Theme
  - Open feedback/draft → Article
  - Pending reviews → Agenda
- [x] Link in nextAction werkt

---

## 6. Theme-scherm (`/theme`)

### Weergave

- [x] Theme-voorstellen tonen titel, rationale en bronlink (geen RSS-feed-URL) — week 33/34 jul 2026 via CE-01 refill
- [x] Bronlink opent juiste artikel (niet news.google.com/rss/...)
- [x] Backlog-navigatie (vorige/volgende week) werkt via `?week=`
- [x] Week vol (quota bereikt): hint **Meer posts per week? Pas het aan onder Objective → Planning.** + link naar Research
- [x] Eigen thema toevoegen: invoerveld + pencil-icon, opslaan werkt

### Review

- [x] Like-knop (gevuld bolletje) en Forget-knop werken per thema
- [x] Liked thema's worden als geliked getoond bij herladen
- [x] **Submit Theme** is alleen actief als er minimaal één thema is geliked
- [x] Na Submit Theme: thema krijgt status `submitted` in DB
- [x] CE-01b wordt getriggerd (slot verschijnt op Agenda in juiste week; fase-label **Theme** of **Research**)

### Data-controle

- [x] Thema-rationale klopt inhoudelijk met archetype/adversary van iGiSNext
- [x] Angle-suggestie per thema past bij het onderwerp

---

## 7. Research-scherm (`/research`)

- [x] Research-items tonen titel, snippet en item_rationale per item
- [ ] ~~**Betrouwbaarheidsbadge** (hoog/gemiddeld/laag)~~ — **niet in 1.0** (geen UI/DB; zie §19)
- [ ] ~~**Contrast-badge** bij stance = contrasts~~ — **niet in 1.0** (geen UI/DB; zie §19)
- [x] Like en Forget werken; wijzigingen **lokaal** tot **Submit** (geen API-save per klik)
- [x] **Submit** (na liking) triggert CE-01b/agenda-update
- [x] Geen RSS-feed-URLs zichtbaar als bronlinks

---

## 8. Agenda-scherm (`/agenda`)

### Weergave

- [x] Paginatitel toont "Agenda _ {vandaag}" (bv. "Agenda _ 16 Jul 2026")
- [x] Weekheaders tonen "Week {n} _ {start} - {einde}" in juist formaat
- [x] Verlopen weken: titel + acties beige; Archive en Delete blijven zwart
- [x] Fase-labels correct: Theme / Research / Article naast de juiste slots
- [x] Slots gegroepeerd in juiste ISO-week (via `themes.cycle_week`, niet verkeerde week door funnel-slug in `phase_key`)

### Statussen

- [x] Draft-slots tonen "Edit | Delete | Draft | Archive"
- [x] Pending-slots tonen "Ingediend — wacht op hoofdredacteur"
- [x] Feedback-slots tonen "Feedback"-label
- [x] Approved-slots tonen "Approved"-label
- [x] Published-slots tonen "Published | View | Archive"
- [x] Campaign-slots tonen "Campaign"-actie die linkt naar `/campaigns/:id`

### Acties

- [x] **Edit** opent correct Article-scherm (Grid #2)
- [x] **Delete** verwijdert slot volledig (slot verdwijnt van Agenda)
- [x] **Delete bij Published**: werkt (FK-safe); WP-post op igisnext.com blijft staan
- [x] **Archive** navigeert naar archief-formulier

---

## 9. Article-scherm (`/article/:id`, Grid #2)

### Meta-kolom (links)

- [x] Rationale toont AI-gegenereerde rationale (inklapbaar)
- [x] Tone: 7 opties (b to b, b to c, internal, partners, personal, archetype, adversary)
- [x] Angle: 3 opties (archetype, adversary, general)
- [x] Image ratio: square / landscape
- [x] Platform: Linkedin / Instagram / X / Facebook / Blog
- [x] AI-suggestie (badge) zichtbaar bij elke keuze
- [x] Geselecteerde waarde toont gevuld bolletje
- [x] Wijziging opslaan via Save schrijft `*_final`-veld

### Aandachtspunten

- [x] Als `self_review` gevuld is: Aandachtspunten-blok zichtbaar (inklapbaar)
- [x] Als `self_review` leeg of null: blok niet zichtbaar

### Hoofdtekst

- [x] Headline bewerkbaar, opslaan werkt
- [x] Body-tekst bewerkbaar, opslaan werkt (content_human, niet content_ai overschrijven)
- [x] Hashtag-veld bewerkbaar
- [ ] Source article-link zichtbaar en correct

### Editor Questions (MessageFatigue)

- [x] Onderaan de draft: marker `---` + `REDACTEUR — vul aan:` zichtbaar
- [x] **4 vragen** met `→`-prefix in **rood** (`text-ce-red`)
- [x] Vragen zijn **artikel-specifiek** (verwijzen naar concrete zin/claim in de draft — niet alleen generieke fallback)
- [x] Vragen nodigen uit tot persoonlijke inzichten: anekdote, cijfer, tegengeluid, twijfel (wat AI niet weet)
- [x] Vragen blijven zichtbaar na herladen; verdwijnen **niet** bij open review-edits
- [x] Bij **Publish WP** / copy voor publicatie: REDACTEUR-block **niet** mee (gestript uit body)

### Afbeelding

- [x] Upload 1080×1080 werkt, preview zichtbaar
- [x] Upload 1200×644 werkt, preview zichtbaar
- [x] Afbeelding blijft na herladen

### Knoppen

- [x] **Save** slaat op zonder statuswijziging
- [x] **Submit for approval** stuurt mail + zet status op `pending_review`
- [ ] **ai-review** triggert CE-04 → feedback verschijnt
- [x] **Publish WP** (alleen bij approved + Blog): publiceert op igisnext.com

---

## 10. Feedback-scherm (`/article/:id/feedback`, Grid #3)

- [x] Header bewerkbaar binnen feedback-layout (niet naar Grid #2)
- [x] **Body copy bewerkbaar binnen feedback-layout** (geen sprong naar Grid #2)
- [x] Feedback-kaarten tonen per wijziging: excerpt + comment + okay | delete
- [x] **Okay** accepteert de wijziging (tekst bijgewerkt)
- [x] **Delete** verwerpt de wijziging (oorspronkelijke tekst blijft)
- [x] Image feedback zichtbaar op slot-niveau
- [x] Labels blijven na handmatige tekstedits
- [x] Na verwerken: Submit opnieuw → mail naar reviewer met "Herzien" in onderwerpregel

---

## 11. Reviewpagina (`/review/:token`)

- [x] Pagina toegankelijk zonder login (publiek via token)
- [x] Artikel-tekst zichtbaar en bewerkbaar
- [x] Rationale zichtbaar
- [x] **Approved**-knop zet status op `approved`
- [x] **Feedback**-knop slaat wijzigingen op als `article_review_edits`
- [x] **Approved** / **Feedback** zonder instructietekst erboven
- [x] Verlopen of al-gebruikte token geeft nette foutmelding (niet blank scherm)

---

## 12. Fastlane-scherm (`/fastlane`)

- [x] "Theme of the article": 40px pink pill, blauw label, pencil-icon
- [x] Research URL 1/2/3: invoerbaar
- [x] Extra thoughts: invoerbaar
- [x] Tone/Angle/Image ratio/Platform selecteerbaar in meta-sidebar
- [x] Image upload: beide formaten werken
- [x] **Write article** triggert CE-03 en opent artikel op Agenda
- [x] **Undo** wist de invoer en blijft op `/fastlane` (geen terug naar Agenda)

---

## 13. Campaign Planner (`/campaign`)

- [x] Invoer: title, motivation, aantal artikelen, **start- en einddatum** (drie pills op één rij)
- [x] Datum-placeholder verdwijnt bij focus op start/eind
- [x] **Submit** genereert plan via n8n (merkbewust: klant-strategie uit DB)
- [x] Plan toont CampaignPostCards met theme, angle, hook, format, tags
- [x] **Edit** per post opent CampaignPostEdit met meta-sidebar (Tone/Angle/Image ratio/Platform)
- [x] **Add** voegt post toe aan Agenda op correcte week (startdatum + weeknummer)
- [ ] **Add all** voegt alle posts tegelijk toe
- [ ] Plan verdwijnt **niet** bij wegnavigeren (persistent in DB)
- [ ] Hervatten via `/campaigns` → "Continue planning" werkt

### Campaign overzicht (`/campaigns`)

- [x] Lijst toont actieve campagnes met titel, status, post-count, datum
- [x] Detail (`/campaigns/:id`) toont originele briefing + post-status
- [x] **Plan opnieuw** laadt briefing voor in Campaign Planner
- [x] **Campaign**-actie op Agenda linkt correct naar `/campaigns/:id`
- [x] **Campaigns**-link in Extra-sidebar (`/campaigns`)

---

## 14. Archive (`/archive`)

### Lijst

- [x] Gearchiveerde posts tonen per week
- [x] **Search**: pulldown Article/Report, zoekveld, resultaten na klikken Search
- [x] Search-resultaten tonen zowel artikelen als rapporten
- [x] **Back** keert terug naar lijst

### Detail (`/archive/:slotId`)

- [x] Artikel-tekst, afbeelding, Interaction score (Views/Liked/Comments/Traffic site)
- [x] Publish-URL's compact: **Blog →** / **LinkedIn →** / **X →** (geen volledige URL)
- [x] REDACTEUR-vragen niet in archief-body
- [x] Promo budget zichtbaar in Interaction score (incl. €0)
- [x] Notes zichtbaar
- [x] Rationale/Tone/Angle/Image ratio/Platform read-only rechts, standaard ingeklapt
- [x] **Archive**-knop opent Archive this post-formulier

### Archive this post

- [x] Date published, Promo budget, URL article: invoerbaar (placeholders, geen labels)
- [x] Notes via placeholder in pink kader
- [x] **URL-check**: groene stip bij bereikbare URL, rood bij echte 404
- [x] 403/429/social-blokkade → groen met "automated check blocked"
- [x] Auto-check bij vooringevulde URL (blog uit publish)
- [x] **Archive**-knop → bevestigingsscherm met "Yes Archive" / "Keep in agenda"
- [x] Na archiveren: post niet meer bewerkbaar; verschijnt in archief-lijst

---

## 15. Report-scherm (`/report`)

- [ ] Generate met start/einddatum werkt
- [ ] **Engagement strategy**-grafiek toont dagelijkse breakdown Strategy vs General
- [ ] Drie blokken: Articles strategy / Articles Generic / +X% Difference
- [ ] Angle-radar toont Archetype/Adversary/General percentages
- [ ] Tone-radar toont alle tone-opties
- [ ] Engagement-paren: published/average articles, likes, comments, views, site traffic
- [ ] **Insight**-tekst toont AI-gegenereerde synthese (niet leeg)
- [ ] **Download** genereert PDF
- [ ] **Save** slaat rapport op in `performance_reports`
- [ ] Opgeslagen rapport vindbaar via Archive-zoekfunctie (pulldown "Report")

---

## 16. n8n-flows

### CE-01 (Theme Director)

- [ ] Maandag-scheduler draait correct (of handmatig te triggeren)
- [x] Handmatige refill buiten horizon: `POST /api/admin/theme-refill` + CE-01 `refill_cycle_weeks` (jul 2026)
- [x] Nieuwe thema's verschijnen in DB met `status = proposed`
- [x] Rationale per thema gegenereerd (niet leeg)
- [x] Geen RSS-feed-URLs in `source_article_url`

### CE-02 (Research)

- [x] Research-items verschijnen per thema in DB (RSS + mailbox + scraper/LinkedIn jul 2026)
- [ ] `item_rationale` gevuld per item
- [ ] `reliability` ~~en~~ `stance` ~~gevuld per item~~ — **niet in 1.0** (CE-02 schrijft ze niet; CE-03 self-review gebruikt `reliability` alleen impliciet default `medium`)
- [ ] Scraper-run gelogd in `scrape_runs` bij Apify-aanroep

### CE-01b (Theme → Slot)

- [ ] Webhook getriggerd na Theme Submit / Research Submit
- [ ] `content_slots`-rij aangemaakt (`themed` / gepland)
- [ ] Slot zichtbaar op Agenda in juiste week
- [ ] Geen directe CE-03 (scheduler week N+2 of handmatig/Fastlane)

### CE-03 (Rationale & Artikel)

- [ ] Artikel in DB na run (status `draft`)
- [ ] `rationale` gevuld
- [ ] `tone_suggested`, `angle_suggested`, `platform_suggested`, `hashtags_suggested` gevuld
- [ ] **Editor Questions** (node `Claude Editor Questions`): 3–4 vragen in `content_ai` na marker `REDACTEUR — vul aan:`
- [ ] `self_review` JSON gevuld (Stap D — Aandachtspunten in UI)
- [ ] Fastlane-route werkt hetzelfde
- [ ] Deploy actueel: `./scripts/push-ce03-n8n.sh`

### CE-04 (AI-review)

- [ ] Webhook triggert Claude-review
- [ ] `article_feedback` rijen aangemaakt
- [ ] Status artikel → `feedback`

### CE-04.1bis (Human review mail)

- [ ] Mail arriveert bij reviewer met juiste inhoud (artikel-tekst, niet content_ai)
- [ ] Review-link werkt en toont correcte tekst
- [ ] Herziene mail (resubmitted) toont "Herzien" in onderwerp

### CE-07 (Performance rapport)

- [ ] Webhook triggert en vult `performance_reports`
- [ ] Data klopt met wat op het Report-scherm staat

---

## 17. Data-integriteitscontroles

- [ ] `headline_human` vs `headline`: UI toont altijd de juiste versie (`ce_article_headline()`)
- [ ] `content_human` vs `content_ai`: UI toont bewerkte versie als die bestaat; Editor Questions uit `content_ai` blijven zichtbaar tot redacteur ze weghaalt
- [ ] REDACTEUR-block in `content_ai` / API-response; **niet** in WP-publish of gearchiveerde publiceerbare copy
- [ ] Tone/Angle: `*_final` wint boven `*_suggested` als ingevuld
- [ ] Status-mapping (`pending_review` → "Pending" in UI) klopt in alle schermen
- [ ] Geen RSS-feed-URLs zichtbaar in Theme, Research of Article
- [ ] `campaign_id` op content_slots correct gekoppeld bij Campaign-posts

---

## 18. Productie-specifieke checks

- [ ] WordPress-publicatie op igisnext.com werkt (niet op ce.igisnext.com)
- [ ] Gepubliceerde WP-post zichtbaar op `igisnext.com/{slug}/`
- [ ] Overzicht op `igisnext.com/blog/` bijgewerkt
- [ ] `CE_AUTH_DISABLED` staat op `0` (login actief in productie)
- [ ] Scraper-tokens encrypted in DB (niet als plaintext)
- [ ] Review-tokens verlopen na gebruik
- [ ] `CE_MAIL_FROM` correct ingesteld (mail komt niet in spam)

---

## 19. Bekende openstaande punten (geen blocker voor 1.0)

Deze punten zijn bewust niet in 1.0 opgenomen en hoeven niet te worden
afgevinkt voor launch:

- Feedback-pagina: volledige fragment-diff (Niveau A refactor) — MVP single-block werkt
- **Objectives in editorial flow (#34)** — CE-01 objective in thema-prompt; Theme/Research/Article labels; CE-02 bronfilter; CE-03 prompt; Report objective-mix. Config-UI live op `/targets` (§3)
- **Onboarding programma** — gestructureerd traject (Strategy → Objective → Sources → kickstart → Theme) met voortgang en begeleiding; zie `docs/BUILD-NOTES.md` § Onboarding programma (#17)
- **Onboarding-run na Sources** — eenmalige CE-run om rolling agenda te vullen (ook bij handmatige publish_mode); zie `docs/BUILD-NOTES.md`
- Publicatiemodi 2 en 3 (handmatig zonder reviewer, automatisch) — in ontwerp, nog niet gebouwd
- CE-06 Preference Learner — na voldoende data
- Doelgroepveld in Strategy
- Contentpijlers als configureerbaar veld
- KPI-koppeling aan brand_goal in Report
- Research: `reliability` + `stance` in CE-02/DB en badges op `/research` (betrouwbaarheid hoog/gemiddeld/laag; contrast bij `stance = contrasts`)

