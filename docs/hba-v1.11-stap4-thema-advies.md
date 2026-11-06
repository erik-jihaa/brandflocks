# HBA ronde 6 — stap 4: advies over het thema zichtbaar

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 4  
Branch: `hba-ronde-6-pilotklaar`

## Probleem

Op Onderzoek stond boven het thema de vraag *Past dit onderwerp bij je merk?* zonder antwoord. Het oordeel zat wel in `hba_theme_verdicts`, maar Onderzoek laadde alleen research-oordelen per bron. Voor Brandpunt is “Black Friday-actie” precies het thema dat met de regel botst.

## Wat erin zit

- Thema-prompt: bij regelbotsing `regel_botsing`, letterlijke `regel`, `invalshoek` (schrijven waarom het merk dit niet doet). Label `botst` wordt intern `verwatert`.
- Toelichting wordt samengesteld tot één leesbare tekst: waarom + “Dit thema botst met je regel: …” + invalshoek.
- UI: onder de vraag het oordeel **versterkt / neutraal / botst** (`ThemeFitAdvice`). Zelfde op Thema’s bij voorstellen, en op Onderzoek voor het actieve thema (`GET/POST /hba/theme-verdicts?theme_id=`).

## Klaar-criteria

| Eis | Lokaal |
| --- | --- |
| Onder de vraag: oordeel + één zin waarom | Gebouwd |
| Bij regelbotsing: regel letterlijk + invalshoek | Prompt + compose (`scripts/test-hba-stap4-thema-advies-v1.11.php`) |
| Zelfde op Thema’s | `ThemeFitAdvice` per voorstel |
| Screenshots Onderzoek + Thema’s (na genereer) | `docs/hba-v1.11-stap4-screenshots-staging-2026-10-18/` (18 okt 2026) |

Geen productie. Bestaande cached oordelen krijgen regel + invalshoek pas na een nieuwe themarun.
