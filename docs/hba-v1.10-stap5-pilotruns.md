# HBA v1.10 — stap 5: pilotversie + runs

Datum: 2026-10-17  
Bron: `briefing/cursor-bouwvolgorde-besluiten-16-oktober.md` § stap 5; besluiten §7.

## Pilotversie

| | |
| --- | --- |
| **Label** | **`v1.10.8`** (`CE_HBA_PROMPT_VERSION`) |
| **Model scoring** | `claude-sonnet-4-6` (ongewijzigd) |
| **Waarom niet v1.10.6/7** | Pilot vereist §1 (poort), §5.3 (hoofdoordeel), §6 (klanttaal) — die zitten in stap 1–4 |

**In deze freeze (code op staging ná deploy):**

- Stap 0: ijkset v0.3 + meetlat-status
- Stap 1: architectuurpoort (geen harde gate; meetlat apart)
- Stap 2: hoofdoordeel-volgorde + Positiebreuk; prompt sync PHP
- Stap 3: verbodslijst architectuur-output
- Stap 4: klanttaal §6 + A6

**Bevriezen tijdens de pilot:** geen prompt-/poort-wijzigingen tot afgesproken moment. Verbeteringen verzamelen, niet live doorzetten.

## Twee metingen (gescheiden)

### A — 5×19 op pilotversie

```bash
./scripts/run-hba-testmerken-matrix.sh --runs 5
```

Output (automatisch): `docs/hba-testmerken-stability-v1.10.8-5runs.json` + stabiliteit-md.

**Stap-2 klaar-criteria meenemen in rapport:**

- T10 → `categorietaal`
- T13 en Duinroos post 7 → zelfde oordeel
- `hoofdoordeel_gecorrigeerd` ≤ 5%
- Brandpunt post 1 = controlepunt t.o.v. v1.10.6

### B — v0.3-ijkrun (nieuwe nulmeting)

Niet vergelijken met v0.2-nulmeting alsof het dezelfde meting is (andere prompt, andere poort).

```bash
# na deploy v1.10.8 — BASE/TOKEN uit .env.staging (zie read_env in run-hba-testmerken-matrix.sh)
# Niet: source .env.staging (parse error regel 57)
./scripts/run-hba-ijkrun-staging.py "$BASE" "$TOKEN" \
  docs/hba-ijkrun-nulmeting-v1.10.8.json \
  2>&1 | tee docs/hba-ijkrun-nulmeting-v1.10.8.log
```

**Judge architectuur — drie checks (niet één):**

1. status ↔ `verwacht_meetlat` (`judgment_meetlat` / `meetlat_status`)
2. inhoudelijk oordeel (geen “alleen melding”)
3. geen content gegenereerd bij meetlat

**Valkuilscore = drie getallen:** goed · fout · niet gedraaid.  
Vijf valkuilgevallen draaien niet: **A15, V01, C02, C03, C06** — niet wegmoffelen.

## Status

| Deel | Status |
| --- | --- |
| Prompt `v1.10.8` in repo | klaar (lokaal) |
| Staging deploy + freeze | wacht op 「ja staging en commit」 |
| 5×19 | ✅ `docs/hba-testmerken-stability-v1.10.8-5runs.json` |
| v0.3-ijkrun | ✅ `docs/hba-ijkrun-nulmeting-v1.10.8.json` |
| Samenvatting | `docs/hba-ijkrun-nulmeting-v1.10.8-samenvatting.md` |

## Klaar als

- [x] `v1.10.8` op staging
- [x] 5×19 afgerond + JSON
- [x] v0.3-ijkrun afgerond + samenvatting (valkuil goed/fout/niet-gedraaid)
- [x] Versie bevroren voor pilot (geen prompt-wijzigingen tot afspraak)
