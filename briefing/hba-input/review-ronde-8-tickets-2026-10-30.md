# Ronde 8: tickets na de review van 30 oktober

Rogier Heijning, Hero Brands, 30 oktober 2026. Hoort bij `ronde-8-previewscope-2026-10-27.md` en `bouwaddendum-ronde-8-strategieen-campagnes-geo-2026-10-27.md`.

## Maatstaf

De maatstaf voor deze ronde is de **previewscope**, niet het volledige addendum. Maak alleen tickets voor wat in de previewscope staat (P1 tot en met P5).

De review bevestigt dat de planningsmotor klopt: kanaalverdeling, fase-aandelen van sjabloon A, één post per kanaal per dag, ankers op ongeveer een op de drie posts, en de posts in de agenda na bevestigen. Daar hoeft niets aan te veranderen.

## Tickets

**1. Check op het plan (P4). Hoogste prioriteit.**
- Een variant van `ce_hba_v2_run_theme_verdicts()`: alle geplande posts van de campagne als batch, met per post een oordeel en één gezamenlijk advies.
- Twee rekenregels in code: (a) balans merkbouw en activatie over de komende tien posts, (b) een signaal als het product of de actie een regel van het merk breekt.
- Toon de uitkomst bij stap 8, vóór "Bevestig", in klanttaal. Elk signaal heeft een voorstel en een knop om het te negeren.
- Neem ook de "ontbreekt"-uitkomst van de productpagina (P3) mee als signaal.
- Eigen prompt met een eigen versie. De scoring per post blijft bevroren.
- **Klaar als:** de lancering van Brandpunt (vier weken, LinkedIn 8, Instagram 12) een check toont, en dezelfde lancering met een korting op koffie die al weken ligt het signaal "Regel" geeft.

**2. Merkonderdeel per fase.**
- Voeg "waar je tegen bent" (`waar_je_tegen_bent`) toe als vijfde keuze.
- De fase stelt het onderdeel voor; het bureau mag het per fase wijzigen.
- **Klaar als:** alle vijf onderdelen kiesbaar zijn en een gewijzigd onderdeel in de opdracht per post terechtkomt.

**3. Fases verschuiven (P2).**
- Fases mogen verschuiven en wegvallen. Weglaten werkt al; verschuiven nog niet. Fases toevoegen hoeft nu niet.
- **Klaar als:** het bureau de volgorde of het aandeel van een fase kan aanpassen en de posts daarna opnieuw verdeeld worden.

**4. Productpagina als bron (P3).**
- Test de extractie met een echte productpagina van Brandpunt. De test met een nep-URL gaf "HTTP 0".
- **Klaar als:** de uitvoer `wat`, `anders`, `bewijs` en `ontbreekt` gevuld is met citaten van de pagina, en `ontbreekt` als tip en als signaal in de check verschijnt.

## Nog testen

- Sjabloon B: een serie van zes afleveringen op LinkedIn, elke dinsdag.

## Niet in deze ronde

Geen tickets voor deze punten; ze staan in de previewscope bij "na de previews":
- de feitenbank en de volledige vragenbank (de ankers komen nu terecht uit de GEO-vragen van Vindbaarheid, zoals P5 vraagt);
- fases toevoegen;
- meer kanalen dan LinkedIn en Instagram, en het veld `min_dagen_tussen`;
- de doelpagina en de checks op te weinig en te veel ankers;
- sjablonen C tot en met F, het campagneverslag, GEO voor en na.

## Issues

Werk de bestaande GitHub-issues bij met deze vier tickets, of maak nieuwe als ze er niet onder passen. Zet bij elk ticket het "klaar als"-criterium.
