# HBA ronde 6 — stap 5: klanttaal (A6)

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 5  
Branch: `hba-ronde-6-pilotklaar`

## Scope

Klant-zichtbare copy in de frontend: geen rest-Engels (behalve gangbare vaktermen community/service/engagement), geen interne HBA-versies in rapporttitels, merkarchetype niet meer als label op Merkarchitectuur.

## Belangrijkste wijzigingen

| Scherm | Wijziging |
| --- | --- |
| Overzicht | Toets actief → Merkcontrole aan |
| Merkarchitectuur | Geen “Je persoonlijkheid: …”; Save/Rewrite → Opslaan / Opnieuw invullen; kernformulering → Hoe jullie werken; upload zonder “HBA” |
| Merkelementen | Schrijfstijl per doelgroep; Verwijder → Verwijderen; toon alleen op Merkarchitectuur |
| Thema's | Eigen thema, Titel, bron-placeholder NL |
| Onderzoek | Review → Beoordelen; bronlink = sitenaam i.p.v. volledige URL |
| Artikel / Terugkoppeling | Upload image → Afbeelding toevoegen; Re-edit → Opnieuw bewerken; Posttitel |
| Vindbaarheid | Vragen aan AI/Google; Opslaan / Ongedaan maken |
| Planning | NL labels; ritme-rollen als Positionering / Bewijs / Kennis delen |
| Rapport | Titel zonder (HBA v…); engagementverschil vanaf 10 posts + uitleg |
| Agenda | nl-NL datums; kies een thema |

## Klaar als

Nieuwe screenshots van alle pilot-schermen (zie briefing) + terugkoppeling met open CE-04-regels (knop Verwijderen).

**Screenshots (staging, 18 okt 2026):** `docs/a6-screenshots-staging-2026-10-18/` (+ `00-LEESMIJ.md`).

**Deploy:** stap 5 bundle `index-NtJQECSq.js`, daarna hotfix CE-04-kaarten (`index-DrAF8zwm.js`) — `mapApiItem` zet `currentLine` zodat API-feedback naast de tekst verschijnt.

**Terugkoppeling shot:** slot `8c576c9a-49bb-45b4-81e2-4ec8a41c31b0` (HBA-test post 3); CE-04 `./scripts/run-hba-r0-ce04-post3-staging.sh 90`.

Geen productie.
