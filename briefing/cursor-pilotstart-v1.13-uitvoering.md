# Pilotstart v1.13 — uitvoering

**Bron:** `briefing/hba-input/hba-pilotbesluit-v1.13-2026-10-22.md` + `briefing/hba-input/hba-laatste-puntjes-pilotstart-2026-10-22.md`
**Branch:** `hba-ronde-6-pilotklaar` (of een nieuwe pilot-branch als je die al bent begonnen — zeg maar welke)
**Niets op productie.** Eén commit per punt (A1–A4, B1, C1–C3 elk apart).

## De bevriezing — geldt voor elke stap hieronder

Prompt v1.13, `ce_hba_resolve_hoofdoordeel`, de flagdefinities en het model (`claude-sonnet-4-6`) liggen vast voor de hele pilotperiode. Geen van de punten A–C mag de scoring raken. Controle na elke stap: offline replay van de 95 v1.13-scores geeft nog precies 19/19 dezelfde hoofdoordelen, en de smoketests slagen. Geen nieuwe 5×19 nodig voor deze punten — als een replay afwijkt, is dat een teken dat de stap de scoring toch heeft geraakt en moet hij terug.

## Volgorde

### A4 — Trede 2 vastleggen (eerst, mag los van de rest)

Controleer dat `&& !kern_categorietaal` niet meer in `ce_hba_resolve_hoofdoordeel` staat. Voeg toe:

```
// Trede 2 zonder kern_categorietaal-voorwaarde: besluit Rogier 22-10-2026, replay v1.13 19/19 met en zonder.
```

Geen logicawijziging, alleen vastleggen. Eigen commit.

### A1 — Geen categorietaal-waarschuwing bij neutrale post

Bij hoofdoordeel `neutraal`: geen waarschuwing/label/rode markering voor `category_fallback` in de klantinterface. Optioneel een zacht advies in het zijpaneel (voorbeeldtekst staat in de bron). Flag blijft in de data voor meting en wekelijks overzicht.
**Klaar als:** screenshot van de drie neutrale testposts (ontkalktips, vacature, webinar) zonder waarschuwing.

### A2 — Wekelijks pilotoverzicht

Script of adminpagina, per pilotklant per week, Markdown of CSV: aantal scores per hoofdoordeel; elke `tegenkracht`/`regelbreuk` met posttitel + citaat uit `flag_citaten` + uitleg; aantal `geen_bron`-overschrijvingen met aangewezen bron; aantal verwijderde CE-04-opmerkingen (welke).
**Klaar als:** voorbeeld op de drie testmerken in staging.

### A3 — Melding bij betwiste Positiebreuk/regelbreuk

**Verduidelijk eerst:** onderscheidt de huidige UI "opmerking verwijderen" van "markeren als onterecht" als twee acties, of is er alleen verwijderen? Pas de opdracht hierop aan voordat je bouwt — meld dit terug in plaats van zelf te kiezen.

Daarna: bij `tegenkracht`/`regelbreuk` waarvan de klant de opmerking verwijdert of als onterecht markeert, dezelfde dag een e-mail naar Erik + Rogier met merk, post, citaat, uitleg.
**Klaar als:** testmelding op staging bij Brandpunt "Breekt de regel".

### Fable — uitgesteld tot op brandflocks.com

**Niet nu starten.** Erik: Fable doen we pas als we op de definitieve server (brandflocks.com) staan. Dit geldt voor zowel de leesronde als de aanpassingen erna.

Zodra dat zover is: eerst alleen lezen (vier zoekpunten uit het stopbericht §4: dode code, dubbel voorkomende regels, Engelse klantteksten, geheimen in de repo + ongecontroleerde tekst naar de klant), geen aanpassingen in die stap. Gevonden geheimen/sleutels zijn de enige uitzondering — die mogen, waar dan ook, altijd meteen verwijderd en vervangen worden, ook vóór de migratie.
Aanpassen op basis van de bevindingen pas na de eerste pilotweek ná de migratie, op een aparte branch, merge alleen bij replay 95 → 19/19 + smoketests.

### B1 — Flagtelling volgens Implementation Kit (vóór eerste klantrapport, na ~10 posts; niet blokkerend voor pilotstart)

Rapport en agenda tellen alleen patroon-posts (hoofdoordeel `categorietaal`, `tegenkracht` of `regelbreuk`); `op_merk`/`neutraal` tellen niet mee voor drempels, ook niet met een flag. Drempels uit de Implementation Kit (3 van 5, flags in 10 posts, PBS −8/−15), met verwijzing naar de paragraaf in de kit. Flags op `op_merk`-posts blijven zichtbaar als advies bij die post, niet als patroon in het rapport.
**Klaar als:** stroomtest Brandpunt (25 posts, stap 8) opnieuw door het rapport, per checkpoint (10/15/20/25) aantal patroon-posts en geraakte drempels, naast de verwachting in `brandpunt-content-25-posts.md`.

### C1–C3 — Backlog, tijdens de pilot

- C1: NL-vertaling zijpaneel (Rationale→Onderbouwing, Tone→Schrijfstijl, Image ratio→Beeldverhouding, square/landscape→Vierkant/Liggend, Rewrite→Herschrijven, Linkedin→LinkedIn). "Details" en overige platformnamen blijven.
- C2: label "Persoonlijk (ik-vorm)" niet afkappen — regelafbreking of meer ruimte.
- C3: kalender op 1280px — zondagkolom past, of de kalender scrollt binnen zijn eigen vak horizontaal zonder de pagina te verbreden.
**Klaar als:** screenshots op 1280px van artikel+zijpaneel en agenda-kalenderweergave.

## Oplevering

Eén zip per voltooid blok (of aan het eind één geheel): screenshots/voorbeeldbestanden per punt, commit-hashes, bevestiging dat de replay van de 95 v1.13-scores na alle wijzigingen nog 19/19 geeft.
