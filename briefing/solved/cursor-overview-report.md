# Content Engine — Overview-grafiek + Report-uitbreidingen

Twee losse features in één briefing omdat ze qua databronnen overlappen.
Raadpleeg bij twijfel ook `briefing/product-vision.md`.

---

## DEEL 1 — Home: 8-weken sparkline "Hoe gaat het"

### Doel

Home moet in één oogopslag beantwoorden of de content op-strategie blijft,
zonder dat de gebruiker naar Report hoeft te klikken. Geen dashboard — één
compacte lijn plus één groot cijfer.

### Wat te bouwen

**Component:** `HomeStrategyTrend` — nieuw, op het Home-scherm boven of naast
de bestaande pipeline-status.

**Weergave:**
```
78% op-strategie deze maand ↑
[sparkline, 8 weken, geen assen/labels]
```

- Groot percentage: huidige week (of lopende 4-weeks gemiddelde, kies wat
  stabieler oogt met weinig data)
- Pijltje: ↑ als de trend van de laatste 8 weken stijgend is, ↓ dalend,
  → vrijwel gelijk (drempel: ±3 procentpunt)
- Sparkline: simpele lijn zonder as-labels, 8 datapunten (één per week),
  kleur groen bij stijgend/stabiel, oranje/geel bij dalend
- Klik op de sparkline navigeert naar `/report` met de juiste periode
  voorgeselecteerd

**Data-bron — geen nieuwe tabel nodig:**
```sql
SELECT
  YEARWEEK(a.published_at) AS week,
  ROUND(
    COUNT(CASE WHEN a.angle_final IN ('archetype','adversary')
               OR (a.angle_final IS NULL AND a.angle_suggested IN ('archetype','adversary'))
          THEN 1 END) * 100.0 / COUNT(a.id), 0
  ) AS pct_on_strategy
FROM articles a
JOIN content_slots s ON s.id = a.slot_id
WHERE s.client_id = :client_id
  AND a.status = 'published'
  AND a.published_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
GROUP BY YEARWEEK(a.published_at)
ORDER BY week ASC;
```

**API:** voeg toe aan `GET /api/home` — geen nieuw endpoint nodig, dit is
een extra veld in de bestaande Home-response:
```json
{
  "strategyTrend": {
    "currentPct": 78,
    "trendDirection": "up",
    "weeklyData": [65, 68, 70, 72, 75, 74, 76, 78]
  }
}
```

**Edge case:** minder dan 3 weken data → toon geen sparkline, toon in plaats
daarvan "Nog te weinig gepubliceerde content om een trend te tonen."

---

## DEEL 2 — Report: drie uitbreidingen

### 2a. Trendlijn over meerdere rapportperiodes

**Doel:** laat zien of de klant maand-op-maand vooruitgang boekt, niet alleen
een momentopname.

**Waar:** nieuwe sectie bovenaan Report, vóór de bestaande Engagement
strategy-grafiek.

**Weergave:**
```
Verschilpercentage op-strategie vs. generiek, laatste 6 maanden

+45%  +52%  +48%  +61%  +58%  +79%
 Feb   Mrt   Apr   Mei   Jun   Jul
```
Eenvoudige staafjes of lijn, elke maand het `engagement_diff_pct` uit het
opgeslagen rapport van die periode.

**Data-bron:**
```sql
SELECT
  DATE_FORMAT(period_start, '%b') AS month_label,
  engagement_diff_pct
FROM performance_reports
WHERE client_id = :client_id
ORDER BY period_start DESC
LIMIT 6;
```

**Ontbrekende input — belangrijk om nu te fixen:**
Deze sectie werkt alleen als rapporten daadwerkelijk worden opgeslagen.
Controleer of de huidige flow rapporten alleen bij expliciete "Save" opslaat.
Als dat zo is: overweeg rapporten **automatisch op te slaan bij elke Generate**,
zodat er altijd historie is, ongeacht of de gebruiker op Save klikt. Dit is
een kleine wijziging in `web/api/report-generate.php` (sla op ná ontvangst
van de CE-07-response, niet pas bij een aparte Save-actie).

**Edge case:** minder dan 2 opgeslagen rapporten → verberg deze sectie
volledig (geen lege grafiek tonen).

---

### 2b. LLM-vindbaarheid

**Doel:** tonen of het merk verschijnt in antwoorden van ChatGPT/Perplexity/
Claude op de vragen die de doelgroep daadwerkelijk stelt.

**Vereiste vooraf:** dit vereist het Strategy-veld "LLM-probleemvragen"
(zie `product-vision.md`, sectie LLM-vindbaarheid). Bouw dat eerst als het
er nog niet is — zonder ingevulde vragen is er niets te testen.

#### Datamodel

```sql
CREATE TABLE llm_probleemvragen (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  client_id   CHAR(36)     NOT NULL,
  question    TEXT         NOT NULL,
  active      BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_llm_vragen_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE llm_visibility_checks (
  id            CHAR(36)     NOT NULL PRIMARY KEY,
  client_id     CHAR(36)     NOT NULL,
  question_id   CHAR(36)     NOT NULL,
  model         VARCHAR(50)  NOT NULL,   -- 'chatgpt' | 'perplexity' | 'claude'
  mentioned     BOOLEAN      NOT NULL,
  raw_response  TEXT,
  checked_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_llm_check_client   FOREIGN KEY (client_id)  REFERENCES clients(id),
  CONSTRAINT fk_llm_check_question FOREIGN KEY (question_id) REFERENCES llm_probleemvragen(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Strategy-scherm: LLM-probleemvragen sectie

Nieuwe subsectie onder Objectives (of onder Brandstory — kies wat logisch
aansluit bij de bestaande volgorde).

```
LLM-probleemvragen (3-5 vragen die je doelgroep aan ChatGPT stelt)

[Tekstveld] "Hoe zorg ik dat kennis niet in hoofden van medewerkers blijft?"
[Tekstveld] "Hoe maak ik mijn organisatie minder afhankelijk van de oprichter?"
[+ Add question]  (max 5)
```

API: `GET/POST/DELETE /api/llm-questions`.

#### n8n: nieuwe node in CE-07, vóór de Claude Insight-call

**Test-cadans: één keer per maand, niet bij elke Report-Generate.**
Voeg een aparte schedule-trigger toe aan CE-07 (of een nieuwe kleine flow
`CE-LLM-Visibility`) die maandelijks draait, los van wanneer een gebruiker
het rapport opent.

```javascript
// Code-node: Check LLM Visibility
const questions = $('Get LLM Questions').all().map(i => i.json);
const clientName = $('Get Client Strategy').first().json.name;

const results = [];

for (const q of questions) {
  // ChatGPT (OpenAI API)
  const gptRes = await $http.post('https://api.openai.com/v1/chat/completions', {
    headers: { Authorization: `Bearer ${$env.OPENAI_API_KEY}` },
    body: {
      model: 'gpt-4o',
      messages: [{ role: 'user', content: q.question }],
    },
  });
  const gptText = gptRes.data.choices[0].message.content;
  results.push({
    question_id: q.id,
    model: 'chatgpt',
    mentioned: gptText.toLowerCase().includes(clientName.toLowerCase()),
    raw_response: gptText,
  });

  // Perplexity API
  const pplxRes = await $http.post('https://api.perplexity.ai/chat/completions', {
    headers: { Authorization: `Bearer ${$env.PERPLEXITY_API_KEY}` },
    body: {
      model: 'sonar',
      messages: [{ role: 'user', content: q.question }],
    },
  });
  const pplxText = pplxRes.data.choices[0].message.content;
  results.push({
    question_id: q.id,
    model: 'perplexity',
    mentioned: pplxText.toLowerCase().includes(clientName.toLowerCase()),
    raw_response: pplxText,
  });

  // Claude (Anthropic API) - zelfde patroon als bestaande Claude-nodes
  const claudeRes = await $http.post('https://api.anthropic.com/v1/messages', {
    headers: {
      'x-api-key': $env.ANTHROPIC_API_KEY,
      'anthropic-version': '2023-06-01',
    },
    body: {
      model: 'claude-sonnet-4-6',
      max_tokens: 1024,
      messages: [{ role: 'user', content: q.question }],
    },
  });
  const claudeText = claudeRes.data.content[0].text;
  results.push({
    question_id: q.id,
    model: 'claude',
    mentioned: claudeText.toLowerCase().includes(clientName.toLowerCase()),
    raw_response: claudeText,
  });
}

return results.map(r => ({ json: r }));
```

Vervolgens: INSERT elk resultaat in `llm_visibility_checks`.

**API-keys als n8n environment variables** (niet in de database):
`OPENAI_API_KEY`, `PERPLEXITY_API_KEY`. `ANTHROPIC_API_KEY` bestaat al
(Header Auth-credential, hergebruiken).

#### Report-UI: nieuwe sectie

Plaats na de Google Reviews-sectie.

```
LLM-vindbaarheid

"Hoe zorg ik dat kennis niet in hoofden van medewerkers blijft?"
   ChatGPT ✅   Perplexity ✅   Claude —

"Hoe maak ik mijn organisatie minder afhankelijk van de oprichter?"
   ChatGPT —   Perplexity ✅   Claude —

Zichtbaar in 3 van 6 gecontroleerde antwoorden deze maand
(vorige maand: 1 van 6)
```

- Geen grafiek, geen percentagecirkel — binaire ja/nee-weergave per vraag
  per model, met tekst
- ✅ = groen vinkje, — = grijs streepje (geen rood kruis; "niet genoemd"
  is geen fout, gewoon een status)
- Onderaan: totaalscore + vergelijking met de vorige meting
- Alleen zichtbaar als er minstens één LLM-probleemvraag is ingevuld
  in Strategy; anders tonen: "Voeg LLM-probleemvragen toe in Strategy
  om je zichtbaarheid in AI-antwoorden te meten."

**Query voor de sectie:**
```sql
SELECT
  q.question,
  c.model,
  c.mentioned
FROM llm_visibility_checks c
JOIN llm_probleemvragen q ON q.id = c.question_id
WHERE c.client_id = :client_id
  AND c.checked_at = (
    SELECT MAX(checked_at) FROM llm_visibility_checks
    WHERE client_id = :client_id
  )
ORDER BY q.created_at, c.model;
```

Voor de vergelijking met "vorige maand": zelfde query met de één-na-laatste
`checked_at`-groep.

---

### 2c. Wat bewust niet gebouwd wordt

- Geen vergelijking met concurrenten in de UI, ook al bevat `raw_response`
  soms concurrentnamen — dat blijft alleen intern opgeslagen, niet getoond.
- Geen "branchegemiddelde"-benchmark — geen betrouwbare bron hiervoor.
- Geen realtime LLM-check bij het openen van Report — de maandelijkse
  cadans is vast, om kosten en vergelijkbaarheid te bewaken.

---

## Wijzigingsmatrix

| Onderdeel | Wijziging |
|-----------|-----------|
| `GET /api/home` | `strategyTrend` veld toevoegen |
| `HomePage.tsx` | `HomeStrategyTrend`-component |
| `llm_probleemvragen` tabel | Nieuw |
| `llm_visibility_checks` tabel | Nieuw |
| `StrategyPage.tsx` | LLM-probleemvragen sectie |
| `GET/POST/DELETE /api/llm-questions` | Nieuw |
| CE-07 n8n | Trendquery (2a) + LLM-visibility-node (2b, maandelijkse trigger) |
| `web/api/report-generate.php` | Automatisch opslaan bij Generate (2a) |
| `ReportPage.tsx` | Trendsectie (2a) + LLM-vindbaarheid sectie (2b) |
| n8n environment | `OPENAI_API_KEY`, `PERPLEXITY_API_KEY` toevoegen |

## Checklist

- [ ] Home: sparkline + percentage, klik navigeert naar Report
- [ ] Home: edge case <3 weken data
- [ ] Report: trendlijn 6 maanden, edge case <2 rapporten
- [ ] Rapporten worden automatisch opgeslagen bij Generate
- [ ] Strategy: LLM-probleemvragen sectie (max 5)
- [ ] CE-07: maandelijkse LLM-visibility-check, los van Report-Generate
- [ ] Report: LLM-vindbaarheid sectie, alleen zichtbaar met ≥1 vraag ingevuld
- [ ] Geen concurrentnamen zichtbaar in de UI
