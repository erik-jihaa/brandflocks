# ContentFlow — codebeoordeling en inpassing HBA-werk van Rogier

**Datum:** 29 september 2026
**Basis:** commit `fd504db` (gelijk aan staging, 3 sept 10:16), branch `cursor/contentflow-ritme-article-platform`
**Nieuwe branch met opschoning:** `cleanup/hba-default` (niet gecommit, niet gepusht)
**Beoordeeld:** `web/api` (242 bestanden, ~58k regels PHP), `frontend/src` (~250 bestanden, ~37k regels TS/React), migraties, `scripts/`

---

## 1. In het kort

De code krijgt een **4 van 8**: het werkt, maar is kwetsbaar. De frontend is netjes (5/8), de backend zit eronder (3/8) door twee lekken tussen tenants die vóór livegang dicht moeten, en de datalaag scoort 4/8. Rogiers twee briefings passen op de bestaande structuur, maar de kalibratiemodule kan pas zinvol gebouwd worden na drie voorbereidende stappen: tenant-isolatie dichten, een echt rollenmodel, en de HBA-prompts en -drempels uit ongeveer twintig plekken in de code halen. De herindeling van Overzicht en Rapport kan vrijwel direct: alle cijfers bestaan al.

---

## 2. Wat er uit de code is gehaald (branch `cleanup/hba-default`)

123 bestanden, 34 verwijderd, 89 aangepast, ruim 7.100 regels minder. PHP-lint, Python-compile, TypeScript-check en `vite build` slagen.

**HBA-schakelaar weg, HBA-laag is standaard.** `ce_client_hba_enabled()` en `ce_client_hba_enabled_by_id()` bestaan niet meer; overal is alleen de HBA-aan-route overgebleven. Weg zijn de schakelaar in Platform → tenant ("Merklaag"), `hbaEnabled` in account/platform/API-antwoorden, `TenantContext` (bestond alleen voor die vlag), de BrandElements-route zonder HBA, de Angle-mix, en de redacteursvragen (REDACTEUR-blok). Merkarchitectuur staat nu altijd in het menu. De kolom `clients.hba_enabled` blijft in de database staan maar wordt niet meer gelezen.

**Automatisch publiceren weg.** Met HBA aan was review op de artikelpagina altijd verplicht, dus de publicatiemodus "Automatisch inclusief plaatsen" kon nooit meer draaien. De optie is uit Mijn account; een opgeslagen `automatic` wordt gelezen als `manual_with_review`. Weg: `automatic-pipeline-repository.php`, `/admin/automatic-pipeline`, `/articles/{id}/auto-publish`, de auto-publish-knopen in de CE-01/CE-03-generatoren.

**Campagnes weg** (stond al verborgen): pagina's, API-routes, repositories, de Agenda-knop naar een campagne, `campaignId` in Snel artikel, en de CE-CampaignPlanner-generator. Tabel `campaigns` en kolom `content_slots.campaign_id` blijven staan.

**Titelposter weg** (stond al verborgen): poster-sectie en huisstijlinstellingen, `/articles/{id}/poster`, `poster-repository.php`, en de posterregel/woordpaar-vraag in CE-03. De kolommen blijven staan.

**Het `hide`-mechanisme in het menu is weg**, zodat er niets meer stil verborgen kan worden.

### Gedragswijzigingen om te weten

- Tenants die op "automatisch" stonden, krijgen nu een review-mail bij indienen. Een webhook-run van CE-01 loopt niet meer door naar CE-02/CE-03 (dat gebeurde alleen bij automatisch); de geplande run doet dat nog wel.
- Archiefpagina's tonen nu de HBA-zijbalk (voorheen de oude, omdat ze de vlag niet doorgaven). Een alleen-lezen zijbalk toont nu ook de gekozen waarden in plaats van alleen de koppen.
- `PATCH /platform/tenants/{id}` bestaat nog maar doet niets meer.

### Deploy-notities (belangrijk)

1. **FTP-deploy verwijdert geen bestanden.** Haal op de server handmatig weg: `campaigns.php`, `campaigns-item.php`, `campaign-plan.php`, `campaign-plan-repository.php`, `campaign-repository.php`, `articles-poster.php`, `poster-repository.php`, `automatic-pipeline-repository.php`, `admin-automatic-pipeline.php`, `articles-auto-publish.php`.
2. **CE-01, CE-03 en CE-07 opnieuw genereren en pushen** naar n8n. Tot dan draait de live CE-03 nog met redacteursvragen, posterregel en (inactieve) auto-publish-knopen.
3. **De n8n-workflow CE-CampaignPlanner uitzetten** en `N8N_CE_CAMPAIGN_PLAN_WEBHOOK_URL` uit de env halen.
4. **De stash** (`stash@{0}`, met HeroBrand en de gedragsregels-migratie) is gemaakt op de oude basis. Veel van dezelfde bestanden zijn nu aangepast, dus `git stash pop` op deze branch geeft conflicten. Beter: de stash-onderdelen stuk voor stuk opnieuw toepassen of laten herbouwen.

---

## 3. Codebeoordeling — 4 van 8

| Laag | Cijfer | Kern |
|---|---|---|
| Backend (PHP API) | 3/8 | Tenant-isolatie per endpoint in plaats van centraal; al lek |
| Frontend (React/TS) | 5/8 | Strak getypeerd, maar nepdata als fallback en een paar racebugs |
| Data en ops | 4/8 | Goede omgevingsscheiding, maar schema versnipperd en geen audittrail |

Schaal: 0 onbruikbaar, 4 werkt maar kwetsbaar, 6 solide productiekwaliteit, 8 voorbeeldig.

### Wat goed is

SQL gebruikt overal prepared statements. Inloggegevens van koppelingen zijn versleuteld (AES-256-GCM). De frontend compileert zonder fouten, ook in strict mode, zonder `any` en zonder `dangerouslySetInnerHTML`. Sessies lopen via cookies, niet via tokens in de browser. De ops-scripts hebben serieuze omgevingschecks en een bevestigingsvraag bij deploy naar productie. HBA v1-scoring gebruikt afgedwongen tool-output, wat JSON-fouten voorkomt.

### Wat vóór livegang moet (kritiek en hoog)

1. **Elke ingelogde tenant kan overstappen naar elke andere tenant.** `PATCH /account` met `switchClientId` controleert alleen of de doel-tenant bestaat, niet of de gebruiker erbij hoort (`account-repository.php`, rond regel 720). Zelf nagelopen: klopt.
2. **Elke ingelogde tenant kan alle content van alle tenants wissen.** `/admin/reset-content` accepteert de reset-token *of* een gewone tenantsessie, en draait dan een `DELETE` zonder `client_id`-filter (`admin-reset-content.php:15-17`). Zelf nagelopen: klopt.
3. **`/sources/sync` en `/linkedin/sync` nemen `client_id` uit het verzoek** en geven een lijst van alle tenants terug.
4. **Drie routes draaien vóór de inlogcontrole** (`/source-folders`, `/sources/{id}/document-upload`).
5. **Artikel indienen kan het artikel van een andere tenant overschrijven**: `ce_fetch_article_by_slot()` negeert de meegegeven `clientId`.
6. **Scrapers halen elke URL op** die een tenant invoert, ook interne adressen (SSRF).
7. **Geüploade strategiedocumenten en wizarddocumenten zijn publiek bereikbaar**; SVG-logo's kunnen script bevatten.
8. **Inloggen:** één wachtwoord per tenant, een globaal hoofdwachtwoord (`CE_LOGIN_PASSWORD`) als terugval, geen 2FA, geen rate limiting. Eén gedeelde header-token opent ~50 admin-routes, waaronder databasedump.

### Middel

Schema-wijzigingen draaien tijdens gewone verzoeken (~350 `ALTER/CREATE`-plekken). Migraties lopen alfabetisch en werken op een lege database alleen omdat eerst `schema.sql` wordt geïmporteerd. HBA-tabellen hebben geen foreign keys; bij het verwijderen van een tenant blijven HBA-gegevens achter. Er zijn zeven losse Anthropic-clients zonder retry bij 429/529. Foutmeldingen lekken SQL-tekst naar de client. In de frontend valt een mislukte API-aanroep terug op nepdata, zodat "opslaan" lokaal lijkt te lukken terwijl er niets is bewaard. Het Rapport heeft een Opslaan-knop die niets opslaat en herlaadt alles bij elke keuze in de artikel-pulldown.

### Laag

Een router van 1.107 regels met ~180 `if`-takken, componenten met 25–40 `useState`s, geen automatische tests (alleen scripts tegen een live omgeving), geen error boundary.

---

## 4. Rogiers kalibratiemodule op de bestaande structuur

Rogiers bouwvolgorde klopt, maar de code vraagt drie stappen ervóór. Rollen bovenop een systeem waar tenants elkaars data kunnen zien, beschermen de methodiek niet.

### Stap 0 — voorwerk (niet in Rogiers spec)

De kritieke punten 1–5 uit §3 dichten, het globale hoofdwachtwoord eruit, en de admin-token beperken. Zonder dit is "alleen Rogier en Erik zien de module" niet te garanderen.

### Stap 1 — rollen en toegang

**Wat er is:** `platform_users.role` kent alleen `admin` en `support`; `ce_require_platform_auth()` kent alleen "admin of niet". Platform- en tenantvlaggen delen één sessie. Geen 2FA.

**Wat erbij moet:** rollen `methodiek_eigenaar` en `beheerder` in `platform_users`, een echte rolcheck-helper, TOTP-2FA voor beide accounts, en een API-blok in `web/api/index.php` vóór de generieke auth-check (regel ~373) dat een 404 geeft voor iedereen anders. Let op: de frontend is één bundel en `.htaccess` stuurt elke onbekende URL naar de SPA met status 200. De 404 moet dus uit de API komen, en de kalibratieschermen moeten hun data pas ophalen na een geslaagde rolcheck; anders is het bestaan van de module zichtbaar.

### Stap 2 — HBA-laag uit de code naar een versiebeheerde configuratie

Dit is het grootste werk. De prompts, drempels en termlijsten staan nu op ongeveer twintig plekken:

| Ingrijppunt | Nu hardcoded in |
|---|---|
| Architectuurcheck | `hba-v2-prompts.php` (hard check, suggesties, wizard), `hba-identity-gate.php` (9 criteria, drempels FAIL ≤10 / REVIEW ≤13) |
| Themakeuze-advies | `hba-v2-prompts.php` (thema, research), `hba-v2-repository.php` |
| Tijdens schrijven | `hba-v2-prompts.php` (hint), `ce03-meta-repository.php` (GRENZEN-blok), `tone-styles.php`, `prompts/nl-avoid-calques-block.txt`, én de n8n-generatoren `generate-ce0{1,2,3,7}-workflow.py` en `prompt_fragments.py` |
| Score achteraf | `hba-prompt.php` (verbodslijst, systeemprompt, tool-schema, versie `v1.3`), `hba-repository.php` (archetypen, temperatuur), `hba-determinism-repository.php` (spreiding 0,5, 3 runs), `hba-v2-repository.php` (PBS-formule, ≥75 / ≥55) |
| Branche-afleider | `hba-branch-record-repository.php`, `hba-branche-seed.php`, `hygiene-repository.php` |
| Compressietest | **bestaat niet** |

Twee dingen maken dit lastiger dan het lijkt. Ten eerste zit een deel van de schrijfprompts in **n8n-workflows** die bij generatie worden ingebakken; die lezen geen configuratie uit de database. Die prompts moeten óf naar een PHP-endpoint verhuizen dat n8n aanroept, óf n8n moet de actieve configuratie ophalen per run. Ten tweede is er een **verbodslijst-bug**: de match is een simpele substring, dus "guide" slaat ook aan op "guideline".

Er is een ongebruikte tabel `calibration_reviews`; die kan dienen als begin van het logboek of weg.

### Stap 3 — `config_versie_id` bij elke uitkomst

| Uitkomst | Tabel | Versie opgeslagen? |
|---|---|---|
| Architectuurcheck | `architectuur_check_result` | Nee; `architecture_version` staat hardcoded op 1 |
| Artikelscore | `hba_scores` | Deels: model, `prompt_version`, hygiënelijst-versie |
| Thema-, research-advies, schrijfhint | `hba_theme_verdicts`, `hba_research_verdicts`, `hba_article_hints` | Alleen het model |
| Determinisme | kolommen op `clients` | Alleen in JSON; elke run overschrijft de vorige |
| Portfoliosignalen, PBS | niet opgeslagen, live berekend | — |

Rogiers spec vraagt één `hba_uitkomst`-tabel met `config_versie_id`. Pragmatischer: een `config_versie_id`-kolom toevoegen aan deze vijf tabellen, zodat bestaande schermen blijven werken.

### Stap 4 — Oneens-knop en logboek

Past goed: elke uitkomst heeft al een eigen tabelrij met id. Het formulier kan per uitkomst de input en output uit die rij halen. Wel nodig: de knop alleen renderen na een server-side rolcheck.

### Stap 5 en 6 — ijkset, kalibratierun, dashboard

**Wat er is:** een determinismemeting (3 runs, spreiding) die hergebruikt kan worden voor de stabiliteitstest; Rogier vraagt 5 runs en spreiding ≤5 op 100.

**Wat ontbreekt:** van de 41 ijkgevallen zijn er 8 van het soort `compressietest`, en die test bestaat niet in de code. Die 8 kunnen pas draaien als de compressietest gebouwd is, of moeten tijdelijk uit de go-live-berekening. Verder kent de branchebibliotheek wel `afgeleid`/`gecureerd`, maar niet de toestanden `voorlopig`/`bevestigd` uit briefing 13 (die zitten in de stash). En `/hba/branches` geeft nu alle branches aan elke tenant, inclusief branches die uit de input van andere tenants zijn afgeleid.

---

## 5. Rogiers Overzicht/Rapport-notitie op de bestaande structuur

Dit is grotendeels verhuizen, zoals Rogier zegt. Bijna alles bestaat.

| Onderdeel notitie | Bestaat al | Wat nodig is |
|---|---|---|
| Vier grafieken naar Rapport | Ja: `BrandWatcherSection`, `BrandPositionTrendChart`, contentrol-/tone-radar staan al op Rapport | Van Home halen; `HomeStrategyTrend` en `HomeFindabilityTrend` zijn al dode code |
| Engagement als één meter | Data: `engagementTrend` in `/home` | `HomeEngagementTrend` (grafiek) vervangen door een meter |
| GA4, LinkedIn, vindbaarheid naar Rapport | Ja, op Rapport onder "Meer detail" | Van Home (`HomeResultSection`) halen |
| Terugkeerregel na >7 dagen | Ja: `tenant_activity` houdt laatste login per dag bij | Veld `daysSinceLastLogin` meegeven in `/home` |
| Signaalblok (max. één) | Ja: `agenda-portfolio-signals-repository.php` | Zwaarste signaal in `/home` meegeven |
| Vijf meters | Merkarchitectuur, merkcijfer, balans (`contentrolMix`), ritme (`publishedThisWeek`/`postsPerWeek`), engagement | Bestaat allemaal |
| Geen decimaal tot determinismemeting | Ja: `hba_score_display_mode` | Gebruiken op de meter |
| Blauwe/grijze stip | Nee: `todos[]` heeft geen urgentieveld | Veld `blocking: true/false` per todo |
| Koppelingen alleen als niet gelegd | Deels: "Podcast toevoegen" is nu een gewone todo | Apart blok onderaan, integratiestatus bestaat al |
| Op-strategie versus generiek | Ja: `engagementDiffPct` en tellingen per rapport | Label aanpassen naar "jouw content" |
| Lege staat | Deels: Home toont bij een API-fout nepdata | Echte lege staat; nepdata-fallback eruit |

**Vooraf te repareren op het Rapport:** de nep-Opslaan-knop, het herladen bij elke keuze in de pulldown, de `.catch` die ontbreekt bij vindbaarheid, en de Engelse labels ("Generate Report").

**Eén tegenstrijdigheid in Rogiers notitie (§1.5):** "research liken" staat bij de blauwe (blokkerende) acties, maar "research-items" staan ook bij het uitstelbare, grijze werk. Voordat dit gebouwd wordt, moet Rogier kiezen welke van de twee het is.

**Routes (§3b) nagelopen tegen `App.tsx`:** `/report`, `/theme`, `/article/:slotId`, `/research`, `/agenda`, `/integrations`, `/brandarchitecture` en `/styleguide` bestaan allemaal. Rogier noemt "Identity / StyleGuide" als bestemming voor "Waar dit vandaan komt"; `/identity` stuurt door naar `/brandarchitecture`. Dat lijkt de juiste bestemming (daar staan de assen), niet `/styleguide`. "Plan een positioneringsstuk" → `/theme`. De Agenda heeft nog geen rolfilter via de URL; dat is voor de signaal-actielink nodig.

---

## 6. Voorgestelde volgorde

1. **Branch `cleanup/hba-default` nakijken, committen en deployen** (met de deploy-notities uit §2).
2. **Beveiliging dichten**: de kritieke en hoge punten uit §3. Klein werk per punt, groot risico.
3. **Overzicht en Rapport herindelen** (Rogiers notitie 15): klein, laag risico, direct zichtbaar voor klanten.
4. **Kalibratiemodule stap 1 t/m 4**, met stap 2 als grootste brok (inclusief de n8n-prompts).
5. **Compressietest bouwen** voordat de ijkset volledig kan draaien.
6. **Kalibratiemodule stap 5 en 6**.

Voor Rogier: de go-live-criteria kunnen pas groen worden als de compressietest bestaat (8 van de 41 ijkgevallen), en de "stabiliteit ≤5 op 100" vergt 5 runs per geval × 41 = ruim 200 LLM-aanroepen per kalibratierun.
