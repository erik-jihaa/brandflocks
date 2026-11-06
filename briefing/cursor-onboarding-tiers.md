# Onboarding programma — Light / Medium / Full

Uitsplitsing van het bestaande onboarding-programma (`BUILD-NOTES.md` §
Onboarding programma, backlog #17) naar drie niveaus. Vult aan op
`briefing/onboarding-eerste-klant.md` (het week-voor-week draaiboek voor de
eerste klant) — dit document geeft de **tier-indeling**: welk niveau je bij
welk moment/doel inzet, en welke schermen/features daarbij horen. Fase 1 uit
het draaiboek komt vrijwel 1-op-1 overeen met **Light**; fase 2-3 met
**Medium/Full**.

---

## De drie niveaus in één zin

| Tier | Doel | Setting |
|---|---|---|
| **Light** | Zo snel mogelijk één artikel produceren dat overtuigt | Salesgesprek / presentatie bij een nieuwe opdrachtgever |
| **Medium** | Redacteur werkt inhoudelijk met het systeem: betere posts + tracking | Actieve klant, eerste weken/maanden |
| **Full** | Complete report-kant ingericht: meten en verantwoorden | Volwassen klant, structureel gebruik |

Belangrijk: dit zijn **cumulatieve** niveaus, geen aparte producten. Medium =
Light + extra; Full = Medium + extra. Niemand hoeft terug te stappen om op
te schalen.

**WordPress / publiceren:** uitgebreide instructie (Application Password,
curl-test, wp-diagnose, automatic-modus) staat in
`briefing/cursor-wordpress-onboarding.md`. Onderstaand per tier wat je wél/niet
doet.

---

## Tier 1 — Light

**Wanneer:** demo, pitch, eerste kennismaking. De prospect zit erbij. Doel is
een "wow, het werkt echt"-moment binnen de sessie, niet een correcte
strategie.

**Tijd:** 30-45 minuten, geheel in de sessie zelf (geen huiswerk vooraf).

### Wat wél

| Stap | Scherm | Minimaal |
|---|---|---|
| Account | `/account` | Naam + login. Geen `publish_mode`-keuze nodig — blijft default `manual_with_review`. |
| Strategy | `/strategy` | Alleen wat CE-03 nodig heeft: `archetype` (werkaanname, één keuze uit twaalf), `adversary`, `brand_goal`, kort `writing_guide`. ~15-20 min gesprek, zie draaiboek Sessie 1 Blok A. |
| Artikel | `/fastlane` | **Fastlane** — omzeilt de week-gating volledig. Thema + evt. één research-URL handmatig invullen → **Write article** → concept binnen minuten. |

### Wat expliciet niet

- **Sources** — overslaan. Fastlane kan zonder bronnen draaien (research-URL is optioneel); een RSS-feed toevoegen kost tijd die de demo niet heeft.
- **Targets/objectives** — overslaan.
- **Account → Publiceren** (WP/LinkedIn-koppeling) — overslaan. Het artikel wordt getoond op het scherm, niet gepubliceerd. Publiceren is een reden om terug te komen, geen onderdeel van de demo. *Wel noemen:* "straks koppelen we één keer WordPress — dan gaat publiceren in één klik."
- **Theme/Research-fase, Agenda-ritme** — niet uitleggen. Zou de indruk wekken dat het altijd zo traag gaat; bewaar dat gesprek voor Medium.
- **Report/tracking** — niet van toepassing, er is nog geen historie.

### Technisch (bestaat al, geen bouwwerk nodig)

`POST /api/fastlane` → themes/research/slot + directe CE-03-call. Precies
het pad dat het draaiboek ("Direct na sessie 1 — Fastlane") al voorschrijft.
Light-tier is dus **vandaag al volledig uitvoerbaar** zonder nieuwe features
— het is een gebruiksinstructie, geen ontwikkelwerk.

### Wat je belooft (en niet)

Wel: "dit is wat het systeem kan met vijf minuten input." Niet: "dit is het
uiteindelijke niveau" — zeg expliciet dat een echte inzet met bronnen en een
volledige strategie beter wordt, anders wekt een dun eerste concept een
verkeerde verwachting.

---

## Tier 2 — Medium

**Wanneer:** de klant is akkoord, eerste 2-4 weken. Doel: inhoudelijk
sterkere artikelen (niet één demo-stuk maar een lopende cyclus) + zichtbare
tracking van hoe het gaat.

**Tijd:** komt overeen met draaiboek fase 1 volledig + het strategie-deel
van fase 2 (zonder de volledige HBA/StoryBrand-sessies).

### Wat wél (bovenop Light)

| Stap | Scherm | Uitbreiding t.o.v. Light |
|---|---|---|
| Account | `/account` | `contact_email` / `reviewer_email` (nodig voor review-mails), `posts_per_week`, `planning_horizon_weeks` realistisch ingesteld. |
| Strategy | `/strategy` | Volledig: `tone_styles` (meerdere tones met eigen schrijfstijl), volledige `writing_guide` inclusief wat níet mag, LLM-probleemvragen (tijdelijk in `writing_guide`, zie draaiboek — apart veld is backlog). |
| Targets | `/targets` | Objectives ingesteld: percentage-verdeling + schrijfstijl per doelstelling, planning horizon. **Let op:** UI en opslag zijn live, maar de koppeling naar CE-01/02/03 zelf is nog backlog (#34) — objectives sturen de content nu nog niet automatisch. Zet dit dus in als **richtinggevend gesprek met de klant**, niet als functionele garantie. |
| Sources | `/sources` | Minimaal 2 actieve bronnen, elk gecheckt (RSS + Mailbox/Trello naar keuze). |
| Account → Publiceren | `/account` | WordPress gekoppeld (moet, om echt te publiceren); LinkedIn OAuth optioneel. Zie **§ WordPress-koppeling** hieronder. |
| Rolling flow | Agenda/Theme/Research/Article | Normale week-gating (CE-01 → CE-02 → CE-03) in plaats van Fastlane, of handmatige refill zolang de eenmalige onboarding-run (#16) nog niet gebouwd is — zie draaiboek-risico "Agenda blijft leeg na Sources." |
| Feedback-cyclus | Grid #3 Feedback | CE-04 AI-review + redacteursvragen actief gebruikt — dit is het inhoudelijke verschil met Light: sturing per artikel, niet alleen een eerste output. |
| Tracking | `/` (Home) | Trendkaarten **% op-strategie** en **cumulatief engagement** (8 weken) — al live, geen extra bouwwerk. Dit ís de "betere tracking" uit de vraag; volwaardige rapportage komt in Full. |

### Wat nog niet

- **Report (CE-07)** — bewust uitgesteld naar Full. Met 2-4 weken historie is een rapport nog dun; de Home-trendkaarten geven al genoeg signaal om bij te sturen.
- **Campaign planner** — optioneel, hoort eigenlijk bij Full (thematische reeksen veronderstellen dat de losse-artikel-flow al stabiel draait).
- **HBA/StoryBrand-verdieping** — blijft draaiboek-fase 2, los van deze tier-indeling; kan parallel lopen maar is geen voorwaarde voor Medium.

### Bekende beperkingen om vooraf te benoemen

- Objectives sturen de content nog niet automatisch (zie boven) — communiceer dit zoals het draaiboek dat doet bij de LLM-probleemvragen: als richting, niet als belofte.
- Onboarding-run na Sources is nog niet gebouwd — reken op één handmatige refill-actie na de Sources-sessie.

### WordPress-koppeling (Medium — verplicht voor echte publicatie)

Doel: tenant kan handmatig publiceren (Article → **Publiceer**). Dit is het
eerste tier-niveau waar WP live moet staan.

**Gesprek met klant (5 min):**

1. In WordPress Admin → Gebruikers → Profiel → **Applicatie-wachtwoorden**
2. Naam bv. `Content Engine` → nieuw wachtwoord → **meteen** volledig kopiëren uit het grijs vak (24 tekens, vaak met spaties)
3. **Gebruikersnaam** = WP login-naam (`erik`), niet het e-mailadres

**Jij vóór Opslaan in CE (verplicht):**

```bash
curl -s -u 'GEBRUIKER:APPLICATION-PASSWORD' \
  'https://klantdomein.nl/wp-json/wp/v2/users/me'
```

→ Succes = JSON met `"slug"`. Faalt dit (`rest_not_logged_in`) → **niet** in Account invullen; opnieuw kopiëren of nieuw app-wachtwoord.

**Invullen in CE:** Account → Publiceren → site-URL, gebruiker, app-wachtwoord → Opslaan.

**Controle in CE:**

```bash
curl -s "https://ce.igisnext.com/api/admin/wp-diagnose?client_id=TENANT-UUID" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN"
```

→ `authenticatedAs` ingevuld, `app_password_length` ≈ 24–29.

**Publish mode Medium:** default `manual_with_review` of `manual_without_review` — geen automatic tenzij bewust afgesproken (zie Full).

**Zelfde WP-site als andere tenant (sandbox):** werkende credentials kopiëren:

```bash
curl -X POST "https://ce.igisnext.com/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN" \
  -d '{"clientId":"DOEL-TENANT","copyWordpressFromClientId":"BRON-TENANT"}'
```

Details, foutentabel en `.env`-fallback: `briefing/cursor-wordpress-onboarding.md`.

---

## Tier 3 — Full

**Wanneer:** klant draait structureel, wil kunnen verantwoorden wat het
oplevert. Komt overeen met draaiboek-fase 3 (overdracht) + rapportage.

**Tijd:** losstaande sessie, typisch na 4+ weken lopende content (anders is er niets om te rapporteren).

### Wat wél (bovenop Medium)

| Stap | Scherm | Toelichting |
|---|---|---|
| Report | `/report` | CE-07 periodiek gegenereerd: engagement, angle-mix, artikeltellingen, **KPI-highlighting** op `brand_goal_category`. |
| Analytics-koppeling | Account/Sources | GA4 OAuth waar van toepassing (`analytics-oauth-helper.php`) — nodig voor engagement-cijfers die verder gaan dan LinkedIn-eigen statistieken. |
| Archive volledig ingericht | `/archive` | `promo_budget`, `publishLinks` (Blog/LinkedIn/X), URL-check — de basis onder het rapport, zoals het draaiboek al zegt: "meten begint bij netjes afsluiten." |
| Campaign planner | `/campaign`, `/campaigns` | Thematische reeksen naast losse artikelen, nu zinvol omdat er al een werkend ritme staat. |
| Ritme + escalatiepad | — | Organisatorisch, geen scherm: wie beoordeelt wat wanneer, wie belt de klant bij stilstand (draaiboek fase 3). |

### WordPress — automatic modus (Full, optioneel)

Voor klanten die **zonder handmatige Publiceer-knop** willen draaien:

| Vereiste | Waar |
|---|---|
| `publish_mode = automatic` | Account → Publicatiemodus |
| WordPress groen | Zelfde flow als Medium (curl + wp-diagnose) |
| Min. 1 beeld in gallery | `/gallery` (automatic kiest beeld uit bibliotheek) |
| Proefpublicatie | Eén slot laten doorlopen CE-03 → check live site |

Niet beloven vóór wp-diagnose groen is en gallery gevuld. Zie
`briefing/cursor-wordpress-onboarding.md` § automatic en
`briefing/cursor-publish-modes.md`.

### Bekende beperkingen om vooraf te benoemen

- **Reports archiveren** — Save op de Report-pagina is nu een lokale bevestiging, geen echte archivering in de DB (backlog, zie `BUILD-NOTES.md` § Reports archief). Rapport blijft wel altijd terug te vinden via Archive-zoek op de ruwe `performance_reports`-data.
- **LinkedIn Ads-budget terugkoppelen** — nog handmatig veld, geen automatische sync (backlog § LinkedIn Ads-budget).
- **"Meetbaar in AI-antwoorden verschijnen"** — niet beloven; LLM-visibility-check (CE-LLM-Visibility, maandelijks) meet dit inmiddels wel structureel, maar het blijft een indicatie, geen garantie — zelfde nuance als in het draaiboek.

---

## Vergelijkingstabel (voor gesprek met klant/prospect)

| Onderdeel | Light | Medium | Full |
|---|:---:|:---:|:---:|
| Account + login | ✅ | ✅ | ✅ |
| Strategy (basis) | ✅ | ✅ | ✅ |
| Strategy (tone_styles, volledige schrijfwijzer) | — | ✅ | ✅ |
| Targets/objectives | — | ✅ (richtinggevend) | ✅ |
| Sources | — | ✅ (2+) | ✅ |
| Publiceren (WP/LinkedIn) | — | ✅ | ✅ |
| WordPress verify (curl + wp-diagnose) | — | ✅ | ✅ |
| Publish mode automatic | — | — | ✅ (optioneel) |
| Rolling flow (Theme→Research→Article) | — (Fastlane only) | ✅ | ✅ |
| Feedback-cyclus (CE-04) | — | ✅ | ✅ |
| Home-tracking (trendkaarten) | — | ✅ | ✅ |
| Report (CE-07) | — | — | ✅ |
| Analytics-koppeling | — | — | ✅ |
| Campaign planner | — | — | ✅ (optioneel) |
| Archive volledig (promo budget, links) | — | — | ✅ |

---

## Technisch — wat nodig is om dit als zelfstandige keuze te bouwen

Bovenstaande is **vandaag al uitvoerbaar** als handmatig proces (jij kiest
welke stappen je met de klant doorloopt). Om dit te laten landen in de
onboarding-hub uit `BUILD-NOTES.md` § Onboarding programma (backlog #17,
nog niet gebouwd) — een scherm waarin de tier expliciet gekozen wordt en
alleen de bijbehorende stappen getoond worden:

1. **`clients.onboarding_tier`** — kolom (`light` / `medium` / `full`), auto-schema pattern zoals overal elders in de API (`ce_table_has_column` + `ALTER`).
2. **`ce_build_home_todos()` tier-aware maken** — stappen als Targets/Sources/Publiceren/Report alleen tonen als de gekozen tier ze vereist; Light-tenants zien na het eerste Fastlane-artikel geen "vul Sources in"-todo die niet voor hen geldt.
3. **Tier-keuze bij tenant-aanmaak** — `./scripts/create-client.sh` / `POST /api/admin/create-client` uitbreiden met een tier-parameter, default `medium`.
4. **Upgrade-pad, geen downgrade-logica nodig** — een tenant die van Light naar Medium gaat krijgt gewoon de extra todo's te zien zodra de tier wijzigt; geen data hoeft te verdwijnen.
5. **Niet vereist:** een aparte Fastlane-variant voor Light. Fastlane blijft zoals het is; de tier bepaalt alleen welke stappen **ervoor en erna** getoond worden, niet hoe het artikel zelf tot stand komt.

Dit bouwwerk is niet nodig om vandaag met tiers te werken — het is alleen
nodig als je de tier-keuze wilt automatiseren in plaats van hem zelf te
sturen tijdens de kickoff.

### Gerelateerde documenten

| Onderwerp | Bestand |
|---|---|
| WordPress-koppeling (detail) | `briefing/cursor-wordpress-onboarding.md` |
| Publish modes | `briefing/cursor-publish-modes.md` |
| Week-voor-week draaiboek | `briefing/onboarding-eerste-klant.md` |
