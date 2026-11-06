# Open punten voor Rogier — na zijn besluiten van 16 oktober

**Van:** Erik. **Datum:** 16 oktober 2026.
**Context:** je document van 16 oktober en ijkset v0.3 beantwoorden de meeste vragen die bij ons openstonden. We zijn begonnen met bouwen in de volgorde die eruit volgt. Vier punten kunnen we niet zelf beslissen.

## 1. Pilot: welke partijen en welke omgeving

Je schrijft: "Welke partijen en welke omgeving spreken we apart af."

**Ons voorstel:** eerst staging, met de drie testmerken en iGiSNext zelf. Pas daarna één echte klant, en dan opnieuw jouw go. Zo zien we de klanttaal uit §6 in de praktijk voordat er een externe partij meekijkt.

> **Besluit Rogier:** [ ]

## 2. Hotfixes tijdens de bevriezing

Je bevriest de pilotversie na de 5×19-run. Er staat niet wat er gebeurt als we daarna een fout vinden.

**Ons voorstel:** een defect dat verkeerde output bij de klant geeft mag eruit, met melding aan jou. Alles wat methodiek of prompt raakt wacht tot na de pilot. Een wijziging die het oordeel verandert maakt de run ongeldig; die doen we dus niet.

> **Besluit Rogier:** [ ]

## 3. §3 hoort vóór de bevriezing, niet bij het fijnslijpen

Je zet §3 (volgorde van het hoofdoordeel, en positiebreuk) bij "§2 tot en met §5 is fijnslijpen; dat mag doorlopen terwijl de pilot loopt."

Maar §3 verandert het hoofdoordeel van Duinroos post 7 en van T10. Dat zijn precies uitkomsten die de 5×19-run meet. Voeren we §3 ná de bevriezing in, dan moet die run over.

**Ons voorstel:** §3 erbij in de pilotversie, dus vóór de bevriezing. §2, §4 en de rest van §5 blijven fijnslijpen.

> **Besluit Rogier:** [ ]

## 4. T13 tegenover Duinroos post 7 (§5.1): wat doen we met het verschil

We hebben het verschil deels gevonden. De tekst is niet het probleem: T13 en testpost 7 zijn woord voor woord identiek. Het verschil zit in de merkarchitectuur. De ijkset geeft acht velden mee, het testmerk veertien. In de ijkroute worden `archetype` en `spanning` afgeleid, en `verboden_logica` bevat alleen de tegenkracht. Afwezig zijn tone of behaviour, de goede en zwakke voorbeelden, en het contextprofiel.

We maken eerst een veldvergelijking en sturen die naar je toe. Daarna de vraag aan jou: vul je v0.3 aan met die velden, of accepteren we dat de ijkroute met een smaller profiel werkt en leggen we dat vast als gegeven? Wij zouden de ijkset niet gelijktrekken met het testmerk zonder jouw besluit, want dan test de ijkset niet meer wat jij geschreven hebt.

> **Besluit Rogier:** [ ]

## Wat we niet aan je vragen, omdat je het al beantwoord hebt

Voor de volledigheid, zodat je weet waar wij op doorbouwen:

- De klant mag lege velden opslaan; de check draait altijd en de poort blokkeert alleen content en meetlat.
- `meetlat_klaar` is strikt: alleen bij volledige velden én elke dimensie minstens voldoende. `aanscherpen` betekent dus geblokkeerd, zoals v0.3 bij A04, A05, A07, A08 en A14 ook aangeeft.
- De branche-afleider met wettelijke normen (§2) is geen pilotblokkade.
- Community en Service blijven onvertaald.
- De pilotversie is per definitie een nieuwe versie na §1, §5.3 en §6.
- T10 houdt de verwachting `categorietaal`; de scorer zit fout, niet de ijkset.
