# HBA v1.8 — V5 (klantweergave + advies-prompt)

Rogier **V5** in `briefing/hba-input/hba-v1.8-antwoorden-rogier.md`.  
Promptversie: **`v1.8.1`**.

## Composer (eerder)

1. **Klantpreview-links** — `/article/{articles.id}?hbaKlant=1` (en archief met dezelfde query) laden via API-resolutie op slot **of** article-id; geen redirect naar archief zonder HBA bij `hbaKlant=1`.
5. **Klantzin** — via `hoofdoordeel` / `hoofdoordeelKlantzin` uit de score-API (na V1-correctie).

URL-lijst: `./scripts/list-hba-r5-klantpreview-urls.sh` → `docs/hba-r5-klantpreview-urls-staging.md`.

## Advies-prompt (V5.2–V5.4)

| Item | Implementatie |
|------|----------------|
| **2** Klanttaal | Fragment `web/api/prompts/hba-verbeteradvies-definitie.txt` + verbodsregel in scoreprompt |
| **2** Logging | `verbeteradvies_verboden_termen` in `raw_llm_response`; `event_log` warning `hba/verbeteradvies_verboden_termen`; retry + `needs_review` |
| **3** `op_merk` | Geen advies of max. één korte zin (prompt); klantpreview toont tip sowieso niet bij `op_merk`/`neutraal` |
| **4** Regel juist | D2-fragment: regel niet anders weergeven dan in de geloofwaardigheidstest |

Nieuwe scores (force rescore) krijgen `prompt_version = v1.8.1`. Bestaande scores op staging blijven v1.8 tot her-score.
