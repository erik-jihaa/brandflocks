# Campaign overzicht + Agenda-koppeling

## Context

Een campagneplan verdwijnt nu zodra je wegnavigeert of pagina ververst.
Posts die via "Add" zijn toegevoegd staan op de Agenda maar tonen niet dat
ze bij een campagne horen. Er is geen weg terug naar de originele briefing.

Dit lost dat op in vier stappen.

---

## Stap 1 — Database (al uitgevoerd via SQL-script)

Twee wijzigingen zijn al doorgevoerd in `add_campaigns_table.sql`:
- Nieuwe tabel `campaigns` (id, client_id, title, theme, target_audience, post_count, generated_plan JSON, status, created_at)
- `content_slots.campaign_id` (nullable FK naar campaigns.id)

---

## Stap 2 — Plan opslaan bij Generate (PHP + CampaignPage)

### `POST /api/campaign-plan` (PHP)

Na het ontvangen van de n8n-response: sla de campagne op in de DB.

```php
// Na ontvangst n8n-response:
$campaignId = generate_uuid();
db_execute(
  "INSERT INTO campaigns (id, client_id, title, theme, target_audience, post_count, generated_plan)
   VALUES ($1, $2, $3, $4, $5, $6, $7)",
  [$campaignId, $clientId, $plan['campaignTitle'], $theme,
   $targetAudience, $postCount, json_encode($plan)]
);

// Geef campaign_id terug in de response:
echo json_encode([
  'campaign_id' => $campaignId,
  ...$plan  // bestaande response ongewijzigd
]);
```

### `CampaignPage.tsx`

Sla `campaign_id` op in state na Generate:
```tsx
const [campaignId, setCampaignId] = useState<string|null>(null);
// Na fetch:
setCampaignId(data.campaign_id);
setPlan(data);
```

---

## Stap 3 — campaign_id meegeven bij Add / Add all

### `createFastlaneArticle()` uitbreiden

Geef `campaign_id` mee in de Fastlane-payload:
```ts
createFastlaneArticle({
  // bestaande velden...
  campaign_id: campaignId,  // nieuw
});
```

### `web/api/fastlane.php`

Sla `campaign_id` op bij het aanmaken van het content_slot:
```php
// Bij INSERT INTO content_slots:
// voeg campaign_id toe als kolom + parameter
```

---

## Stap 4 — UI: twee nieuwe onderdelen

### 4a. Campaign overzicht (`/campaigns`)

Nieuw scherm, link in de sidebar tussen Campaign en Archive.

**Lijst-weergave** (zelfde stramien als Agenda/Archive):
```
Campaign titel          Status    Posts    Aangemaakt
─────────────────────────────────────────────────────
Adversary-serie Q3      Actief    4/4      28 jun
Onboarding campagne     Actief    2/4      1 jul
```

- `GET /api/campaigns` → lijst van campaigns voor huidige client, inclusief
  aantal gekoppelde content_slots (via JOIN op campaign_id)
- Status-badge: Actief (groen) / Voltooid / Gearchiveerd
- Klik op een rij → detailpagina `/campaigns/:id`

**Detail `/campaigns/:id`**:
- Toont de originele briefing (title, theme, targetAudience, postCount)
- Toont `generated_plan` als leesbare post-lijst (dezelfde CampaignPostCard-stijl)
- Toont welke posts al op de Agenda staan (JOIN via campaign_id)
- Knop **Plan opnieuw** → vult CampaignPage voor met dezelfde briefing-waarden
- Knop **Archiveren** → zet campaigns.status op 'archived'

### 4b. Campaign-badge op de Agenda

Voor Agenda-rijen waarbij `content_slots.campaign_id` gevuld is:

```
Post name 1   [Campagne: Adversary-serie Q3]   Edit | Delete | Approved | Archive
```

- Klein label/badge naast de posttitel (beige tekst, 14px, niet vetgedrukt)
- Label is klikbaar → opent `/campaigns/:campaign_id` in dezelfde tab
- Agenda-API (`GET /api/agenda`) al uitbreiden met `campaign_id` en
  `campaign_title` per slot (via LEFT JOIN op campaigns)

---

## API-overzicht

| Methode | Route | Wat |
|---------|-------|-----|
| GET | `/api/campaigns` | Lijst van campagnes voor huidige client |
| GET | `/api/campaigns/:id` | Detail + gekoppelde slots |
| PATCH | `/api/campaigns/:id` | Status wijzigen (archived) |

---

## Wat je NIET hoeft te doen

- Geen wijziging aan de n8n Campaign Planner flow (plan opslaan gebeurt in PHP)
- Geen nieuwe Agenda-states (campaign is een label, geen aparte status-waarde)
- Geen wijziging aan bestaande content_slots statussen

---

## Checklist

- [ ] `add_campaigns_table.sql` uitgevoerd op DB
- [ ] `POST /api/campaign-plan` slaat campagne op + geeft campaign_id terug
- [ ] `CampaignPage.tsx` bewaart campaign_id in state
- [ ] `createFastlaneArticle()` stuurt campaign_id mee
- [ ] `fastlane.php` schrijft campaign_id naar content_slots
- [ ] `GET /api/campaigns` + `GET /api/campaigns/:id` endpoints
- [ ] `/campaigns` overzichtsscherm (sidebar-link)
- [ ] `/campaigns/:id` detailscherm met originele briefing + post-status
- [ ] Agenda LEFT JOIN op campaigns → campaign_title per slot
- [ ] Campaign-badge op Agenda-rijen met klikbare link
