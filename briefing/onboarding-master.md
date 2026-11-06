# Onboarding — draaiboek, tiers & WordPress-koppeling

**Versie 2.0 · 3 augustus 2026 · intern (niet naar klant sturen)**

Samenvoeging van drie eerder losse documenten: `onboarding-eerste-klant.md`
(week-voor-week draaiboek), `cursor-onboarding-tiers.md` (Light/Medium/Full-
indeling) en `cursor-wordpress-onboarding.md` (WP-koppeling). Ze verwezen
voortdurend naar elkaar en overlapten — dit is nu één doorlopend verhaal: van
kickoff-voorbereiding tot volledige rapportage-inrichting, met de
tier-indeling als leidraad voor *hoe ver* je in één sessie gaat.

**Leeswijzer:** § 1 geeft het overzicht (tiers). § 2-7 volgen het
tijdspad van een klanttraject (voorbereiding → Light → Medium → Full).
§ 8 is de losstaande technische WordPress-checklist, te gebruiken op elk
moment dat je publiceren instelt. § 9-12 zijn overkoepelend (beloftes,
risico's, tijdsbesteding, vergelijkingstabel).

De onderliggende technische specs blijven apart en worden hier niet
gedupliceerd: `briefing/cursor-publish-modes.md` (pipeline-gedrag per
publicatiemodus) en `briefing/cursor-onboarding-kickstart.md` (voorstel
voor de eenmalige onboarding-run die de rolling agenda in één keer vult).

> **Identity-nav (aug 2026):** **BrandArchitecture** (`/brandarchitecture`, alleen bij HBA aan) ·
> **BrandElements** (`/styleguide`) · **Objectives** (`/targets`). Definitieve indeling:
> `briefing/cursor-identity-tabindeling.md`. Deeplink `/strategy` → `/styleguide`.

---

## 1. De drie niveaus in één zin

| Tier | Doel | Setting |
|---|---|---|
| **Light** | Zo snel mogelijk één artikel produceren dat overtuigt | Salesgesprek / presentatie bij een nieuwe opdrachtgever |
| **Medium** | Redacteur werkt inhoudelijk met het systeem: betere posts + tracking | Actieve klant, eerste weken/maanden |
| **Full** | Complete report-kant ingericht: meten en verantwoorden | Volwassen klant, structureel gebruik |

Cumulatief: Medium = Light + extra, Full = Medium + extra. Niemand hoeft
terug te stappen om op te schalen. Fase 1 hieronder komt vrijwel 1-op-1
overeen met Light; fase 2-3 met Medium/Full.

De volgorde is bewust. Wie begint met een sessie van twee uur over
archetypen, verkoopt een adviestraject. Wie begint met een gepubliceerd
artikel, verkoopt een systeem — en krijgt de archetype-sessie er later
gratis bij, omdat de klant dan zelf vraagt waarom sommige stukken beter
voelen dan andere.

---

## 2. Voorbereiding (vóór kickoff)

Wat je zelf klaarzet, zodat de kickoff geen installatie-sessie wordt.

| Actie | Waar | Let op |
|---|---|---|
| Tenant aanmaken | `./scripts/create-client.sh` → `POST /api/admin/create-client` | Deploy-status checken; op studio gaf FTP eerder een timeout |
| Contact-/reviewer-e-mail invullen | Account-scherm, `contact_email` / `reviewer_email` | Dit adres krijgt de review-mails (bij `manual_with_review`) — verkeerd adres = stille flow |
| Login testen | `/login`, evt. `/forgot-password` | Vóór de kickoff, niet tijdens |
| `publish_mode` bepalen | Account | Zie § 8.2 voor de drie modi. Start Light-demo's altijd op **manual_with_review of manual_without_review**; **automatic** pas als gallery + WP-koppeling getest zijn |
| Posts per week + planning horizon | Account | Realistisch: 1/week. Liever opschalen dan teleurstellen |
| Bestaande content bekijken | Hun site/LinkedIn | Je hebt straks ammunitie nodig voor de tone-of-voice discussie |

**Valkuil:** verleiding om alvast BrandElements in te vullen "om tijd te
besparen". Niet doen — de klant moet die woorden zelf zeggen, anders
herkent hij het artikel straks niet als het zijne.

---

## 3. Light-tier / Fase 1 — Snel live (30-45 min of week 1)

Doel: **binnen één sessie (demo) of zeven dagen (echte klant) één
artikel** waar de klant zich in herkent. Alles wat dat doel niet dient,
schuift naar Medium/Fase 2.

### Wat wél

| Stap | Scherm | Minimaal |
|---|---|---|
| Account | `/account` | Naam + login. Geen `publish_mode`-keuze nodig voor een demo — blijft default `manual_with_review`. |
| BrandElements | `/styleguide` | Minimaal voor CE-03: `brandstory` (BrandValues), kort `writing_guide`. Archetype/adversary bij HBA: tab **BrandArchitecture**. |
| Artikel | `/fastlane` | **Fastlane** — omzeilt de week-gating volledig. Thema + evt. één research-URL → **Write article** → concept binnen minuten. |

### Wat expliciet niet (bij een pure demo)

- **Sources** — overslaan; Fastlane kan zonder bronnen draaien.
- **Targets/objectives** — overslaan.
- **Account → Publiceren** — overslaan als het puur een demo is; het artikel wordt getoond, niet gepubliceerd.
- **Theme/Research-fase, Agenda-ritme** — niet uitleggen, bewaar voor Medium.

### Sessie 1 — Kickoff (90 min, samen achter het scherm, bij een echte klant)

Niet presenteren. Samen invullen. De klant moet het systeem zien reageren
op zijn eigen input.

**Blok A — BrandElements (40 min)**

**1. LLM-probleemvragen (15 min) — begin hier**

Dit is het eerste wat je invult, niet het laatste. Zodra iemand hardop
formuleert welke vraag zijn klant écht heeft, komen doel, tegenkracht en
toon vanzelf boven tafel.

Vraag: *"Stel, je ideale klant zit met een probleem en typt dat vanavond
in ChatGPT. Wat typt hij precies?"* Doorvragen tot je 3 tot 5 concrete,
probleem-gedreven vragen hebt — geen categorie-vragen ("wat is een goed
bureau voor X"), maar de vraag onder het probleem.

Voorbeelden ter illustratie:

- "Hoe zorg ik dat kennis niet alleen in de hoofden van mijn medewerkers zit?"
- "Hoe maak ik mijn organisatie minder afhankelijk van de oprichter?"
- "Wat heb ik nodig voordat AI echt waarde toevoegt in mijn bedrijf?"

> **Let op — veld bestaat nog niet.** Er is nog geen apart Findability-veld
> voor deze vragen (backlog, prioriteit Hoog). **Tijdelijke werkwijze:**
> zet ze onderaan `writing_guide` op **BrandElements** onder een kopje *"Vragen die onze
> doelgroep aan AI stelt:"*. CE-01 en CE-03 lezen dat veld mee in hun
> prompt. Wat je de klant hierover vertelt: *"deze vragen nemen we mee bij
> het kiezen van thema's"* — niet *"elke maand wordt er automatisch één
> beantwoord"*.

**2. De rest van BrandElements (25 min)**

- **`brand_goal`** — waar het bedrijf naartoe wil. Doorvragen tot er iets staat dat geen holle frase is.
- **Archetype (voorlopig)** — kies er één uit de twaalf, zonder de volledige HBA-sessie. Noteer dat dit een werkaanname is die in fase 2 wordt getoetst.
- **`adversary`** — waar vechten jullie tegen? Dit veld maakt artikelen scherp.
- **`writing_guide`** — schrijfwijzer, incl. wat níet mag. Plus de probleemvragen uit stap 1.

**Blok B — Sources (30 min)**

Minimaal één bron, liefst twee. Zonder bronnen zijn de eerste
thema-voorstellen mager.

| Bron | Wanneer | Aandachtspunt |
|---|---|---|
| RSS-feed | Altijd — laagste drempel | Vakblad of nieuwsbron uit hun sector |
| Mailbox (IMAP) | Als ze nieuwsbrieven lezen | Wachtwoord/app-wachtwoord nodig; IMAP-host meestal auto-detect |
| Trello | Als ze al ideeën ergens parkeren | Snelle win: bestaand materiaal komt de flow in |
| Scraper (Apify) | Alleen bij expliciete behoefte | Kost budget per run — niet in week 1 |

Klik altijd op **Check** terwijl de klant kijkt.

**Blok C — Verwachtingen (20 min)**

> Week N stelt het systeem thema's voor. Week N+1 verzamelt het de
> onderbouwing. Week N+2 ligt het artikel klaar. Dat lijkt traag, maar het
> is bewust — een thema dat een week mag rijpen levert een beter stuk op
> dan een thema dat direct wordt volgeschreven.

En daarna meteen: **"maar voor de eerste keer slaan we dat over."**

### Direct na sessie 1 — Fastlane

Gebruik **Fastlane** (`/fastlane`) om de weekgating te omzeilen: thema
invullen, eventueel een research-URL, Write article. Binnen minuten ligt
er een concept.

**Valkuil:** het eerste Fastlane-artikel is zelden meteen goed — de
BrandElements is nog dun. Zeg dat vóóraf: *"Dit is versie één. Wat je hier
terugziet vertelt ons wat er nog aan de strategie ontbreekt."*

### Sessie 2 — Eerste redactie (45 min, 2-3 dagen later)

Loop samen het Article-scherm door:

- **Redacteursvragen** (rode `→`-regels) — leg uit waarom ze er staan: AI schrijft technisch prima, maar zonder een echt cijfer, een echte anekdote of een echt tegengeluid prikt de lezer erdoorheen.
- **Aandachtspunten** (zelfcontrole uit CE-03) — het systeem geeft zelf aan waar de zwakste claim zit.
- **Tone en Angle** — per artikel te sturen.
- **Beeld** — uploaden; de bredere beeldbibliotheek is nog toekomst.
- **Submit** → review-mail (bij `manual_with_review`) → **Approved** → **Plaats**.

### Einde week 1 — publiceren

Zie § 8 voor de volledige WordPress-checklist. Klant plaatst zelf op
LinkedIn (bewuste keuze: handmatig geeft beter bereik — verkoop dat als
voordeel).

Vul daarna samen het **Archive**-formulier in: datum, promotiebudget,
URL. Dit is de basis voor het rapport later — meten begint bij netjes
afsluiten.

### Checkpoint einde Light/Fase 1

- [ ] Klant kan zelf inloggen
- [ ] Minimaal één bron staat op groen na Check
- [ ] Eén artikel gepubliceerd en gearchiveerd
- [ ] Klant heeft zelf minstens één redacteursvraag beantwoord
- [ ] Agenda is gevuld voor de komende weken
- [ ] Klant weet waar hij moet zijn: Home → to-do's

**Aandachtspunt:** de eenmalige onboarding-run die de rolling agenda
volledig vult zodra de bronnen staan, is nog niet gebouwd — zie
`briefing/cursor-onboarding-kickstart.md`. Tot die tijd handmatig
triggeren via `POST /api/admin/theme-refill`, anders wacht de klant tot
maandag en denkt hij dat er niets gebeurt. **Zet dit als taak in je
agenda voor de dag na de kickoff.**

---

## 4. Medium-tier — inhoudelijk sterker + tracking (2-4 weken)

**Wanneer:** de klant is akkoord. Doel: inhoudelijk sterkere artikelen
(lopende cyclus, geen demo-stuk) + zichtbare tracking.

### Wat wél (bovenop Light)

| Stap | Scherm | Uitbreiding t.o.v. Light |
|---|---|---|
| Account | `/account` | `contact_email` / `reviewer_email`, `posts_per_week`, `planning_horizon_weeks` realistisch ingesteld. |
| BrandElements | `/styleguide` | Volledig: `tone_styles` (meerdere tones met eigen schrijfstijl), volledige `writing_guide`. |
| Objectives | `/targets` | Objectives ingesteld: percentage-verdeling + schrijfstijl per doelstelling. **Let op:** UI/opslag zijn live, maar koppeling naar CE-01/02/03 zelf is nog backlog (#34) — zie als richtinggevend gesprek met de klant, niet als functionele garantie. |
| Sources | `/sources` | Minimaal 2 actieve bronnen, elk gecheckt. |
| Account → Publiceren | `/account` | WordPress gekoppeld (zie § 8, verplicht om te publiceren); LinkedIn OAuth optioneel. |
| Rolling flow | Agenda/Theme/Research/Article | Normale week-gating (CE-01 → CE-02 → CE-03), of handmatige refill zolang de onboarding-run (§ kickstart-brief) nog niet gebouwd is. |
| Feedback-cyclus | Grid #3 Feedback | CE-04 AI-review + redacteursvragen actief — het inhoudelijke verschil met Light. |
| Tracking | `/` (Home) | Trendkaarten **% op-strategie** en **cumulatief engagement** (8 weken) — al live, geen extra bouwwerk. |

### Wat nog niet

- **Report (CE-07)** — bewust uitgesteld naar Full; met 2-4 weken historie is een rapport nog dun.
- **Campaign planner** — optioneel, hoort eigenlijk bij Full.
- **HBA/StoryBrand-verdieping** — zie § 5, kan parallel lopen maar is geen voorwaarde.

### Bekende beperkingen om vooraf te benoemen

- Objectives sturen de content nog niet automatisch — communiceer als richting, niet als belofte.
- Onboarding-run na Sources is nog niet gebouwd — reken op één handmatige refill-actie na de Sources-sessie.

---

## 5. Fase 2 — Merkarchitectuur (week 3-4, optioneel naast Medium)

De klant heeft inmiddels drie of vier artikelen gezien en voelt zelf dat
sommige beter landen dan andere. Dat is het moment waarop de vraag
"waarom klinkt dit soms niet als ons?" vanzelf komt.

### Sessie 3 — HBA-werksessie (2 uur, met het team)

Wizard op **BrandArchitecture** (`/brandarchitecture`) — acht stappen, veertien velden, waaronder:

- Archetype (nu definitief toetsen, niet de werkaanname uit Light)
- Doel en Foe, causaal verbonden — niet los van elkaar
- Spanning en mechanisme
- Geloofwaardigheidstest en verboden logica
- Bewijsvelden, goede en zwakke voorbeelden

**Regie:** dit is een sessie met het team, niet met één persoon. Vraag
door op het Foe-veld — daar zit de energie, en daar wordt het vaak vaag.

**Bewaken:** StoryBrand-taal ("de klant is de held", "wij zijn de gids")
mag hier niet binnenkomen. Eerst merkarchitectuur, dan pas klanthelderheid.

**Licentie:** HBA is beschermd IP van Hero Brands. Check de
licentiestatus vóór je dit inzet bij een betalende klant.

### Sessie 4 — StoryBrand-vertaling (2 uur)

Negentien velden: klantsegment, buying stage, kanaal, customer goal,
extern/intern/filosofisch probleem, rol van het merk, plan, primaire en
secundaire CTA, success picture, failure/stakes, one-liner.

Begin met één segment — het belangrijkste. Uitbreiden kan altijd.

### Sessie 5 — Terugkoppeling (60 min)

Neem de artikelen uit Light er weer bij en toets ze tegen wat er nu op
tafel ligt. Dit is het meest overtuigende gesprek van het hele traject.

Werk daarna **BrandArchitecture** bij in de app en **BrandElements** waar nodig; laat de volgende
thema-ronde draaien.

---

## 6. Full-tier — Report & analytics ingericht (na 4+ weken lopende content)

**Wanneer:** klant draait structureel, wil kunnen verantwoorden wat het
oplevert.

### Wat wél (bovenop Medium)

| Stap | Scherm | Toelichting |
|---|---|---|
| Report | `/report` | CE-07 periodiek gegenereerd: engagement, angle-mix, artikeltellingen, **KPI-highlighting** op `brand_goal_category`. |
| Analytics-koppeling | Account/Sources | GA4 OAuth waar van toepassing — nodig voor engagement-cijfers verder dan LinkedIn-eigen statistieken. |
| Archive volledig ingericht | `/archive` | `promo_budget`, `publishLinks` (Blog/LinkedIn/X), URL-check — de basis onder het rapport: meten begint bij netjes afsluiten. |
| Campaign planner | `/campaign`, `/campaigns` | Thematische reeksen naast losse artikelen, nu zinvol met werkend ritme. |
| Ritme + escalatiepad | — | Organisatorisch: wie beoordeelt wat wanneer, wie belt de klant bij stilstand. |

### Bekende beperkingen om vooraf te benoemen

- **Reports archiveren** — Save op de Report-pagina is nu een lokale bevestiging, geen echte DB-archivering. Terug te vinden blijft mogelijk via Archive-zoek.
- **LinkedIn Ads-budget terugkoppelen** — nog handmatig veld, geen automatische sync.
- **"Meetbaar in AI-antwoorden verschijnen"** — niet beloven; CE-LLM-Visibility meet dit maandelijks, maar blijft een indicatie.

---

## 7. Fase 3 — Overdracht (week 5-6)

Doel: de klant draait zelfstandig, jij kijkt mee.

| Actie | Toelichting |
|---|---|
| Eerste rapport genereren | CE-07 → `/report`. Engagement, angle-mix, artikeltellingen |
| Ritme afspreken | Wie beoordeelt wat, wanneer, met welke doorlooptijd |
| Campagne-uitleg | `/campaign` voor thematische reeksen naast losse artikelen |
| Escalatiepad | Wie belt de klant als er iets stilstaat |
| Evaluatie inplannen | Over 4 weken; niet vrijblijvend, gewoon in de agenda |

---

## 8. WordPress-koppeling — technische checklist

Gebaseerd op productie-incident Sandbox 3 (aug 2026). Te gebruiken op elk
moment dat je publiceren instelt (Light, Medium of Full).

### 8.1 Waar staan credentials?

| Laag | Waar | Wanneer gebruikt |
|------|------|------------------|
| **Tenant (primair)** | Account → Publiceren (`clients.wordpress_*` in DB) | Altijd eerst — elke klant/tenant heeft eigen velden |
| **Platform-fallback** | `.env` → `web/includes/ce.local.php` op server | Alleen als tenant-velden leeg zijn |

**Regel:** vul altijd **Account → Publiceren** in voor de tenant. `.env`
is platform-default / backup, geen vervanging voor tenant-config.

Diagnose per tenant:

```bash
curl -s "https://ce.igisnext.com/api/admin/wp-diagnose?client_id=TENANT-UUID" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN"
```

Verwacht: `"authenticatedAs": { "slug": "…", "roles": ["administrator" | "editor"] }` — geen `authError`.

### 8.2 Publicatiemodi

| Modus | Gedrag na CE-03 |
|-------|-----------------|
| **Handmatig met tegenlezer** (`manual_with_review`) | Draft → redacteur dient in → reviewer keurt goed → redacteur plaatst. Huidig standaard. |
| **Handmatig zonder tegenlezer** (`manual_without_review`) | Draft → redacteur plaatst zelf, één klik "Indienen" = direct live. CE-03 genereert ook een beeldadvies-hint. Geen approval-mail. **Geen buffer** — er is geen pending-status om nog uit te stappen. |
| **Automatisch inclusief plaatsen** (`automatic`) | Scheduler plaatst automatisch via de beelddatabase in Sources/Gallery. Vereist: minimaal 1 beeld in `client_images`. |

Volledige pipeline-details per modus: `briefing/cursor-publish-modes.md`.

### 8.3 Wat is een Application Password?

WordPress **Application Password** ≠ normaal inlogwachtwoord.

| | Normaal wachtwoord | Application Password |
|---|-------------------|----------------------|
| Waar aanmaken | Login | WP Admin → Gebruikers → **Profiel** → Applicatie-wachtwoorden |
| Wanneer zichtbaar | Altijd (via reset) | **Eén keer** in grijs vak na aanmaken |
| Formaat | willekeurig | meestal **24 tekens**, vaak getoond als `xxxx xxxx xxxx xxxx xxxx xxxx` (29 tekens mét spaties) |
| REST API | ❌ | ✅ |

**Gebruikersnaam:** WordPress **login-naam** (slug), bijv. `erik` — **niet**
het e-mailadres, tenzij login en e-mail toevallig gelijk zijn.

**Revoke:** maakt het Application Password **definitief ongeldig**. Na
Revoke overal opnieuw invullen (alle tenants die dat wachtwoord gebruiken).

### 8.4 Onboarding-checklist per tenant

**A. Basis (alle modi)**

1. **Account → Publiceren:** Site-URL (`https://klantdomein.nl`, geen `/wp-admin`), WordPress-gebruiker (login-naam), Application Password (volledig gekopieerd uit grijs vak), optioneel auteur-ID.
2. **Opslaan** in Account.
3. **Verifiëren** via `wp-diagnose` (§ 8.1).
4. Minimaal **één beeld** in Gallery (`/gallery`) als automatic-modus of auto-beeld gewenst is.

**B. Automatic-modus — extra vereisten**

1. `publish_mode = automatic` in Account.
2. Gallery: minimaal 1 geliked/geüpload beeld (`client_images`).
3. WordPress-koppeling **werkend** (wp-diagnose groen).
4. CE-01 → CE-03 stuurt `auto_publish`-vlaggen mee (platform; sinds aug 2026 gefixt).
5. Optioneel testen:
   ```bash
   curl -X POST "https://ce.igisnext.com/api/articles/SLOT-UUID/auto-publish" \
     -H "X-CE-Reset-Token: $CE_RESET_TOKEN"
   ```

### 8.5 Verificatie — altijd vóór "het werkt"

**Stap 1 — curl (lokaal, vóór CE):**

```bash
curl -s -u 'GEBRUIKER:APPLICATION-PASSWORD' \
  'https://klantdomein.nl/wp-json/wp/v2/users/me'
```

Succes: JSON met `"slug":"…"` en `"id":…`. Fout:
`{"code":"rest_not_logged_in",…}` → wachtwoord/gebruiker klopt niet, niet
verder in CE invullen. Gebruik **enkele quotes** rond `gebruiker:wachtwoord`.

**Stap 2 — wp-diagnose (tenant in CE):** zie § 8.1. Controleer
`config.user`, `config.app_password_length` (≈24-29, niet 16 of korter),
`authenticatedAs` ≠ null, `resolvedPostAuthorId` ≠ null.

**Stap 3 — proefpublicatie:** handmatig op Article → **Publiceer**, of
auto-publish-endpoint (automatic). Controleer post op live site.

### 8.6 Scenario's

**Zelfde WordPress-site, meerdere tenants (sandbox + productie):**
één Application Password delen mag. Praktisch: kopieer werkende
credentials van hoofdtenant:

```bash
curl -X POST "https://ce.igisnext.com/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN" \
  -d '{"clientId":"DOEL-TENANT","copyWordpressFromClientId":"BRON-TENANT"}'
```

Revoke op dat wachtwoord breekt **alle** tenants op die site tegelijk.

**Eigen WordPress-site per klant:** per tenant eigen Application
Password aanmaken op hun WP; `.env` niet gebruiken als bron.

**`.env` (platform, alleen fallback):**

```env
WORDPRESS_SITE_URL=https://igisnext.com
WORDPRESS_USER=erik
WORDPRESS_APP_PASSWORD="abcd EFGH ijkl MNOP qrst UVWX"
WORDPRESS_AUTHOR_ID=1
```

Speciale tekens (`&`, `)`, `!`, `#`): wachtwoord tussen dubbele quotes.
Na wijziging: `./scripts/deploy-ftp.sh`.

### 8.7 Veelvoorkomende fouten

| Symptoom | Oorzaak | Oplossing |
|----------|---------|-----------|
| `rest_not_logged_in` | Geen geldig Application Password | Nieuw app-wachtwoord in WP; curl-test eerst |
| `app_password_length: 16` | Afgekapt / verkeerd gekopieerd / normaal wachtwoord | Opnieuw aanmaken; volledige 24 tekens |
| Gebruiker = e-mail | Verkeerd veld | Login-naam uit WP (Users) |
| curl werkt, CE niet | Alleen `.env` gevuld, niet Account | Account → Publiceren voor **die tenant** |
| `.env` werkt niet na edit | Niet gedeployed | `./scripts/deploy-ftp.sh` |
| `article_not_found` bij auto-publish | Systeem-call zonder tenant-context | Opgelost aug 2026 in API; deploy vereist |
| Automatic publiceert niet | Geen gallery-beeld / WP faalt / geen auto_publish | wp-diagnose + gallery + publish_mode |
| Rode syntax in `.env` | Speciale tekens | Quotes om wachtwoord |

### 8.8 Onboarding-gesprek — korte flow

1. **Kickoff:** publish mode kiezen (Light-demo → handmatig; productie → automatic pas als gallery + WP klaar).
2. **WordPress:** klant maakt Application Password aan (scherm delen of instructie-mail).
3. **Jij:** curl-test → Account invullen → wp-diagnose groen.
4. **Gallery:** min. 1 beeld uploaden + taggen.
5. **Sources + BrandElements** (volgens tier — zie § 3-6).
6. **Theme/rolling agenda** starten.
7. **Automatic:** bevestig proefpost op live site.

### 8.9 Incident Sandbox 3 — lessons learned (aug 2026)

1. **CE-01** stuurde geen `auto_publish` naar CE-03 → automatic-modus schreef wel, publiceerde niet → gefixt in `generate-ce01-workflow.py`.
2. **Auto-publish API** faalde zonder sessie (tenant-scope) → gefixt: client via slot-id.
3. **WP-credentials** leken ingevuld maar Application Password was ongeldig; curl-test ving dit direct.
4. **Werkende oplossing:** credentials van hoofdtenant gekopieerd (`copyWordpressFromClientId`) → auto-publish succesvol naar igisnext.com.

---

## 9. Wat je wél en niet belooft

**Wel:**

- Elk stuk wordt getoetst aan hetzelfde merkverhaal — dat is wat consistentie oplevert.
- Het systeem stelt zelf vragen om er iets menselijks in te krijgen.
- Thema's vertrekken vanuit de vragen die hun doelgroep aan AI-assistenten stelt.
- Het scheelt een dag per week aan bedenken, zoeken en schrijven.

**Niet:**

- Dat je meetbaar in AI-antwoorden verschijnt. CE-LLM-Visibility meet dit inmiddels maandelijks, maar het blijft een indicatie, geen garantie.
- AI-gegenereerde beelden (bewust buiten scope).
- Automatisch teruggekoppeld advertentiebudget (backlog).
- Volledig automatisch publiceren zonder enige menselijke stap tussendoor — technisch mogelijk (`automatic`), maar zet dat pas in als gallery + WP-koppeling getest zijn.

---

## 10. Risico's

| Risico | Signaal | Actie |
|---|---|---|
| Agenda blijft leeg na Sources | Klant ziet niets gebeuren | Handmatig refill draaien (`POST /api/admin/theme-refill`); eenmalige onboarding-run staat nog op de backlog — zie `briefing/cursor-onboarding-kickstart.md` |
| Klant vindt eerste artikel tegenvallen | Lauwe reactie in sessie 2 | Vooraf framen als diagnose; direct BrandElements aanscherpen |
| Probleemvragen verdwijnen uit beeld | Geen enkel artikel beantwoordt er één | Veld bestaat nog niet — maandelijks handmatig één omzetten in thema; bijhouden in klantdossier |
| Redacteursvragen worden genegeerd | Artikelen blijven generiek klinken | Terugkomen op het vertrouwensverhaal; laat het verschil zien |
| Review-mail komt niet aan (`manual_with_review`) | Stille flow, niets in Pending | `reviewer_email` checken; `reviewUrl` als fallback |
| **Publiceren in `manual_without_review`** | — | Geen pending-buffer: "Indienen" = direct live op de echte site. Doe de eerste klik bewust, niet als losse test tijdens een demo. |
| HBA-sessie te vroeg | Klant haakt af op abstractie | Vasthouden aan de fasering, ook onder druk |
| WordPress-credentials lijken goed maar zijn het niet | `rest_not_logged_in` bij publiceren | Altijd eerst losse curl-test (§ 8.5), niet blind Account invullen |
| Testen op productiedata | — | Nooit. Gebruik een sandbox-tenant. Er is eerder editorial content verloren gegaan bij een delete-test |

---

## 11. Tijdsbesteding (indicatie)

| Fase/Tier | Jouw uren | Klant-uren |
|---|---|---|
| Voorbereiding | 2 | 0 |
| Light / Fase 1 | 5 | 3 |
| Medium (doorlopend, geen losse sessie) | — | — |
| Fase 2 (HBA/StoryBrand) | 6 | 5 (team) |
| Full / Fase 3 | 3 | 2 |
| **Totaal (Light t/m Full)** | **16** | **10** |

Bij de eerste klant realistisch 1,5×. Houd bij wat je tegenkomt.

---

## 12. Vergelijkingstabel (voor gesprek met klant/prospect)

| Onderdeel | Light | Medium | Full |
|---|:---:|:---:|:---:|
| Account + login | ✓ | ✓ | ✓ |
| BrandElements (basis) | ✓ | ✓ | ✓ |
| BrandElements (tone_styles, volledige schrijfwijzer) | — | ✓ | ✓ |
| BrandArchitecture (HBA-wizard) | — | ✓ (optioneel) | ✓ |
| Objectives | — | ✓ (richtinggevend) | ✓ |
| Sources | — | ✓ (2+) | ✓ |
| Publiceren (WP/LinkedIn) | — | ✓ | ✓ |
| Rolling flow (Theme→Research→Article) | — (Fastlane only) | ✓ | ✓ |
| Feedback-cyclus (CE-04) | — | ✓ | ✓ |
| Home-tracking (trendkaarten) | — | ✓ | ✓ |
| Report (CE-07) | — | — | ✓ |
| Analytics-koppeling | — | — | ✓ |
| Campaign planner | — | — | ✓ (optioneel) |
| Archive volledig (promo budget, links) | — | — | ✓ |

---

## Referenties

| Onderwerp | Document |
|-----------|----------|
| Pipeline-gedrag per publicatiemodus (technische spec) | `briefing/cursor-publish-modes.md` |
| Voorstel eenmalige onboarding-run (backlog #16) | `briefing/cursor-onboarding-kickstart.md` |
| WP setup-script (platform) | `scripts/setup-igisnext-wordpress-ce.sh` |
| Diagnose | `GET /api/admin/wp-diagnose` |
| Slots/articles debug | `GET /api/admin/list-slots?client_id=…` |
| Status/architectuur-overzicht | `docs/BUILD-NOTES.md` |
| Identity-tabs (BrandArchitecture / BrandElements / Objectives) | `briefing/cursor-identity-tabindeling.md` |

---

*Dit document vervangt `onboarding-eerste-klant.md`, `cursor-onboarding-tiers.md`
en `cursor-wordpress-onboarding.md` — die drie kunnen na controle
gearchiveerd worden.*
