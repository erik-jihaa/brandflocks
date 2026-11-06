# Pilotbesluit Rogier, v1.13

22 oktober 2026. Reactie op `12-rapportage-v113-eindmeting-2026-10-22.md` en `09-ruwe-json-v1.13-5runs.json`.

## 1. De pilot start op v1.13

Ik heb de ruwe JSON zelf nagelopen:
- 19 van de 19 posts zijn 5 van 5 goed;
- de vier categorietaal-posts komen in geen enkele run op `tegenkracht`;
- de spreiding in merkbijdrage is overal hoogstens 1;
- `merkbewering` en `kern_categorietaal` staan in elke run.

De drie criteria zijn gehaald. Mooi werk.

**Besluit: de pilot start op v1.13.**

Voorwaarden voor de pilotperiode:
1. **Bevriezing.** Prompt v1.13, de resolver en het model (`claude-sonnet-4-6`) liggen vast. Elke wijziging die de scoring raakt, ook een ander model of een andere modelversie, vraagt eerst een nieuwe 5 × 19 met dezelfde drie criteria.
2. **Architectuurpoort.** Een pilotklant krijgt pas content en scores als zijn merkarchitectuur compleet en minstens voldoende is (besluit 7.6).
3. **Wekelijkse controle.** Lever elke week van de pilot een overzicht:
   - het aantal scores per hoofdoordeel;
   - elke `tegenkracht` en `regelbreuk` met het citaat, zodat ik controleer of het terecht is;
   - het aantal keer dat een klant een `geen_bron`-markering heeft overschreven, met de aangewezen bron;
   - het aantal CE-04-opmerkingen dat de klant heeft verwijderd.
4. **Melding bij twijfel.** Krijgt een pilotklant een Positiebreuk of regelbreuk die hij betwist, stuur die dan dezelfde dag door. Dat is de fout die we net hebben gerepareerd. Ik wil weten of hij terugkomt.

## 2. Trede 2: de voorwaarde blijft weg

De replay geeft 19 van 19 met en zonder `&& !kern_categorietaal`. De voorwaarde vervalt definitief. Zo blijft de code gelijk aan de volgorde in §3, en er is maar één plek waar de grens tussen tegenkracht en categorietaal wordt getrokken: de flagdefinitie.

## 3. De bijlagen flagtelling en valkuilen

Deze besluiten staan al in `hba-besluiten-stopbericht-ronde-6-2026-10-20.md`, punt 2. Ter herhaling, zodat ze uit de lijst met open punten kunnen:

- **Valkuilen:** voor de pilot hoeft het niet 100 procent te zijn, voor go-live wel. De volgorde is signaal, oordeel, meetlat.
- **Flagtelling:** de Implementation Kit is leidend. Tel patroon-posts (hoofdoordeel is niet `op_merk` en niet `neutraal`), niet elke losse flag. Dit moet klaar zijn voordat een pilotklant zijn eerste rapport ziet, na 10 posts.

Aanvulling: de neutrale posts hebben nog een `category_fallback`-flag, al is het hoofdoordeel goed. Zorg dat een klant bij een neutrale post geen waarschuwing over categorietaal te zien krijgt. Op een neutrale post is die flag hoogstens een advies.

## 4. De slotrun op Fable

Die mag nu, in twee stappen:

1. **Nu: alleen lezen.** Fable loopt de code en documentatie door en levert een lijst met bevindingen, zonder iets aan te passen. Gebruik de vier zoekpunten uit het stopbericht, punt 4:
   - dode code;
   - regels die op twee plekken staan;
   - Engelse klantteksten;
   - geheimen in de repo en ongecontroleerde tekst naar de klant.
2. **Na de eerste pilotweek: aanpassen.** Op een aparte branch, met de scoring bevroren. Er wordt alleen gemerged als de offline replay van de 95 v1.13-scores precies dezelfde hoofdoordelen geeft en de smoketests slagen.

Geheimen of sleutels die Fable in de repo vindt, mogen meteen worden verwijderd en vervangen. Daar hoef je niet op de pilotweek te wachten.

## 5. Kleine correctie in de rapportage

In §1 en §2 staat "15 runs per post = 60 scores". Het zijn 5 runs per post, dus 20 scores voor de vier posts samen. De uitkomst verandert er niet door.
