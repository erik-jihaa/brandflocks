# Cursor-briefing — bouwvolgorde na Rogiers besluiten van 16 oktober

**Eigenaar:** Erik. **Datum:** 16 oktober 2026.
**Bron:** `briefing/hba-input/Voor Erik 2026-10-16/hba-besluiten-en-nulmeting-2026-10-16.md` en `HBA_ijkset_v0_3.json`.
**Status van de antwoorden:** de meeste openstaande vragen zijn hieronder al beslist, met de bron erbij. De vier punten die nog bij Rogier liggen staan in `docs/open-punten-rogier-2026-10-16.md`; die blokkeren stap 1 tot en met 5 niet.

## Harde regels

- **Niets naar productie.** Elke deploy, ook naar staging, pas na een expliciete "ja" van Erik.
- **Eén commit per stap.** Lukt een klaar-criterium na twee pogingen niet, stop en meld het.
- Methodiek (prompts, drempels, criteria, labels) is van Rogier. Klopt iets niet met deze briefing, meld het in plaats van een eigen variant te bouwen.
- Seed geen branchegegevens om een ijkgeval groen te krijgen. Dan meet je je eigen patch.

---

## Stap 0 — ijkset v0.3 inlezen

Lees `HBA_ijkset_v0_3.json` in als nieuwe bron voor de ijkrun. 46 gevallen. Nieuw ten opzichte van v0.2:

- elk architectuurgeval heeft `verwacht_meetlat` (`actief` of `geblokkeerd`);
- het blok `definities` bovenin bevat `architectuurpoort`, `hoofdoordeel_volgorde`, `positiebreuk` en `contentrol`;
- A15, T04, T10 en T13 hebben gewijzigde verwachtingen.

**Let op:** `verwacht_oordeel` en `verwacht_meetlat` zijn twee losse assen. A04, A05, A07, A08 en A14 staan op `aanscherpen` én `geblokkeerd`. Dat is geen tegenstrijdigheid: het oordeel zegt hoe goed de architectuur is, de meetlat zegt of de klant verder mag. De mapping is mechanisch: sterk wordt `actief`, aanscherpen en zwak worden `geblokkeerd`.

**Klaar als:** de ijkrunner v0.3 draait en per geval zowel oordeel als meetlatstatus wegschrijft.

## Stap 1 — architectuurpoort (§1)

Dit is de enige stap waar Rogier een klaar-lijst voor geeft.

1. **Geen invoervalidatie.** De klant mag lege velden opslaan. Rogier: "Een leeg veld is een bevinding in het oordeel, geen reden om de check niet te draaien." De check draait altijd en geeft altijd een oordeel met advies.
2. **De poort blokkeert content en meetlat, niet het oordeel.** Status `actief` alleen bij: alle velden gevuld, inclusief tegenkracht en regel, én elke dimensie minstens voldoende. Anders `geblokkeerd`, met oordeel en advies.
3. **Twee nieuwe signalen:** `tegenkracht_ontbreekt` en `regel_ontbreekt`. Zet ze in de JSON van de check zodat de judge ze kan lezen, en toon ze in de wizard in gewone taal met advies erbij ("met advies welk soort tegenkracht"). Maak er geen nieuwe dimensie van: een ontbrekend veld heeft geen oordeel sterk/voldoende/aanscherpen. Het is een bevinding op veldniveau die de betreffende dimensie wel op `aanscherpen` zet.
4. **De harde poort in `web/api/hba-architectuur-check-repository.php:325-327`** (`Vul eerst persoonlijkheid, doel en tegenkracht in`) vervalt. De identity gate erachter mag de inhoudelijke check ook niet meer overslaan; die wordt een bevinding, geen afbreking. Zie ook `briefing/cursor-ijkrun-a15-en-aparte-routes.md`, dat hiermee samenvalt — voer die briefing in deze stap mee uit.

**Klaar als** deze acht ijkgevallen kloppen: A01, A02, A03, A17 en A18 op `actief`; A09, A12 en A15 op `geblokkeerd` mét inhoudelijk oordeel en advies, zonder dat er content is gegenereerd.

## Stap 2 — hoofdoordeel-volgorde en positiebreuk (§3)

**Dit hoort vóór de bevriezing, ook al noemt Rogier §3 fijnslijpen.** §3 verandert de uitkomst van Duinroos post 7 en T10, en dus wat de 5×19-run meet. Doe je het erna, dan moet die run over.

1. **Vaste volgorde:** regelbreuk → tegenkracht → categorietaal → op_merk/neutraal. Tegenkracht alleen als de foe op de kop of in de openingszin staat. Een tekst die zich tégen de eigen tegenkracht keert is geen tegenkracht maar categorietaal. Dat repareert T10, waarvan de verwachting in v0.3 ongewijzigd `middelmatig` + `categorietaal_gedetecteerd` + `deels_onderscheid_herkend` is.
2. **Eén definitie, twee plekken.** Zet de volgorde op één plek en synchroniseer de prompt met diezelfde tekst. De 7,4 procent `hoofdoordeel_gecorrigeerd` uit run 3 is het bewijs dat prompt en PHP-filter nu uiteenlopen; dat percentage is het meetpunt of dit gelukt is (criterium ≤ 5 procent).
3. **Positiebreuk is uitsluitend een label in de presentatielaag.** Intern blijft de waarde `tegenkracht`: database, API naar n8n, aggregaties, Overzicht, Rapport, portfoliosignalen. Het label verschijnt overal waar een mens het oordeel leest: artikelpaneel, CE-04-feedback, hints, reviewmails, klantpreview, en ook in Rapport en Overzicht als daar nu het woord "tegenkracht" zichtbaar is (§6.3 noemt Positiebreuk in de klantlijst). Doe de vertaling via **één gedeelde functie**. Vertaal niet verspreid; zo loopt het weer uit elkaar.
4. **Duinroos post 7** krijgt dezelfde verwachting als T13: `tegenkracht`, flag op de kop, merkbijdrage hoogstens 5. Of dat botst met de Brandpunt-kalibratie van v1.10.6 moet uit de 5×19 blijken; zet het daar als expliciet controlepunt in.

**Klaar als:** T10 geeft `categorietaal`, T13 en Duinroos 7 geven hetzelfde oordeel, en `hoofdoordeel_gecorrigeerd` ligt onder 5 procent.

## Stap 3 — verbodslijst op de output (§5.3)

Methodiekterminologie mag niet in klantsuggesties staan. Filter en vraag opnieuw, in hetzelfde patroon als de verbodstermen bij scoring. Blokkeren niet: dan houdt de klant niets over. Staat de term na een tweede poging nog steeds in de output, laat de suggestie dan weg en log de treffer, zodat het meetbaar blijft.

**Eerst vaststellen, dan bouwen:** ziet de klant de toelichting per dimensie? §1.3 zegt dat de klant oordeel en advies in de wizard ziet, en Rogiers regel is dat de klant nooit een HBA-term ziet. Staan de dimensie-toelichtingen in dat scherm, dan vallen ze er wél onder en was de fix van run 3 te smal. Meld wat je vindt.

**Klaar als:** geen enkele klantzichtbare suggestie of toelichting bevat een term uit de verbodslijst, en elke onderschepte treffer is terug te vinden in de log.

## Stap 4 — klanttaal (§6)

1. Voer de labeltabel uit §6 door in alle klantzichtbare schermen.
2. **Community en Service blijven onvertaald.** Rogier: "Community en service zijn gangbaar in het Nederlandse vak; die blijven."
3. Maak de A6-screenshots die Rogier vraagt: elk scherm dat een pilotklant ziet. Hij keurt de rijen in één keer goed.

§6 blokkeert de eerste echte pilotklant, niet het interne testen op staging. Intern doordraaien mag terwijl dit afloopt.

**Klaar als:** de screenshots klaarstaan en Rogier ze heeft goedgekeurd.

## Stap 5 — de runs

Twee verschillende metingen; houd ze gescheiden.

- **De 5×19 op de pilotversie (§7).** De pilotversie kan per definitie niet v1.10.6 zijn: §1, §5.3 en §6 moeten erin zitten. Het is dus een nieuwe versie ná stap 1 tot en met 4. Eén volledige run erop, dan bevriezen.
- **De v0.3-ijkrun** als nieuwe nulmeting, ná stap 1, want de klaar-criteria van stap 1 zijn letterlijk v0.3-gevallen. De v0.2-nulmeting is historisch: andere promptversie, andere poort. Niet vergelijken alsof het dezelfde meting is.

**De judge toetst drie dingen per architectuurgeval, niet één:**

1. komt de status overeen met `verwacht_meetlat`;
2. is er een inhoudelijk oordeel (`alleen een melding zonder inhoudelijk oordeel` staat in `verboden_signalen`);
3. is er geen content gegenereerd (`meetlat geactiveerd of content gegenereerd`, idem).

Zoek op welk technisch veld "meetlat aan" representeert — waarschijnlijk de guardrail — en meld welk veld je gebruikt.

**Rapporteer de valkuilscore als drie getallen:** goed, fout, niet gedraaid. Vijf valkuilgevallen draaien niet (A15, V01, C02, C03, C06); een score die die vijf wegmoffelt is misleidend.

## Stap 6 — onderzoek, geen bouw: T13 tegenover Duinroos post 7 (§5.1)

Vastgesteld: post 7 is de zevende testpost ("Onze wachtlijst is in één maand opgelost") en de tekst is woord voor woord identiek aan `T13.input.tekst`. Het verschil zit dus niet in de tekst.

Sterkste spoor: de merkarchitectuur. `ce_hba_ijk_narrative_from_merkarchitectuur()` (`web/api/hba-ijkrun-repository.php:93-152`) bouwt uit de acht ijkvelden een narratief: `foe` uit `niet_in_geloven`, `mechanisme` uit `wat_anders`, `geloofwaardigheidstest` uit `regels`, `archetype` afgeleid uit `branche_library.markt_archetype` (standaard 'Maker'), `spanning` samengesteld, `verboden_logica` gevuld met alleen de foe. Het testmerk heeft veertien narratiefvelden. Ontbreken dus: de echte `verboden_logica`, `tone_of_behaviour`, `goede_voorbeelden`, `zwakke_voorbeelden` en `contextprofiel`.

**Begin met een veldvergelijking van beide narratieven naast elkaar**, vóór je beide routes draait. Dat is sneller en sluitender. Leg de uitkomst vast in `docs/hba-t13-vs-duinroos-7.md`; die gaat naar Rogier, het staat op zijn aanleverlijst.

**Niet doen:** het ijkrun-narratief normaliseren naar het volledige testmerk-narratief. Dan test de ijkset niet meer wat Rogier geschreven heeft.

## Niet in deze ronde

- Branche-afleider fase 2 met wettelijke normen (§2). Rogier zet §2 tot en met §5 bij het fijnslijpen dat mag doorlopen tijdens de pilot; alleen §5.3 is pre-pilot. Geen branchelijst hardcoden.
- De overige punten uit §5 behalve §5.3.

## Documentbeheer

Eén koppeldocument, bijgewerkt naar v0.3, met de besluiten 7.1 tot en met 7.7 ingevuld. Geen tweede document ernaast; dat reproduceert precies het "bevestigd maar vakjes open"-probleem dat Rogier in §4 aanwijst.

## Afronden

- `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap.
- Kort verslag aan Erik per stap: klaar / gewijzigd / waar je een keuze moest maken.
