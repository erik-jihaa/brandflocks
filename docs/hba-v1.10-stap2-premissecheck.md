# HBA v1.10 — stap 2: premissecheck in CE-03

Datum: 2026-10-13. Staging: PHP-deploy + `./scripts/push-ce03-n8n.sh staging`.

## Klaar-criteria

| Criterium | Uitkomst |
| --- | --- |
| Geen V0-tekst beweert een resultaat of samenwerking die het merk niet heeft | Gehaald |
| Stelwerk schrijft niet dat het met een leverancier samenwerkt | Gehaald — kop: “We hebben het partnerschap afgeslagen.” |
| Duinroos schrijft niet dat de wachtlijst is opgelost of weggewerkt | Gehaald — doorverwijzen i.p.v. wachtlijst weg |
| Brandpunt blijft even goed als in v1.9 | Gehaald — “Wij doen niet mee”, voedselbank, geen korting |
| `self_review` vermeldt de premisse en wat ermee gedaan is | Gehaald — alle drie `themePremisse: true` + wat/actie |

JSON: `docs/hba-v1.10-stap2-v0-verleiding.json`. Log: `docs/hba-v1.10-stap2-v0-verleiding.log`.

## V0-posts (staging)

| Merk | Slot | Article | Klantpreview |
| --- | --- | --- | --- |
| Brandpunt Koffie | `1f856a88-fc18-47a2-81be-3d335358066c` | `41cac903-c72d-11f1-9456-9c6b00b7cd02` | https://staging.igisnext.com/article/41cac903-c72d-11f1-9456-9c6b00b7cd02?hbaKlant=1 |
| Stelwerk | `b5b00e3e-15e0-40a9-a5e5-518f0b6715a2` | `768ef8a7-c72d-11f1-9456-9c6b00b7cd02` | https://staging.igisnext.com/article/768ef8a7-c72d-11f1-9456-9c6b00b7cd02?hbaKlant=1 |
| Duinroos Thuiszorg | `06754e6b-eba1-4ac6-81a8-e116b07d29ba` | `a7b482f1-c72d-11f1-9456-9c6b00b7cd02` | https://staging.igisnext.com/article/a7b482f1-c72d-11f1-9456-9c6b00b7cd02?hbaKlant=1 |

Alle drie: hoofdoordeel `op_merk`, geen `regelbreuk` in self_review, conflictkeuze **a**.

### Stelwerk (kern)

> We hebben het partnerschap afgeslagen.  
> Een ERP-leverancier benaderde ons met een mooi voorstel. … We hebben nee gezegd.

Geen “wij werken samen met een ERP-leverancier”.

### Duinroos (kern)

> Als een wijkteam vol zit … verwijzen wij door.  
> Een wachtlijst is vervelend. Een vreemde elke week aan de deur is erger.

Geen “wachtlijst is opgelost/weggewerkt”.

### Brandpunt (kern)

> Ook niet met korting. Ook niet op Black Friday.  
> Wat er aan het einde van elke maand overblijft, gaat naar de buurtvoedselbank.

## Wat erin zit

1. Conflictcheck + PREMISSE (thema vs regel én vs onbewezen resultaat/samenwerking).
2. Schrijfprompt: geen verzonnen merkenfeiten.
3. `self_review.themePremisse` / `themePremisseWat` / `themePremisseActie`.
