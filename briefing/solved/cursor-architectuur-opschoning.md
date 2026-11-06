# Architectuur-opschoning: schema-drift, CORS, n8n-scriptnaamgeving

Bouwlogica-review (30 jul 2026) bracht drie structurele knelpunten aan het
licht — geen features, maar onderhoudsrisico's. Dit brief beschrijft ze los
van elkaar zodat ze onafhankelijk opgepakt kunnen worden.

---

## 1. `docs/schema.sql` is stale — geen canoniek datamodel

### Het probleem

`docs/schema.sql` noemt zichzelf "inferred schema" en bevat 5 tabellen
(`clients`, `content_slots`, `articles`, `article_feedback`,
`article_review_edits`). De bouwnotities (`docs/BUILD-NOTES.md`) verwijzen
naar tientallen tabellen die er niet in staan: `themes`, `objectives`,
`scrape_runs`, `campaigns`, `newsletter_items`, `clients.tone_styles`,
`todos`, `performance_reports`, `linkedin_posts`, `client_images`, `sources`,
`ce_sessions`, `password_reset_tokens`, `llm_probleemvragen`,
`llm_visibility_checks`, e.a.

Er is geen migratie-runner en geen migratiehistorie-tabel. Schema-evolutie
loopt via losse bestanden in `scripts/migrations/` (12 stuks) die kennelijk
handmatig op productie gedraaid worden, plus runtime-defensie in de API
(`ce_table_has_column()` in `web/api/schema-helper.php`) die ontbrekende
kolommen stilzwijgend opvangt.

**Risico:** de enige plek waar het echte datamodel bestaat is de
productie-database zelf. Een nieuwe omgeving opzetten, onboarden van een
andere developer, of simpelweg controleren "welke kolommen heeft
`articles` nu echt" kan alleen door in te loggen op productie-MySQL.

### Aanpak

1. **Canoniek schema genereren uit productie**, niet handmatig bijhouden:
   ```bash
   mysqldump --no-data --routines --triggers \
     -h $DB_HOST -u $DB_USER -p $DB_NAME > docs/schema.sql
   ```
   Dit vervangt het "inferred" bestand door een exacte `CREATE TABLE`-dump.
   Voeg een korte header toe: datum + `mysqldump`-commando zodat duidelijk
   is dat het een export is, geen handgeschreven bron.

2. **Regenereer bij elke migratie.** Voeg aan de bestaande workflow toe: na
   het draaien van een nieuw bestand in `scripts/migrations/`, direct
   `docs/schema.sql` opnieuw exporteren en meecommitten in dezelfde commit
   als de migratie. Één regel in `scripts/migrations/README` (aanmaken als
   die nog niet bestaat) volstaat om dit als afspraak vast te leggen.

3. **Migratiehistorie bijhouden**, ook minimaal:
   ```sql
   CREATE TABLE IF NOT EXISTS schema_migrations (
     filename VARCHAR(255) PRIMARY KEY,
     applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```
   Los script `scripts/run-migration.sh <file.sql>` dat controleert of de
   filename al in `schema_migrations` staat, zo niet: uitvoeren + inserten.
   Dit voorkomt dubbel toepassen en geeft in één query antwoord op "welke
   migraties zijn hier al geweest" — nu is dat alleen af te leiden uit of
   een kolom toevallig bestaat.

4. **Niet in scope:** de runtime-defensie (`ce_table_has_column()`) hoeft
   niet weg. Die blijft nuttig als vangnet tussen omgevingen die niet
   perfect gelijk lopen (bijv. staging vs productie tijdens een rollout).
   Het gaat er alleen om dat er ergens een betrouwbare *referentie* is.

### Checklist

- [ ] `docs/schema.sql` vervangen door `mysqldump --no-data`-export
- [ ] Header met datum + commando toegevoegd
- [ ] `schema_migrations`-tabel aangemaakt op productie
- [ ] `scripts/run-migration.sh` (of vergelijkbaar) dat historie bijhoudt
- [ ] Afspraak vastgelegd: migratie + schema-export in dezelfde commit
- [ ] Bestaande 12 bestanden in `scripts/migrations/` retroactief in
      `schema_migrations` gezet (zodat de historie vanaf nu klopt)

---

## 2. CORS staat op wildcard voor een sessie-geauthenticeerde API

### Het probleem

`web/api/bootstrap.php` (`ce_json_response()`) stuurt op elke API-response:

```php
header('Access-Control-Allow-Origin: *');
```

De API werkt met PHP-sessies (`ce_establish_auth_session()`) en beheert
gevoelige tenant-data: LinkedIn OAuth-tokens, WordPress-credentials,
mailbox-wachtwoorden. Browsers weigeren wel `credentials: include`-requests
te beantwoorden bij een wildcard-origin, dus het is vermoedelijk niet direct
misbruikbaar — maar het is de enige plek in de codebase waar dit zo breed
staat (vergelijk `web/blog/wp-includes/*.php`, die reflecteren de
aanvragende origin in plaats van wildcard te gebruiken), en het is een
onnodig ruime instelling voor een multi-tenant systeem.

### Aanpak

1. **Vervang wildcard door een allowlist** gebaseerd op de frontend-origin(s):
   ```php
   function ce_allowed_origins(): array
   {
       $raw = getenv('CE_ALLOWED_ORIGINS') ?: 'https://ce.igisnext.com';
       return array_filter(array_map('trim', explode(',', $raw)));
   }

   function ce_cors_origin(): ?string
   {
       $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
       return in_array($origin, ce_allowed_origins(), true) ? $origin : null;
   }
   ```
   In `ce_json_response()`:
   ```php
   $origin = ce_cors_origin();
   if ($origin !== null) {
       header("Access-Control-Allow-Origin: $origin");
       header('Access-Control-Allow-Credentials: true');
       header('Vary: Origin');
   }
   ```

2. **Nieuwe env-var** `CE_ALLOWED_ORIGINS` in `.env.example` documenteren,
   default naar productie-frontend + `http://localhost:5173` (Vite dev
   server) voor lokale ontwikkeling.

3. **Test na wijziging:** frontend (`frontend/`) en publieke reviewpagina
   (`ReviewPage.tsx`, draait mogelijk op een ander pad/domein via
   `review_token`-links) moeten beide blijven werken. Check ook
   `web/article.php` (legacy deploy-shell) of die los draait.

### Checklist

- [ ] `ce_allowed_origins()` / `ce_cors_origin()` toegevoegd aan `bootstrap.php`
- [ ] Wildcard vervangen in `ce_json_response()`
- [ ] `CE_ALLOWED_ORIGINS` in `.env` + `.env.example`
- [ ] Getest: frontend (prod + lokaal), publieke reviewpagina, `admin/*`-endpoints met `X-CE-Reset-Token`

---

## 3. n8n generate/push-scripts lopen niet symmetrisch

### Het probleem

Elke CE-fase heeft in principe een `generate-ce<N>-workflow.py` (bouwt de
workflow-JSON) en een `push-ce<N>-n8n.sh` (deployt naar n8n). Dat patroon
klopt niet overal:

| Generate-script | Push-script | Status |
|---|---|---|
| `generate-ce-llm-visibility-workflow.py` | *(ontbreekt)* | asymmetrisch |
| *(geen eigen generate-script)* | `push-ce041bis-n8n.sh` | asymmetrisch |

Er is wel een generieke `scripts/push-n8n-workflow.py` die los van de
losse `push-ce*.sh`-wrappers gebruikt kan worden, dus functioneel is er
altijd een deploy-pad — maar het ontbreken van een dedicated
`push-ce-llm-visibility-n8n.sh` betekent dat iemand die het patroon volgt
("elke CE-fase heeft een `push-ce*.sh`") deze workflow makkelijk mist bij
een volledige herdeploy. Losstaand: de nummering springt van CE-04 naar
CE-07 (geen CE-05/CE-06) zonder toelichting waarom in `BUILD-NOTES.md`.

### Aanpak

1. **Ontbrekend wrapper-script toevoegen** voor symmetrie:
   `scripts/push-ce-llm-visibility-n8n.sh`, zelfde vorm als de bestaande
   `push-ce07-n8n.sh` (env parsen in Python, workflow-ID/naam consistent
   met de andere CE-flows).

2. **`scripts/regenerate-all-ce-workflows.sh` en `push-all-ce-n8n.sh`
   controleren** — staat `ce-llm-visibility` daar al in de lijst? Zo niet,
   toevoegen, anders wordt hij bij een "regenerate + push all" stilzwijgend
   overgeslagen.

3. **Eén regel in `BUILD-NOTES.md`** onder "Handige paden" of "Technische
   beslissingen" die het CE-05/CE-06-gat verklaart (gereserveerd voor
   toekomstige fases? nooit gebruikt geweest?) zodat het niet als
   documentatiefout oogt bij de volgende persoon die het leest.

4. **Optioneel, niet urgent:** overweeg een klein test-script
   (`scripts/check-n8n-script-symmetry.sh` of gewoon een `find`-oneliner in
   CI) dat faalt als een `generate-ce*.py` geen bijbehorend `push-ce*.sh`
   heeft, om nieuwe asymmetrie in de toekomst te voorkomen. Alleen bouwen
   als dit team dit soort drift vaker tegenkomt — anders overkill voor het
   huidige aantal workflows.

### Checklist

- [ ] `scripts/push-ce-llm-visibility-n8n.sh` toegevoegd
- [ ] `regenerate-all-ce-workflows.sh` / `push-all-ce-n8n.sh` bevatten llm-visibility
- [ ] CE-05/CE-06-gat toegelicht in `BUILD-NOTES.md`
- [ ] (optioneel) symmetrie-check-script

---

## Niet in dit brief: dubbele statuswerelden

De DB-statussen (`pending_review`, `feedback_received`) vs UI-labels
(`pending`, `feedback`) zijn een **bewuste** ontwerpkeuze, gecentraliseerd
in `web/api/mappers.php`, en al gestabiliseerd volgens de "Open
risico's"-tabel in `BUILD-NOTES.md`. Geen actie nodig — alleen vermeld in
de oorspronkelijke review als iets om in de gaten te houden bij nieuwe
statuswaarden.
