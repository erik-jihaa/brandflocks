# Rooktest Brandflocks (handmatig op staging)

Uitvoeren na deploy van de fork-op staging. Elke regel: uitvoeren, verwacht gedrag, vink af.

| # | Stap | Verwacht | OK |
|---|------|----------|-----|
| 1 | Inloggen en van account wisselen | Twee tenants bereikbaar zonder fout | ☐ |
| 2 | Bron toevoegen | Bron verschijnt in lijst, opslaan slaagt | ☐ |
| 3 | Thema → onderzoek → artikel schrijven | Flow compleet, artikel opgeslagen | ☐ |
| 4 | Merkarchitectuur opslaan | Opslaan slaagt; architectuurcheck toont signalen (blokkeert niet) | ☐ |
| 5 | Campagne maken en bevestigen | Preview + bevestiging; slots/thema’s zichtbaar | ☐ |
| 6 | Agenda en Rapport | Data zichtbaar voor testperiode | ☐ |
| 7 | Publiceren naar WordPress (test) | Testpublicatie slaagt of duidelijke fout zonder crash | ☐ |
| 8 | Platformbeheer: tenant aanmaken | Nieuwe tenant aan te maken | ☐ |

**Na elke opschoonronde (A–F):** ook `./scripts/test-all.sh` groen (geautomatiseerd vangnet).
