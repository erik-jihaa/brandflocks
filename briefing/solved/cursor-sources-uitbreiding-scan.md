# Scan — vijf nieuwe brontypen voor Sources

**Status:** verkenning, nog geen bouwbesluit
**Datum:** aug 2026
**Vraag:** YouTube/TikTok, Google Drive, PDF-upload, Slack, Asana — wat kan er, en hoe voeg je het in?

---

## 1. Samenvatting

| # | Bron | Haalbaar | Auth | Kosten | Werk | Advies |
| --- | --- | --- | --- | --- | --- | --- |
| 3 | **PDF-upload** | ja | geen | geen | klein | **eerst doen** |
| 1a | **YouTube-kanaal** | ja, via RSS | geen | geen | zeer klein | **eerst doen** |
| 5 | **Asana** | ja | PAT of OAuth | geen | klein — kloon van Trello | doen |
| 2 | **Google Drive** | ja, mits `drive.file` | Google OAuth (bestaat al) | geen | middel | doen, met de scope-val vermijden |
| 1b | **TikTok-account** | alleen via Apify | Apify-token (bestaat al) | per run | middel | kan, kosten bewaken |
| 4 | **Slack** | technisch ja | eigen Slack-app + review | geen | groot | **laatste — privacyvraag eerst** |

Twee daarvan zijn bijna gratis binnen te halen. Eén (Slack) is niet zozeer een technisch probleem
als een besluit dat je bewust wilt nemen.

---

## 2. Hoe een brontype wordt ingevoegd — het bestaande patroon

Twaalf aanraakpunten. Iedere bestaande bron volgt exact dit pad; wijk er niet van af.

**Frontend**

1. `types/sources.ts` — `SourceTab` uitbreiden (nu: `'URL/RSS' | 'Blogs' | 'Linkedin' | 'Scraper' | 'Mailbox' | 'Analythics' | 'Trello' | 'Calendar'`)
2. `SourceTabs.tsx` — knop toevoegen
3. `SourceForm.tsx` — velden achter `{category === 'X' && …}`; gebruik `Field` en `Pulldown`, geen handgerolde controls
4. `defaultSourceRowTitle()` — regel voor de nieuwe tab
5. `sourceFromDto` / payload-mapping onderaan `SourceForm.tsx`

**Datamodel**

6. `ce_ensure_sources_category_enum()` — nieuwe waarde in de ENUM
7. `ce_ui_category_to_db()` / `ce_db_category_to_ui()` — beide richtingen
8. Nieuwe kolommen via het bestaande auto-schema-patroon (`ALTER TABLE sources ADD COLUMN`, idempotent — zie `analytics-oauth-helper.php` regel 26)
9. Twee `FIELD(...)`-sorteerlijsten in `sources-repository.php` (regels ~340 en ~349) — vergeet de tweede niet

**Backend**

10. Check-handler: de `if/elseif`-keten in `ce_check_source()` (`sources-repository.php` vanaf ~837)
11. Sync-handler in `sources-sync-repository.php`, zodat de uurlijkse `CE-Sources Sync` hem meepakt
12. Wegschrijven naar `research_items` met een eigen `source_type` — dat veld komt in de CE-02-prompt terecht als `[id] (source_type) titel`

**Als er OAuth bij komt:** kopieer `calendar-oauth-helper.php`. Die deelt al de Google-app met GA4
en detecteert via de sessie welk type het is. Tokens staan per **bronrij**, niet per client.

**Eén ding om te beslissen bij elk nieuw type:** CE-02 krijgt het `source_type` letterlijk in de
prompt te zien. Bij Trello staat er nu een instructie omheen (*"Trello-kaarten zijn interne
PR-concepten — neem minimaal 1 Trello-item op als het past"*). Elk nieuw type dat inhoudelijk
anders van aard is, heeft zo'n regel nodig, anders weegt het model het verkeerd.

---

## 3. Per bron

### 3.1 YouTube-kanaal — bijna gratis

**Volg-een-kanaal werkt via RSS.** Elk publiek YouTube-kanaal heeft nog steeds een feed op
`https://www.youtube.com/feeds/videos.xml?channel_id=UC…`. Geen API-key, geen quota, geen OAuth.

Dat betekent dat je het **niet als nieuw brontype hoeft te bouwen**. Twee opties:

- *Nu meteen:* documenteer het als een URL/RSS-bron. Werkt vandaag al, nul regels code.
- *Netter:* een tab **YouTube** die alleen een kanaal-URL vraagt, daaruit het `channel_id` haalt en
  intern een `url_rss`-bron aanmaakt. Puur een invoergemak — de klant kent zijn channel-ID niet,
  maar plakt wel de kanaalpagina.

De feed geeft titel, beschrijving, link en publicatiedatum. **Geen transcripties.** Wil je de
gesproken inhoud, dan is dat de YouTube Data API + captions, en dat is een aparte discussie.

*Eigen kanaal met kijkcijfers* is iets anders: dat is de YouTube Analytics API met OAuth, en hoort
conceptueel bij Analytics/Report, niet bij Sources.

### 3.2 TikTok-account — alleen via Apify

**De officiële weg is dicht.** De Display API geeft alleen toegang tot het *eigen*
geauthenticeerde account, niet tot willekeurige publieke accounts. De Research API is voorbehouden
aan academische instellingen en non-profits. Er is geen gratis commerciële toegang tot publieke
TikTok-data.

**De weg die wel werkt is er al:** Apify. CF heeft de scraper-infrastructuur staan
(`scrape-repository.php`, platformtoken, `scrape_runs`, budgetbewaking per bron). Een TikTok-actor
is hetzelfde patroon als de bestaande LinkedIn-scraper — andere actor-ID, zelfde flow.

Werk: klein tot middel, want de plumbing bestaat. Aandachtspunten: kosten per run tellen mee in de
tenant-kostenberekening, en scrapen van een platform dat het niet wil is fragiel — actors breken
regelmatig. Zet de foutafhandeling erop dat een gebroken actor niet de hele sync laat vallen.

### 3.3 Google Drive — kan, mits je de scope-val vermijdt

**Dit is de belangrijkste technische bevinding van deze scan.**

De voor de hand liggende scope `drive.readonly` is door Google geclassificeerd als **restricted**.
Dat betekent verplichte app-verificatie plus een jaarlijkse externe security assessment — een
serieuze kostenpost en doorlooptijd voor een tool van deze omvang.

**Gebruik in plaats daarvan `drive.file`.** Die scope is niet-gevoelig, vereist géén verificatie, en
geeft toegang tot precies de bestanden die de gebruiker zelf aanwijst via de Google Picker. Voor ons
gebruik is dat functioneel prima: de klant kiest een map of een set documenten die als bron mag
dienen. Sterker nog, het is beter — de klant ziet expliciet wat hij deelt.

Verder is dit de goedkoopste OAuth-uitbreiding die we kunnen doen, want **de Google-app bestaat al**
(zelfde Cloud-project als GA4 en Calendar, gedeelde redirect-URI met sessie-detectie). Het wordt een
derde tak in `analytics-oauth-callback`.

Te ondersteunen bestandstypen: Google Docs (exporteren als platte tekst), PDF's in Drive (zie §3.4
voor de extractie), Sheets alleen als er een duidelijke use case is.

Openstaand punt dat hierbij hoort: het OAuth consent screen staat nog niet op productie (#23b in
BUILD-NOTES). Dat moet sowieso geregeld worden vóór een derde Google-scope erbij komt.

### 3.4 PDF-upload — de eenvoudigste, en meteen nuttig

Geen API, geen OAuth, geen doorlopende kosten, geen afhankelijkheid van een derde partij die morgen
zijn voorwaarden wijzigt. Klant uploadt een jaarverslag, whitepaper of brancherapport en CE-02 kan
eruit putten.

**Eén conceptueel verschil om bewust af te handelen:** het bronmodel gaat uit van periodiek
synchroniseren — `last_checked`, de uurlijkse sync, de knop **Check**. Een PDF verandert niet. Maak
er een categorie `document` van waarbij sync een no-op is en de extractie één keer bij upload
gebeurt. Toon in plaats van "Check" de uploaddatum en het aantal geëxtraheerde items.

Extractie: `pdftotext` (poppler) via shell is het robuustst; `smalot/pdfparser` als je het in PHP
wilt houden. **Gescande PDF's zonder tekstlaag geven niets terug** — vang dat af met een duidelijke
melding in plaats van een lege bron. OCR is een aparte beslissing.

Opslag volgt het patroon van de gallery (`client_images`): bestand op schijf per tenant, rij in de
database. Let op tenant-isolatie in het pad.

Splitsing: een rapport van 80 pagina's is één bron maar hoort niet als één `research_item`. Knip op
kop of op alinea-blokken van een paar duizend tekens, zodat CE-02 er losse stukken uit kan kiezen.

### 3.5 Slack — technisch te doen, maar begin met de vraag of je het wilt

**Technisch:** Slack API met OAuth, scopes `channels:read`, `channels:history`, eventueel
`groups:history` voor privékanalen. Voor meerdere tenants moet je een publieke Slack-app maken en
die door Slack laten reviewen, of elke klant zijn eigen app laten aanmaken — dat laatste is voor
onze doelgroep (§supportplan: kent geen API-keys) geen begaanbare weg.

**Waarom ik hem als laatste zet, en niet om technische redenen:** Slack bevat gesprekken tussen
collega's die er geen toestemming voor hebben gegeven dat hun berichten een contentgenerator voeden.
Trello-kaarten zijn opgestelde concepten; Slack-berichten zijn informeel intern verkeer. Dat is een
AVG-vraag en een vertrouwensvraag bij de klant, geen technische.

Wil je het toch: **nooit een hele workspace, altijd expliciet per kanaal**, met een zichtbare lijst
van welke kanalen worden meegelezen en sinds wanneer. En de klant moet zijn team kunnen laten zien
wat er gedeeld wordt.

Signaal-ruisverhouding is bovendien laag. Ik zou hier de minste waarde per bouwuur verwachten van de
vijf.

### 3.6 Asana — kloon van Trello

Structureel bijna identiek aan de bestaande Trello-bron: authenticatie met een Personal Access
Token, keuze van workspace en project via een pulldown (zoals `sources-trello.php` de borden
ophaalt), taken als interne concepten naar `research_items`.

Een PAT vraagt de klant wel zelf aan te maken — dat is precies het soort veld waar het supportplan
over gaat, dus geef het een uitlegballetje met klikroute en de knop **Help me verder**.

Nieuwe bestanden: `sources-asana.php` (projectlijst), velden in `SourceForm.tsx`,
`asana_pat_ref` / `asana_workspace_id` / `asana_project_id` op `sources`. OAuth in plaats van PAT
kan later; begin met PAT, net als Trello.

---

## 4. Volgorde die ik zou aanhouden

1. **PDF-upload** — grootste waarde per uur, geen externe afhankelijkheid, en het brengt
   documentextractie in huis die Google Drive later hergebruikt.
2. **YouTube** — vandaag al mogelijk als RSS-bron; de tab is puur invoergemak.
3. **Asana** — kloon Trello, weinig risico.
4. **Google Drive** — bouwt voort op de PDF-extractie uit stap 1 en de Google-OAuth die er al is.
   Regel eerst het consent screen (#23b).
5. **TikTok** — als er klantvraag is; kosten per run in de gaten houden.
6. **Slack** — pas na een expliciet besluit over de privacykant.

---

## 5. Wat dit raakt buiten Sources

- **CE-02-prompt**: elk nieuw `source_type` heeft een regel nodig over hoe het gewogen moet worden,
  net als Trello die nu heeft.
- **Onboarding**: `briefing/onboarding-master.md` noemt de brontypen; die lijst loopt achter zodra
  hier iets bijkomt.
- **Support-systeemprompt**: bevat een opsomming van bronnen en waar je de keys vindt. Die moet mee
  — hij bevat nu al twee verouderde verwijzingen (zie `cursor-fieldhint-support.md` §6).
- **Kosten per tenant** (`tenantCosts.ts`): alleen TikTok/Apify voegt variabele kosten toe.
- **Sources-onboarding-guard**: er is een check op "0 bronnen"; controleer dat nieuwe typen
  meetellen.

---

Sources:
- [Requesting Minimum Scopes — Google Cloud Console Help](https://support.google.com/cloud/answer/13807380?hl=en)
- [Google Drive API Terms of Service](https://developers.google.com/drive/api/terms)
- [YouTube RSS Feed: Find Any Channel Without a Tool (2026)](https://www.wprssaggregator.com/youtube-rss-feed/)
- [TikTok Scraping APIs in 2026 — ScrapeBadger](https://scrapebadger.com/blog/tiktok-scraping-apis-in-2026-the-complete-deep-guide)
- [Is TikTok API Free? What Developers Use Instead — SociaVault](https://sociavault.com/blog/tiktok-api-free-2026)
