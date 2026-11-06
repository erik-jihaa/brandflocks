# Cursor-briefing — stap 3a: stap D beoordeelt de verkeerde zinnen

**Eigenaar:** Erik. **Datum:** 18 oktober 2026.
**Hoort bij:** `briefing/hba-input/hba-ronde-6-pilotklaar.md`, stap 3.
**Model:** Composer 2.5. Dit is een definitie plus een hermeting, geen nieuwe rewrite.

## Geen tweede rewrite, en geen retry op de schrijver

De rewrite is geslaagd. Uit `docs/hba-v1.11-stap3-geen-verzonnen-feiten.json`: de verzonnen procesfeiten (kleine batches, op bestelling, uitverkocht, aanmeldlijst) zijn weg, de aanspreekvorm is "je", `themePremisse` zit erin, en vijf van de zeven merkbeweringen hebben een bron uit de merkarchitectuur of een bewijsveld.

De twee resterende gevallen zijn allebei labelfouten van stap D, van twee verschillende soorten.

**1. Contextverlies.** *"Dan zetten wij een vers gezette kop voor je neer en leg je uit wat je proeft."* Dit is de directe voortzetting van *"Of kom langs op de proefochtend, elke eerste donderdag van de maand"*, die wél op het bewijsveld staat. Stap D beoordeelt per zin los van zijn omgeving en verliest daardoor de bron van de vorige zin.

**2. Scope.** *"Kortingen op koffie die ergens vandaan komt, zorgvuldig gebrand is en gewoon goed smaakt — daar is geen reden voor."* Dit is geen bewering over het merk maar het standpunt van het artikel. Er wordt geen feit geclaimd, dus er valt niets te onderbouwen. Stap D rekent retoriek als feitelijke claim.

Een tweede rewrite lost geen van beide op; de schrijver heeft zijn werk gedaan. De retry-variant is schadelijker: die laat de schrijver opnieuw proberen op zinnen die goed zijn, en haalt het argument uit het artikel — juist de zin die het stuk op merk maakt. Dit is hetzelfde patroon als bij de hint in stap 2a: de generator is in orde, de controle niet.

## Te bouwen

### 1. De definitie van `merk_bewering` aanscherpen

Zet de definitie op één plek, naast de bestaande definitiebestanden in `web/api/prompts/`, en gebruik hem in stap D.

**Telt als merkbewering:** een controleerbare uitspraak over het merk zelf — wat het doet, heeft, levert, belooft, of met welk getal. "Wij branden in kleine batches", "de branddatum staat op de zak", "elke eerste donderdag van de maand".

**Telt niet:**

- een mening, een standpunt of een argument ("daar is geen reden voor");
- een uitspraak over de categorie of over anderen, niet over dit merk;
- aanspreking van de lezer, een vraag, een oproep;
- een zin die een onmiddellijk voorafgaande, onderbouwde bewering uitwerkt. Die erft de bron van die bewering.

Laat stap D daarom in context beoordelen: geef het model de hele passage mee, niet losse zinnen, en laat het per bewering vastleggen op welke eerdere bewering hij eventueel steunt.

### 2. Hermeten, niet herschrijven

Draai stap D opnieuw op hetzelfde artikel `9e73e3fd-caee-11f1-84cf-9c6b00b7cd02`. Geen nieuwe rewrite, zodat zichtbaar blijft dat alleen de beoordeling veranderde.

Leg de uitkomst naast de huidige zeven beweringen in `docs/hba-v1.11-stap3a-merkbewering-hermeting.json`: per citaat het oude label en het nieuwe, met de reden van de wijziging.

### 3. Eén tekstcorrectie

In het artikel klapt het onderwerp om: *"Dan zetten **wij** een vers gezette kop voor je neer en leg **je** uit wat je proeft."* Corrigeer dat ("leggen wij uit"). Een losse correctie, geen rewrite.

**Klaar als:** geen enkele bewering in dit artikel nog op `geen_bron` staat, de vijf bestaande bronlabels onveranderd zijn, en de hermeting laat zien welke twee labels zijn omgezet en waarom.

## Punt voor Rogier — niet bouwen

`geen_bron` blokkeert nu indienen en publiceren. Met een detector die retoriek of een vervolgzin verkeerd labelt, kan een klant een correct artikel dus niet publiceren. Dat is voor een pilotklant een groter probleem dan de oorspronkelijke fout.

Vraag aan Rogier: blijft het blokkeren hard, of krijgt de klant de mogelijkheid een gemarkeerde zin te bevestigen met de bron erbij? Erik legt dat voor. **Verander het blokkeergedrag niet op eigen initiatief.** Dit gaat mee in de terugkoppeling na stap 9; zie `docs/terugkoppeling-rogier-ronde-6.md`.

## Afronden

- `php -l` en `py_compile` op de gewijzigde bestanden.
- Commit de bijgewerkte `docs/hba-v1.11-stap3-geen-verzonnen-feiten.md` en `docs/BUILD-NOTES.md` mee; die staan nu nog open.
- Eén commit, met stapnummer 3a in de message.
- Geen staging zonder een expliciete "ja staging" van mij. Niets op productie.
