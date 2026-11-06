# Referentiemerk Tony's Chocolonely: wat je ermee doet

Voor Erik, 9 oktober 2026. Alleen intern: markeer de tenant als testaccount (bouwspecificatie kalibratiemodule, §6).

## Waarom dit merk

De drie testmerken zijn verzonnen. Daarmee zien we of de app een regel herkent, maar niet hoe hij reageert op echte content van een merk dat al op een regel draait. Tony's kan dat laten zien. Het merk heeft een regel die geld kost (een extra premie bovenop Fairtrade tot een leefbaar inkomen), het publiceert misstanden in de eigen keten, en het plaatst Nederlandstalige content op meerdere kanalen. De markt eromheen heeft woorden als "eerlijk" en "traceerbaar" deels overgenomen. Daarmee test dit merk ook of de app een regel onderscheidt van een woord.

## Wat er in `Tonys_Chocolonely_referentiemerk.json` zit

| Blok | Waarvoor |
| --- | --- |
| `wizard_antwoorden` | Zelfde velden als de wizard en de ijkset. Voor de architectuurcheck. |
| `brand_narrative` | Zelfde schema als de testmerken. Voor provisioning en scoring. |
| `markthygiene` | Een gecureerde branche voor `branche_library`. Dit is ook het format voor elke branche (zie onder). |
| `testposts` | Tien posts met verwachting, in het formaat van ronde 2 (T6). |
| `architectuurcheck_verwachting`, `compressietest_verwachting` | Twee ijkgevallen voor de ijkset. |
| `themakeuze_test` | Zes thema's met het advies dat we vooraf verwachten. |
| `drift_test` | Vervangende posts voor de drifttest. |

## Inladen

1. Provision een tenant met `brand_narrative`, zoals bij de testmerken.
2. Zet `markthygiene` in `branche_library` als gecureerde branche `chocolade_consument_nl` en koppel de tenant daaraan.
3. Importeer de tien `testposts` als nulmeting.
4. Laad echte content: de Nederlandstalige posts van Tony's van de afgelopen twaalf maanden van Instagram, LinkedIn en het nieuws op de website. Mik op 40 tot 60 stuks, elk met de oorspronkelijke publicatiedatum.

**Staging (9 okt 2026):** `./scripts/provision-tonys-referentiemerk-staging.sh` — JSON: `Tonys_Chocolonely_referentiemerk-2.json`.

| Item | Waarde |
| --- | --- |
| Tenant | Tony's Chocolonely |
| `clientId` | `84c535f4-40dc-06f6-bffe-d91b7ce1c303` |
| Login | `tony-s-chocolonely-hba@igisnext.com` |
| Branche | `chocolade_consument_nl` (markt-archetype Beminnaar) |
| Testposts | 10 nulmeting-posts, HBA-score **v1.6** |
| Intern | status uit JSON in `identity_intro` (referentiemerk, niet voor klanten) |

Stap 4 (40–60 echte posts) staat nog open.

## De vier tests

Draai elke test twee keer en leg uitkomsten vast zoals in `docs/hba-testmerken-resultaat.md`.

### Test 1. De tien testposts

Zelfde matrix en oordeelslogica als in ronde 2.

**Klaar als:** posts 4 en 5 `constraint_breach` krijgen, en die flag bij geen enkele andere post verschijnt. Post 3 moet `category_fallback` krijgen. Posts 6 en 8 moeten neutraal zijn zonder alarm. Post 10 staat op review.

### Test 2. Echte content

Score de 40 tot 60 echte posts.

**Klaar als:**
- geen enkele echte post `constraint_breach` krijgt;
- posts over de keten, de premie, Open Chain, het FAIR-verslag of wetgeving niet als categorietaal worden gemarkeerd;
- product- en seizoensposts neutraal uitkomen, zonder alarm.

Rapporteer de verdeling: hoeveel posts hoog, neutraal en laag, en welke flags. Dit is ons beeld van hoe een merk dat het goed doet in de app uitkomt.

### Test 3. Blind

Dezelfde echte posts nog een keer, maar vervang "Tony's", "Tony's Chocolonely" en "Chocolonely" door "het merk". Laat Open Chain en de rest staan.

**Wat we meten:** of de score daalt als de naam weg is. Een gemiddeld verschil van meer dan 1 punt (schaal 0 tot 10) betekent dat het model op de bekendheid van het merk scoort en niet op de tekst. Dat is een bevinding, geen fout van jou.

### Test 4. Drift over tijd

Brand identity drift heeft twee richtingen: een merk raakt zijn eigen lijn kwijt, of het gaat samenvallen met de categorie. Een losse postscore meet dat niet. Daarvoor is een reeks nodig.

Bouw drie reeksen van twaalf maanden uit de echte content:

| Reeks | Wat je doet | Verwacht |
| --- | --- | --- |
| A | Echte content, ongewijzigd | Geen driftalarm |
| B | Vanaf maand 7 de helft van de ketenposts vervangen door `vervangers_categorie` | Alarm in de richting "samenvallen met de categorie", uiterlijk in maand 9 |
| C | In maand 10 twee posts vervangen door `vervangers_regelbreuk` | Direct alarm op de regel |

Heeft de app nog geen weergave of alarm voor drift over tijd, rapporteer dan wat er wel is (merkcijfer, gemiddelden per maand). Dan weten we wat er nog moet komen.

### Themakeuze (los van de vier tests)

Geef de app de zes thema's uit `themakeuze_test` en leg per thema het advies vast naast de verwachting.

## Markthygiëne: het format voor elke branche

De vier velden uit het tabblad Branches blijven gelijk. Twee aanvullingen:

| Veld | Wat erin staat |
| --- | --- |
| `hygienefactoren` | Wat je moet hebben om mee te doen: dingen, geen zinnen. |
| `categorietaal` | Letterlijke claims die iedereen in de branche doet: 15 tot 25 stuks, in de woorden van de markt. |
| `markt_archetype` | Het gangbare archetype van de branche. |
| `differentiatie_assen` | Per as de gangbare positie en de afwijkende. |
| `wordt_onderscheidend_als` (nieuw, per item) | Wanneer een hygiënewoord wel onderscheidend wordt: als het gekoppeld is aan een handeling die geld kost en te controleren is. |
| `bronnen` (nieuw) | Waar de claims vandaan komen. |

Branchetaal hoort voortaan hier en niet in de verboden logica van het merk. De verboden logica van een merk bevat alleen de logica van de tegenkracht en handelingen tegen de regel. Dat haalt de verwarring weg die in ronde 2 tot `constraint_breach` op categorietaal leidde.

## Afstemming met de kalibratiemodule

Vier dingen lopen nu uit elkaar. Graag oplossen voordat de kalibratierun gebouwd wordt.

1. **Schaal.** De ijkset en de go-live-criteria rekenen op 0 tot 100. De tekstscore in de app loopt van 0 tot 10, en het merkcijfer deelt nog door 5. Voorstel: in de kalibratie geldt tekstscore maal 10, en T5 uit ronde 2 lost het merkcijfer op.
2. **Twee invoerschema's.** De ijkset gebruikt de wizardvelden (`wie`, `doel`, `wat_anders`, `niet_in_geloven`, `regels`). De testmerken gebruiken `brand_narrative`. De kalibratierun moet dezelfde invoer gebruiken als de app. Leg vast welke vertaling de app tussen die twee maakt, en gebruik die in de run.
3. **Signaalnamen.** De ijkset noemt signalen die de app nog niet zo teruggeeft. Vul de rechterkolom in met de echte veldnamen:

   | Signaal in de ijkset | Waarschijnlijk in de app |
   | --- | --- |
   | `hygienefactor_gedetecteerd`, `categorietaal_gedetecteerd` | `category_fallback`, of de hygiënetest in de architectuurcheck |
   | `risico_flag_tegenspraak_met_regel` | `constraint_breach` |
   | `strawman_gedetecteerd` | strawman-test in de architectuurcheck |
   | `regel_met_commerciele_prijs_herkend` | beoordeling van de regels in de architectuurcheck |
   | `erosion_alert` | alarm in de tracker |

4. **Waar de definities staan.** Ronde 2 zet de definities (regelbreuk, flags, archetype-regel) in een gedeelde constante. Stap 2 van de kalibratiemodule haalt de HBA-laag uit de code. Zet de teksten uit ronde 2 daarom meteen zo neer dat ze bij stap 2 de eerste inhoud van de configuratieopslag worden.

Daarnaast stelt Rogier twee nieuwe soorten ijkgevallen voor: `themakeuze` en `drift_reeks`. De ijkset test nu wel de architectuurcheck, de tekstscore, het schrijfadvies en de compressietest, maar niet het advies vooraf en niet de drift over tijd. Juist drift is de kern van wat de app belooft.
