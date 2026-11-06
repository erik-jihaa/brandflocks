# A6 ronde 2 — stap C: hashtag-bewijs (restpunt 6)

Bron: `briefing/hba-input/hba-a6-restpunten-ronde-2-2026-10-20.md` punt C.

## 1. Brandpunt week 41 (Rogier-voorbeeld in de app)

**Slot:** `d733223a-7964-4324-9883-dcd4b1809006`  
**Artikel:** `9e73e3fd-caee-11f1-84cf-9c6b00b7cd02`  
**Staging:** https://staging.igisnext.com/article/d733223a-7964-4324-9883-dcd4b1809006

Hashtags onder de post (zoals in ronde 6 besproken):

```json
{
  "hashtags": "#blackfriday, #specialtycoffee, #versgebrand, #koffie, #ambacht",
  "postTextTail": "…\n\n#blackfriday, #specialtycoffee, #versgebrand, #koffie, #ambacht"
}
```

Volledige API-output: `docs/a6-screenshots-staging-2026-10-20/08-hashtag-bewijs-brandpunt-week41.json`.

**Scoring:** `ce_hba_article_post_text()` plakt `hashtags_final`/`hashtags_suggested` achter kop+body vóór HBA-scoring (v1.12). Deze post is inhoudelijk op merk; HBA geeft daarom geen risk-flags (diagnose bevestigt de zes-wekenregel). Het bewijs voor restpunt 6 is dat **hashtags in de gescoorde posttekst zitten**, niet dat elke on-brand post een flag krijgt.

## 2. HBA-test Categorietaal + `#versgebrand` (score markeert versgebrand-claim)

**Slot:** `e21d66d8-2bc3-4336-81fd-bbe07ff42e7d` (HBA-test post 2, na v1.13-herimport)  
**Hashtags (staging):** `#versgebrand, #koffie` — via `GET …/hba-hashtag-bewijs?prepare_categorietaal=1` (dynamisch slot, geen hardcoded UUID meer).

Fragment na scoring (20 okt 2026, v1.12):

```json
{
  "hashtags": "#versgebrand, #koffie",
  "postTextTail": "…\n\n#versgebrand, #koffie",
  "hbaScore": {
    "flags": ["category_fallback", "proof_gap"],
    "flag_citaten": {
      "category_fallback": "Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt.",
      "proof_gap": "Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt."
    }
  }
}
```

Volledige JSON: `docs/a6-screenshots-staging-2026-10-20/08-hashtag-bewijs-brandpunt-categorietaal.json`.

## 3. CE-03 (generator)

Hashtag-regel in de CE-03-workflow (`scripts/generate-ce03-workflow.py`, gepusht naar n8n staging):

> Geen hashtag die woorden_die_niet_passen of verboden_logica uit de brand narrative schendt (bijv. geen #versgebrand als hoofdclaim zonder de zes-wekenregel). Hashtags tellen mee als onderdeel van de post.

## Admin (intern)

`GET /api/admin/hba-hashtag-bewijs?slot_id=…` — staging/local, `CE_RESET_TOKEN`. Optioneel `prepare_categorietaal=1` zet test-hashtags op HBA-post 2 en scoort opnieuw (alleen bewijsrun).
