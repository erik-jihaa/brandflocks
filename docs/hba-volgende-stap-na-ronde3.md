# Volgende stap na HBA ronde 3 (briefing uitgevoerd + staging)

**Datum:** 10 oktober 2026  
**Staging:** https://staging.igisnext.com  
**Testlogin:** `test-tenant@igisnext.com` / `StagingHba2026!` (Brandpunt, Stelwerk, Duinroos)

---

## 1. Pakket voor Rogier — downloaden

| Wat | Waar |
| --- | --- |
| **Zip (alles-in-één)** | `docs/rogier-hba-testmerken-pakket-2026-10-10.zip` |
| **Map** | `docs/rogier-hba-testmerken-pakket/` |
| **Vernieuwen** | `./scripts/build-rogier-hba-pakket.sh` |

**Mail Rogier (kort):**

1. Bijlage zip (of Dropbox-link naar `docs/rogier-hba-testmerken-pakket/`).
2. Start met **`hba-kalibratie-beslispunten-roger-v1.8.md`** — zes keuzes (A/B/C) vóór prompt v1.8.
3. Daarna **`hba-testmerken-ronde3-rapport-roger.md`** + **`stabiliteit-v1.7.md`**.
4. Staging preview: URL’s in **`hba-r5-klantpreview-urls-staging.md`** (`?hbaKlant=1`, ingelogd).
5. Klanttaal: **`a6-klanttaal-inventaris-voorstel.md`** — akkoord per regel vóór UI-wijziging.

**Nog niet naar Rogier sturen (Erik lokaal):** R5-screenshots (17 PNG’s) zodra gemaakt — optioneel map `docs/rogier-hba-testmerken-pakket/screenshots-r5/`.

---

## 2. Blokkade: v1.8 kalibratie

Zolang Rogier niet antwoordt op de **6 beslispunten**, geen prompt-tuning in de repo.

| Na akkoord Rogier | Actie |
| --- | --- |
| Prompt v1.8 | Fragmenten + matrix-regels implementeren |
| Opnieuw meten | `./scripts/run-hba-testmerken-matrix.sh` (5×17) |
| Groen | Hoofdoordeel-klantweergave productieklaar (feature flag) |

---

## 3. Parallel (Erik / geen Rogier-blocker)

| # | Taak | Hoe |
| --- | --- | --- |
| A | **R0 CE-04 feedback vastleggen** | `./scripts/run-hba-r0-ce04-post3-staging.sh 60` → tabel in `hba-testmerken-ronde3-r0.md` |
| B | **R5 screenshots** | 17 URL’s uit `hba-r5-klantpreview-urls-staging.md` |
| C | **A3 verleidingstest** | Black Friday fastlane hertest → CE-03 → score + zelfcontrole (3 merken) |
| D | **A2 bewijs** | Screenshots Merkarchitectuur (3 testmerken) |
| E | **Ongecommit werk** | Aparte commits: testset, artikel-UI, publish — buiten Rogier-pakket |

---

## 4. Later (product / kalibratiemodule)

- **A6** UI-teksten na Rogier-akkoord.
- **A8** contentrollen samenvoegen — expliciet **niet** zonder Rogier.
- **A9** alertniveau — feature nog niet gebouwd.
- **T4** wizard zwaardere bewijslast (merk = branche-archetype) — ontwerp, geen code.
- **Productie** — geen HBA-hoofdoordeel live tot Rogier + groene 5×17.

---

## 5. Status briefing (samenvatting)

| Deel | Status |
| --- | --- |
| R1–R4, R6, 5×17 | ✅ |
| R5 UI | ✅ · screenshots ⚠️ |
| R0 CE-04 trigger | ✅ staging · feedback ⚠️ |
| A1–A5, A7, D1–D2, D4 | ✅ op staging |
| A3 hertest | ⚠️ |
| A6/A8/A9/D3 | 📋 document |
| Klaar-criterium | ❌ Stelwerk post 2 → Rogier |

Volledige checklist: `docs/hba-ronde3-afronding-briefing.md`.
