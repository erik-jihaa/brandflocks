# Ronde 8: wat vóór de previews, wat erna

27 oktober 2026, Rogier. Reactie op `reactie-rogier-bouwaddendum-ronde-8-codereview.md`. Hoort bij `bouwaddendum-ronde-8-strategieen-campagnes-geo-2026-10-27.md`.

Dank voor het codeonderzoek. Daarmee kunnen we de omvang goed afbakenen.

## 1. Het besluit

We bouwen **niet het hele addendum** vóór de eerste previews. We bouwen een **previewversie**: net genoeg om een bureau te laten zien dat de app in campagnes denkt, en dat merk en GEO daarin meedoen. De rest komt na de previews, in de volgorde die de bureaus ons dan zelf aangeven.

**Waarom zo:**
- Een bureau beoordeelt de app op de eerste indruk. Eén thema per week is te mager om mee te beginnen.
- We willen de previews niet weken uitstellen.
- De previews zijn ook bedoeld om te leren. Laat de bureaus zeggen welke sjablonen ze het meest missen, in plaats van alles vooraf te bouwen.

## 2. Previewversie: vóór de eerste previews

| Onderdeel | Wat precies | Bouwt voort op |
|---|---|---|
| **P1. Campagne als eenheid** | Een campagne met naam, sjabloon, doel, periode en kanalen met een aantal posts per kanaal. De app maakt per kanaal N aparte postslots en zet ze in de agenda. | Nieuw. Geen kanaalvarianten nodig: elk slot is een gewone post met één platform. |
| **P2. Twee sjablonen** | A (lancering) en B (serie). Vaste fases met vaste aandelen, zoals in paragraaf 2 van het addendum. Fases mogen verschuiven of wegvallen, maar nog niet worden toegevoegd. | Nieuw, eigen structuur naast het weekritme. Het weekritme blijft zoals het is voor wie per week een thema kiest. |
| **P3. Productpagina als bron** | Bij sjabloon A geeft het bureau één productpagina op (URL-bron). Eén prompt haalt eruit: wat het is, wat het anders maakt, welk bewijs er staat. Dat gaat mee in de opdracht per post. | De bestaande bronnen (URL). De extractieprompt staat hieronder. |
| **P4. Check op het plan** | Een variant van `ce_hba_v2_run_theme_verdicts()`: de geplande posts van de campagne als batch, met per post een oordeel en één gezamenlijk advies. Plus twee rekenregels in code: balans merkbouw en activatie, en een signaal bij een product dat een regel breekt. | `ce_hba_v2_run_theme_verdicts()`, zoals je zelf al aangeeft. |
| **P5. GEO-vragen als anker** | Bij een campagne kiest het bureau 1 tot 3 van de bestaande GEO-vragen uit Vindbaarheid als anker. De app geeft ongeveer een op de drie posts een vraag mee in de opdracht. Geen rotatie, geen doelpagina, geen gebruikshistorie. | Het bestaande scherm Vindbaarheid (5 GEO-vragen). |
| **Al aanwezig** | Eigen thema invoeren | `customDraft` in `ThemePage.tsx` |

**Niet in de previewversie:** sjablonen C tot en met F, de feitenbank, de volledige vragenbank (10 vragen, doelpagina, rotatie, signalen bij te veel), de indeling Hero, Hub, Help, het campagneverslag, en GEO voor en na.

**De scoring blijft bevroren.** P1 tot en met P5 voegen alleen gegevens toe aan een post. P4 is een aparte prompt met een eigen versie.

### Extractieprompt voor P3 (productpagina)

```
Je leest één productpagina van een merk. Haal er letterlijk uit:
1. wat: wat het product is en voor wie (één zin);
2. anders: wat dit product anders maakt dan vergelijkbare producten in de branche. Alleen wat er staat, met citaat. Zet erbij of het branchetaal is volgens de categorietaal en hygiënefactoren van de merkarchitectuur;
3. bewijs: concrete gegevens, cijfers, namen, keuzes die de bewering onderbouwen, met citaat;
4. ontbreekt: wat een post over dit product nodig heeft maar niet op de pagina staat (bijvoorbeeld waarom het product bestaat, of bewijs).
Geen aannames. Staat iets er niet, zeg dat.
```

Uitvoer: `{"wat": "string", "anders": [{"citaat": "string", "branchetaal": true}], "bewijs": [{"citaat": "string"}], "ontbreekt": ["string"]}`

**De regel bij "ontbreekt":** de app toont dit aan het bureau als tip voor de productpagina. Het geeft ook een signaal bij de check op het plan.

### Test voor de previewversie

- Lancering van een nieuwe blend voor Brandpunt: vier weken, LinkedIn 8, Instagram 12. Lever het plan met per post de fase, het kanaal, de opdracht en het eventuele anker, plus de uitkomst van de check.
- Dezelfde lancering met een korting op koffie die al weken ligt: de check moet een regelbreuk melden.
- Een serie van zes afleveringen op LinkedIn, elke dinsdag.

## 3. Na de previews

In deze volgorde, tenzij de bureaus in de previews iets anders vragen:
1. **Sjabloon C (opknippen).** Op de bestaande documentbronnen. Het nieuwe deel is het halen van stellingen uit een document.
2. **De feitenbank en de volledige vragenbank.** 10 vragen en 10 feiten, met doelpagina, rotatie en de signalen bij te weinig en te veel. Dit wordt een eigen scherm, geen verzwaring van Vindbaarheid.
3. **Pijlers, de indeling Hero, Hub, Help, en het campagneverslag.**
4. **Sjablonen D en F, en GEO voor en na,** samen met de GEO-module.

## 4. De twee verwijzingen

Die bestaan als document, maar zijn nog niet gebouwd:
- **Stap A en B (website lezen, eigen verhaal ophalen)** staan in deel B van `bid-meter-bouwpakket-v1.1-2026-10-26.md`, het bouwpakket van de driftmeter. Ze staan ook in `geo-module-bouwspec-2026-10-22.md`.
- **Prompt C (vragen maken)** staat in `geo-module-bouwspec-2026-10-22.md`.

Ze horen bij de driftmeter en de GEO-module. Voor de previewversie zijn ze **niet nodig**:
- P3 heeft een eigen, kleinere extractieprompt (hierboven);
- P5 gebruikt de bestaande vijf GEO-vragen.

Na de previews bouwen we stap A en B één keer, en dan gebruiken de driftmeter, de GEO-module, de feitenbank en de vragenbank ze alle vier. Daarom staan de feitenbank en de vragenbank in paragraaf 3 pas op plek 2.

Beide documenten stuur ik je opnieuw mee.
