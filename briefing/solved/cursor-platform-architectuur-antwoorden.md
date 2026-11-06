# Antwoorden op vragen bij `cursor-platform-architectuur.md`

Aanvulling op het architectuurbrief. Waar een antwoord uit de code volgt, staat de
vindplaats erbij. Waar het een keuze van Erik is, staat **[Erik beslist]** met een
aanbeveling erbij zodat je niet hoeft te wachten om te beginnen.

---

## Omgevingen & deploy

**1. Staging-hosting — [Erik beslist]**

Aanbeveling: **apart cPanel-account of minstens een aparte FTP-gebruiker en database**,
niet alleen een subdomein binnen hetzelfde account. Als staging op hetzelfde account
draait met dezelfde FTP-credentials, is de isolatie fictief: één gecompromitteerde
FTP-login geeft toegang tot beide, en een verkeerd `FTP_REMOTE_PATH` overschrijft
productie. De hele `.env.staging`-opzet leunt erop dat `FTP_*` en `DB_*` echt
verschillen. Als dat budgettair niet kan: minimaal een aparte DB + aparte FTP-user
met een remote path dat productie niet kán raken.

**2. Productie-deploy vanaf tag**

Fase 1: **bevestigingsprompt + weigeren bij uncommitted changes** is genoeg. Een harde
tag-eis is nu vooral wrijving bij een team van één à twee mensen, en verleidt tot
`--force`-achtige omwegen. Bouw het wel zo dat de tag-check later een vlag kan worden
(`--require-tag`), en log de huidige commit-hash mee in de release-notitie zodat je
achteraf altijd weet wat er draait.

**3. Lokale DB**

**Leeg schema + seed**, niet een dump. Het schema komt uit `docs/schema.sql` (is nu een
echte productie-export) en de seed is dezelfde geanonimiseerde demo-tenant als staging —
zo is er één seed-script voor twee doelen.

Productiedata trekken: **alleen geanonimiseerd, en alleen via een expliciet script**,
nooit ad-hoc. Praktisch heb je dat zelden nodig; als een bug alleen op productiedata
optreedt, is een geanonimiseerde export van díe ene tenant genoeg.

---

## WordPress / database

**4. Timing WP-scheiding — [Erik beslist, maar de fasering in het brief was te laks]**

Je hebt gelijk dat dit wringt: het brief noemt het het grootste risico en zet het in
fase 3. Reden was dat het losstaat van de rest. Maar de juiste volgorde is:
**vóór de eerste betalende klant**, of in elk geval vóór er klantdata in staat die je
niet kunt reproduceren.

Verzachtende omstandigheid: het gaat om één WordPress-installatie die je zelf beheert
(zie 5), niet om een publieke multisite. Het risico is dus "WP-plugin-kwetsbaarheid →
toegang tot de CE-database", niet "klant kan bij andere klant". Als je WordPress
strak bijwerkt, is dit weken uitstelbaar — maar niet maanden. Zet het in **fase 1b**:
direct na staging, vóór het dashboard.

**5. `wpce_*`-tabellen — beantwoord uit de code**

Het is **alleen de CE-blog op `igisnext.com`**, niet de sites van klanten. Bewijs:
`web/blog/wp-config.php` heeft `DB_NAME = 'igisnext_ce'` (dezelfde database als de app)
met `$table_prefix = 'wpce_'`. Klant-WordPress-sites worden benaderd via de REST API
met per-tenant credentials uit `clients.wordpress_*` — die raken deze database nooit.

Migratie is dus overzichtelijk: nieuwe database aanmaken, `wpce_*`-tabellen
verplaatsen, `wp-config.php` aanpassen, eigen DB-gebruiker. **De CE-code hoeft niet
gewijzigd te worden** — die praat met WordPress uitsluitend over REST.

---

## Observability

**6. PII in `event_log.context` — alleen IDs**

Regel: **IDs, statuscodes, endpoint-namen en foutmeldingen van systemen. Geen
e-mailadressen, geen namen, geen artikelinhoud, geen headlines.** Een `client_id` en
`slot_id` zijn genoeg om in de app door te klikken naar de rest.

Uitzondering die je niet moet vergeten: foutmeldingen van externe API's bevatten soms
zelf een e-mailadres of URL met token. Voeg daarom een kleine sanitizer toe die
e-mailpatronen en `Bearer`/token-achtige strings uit `message` filtert vóór opslag.

Retentie zoals in het brief: `info` 30 dagen, `warn`/`error` 12 maanden.

**7. Alarmering in fase 1**

Ja, **wel meteen mail bij `severity=error`** — maar met dedupe en cooldown (zelfde
`event` + `client_id` maximaal 1× per uur), anders is het na één kapotte webhook
onbruikbaar. Zonder mail moet je het dashboard uit gewoonte openen, en precies dat gaat
in een drukke week mis.

Houd het simpel: één functie in `event-log-repository.php` die bij `error` de bestaande
mail-helper aanroept naar `CE_SUPPORT_EMAIL`. Dit is dezelfde anomaly-mail die #35
noemt, alleen dan in zijn eenvoudigste vorm.

**8. n8n-fouten**

Fase 1: **API-side is genoeg**, want de meeste n8n-problemen worden aan de CE-kant al
zichtbaar (webhook geeft geen 2xx, of het verwachte record verschijnt niet). De
bestaande `ce_trigger_ce01_theme_director()` vangt HTTP-status en 404 al netjes af —
daar hangt de logregel aan.

Wat je daarmee mist: een workflow die *start* maar halverwege faalt (Claude-timeout).
Fase 2-oplossing, twee opties — de eerste heeft mijn voorkeur:

- **Poll de n8n API** op recente failed executions (je hebt `N8N_API_URL` + `N8N_API_KEY` al in `.env`) en schrijf die naar `event_log`. Geen wijziging aan tien workflows nodig.
- Callback-node in elke workflow bij failure. Betrouwbaarder qua timing, maar tien workflows aanpassen en opnieuw pushen.

---

## Dashboard & toegang

**9. Platform-admin: aparte tabel, niet een vlag op `clients`**

Je eigen argument is het juiste. `clients.is_platform_admin` betekent dat de beheerder
óók een tenant is, met een `ce_client_id` in de sessie — precies de situatie waarin je
per ongeluk in klantdata werkt. Bovendien vervuilt het de tenant-lijst en elke
`COUNT(*) FROM clients`.

Dus: **`platform_users`** (id, email, password_hash, name, role, active, created_at) met
een eigen sessievlag (`ce_platform_user_id`), volledig los van `ce_client_id`. Het
brief had hier de verkeerde default; neem deze.

**10. Wie mag `/platform` — [Erik beslist]**

Aanbeveling: **Erik volledig, Eva lezen + diagnose**. Daarom `platform_users.role` met
twee waarden: `admin` (alles) en `support` (lezen + niet-destructieve diagnose zoals
wp-diagnose en mailbox-sync). Destructief — delete client, reset content, theme-refill
met `force` — alleen `admin`, plus altijd een bevestigingsdialoog.

**11. Overlap met #35 — één bron: dit brief**

Goede vraag, en de overlap is groot. Voorstel:

- `cursor-platform-architectuur.md` wordt de **canonieke spec**.
- Neem uit #35 over wat daar concreter is en hier ontbreekt: de **pipeline-status-blok** (stuck slots, draft zonder CE-03, pending zonder mail, lege gallery bij automatic), de **anomalie-signalentabel**, en het punt **"geen secrets in UI, wel geconfigureerd/ontbreekt/verlopen"**. Die zijn goed doordacht — niet weggooien.
- Vervang de sectie *Beheerder / Ops-dashboard* in `BUILD-NOTES.md` door een verwijzing naar dit brief, met behoud van die drie stukken. Backlogpunt #35 blijft bestaan als tracker, met de spec-verwijzing.
- Naamgeving gelijktrekken: #35 noemt `ops_events`, dit brief `event_log`. **Kies `event_log`** (breder toepasbaar, ook niet-ops-events).

---

## Servicevragen & privacy

**12. Support-chat-berichten — [Erik beslist, aanbeveling: volledige vraag, 12 maanden]**

De volledige vraag opslaan is functioneel veel waardevoller dan een categorie: je wilt
de formulering zien om te snappen waar iemand vastloopt. Voorwaarden:

- **Alleen de vraag van de gebruiker**, niet het antwoord van de chatbot (dat kun je reproduceren en het is de helft van het volume).
- Retentie **12 maanden**, daarna verwijderen.
- Noem het expliciet in je verwerkersovereenkomst en privacyverklaring; dit is de meest gevoelige opslag uit dit hele brief, want klanten typen daar soms bedrijfsdetails in.
- Overweeg een `redacted`-vlag zodat je een vraag handmatig kunt wissen als er iets gevoeligs in staat.

**13. `service_tickets` — intern beginnen**

**Alleen jij vult ze**, vanuit mail en telefoon. Een intern notitieveld dus, geen
klant-UI. Reden: op het moment dat klanten zelf tickets aanmaken, ontstaat de
verwachting van statusupdates en reactietijden — dan bouw je een helpdesk. De vraag
"waar lopen klanten tegenaan" beantwoord je al met een intern lijstje plus de
support-chat-vragen.

---

## Scope & volgorde

**14. Prioriteit — [Erik beslist, aanbeveling: fase 1 eerst]**

Fase 1 (staging + `event_log` + backup + guards) vóór #36 Joomla en #37 Yoast. Redenen:

- Je hebt nu meerdere tenants; het mass-delete-incident uit `BUILD-NOTES.md` gebeurde bij één. Zonder backup en guards is de volgende keer duurder.
- Joomla en Yoast zijn features voor nog-niet-bestaande klanten; staging en backups beschermen bestaande.
- Fase 1 is klein: vooral scripts en één tabel, geen UI.

Uitzondering: als een concrete deal afhangt van Joomla, gaat die deal voor —
maar doe dan minimaal de dagelijkse backup (punt 5 van fase 1) eerst, dat is een uur werk.

**15. Smoke-test `wp-diagnose` — conditioneel**

Beide eigenlijk: draai de check **alleen voor tenants met `wordpress_site_url` gevuld**,
en laat de smoke-test standaard tegen een vaste test-tenant (iGiSNext-sandbox) draaien.
Een tenant zonder WP levert `skipped`, geen `failed` — anders leer je de test negeren,
en dan is hij waardeloos.

**16. Migratie-tooling — vanaf nul, maar klein**

`run-migration.sh` bestaat nog niet; `scripts/migrations/` bevat alleen `.sql`-bestanden.
Wat er wél al is: `schema_migrations` (tabel bestaat, zie `docs/schema.sql` regel 389) en
`POST /api/admin/schema-migrations-init` dat de twaalf retroactieve bestanden registreert.

Uitvoering: **via PHP/PDO op afstand**, in de geest van de bestaande admin-endpoints —
`POST /api/admin/run-migration` met `X-CE-Reset-Token`, body met de bestandsnaam, dat
het bestand van de server leest, in een transactie uitvoert en in `schema_migrations`
registreert. Reden: `DB_HOST=localhost` betekent dat de database niet van buitenaf
bereikbaar is, dus lokaal `mysql <` werkt niet. `run-migration.sh` wordt dan een dunne
wrapper om die call, met omgevingsargument.

Let op: DDL in MySQL is niet transactioneel — een half mislukte `ALTER` rolt niet terug.
Houd migratiebestanden daarom klein en idempotent (`IF NOT EXISTS`), zoals de huidige.

---

## Bewust niet in scope — bevestiging

**17. Geen Sentry/Datadog — bevestigd, met nuance**

Klopt, maar de zorg is terecht en wordt weggenomen door antwoord 7: met foutmail bij
`severity=error` hoef je het dashboard niet uit gewoonte te openen. Het dashboard is dan
voor context en trends, niet voor detectie.

Herzie dit zodra: je meer dan ~10 tenants hebt, of wanneer je merkt dat je zelf een
uptime-check mist (een dienst als Sentry ziet dat de site plat ligt; je eigen
`event_log` niet — die ligt dan ook plat). Een externe uptime-ping op `/api/health` is
overigens een prima tussenstap en kost vrijwel niets.

**18. Backup-retentie en -locatie — [Erik beslist]**

Aanbeveling: **dagelijks 14 dagen + wekelijks 8 weken**, en de bestemming moet
**buiten het cPanel-account** liggen. cPanel's eigen backup op dezelfde server helpt
niet bij accountverlies of ransomware.

Praktisch, gegeven je setup: dump op de server, dan via `rclone` of `curl` naar de
Dropbox waar dit project al staat, of naar een aparte object-storage-bucket. Belangrijk:
**test één keer een restore** — een backup die je nooit hebt teruggezet is een aanname,
geen zekerheid.

---

## Samengevat: wijzigingen t.o.v. het oorspronkelijke brief

1. **`platform_users`-tabel** in plaats van `clients.is_platform_admin` (vraag 9), met rollen `admin`/`support`.
2. **WP-scheiding naar fase 1b**, direct na staging (vraag 4).
3. **Foutmail bij `severity=error`** al in fase 1, met dedupe en cooldown (vraag 7).
4. **Migraties via een admin-endpoint**, niet via directe DB-toegang (vraag 16) — `DB_HOST=localhost` laat niets anders toe.
5. **Dit brief wordt canoniek** boven `BUILD-NOTES.md` § Ops-dashboard; pipeline-status-blok en anomaliesignalen uit #35 overnemen; tabelnaam `event_log` (vraag 11).
6. **PII-sanitizer** op `event_log.message` toevoegen (vraag 6).
