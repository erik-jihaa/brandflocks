# Content Engine — koppelingen ordenen (Integrations-hub)

**Status:** voorstel — nog niet gebouwd
**Datum:** aug 2026
**Raakt:** `IntegrationsPage.tsx`, `AccountPage.tsx`, `SourceForm.tsx`, `SourcesFormPage.tsx`, `WordPressPublishFields.tsx`
**Zie ook:** `docs/BUILD-NOTES.md` § Integrations-scherm, § LinkedIn publish multi-tenant, § Google Calendar, § Joomla publiceren, § Navigatie

---

## 1. Het probleem

Het aantal koppelingen is organisch gegroeid en staat nu verspreid over **drie schermen met drie
verschillende mentale modellen**. Inventarisatie van wat er vandaag live is:

| Koppeling | Waar te vinden | Type |
| --- | --- | --- |
| URL/RSS | Sources | invoer, geen auth |
| LinkedIn-scraper (Apify) | Sources → tab Linkedin / Scraper | invoer, API-token + wachtwoord |
| Mailbox (IMAP) | Sources → tab Mailbox | invoer, wachtwoord |
| Trello | Sources → tab Trello | invoer |
| Google Analytics (GA4) | Sources → tab Analythics **én** Integrations | meten, OAuth |
| Google Calendar | Sources → tab Calendar | invoer, OAuth (deelt Google-app met GA4) |
| WordPress / Joomla / static FTP | Integrations (pulldown `websiteCms`) | publiceren, credentials |
| LinkedIn publiceren | Integrations (OAuth) **én** Account (toggle + `organizationUrn`) | publiceren, OAuth |
| Online magazine | Account → extra bestemming | publiceren, URL |

**Drie concrete pijnpunten:**

1. **LinkedIn staat op drie plekken** (Sources-scraper, Integrations-OAuth, Account-bestemming) en
   het zijn drie verschillende dingen. Niets in de UI legt dat uit.
2. **GA4 staat op twee plekken** met verschillend gedrag — en de OAuth-callback springt altijd terug
   naar Sources, ook als je vanaf Integrations klikte (bekende beperking in BUILD-NOTES).
3. **Er is geen enkel scherm dat "hoe sta ik ervoor" beantwoordt.** De screenshot uit Account
   (`LinkedIn — on — Nog niet gekoppeld — beheer koppeling in Integrations`) is het symptoom: een
   aangezette bestemming die niet kan werken, en de gebruiker moet zelf naar een ander scherm.

Er komen er nog meer aan (Pexels, n8n per omgeving, klant-WP, bedrijfspagina-scope). Zonder
structuur wordt dit een lijst die niemand meer leest.

---

## 2. Het ordeningsprincipe

Scheid twee dingen die nu door elkaar lopen:

- **Koppeling** = de verbinding zelf. Auth, credentials, tokens, "werkt het ja/nee". → **Integrations**
- **Configuratie** = wat we met die verbinding doen. Aan/uit, welk bedrijfspagina-ID, welke
  Property ID, welke agenda, welke categorie. → **blijft waar het inhoudelijk hoort** (Account,
  Sources, de bron zelf).

Vuistregel voor Cursor: *staat er een wachtwoord, token of OAuth-knop? Dan hoort het op Integrations.
Is het een keuze over gedrag? Dan hoort het bij het onderwerp.*

Daarbovenop één ordening in **drie groepen**, in de volgorde waarin de engine draait:

```
INPUT       →   wat komt er binnen        (bronnen, agenda, mailbox, scrapers)
PUBLICEREN  →   waar gaat het naartoe     (website-CMS, LinkedIn, magazine)
METEN       →   wat komt er terug         (GA4, later LinkedIn-engagement)
```

Deze drie kopjes zijn dezelfde taal als de sidebar-secties (Knowledge → Content → Overview/Report),
dus de gebruiker leert geen nieuw model.

---

## 3. Nieuw Integrations-scherm

### 3.1 Statusstrook bovenaan

Eén regel die de vraag "hoe sta ik ervoor" in één blik beantwoordt:

```
Koppelingen   4 actief · 1 actie nodig · 3 niet ingesteld
```

Klik op "actie nodig" **filtert** de lijst (toggle, geen aparte modus): alleen kaarten met status
`action_required` blijven staan; lege groepskoppen verdwijnen. De strook toont dan
`Gefilterd op actie nodig · Toon alles`. Niet in URL/onthouden. Bij nul treffers is de teller niet
klikbaar. Meer niet — geen dashboard, geen grafiek.

### 3.2 Drie groepen met uniforme kaarten

Elke koppeling wordt dezelfde **connection card**, ongeacht het type. Vaste anatomie:

```
┌──────────────────────────────────────────────────────────────┐
│  LinkedIn                                    ● Gekoppeld     │
│  Publiceren · als Erik Hoogendorp · sinds 14 jul 2026        │
│  Posts vanuit Article → Publiceer.                           │
│                                                              │
│  [ Opnieuw koppelen ]  [ Ontkoppel ]      Instellingen  →    │
└──────────────────────────────────────────────────────────────┘
```

Vaste elementen, in deze volgorde:

1. **Naam** — merknaam, niets erbij.
2. **Statuspill** rechts (zie 3.3).
3. **Contextregel** — groep · wie/wat · sinds wanneer. Dit is de regel die LinkedIn-verwarring
   oplost: de scraper-kaart zegt `Input · profiel scrapen`, de publish-kaart `Publiceren · als …`.
4. **Eén zin** wat de koppeling doet in CE-termen.
5. **Primaire actie** (Koppel / Opnieuw koppelen) + **secundair** (Ontkoppel) + **Test** (live ping,
   verplicht op elke kaart — zie §9.4).
6. **Deeplink** naar configuratie elders (`Instellingen →` / `Bronnen (n) →`) via `?focus=…` op
   Account of Sources (zie §9.12).

Geen kaart is uitgeklapt bij het laden (§9.11). Alleen de kaart waar je op klikt vouwt open met de velden.
Dat is het verschil met nu: het huidige scherm toont álle WordPress/Joomla-velden altijd.

### 3.3 Statusmodel — vier statussen, niet meer

| Status | Pill | Wanneer |
| --- | --- | --- |
| **Gekoppeld** | groen | Test-knop live geslaagd (niet alleen "token aanwezig") |
| **Actie nodig** | rood | Test faalt, token verlopen, credentials afgekeurd, keten incompleet (GA4 zonder Property ID), *Account-bestemming aan maar niet gekoppeld*, of *automatic publish zonder werkende website-CMS* |
| **Niet ingesteld** | beige | nooit gekoppeld |
| **Niet beschikbaar** | grijs | platform-config ontbreekt (`LINKEDIN_CLIENT_ID` leeg, Calendar API uit) — tenant kan hier niets aan doen |

Die laatste bestaat nu niet en veroorzaakt onnodige supportvragen: als de platform-app niet
geconfigureerd is, moet de tenant geen knop zien die toch faalt.

**"Actie nodig" is de belangrijkste toevoeging.** De situatie uit de screenshot — LinkedIn `on` in
Account, geen OAuth — is precies dit geval en moet rood zijn op Integrations én in de statusstrook,
niet alleen een grijs zinnetje op een ander scherm.

### 3.4 Groepsindeling concreet

**Input** — Google Calendar · Mailbox · Apify-scraper · Trello · (URL/RSS: geen kaart, geen auth)
**Publiceren** — Website (WordPress/Joomla/FTP) · LinkedIn · ~~Online magazine~~ *(verborgen tot later)*
**Meten** — Google Analytics (GA4)

Google Calendar en GA4 delen dezelfde Google-app. Toon dat expliciet met een gedeelde subregel
`Via je Google-account` onder beide kaarten. **Ontkoppelen op Integrations trekt beide Google-diensten
tegelijk offline** (één OAuth-app, één disconnect).

---

## 4. Wat verandert er op de andere schermen

| Scherm | Nu | Straks |
| --- | --- | --- |
| **Account** | LinkedIn-toggle + `organizationUrn` + magazine-URL onder "Extra bestemming"; read-only WP-status | Blijft publish-**configuratie**. **Online magazine verborgen** (§9.7). Statuspill (zelfde vier statussen); bij rood knop `Koppel op Integrations →`. **Save niet blokkeren** bij losse bestemming (§9.8). Bij `publish_mode = automatic` zonder werkende CMS-koppeling: rood + waarschuwing (§9.9). Toggle = bedoeling, niet werkende staat |
| **Sources** | GA4- en Calendar-OAuth in bronform | Blijft (aanmaak-in-één-stap). Zelfde statuspill + `Beheer op Integrations`. Link onderaan `SourceForm.tsx` r.410 vervalt. URL/RSS: geen Integrations-kaart (§9.6) |
| **Integrations** | Twee losse blokken + altijd-open WP-velden | Drie groepen, uniforme kaarten, ingeklapt |

Belangrijk: **niets wordt weggehaald zonder vervanging.** De GA4-flow op Sources blijft intact.

---

## 5. Architectuur — één registry, geen losse kaarten

Dit is de kern; zonder dit groeit het scherm weer scheef bij koppeling nummer twaalf.

### 5.1 Frontend: declaratieve lijst

Nieuw bestand `frontend/src/config/integrations.ts`:

```ts
export type IntegrationGroup = 'input' | 'publish' | 'measure'
export type IntegrationStatus = 'connected' | 'action_required' | 'not_set' | 'unavailable'

export interface IntegrationDef {
  key: string                    // 'linkedin_publish', 'ga4', 'calendar', 'website_cms', …
  name: string
  group: IntegrationGroup
  purpose: string                // één zin
  settingsHref?: string          // '/account?focus=extra-destinations'
  connect?: () => Promise<void>
  disconnect?: () => Promise<void>
  test: () => Promise<{ ok: boolean; note: string }>   // verplicht — live ping
}
```

`IntegrationsPage.tsx` rendert `INTEGRATIONS.filter(i => i.group === g).map(renderCard)`. Een
koppeling toevoegen = één object toevoegen, geen JSX schrijven.

### 5.2 Backend: één statusendpoint

Nieuw: `GET /api/integrations/status` → per key `{ status, label, since, note }` voor de ingelogde
tenant. Bundelt wat nu verspreid zit over `fetchLinkedInOAuthStatus`, `fetchAllSources`,
`/api/admin/wp-diagnose` en `/api/admin/cms-diagnose`.

- Leest per client uit `clients` (`linkedin_oauth_*`, `wordpress_*`, `joomla_*`, `website_cms`,
  `publish_extra_destinations`) en uit `sources` (analytics/calendar OAuth-kolommen).
- Bepaalt `action_required` door **kruisverwijzing** (LinkedIn bestemming aan + geen OAuth;
  `publish_mode = automatic` + website-CMS niet groen; GA4 token wel + Property ID leeg — §9.10).
- **Google:** één disconnect wist Calendar + GA4 OAuth voor de tenant (§9.14).
- `unavailable` als platform-env ontbreekt.
- Eén call bij page load; Test-knoppen roepen bestaande diagnose/probe-endpoints aan.

**Platform:** dezelfde registry + statusmodel op `/platform` (tenant-overzicht) — gelijk trekken met
tenant-Integrations (§9.2). Geen aparte kaartlogica.

Hergebruik `ce_credential_encrypt`-conventies; geen tokens in de response, alleen status.

### 5.3 Meelift-fix: GA4-redirect

Geef `ce_analytics_oauth_frontend_redirect()` een `return_to`-parameter die bij start wordt
meegegeven (`sources` of `integrations`) en in de sessie bewaard. Default blijft `sources`, dus de
bestaande aanmaakflow verandert niet. Lost de bekende beperking uit BUILD-NOTES op.

---

## 6. Bouwvolgorde

**Eén release** — niet deployen in fases zonder Account/Sources-pills en GA4-redirect (§9.1).

1. **Statusendpoint** `GET /api/integrations/status` + kruisverwijzing-checks (`action_required`).
2. **Registry + kaartcomponent** (`IntegrationCard.tsx`), Integrations op drie groepen; Test per kaart.
3. **Statusstrook** + filter-toggle "actie nodig".
4. **Account + Sources**: statuspills, `useFocusSection` + `?focus=…` deeplinks.
5. **GA4/Calendar `return_to`**-fix + Google unified disconnect.
6. **Platform** (indien inzelfde release): zelfde status/registry voor tenant-beheer.
7. **Migratie-check**: tenants op Integrations vs `cms-diagnose` / `wp-diagnose`.

---

## 7. Niet in scope

- Bronnen verplaatsen van Sources naar Integrations. Een bron is inhoud (welke feed, welke
  mailbox), geen koppeling — die blijft op Sources.
- Nieuwe koppelingen bouwen (Pexels, n8n, LinkedIn-bedrijfspagina). Geen placeholder-kaarten —
  pas registry-entry als de koppeling echt bestaat (§9.3).
- **Online magazine** als publish-bestemming in UI — verborgen tot verder besluit (§9.7).
- Multi-user/Team-rechten op koppelingen — zie productvisie-backlog.
- Automatisch vernieuwen van verlopen tokens. Wel tonen, niet oplossen.

---

## 8. Acceptatiecriteria

- Op `/integrations` staan alle koppelingen in drie groepen; geen koppeling is alleen elders
  bereikbaar.
- Een aangezette bestemming zonder werkende koppeling is **rood** op Integrations én op Account.
- Elke koppeling toont dezelfde elementen in vaste volgorde; **Test** live ping op elke kaart.
- Een nieuwe koppeling toevoegen kost één entry in `integrations.ts` plus status + test backend.
- Bestaande flows werken: Analytics-bron op Sources, LinkedIn koppelen, WP/Joomla publiceren.
- Platform gebruikt dezelfde status/registry als tenant-Integrations.

---

## 9. Antwoorden op vragen uit Cursor (besluiten aug 2026)

### 1 — Gefaseerd deployen (stap 1–2 zonder Account/Sources)?

**Nee. Eén release.** Niet half live: Integrations-hub, Account/Sources-pills, GA4-redirect en
deeplinks horen in dezelfde deploy.

### 2 — Alleen tenant, of ook platform-dashboard?

**Gelijk trekken.** `/platform` (tenant-overzicht / beheer) gebruikt dezelfde registry +
statusendpoint — geen tweede kaartlogica.

### 3 — Placeholder-kaarten voor Pexels / n8n / bedrijfspagina?

**Nee.** Pas toevoegen aan `integrations.ts` + backend wanneer de koppeling echt bestaat.

### 4 — "Gekoppeld" = credentials of live Test?

**Elke kaart heeft een Test-knop die live pingt.** Groen (`connected`) alleen na geslaagde test
(of equivalente diagnose-call: `wp-diagnose`, `cms-diagnose`, `ce_apify_probe_token()`, OAuth
refresh-check, enz.). Credentials alleen aanwezig ≠ groen.

### 5 — Apify-scraper: één kaart of één per bron?

**Één kaart "Apify-scraper".**

Reden staat in de code: `ce_scraper_resolve_apify_token()` valt terug op een **platformtoken**
(`APIFY_API_TOKEN` / `ce.local.php → apify_api_token`), met een optionele per-bron override
(`scraper_api_token_ref`). De credential is dus niet per bron — precies wat een koppeling is.

Wat per bron blijft: actor-URL, `scraperActorOrTaskId`, LinkedIn-profiel + wachtwoord, webhook.
Dat is broninhoud en blijft op Sources.

De kaart toont:

```
Apify-scraper                                  ● Gekoppeld
Input · platformtoken · 3 scraper-bronnen
Haalt LinkedIn-profielen en websites op voor CE-02.
[ Test token ]                              Bronnen (3)  →
```

`Bronnen (3)` is een deeplink naar Sources, gefilterd op tab Scraper/Linkedin. Heeft één bron een
eigen token, dan een subregel `1 bron gebruikt een eigen token`. Status `unavailable` als
`ce_apify_platform_configured()` false is én geen bron een eigen token heeft.

Hergebruik `ce_apify_probe_token()` voor de Test-knop en `GET /api/admin/scraper-sync` voor
`tokenConfigured`.

### 6 — URL/RSS zonder kaart?

**Klopt — geen kaart, zo eenvoudig mogelijk.** Geen uitlegregel op Integrations; URL/RSS blijft
puur op Sources.

### 7 — Online magazine op Integrations?

**Verberg de magazine-optie voor nu** — niet in registry, niet in Account "Extra bestemming
toevoegen". Later opnieuw beoordelen.

### 8 — LinkedIn `on` zonder OAuth: Save blokkeren?

**Niet blokkeren. Alleen rood tonen.**

Drie redenen:

1. Account-save draagt ook alle andere velden (planning, HBA, reviewer). Eén niet-gekoppelde
   bestemming mag de rest niet gijzelen.
2. Eerst aanzetten, dan koppelen is een legitieme volgorde — zeker tijdens onboarding, waar de
   tenant de bestemming kiest vóór hij de LinkedIn-login bij de hand heeft.
3. Het echte risico ligt niet bij Save maar bij **Publiceer**, en dáár bestaat de guard al
   (BUILD-NOTES § Handmatig publiceren: *OAuth ontbreekt/verlopen → rode melding onder Publiceer*).

Wel doen: bij `on` + geen OAuth de rij op Account rood met knop `Koppel op Integrations →`, en de
koppeling telt mee als **actie nodig** in de statusstrook. De toggle blijft aan — hij beschrijft de
bedoeling, niet de werkende staat.

### 9 — Automatic publish zonder werkende CMS?

**Ja — rood op Integrations én waarschuwing op Account** als `publish_mode = automatic` terwijl
website-CMS (WordPress/Joomla/static) niet groen is. Je kunt niet automatisch publiceren als de
keten niet staat. (Los van Save — zie §9.8.)

### 10 — GA4 "niet actief" / tokens op bronrij

*Toelichting bij de vraag, want die is dubbelzinnig geformuleerd:* GA4-tokens staan **niet** op
`clients` maar op de **bronrij** (`/sources/analytics/oauth/start?source_id=…`). De vraag is dus
wat de kaart toont als de koppeling wel bestaat maar niets kan opleveren. Er is geen `active`-vlag
op sources, dus "niet actief" kan drie dingen betekenen. Per geval:

| Situatie | Status | Waarom |
| --- | --- | --- |
| Bron bestaat, OAuth geldig, Property ID ingevuld | **groen** | werkt |
| Bron bestaat, OAuth geldig, **Property ID leeg** | **rood** — actie nodig | koppeling is af, maar Report/Overview krijgen niets. Dit is het echte geval |
| Bron verwijderd | **beige** — niet ingesteld | token gaat mee met de rij; er is geen koppeling meer om te tonen |
| OAuth verlopen / geweigerd | **rood** | standaard |

Regel voor Cursor: **groen alleen als de keten compleet is** (token geldig **én** Property ID
gevuld). Een koppeling die technisch bestaat maar geen data kan leveren is geen succes.

Bij meerdere analytics-bronnen: één kaart, status is de *slechtste* van de bronnen, subregel
`2 van 3 bronnen leveren data`.

### 11 — Kaarten altijd ingeklapt?

**Ja.** Credentials/velden alleen na klik op de kaart. Geen uitzondering voor Website.

### 12 — Bestaan de anchors al?

**Nee, die moeten mee gebouwd worden.** In `AccountPage.tsx` staan vier `id=`-attributen en die
zitten allemaal op inputvelden (`account-password`, `account-hba-enabled`, …), niet op secties. Er
is nergens hash-afhandeling: geen `scrollIntoView`, geen `location.hash` in de frontend.

Gebruik **geen** hash maar een query-param, want `IntegrationsPage` werkt al met
`useSearchParams` en react-router slikt hashes stroef:

- Deeplink: `/account?focus=extra-destinations`
- Kleine hook `useFocusSection(param)` — leest de param, scrollt met `scrollIntoView({ block: 'center' })`
  en zet 2 seconden een subtiele highlight op de sectie.
- Benodigde ids: `extra-destinations` (Account), `publish-mode` (Account), en op Sources per bron
  `source-{id}`.

Eén hook, drie ids. Bouw hem in stap 4 van de bouwvolgorde, niet eerder.

### 13 — Filter "actie nodig": verbergen of highlighten?

**Verbergen — als toggle, niet als aparte modus.**

Bij negen-plus kaarten is highlighten te zwak; je moet nog steeds scannen. Gedrag:

- Klik op `1 actie nodig` → alleen kaarten met status `action_required` blijven staan.
- Groepskoppen met nul treffers verdwijnen mee (geen lege kopjes).
- De strook toont dan `Gefilterd op actie nodig · Toon alles`, zodat altijd zichtbaar is dat je een
  deelverzameling ziet.
- Niet in de URL bewaren, niet onthouden tussen bezoeken — het is een kortstondige actie.
- Nul kaarten met `action_required` → de teller is niet klikbaar.

### 14 — Google: Calendar ontkoppelen vs GA4?

**Eén Google-app.** Ontkoppelen op Integrations (of expliciete "Google ontkoppelen") zet **Calendar
én GA4** offline voor die tenant. UI: gedeelde subregel `Via je Google-account`; één disconnect-actie
 waar beide kaarten onder vallen.
