# Database-migraties

De productiedatabase is leidend; **`docs/schema.sql`** is een `SHOW CREATE TABLE`-export van productie (geen handgeschreven inferentie).

Productie draait **MariaDB**, niet MySQL — te zien aan `DEFAULT current_timestamp()` en
display widths als `int(10) unsigned` in de schema-export. Dat is relevant omdat
`ADD COLUMN IF NOT EXISTS` een MariaDB-uitbreiding is die MySQL 8 níet kent. Test lokaal
dus tegen MariaDB (zie "Lokaal testen" onderaan), niet tegen `mysql:8`.

## Workflow (vanaf aug 2026)

1. Voeg een nieuw `.sql`-bestand toe in `scripts/migrations/` (beschrijvende naam).
2. Deploy de code, zodat het bestand op de server staat: `./scripts/deploy-ftp.sh <omgeving>`.
3. Draai de migratie: `./scripts/run-migration.sh <omgeving> <bestand>` (of `--all`).
4. **Direct daarna:** `./scripts/export-production-schema.sh` → commit **`docs/schema.sql`** in dezelfde commit als het migratiebestand.
5. Nieuwe kolommen/tabellen ook in PHP auto-schema alleen als vangnet — **niet** als enige migratiebron.

Eerst op staging, dan op productie.

**Volgorde bij een release:** *additieve* migraties (nieuwe tabel, nullable kolom) draai je
**vóór** de code-deploy, zodat de oude code blijft werken. *Destructieve* migraties (kolom
verwijderen, hernoemen) pas **ná** de deploy, want de oude code verwacht die kolom nog.

**DDL is niet transactioneel** — een half mislukte `ALTER` rolt niet terug. Houd
migratiebestanden daarom klein en idempotent (`IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`).
Dat geldt per *statement*: een bestand met `CREATE TABLE IF NOT EXISTS` maar een kale
`ADD COLUMN` erna is nog steeds niet idempotent.

## run-migration.sh

```bash
./scripts/run-migration.sh                              # status: toegepast + openstaand
./scripts/run-migration.sh staging add_event_log.sql
./scripts/run-migration.sh production --all
```

Draait via `GET/POST /api/admin/run-migration` (header `X-CE-Reset-Token`), die het bestand
**van de server** leest en na succes in `schema_migrations` registreert. Reden voor die
omweg: `DB_HOST=localhost` betekent dat de database niet van buitenaf bereikbaar is, dus
een lokale `mysql < bestand.sql` werkt niet.

## Retroactieve migraties (jul 2026)

De 12 bestaande bestanden zijn retroactief in `schema_migrations` gezet via `POST /api/admin/schema-migrations-init`.

> **Aug 2026 — idempotentie gerepareerd.** Vijf van deze bestanden gebruikten een kale
> `ADD COLUMN` en braken op `1060 Duplicate column name` zodra ze op een bestaand schema
> liepen: `add_article_self_review`, `add_publish_mode`, `add_report_article_counts`,
> `add_source_checks_per_week` en `add_objectives_table`. Op productie was dat onzichtbaar
> omdat ze daar al geregistreerd stonden, maar `--all` op een verse database (zoals bij
> `sync-staging-from-production.sh`) liep erop vast. Alle vijf gebruiken nu
> `ADD COLUMN IF NOT EXISTS`; geverifieerd door alle 13 migraties op een verse MariaDB
> 10.11 met `docs/schema.sql` te draaien — 13× toegepast, en een tweede `--all` is een no-op.

Verificatie:

| Bestand | Verificatie |
|---------|-------------|
| `add_article_feedback_table.sql` | auto-heal `feedback-repository.php` |
| `add_article_self_review.sql` | auto-heal `article-repository.php` |
| `add_campaign_end_date.sql` | auto-heal `campaign-repository.php` |
| `add_campaign_start_date.sql` | kolom in basis-CREATE `campaigns` + campaign-repository |
| `add_campaigns_table.sql` | **geen auto-heal** — geverifieerd via schema-export (`campaigns`-tabel) + Campaign planner live |
| `add_client_images_table.sql` | auto-heal `client-images-repository.php` |
| `add_linkedin_posts_table.sql` | auto-heal `linkedin-engagement-repository.php` |
| `add_objectives_table.sql` | auto-heal `objectives-repository.php` |
| `add_publish_mode.sql` | auto-heal `account-repository.php` |
| `add_report_article_counts.sql` | auto-heal `report-repository.php` |
| `add_scrape_runs_table.sql` | auto-heal `scrape-repository.php` |
| `add_source_checks_per_week.sql` | auto-heal `account-repository.php` |

## schema_migrations

```sql
CREATE TABLE schema_migrations (
  filename VARCHAR(255) PRIMARY KEY,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

`GET /api/health` geeft de laatst toegepaste migratie terug (`lastMigration`), en
`./scripts/smoke-test.sh` faalt als er migraties openstaan.

## Lokaal testen tegen MariaDB (Docker)

Een migratie op productie draaien zonder hem eerst op het echte schema te proberen is
onnodig risico. Zet een wegwerpdatabase op met het productieschema:

```bash
docker run -d --name ce_test_db \
  -e MARIADB_ROOT_PASSWORD=cetest -e MARIADB_DATABASE=ce_test \
  -e MARIADB_USER=ce_test -e MARIADB_PASSWORD=cetest \
  -p 3310:3306 mariadb:10.11

docker exec -i ce_test_db mariadb -uce_test -pcetest ce_test < docs/schema.sql
```

Wijs de API daarna naar poort 3310 via `web/includes/db.local.php` (of een `.env.local` met
`DB_PORT=3310`) en draai `./scripts/run-migration.sh local --all`. Verwacht: elke migratie
`applied`, en een tweede `--all` levert een lege `results`. Opruimen met
`docker rm -f ce_test_db`.

Let op: `docs/schema.sql` bevat ook de `wpce_*`-tabellen, omdat WordPress nu dezelfde
database deelt. `admin-db-dump.php` slaat die bewust over.
