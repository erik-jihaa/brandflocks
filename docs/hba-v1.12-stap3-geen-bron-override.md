# Stap 3 — `geen_bron` klantbevestiging (besluit 2)

**Besluit Rogier (ronde 6):** hard blokkeren alleen zolang de klant geen bron aanwijst.

## Gedrag

- Zin met `geen_bron` in CE-03 self_review **blokkeert indienen/publiceren niet** na klantbevestiging.
- Bevestiging: type `merkarchitectuur` | `bewijsveld` | `url` | `document` + vrije tekst (veldnaam, URL, document).
- Opslag in `articles.self_review` → `merk_beweringen[].klant_bevestiging` (bron blijft `geen_bron` voor meting).
- Audit: `event_log` type `geen_bron_klant_bevestigd`.

## API

`POST /api/articles/{slotId}/self-review/confirm-geen-bron`

```json
{ "citaat": "…", "bronType": "bewijsveld", "bronWaarde": "…" }
```

## UI

Artikel → Zelfcontrole → bij rode **Geen bron**: dropdown + bronveld + **Bron bevestigen**.
