# Cursor-briefing — stap 2a: de hint mag niets citeren dat niet in het artikel staat

**Eigenaar:** Erik. **Datum:** 18 oktober 2026.
**Hoort bij:** `briefing/hba-input/hba-ronde-6-pilotklaar.md`, stap 2.
**Model:** Composer 2.5. Dit is een controle in PHP, geen promptwerk.

## Waarom dit geen derde promptschuif is

Je stopte terecht na twee pogingen. De diagnose kan alleen scherper, en dan blijkt het een andere fout te zijn dan waar stap 2 over ging.

In `docs/hba-v1.11-stap2-hints-brandpunt-poging2.json` zet de hint in alle vijf runs woord voor woord dezelfde zin tussen aanhalingstekens:

> De zin 'koffie die de zes weken na het branden nadert, verkopen wij niet' beschrijft een grens die afwijkt van de regel zoals die geldt…

Die zin staat niet in het artikel. Het model verzint dus een citaat en corrigeert daarna zijn eigen verzinsel. De correctie zelf is goed, en `passage_na` geeft de regel letterlijk weer ("langer dan zes weken geleden is gebrand").

**Het klaar-criterium van stap 2 is dus gehaald op de regelweergave.** Wat `derivedBoundary` aanslaat is een verzonnen citaat, niet een afgeleide grens in het advies. Vijf identieke runs betekent bovendien dat het deterministisch is: met een andere formulering van de prompt schuift dit niet weg. Dit is dezelfde fout als stap 3, maar dan over een citaat uit de eigen tekst in plaats van over feiten over het merk.

De oplossing is daarom een verificatie, zoals de controlecall bij `constraint_breach` in stap 1. Die bestaat al in de codebase (`web/api/admin-hba-constraint-breach-verificatie-test.php`). **Raak de hintprompt niet aan.**

## Te bouwen

### 1. Citaatcontrole op de hint

In `ce_hba_v2_run_article_hint()` (`web/api/hba-v2-repository.php:1490`), bij de validatie rond regel 1592, dus **vóór** het wegschrijven:

- Haal elk fragment op dat het model tussen enkele of dubbele aanhalingstekens zet in `aanwijzing` en in `regelOvertreden`, en het veld `passage_voor`.
- Elk fragment moet letterlijk in de artikeltekst voorkomen. Vergelijk genormaliseerd: witruimte samengetrokken, typografische en rechte aanhalingstekens gelijkgesteld, hoofdletters genegeerd. Verder geen soepelheid — geen fuzzy match, geen gedeeltelijke overeenkomst.
- Komt een fragment niet voor: één keer opnieuw genereren. Gaat het dan nog mis, lever dan geen hint en geef de bestaande foutmelding terug. **Liever geen aanwijzing dan een aanwijzing die iets citeert dat er niet staat.**
- Log per afgekeurde poging het fragment en de reden, zodat het meetbaar blijft.

### 2. Twee dingen in dezelfde controle

Allebei zichtbaar voor de klant in `passage_na` van dezelfde run:

- **Aanspreekvorm.** De passage gebruikt "u"; Brandpunt is "je". Stap 3 punt 4 maakt de aanspreekvorm een veld in de merkarchitectuur. Laat de hint dat veld volgen en keur een passage af die ervan afwijkt. Bestaat het veld nog niet, bouw het dan hier en hergebruik het in stap 3.
- **Placeholders.** De passage bevat letterlijk `[hoeveel kilo er vorige maand is afgevoerd]`. Een tekst tussen vierkante haken hoort niet bij de klant te komen. Keur hem af, of toon hem als expliciete invulvraag naast de passage, niet als onderdeel van de lopende tekst. Kies er één en meld welke.

### 3. De detector uitbreiden

`scripts/run-hba-stap2-hints-staging.py` (~regel 101) meet nu `derivedBoundary`. Voeg toe:

- `quoteNotInSource`: een geciteerd fragment komt niet in het artikel voor;
- `wrongAddress`: de aanspreekvorm wijkt af van het merkveld;
- `placeholder`: er staat tekst tussen vierkante haken in `passage_na` of `aanwijzing`.

Rapporteer die drie apart van `derivedBoundary`, zodat een volgende run laat zien welke fout het precies is.

**Klaar als:** vijf runs op het Brandpunt-artikel, en de drie V0-hints, geen enkel fragment citeren dat niet in de brontekst staat; geen placeholders; de aanspreekvorm volgt het merk; en de regel wordt nog steeds letterlijk weergegeven. Ruwe output in `docs/hba-v1.11-stap2a-citaatcontrole.json`.

## Daarna

Stap 3. Dat is bewust die volgorde: stap 3 schrijft het artikel opnieuw, en zonder deze controle verzint de hint op de nieuwe tekst gewoon een ander citaat. Het aanspreekvormveld uit punt 2 hierboven is dan al gebouwd, dus stap 3 punt 4 is daarmee grotendeels klaar.

Stap 2 zelf gaat als gehaald naar Rogier, met deze bevinding erbij. Hij beslist of hij dat zo accepteert; ga daar niet op wachten.

## Afronden

- `php -l` en `py_compile` op de gewijzigde bestanden.
- Eén commit, met stapnummer 2a in de message.
- Geen staging zonder een expliciete "ja staging" van mij. Niets op productie.
