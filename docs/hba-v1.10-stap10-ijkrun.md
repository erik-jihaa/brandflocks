# HBA — stap 10: ijkrun-runner + nulmeting

**Model scoring:** `claude-sonnet-4-6` (via bestaande HBA-modelconfig)  
**Prompt scoring:** `CE_HBA_PROMPT_VERSION` (v1.10.6)  
**Ijkset:** v0.3 (Rogier 16 okt) — `web/data/HBA_ijkset_v0_3.json`; fallback v0.2 als v0.3 ontbreekt.

## Gedaan

- **`web/api/hba-ijkrun-repository.php`** — laden ijkset, sandbox-client, routes per `soort`, judges (Rogier 7.1–7.2).
- **`GET /api/admin/hba-ijkrun`** — reset-token, alleen staging/local.
  - Zonder params: inventory (46 gevallen).
  - `?execute=1&id=T01`: één geval draaien (LLM).
  - `?execute=1`: alle gevallen (lang — liever Python-script).
- **`scripts/run-hba-ijkrun-staging.py`** — nulmeting case-voor-case, optioneel JSON-output.
- **C01–C08:** `runStatus=niet_gedraaid` (stap 9).
- **V01:** `niet_gedraaid` — vereist CE-03 fastlane (`admin-hba-v0-verleiding.php`); apart van batch-runner.

## Sandbox

- Client **`HBA Ijkrun sandbox`** — merkverhaal per geval tijdelijk; guardrail PASS zoals testmerken.
- Scores: `origin=ijkrun` waar kolom bestaat; synthetische `post_id` per ijk-id.

## Judge (kort)

| Soort | Goed/fout |
| --- | --- |
| **tekstscore** | Primair hoofdoordeel + flags + BC-cap (testmerken-logica); T13/T14 valkuil extra kop-check; positionering buiten band = waarschuwing |
| **architectuurcheck** | Twee assen: `judgment_oordeel` (`totaaloordeel` vs `verwacht_oordeel`) en `judgment_meetlat` (`meetlat_status` vs `verwacht_meetlat`); payload bevat `oordeel_status`, `meetlat_status`, `ijkset_definities` |
| **schrijfadvies** | Automatisch minimaal (S05 invulveld); overige **review** (signalen handmatig) |

## Nulmeting draaien

```bash
chmod +x scripts/run-hba-ijkrun-staging.py
./scripts/run-hba-ijkrun-staging.py "https://staging…" "$CE_RESET_TOKEN" \
  docs/hba-ijkrun-nulmeting-v1.10.6.json
```

Deploy naar staging vereist (JSON in `web/data/` mee). Geen deploy = geen run op staging.

## Klaar als

- [x] Runner + endpoint + script.
- [x] Nulmeting staging (her-run 3) — `docs/hba-ijkrun-nulmeting-v1.10.6.json` + **`docs/hba-ijkrun-nulmeting-v1.10.6-samenvatting.md`**.
- [ ] V01 handmatig of aparte CE-03-run.
- [ ] Valkuil-gate 100% + compressie-route (go-live).
