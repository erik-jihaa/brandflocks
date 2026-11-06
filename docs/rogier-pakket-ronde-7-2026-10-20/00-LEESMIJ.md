# Rogier-pakket ronde 7 — stopbericht, v1.12, A6 ronde 2, v1.13-status

**Datum:** 20 oktober 2026  
**Branch:** `hba-ronde-6-pilotklaar`  
**Briefing:** `briefing/cursor-ronde-7-v113-en-a6-ronde-2.md` (spoor 3)

Erik levert de **artefacten**; begeleidende tekst naar Rogier schrijft Erik.

## Pilotbesluit (kort)

| Meting | Uitslag | Pilot? |
| --- | --- | --- |
| **v1.12 — 5×19 poging 2/2** | **13/19** stable goed (doel ≥16) | **Nee** (afgesproken drempel) |
| **v1.13 — 5×19 poging 3** | **19/19** stable; vier categorietaal-posts **0× tegenkracht** (22 okt) | **Ja** (drempel gehaald — besluit Erik/Rogier) |
| **A6 ronde 2** | A + B + C bewijs staging 20 okt | Parallel klaar (geen scoring) |

Rogier **20 okt:** één **gerichte reparatie** (`foe_vervaging` → v1.13) vóór pilot. **22 okt:** volledige 5×19 op staging — zie `08-v1.13-stap5-5x19-eindmeting.md`, `09-ruwe-json-v1.13-5runs.json`, `10-tegenkracht-diagnose-replay-v1.13.md`.

## Bestanden (checklist spoor 3)

| # | Gevraagd | Bestand |
| --- | --- | --- |
| 1 | Tabel per post, 5 runs, flags, verwachting | **`01-tabel-per-post-19x5-v1.12.md`** (+ samenvatting `02-stabiliteit-samenvatting-v1.12.md`) |
| 2 | Ruwe JSON v1.12 + logging | **`03-ruwe-json-v1.12-5runs.json`** — velden `merkbewering` / `kern_categorietaal` per run via **replay-backfill** (geen raw_llm in export); zie `loggingBackfill` in JSON |
| 3 | Besluit → bouw v1.12 | **`04-besluit-naar-bouw-v1.12.md`** |
| 4 | A6 stap A-B en C (ontbraken in vorige zip) | **`a6/hba-a6-ronde2-stap-A-B.md`**, **`a6/hba-a6-ronde2-stap-C-hashtag-bewijs.md`**, **`a6/hba-spoor2-a6-ronde2-eindstatus.md`** |
| 5 | Screenshots 20 okt | **`a6/screenshots/`** (agenda lijst/kalender, CE-04 JSON; screenshot CE-04 op **nieuw** slot — zie `a6/screenshots-LEESMIJ.md`) |

## Overige bijlagen

- `05-eindmeting-v1.12-poging-2.md` — stopbericht eindmeting  
- `06-tegenkracht-diagnose-replay-v1.12.md` — offline replay met/zonder `&& !kern_categorietaal`  
- `07-neutrale-posts-v1.12.md`  
- `08-v1.13-stap5-5x19-eindmeting.md`, `09-ruwe-json-v1.13-5runs.json`, `10-tegenkracht-diagnose-replay-v1.13.md`, `11-stabiliteit-tabel-v1.13.md`, **`12-rapportage-v113-eindmeting-2026-10-22.md`** (tekst voor Rogier)  
- `bijlage-flagtelling-implementation-kit.md`, `bijlage-valkuil-prioriteit-go-live.md`

## Oudere map

Volledige stopbericht-collectie (19 okt): `docs/rogier-stopbericht-ronde-6-2026-10-19/` — inhoud grotendeels **hergebruikt** hier, plus A6 ronde 2 en v1.13-status.

## Zip naar Rogier — **klaar (21 okt, na stap 3)**

Reactie Rogier 21 okt: één complete zip na stap 3 (kalender + feedback @ 1280px) + verse poll-JSON.

| Bestand | Inhoud |
| --- | --- |
| `a6/12-agenda-kalender.png` (+ `a6/screenshots/`) | Echte kalender-tab (niet Terugkoppeling/Laden…) |
| `a6/07-feedback-terugkoppeling.png` (+ `a6/screenshots/`) | 1280px-viewport, volledige opmerkingenkolom |
| `a6/07-ce04-post3-poll.json` | **`regelFeedbackOk` ×3**; Duinroos = vier-zorgverlenersregel (commit **`1dc37bb`**) |

**Zip:** `docs/rogier-pakket-ronde-7-2026-10-20.zip` (herbouwd **23 okt** — pilotstart `13`–`16`, A2 week 43, A3-opvolging).

| Pilotstart (22–23 okt) | Bestand |
| --- | --- |
| Rapportage pilotstart | `13-rapportage-pilotstart-2026-10-22.md` |
| A2 voorbeeld week 43 | `14-a2-weekoverzicht-voorbeeld-week43.md` |
| Opvolging reactie Rogier | `15-rapportage-pilotstart-opvolging-2026-10-23.md` |
| Status: input verwerkt? | `16-rapportage-pilotstart-status-2026-10-23.md` |

## Niet in dit pakket

De geplande Fable-slotrun (vier condities uit het stopbericht) is nog niet gedraaid — bewust uitgesteld. Dit pakket bevat uitsluitend de terugkoppeling op v1.12/v1.13-status en A6 ronde 2.
