# Content Engine — Objectives (doelstellingen) + Google Reviews

## Overzicht van de wijzigingen

| Laag | Wat verandert |
|------|--------------|
| Datamodel | Nieuwe tabel `objectives`, velden verhuizen van `clients` |
| Strategy-scherm | Objectives-sectie + schrijfstijl-pulldown |
| Article-scherm (meta) | Objective als vijfde keuzeveld in de meta-kolom |
| CE-01 (n8n) | Objectives meenemen in thema-prompt |
| CE-03 (n8n) | Objective meenemen in artikel-prompt + Stap C |
| Report-scherm | Objective-verdeling + Google Reviews-sectie |
| Sources | Google Places ID per klant |
| CE-07 (n8n) | Google Reviews ophalen via Places API |

Raadpleeg bij grote wijzigingen ook `briefing/product-vision.md`.

---

## Stap 1 — Datamodel

### Nieuwe tabel: `objectives`

```sql
CREATE TABLE objectives (
  id            CHAR(36)      NOT NULL PRIMARY KEY,
  client_id     CHAR(36)      NOT NULL,
  label         VARCHAR(255)  NOT NULL,   -- bv. "Brand Awareness", "Lead Generation"
  description   TEXT,                     -- wat deze doelstelling inhoudt
  percentage    TINYINT       NOT NULL,   -- 0-100; alle objectives per client tellen op tot 100
  sort_order    TINYINT       NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_objectives_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Aanpassingen `clients`-tabel

```sql
-- Verhuizen naar objectives (worden planning_horizon_weeks en posts_per_week
-- bewaard als globale defaults op clients, maar ook per objective instelbaar):
-- Geen verwijdering nodig — de kolommen op clients blijven als fallback.

-- Nieuwe kolom voor Google Places:
ALTER TABLE clients
  ADD COLUMN google_place_id VARCHAR(255) NULL;
```

### Aanpassingen `articles`-tabel

```sql
ALTER TABLE articles
  ADD COLUMN objective_id CHAR(36) NULL,
  ADD CONSTRAINT fk_articles_objective
    FOREIGN KEY (objective_id) REFERENCES objectives(id);
```

### Aanpassingen `content_slots`-tabel

```sql
ALTER TABLE content_slots
  ADD COLUMN objective_id CHAR(36) NULL,
  ADD CONSTRAINT fk_slots_objective
    FOREIGN KEY (objective_id) REFERENCES objectives(id);
```

### SQL-migraties

Maak aan als losse bestanden:
- `scripts/migrations/add_objectives_table.sql`
- `scripts/migrations/add_google_place_id.sql`
- `scripts/migrations/add_objective_id_to_articles.sql`
- `scripts/migrations/add_objective_id_to_slots.sql`

---

## Stap 2 — Strategy-scherm (`/strategy`)

### 2a. Objectives-sectie (nieuw, bovenaan Strategy)

Voeg een nieuwe sectie toe vóór Archetype/Adversary/Brandstory.

**Koptekst:** Objectives

**UI per objective:**
```
[Label-veld]         [Percentage %]    [Delete]
[Description-textarea]
─────────────────────────────────────────────
Totaal: 85% ← rode indicator als ≠ 100%
       100% ← groene indicator als = 100%
```

- Knop **+ Add objective** voegt een lege rij toe
- Maximaal 6 objectives per klant
- Percentage-validatie: toon een inline waarschuwing als de som ≠ 100%,
  blokkeer opslaan totdat de som exact 100 is
- Volgorde aanpasbaar via drag-and-drop (sort_order)

**API:**
```
GET    /api/objectives          → lijst voor huidige client
POST   /api/objectives          → nieuwe objective aanmaken
PATCH  /api/objectives/:id      → label/description/percentage/sort_order bijwerken
DELETE /api/objectives/:id      → verwijderen (alleen als niet gekoppeld aan slots/articles)
```

### 2b. Planning horizon + Posts per week verhuizen

Verplaats de velden **Planning horizon** en **Posts per week** van het
Account-scherm naar de Objectives-sectie in Strategy.

Toon ze als globale defaults onder de objectives-tabel:
```
Standaard planning horizon:  [4] weken
Standaard posts per week:    [2]
```

Verwijder ze van het Account-scherm. De DB-kolommen op `clients` blijven staan.

### 2c. Schrijfstijl-pulldown (nieuw)

Voeg onder de Schrijfwijzer-textarea een pulldown toe:

**Label:** Schrijfstijl

**Opties** (uitbreidbaar):
- Zakelijk en direct
- Persoonlijk en verhalend
- Theoretisch en analytisch (Erik/JiHaa-stijl)
- Toegankelijk en informatief

Sla op als `clients.writing_style` (nieuw VARCHAR-veld):

```sql
ALTER TABLE clients
  ADD COLUMN writing_style VARCHAR(100) NULL DEFAULT 'zakelijk_direct';
```

De geselecteerde schrijfstijl wordt meegegeven aan CE-03 als extra
prompt-parameter naast de Schrijfwijzer-tekst.

---

## Stap 3 — Article-scherm: Objective in de meta-kolom

Voeg **Objective** toe als vijfde keuzeveld in de linker meta-kolom,
onder Platform en boven de upload-zone.

**Volgorde meta-kolom:**
1. Rationale (inklapbaar)
2. Tone
3. Angle
4. Image ratio
5. Platform
6. **Objective** ← nieuw
7. Aandachtspunten (self_review, als gevuld)

**UI:**
Zelfde accordeon + radiobutton-patroon als Tone/Angle/Image ratio/Platform.
De radiobuttons zijn de objectives van de klant, dynamisch geladen:

```tsx
// Opties zijn de objectives van de huidige client:
[● Brand Awareness (40%)]
[○ Lead Generation (35%)]
[○ Thought Leadership (25%)]
```

- De geselecteerde objective schrijft naar `articles.objective_id`
- CE-03's suggestie (Stap C) vult dit veld als `objective_suggested`;
  handmatige keuze schrijft naar `objective_final` (zie Stap 5)
- Als een klant nog geen objectives heeft ingesteld: toon het veld niet,
  toon in plaats daarvan een link naar Strategy

**Datamodel-toevoeging articles:**
```sql
ALTER TABLE articles
  ADD COLUMN objective_suggested CHAR(36) NULL,
  ADD COLUMN objective_final CHAR(36) NULL;
```

---

## Stap 4 — CE-01 (n8n): Objectives meenemen in thema-prompt

### Get Client Strategy — query uitbreiden

Voeg aan de bestaande `Get Client Strategy`-node de objectives toe:

```sql
SELECT o.id, o.label, o.description, o.percentage
FROM objectives o
WHERE o.client_id = $1
ORDER BY o.sort_order;
```

### Build Prompt — objectives in de CE-01-prompt

Voeg een OBJECTIVES-sectie toe aan de thema-prompt:

```
DOELSTELLINGEN (verdeling over de contentcyclus):
{{#each objectives}}
- {{label}} ({{percentage}}%): {{description}}
{{/each}}

Stel thema's voor die de doelstellingen weerspiegelen in de juiste verhouding.
Bij 40% Brand Awareness, 35% Lead Generation en 25% Thought Leadership:
stel per 4 thema's bij benadering voor:
- 2 Brand Awareness-thema's
- 1 Lead Generation-thema
- 1 Thought Leadership-thema
Varieer de volgorde — geen vaste rotatie.
```

### Thema-output: objective_id meegeven

Voeg aan het JSON-schema van CE-01's tool-output toe:

```json
{
  "objective_id": "string (id uit objectives-tabel, of null)"
}
```

Sla op in `themes.objective_id` (nieuw nullable FK-veld):

```sql
ALTER TABLE themes
  ADD COLUMN objective_id CHAR(36) NULL,
  ADD CONSTRAINT fk_themes_objective
    FOREIGN KEY (objective_id) REFERENCES objectives(id);
```

---

## Stap 5 — CE-03 (n8n): Objective in artikel-prompt en Stap C

### Input: objective meelezen

Voeg aan de `Get Slot`-query toe:

```sql
-- JOIN objectives via slot.objective_id of theme.objective_id:
LEFT JOIN objectives obj ON obj.id = COALESCE(s.objective_id, t.objective_id)
```

Geef door als `slot.objective_label` en `slot.objective_description`.

### Stap A (rationale-prompt): objective toevoegen

```
DOELSTELLING VAN DEZE POST: {{slot.objective_label}}
{{slot.objective_description}}

De rationale moet aansluiten op deze doelstelling.
```

### Stap B (artikel-prompt): schrijfstijl toevoegen

```
SCHRIJFSTIJL: {{client.writing_style}}
```

### Stap C (suggesties): objective_suggested toevoegen

Voeg toe aan het JSON-schema van `generate_suggestions`:

```json
{
  "objective_id": {
    "type": "string",
    "description": "ID van de meest passende objective uit de lijst"
  }
}
```

Geef de beschikbare objectives mee in de Stap C-prompt:

```
BESCHIKBARE DOELSTELLINGEN:
{{#each objectives}} - {{id}}: {{label}} ({{description}}) {{/each}}

Kies de objective die het beste past bij de rationale en het artikel.
```

Sla op als `articles.objective_suggested`.

---

## Stap 5b — Sources: mapstructuur (objective + Algemeen)

**Doel:** Research-input structureren zodat CE-01/CE-02 per doelstelling de **juiste bronnen** gebruiken.

### UI op `/sources`

```
[ Algemeen ]                    ← altijd zichtbaar, altijd meegenomen
[ Brand Awareness (40%) ]
[ Lead Generation (35%) ]
[ Thought Leadership (25%) ]
─────────────────────────────
+ Tab: URL/RSS | Scraper | Mailbox | …  (bestaande categorieën)
```

- Elke bron hoort in **Algemeen** óf in **exact één objective-map** (v1: geen dubbele mappen; later optioneel multi-map).
- Lege objective-map: hint “Voeg bronnen toe die passen bij [label]”.
- Drag bron tussen mappen (PATCH `objective_id`).

### Datamodel

```sql
ALTER TABLE sources
  ADD COLUMN objective_id CHAR(36) NULL,
  ADD CONSTRAINT fk_sources_objective
    FOREIGN KEY (objective_id) REFERENCES objectives(id);
-- objective_id IS NULL → map Algemeen
```

Migratie: `scripts/migrations/add_objective_id_to_sources.sql`

### CE-01 / CE-02

Bij theme/slot met `objective_id = X`:

```sql
SELECT * FROM sources
WHERE client_id = :client_id
  AND (objective_id IS NULL OR objective_id = :objective_id);
```

### API

- `GET /api/sources?objectiveId=` — filter op map (`general` = alleen NULL).
- `PATCH /api/sources/:id` — verplaatsen tussen mappen.

---

## Stap 6 — Sources: Google Place ID

Voeg aan het **Account-scherm** (niet Sources) een veld toe:

**Label:** Google Place ID
**Placeholder:** ChIJ...
**Help-tekst:** Vind je Place ID via: https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder

Sla op als `clients.google_place_id`.

Toon alleen als het veld leeg is: een inline link naar de Place ID-finder.

---

## Stap 7 — CE-07 (n8n): Google Reviews ophalen

### Nieuwe node: Fetch Google Reviews

Na de bestaande data-query's, vóór de Claude Insight-call:

```javascript
// Code-node: Fetch Google Reviews
const placeId = $('Get Client Strategy').first().json.google_place_id;

if (!placeId) {
  return [{ json: { google_reviews: null, google_rating: null } }];
}

const apiKey = $env.GOOGLE_PLACES_API_KEY; // in n8n environment variables
const url = `https://maps.googleapis.com/maps/api/place/details/json`
  + `?place_id=${placeId}`
  + `&fields=rating,user_ratings_total,reviews`
  + `&language=nl`
  + `&key=${apiKey}`;

const res = await $http.get(url);
const result = res.data?.result ?? {};

return [{
  json: {
    google_rating: result.rating ?? null,
    google_total_reviews: result.user_ratings_total ?? null,
    google_reviews: (result.reviews ?? []).slice(0, 5).map(r => ({
      rating: r.rating,
      text: r.text,
      time: r.relative_time_description,
    })),
  }
}];
```

**Google Places API-key:** sla op als n8n environment variable
`GOOGLE_PLACES_API_KEY` — niet in de database.

### Claude Insight-prompt uitbreiden

Voeg toe aan de CE-07-synthese-prompt:

```
GOOGLE REVIEWS (laatste 5):
Rating: {{google_rating}}/5 ({{google_total_reviews}} beoordelingen totaal)
{{#each google_reviews}}
- {{rating}}/5: "{{text}}" ({{time}})
{{/each}}

Verwerk de Google-reviews als signaal voor merkwaardering buiten de eigen
content om. Benoem of de toon van de reviews overeenkomt met de merkpositionering
en of er thema's in de reviews zitten die de volgende contentcyclus kunnen voeden.
```

### performance_reports uitbreiden

```sql
ALTER TABLE performance_reports
  ADD COLUMN google_rating       DECIMAL(3,1) NULL,
  ADD COLUMN google_total_reviews INT NULL,
  ADD COLUMN google_reviews_snapshot JSON NULL;
```

---

## Stap 8 — Report-scherm: twee nieuwe secties

### 8a. Objective-verdeling

Toon na de Engagement strategy-grafiek een nieuwe sectie:

**Koptekst:** Objectives

Staafdiagram (horizontaal, zelfde stijl als de bestaande grafieken):

```
Brand Awareness     ████████░░░░  40% gepland  |  38% gerealiseerd
Lead Generation     ██████░░░░░░  35% gepland  |  36% gerealiseerd
Thought Leadership  █████░░░░░░░  25% gepland  |  26% gerealiseerd
```

- "Gepland" = percentage uit `objectives.percentage`
- "Gerealiseerd" = percentage van gepubliceerde posts in de periode
  met `objective_final = objective.id` (of `objective_suggested` als final null)
- Berekening via CE-07:

```sql
SELECT
  o.label,
  o.percentage AS planned_pct,
  ROUND(
    COUNT(CASE WHEN COALESCE(a.objective_final, a.objective_suggested) = o.id
               THEN 1 END) * 100.0 / COUNT(a.id), 1
  ) AS realized_pct
FROM objectives o
CROSS JOIN articles a
JOIN content_slots s ON s.id = a.slot_id
WHERE s.client_id = :client_id
  AND a.published_at BETWEEN :period_start AND :period_end
GROUP BY o.id, o.label, o.percentage;
```

### 8b. Google Reviews

Toon als aparte sectie onder de Objectives-verdeling:

```
Google Reviews
★★★★☆  4.2 / 5  (127 beoordelingen)

Laatste reviews:
★★★★★  "Doordat onze identiteit eerst is vastgelegd..."  — 2 weken geleden
★★★★☆  "Professionele aanpak, duidelijke communicatie"  — 1 maand geleden
```

- Toon maximaal 5 meest recente reviews
- Alleen zichtbaar als `clients.google_place_id` is ingesteld
- Als Place ID ontbreekt: toon een inline hint "Stel je Google Place ID in
  via Account om reviews hier te tonen"

---

## Wijzigingsmatrix

| Bestand / onderdeel | Wijziging |
|--------------------|-----------|
| `objectives` tabel | Nieuw |
| `clients.google_place_id` | Nieuw |
| `clients.writing_style` | Nieuw |
| `themes.objective_id` | Nieuw FK |
| `content_slots.objective_id` | Nieuw FK |
| `articles.objective_id/suggested/final` | Nieuw |
| `performance_reports` | Google-velden + objective-snapshot |
| `StrategyPage.tsx` | Objectives-sectie + percentage-validatie + schrijfstijl-pulldown |
| `AccountPage.tsx` | Planning horizon + Posts per week verwijderen |
| `ArticlePage.tsx` (meta-kolom) | Objective als vijfde keuzeveld |
| `ReportPage.tsx` | Objective-verdeling grafiek + Google Reviews sectie |
| `sources.objective_id` | Nieuw FK (NULL = Algemeen) |
| `SourcesPage.tsx` | Map-sidebar: Algemeen + per objective |
| `PATCH /api/account` | google_place_id toevoegen |
| CE-01 n8n | Objectives in prompt + objective_id in output |
| CE-03 n8n | Objective + writing_style in prompt; objective_suggested in Stap C |
| CE-07 n8n | Google Places API-call + objectives-query |
| `product-vision.md` | Objectives en LLM-vindbaarheid bijwerken na implementatie |

---

## Wat bewust buiten scope is

- Objectives per post handmatig wijzigen vanuit de Agenda — gaat via het
  Article-scherm (meta-kolom)
- Budgetbewaking per objective (bv. "stop met Lead Generation-posts als
  het budget op is") — later uitbreiding
- Google Reviews als research-bron in CE-02 — bewust niet; reviews zijn
  een rapport-signaal, geen contentbron
- Automatisch herbalanceren van thema-voorstellen als de realisatie sterk
  afwijkt van het plan — CE-06 (Preference Learner) kan dit later oppakken
