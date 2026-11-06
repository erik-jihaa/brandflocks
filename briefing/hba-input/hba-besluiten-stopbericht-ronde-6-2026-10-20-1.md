# Besluiten Rogier op het stopbericht van ronde 6

20 oktober 2026. Reactie op `rogier-stopbericht-ronde-6-2026-10-19` (v1.12, 13 van 19).

Dank voor het stopbericht en vooral voor de offline replay. Die maakt het probleem goed zichtbaar. Eerst het goede nieuws: de drie neutrale posts zijn opgelost (14 van 15 runs neutraal), regelbreuk is 15 van 15, Stelwerk post 5 is 5 van 5, en het hoofdoordeel volgt in 100 procent van de scores uit de flags.

## 1. De pilot start nog niet, er komt één gerichte reparatie

Vijf van de zes posts die niet stabiel zijn, falen op hetzelfde punt: het model zet `foe_vervaging` op een algemene, niet onderbouwde belofte. Daarmee wordt een post die gewoon klinkt als de branche een **Positiebreuk**.

| Post | Gemarkeerde zin | Wat het werkelijk is |
|---|---|---|
| Brandpunt, categorietaal | "versgebrande koffie van topkwaliteit, met liefde gemaakt" | Branchetaal |
| Duinroos, categorietaal | "warme, persoonlijke zorg van betrokken professionals" | Branchetaal |
| Duinroos, archetype zonder bewijs | "Zo zien wij zorg." | Toon zonder bewijs |
| Stelwerk, branchetaal | "Daarom kiezen steeds meer maakbedrijven voor Stelwerk." | Algemeen verkoopargument |
| Stelwerk, neutraal (1 run) | "AI in de maakindustrie, wat kun je ermee?" | Aankondiging |

Bij een pilotklant is dit de schadelijkste fout die de app kan maken: tegen een klant zeggen dat zijn post tegen zijn eigen merk ingaat, terwijl de post alleen algemeen is. Daarom eerst repareren.

### 1a. `foe_vervaging` scherper definiëren (prompt v1.13)

Neem dit op in `hba-flags-definitie.txt`:

> `foe_vervaging` geldt alleen als de tekst **doet, aanprijst of als eigen werkwijze presenteert** wat de tegenkracht doet. Er moet een citaat zijn waarin dat gedrag of die logica zelf staat.
>
> Geen `foe_vervaging`:
> - een algemene of niet onderbouwde belofte die ook concurrenten doen. Dat is `category_fallback` en eventueel `proof_gap`;
> - een tekst die iets weglaat (geen bewijs, geen mechanisme, geen regel). Ontbreken is geen omarmen;
> - een vraag of aankondiging zonder merkbewering;
> - een tekst die de logica van de tegenkracht noemt om zich er daarna tegen te keren.
>
> Toets: kun je in het citaat aanwijzen welk gedrag van de tegenkracht de tekst overneemt? Lukt dat alleen met de uitleg "omdat het merk hier zijn eigen verhaal niet vertelt", dan is het geen `foe_vervaging`.

Neem als voorbeelden op:
- **Wel `foe_vervaging`:** "Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg" (Duinroos: efficiëntie als zorgwinst) en de Black Friday-korting (Brandpunt).
- **Geen `foe_vervaging`:** de vijf zinnen uit de tabel hierboven.

### 1b. Code: voorlopig geen voorwaarde terug in trede 2

Ik vroeg vorige keer om de voorwaarde `&& !kern_categorietaal` in trede 2 weg te halen. Jullie replay laat zien dat die voorwaarde de te ruime flag opving: met de voorwaarde was het 14 van 19 geweest. Het echte probleem zit in de flag, dus repareer ik het daar. Zet de voorwaarde nog niet terug, want hij kost ook iets: "Logica van de tegenkracht" zakt er volgens de replay naar 3 van 5. Draai na de 5 × 19 de replay opnieuw met en zonder voorwaarde, dan beslis ik op basis van die twee getallen.

### 1c. Verwachting Duinroos post 7 aanpassen naar `op_merk`

De kop "Onze wachtlijst is in één maand opgelost" klinkt als de logica van de tegenkracht, maar de tweede zin keert zich ertegen: "Maar niet zoals je misschien denkt." Volgens onze eigen regel is een tekst die zich tegen de tegenkracht keert geen tegenkracht. Mijn verwachting van vorige keer (`tegenkracht`) was dus niet juist.

Nieuwe verwachting: `op_merk`, met `proof_gap` op het niet onderbouwde resultaat. De drie runs met `op_merk` waren goed.

### 1d. Stelwerk branchetaal blijft `categorietaal`

"Daarom kiezen steeds meer maakbedrijven voor Stelwerk" is een sociaal bewijs dat elke adviseur gebruikt. De tegenkracht van Stelwerk is een systeem verkopen op wat het kan in plaats van op wat het bedrijf nodig heeft. Populariteit als argument is iets anders. De verwachting blijft `categorietaal`, met `proof_gap` op "volledig onafhankelijk".

### 1e. Eén extra 5 × 19, als uitzondering

De briefing zegt maximaal twee pogingen. Omdat de oorzaak nu precies bekend is, sta ik één derde poging toe, op v1.13, met:
- de definitie uit 1a;
- de verwachting uit 1c;
- `merkbewering` en `kern_categorietaal` gelogd in de run zelf (in deze export stonden ze nog op null);
- daarna de replay met en zonder de voorwaarde uit 1b.

**De pilot start als:**
- minstens 16 van de 19 posts 5 van 5 goed scoren;
- geen enkele van de vier categorietaal-posts (Brandpunt categorietaal, Duinroos categorietaal, Duinroos archetype zonder bewijs, Stelwerk branchetaal) in een run `tegenkracht` krijgt;
- de spreiding in merkbijdrage overal hoogstens 1 is.

Haalt v1.13 dat niet, dan stoppen we en kijk ik met de ruwe output naar de volgende stap. Er komt dan geen vierde poging.

## 2. Open besluiten uit de bijlagen

**Valkuil-prioriteit:** akkoord met jullie voorstel. Voor de pilot hoeft het niet 100 procent, voor go-live wel. Volgorde: signaal (15), oordeel (8), meetlat (3). Het probleem uit punt 1 is ook een signaalprobleem, dus 1a telt mee in die volgorde.

**Flagtelling:** de Implementation Kit is leidend. Tel patroon-posts (hoofdoordeel is niet `op_merk`), niet elke losse flag. Een flag op een `op_merk`-post is een advies, geen drift. Dit moet in rapport en agenda staan vóórdat een pilotklant zijn eerste rapport ziet. Omdat een rapport pas na 10 posts verschijnt, hoeft het niet vóór de start van de pilot klaar te zijn.

## 3. A6 ronde 2

Het stopbericht noemt `docs/hba-a6-ronde2-stap-A-B.md`, `-C-` en screenshots van 20 oktober, maar die zitten niet in de zip. Stuur die graag mee: Agenda in lijst- en kalenderweergave, de CE-04-opmerking met de regel letterlijk (JSON plus screenshot) en het hashtagvoorbeeld.

## 4. De slotrun op Fable

Akkoord, de kosten zijn klein: jullie schatting klopt met de officiële prijzen ($10 per miljoen tokens invoer, $50 uitvoer, $0,25 cachelezen). Wel onder vier voorwaarden:

1. **Eerst punt 1 afmaken.** Opruimen lost het methodiekprobleem niet op, en een opruimactie midden in een scoringwijziging maakt het lastig om te zien wat een verschil veroorzaakt.
2. **Op een aparte branch, met scoring bevroren.** De bestanden voor de prompt en het hoofdoordeel (`hba-prompt.php`, `ce_hba_resolve_hoofdoordeel`, de definitiebestanden in `web/api/prompts/`) verandert de opruimronde niet inhoudelijk.
3. **Bewijs dat er niets verandert.** Na het opruimen geeft de offline replay van de 95 opgeslagen scores precies dezelfde hoofdoordelen, en de smoketests slagen. Pas dan mergen.
4. **Een doel vooraf.** Laat Fable zoeken naar:
   - dode code;
   - regels die op twee plekken staan (zoals eerder het hoofdoordeel in prompt én PHP);
   - Engelse klantteksten;
   - geheimen of sleutels in de repo;
   - plekken waar gegenereerde tekst zonder controle naar de klant gaat.
   
   Lever een lijst met bevindingen voordat er iets wordt aangepast.

De uitsluitingen die je noemt (`web/blog`, `web/assets`, `backups`, gegenereerde workflow-JSON) zijn verstandig.
