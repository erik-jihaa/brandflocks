# Cursor brief: campagneplanning toevoegen aan Content Engine

## Context
De Content Engine (CE) heeft een bestaand formulier voor het aanmaken van een enkele post (**Fastlane**, `/fastlane`). We voegen een **aparte campagneplanner** toe op route `/campaign`: de gebruiker beschrijft een campagne, kiest hoeveel posts, en de CE genereert een volledig postschema — inclusief kernboodschap, format, hook en week — dat daarna **per post** ter review staat en pas daarna als losse posts de bestaande contentketen ingaat.

Gebruik **uitsluitend bestaande componenten, kleuren, typografie en spacing** uit de huidige CE-codebase. Voeg geen nieuwe design-tokens of externe libraries toe. Designreferentie: `briefing/campagne planner.png` (invoer) en `briefing/campagne result.png` (resultaat).

**Belangrijk:** Fastlane blijft enkel-post. Campagneplanning is een **eigen navigatie-item** (sidebar: Campaign), geen toggle op Fastlane.

---

## Wat je bouwt

### 1. Aparte route `/campaign` (sidebar-navigatie)

Voeg een sidebar-item **Campaign** toe tussen Theme en Archive. Route: `/campaign` → `CampaignPage.tsx`.

Fastlane (`/fastlane`) blijft ongewijzigd: alleen enkele post, geen campagnemodus.

### 2. Campagne-invoerscherm

Invoer volgt het design-PNG (`campagne planner.png`):

```
┌─────────────────────────────────────────────────────┐
│  Enter title                                         │
│  ┌───────────────────────────────────────────────┐  │
│  │ (optioneel — wordt doelgroep-hint)            │  │
│  └───────────────────────────────────────────────┘  │
│                                                      │
│  Motivate the campaign                               │
│  ┌───────────────────────────────────────────────┐  │
│  │ Textarea — pink kader, zelfde stijl als Theme │  │
│  └───────────────────────────────────────────────┘  │
│                                                      │
│  Amount of articles                                  │
│  ┌───────────────────────────────────────────────┐  │
│  │ Tekstinput (3–6, default 4)                   │  │
│  └───────────────────────────────────────────────┘  │
│                                                      │
│  [ Generate plan ]  [ Undo ]                         │
└─────────────────────────────────────────────────────┘
```

- **Enter title** — optioneel; wordt `targetAudience` in de plannings-API
- **Motivate the campaign** — verplicht; kern van het campagnethema
- **Amount of articles** — aantal posts (3–6)
- **Generate plan** — roept **alleen** de plannings-API aan; voegt nog niets toe aan de wachtrij

### 3. Loading state
Tekst: **"Generating plan…"** (zelfde typografie als andere CE-loadingstates).

### 4. Planningsresultaat (na succesvolle API-response)

**Twee-staps-flow:** eerst plannen, dan reviewen, dan toevoegen. Geen one-shot submit.

**Bovenaan het resultaatscherm (één keer, niet per kaart):**
- Campagnetitel uit de API-response
- Doelgroep (uit Enter title) + aantal posts
- Knoppen: **Add all to queue**, **Plan again**
- Na alles toegevoegd: link **View Agenda →**

```
┌─────────────────────────────────────────────────────┐
│  [campagnetitel]                                     │
│  Audience: [targetAudience] · 4 posts generated      │
│  [Add all to queue]  [Plan again]                    │
└─────────────────────────────────────────────────────┘
```

**Daarna: één kaart per post** (`CampaignPostCard`):

```
┌─────────────────────────────────────────────────────┐
│  Post 1 · Week 1                  [storytelling]    │
│                                                      │
│  [kernboodschap / theme]                            │
│                                                      │
│  "[hook — openingszin voor LinkedIn]"               │
│                                                      │
│  #tag1  #tag2  #tag3                                │
│                                                      │
│  [Add]  [Edit]  [Delete]                            │
└─────────────────────────────────────────────────────┘
```

Velden op de kaart:

| Kaartonderdeel | CampaignPost-veld | Opmerking |
|---|---|---|
| Kernboodschap (body) | `theme` | Primaire promptinput |
| Hook (cursief, quoted) | `hook` | Openingszin LinkedIn |
| Format badge (rechts boven) | `format` | Stuurt structuur |
| Tags | `tags` | Hashtags |
| Week (naast postnummer) | `week` | Week-offset (1 = deze week) |

Invalshoek / briefing (`angle`) staat **niet** op de kaart — alleen zichtbaar en bewerkbaar via de edit-view (§5).

Als `readyToPublish: false`: subtiele waarschuwing met `holdReason`. Add blijft actief.

Knoppen per kaart:
- **Add** — voegt die ene post toe via `createFastlaneArticle()` → wachtrij + CE-03
- **Edit** — opent inline edit-view (§5) vóór toevoeging
- **Delete** — verwijdert post uit het plan (alleen vóór toevoeging)
- Na toevoegen: ✓ Added + link Open article; knoppen inactief

### 5. "Edit"-inline view (per post)

Component: `CampaignPostEdit.tsx`. Zelfde stijl als bestaande bewerkingsschermen.

Bewerkbare velden:
- Thema / kernboodschap (textarea) → `theme`
- Hook / openingszin (tekstinput) → `hook`
- Invalshoek / briefing (tekstinput) → `angle` — niet op de kaart
- Format (dropdown: storytelling, 3-stappen, opinie, cijfers + verhaal) → `format`
- Week (nummerveld) → `week`
- Tags (spatie-gescheiden) → `tags`

Knoppen: **Save and add** (opslaan + wachtrij), **Cancel** (terug naar resultaat).

---

## TypeScript interfaces

```typescript
// CampaignPlanRequest — wordt naar POST /api/campaign-plan gestuurd
interface CampaignPlanRequest {
  theme: string;           // motivatie (+ optioneel title-prefix)
  postCount: 3 | 4 | 5 | 6;
  targetAudience?: string; // uit Enter title
  client_id?: string;      // UUID — n8n haalt merkstrategie op uit clients-tabel
}

interface CampaignPost {
  postNumber: number;
  week: number;
  theme: string;
  angle: string;            // invalshoek / briefing (alleen edit-view)
  hook: string;
  format: 'storytelling' | 'steps' | 'opinion' | 'data';
  tags: string[];
  readyToPublish: boolean;
  holdReason?: string;
}

interface CampaignPlanResponse {
  campaignTitle: string;
  posts: CampaignPost[];
  generatedAt: string;
}
```

---

## API (CE → PHP → n8n)

### Stap 1 — Alleen plannen

```typescript
// frontend/src/api/campaign.ts
export function generateCampaignPlan(body: CampaignPlanRequest): Promise<CampaignPlanResponse> {
  return apiFetch('/campaign-plan', { method: 'POST', body: JSON.stringify(body) })
}
```

PHP: `web/api/campaign-plan.php` → `ce_generate_campaign_plan()` → n8n webhook.

| Variabele | Voorbeeld |
|---|---|
| `N8N_API_URL` | `https://erikjihaa.app.n8n.cloud` |
| `N8N_CE_CAMPAIGN_PLAN_WEBHOOK_URL` | `https://erikjihaa.app.n8n.cloud/webhook/ce-campaign-plan` |

Productie: `scripts/deploy-ftp.sh` schrijft `ce_campaign_plan_webhook_url` naar `web/includes/ce.local.php`.

### Stap 2 — Per post toevoegen (frontend)

Na review roept de frontend **per goedgekeurde post** `createFastlaneArticle()` aan (`POST /api/fastlane`). Er is **geen** `POST /api/campaign-submit` meer — plan en enqueue zijn gescheiden.

---

## "Toevoegen aan wachtrij" — veldmapping

| CampaignPost | Fastlane payload | CE / DB |
|---|---|---|
| `theme` | `title` | Theme-titel |
| `hook` | `hook` | Wordt in rationale/extraThoughts verwerkt |
| `angle` | `rationale` | Briefing / invalshoek |
| `format` | `format` | Artikelstructuur |
| `tags` | `tags` | Hashtags |
| `week` | `scheduledWeek` | Week-offset → `ce_week_start()` |

Doelgroep (`targetAudience`) gaat **niet** per post mee — alleen naar de planningsprompt.

---

## State machine

```
INPUT (/campaign)
  → [klikt Generate plan]
LOADING
  → [API response ontvangen]
RESULT
  → [klikt Add per post]         → wachtrij, kaart ✓
  → [klikt Edit]                 → EDIT_POST
  → [klikt Add all to queue]     → alle posts naar wachtrij
  → [klikt Plan again]           → terug naar INPUT
EDIT_POST
  → [klikt Save and add]         → RESULT, kaart ✓
  → [klikt Cancel]               → RESULT
```

---

## CE-componentreferentie (werkelijke namen)

| Onderdeel | Component / bestand |
|---|---|
| Pagina | `frontend/src/pages/CampaignPage.tsx` |
| Sidebar-nav | `Campaign` in `frontend/src/components/SidebarNav.tsx`, route in `App.tsx` |
| Invoer (title + motivatie) | `frontend/src/components/CampaignInputCard.tsx` |
| Aantal posts | `FieldSingle` placeholder `Amount of articles` |
| Knoppen | `PillButton` |
| Resultaatkaarten | `frontend/src/components/CampaignPostCard.tsx` |
| Inline edit | `frontend/src/components/CampaignPostEdit.tsx` |
| Format-dropdown | `Pulldown` uit `frontend/src/components/ui/Pulldown.tsx` |
| Layout | `GridShell` + `activeNav="campaign"` |
| Plannen | `generateCampaignPlan()` → `POST /api/campaign-plan` |
| Wachtrij | `createFastlaneArticle()` → `POST /api/fastlane` → `ce_create_fastlane()` |
| Types | `frontend/src/types/campaign.ts` |
| n8n-workflow | `CE-CampaignPlanner` — `scripts/generate-ce-campaign-workflow.py`, push via `scripts/push-ce-campaign-n8n.sh` |
| Enkele post (apart) | `frontend/src/pages/FastlanePage.tsx` — ongewijzigd |

---

## Wat Cursor NIET hoeft te doen

- Geen nieuwe kleurpalet, typografie of spacing introduceren
- Geen campagne-toggle op Fastlane — aparte route only
- Geen `campaign-submit` endpoint — plan en enqueue zijn gescheiden
- Geen wijzigingen aan de n8n-verbinding voor bestaande contentgeneratie (CE-03 blijft hetzelfde)
- Geen database-wijzigingen — posts gaan via bestaande fastlane/wachtrij-logica

---

## Hoe je Cursor instrueert

```
Voeg campagneplanning toe conform CE-campagneplanning-cursor-brief.md.

Aparte route /campaign (CampaignPage), sidebar-item Campaign.
Fastlane (/fastlane) blijft enkel-post.

Flow: Generate plan → review (CampaignPostCard) → Add / Add all via createFastlaneArticle().
Edit: CampaignPostEdit inline, Save and add.

API: alleen POST /api/campaign-plan (n8n CE-CampaignPlanner).
Geen campaign-submit.

Design: briefing/campagne planner.png + campagne result.png.
Placeholders exact: Enter title, Motivate the campaign, Amount of articles.
```
