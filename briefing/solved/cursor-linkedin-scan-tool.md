# LinkedIn Scan-tool — `linkedin-analyse.igisnext.com`

**Doel:** URL van een LinkedIn-bedrijfspagina invoeren → Apify haalt de posts op →
de tool genereert een vormgegeven klantrapportage die je met één klik als e-mail
kunt versturen of plakken.

Gebruik: acquisitie. Vóór een gesprek met een prospect in twee minuten een scan
draaien die laat zien dat je hun situatie kent — zoals de handmatige
GrowthElements-scan, maar dan zonder handwerk.

**Belangrijk:** dit is een **losstaande tool**, geen onderdeel van de Content Engine.
Eigen subdomein, eigen (kleine) database, geen tenant-context, geen login-koppeling
met CE. Wel: dezelfde Apify-actor en dezelfde analyse-logica hergebruiken.

**Design — twee lagen (niet door elkaar halen):**

| Laag | Doel | Vormgeving |
|---|---|---|
| **App** (`index.php`, `scan.php`, knoppen) | URL invoeren, scan draaien, rapport bekijken | **Content Engine** — zelfde tokens als `ce.igisnext.com` (zie § 5.0) |
| **Rapport** (e-mailfragment) | Plakken in Gmail/Outlook | **GrowthElements-scan** — documentstijl uit `GrowthElements-social-media-scan.docx` / screenshot (zie § 5.1) |

De app toont het rapport in een preview; wat naar het klembord gaat is **alleen** het
e-mailfragment — geen pagina-wrapper, geen witte body-rand.

---

## 1. Wat er al bestaat (hergebruiken, niet opnieuw bedenken)

Dit is de grootste tijdwinst — de moeilijke onderdelen zitten al in de CE-codebase.

| Onderdeel | Waar | Hergebruik |
|---|---|---|
| **Apify-actor voor bedrijfspagina's** | `ce_scraper_linkedin_company_actor_id()` in `web/api/scrape-repository.php` → `apimaestro~linkedin-company-posts-batch-scraper-no-cookies` | Exact dezelfde actor. De veldstructuur is bekend en getest (zie § 3). |
| **Apify-aanroep + wachten op resultaat** | `ce_apify_http_json()`, `ce_apify_wait_for_terminal_run()`, `ce_apify_fetch_run()` | Kopiëren naar de nieuwe tool. Bevat al retry-, timeout- en foutafhandeling. |
| **Kosten uit een run halen** | `ce_apify_unwrap_run()` — leest `usage.ACTOR_COMPUTE_UNITS` en `usageTotalUsd` | Directe basis voor de kostenbewaking in § 4. |
| **Herkennen bedrijfs- vs persoonlijk profiel** | `ce_scraper_linkedin_is_company_profile()` — regex op `linkedin.com/company/` | Gebruiken als invoervalidatie. |
| **Analyse-logica** | `briefing/GrowthElements-social-media-scan.docx` + de analyse in die conversatie | De metrieken uit § 3 zijn precies wat daar handmatig is berekend. |

**Aanpak:** kopieer de Apify-helpers naar de nieuwe codebase in plaats van de CE-API
te importeren. Reden: de tool moet los kunnen draaien en niet meebreken met een
CE-deploy. Het is ~300 regels; duplicatie is hier goedkoper dan koppeling.

---

## 2. Architectuur

```
linkedin-analyse.igisnext.com
├── index.php          formulier: URL invoeren
├── scan.php           POST → Apify-run → analyse → rapport tonen
├── api/
│   ├── apify-client.php     (gekopieerd uit scrape-repository.php)
│   ├── analyzer.php         metrieken berekenen uit de dataset
│   ├── report-renderer.php  e-mailfragment HTML (GrowthElements-stijl)
│   └── scan-repository.php  opslag + kostenlogging
├── assets/
│   ├── app.css              CE-tokens — alleen scherm-UI
│   └── logo.svg             kopie van SVG/iGiSNext_Blue-main.svg
└── includes/
    └── config.local.php  APIFY_API_TOKEN, wachtwoord, tarieven
```

**Stack:** PHP + MySQL, zoals de rest — geen nieuwe technologie voor een tool van deze
omvang. Rendering server-side, geen React-build nodig.

**Database:** één kleine tabel, mag in een aparte database of naast de bestaande
(níet in de CE-tenantdatabase mengen):

```sql
CREATE TABLE IF NOT EXISTS scans (
  id CHAR(36) NOT NULL PRIMARY KEY,
  company_url VARCHAR(500) NOT NULL,
  company_name VARCHAR(255) NULL,
  company_slug VARCHAR(255) NULL,
  posts_count INT NULL,
  apify_run_id VARCHAR(100) NULL,
  compute_units DECIMAL(10,4) NULL,
  cost_usd DECIMAL(10,4) NULL,
  raw_data LONGTEXT NULL,          -- JSON dataset, voor hergebruik zonder nieuwe kosten
  metrics JSON NULL,               -- berekende uitkomsten
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_slug_created (company_slug, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`raw_data` bewaren is belangrijk: bij een tweede scan van dezelfde pagina binnen
X dagen hergebruik je de opgeslagen data en betaal je niets (zie § 4).

---

## 3. De analyse

De metrieken hieronder zijn exact wat de GrowthElements-scan bruikbaar maakte.
Bereken ze uit de Apify-dataset.

### 3.1 Ontdubbelen — doe dit eerst

De actor levert **reposts dubbel**: de repost-rij en de originele rij hebben dezelfde
tekst en tijdstip. In de GrowthElements-dataset waren 21 rijen in werkelijkheid
17 unieke posts. Zonder ontdubbeling kloppen ritme en gemiddelden niet.

Methode: dedupliceer op de eerste ~120 tekens van `text`. Filter ook rijen waarin
`author/name` leeg is (de scraper zet daar samenvattingsregels neer) en rijen waarvan
`author/name` niet het gescande bedrijf is (reposts dóór anderen).

### 3.2 Metrieken

| Metriek | Berekening | Velden |
|---|---|---|
| Periode | min/max van `posted_at/date` | `posted_at/date` |
| Aantal posts | rijen na ontdubbeling | — |
| Ritme | posts ÷ weken in periode | `posted_at/date` |
| **Stiltes** | grootste gaten tussen opeenvolgende posts, top 3 in dagen | `posted_at/date` |
| Gem. engagement | gemiddelde en mediaan van `stats/total_reactions` | `stats/total_reactions` |
| Reacties | aantal posts met `stats/comments` leeg of 0 | `stats/comments` |
| Formats | verdeling van `media/type` (image / article / video / none) | `media/type` |
| Beste post | hoogste `total_reactions` + de tekst erbij | `text`, `stats/*` |
| Zwakste posts | laagste `total_reactions`, met datum | idem |
| Volgers | `author/follower_count` | `author/follower_count` |
| Hashtags | top 10 uit `text` via regex `#(\w+)` | `text` |
| Taal | verdeling `post_language_code` | `post_language_code` |

### 3.3 Afgeleide observaties (dit maakt het rapport waardevol)

Losse cijfers overtuigen niet; de conclusies eruit wel. Genereer regelbaar:

| Observatie | Trigger |
|---|---|
| "Geen vast ritme" | ≥1 gat > 21 dagen |
| "Eenrichtingsverkeer" | >70% van de posts heeft 0 comments |
| "Weinig variatie in vorm" | >80% van de posts heeft hetzelfde `media/type` |
| "Engagement onder verwachting" | gem. reacties < 5% van `follower_count` |
| "Onbenutte piek" | beste post ≥2× de mediaan → benoem die post expliciet |
| "Recente terugval" | gemiddelde laatste 3 posts < 50% van totaalgemiddelde |

**Bewust weglaten:** de merk-inhoudelijke beoordeling (past dit bij hun kernboodschap?)
kan een geautomatiseerde scan niet betrouwbaar bepalen. Dat is precies de waarde die
jij in het gesprek toevoegt — het rapport eindigt daarom met een uitnodiging, niet met
een oordeel. Zie § 5.4.

### 3.4 Optioneel: AI-samenvatting

Overweeg één Claude-call die de post-teksten leest en in 2-3 zinnen de rode draad
benoemt ("jullie posts draaien consequent om X"). Dat tilt het rapport boven
statistiek uit. Kosten: verwaarloosbaar (~€0,01 per scan). Maak het een schakelaar
zodat de tool ook zonder werkt.

---

## 4. Kostenbewaking

Dit is de eis "voor een klein bedrag" — bouw het expliciet, want een Apify-actor die
per ongeluk 500 posts ophaalt is zo tientallen euro's.

| Maatregel | Implementatie |
|---|---|
| **Limiet op posts** | `maxPosts` (of gelijkwaardige actor-parameter) hard op bijvoorbeeld 30 zetten in de input, niet instelbaar via de UI. |
| **Kosten vooraf tonen** | "Deze scan kost circa € 0,05" vóór bevestiging — schatting op basis van eerdere runs uit de `scans`-tabel. |
| **Werkelijke kosten loggen** | Uit `usageTotalUsd` en `ACTOR_COMPUTE_UNITS` van de run, opslaan in `scans`. |
| **Cache** | Zelfde `company_slug` binnen 7 dagen → hergebruik `raw_data`, geen nieuwe run. Toon "resultaat van 3 dagen geleden" + knop "opnieuw ophalen". |
| **Dagelijkse cap** | Weiger nieuwe scans boven X per dag (bijv. 20) of boven een dagbedrag. Beschermt tegen een vergeten tab die blijft herladen. |
| **Timeout** | Run afbreken na 120 seconden; toon nette foutmelding. |

**Let op — USD versus EUR.** In de bestaande CE-code zit hier een slordigheid:
`ce_apify_unwrap_run()` geeft `cost_usd` terug, maar in `scrape-repository.php` wordt
die waarde op sommige plekken direct in de kolom `cost_eur` geschreven zonder omrekening.
Doe dat in deze tool wél goed: sla USD op als `cost_usd` en reken om met een instelbare
koers, of toon consequent in dollars. Neem de fout niet over.

---

## 5. Het rapport

### 5.0 App-UI — Content Engine-stijl

De **tool-pagina's** volgen `.cursor/rules/content-engine-design.mdc` (zelfde merk als CE,
geen apart design):

| Token | Waarde | Gebruik op scan-tool |
|---|---|---|
| Achtergrond pagina | `#EAE6DC` (`--ce-offwhite`) | `<body>` |
| Tekst | `#2E2014` (`--ce-black`) | body copy |
| Koppen / links | `#033DFF` (`--ce-blue`) | paginatitel, field headers |
| Accent | `#52FFA8` (`--ce-green`) | pill-knoppen (tekst blauw op groen) |
| Inputs | `#F7EEF3` (`--ce-pink`) | URL-veld |
| Secundair | `#ADA695` (`--ce-beige`) | hints, placeholders |
| Font | Figtree 400/600 (Google Fonts) | mag op web — **niet** in e-mailfragment |
| Logo | `SVG/iGiSNext_Blue-main.svg` | header links |

Layout: eenvoudig — gecentreerde kolom ~820px, logo + titel "LinkedIn Scan", één
formulier, daaronder preview van het rapport in `<div id="report-preview">` (geen CE
grid/nav — single-purpose tool).

Knoppen: groene pill (primary), beige pill (secondary) — zelfde patronen als CE
`PillButton`.

### 5.1 Rapport-vormgeving — GrowthElements (e-mail)

Visuele referentie: GrowthElements-scan screenshot + `briefing/GrowthElements-social-media-scan.docx`.

| Element | Stijl |
|---|---|
| Achtergrond document | `#EAE6DC` — **overal**, tot aan de rand (geen wit kader) |
| Logo | iGiSNext (blauw + groen), als **inline `<img>`** met absolute URL |
| Hoofdtitel | `#033DFF`, Arial/Helvetica, **underline**, 22–24px |
| Sectiekoppen | `#2E2014`, bold, 14–16px ("Over deze scan", "De cijfers") |
| Body | `#2E2014`, 14px, line-height 1.35, Arial/Helvetica |
| Intro "Over deze scan" | body + underline (zoals handmatige scan) |
| Tabel "De cijfers" | 2 kolommen Meting \| Uitkomst; **alleen horizontale lijnen**; geen verticale randen |
| Binnenmarge content | 24–32px padding binnen het beige blok |

### 5.2 Eis: plakken in e-mail **zonder witte rand**

Veel plak-fouten komen doordat de **hele webpagina** (witte `<body>`) wordt gekopieerd
in plaats van alleen het rapport.

| Regel | Reden |
|---|---|
| **Tabel-layout**, geen flexbox/grid | Outlook (Word-renderer) |
| **Inline CSS** op elk element | Gmail strijpt `<style>` |
| **Geen webfonts in fragment** | Arial/Helvetica in e-mail |
| **Buitenste laag: `<table width="100%" bgcolor="#EAE6DC">`** | Beige vult compose-breedte |
| **`cellpadding="0" cellspacing="0" border="0"`** | Geen onbedoelde witruimte |
| **Geen witte `<body>` in klembord-HTML** | Witte rand bij plakken |
| **Binnenste content max 600px**, gecentreerd | Gmail/Outlook standaard |
| **`bgcolor`-attribuut** naast `background-color` | Outlook negeert soms CSS |
| **Cijfers als tekst**, geen grafiek-afbeeldingen | Vaak geblokkeerd |

**Structuur klembord-fragment** (alleen dit kopiëren):

```html
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       bgcolor="#EAE6DC" style="margin:0;padding:0;background-color:#EAE6DC;">
  <tr>
    <td align="center" style="padding:24px 16px;background-color:#EAE6DC;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
             style="max-width:600px;width:100%;background-color:#EAE6DC;">
        <!-- logo, titel, secties, metingen-tabel -->
      </table>
    </td>
  </tr>
</table>
```

**Op scan.php:** preview toont het fragment op `--ce-offwhite` (zelfde kleur). Knop
"Kopieer voor e-mail" pakt **alleen** `#scan-report-email` — nooit `#page-shell`.

**Test na bouw:** plak in Gmail web + Outlook — beige tot aan compose-rand, geen witte marge.

Als je toch een visueel element wilt: een balk van een gekleurde `<td>` met
breedte-percentage werkt overal. Geen JavaScript, geen SVG in het fragment.

### 5.3 Knoppen (app)

1. **Kopieer voor e-mail** — `navigator.clipboard.write()` met `text/html` = fragment uit § 5.2 + `text/plain` fallback.
2. **Download als HTML** — fragment als `.html`-bestand.
3. **Open in e-mail** — kopieer eerst HTML, daarna `mailto:?subject=Social Media Scan …`.

### 5.4 Opbouw van het rapport

Volg de structuur van `briefing/GrowthElements-social-media-scan.docx` — die is al op
een klant getest:

| Blok | Inhoud |
|---|---|
| Kop | "Social Media Scan — {bedrijfsnaam} op LinkedIn", datum, "uitgevoerd door ContentFlow" |
| Over deze scan | Twee zinnen: wat is bekeken, welke periode. Expliciet: momentopname, geen eindoordeel |
| De cijfers | Tabel met de metrieken uit § 3.2 |
| Wat opvalt | De gegenereerde observaties uit § 3.3 |
| Wat dit oplevert | Vaste tekstblokken per gevonden knelpunt (ritme → vaste planning, geen reacties → redactiestap, enz.) |
| Tot slot | Uitnodiging tot gesprek |

**Toon:** beschrijvend, niet belerend. "14 van de 17 posts kregen geen reactie" is
sterker dan "jullie engagement is slecht" — en het is hun eigen cijfer, dus
onweerlegbaar.

---

## 6. Toegang en juridisch

**Afscherming:** de tool moet **niet publiek toegankelijk** zijn. Anders draait iemand
scans op jouw Apify-rekening. Minimaal HTTP Basic Auth via `.htaccess`, of een
wachtwoordveld met een hash in `config.local.php`. Geen gebruikersbeheer nodig.

**Twee dingen om vooraf te regelen:**

- **Scraping en voorwaarden.** LinkedIn staat geautomatiseerd verzamelen in zijn gebruiksvoorwaarden niet toe, ook bij publieke bedrijfspagina's. Apify draait dit via derden en dat gebeurt op grote schaal, maar het risico ligt bij de gebruiker. Praktisch: gebruik het voor eigen analyse en klantgesprekken, niet als een dienst die je publiek aanbiedt of doorverkoopt. Ik ben geen jurist — als je dit commercieel wilt aanbieden, leg het even voor.
- **AVG.** Bedrijfspagina-posts zijn grotendeels bedrijfsgegevens, maar `author/name` en genoemde personen zijn persoonsgegevens. Bewaar `raw_data` niet langer dan nodig (voorstel: 90 dagen, dan automatisch opruimen) en zet geen namen van individuen in het rapport dat je verstuurt.

---

## 7. Bouwvolgorde

1. **Skelet + Apify-koppeling** — formulier, URL-validatie, actor aanroepen, ruwe JSON opslaan en tonen. Hiermee weet je meteen of de actor doet wat je verwacht.
2. **Analyzer** — metrieken uit § 3.2, met ontdubbeling. Valideer tegen de GrowthElements-dataset: je moet op 17 posts, 0,6/week en de stiltes van 35/32/26 dagen uitkomen. Dat is je testcase.
3. **Kostenbewaking** — limieten, cache, logging (§ 4).
4. **Rapport-renderer** — GrowthElements e-mailfragment (§ 5.1–5.2) + kopieerknoppen; app-UI in CE-stijl (§ 5.0).
5. **Observaties** — de regels uit § 3.3.
6. **Afscherming + opruimtaak** voor oude `raw_data`.
7. *Optioneel:* AI-samenvatting (§ 3.4).

Stap 1-4 is een werkende tool. De rest is aankleding.

---

## 8. Testcase

Gebruik `https://www.linkedin.com/company/growthelements/` als vaste testcase. De
handmatige analyse gaf:

| Metriek | Verwacht |
|---|---|
| Unieke posts | 17 (uit 21 ruwe rijen) |
| Periode | 8 jan – 30 jul 2026 |
| Ritme | 0,6 posts/week |
| Grootste stiltes | 35, 32, 26 dagen |
| Gem. engagement | 7,9 (mediaan 8,5) |
| Posts zonder comments | 14 van 17 |
| Volgers | 130 |

Wijkt de tool hiervan af, dan zit er een fout in de ontdubbeling of de datumparsing.
