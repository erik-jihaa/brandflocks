# Laatste puntjes vóór en tijdens de pilotstart

22 oktober 2026, Rogier. Hoort bij `hba-pilotbesluit-v1.13-2026-10-22.md`.

**Algemene regel voor deze lijst:** niets hieronder raakt de scoring. Prompt v1.13, `ce_hba_resolve_hoofdoordeel`, de flagdefinities en het model (`claude-sonnet-4-6`) blijven ongewijzigd. Controle na elke stap: de offline replay van de 95 v1.13-scores geeft nog precies dezelfde hoofdoordelen (19/19), en de smoketests slagen. Er is geen nieuwe 5 × 19 nodig.

Volgorde: eerst de punten onder A (vóór de eerste pilotklant), dan B (vóór het eerste rapport van een pilotklant), dan C (backlog, mag tijdens de pilot).

---

## A. Vóór de eerste pilotklant

### A1. Geen waarschuwing over categorietaal bij een neutrale post

**Nu:** neutrale posts (hoofdoordeel `neutraal`, `merkbewering = nee`) dragen nog een `category_fallback`-flag. In v1.13 staat die op alle drie de neutrale testposts.

**Opdracht:**
1. Bij hoofdoordeel `neutraal` toont de klantinterface geen waarschuwing, label of rode markering voor `category_fallback`.
2. Wil je de flag laten zien, dan alleen als neutraal advies in het zijpaneel, bijvoorbeeld: "Deze post zegt niets over je merk. Dat is prima voor een tip of aankondiging."
3. De flag blijft wel in de data staan, voor de meting en het wekelijkse overzicht.

**Klaar als:** screenshot van de drie neutrale testposts (ontkalktips, vacature, webinar) zonder waarschuwing.

### A2. Wekelijks pilotoverzicht

**Opdracht:** een script of adminpagina die per pilotklant per week dit oplevert, als Markdown of CSV:
1. aantal scores per hoofdoordeel (`op_merk`, `neutraal`, `categorietaal`, `tegenkracht`, `regelbreuk`);
2. elke `tegenkracht` en `regelbreuk` met de posttitel, het citaat uit `flag_citaten` en de uitleg;
3. het aantal keer dat een klant een `geen_bron`-markering heeft overschreven, met de aangewezen bron;
4. het aantal CE-04-opmerkingen dat de klant heeft verwijderd, en welke.

**Klaar als:** een voorbeeld van het overzicht op de drie testmerken in staging.

### A3. Melding bij een betwiste Positiebreuk of regelbreuk

**Opdracht:** krijgt een post `tegenkracht` of `regelbreuk` en verwijdert de klant de bijbehorende opmerking, of markeert hij die als onterecht, stuur dan dezelfde dag een melding naar Erik en Rogier. Een e-mail met merk, post, citaat en uitleg is genoeg.

**Klaar als:** een testmelding op staging bij Brandpunt "Breekt de regel".

### A4. Trede 2 vastleggen

De voorwaarde `&& !kern_categorietaal` in trede 2 vervalt definitief. Controleer dat hij niet meer in de code staat, en zet in `ce_hba_resolve_hoofdoordeel` één regel commentaar:

```
// Trede 2 zonder kern_categorietaal-voorwaarde: besluit Rogier 22-10-2026, replay v1.13 19/19 met en zonder.
```

---

## B. Vóór het eerste rapport van een pilotklant (na 10 posts)

### B1. Flagtelling volgens de Implementation Kit

**Nu:** de app telt elke `architecture_risk`-flag, ook op posts met hoofdoordeel `op_merk`.

**Opdracht:**
1. Rapport en agenda tellen **patroon-posts**: posts met hoofdoordeel `categorietaal`, `tegenkracht` of `regelbreuk`. Posts met `op_merk` of `neutraal` tellen niet mee voor de drempels, ook als ze een flag hebben.
2. Gebruik de drempels uit de Implementation Kit, zoals 3 van de 5 en flags in 10 posts, en PBS −8 / −15, en verwijs in de code naar de paragraaf in de kit.
3. Flags op `op_merk`-posts blijven zichtbaar als advies bij die post, maar niet als patroon in het rapport.

**Klaar als:** de stroomtest Brandpunt (25 posts, stap 8) opnieuw door het rapport gehaald, met per checkpoint (10, 15, 20, 25) het aantal patroon-posts en welke drempels geraakt worden. Leg die naast de verwachting in `brandpunt-content-25-posts.md`.

---

## C. Backlog (mag tijdens de pilot)

### C1. Zijpaneel artikel en terugkoppeling in het Nederlands

| Nu | Wordt |
|---|---|
| Rationale | Onderbouwing |
| Tone | Schrijfstijl |
| Image ratio | Beeldverhouding |
| square / landscape | Vierkant / Liggend |
| Rewrite | Herschrijven |
| Linkedin | LinkedIn |

"Details" en de overige platformnamen blijven zoals ze zijn.

### C2. "Persoonlijk (ik-vorm)" niet afkappen

In het zijpaneel staat nu "Persoonlijk (ik-vor". Laat het label afbreken op een nieuwe regel, of geef de kolom meer ruimte.

### C3. Kalender op 1280 px

De zondagkolom valt rechts weg. Laat de week passen op laptopbreedte, of laat de kalender binnen zijn eigen vak horizontaal scrollen, zodat de pagina zelf niet breder wordt.

**Klaar als (C1 tot en met C3):** screenshots op 1280 px van het artikel met zijpaneel en van de agenda in de kalenderweergave.

---

## Opleveren

Eén zip met per punt de screenshots of voorbeeldbestanden, de commit-hashes, en de bevestiging dat de replay van de 95 v1.13-scores na alle wijzigingen nog 19/19 geeft.
