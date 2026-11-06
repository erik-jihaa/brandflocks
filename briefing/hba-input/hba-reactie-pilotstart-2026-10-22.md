# Reactie Rogier op de rapportage pilotstart

22 oktober 2026. Reactie op `13-rapportage-pilotstart-2026-10-22.md` (HEAD `54b52fa`).

De scoring blijft bevroren. Niets hieronder raakt prompt v1.13, de resolver, de flags of het model.

## 1. A3: akkoord, melding op Negeer

Akkoord om A3 te koppelen aan **Negeer**. Er komt voorlopig geen aparte knop "onterecht".

- Klikt een klant op Negeer bij een post met hoofdoordeel `tegenkracht` of `regelbreuk`, dan gaat dezelfde dag een melding naar Erik en Rogier.
- In de melding staan: merk, posttitel, hoofdoordeel, het citaat uit `flag_citaten` en de uitleg.
- Neem in het wekelijkse overzicht (A2) ook op hoe vaak Negeer is gebruikt op andere hoofdoordelen. Zo zien we of klanten Negeer gebruiken om "klopt niet" te zeggen of om "nu even niet" te zeggen.

Blijkt in de pilot dat Negeer vaak "nu even niet" betekent, dan voegen we na de pilot een keuze toe: "onterecht" of "niet nu".

**Klaar als:** een testmelding op staging bij Brandpunt "Breekt de regel", na Negeer.

## 2. Fable: alleen het geheimencheck naar voren

Akkoord dat de volledige Fable-run wacht tot de definitieve server. Eén onderdeel wil ik niet laten wachten: **het zoeken naar geheimen in de repo**. Dat hangt niet van de server af, en een gelekte sleutel is nu al een risico.

- Doe dat nu met een standaard scanner over de hele git-geschiedenis, bijvoorbeeld gitleaks of trufflehog. Daar is Fable niet voor nodig.
- Wordt er iets gevonden: de sleutel intrekken, een nieuwe aanmaken en uit de repo halen.
- Lever de uitkomst als één regel in de volgende rapportage: "scan gedraaid op [datum], [n] vondsten, [actie]".

Controleer ook even de domeinnaam. In de rapportage staat `brandfloks.nl`; op de site en in de scan gebruik ik brandflocks.com. Als het een tikfout is, hoeft er niets te gebeuren.

## 3. Wat ik nog wil zien

- **A2:** het voorbeeld van week 43 (`docs/pilotstart-a2-weekly-report-voorbeeld-2026-10-22.md`). Het zat niet in de zip. Stuur het mee in het volgende pakket.
- **A1:** de screenshots van de drie neutrale testposts, als je op staging bent ingelogd.

## 4. Planning

- **B1** (flagtelling) moet af voor het eerste klantrapport, na 10 posts. Laat me weten wanneer de eerste pilotklant begint. Dan rekenen we terug wanneer B1 klaar moet zijn.
- **C1 tot en met C3** blijven backlog.
