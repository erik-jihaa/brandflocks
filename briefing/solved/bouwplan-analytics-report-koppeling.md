# Bouwplan — Analytics-koppeling Overview ↔ Report (Content Engine 1.0)

**Aanleiding:** twijfel of Apify LinkedIn-scraper- en GA4-data goed doorkomen in de
rapportage op het Overview-scherm. Diagnose bevestigt een echte bug + architecturaal
gat. Dit document beschrijft de root cause, de fix (aug 2026, live) en de resterende
fases richting een volwaardige, gedeelde metrics-laag voor Overview én Report.

Laatste update: 3 augustus 2026

---

## 1. Diagnose

### 1.1 Dubbeltelling LinkedIn-engagement (bug)

`ce_linkedin_apply_post_to_article()` schreef bij het matchen van een Apify LinkedIn-post
aan een artikel (via URL-match) de **LinkedIn-reacties** naar de kolom
`articles.interaction_views` — een kolomnaam die overal in de code (Archive, CE-07,
Overview) wordt gelezen als "website-paginaweergaven". Er was **geen enkel** codepad dat
echte GA4-paginaweergaven in die kolom schreef.

Gevolg in `ce_fetch_home_engagement_trend()` (Overview-kaart "Totale engagement"):

- **Bucket "articles"** = som van `interaction_views` (= LinkedIn-reacties, verkeerd
  gelabeld) + `interaction_likes` + `interaction_comments` (= echte LinkedIn-cijfers).
- **Bucket "linkedIn"** = dezelfde reacties/likes/comments, nu rechtstreeks opgeteld uit
  `linkedin_posts`.

Voor elk artikel met een gematchte LinkedIn-post werd diens engagement dus **twee keer**
meegeteld in de cumulatieve trendgrafiek, terwijl GA4 (website-traffic) **nul** invloed
had op het getoonde totaal — exact het beeld dat de gebruiker beschreef.

### 1.2 Zelfde databron in CE-07 (Report)

`scripts/generate-ce07-workflow.py` gebruikt voor de vergelijking "engagement on-strategy
vs. general" en "best performing post" dezelfde velden:
`COALESCE(a.interaction_views,0) + interaction_likes + interaction_comments`. Report leed
dus aan hetzelfde grondprobleem als Overview — beide schermen waren "consistent fout".

### 1.3 Kritiek: Google Analytics Data API staat uit in het gekoppelde Google Cloud-project

Bij live verificatie (zie §2) bleek de GA4-koppeling voor **alle** tenants stuk: elke
call naar `analyticsdata.googleapis.com` geeft **HTTP 403 `SERVICE_DISABLED`**:

> Google Analytics Data API has not been used in project **1032126780556** before or it
> is disabled.

Het OAuth-clientpaar (`GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`, `.env`/`ce.local.php`,
`ce_analytics_oauth_config()`) is **platformbreed** — één Google Cloud-project voor alle
tenants. Omdat de "Google Analytics Data API" in dát project uitstaat, faalt **elke**
GA4-aanroep silent (`ce_analytics_run_report()` geeft `null` terug bij non-2xx, dus geen
crash, gewoon lege/nul-cijfers) voor **elke** tenant met GA4 gekoppeld — niet alleen de
nieuwe per-artikel koppeling uit §2, maar ook de al langer bestaande site-brede cijfers
("Website views deze week" op Home, GA4-sectie op Report).

**Actie (buiten codebase, één keer, platformbreed):**

1. Open <https://console.developers.google.com/apis/api/analyticsdata.googleapis.com/overview?project=1032126780556>
2. Klik **Enable** (inloggen met het Google-account dat bij `GOOGLE_CLIENT_ID` hoort).
3. Wacht een paar minuten (propagatie) en herhaal `POST /api/admin/analytics-sync?client_id=…&debug=1` om te bevestigen dat `matched` > 0 wordt.

Dit verklaart het gevoel van de gebruiker dat "GA4-data niet goed doorkomt" volledig los
van de LinkedIn-dubbeltelling uit §1.1 — zonder deze API-activatie blijft **elke**
GA4-koppeling nul opleveren, hoe goed de code ook matcht.

### 1.4 Geen bestaande GA4 ↔ artikel-koppeling

`metrics-repository.php` haalde GA4-cijfers uitsluitend **site-breed** op (totalen,
top-pagina's op titel) voor de Report-pagina en de "Website views deze week"-regel op
Home. Er bestond geen functie om een GA4 `pagePath` te matchen aan een specifiek,
gepubliceerd artikel. Zonder die koppeling kan `interaction_views` per artikel nooit een
zinvol getal bevatten — welke bron je ook aanspreekt.

---

## 2. Fix — Fase 1 (live, aug 2026)

| Wijziging | Bestand |
|---|---|
| LinkedIn schrijft niet langer naar `interaction_views` (alleen nog `interaction_likes`/`interaction_comments`, echte per-post cijfers) | `linkedin-engagement-repository.php` |
| Nieuwe functies: `ce_analytics_fetch_page_views_by_path()` (1 GA4-call, pagePath → views) en `ce_analytics_sync_article_views()` (matcht op de opgeslagen blog-URL uit `publish_urls`/`social_post_url`, schrijft naar `interaction_views`) | `metrics-repository.php` |
| Overview-trend: "articles"-bucket = alleen echte GA4-views; LinkedIn-likes/comments niet meer meegeteld in die bucket (voorkomt dubbeltelling met de losstaande LinkedIn-bucket) | `home-repository.php`, `HomeEngagementTrend.tsx`, `api/home.ts` |
| Report-generate ververst GA4-attributie **vóór** de CE-07-webhook wordt aangeroepen — zelfde bron als Overview, geen aparte databronnen meer | `report-repository.php` (`ce_generate_report`) |
| GA4-sync automatisch in de uurlijkse sources-cron (analytics-bron, naast de bestaande LinkedIn-scraper-sync) | `sources-sync-repository.php` |
| Handmatige trigger voor test/debug: `GET/POST /api/admin/analytics-sync?client_id=…` (reset-token) | `admin-analytics-sync.php`, `index.php` |
| CE-07 (n8n) **hoefde niet aangepast**: de engagement-formule (`views + likes + comments`) was al correct — ze consumeerde alleen verkeerd-gevulde data. Met echte GA4-views in `interaction_views` is dezelfde workflow nu correct, zonder redeploy. | — |

**Resultaat:** Overview en Report gebruiken vanaf nu dezelfde bron
(`articles.interaction_views` = echte GA4-weergaven, gescheiden van de LinkedIn-tabel)
— geen dubbeltelling meer, en de "via artikelen"-cijfers zijn nu daadwerkelijk website-
verkeer in plaats van verdwaalde LinkedIn-reacties. Archive-detail (Interaction score)
profiteert automatisch mee, geen losse actie nodig.

---

## 3. Resterende fases (niet in 1.0-blocker, wel op de radar)

### Fase 2 — Verificatie op productie

- [x] `POST /api/admin/analytics-sync?client_id=016384af-…&debug=1` handmatig gedraaid op
      iGiSNext → **HTTP 403 `SERVICE_DISABLED`** van `analyticsdata.googleapis.com`
      (zie §1.3). Root cause geïdentificeerd, nog niet opgelost (externe actie nodig).
- [ ] **Blokkerend:** Google Analytics Data API inschakelen in Google Cloud-project
      `1032126780556` (link + stappen in §1.3) — zonder deze stap blijft GA4-attributie
      overal 0 opleveren, ook na de codefix.
- [ ] Na activatie: `POST /api/admin/analytics-sync?client_id=016384af-…&debug=1` opnieuw
      draaien, `matched` > 0 verwachten voor gepubliceerde iGiSNext-artikelen.
- [ ] Overview "Totale engagement" visueel vergelijken vóór/na — cijfer moet dalen
      (dubbeltelling weg) en `via LinkedIn`/`weergaven (GA4)`-labels moeten kloppen.
- [ ] Nieuwe Report genereren en `engagement_on_strategy`/`engagement_general` handmatig
      tegen de brontabel valideren.
- [ ] Tijdelijke raw-debugtak in `admin-analytics-sync.php` (`?raw=1`) opruimen of achter
      een expliciete `CE_DEBUG`-check zetten zodra de GCP-activatie bevestigd is.

### Fase 3 — Koppelfrequentie & performance

- GA4-attributie loopt nu op de bestaande **uurlijkse sources-cron** (`checksPerWeek`
  interval per tenant) én bij elke Report-generate. Bij tenants met veel artikelen kan
  één GA4-call met duizenden `pagePath`-rijen nodig zijn (`limit: 100000`) — monitoren of
  GA4 Data API dat binnen de tenant se quota blijft; zo niet, per-pad filteren i.p.v. alle
  paden ophalen.
- Overweeg een `analytics_views_synced_at`-kolom (zoals sources al `last_checked_at`
  hebben) zodat de Overview-kaart kan tonen *wanneer* de GA4-cijfers voor het laatst
  ververst zijn (transparantie richting redacteur).

### Fase 4 — Rijkere Report-koppeling

- CE-07 gebruikt engagement nu als simpele som (`views + likes + comments`) — GA4-views
  hebben een heel andere schaal dan LinkedIn-likes. Overweeg een gewogen score of aparte
  weergave (website-bereik vs. social-engagement) in de narratief-synthese.
- Report toont vandaag `websiteAnalytics` (site-breed GA4) én `linkedInEngagement` los
  van elkaar, zonder de nu beschikbare **per-artikel** GA4-koppeling te tonen. Toevoegen:
  "Top-artikelen (GA4-weergaven)"-sectie op Report, gevoed door dezelfde
  `ce_analytics_sync_article_views()`-data.
- Overview-kaart "Totale engagement" linkt al door naar Report — na fase 4 kan die link
  een specifiek artikel/periode meenemen i.p.v. alleen de periode.

### Fase 5 — Zichtbaarheid van databron-status

- Sources-pagina (Analythics/LinkedIn-tab) toont vandaag alleen "reachable/unreachable".
  Toevoegen: laatst gesyncte artikel-aantal (`matched`/`checked` uit
  `ce_analytics_sync_article_views()` / LinkedIn-equivalent) zodat de redacteur ziet of de
  koppeling daadwerkelijk data oplevert, niet alleen of de verbinding technisch werkt.

---

## 4. Samenvatting voor de gebruiker

- **Wat was er mis (2 losse problemen):**
  1. LinkedIn-reacties werden per ongeluk als "website-weergaven" opgeslagen, en diezelfde
     LinkedIn-cijfers werden twee keer opgeteld in de Overview-grafiek.
  2. **Google Analytics Data API staat uit** in het Google Cloud-project achter de
     platformbrede GA4-OAuth-koppeling → élke GA4-aanroep faalt silent met 403, voor
     alle tenants, ook de al langer bestaande site-brede cijfers.
- **Wat is er nu gefixt (code, live):** geen dubbeltelling meer; echte GA4-paginaweergaven
  per artikel (matching op de gepubliceerde blog-URL); Report en Overview delen dezelfde
  bron; automatische verversing (uurlijkse cron + bij elke Report-generate).
- **Wat blokkeert nog (actie buiten de code, éénmalig):** de Google Analytics Data API
  inschakelen in Google Cloud-project `1032126780556` (link in §1.3) — zonder die stap
  blijven alle GA4-cijfers 0, hoe goed de matching-code ook werkt.
- **Daarna nog open:** performance-check bij veel artikelen (fase 3) en een rijkere
  Report-sectie die de nieuwe per-artikel GA4-data ook toont (fase 4).
