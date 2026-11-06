# Campaign Planner — client_id in plaats van client-naam

## Wat er verandert

De n8n `CE-CampaignPlanner`-flow (v2) verwacht nu `client_id` (UUID) in de payload
in plaats van `client` (vrije tekst). De flow haalt zelf Archetype, Adversary,
Schrijfwijzer etc. op uit de `clients`-tabel en geeft die mee aan de Claude-prompt.

## Wat Cursor moet aanpassen

### `POST /api/campaign-plan` payload

**Was:**
```json
{ "theme": "...", "postCount": 4, "targetAudience": "...", "client": "iGiSNext" }
```

**Wordt:**
```json
{ "theme": "...", "postCount": 4, "targetAudience": "...", "client_id": "016384af-b26e-4415-aa9c-abc46b804798" }
```

### `CampaignPage.tsx` / `CampaignInputCard`

1. Verwijder het vrije tekst-veld `client` (of rename het naar een read-only label).
2. De `client_id` is al bekend in de frontend (zit in de session/context van de
   ingelogde gebruiker, net zoals bij alle andere API-calls). Stuur die mee in de payload.
3. Als de frontend de client_id nog niet heeft: haal hem op via `GET /api/clients`
   (bestaat al, zie `web/api/account.php`).

### Geen andere wijzigingen nodig

- `POST /api/campaign-plan` (PHP) hoeft alleen de `client_id` door te sturen naar n8n —
  geen DB-query in PHP nodig, de n8n-flow doet dat zelf.
- De response van n8n verandert niet: nog steeds `{ campaignTitle, posts[], generatedAt }`.
- De `posts[].angle` is nu merkstrategie-bewust (de flow kent de adversary/archetype).
