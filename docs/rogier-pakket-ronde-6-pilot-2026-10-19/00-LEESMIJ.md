# HBA ronde 6 — pakket voor Rogier, 19 oktober 2026

**Omgeving:** staging (`staging.igisnext.com`) — **geen productie**  
**Branch:** `hba-ronde-6-pilotklaar` (gepusht naar GitHub)  
**Pilotversie scoring:** **v1.11** · git-anker stap 1–5: **`b9c8e03`**  
**Model:** `claude-sonnet-4-6`  
**Opdracht:** `briefing/hba-input/hba-ronde-6-pilotklaar.md` (stappen 1–9)

---

## Wat we van je vragen (kort)

1. **Besluit §3 — neutraal vs categorietaal** (blokkeert pilot-criterium 5×19). Zie `02-terugkoppeling-besluiten.md`.
2. **Besluit §2 — blijft `geen_bron` hard blokkeren?** Zelfde document.
3. **Akkoord §4 — klanttaal A6** op basis van `screenshots-a6/` (18 okt, incl. CE-04 terugkoppeling).
4. **Mag de pilot starten** zodra §3–§4 helder zijn? De bouw ronde 6 is af; eindmeting haalt **12/19** stable (doel ≥16). Stroomtest: **`02-terugkoppeling-besluiten.md` §7–§8**.

---

## Leesvolgorde

| # | Map / bestand | Waarvoor |
| --- | --- | --- |
| 01 | `01-pilotversie-eindmeting.md` | Pilot freeze, tabel metingen, klaar-criteria ✅/❌ |
| 02 | `02-terugkoppeling-besluiten.md` | **Jouw besluiten** + toelichting stap 2/3 controles |
| 03 | `03-bouwrapport-2026-10-17.md` | Waarom ronde 6 startte (v1.10.8-terugval) |
| 04 | `04-stabiliteit-v1.11.md` + `04-stabiliteit-v1.11-5runs.json` | Volledige 5×19 eindmeting |
| 05 | `05-ijkrun-uitsplitsing.md` + `05-ijkrun-samenvatting.md` + `05-ijkrun-v1.11.json` | Ijkrun v1.11 per as |
| 06 | `06-stroomtest-brandpunt.md` + `06-stroomtest-screenshots/` + `06-klanttaal-stap5.md` | Stap 8 stroomtest + stap 5 A6-toelichting |
| 07 | `07-ce04-post3-v1.11.json` + `07-v0-eindmeting.json` | CE-04 regel letterlijk + V0-verleiding |
| 08 | `08-stappen-1-9-samenvatting/` | Per-stap docs (v1.11) |
| 09 | `09-ijkset-koppeling-v0.3.md` | Enige bron besluiten 7.1–7.7 |
| 10 | `10-opdracht-ronde-6.md` | Jouw opdracht aan Cursor |
| — | `screenshots-a6/` | Klanttaal pilot-schermen (18 okt) |

---

## Wat is gebouwd (stap 1–5 = pilotcode)

| Stap | In het kort |
| --- | --- |
| 1 | Hoofdoordeel **alleen uit flags** in PHP; prompt v1.11; `hoofdoordeel_gecorrigeerd` vervallen |
| 2 + 2a | Hint/adviezen: regel letterlijk; **citaatcontrole** op hint (geen verzonnen quotes) |
| 3 + 3a | Geen verzonnen merkfeiten in CE-03; `merk_beweringen` + aangescherpte definitie |
| 4 | Thema-oordeel (versterkt/neutraal/botst) op Onderzoek/Thema’s |
| 5 | Klanttaal A6 in pilot-UI |

Stap 6–8: ijkrun-uitsplitsing, wizardvelden in runner, stroomtest Brandpunt — **geen** scoringwijziging.

---

## Eindmeting in één oogopslag

| Meting | Resultaat |
| --- | --- |
| **5×19 v1.11** | **12/19** stable 5/5 · 1 instabiel · 7 posts 0/5 consistent fout |
| **Hoofdoordeel uit flags** | **100%** (0/95 gecorrigeerd) |
| **BC-spreiding** | ≤ 1 op alle posts |
| **CE-04 post 3** | 3/3 merken: regel + Verwijderen |
| **Ijkrun v1.11** | 26 fout · 6 goed · 5 review · 9 niet gedraaid |
| **Stroomtest** | Onboarding 1 geblokkeerd / 2 actief; 25 posts gescoord; afwijkingen t.o.v. verwachting in stroomtest-doc |

**Kernblokker:** posts *Neutraal / grensgeval* (alle drie merken) scoren 0/5 **categorietaal** — methodiek, niet run-ruis. Uitleg in bestand 02.

---

## Wat we bewust niet opnieuw draaien

Geen tweede 5×19 vóór jouw besluit bij §3 (zeven posts 0/5 zonder spreiding). Kleine staging-defecten (V0 score 404, één hint-timeout) zijn weergave/timing — geen nieuwe prompt-run.

---

## Contact / repo

Erik — volledige repo: branch `hba-ronde-6-pilotklaar`. Opnieuw zip bouwen: `./scripts/build-rogier-pakket-ronde-6.sh`.
