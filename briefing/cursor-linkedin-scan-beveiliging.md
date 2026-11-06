# Cursor-briefing — LinkedIn Scan: blootstelling en beveiliging dichten

**Project:** `linkedin-analyse/` → https://linkedin-analyse.igisnext.com (alleen productie, er is geen staging)
**Eigenaar:** Erik. **Datum:** 1 oktober 2026.
**Achtergrond:** codebeoordeling 1 okt, cijfer 4/8. De applicatiecode is netjes: SQL via prepared statements, output ge-escaped, een degelijke reset-flow. De deploy en een paar ontbrekende beveiligingen zijn het probleem.
**Model:** Composer 2.5. De fixes zijn klein en precies omschreven. Laat na ronde A en na ronde B de diff nalezen door een ander model (Opus 5 of GPT-5.5), met de vraag: "kan iemand zonder login hier nog data zien of kosten maken?"

## Harde regels

- **Elke stap op productie** (deploy, bestanden op de server verwijderen) alleen na een expliciete "ja" van Erik in de chat.
- **Ronde A eerst en apart deployen.** Dat is de directe blootstelling. Ronde B en C mogen later.
- **Geen database-migraties** behalve wat hieronder staat. Niets droppen.
- Klopt iets niet met deze briefing, meld het, in plaats van zelf een andere oplossing te kiezen.

---

## Ronde A — vandaag: blootstelling dicht

### A0. Eerst vaststellen wat er openstaat (alleen lezen)

Erik opent in zijn browser (niet ingelogd, of in een privévenster):

- `https://linkedin-analyse.igisnext.com/output/growthelements-raw.json`
- `https://linkedin-analyse.igisnext.com/output/`
- `https://linkedin-analyse.igisnext.com/fixtures/growthelements-sample.json`
- `https://linkedin-analyse.igisnext.com/BUILD.md`
- `https://linkedin-analyse.igisnext.com/sql/schema.sql`
- `https://linkedin-analyse.igisnext.com/scripts/test-analyzer.php`

**Roep `scripts/test-narrative.php` niet aan**, want die doet een betaalde Claude-call. Noteer per URL: opent / 403 / 404. Bewaar die lijst; hij komt in de afronding terug.

### A1. Niet meer meedeployen

`scripts/deploy-linkedin-analyse.sh` spiegelt nu de hele map (`deploy_ftp_upload.py` → `lftp mirror --reverse`, met als enige uitsluiting `.DS_Store` en `blog/*`).

- Geef de LinkedIn-deploy een eigen uitsluitingslijst: `output/`, `fixtures/`, `scripts/`, `sql/`, `*.md`, `.gitignore`, `.htaccess.example`, `includes/config.local.php.example`.
- **Pas `deploy_ftp_upload.py` niet zo aan dat de CE-deploy verandert.** Voeg een parameter toe (bijvoorbeeld `--exclude-file` of een extra lijst) die alleen het LinkedIn-script meegeeft. De CE-deploy (`deploy-ftp.sh`) moet byte-voor-byte hetzelfde blijven werken.

### A2. Een echte `.htaccess`

Voeg `linkedin-analyse/.htaccess` toe, die wél wordt gedeployd:

- `Options -Indexes` (geen mappenlijsten).
- Deny-all voor `output/`, `fixtures/`, `scripts/`, `sql/` en `includes/`, en voor `*.md`, `*.sql`, `*.json`, `*.example`, `.git*`.
- Laat de pagina's die wel publiek moeten werken bereikbaar: `index.php`, `scan.php`, `report.php`, `forgot-password.php`, `reset-password.php` en `assets/`.
- Controleer of de server Apache is en `.htaccess` respecteert. Werkt dat niet, meld het, en voeg als tweede laag in elk script en in `includes/*.php` een check toe die bij een directe aanroep stopt.

### A3. Scripts alleen via de commandline

Zet bovenaan elk bestand in `scripts/` en in `cleanup.php` (vóór `require`):

```php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
```

Laat de bestaande web-tak (`li_require_auth()`) in `cleanup.php` vervallen; cleanup draait alleen nog via cron of CLI. Het weggaan van `fwrite(STDERR, …)` in een web-context is hiermee ook opgelost.

### A4. Geen Claude-call vanuit de openbare rapportlink

`report.php:21` roept `li_scan_metrics_from_row($pdo, $row, false)` aan. Bij een scan zonder geslaagd verhaal start dat bij **elke** keer openen een Claude-call, die niet wordt opgeslagen en niet meetelt in de daglimiet.

- Voeg een parameter toe (bijvoorbeeld `$allowNarrative = false`), of een aparte functie, zodat `report.php` **nooit** een LLM-call doet. Ontbreekt het verhaal, dan toont het rapport alleen de cijfermatige versie.
- Het verhaal opnieuw laten maken kan alleen vanuit `scan.php`, ingelogd, en dan wordt het ook opgeslagen en meegeteld.

### A5. Inloggen faalt dicht

In `includes/bootstrap.php` geven `li_is_logged_in()` (~regel 80) en `li_attempt_login()` (~regel 101) `true` terug als `li_app_users()` leeg is. Dat moet `false` worden, met een duidelijke foutmelding op de loginpagina: "Inloggen tijdelijk niet mogelijk." Er is geen open modus meer.

### A6. Deployen en opruimen (na Eriks ja)

1. `./scripts/deploy-linkedin-analyse.sh production`
2. Een gewijzigde deploy verwijdert niets wat al op de server staat. Verwijder via FTP **alleen** deze mappen en bestanden in de webroot van het subdomein: `output/`, `fixtures/`, `scripts/`, `sql/`, `BUILD.md`, `.gitignore`, `.htaccess.example`, `includes/config.local.php.example`. **Laat Erik de lijst eerst zien.** `includes/config.local.php` blijft staan, want dat is de configuratie.
3. Loop de URL-lijst uit A0 opnieuw na. Alles moet 403 of 404 geven. Inloggen en een scan openen moeten nog werken, en een rapportlink ook.

---

## Ronde B — deze week: sessie en formulieren

### B1. Sessiecookie

In `includes/bootstrap.php`, vóór `session_start()`:

```php
session_set_cookie_params(['secure' => true, 'httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
```

Na een geslaagde login: `session_regenerate_id(true)`.

### B2. CSRF

- Eén token per sessie in `$_SESSION`, als hidden field in elk formulier: login, scan starten (`index.php:147`), verwijderen (`index.php:232`), context bijwerken (`scan.php:42`), opnieuw scannen, en wachtwoord vergeten en resetten.
- Controleer het token bij elke POST, met `hash_equals`.
- **Opnieuw scannen** (een betaalde Apify-run) gaat nu ook via GET (`scan.php:13`, `?refresh=1`). Maak dat alleen-POST met token, en verwijder de GET-route.

### B3. Inlogpogingen beperken

- Een tabel `login_attempts` (ip, email, attempted_at), aan te maken via de bestaande `migrate.php`-lijst.
- Na 5 mislukte pogingen per e-mail of per IP binnen 15 minuten: 15 minuten blokkeren, met de melding "Te veel pogingen, probeer het later opnieuw." Geef niet prijs of het e-mailadres bestaat.
- Een geslaagde login wist de pogingen voor dat e-mailadres.

### B4. Kleine dingen

- **Wachtwoord trimmen:** login trimt het wachtwoord (`index.php:11`), reset niet. Trim nergens; een wachtwoord is wat de gebruiker typt.
- **Apify-token:** gaat nu mee in de URL (`apify-client.php` ~193, 279, 307). Gebruik een header `Authorization: Bearer …`.

---

## Ronde C — kosten en wat de prospect leest

### C1. De daglimiet klopt weer

- **Nu:** de limiet telt alleen bestaande rijen, dus verwijderen zet het budget terug.
  **Doen:** een tabel `cost_log` (scan_id, soort apify/claude, usd, created_at), waarin elke betaalde call direct een regel krijgt. De daglimiet telt uit `cost_log`.
- **Nu:** bij een time-out van Apify (`apify-client.php` ~531–550) loopt de run door en kost door.
  **Doen:** roep het abort-endpoint van Apify aan, en geef bij het starten `timeout` en `maxTotalChargeUsd` mee.
- **Nu:** Haiku-prijzen in `narrative-analyzer.php:6-7` staan op 0,8/4.
  **Doen:** controleer de actuele prijzen per miljoen tokens bij Anthropic en zet ze goed. Noteer de bron in een commentaarregel.

### C2. De interne notitie niet citeren

`report-renderer.php` ~757 en ~763 citeren de bedrijfsomschrijving die Erik of Eva invult ("heeft een sterke positionering rond “…”"). Dat is een interne notitie.

- Gebruik die omschrijving alleen als input voor het model, nooit letterlijk in de tekst voor de prospect.
- Herschrijf de zinnen zonder citaat, bijvoorbeeld: "…komt in een deel van de posts duidelijk terug, in andere niet."

### C3. Feitelijke fouten in het rapport

- **"Kregen geen enkele reactie"** (`analyzer.php` ~650) telt alleen opmerkingen. Schrijf "kregen geen opmerkingen", of tel likes mee.
- **Eén post geeft "7 posts per week"** (~404). Toon de frequentie pas vanaf 3 posts of een periode van minimaal 2 weken.
- **Een slapende pagina lijkt gezond.** Voeg "laatste post: N dagen geleden" toe, met een observatie bij meer dan 30 dagen.
- **Te sterke claims eruit:** "zeldzamer dan de meeste bedrijven denken" (~780), "geen mens en geen model kan…" (~833), en "klopt het verhaal consistent" (~772) zonder controle van het consistentielabel.
- **Eén aanspreekvorm per rapport:** nu wisselt het tussen "ze/hun" en "jullie" (~780 tegenover ~824). Kies er één.
- **Anglicisme:** "Posts zonder comments" → "Posts zonder opmerkingen".

### C4. Bewaartermijn eerlijk maken

- `metrics.top_posts`, `best_post` en het verhaal bevatten fragmenten van posts en blijven nu onbeperkt bewaard. Rapportlinks verlopen nooit.
  - Na 180 dagen: vervang de fragmenten door "[verwijderd]" en laat de rapportlink 404 geven.
  - Voeg dat toe aan `li_scan_purge_old_raw_data`, zodat het ook via de bestaande aanroep in `scan.php` meeloopt.
- Pas `BUILD.md` aan: zet in plain tekst wat er bewaard wordt en hoe lang, in plaats van "AVG ✅".

---

## Afronden

- `php -l` op alle gewijzigde bestanden; draai `scripts/test-analyzer.php` via de CLI.
- Eén commit per ronde.
- Een kort verslag aan Erik: de URL-lijst uit A0 vóór en na, en per punt klaar / gewijzigd / waar je een keuze moest maken.

---

## Update 1 oktober — controle na ronde A–C (cijfer 5/8)

Ronde A en B zijn goed uitgevoerd. In C zitten drie fixes die niet doen wat ze beloven. Hieronder de vervolgstappen, **in deze volgorde**.
**Model:** Composer 2.5. Alleen D7 (teksten) liever met Opus 5.

### Stap 0 — Eerst deployen en opruimen (A6 was nog niet gedaan)

Ronde A en B verkleinen alleen de risico's, dus deploy die nu, na Eriks "ja":

1. `./scripts/deploy-linkedin-analyse.sh production`
2. Verwijder via FTP **alleen**: `output/`, `fixtures/`, `scripts/`, `sql/`, `BUILD.md`, `.gitignore`, `.htaccess.example`, `includes/config.local.php.example`. Laat Erik de lijst eerst zien. `includes/config.local.php` blijft staan.
3. Draai daarna `migrate.php` (met key), zodat `cost_log` en `login_attempts` bestaan.
4. Controleer direct of de site laadt. `Options -Indexes` in `.htaccess` geeft op sommige hosts een 500-fout. Haal dan alleen die regel weg; de rest van de `.htaccess` blijft.
5. Loop de URL-lijst uit A0 opnieuw na: alles 403 of 404. Inloggen, een scan openen en een rapportlink moeten nog werken.

### D1. Apify-kostenplafonds werken pas als ze in de URL staan

`li_apify_run_start_payload()` (`apify-client.php` ~260) zet `timeoutSecs` en `maxTotalChargeUsd` in de invoer van de actor. Apify leest die daar niet; ze horen als queryparameters op de run-aanroep.

- Zet `timeout=` en `maxTotalChargeUsd=` in `li_apify_start_run_url()` (~353), naast `waitForFinish`. Controleer de exacte parameternamen in de Apify API-documentatie (Run actor) en noteer de bron in een commentaarregel.
- Haal ze uit de actor-invoer, zodat de scraper geen onbekende velden krijgt.
- Stel het plafond in op **het budget dat vandaag nog over is**, niet op de hele daglimiet.

### D2. Interne regel uit de outreach-mail

Verwijder uit de platte tekstversie (`report-renderer.php` ~1053): "(In de opgemaakte mail staan intro, scores, cijfers en observaties. Vanaf «Posts op reacties» staat alles in het online rapport.)". Zoek ook in de HTML-versie en de teaser naar meer interne aanwijzingen voor Erik/Eva ("Vanaf Posts op reacties", "ranking", "via onderstaande link" terwijl er geen link is) en haal die weg.

**Klaar als:** geen enkele zin in de mail is bedoeld voor Erik of Eva in plaats van voor de prospect.

### D3. Verlopen rapportlinks blijven verlopen

- `scan.php` ~178 roept `li_scan_ensure_report_token()` aan en maakt zo een nieuwe link voor een scan waarvan de link na 180 dagen is gewist. Maak voor scans ouder dan de bewaartermijn geen nieuwe token aan; toon in de tool "Rapportlink verlopen".
- Laat `li_scan_find_by_report_token()` ook zelf op `created_at` controleren, zodat een link na de termijn altijd 404 geeft, ook als het opruimen nog niet heeft gedraaid.

### D4. Alle betaalde calls komen in `cost_log`

- **Schoolscans (het pad met wachten op de achtergrond):** log bij de start een Apify-regel met de geschatte kosten, en corrigeer die bij de afronding. Een lopende schoolscan telt dan meteen mee in de daglimiet. Een afgebroken run wordt gelogd en via het abort-endpoint gestopt.
- **Claude:** log de kosten **vóór** het verwerken van het antwoord (`narrative-analyzer.php` ~367–377). Een antwoord dat niet te verwerken is, is ook betaald.
- **`cost_log` in de runtime-DDL**, net als `login_attempts`. Faalt het schrijven toch, dan stopt de scan met een duidelijke fout in plaats van stil terug te vallen op de oude telling (`scan-repository.php` ~112, ~160).

### D5. Cijfers die de prospect ziet

- **"Laatste post: N dagen geleden"** rekent nu vanaf vandaag, waardoor oude scans rood kleuren (`report-renderer.php` ~325, ~925). Reken vanaf de scandatum (`created_at` van de scan) en zeg het zo: "op het moment van de scan was de laatste post N dagen oud."
- **Posts per week:** drie posts op één dag geven nog "21 per week" (`analyzer.php` ~415: de voorwaarde gebruikt OR). Toon een frequentie pas bij minimaal 3 posts **én** een periode van minimaal 14 dagen; anders "te weinig posts voor een ritme".

### D6. Opruimen en privacy

- **Scans met nul posts** krijgen nooit "[verwijderd]" en worden daardoor bij elke opruimronde opnieuw opgepakt (`LIMIT 200`, ~973). Voeg een kolom `metrics_redacted_at` toe en sla scans over die al gedaan zijn.
- **Opruimen bij elk paginabezoek:** laat het opruimen niet meer bij elke `scan.php`-load draaien, maar hooguit één keer per dag. Sla de tijd van de laatste run op, of zet het in cron, als Erik dat kan instellen.
- **`login_attempts`:** ruim mislukte pogingen ouder dan 30 dagen op in dezelfde opruimronde.
- **`BUILD.md`:** vervang "AVG ✅" door een korte, eerlijke beschrijving van wat er bewaard wordt en hoe lang: ruwe data 90 dagen, fragmenten en rapportlink 180 dagen, inlogpogingen 30 dagen.

### D7. Teksten voor de prospect (Opus 5)

- **Eén aanspreekvorm.** Het verhaal staat nu in de derde persoon, maar de kop luidt "Wat jullie over jezelf vertellen" en een case-zin zegt "waar jullie voor staan". Kies de derde persoon (de bedrijfsnaam / "ze") en pas koppen en vaste zinnen daarop aan.
- **De interne omschrijving niet verraden.**
  - Weg met het label "Posts vs. bedrijfsomschrijving" (~1222). Schrijf bijvoorbeeld "Wat de posts over het bedrijf vertellen".
  - De strip-helper (~1157) vervangt de omschrijving door "de eigen positionering", ook aan het begin van een zin. Zorg voor een hoofdletter aan het begin van een zin, of herschrijf de zin zonder vervanging.
- **Taal:**
  - "Die scherpte is winst" en "Die consistentie is winst" → "Dat is een sterk punt."
  - "De laatste post is N dagen geleden" → "dateert van N dagen geleden".
  - "stil ligt" → "stilligt".
- **Overclaim:** "houdt jullie merk in beeld bij … AI-systemen" → voorzichtiger, of weg.
- **Tegenspraak:** "5 van de 5 posts … grotendeels" spreekt zichzelf tegen; bij 100% "alle posts".

### D8. Wacht op Erik — geen bouwwerk

Cursor heeft in de vorige ronde de outreach-mail op eigen initiatief herbouwd, met de volledige tabel en de observaties. Dat stond niet in de briefing. **Laat het voorlopig zoals het is**, en zet in je verslag naast elkaar wat de mail vóór en ná die wijziging bevatte. Erik beslist of het blijft. Voortaan geldt: afwijkingen melden, niet zelf kiezen.

### Afronden

- `php -l`; draai `scripts/test-analyzer.php` via de CLI.
- Eén commit per D-punt.
- **Deploy van D1–D7 pas na Eriks "ja".**
- Een kort verslag aan Erik: per punt klaar / gewijzigd / waar je een keuze moest maken, plus het voor-en-na van D8.
