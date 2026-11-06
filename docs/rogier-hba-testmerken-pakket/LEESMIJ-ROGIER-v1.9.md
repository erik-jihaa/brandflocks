# HBA ronde 4 — pakket voor Rogier (prompt v1.9)

**Datum:** 13 oktober 2026  
**Omgeving:** staging (`https://staging.igisnext.com/`) — **niet** productie  
**Opdracht:** `hba-ronde-4-v1.9.md` (kopie in dit pakket)

---

## Start hier

1. **`hba-v1.9-verslag-roger.md`** — hele ronde in gewone taal (stappen 1–5, V0-teksten, open punten).
2. **`stabiliteit-v1.9.md`** + **`stabiliteit-v1.9-5runs.json`** — 5×17 op **v1.9** (13 okt, na testset-stap 4).
3. **`hba-v0-testresultaat.md`** + **`.json`** — V0 leidingen + verleiding (stap 1–2).
4. **`hba-v0-klantpreview-urls-staging.md`** — 3 V0-posts (`?hbaKlant=1`).
5. **`hba-r5-klantpreview-urls-staging.md`** — 17 nulmeting-posts (herimport okt 2026).

---

## Definities (v1.9)

- `hba-regelbreuk-definitie.txt`
- `hba-flags-definitie.txt` (incl. `constraint_breach_uitleg`)
- `hba-hoofdoordeel-definitie.txt`

---

## Kern cijfers v1.9 (5×17)

| Metric | Waarde |
| --- | ---: |
| Stable goed (5/5) | **16 / 17** |
| Review | **1** (Brandpunt post 6 — kalibratie) |
| Instabiel / fout | **0** |
| Valse regelbreuk (verificatie) | **0** |
| `hoofdoordeel_gecorrigeerd` | **0%** |
| Post 2 Stelwerk + post 6 Duinroos | **5/5** (was 3/5 in v1.8.1) |

**Na meting:** functionele-flag-telling negeert CTA-zin na merkdeel (Stelwerk post 2); live matrix toont **0** functionele hits. De JSON `stabiliteit-v1.9-5runs.json` is van vóór die PHP-fix (3 hits in rapport) — zie verslag §6.

---

## Vergelijk v1.8.1

- `stabiliteit-v1.8.1.md` + `stabiliteit-v1.8.1-5runs.json` (12 okt)
- `hba-v1.8-verslag-roger.md`

---

## Testset

- `Brandpunt_Koffie.json`, `Stelwerk.json`, `Duinroos_Thuiszorg.json` — incl. koffie-markthygiëne, post 4 niet-ambigu, Duinroos post 6 `tegenkracht`.

---

Vernieuwen: `./scripts/build-rogier-hba-pakket.sh` → zip `docs/rogier-hba-testmerken-pakket-YYYY-MM-DD.zip`
