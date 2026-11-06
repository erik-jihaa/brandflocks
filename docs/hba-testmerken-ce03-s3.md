# S3 — CE-03 haalt merkregels zelf op (geplande CE-01-run)

## Wat er gebouwd is

- **API:** `POST /api/ce03/slot-meta` met `{ "slot_id": "<uuid>" }` en header `X-CE-Reset-Token`.
  Zelfde payload als de app meestuurt via `ce_ce03_webhook_meta_from_slot()`:
  `write_directive`, platform-meta, `grenzen_block`, `nl_writing_style_block`, objectives, tone_options, enz.
- **CE-03 (n8n):** na `Parse Input` controleert `Prepare CE03 Slot Meta` of
  `grenzen_block`, `write_directive` of `nl_writing_style_block` ontbreken.
  Zo ja → `Fetch CE03 Slot Meta` → `Merge CE03 Slot Meta` → daarna `Get Slot` / `Validate Slot`.
  CE-01 hoeft alleen `{ "slot_id": "…" }` te sturen.

## Staging deploy

1. App: `ce03-slot-meta.php` + route in `index.php` (of volledige `./scripts/deploy-ftp.sh staging`).
2. n8n: `./scripts/push-ce03-n8n.sh staging` (generator leest `CE_PUBLIC_BASE_URL` + `CE_RESET_TOKEN` uit `.env.staging`).

## Bewijs — één run vastleggen

### A. API (zonder n8n)

Kies een slot met artikel-stub (bijv. via `/api/admin/ce03-phase2-test?trigger=0`):

```bash
curl -sS -X POST "$CE_PUBLIC_BASE_URL/api/ce03/slot-meta" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN" \
  -d '{"slot_id":"<uuid>"}' | jq '.grenzenHasBlock, .writeDirectiveChars, .meta.grenzen_block[:80]'
```

Verwacht: `grenzenHasBlock: true` voor een tenant met actieve meetlat + volledig merkverhaal.

### B. CE-03 alsof CE-01 start (alleen slot_id)

```bash
curl -sS -X POST "$N8N_CE03_WEBHOOK_URL" \
  -H "Content-Type: application/json" \
  -d '{"slot_id":"<uuid>"}'
```

In **n8n → CE-03 execution → Merge CE03 Slot Meta**:

- `meta_merge_source` = `"api"`
- `grenzen_block` begint met `GRENZEN:`

In **Claude Step B** (system/user in execution log): het GRENZEN-blok staat in de prompt
(via `$('Format Data').item.json.slot.grenzen_block`).

Noteer execution-id en datum hier:

| Veld | Waarde |
| --- | --- |
| Omgeving | staging |
| slot_id | |
| n8n execution id | |
| meta_merge_source | api |
| grenzen_block in Step B | ja/nee |
