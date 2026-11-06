# Checklist — HBA-integratie, CF-flow en dataplaatsing

**Doel:** in één ronde vaststellen of (a) de CF-flow werkt, (b) de HBA-laag er correct overheen
ligt, en (c) data op de juiste plek belandt.
**Duur:** ongeveer een halve dag voor de volledige ronde; blok A–C is het minimum vóór een release.
**Waar:** staging, met twee testtenants. Nooit voor het eerst op productie.

Vul de uitkomst per regel in met ✅ / ❌ / n.v.t. Bij ❌: noteer de tenant, het slot-id en het
tijdstip — dan is het terug te vinden in `event_log`.

---

## 0. Voorbereiding

- [ ] **Twee testtenants** op staging: tenant **A** met `hba_enabled = 1`, tenant **B** met
      `hba_enabled = 0`. Alles hieronder loop je in beide, tenzij anders vermeld.
- [ ] Tenant A heeft een complete `brand_narratives`-rij; tenant B expliciet géén.
- [ ] Beide hebben minimaal één bron, één gallery-beeld en een werkende website-koppeling
      (`GET /api/admin/cms-diagnose`).
- [ ] Noteer vooraf `SELECT id, name, hba_enabled FROM clients WHERE …` — je hebt de UUID's
      constant nodig.
- [ ] Snapshot van de database vóór je begint, zodat je kunt vergelijken én terug kunt.

**Handige endpoints tijdens het testen** (allemaal achter `X-CE-Reset-Token`):
`/admin/list-slots`, `/admin/debug-article`, `/admin/wp-diagnose`, `/admin/cms-diagnose`,
`/admin/events`, `/admin/schema-export`, `/admin/export-tenant`.

---

## A. Fundament — datamodel en isolatie

- [ ] `GET /admin/schema-export` bevat alle verwachte tabellen: `clients`, `sources`, `themes`,
      `content_slots`, `research_items`, `articles`, `objectives`, `brand_narratives`,
      `hba_scores`, `hba_theme_verdicts`, `hba_article_hints`, `branche_bibliotheek`,
      `linkedin_posts`, `performance_reports`, `event_log`.
- [ ] Elke inhoudelijke tabel heeft een `client_id` en die is gevuld. Losse check op de tabel die
      dit ooit miste:
      `SELECT COUNT(*) FROM newsletter_items WHERE client_id IS NULL OR client_id = '';` → **0**
- [ ] **Kruislekkage.** Voor elke tabel: geen rij van tenant A zichtbaar in een query met het
      client_id van B. Steekproef via `/admin/export-tenant` voor A en controleer dat er geen
      B-UUID's in de bundel zitten.
- [ ] `hba_pbs_revealed_at` bestaat op `clients` en is `NULL` bij een verse tenant.
- [ ] Migraties: `/admin/run-migration` draaien is idempotent — twee keer draaien geeft geen fout.

---

## B. Ronde 1 — de CF-flow zonder HBA (tenant B)

Dit is de basis. Werkt dit niet, dan zegt de HBA-test niets.

### B1 — Identity

- [ ] Nav toont **BrandElements** en **Objectives**, en géén BrandArchitecture.
- [ ] BrandElements toont BrandValues, Schrijfwijzer, Schrijfstijl per tone en Auteurs.
- [ ] Nergens een Story Triangle-veld, branche of contextprofiel.
- [ ] Objectives: zet 40 / 30 / 20 / 10. Opslaan, pagina verversen, waarden staan er nog.
      `SELECT label, percentage FROM objectives WHERE client_id = '<B>' ORDER BY sort_order;`
- [ ] Velden lijnen uit met Integrations — zelfde x-positie, zelfde breedte.

### B2 — Sources → Theme

- [ ] **Check** op elke bron geeft items terug; foutmeldingen zijn leesbaar, niet technisch.
      `SELECT source_type, COUNT(*) FROM research_items WHERE client_id='<B>' GROUP BY source_type;`
- [ ] CE-01 draaien → `themes` met status `proposed` en een gevulde `rationale`.
- [ ] Elk thema heeft een `objective_id` als er objectives zijn (`themes.objective_id`).
- [ ] `cycle_week` klopt met de week waarvoor het thema bedoeld is.

### B3 — Research → Article

- [ ] Like/Forget op Research schrijft weg; `content_slots.objective_id` volgt het thema
      (`COALESCE(content_slots.objective_id, themes.objective_id)`).
- [ ] CE-03 → `articles` met status `draft`, gevulde `rationale`, body, `tone_final`,
      `angle_final`, `objective_suggested`.
- [ ] Article-metakolom toont Rationale, Tone, Angle, Auteur en Objective — en die zijn te wijzigen.
- [ ] Beeldvoorstel verschijnt onder het artikel.

### B4 — Review → Publiceren → Report

- [ ] Submit → reviewer krijgt mail → Approve → **Publiceer**.
- [ ] Post staat live op de site; `articles.status = 'published'` en het WP/Joomla-post-ID is gevuld.
- [ ] Bij LinkedIn aan: `linkedin_posts`-rij met share-URN.
- [ ] Agenda toont het slot in de juiste week met de juiste statuskleur.
- [ ] Archive toont de gepubliceerde post; Report toont de GA4-cijfers zodra die binnen zijn.
- [ ] **Verwijderen** van een slot met rapportkoppeling geeft geen FK-fout (`SQLSTATE[23000]`).

---

## C. Ronde 2 — HBA aan (tenant A)

### C1 — Identity met HBA

- [ ] Nav toont **BrandArchitecture**, **BrandElements** en **Objectives** — drie tabs.
- [ ] BrandArchitecture bij lege architectuur: de acht stappen starten vanzelf.
- [ ] Stap 6 toont een **voorgesteld** archetype met motivatie en twee alternatieven. Nooit een leeg
      archetype-veld.
- [ ] Stap 7 levert exact drie goede en drie zwakke voorbeelden op.
      `SELECT JSON_LENGTH(goede_voorbeelden), JSON_LENGTH(zwakke_voorbeelden) FROM brand_narratives WHERE client_id='<A>' AND is_current=1;`
      → **3 en 3**. Anders weigert de scoring met `narrative_incomplete`.
- [ ] Bevestigingsscherm toont de architectuur in mensentaal + de knop naar een HBA-strateeg.
- [ ] BrandElements: BrandValues is **weg**, Schrijfwijzer, tone-styles en auteurs staan er nog en
      zijn bewerkbaar.
- [ ] Objectives: nog steeds sliders, nog steeds `/targets`.
- [ ] `contextprofiel` is gevuld en heeft een van de zes toegestane waarden.

### C2 — Vlag omzetten raakt geen data

Dit is de belangrijkste test van de hele lijst.

- [ ] Zet bij tenant B `hba_enabled = 1`. Objectives blijft **40 / 30 / 20 / 10**, ook na een save
      vanuit een ander scherm.
- [ ] `brandstory` staat nog in de database, ook al is het veld verborgen.
- [ ] Zet weer terug naar 0. BrandValues komt terug met de oude inhoud; `brand_narratives` blijft
      bestaan.
- [ ] Draai daarna nogmaals: geen enkele waarde in `clients`, `objectives` of `brand_narratives` is
      veranderd door het heen-en-weer zetten.

---

## D. De drie momenten

### D1 — Moment 1: adviseur vooraf (`/theme`)

- [ ] Na CE-01 verschijnt per thema een label `versterkt` / `neutraal` / `verwatert` met één zin.
- [ ] Boven de lijst staat één agendaregel (`watOntbreekt`).
- [ ] `SELECT theme_id, label FROM hba_theme_verdicts WHERE …;` — één rij per voorgesteld thema.
- [ ] Bij tenant B (HBA uit): géén labels, géén agendaregel, en `runThemeVerdicts` wordt niet
      aangeroepen (controleer in de netwerktab).
- [ ] Onderaan staat dat de klant zelf kiest.

### D2 — Moment 2: co-auteur tijdens (`/article`, `/fastlane`)

- [ ] Bij een gegenereerd concept verschijnt één aanwijzing, met de kop *Positionering scherper
      maken*.
- [ ] De **voorgestelde tekst is zichtbaar vóór** je op Pas toe klikt (`passageNa` gerenderd).
- [ ] **Pas toe** vervangt exact `passageVoor` door `passageNa` in de editor en zet
      `hba_article_hints.toegepast = 1`.
- [ ] **Negeer** laat de tekst ongemoeid en de rij blijft met `toegepast = 0`.
- [ ] Bij een overtreden merkregel staat die melding bovenaan, vóór de aanwijzing.
- [ ] Tone, Angle, Objective en Rationale bestaan nog en werken nog — achter `Details`.
- [ ] Bij tenant B: geen aanwijzing, geen extra LLM-call.

### D3 — Moment 3: bewaker achteraf (Overview + Report)

- [ ] Onder de 10 gescoorde posts: ring met `X van 20`, drie tellers, **geen cijfer**.
- [ ] Teller "gekozen thema's die versterkten" telt alleen thema's die de klant ook echt koos —
      niet alle voorgestelde.
- [ ] Tussen 10 en 19: PBS met label `Indicatief`, afgerond op vijftallen.
- [ ] Vanaf 20: cijfer met duidingzin, pijl en trendlijn die de opbouwweken meeneemt.
- [ ] De onthullingsmelding verschijnt **precies één keer**; daarna is
      `clients.hba_pbs_revealed_at` gevuld en blijft de melding weg.
- [ ] Erosion alert (indien getriggerd) heeft de accentbalk en is te onderscheiden van een gewoon
      informatieblok.
- [ ] Report toont de uitgebreide variant; Overview alleen de compacte.

---

## E. Waar landt de data — eindcontrole

Loop dit na op tenant A, ná een volledige ronde.

| Wat | Tabel · kolom | Controle |
| --- | --- | --- |
| Merkarchitectuur | `brand_narratives` (`is_current = 1`) | één actuele rij, alle verplichte velden gevuld |
| Branche | `sources`? **nee** → `brand_narratives.branche_code` | verwijst naar `branche_bibliotheek` |
| Themaoordeel | `hba_theme_verdicts` | `theme_id` bestaat in `themes`, `label` in de enum |
| Artikelaanwijzing | `hba_article_hints` | `article_id` bestaat, `toegepast` klopt met wat je deed |
| Postscore | `hba_scores` | verwijst naar de **narrative-versie** die actief was bij scoring |
| Objectives | `objectives` | percentages ongewijzigd t.o.v. wat je invulde |
| Objective op keten | `themes` → `content_slots` → `articles.objective_final` | dezelfde id over de hele keten |
| Research | `research_items.source_type` | matcht het brontype dat je aanmaakte |
| Publicatie | `articles.status`, WP-post-id, `linkedin_posts` | drie plekken consistent |
| Rapport | `performance_reports.scope_article_id` | koppelt aan een bestaand artikel |

Verweesde rijen — alle vier moeten **0** teruggeven:

```sql
SELECT COUNT(*) FROM hba_theme_verdicts v LEFT JOIN themes t ON t.id=v.theme_id WHERE t.id IS NULL;
SELECT COUNT(*) FROM hba_article_hints h LEFT JOIN articles a ON a.id=h.article_id WHERE a.id IS NULL;
SELECT COUNT(*) FROM hba_scores s LEFT JOIN articles a ON a.id=s.post_id WHERE a.id IS NULL;
SELECT COUNT(*) FROM content_slots s LEFT JOIN clients c ON c.id=s.client_id WHERE c.id IS NULL;
```

---

## F. Regressies op bekende problemen

Dit zijn dingen die al een keer stuk zijn geweest. Kijk er expliciet naar.

- [ ] **Objectives-percentages** worden niet gelijk verdeeld bij een save (zie C2). Grep:
      `IdentityObjectivesSection` komt nergens meer voor.
- [ ] **Schrijfwijzer, tone-styles en auteurs** zijn bereikbaar met HBA aan — geen redirect weg van
      BrandElements.
- [ ] **Kolombreedte**: alle Identity-schermen gebruiken `mainLayout="grid2"` en
      `GRID2_REVIEW_MAIN_WIDTH`, niet `max-w-[680px]`.
- [ ] **Geen handgerolde controls**: grep op `<select` en `border-2 border-ce-` in `pages/` en
      `components/` — hoort alleen via `Field` / `Pulldown` te lopen.
- [ ] **Geen vaktaal in de klant-UI.** Grep op: `archetype`, `adversary`, `Foe`, `verboden logica`,
      `bewijsvelden`, `contextprofiel`, `tone_of_behaviour`, `write_directive`, `CE-0`,
      `StyleGuide`, `contentrol`.
- [ ] **Geen onverwerkte Markdown** (`###`, `**`) in tekst die de klant ziet.
- [ ] **GA4-koppeling**: status groen betekent token geldig **én** Property ID gevuld.
- [ ] **Support-chat** verwijst naar bestaande schermen: WordPress staat op Integrations (niet
      Account), BrandElements bestaat, StyleGuide niet meer.

---

## G. Twee dingen die vermoedelijk niet kloppen — controleer als eerste

**G1 — Telt de ring concepten mee?**
`scripts/generate-ce03-workflow.py` roept `/api/hba/score` aan **tijdens CE-03**, dus op het moment
dat het artikel geschreven wordt — niet bij publiceren. Dat betekent dat een concept dat nooit de
deur uitgaat wél een `hba_scores`-rij krijgt en dus meetelt in de ring en de PBS.

Test: maak een artikel, laat CE-03 draaien, publiceer het **niet**, en controleer
`GET /api/hba/progress`. Gaat de teller omhoog, dan meet je content die niemand heeft gezien.
Zie `cursor-hba-v2-meedenker.md` §9.4.2 — de score-call hoort na
`ce_apply_ui_status(..., 'published')`.

- [ ] Concept zonder publicatie verhoogt de teller **niet**.
- [ ] Republish geeft geen tweede `hba_scores`-rij voor hetzelfde artikel.

**G2 — Verschuift de meetlat bij een gewijzigd contextprofiel?**
De targetmix-bandbreedtes volgen uit `contextprofiel`. Wijzigt de klant dat, dan verandert waar de
PBS tegen meet.

- [ ] Wijzig `contextprofiel` en controleer dat oude `hba_scores` naar de **oude** narrative-versie
      blijven verwijzen, en niet met terugwerkende kracht anders scoren.

---

## H. Multi-tenant en omgevingen

- [ ] Tenant wisselen in dezelfde sessie toont het juiste Account, de juiste koppelingen en de
      juiste HBA-status.
- [ ] `GET /admin/wp-diagnose` toont per `client_id` de eigen `linkedinConnected` en extras.
- [ ] Twee tenants tegelijk een OAuth-flow starten in dezelfde browser verwart de state niet
      (bekende beperking — vaststellen of het nog speelt).
- [ ] Staging schrijft niet naar productie-WordPress. Controleer `WORDPRESS_SITE_URL` in
      `ce.local.php` op staging.
- [ ] `event_log` bevat geen tokens of wachtwoorden — de redactie-regex uit
      `event-log-repository.php` doet zijn werk.

---

## I. Afsluiten

- [ ] Alle ❌ hebben een issue met tenant, slot-id en tijdstip.
- [ ] Databasesnapshot ná de ronde vergeleken met die van vóór: alleen de verwachte tabellen zijn
      gegroeid.
- [ ] Testtenants opgeruimd of duidelijk gemarkeerd, zodat ze niet in benchmarks of de
      sectorbenchmark terechtkomen.
