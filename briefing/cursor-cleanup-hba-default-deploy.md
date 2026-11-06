# Cursor-briefing — branch `cleanup/hba-default` nakijken, committen en deployen

**Status:** klaar om uit te voeren. **Eigenaar:** Erik. **Achtergrond:** `briefing/rapport-codebeoordeling-hba-inpassing-2026-09-29.md`, §2.

## Wat er op de branch staat

Branch `cleanup/hba-default`, vertrokken van `fd504db` (gelijk aan staging). Nog **niet gecommit**. 123 bestanden: 34 verwijderd, 89 aangepast. De patch staat ook in `backups/hba-default-cleanup.patch`.

Inhoud, besloten door Erik:

1. **HBA-laag altijd aan.** `ce_client_hba_enabled()` en `ce_client_hba_enabled_by_id()` zijn weg, net als `hbaEnabled` in API/types en `TenantContext`. Alleen de HBA-aan-routes zijn over. De kolom `clients.hba_enabled` blijft in de database, maar wordt niet meer gelezen.
2. **Automatisch publiceren weg.** Met HBA aan kon deze modus toch nooit draaien. De optie is uit Mijn account; een opgeslagen `automatic` wordt gelezen als `manual_with_review` (`publish-settings-repository.php`).
3. **Campagnes volledig weg** (code). Tabel `campaigns` en kolom `content_slots.campaign_id` blijven staan.
4. **Titelposter volledig weg** (code). De poster-kolommen blijven staan.
5. **Het `hide`-veld in `SidebarNav` is weg.**

**Er is bewust geen database-migratie.** Maak er ook geen, en drop niets.

## Harde regels

- **Nooit iets naar productie** (FTP, n8n-push, migratie) zonder expliciete "ja" van Erik in de chat, per stap.
- **Geef altijd de omgeving mee.** `push-ce0x-n8n.sh` en `regenerate-all-ce-workflows.sh` vallen zonder argument terug op **productie**. Schrijf dus altijd `staging` of `production` uit, ook bij regenereren.
- **Geen `git stash pop`.** `stash@{0}` (HeroBrand, gedragsregels) is gemaakt op de oude basis en botst met deze branch. Laat hem staan.
- **Niet mergen naar de hoofdbranch** voordat productie is nagelopen.
- Kom je iets tegen dat niet klopt met deze briefing, stop en meld het, in plaats van het zelf anders op te lossen.

## Stap 1 — Nakijken (alleen lezen, niets wijzigen)

```bash
git status -sb                  # moet: cleanup/hba-default, alleen wijzigingen van de patch
git diff --stat | tail -1       # 123 files changed, 333 insertions(+), 7128 deletions(-)
grep -rnE "hba_enabled|hbaEnabled|ce_client_hba_enabled" web/api frontend/src scripts   # leeg
grep -rliE "campaign|poster" web/api frontend/src   # alleen web/api/admin-schema-migrations-init.php (bewust)
```

Draai de checks:

```bash
for f in $(git diff --name-only --diff-filter=AM | grep '\.php$'); do php -l "$f"; done
for f in $(git diff --name-only --diff-filter=AM | grep '\.py$'); do python3 -m py_compile "$f"; done
cd frontend && npx tsc -p tsconfig.app.json --noEmit && npm run build && cd ..
```

Lees daarna deze vier diffs bewust door, want hier verandert gedrag:

- `web/api/publish-repository.php` en `web/api/articles-submit.php`: automatisch publiceren is weg; review is altijd verplicht.
- `scripts/generate-ce01-workflow.py`: een webhook-run loopt niet meer door naar CE-02/CE-03; alleen de geplande run doet dat nog.
- `scripts/generate-ce03-workflow.py`: de redacteursvragen, de posterregel/het woordpaar en de auto-publish-knopen zijn weg.
- `frontend/src/components/ArticleMetaSidebar.tsx` en de archiefpagina's: die tonen nu altijd de HBA-zijbalk, en een alleen-lezen zijbalk toont ook de waarden.

Geef Erik een kort verslag: checks groen/rood, en of je in de diff iets zag dat niet klopt met deze lijst. **Wacht op zijn akkoord** voor stap 2.

## Stap 2 — Committen

Na akkoord: één commit op `cleanup/hba-default`, met de bestaande commit-stijl uit `git log`. Voorstel voor de boodschap:

```
Make the HBA layer the default and remove hidden features.

Removes the per-tenant HBA switch, automatic publishing (unreachable with HBA on),
campaigns and the title-poster generator. DB columns and tables are kept.
```

**Vraag Erik of de branch naar GitHub gepusht moet worden.** Niet zelf pushen.

## Stap 3 — Staging

In deze volgorde:

1. **Frontend en API deployen:** `./scripts/deploy-ftp.sh staging`.
2. **Verwijderde bestanden van de staging-server halen.** FTP-deploy ruimt ze niet op. Het gaat om precies deze tien, in `api/` onder `FTP_REMOTE_PATH` (zie `deploy_ftp_upload.py`):
   `campaigns.php`, `campaigns-item.php`, `campaign-plan.php`, `campaign-plan-repository.php`, `campaign-repository.php`, `articles-poster.php`, `poster-repository.php`, `automatic-pipeline-repository.php`, `admin-automatic-pipeline.php`, `articles-auto-publish.php`.
   Laat Erik eerst de lijst zien. Gebruik de FTP-gegevens uit `.env.staging` en verwijder niets anders.
3. **n8n-workflows regenereren en pushen naar staging:**
   ```bash
   ./scripts/push-ce01-n8n.sh staging
   ./scripts/push-ce03-n8n.sh staging
   ./scripts/push-ce07-n8n.sh staging
   ```
4. **CE-CampaignPlanner deactiveren** in de staging-n8n (webhook `ce-campaign-plan`). Haal `N8N_CE_CAMPAIGN_PLAN_WEBHOOK_URL` uit `.env.staging`.
5. **Smoke test:** `./scripts/smoke-test.sh staging`.

**Handmatige controle op staging.** Laat Erik dit meekijken of doe het met een staging-testtenant:

- Mijn account toont geen HBA-schakelaar en geen "Automatisch inclusief plaatsen".
- Merkarchitectuur staat in het menu; Campagnes niet.
- Home laadt. Rapport laadt. Agenda laadt zonder campagneknop.
- Een artikel openen: HBA-zijbalk, geen postersectie. Opslaan werkt. Indienen stuurt de review-mail.
- Snel artikel aanmaken werkt.
- Archief: een gepubliceerd artikel openen toont de zijbalk met waarden.
- Een CE-03-run (nieuw artikel) loopt door zonder fout in n8n; het artikel heeft geen redacteursvragen-blok.
- Platform → tenant-detail toont geen "Merklaag"-schakelaar.
- `/api/campaigns` en `/api/articles/<id>/poster` geven 404.

Meld Erik het resultaat per regel. **Wacht op zijn akkoord** voor productie.

## Stap 4 — Productie (alleen na expliciet akkoord)

Dezelfde stappen als staging, met `production`:

1. `./scripts/deploy-ftp.sh production` (vraagt zelf om bevestiging).
2. Dezelfde tien bestanden van de productie-server verwijderen, na akkoord op de lijst.
3. `push-ce01/03/07-n8n.sh production`.
4. CE-CampaignPlanner deactiveren in de productie-n8n; de env-variabele uit `.env.production` halen.
5. `./scripts/smoke-test.sh production`, plus een korte handmatige check (inloggen, Home, één artikel openen).

Daarna mag de branch gemerged worden. Vraag Erik naar welke branch.

## Klaar als

- De commit staat op `cleanup/hba-default`, en is gepusht als Erik dat wilde.
- Staging en productie draaien de nieuwe bundle, zonder de tien oude PHP-bestanden.
- CE-01, CE-03 en CE-07 zijn op beide omgevingen opnieuw gepusht; CE-CampaignPlanner staat uit.
- De smoke test is groen op beide omgevingen.
- `stash@{0}` staat er nog.

## Buiten scope

De beveiligingspunten uit het rapport (§3), de Overzicht/Rapport-herindeling en de kalibratiemodule. Die komen als eigen briefings.
