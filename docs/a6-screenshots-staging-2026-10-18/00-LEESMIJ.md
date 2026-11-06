# A6-screenshots voor Rogier (staging) — stap 5 klanttaal

**Datum:** 18 oktober 2026  
**Omgeving:** https://staging.igisnext.com/  
**Tenant:** CF_Staging / Brandpunt Koffie  
**Branch:** `hba-ronde-6-pilotklaar`  
**Frontend-bundle:** `index-DrAF8zwm.js` (na CE-04-feedbackfix + stap 5 deploy)

Lijst = `docs/hba-v1.10-stap4-klanttaal.md` (11 schermen).  
Buildnotitie stap 5: `docs/hba-v1.11-stap5-klanttaal.md`.

## Bestanden

| # | Bestand | Scherm | Route |
| --- | --- | --- | --- |
| 1 | `01-overzicht.png` | Overzicht | `/` |
| 2 | `02-merkarchitectuur.png` | Merkarchitectuur | `/brandarchitecture` |
| 3 | `03-merkelementen.png` | Merkelementen | `/styleguide` |
| 4 | `04-themas.png` | Thema's (week 42) | `/theme` |
| 5 | `05-onderzoek.png` | Onderzoek (week 41, Black Friday) | `/research` |
| 6 | `06-artikel-meta.png` | Artikel + zijpaneel | `/article/d733223a-7964-4324-9883-dcd4b1809006` |
| 7 | `07-feedback-terugkoppeling.png` | Terugkoppeling + CE-04 | `/article/8c576c9a-49bb-45b4-81e2-4ec8a41c31b0/feedback` |
| 8 | `08-vindbaarheid.png` | Vindbaarheid | `/search` |
| 9 | `09-planning.png` | Planning | `/planning` |
| 10 | `10-rapport.png` | Rapport | `/report` |
| 11 | `11-agenda-signalen.png` | Agenda (lijstweergave) | `/agenda` → Lijst |

Volledige pagina-screenshots (scrollbaar in de PNG).

## Rogier-check (A6 / stap 5)

- **Overzicht (`01`):** Merkcontrole aan; geen archetype-label.
- **Merkarchitectuur (`02`):** Hoe jullie werken; Opslaan / Opnieuw invullen; geen “Je persoonlijkheid: …”; upload zonder HBA.
- **Merkelementen (`03`):** Schrijfstijl per doelgroep; knop **Verwijderen**.
- **Thema's / Onderzoek (`04`, `05`):** NL placeholders; advies *Past dit onderwerp bij je merk?*; Onderzoek **Beoordelen** + bron **NOS** (sitenaam).
- **Artikel (`06`):** Afbeelding toevoegen, Opnieuw bewerken, Opslaan; geen archetype in meta.
- **Terugkoppeling (`07`):** open CE-04-regel met knop **Verwijderen** (slot HBA-test post 3; CE-04 via `./scripts/run-hba-r0-ce04-post3-staging.sh`).
- **Vindbaarheid / Planning (`08`, `09`):** NL knoppen Opslaan / Ongedaan maken; geen Content Engine in kop.
- **Rapport (`10`):** titelveld zonder `(HBA v…)`; gating 6/10 posts zichtbaar.
- **Agenda (`11`):** nl copy “kies een thema” waar van toepassing; **kopdatum kan nog Engels** (“18 October 2026”) — rest nl-NL.

## Bekende restpunten (niet blokkerend voor “ronde klaar”)

- **Sidebar meta (Tone / Image ratio / Platform)** op artikel/terugkoppeling: nog EN labels op sommige radio’s (interne waarden).
- **Rapport:** oud opgeslagen rapport in lijst kan nog `(HBA v1.7)` in titel hebben; nieuw gegenereerd veld is schoon.
- **Feedback (`07`):** alleen één regel met **Verwijderen** in beeld (meerdere open regels op dezelfde tekstregel).

## Naar Rogier

Zip deze map + `docs/hba-v1.11-stap5-klanttaal.md`.
