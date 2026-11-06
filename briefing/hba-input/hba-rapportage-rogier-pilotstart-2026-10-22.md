# Rapportage aan Rogier — pilotstart v1.13 en eerste uitvoering

**Datum:** 22 oktober 2026 (avond)  
**Branch:** `hba-ronde-6-pilotklaar` (HEAD `54b52fa`, gepusht)  
**Reactie op:** `hba-pilotbesluit-v1.13-2026-10-22.md`, `hba-laatste-puntjes-pilotstart-2026-10-22.md`, `briefing/cursor-pilotstart-v1.13-uitvoering.md`

---

Rogier,

Dit is de stand na jouw **pilotbesluit op v1.13** en de eerste uitvoeringsstappen uit je lijst “laatste puntjes”. Scoring (prompt v1.13, resolver, flags, model **claude-sonnet-4-6**) is **niet** gewijzigd sinds de geslaagde 5×19; na elke stap controleren we de offline replay van de 95 v1.13-scores → nog steeds **19/19**.

## 1. Pilot — waar we staan

| Onderwerp | Status |
| --- | --- |
| **Pilotbesluit v1.13** | Jij bevestigde start op v1.13 (22 okt); bevriezing en wekelijkse controle zijn leidend |
| **v1.13 eindmeting 5×19** | **19/19** stable, vier categorietaal-posts **0× tegenkracht**, merkbijdrage-spreiding ≤ 1 — zie eerdere rapportage + zip ronde 7 (`08`–`12`) |
| **Trede 2** | `&& !kern_categorietaal` blijft **weg**; vastgelegd in code (A4, commit `85b448d`) |
| **A6 ronde 2** | Ongewijzigd akkoord (21 okt); in zip |
| **Productie** | Geen deploys; alles **staging** |

## 2. Uitvoering “laatste puntjes” (A)

| Punt | Status | Bewijs / opmerking |
| --- | --- | --- |
| **A4 — Trede 2 vastleggen** | **Klaar** | Comment bij trede 2 in `ce_hba_resolve_hoofdoordeel`; geen logicawijziging |
| **A1 — Geen categorietaal-waarschuwing bij neutraal** | **Code klaar, staging live** | UI filtert `category_fallback` bij hoofdoordeel `neutraal`; optioneel neutraal advies in zijpaneel (`4090651`). **Screenshots** drie neutrale testposts (ontkalk / vacature / webinar) nog te maken na login staging — URLs in `docs/pilotstart-a1-neutrale-posts-2026-10-22/00-LEESMIJ.md` |
| **A2 — Wekelijks pilotoverzicht** | **Klaar** | Admin-API + script; **voorbeeld week 43** op drie testmerken: `docs/pilotstart-a2-weekly-report-voorbeeld-2026-10-22.md`. Aanroep: `./scripts/run-hba-pilot-weekly-report.sh` of `GET /api/admin/hba-pilot-weekly-report?format=md` |
| **A3 — Melding betwiste Positiebreuk/regelbreuk** | **Nog niet gebouwd** | UI heeft alleen **Negeer** op positioneringshints (`genegeerd=1`); geen aparte actie “onterecht”. Voorstel: A3 koppelen aan **Negeer** + score `tegenkracht`/`regelbreuk` — bevestig jij dat, of wil je eerst een expliciete “onterecht”-knop? |

## 3. Nog open (bewust)

| Onderwerp | Wanneer |
| --- | --- |
| **B1 — Flagtelling Implementation Kit** | Vóór **eerste klantrapport** (~10 posts); niet blokkerend voor pilot**start** |
| **C1–C3 — NL zijpaneel, ik-vorm, kalender 1280px** | Backlog tijdens pilot |
| **Fable (lezen + later aanpassen)** | **Pas op definitieve server (brandflocks.com)** — niet op staging; geheimen-scan repo wel nu (gitleaks) |
| **A1-screenshots** | Na staging-login Erik |

## 4. Technische check (ongewijzigd)

- Offline replay: `php scripts/hba-v1.13-tegenkracht-diagnose.php` → **live = replay nu = replay legacy = 19/19**
- Staging smoke na deploys: **6/6 pass**

## 5. Commits sinds v1.13-eindmeting-rapport (`01b2abb`)

| Commit | Inhoud |
| --- | --- |
| `85b448d` | A4 trede-2-comment |
| `4090651` | A1 neutrale posts UI |
| `364d257` | A1 URL-checklist |
| `54b52fa` | A2 wekelijks overzicht |

## 6. Bijlagen (repo)

| Bestand | Inhoud |
| --- | --- |
| `briefing/hba-input/hba-rapportage-rogier-v113-eindmeting-2026-10-22.md` | Eindmeting + pilotcriteria (ochtend) |
| `docs/rogier-pakket-ronde-7-2026-10-20.zip` | v1.13 JSON, replay, A6 (herzip 22 okt) |
| `docs/pilotstart-a2-weekly-report-voorbeeld-2026-10-22.md` | Weekoverzicht testmerken |

---

**Vraag:** akkoord om **A3** te baseren op **Negeer** (geen aparte “onterecht”-UI), of wil je eerst UX daar?

Erik kan de begeleidende mail aanvullen; cijfers en artefacten hierboven zijn actueel per `54b52fa`.
