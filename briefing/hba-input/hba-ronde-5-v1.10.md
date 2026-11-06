# HBA ronde 5 (v1.10): bewijs, premisse en verzonnen feiten

Rogier Heijning, 13 oktober 2026

---

## Voor Erik

Erik, v1.9 is een grote stap. De scoring is bijna af:

- 16 van de 17 posts zijn 5/5 stabiel. Er zijn geen instabiele posts meer, en ook geen foute.
- Stelwerk post 2 en Duinroos post 6 zijn nu 5/5.
- Er zijn geen valse regelbreuken meer. Alle 15 echte regelbreuken blijven staan.
- Er zijn 0 PHP-correcties nodig.
- self_review en de hint werken, en de schrijver kiest bij alle drie de verleidingsthema's voor de regel.

Deze ronde is kleiner dan de vorige. Er staan drie dingen open.

**1. Twee dingen zijn nog niet aangetoond.**

- **CE-04.** Volgens het verslag werkt CE-04 sinds de hertest van 12 oktober. De JSON in het pakket is van 13 oktober, 10:26 UTC, dus van ná die fix. Daarin staat bij alle drie de merken nog "tool-output niet gelezen", met als ruwe output `{"success":true}`. Er is dus nog geen machineleesbaar bewijs dat het werkt.
- **De controlecall op regelbreuken.** Die heeft in de run niets afgewezen. Dat kon ook niet: er waren geen valse regelbreuken meer, want de scherpere definitie had dat werk al gedaan. De controle is dus nog niet getest op de gevallen waarvoor hij bedoeld is. Je hebt er bovendien de verboden logica aan meegegeven. Daar staan precies de woorden in waarop het eerder misging ("partnerschap en ontzorgen", "efficiëntie en productiviteit als prestatie"). Ik wil zien dat de controle op die gevallen "nee" zegt. Het veld `constraint_breach_verificatie` staat ook niet in de ruwe JSON, dus ik kan niet nagaan wat de controle per geval antwoordde.

**2. De schrijver vervangt het middel, maar houdt het resultaat dat het thema belooft.** De Brandpunt-tekst is goed. Bij de andere twee gaat het mis:

- **Stelwerk** opent met "Wij werken met een ERP-leverancier samen", zonder contract en zonder commissie. Volgens de definitie is dat geen regelbreuk. Maar de schrijver neemt een samenwerking aan die het merk niet heeft, en verzint "jaren ervaring met hoe dit systeem zich gedraagt". Een adviseur die zich aan één systeem verbindt, ondergraaft het keuzerapport met drie opties.
- **Duinroos** kopt "Onze wachtlijst is in één maand opgelost". De oplossing is één doorverwezen cliënt, waarna "de overige mensen op de wachtlijst terecht konden bij zorgverleners die ze al kenden". Dat kan niet. Met één doorverwijzing werk je geen wachtlijst weg, en mensen op een wachtlijst kennen hun zorgverleners nog niet. De kop viert bovendien nog steeds een wachtlijst die in een maand is weggewerkt.

In beide gevallen neemt de schrijver de premisse van het thema over als feit. Hij verandert alleen het middel. De goede versie gaat over de regel zelf: waarom Stelwerk geen partnerschappen aangaat, en waarom Duinroos doorverwijst in plaats van de wachtlijst weg te werken.

De scorer gaf beide teksten op_merk, en Duinroos zelfs merkbijdrage 8 zonder flags. Dat is te mild: de kernbewering van de tekst is niet onderbouwd en klopt niet.

**3. De hints verzinnen feiten.** Bij Duinroos voegt de herschreven passage toe "waar we zelf het eerste gesprek voor hebben geregeld". Bij Brandpunt staat er "gebrand op hetzelfde moment als op elke andere dag", en dat zegt niets. Een hint mag een tekst concreter maken, maar alleen met feiten die het merk echt heeft. Ontbreekt zo'n feit, dan verzint de hint het niet, maar vraagt hij erom: hij zet een invulveld in de tekst en zegt erbij wat voor feit hij zoekt. Dat leert de klant ook meteen welk bewijs zijn merk sterker maakt.

**Wat blijft zoals het is.** Brandpunt post 6 (mechanisme dat markthygiëne is) blijft review en blokkeert niets. Dat neem ik mee in de kalibratiemodule.

**Eerst de pilot, dan de rest.** We willen snel de eerste partijen met de app laten werken. Daarom is deze ronde in twee delen gesplitst:

- **Deel A, vóór de pilot:** CE-04 aantoonbaar werkend (stap 1, punt 1), de premissecheck in de schrijver (stap 2) en hints die om feiten vragen in plaats van ze te verzinnen (stap 3). Dit moet af vóór er klanten op komen. Een klant mag geen tekst krijgen met een bewering over zijn eigen bedrijf die niet klopt, en geen review die "mislukt" meldt.
- **Deel B, tijdens de pilot:** de test van de controlecall, de aanscherping van de scorer, de testset en de volledige run (stap 1 punt 2 tot en met 4, en stap 4 tot en met 6). Dat is fijnslijpen. Het mag doorlopen terwijl de eerste partijen al werken.

Wanneer de pilot start en op welke omgeving, beslissen jij en ik samen. Dat staat los van deze opdracht.

Je kunt dit hele document aan Cursor geven. Werk stap voor stap en maak per stap één commit. Zet het pas op staging nadat ik "ja" heb gezegd, en niet op productie.

Gebruik Opus 5 voor het promptwerk (stap 2, 3 en 4) en Composer 2.5 voor de rest.

---

## Opdracht voor Cursor

Context: dit is de HBA-laag in ContentFlow. De huidige promptversie is v1.9 en het model is claude-sonnet-4-6. Na deze ronde wordt de versie **v1.10**. Het model blijft hetzelfde.

Lees eerst deze bestanden:

- `hba-v1.9-verslag-roger.md`, met de drie V0-teksten in §4;
- `hba-regelbreuk-definitie.txt`
- `hba-flags-definitie.txt`
- `hba-verbeteradvies-definitie.txt`
- `stabiliteit-v1.9.md`
- `hba-v0-testresultaat.json`

De opdracht bestaat uit twee delen. Doe deel A eerst en rapporteer dan aan Erik, vóór je aan deel B begint.

- **Deel A (vóór de pilot):** stap 1 punt 1 (CE-04), stap 2 en stap 3. Draai daarna de verleidingstest (V0) en CE-04 opnieuw.
- **Deel B (tijdens de pilot):** stap 1 punt 2 tot en met 4, en stap 4, 5 en 6.

Ga binnen een deel pas naar de volgende stap als de klaar-criteria van de huidige stap gehaald zijn. Maak per stap één commit, met het stapnummer in de commit-message.

**Klaar voor de pilot (na deel A):**

- [ ] CE-04 via het script: een feedbackregel met regel en citaat bij alle drie de post-3's, zonder "Review mislukt".
- [ ] De verleidingstest: geen tekst neemt een premisse over die het merk niet heeft, en geen tekst verzint feiten.
- [ ] De hints: geen verzonnen feiten. Waar een feit ontbreekt, staat een invulveld met voorbeelden.
- [ ] Een tekst met een open invulveld kan niet gepubliceerd worden.
- [ ] Geen achteruitgang: draai 1 run × 17 posts als steekproef, en controleer dat de 16 stabiele posts goed blijven.

### Stap 1: Bewijs eerst (CE-04 en de controlecall)

Punt 1 (CE-04) hoort bij deel A en gaat vóór het promptwerk. Punt 2 tot en met 4 horen bij deel B.

1. **CE-04.** Draai CE-04 opnieuw via het script, op post 3 van alle drie de merken. Gebruik dezelfde route als `hba-v0-testresultaat.json`, niet handmatig. Komt er weer "tool-output niet gelezen" uit, met ruwe output `{"success":true}`, dan leest Build Feedback Rows nog de output van een node die alleen een status teruggeeft. Controleer dan:
   - of de workflow op staging de versie met de fix is. Vergelijk de versie in n8n met die in de repo;
   - of de route via het script anders loopt dan de handmatige test, bijvoorbeeld via een andere webhook of een andere workflow-id.
2. **Logging van de controlecall.** Schrijf `constraint_breach_verificatie` (ja/nee plus de reden) weg in `raw_llm_response`, en neem het op in de stabiliteits-JSON.
3. **Test de controlecall los.** Geef hem deze zes gevallen, met de regel en de citaten zoals ze in eerdere runs voorkwamen:

   | Merk | Citaat | Verwacht |
   | --- | --- | --- |
   | Stelwerk | "Wij zijn uw betrouwbare partner voor ERP-implementaties." | nee |
   | Stelwerk | "Wij zijn volledig onafhankelijk." | nee |
   | Duinroos | "Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg." | nee |
   | Brandpunt | "Black Friday: alle zakken van vorige maand voor de halve prijs!" | ja |
   | Stelwerk | "Stelwerk is nu officieel gecertificeerd resellerpartner van een groot ERP-merk!" | ja |
   | Duinroos | "door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst in een maand weggewerkt" | ja |

   Gebruik bij de drie nee-gevallen als `constraint_breach_uitleg` de uitleg die het model zelf gaf, of een vergelijkbare. Draai elk geval 5 keer.
4. Zegt de controle "ja" op een van de nee-gevallen, pas dan de input aan:
   - geef de verboden logica alleen mee met de zin "De verboden logica telt alleen als het verboden punt een handeling of aanbod beschrijft. Een woord of claim uit die lijst is nooit een regelbreuk.";
   - lukt het daarmee niet, haal dan de verboden logica uit de controle en geef alleen de geloofwaardigheidstest mee.

   Test daarna opnieuw.

**Klaar als:**
- CE-04 levert via het script bij alle drie de post-3's een feedbackregel die de regel noemt en de zin citeert, zichtbaar in `regelFeedback` in de JSON;
- de controlecall zegt 5/5 "nee" op de drie valse gevallen en 5/5 "ja" op de drie echte;
- `constraint_breach_verificatie` staat in de ruwe data.

### Stap 2: Premissecheck in de schrijver (CE-03)

Doel: de schrijver neemt geen feit over het merk aan dat het thema voorstelt, maar dat het merk niet heeft.

1. Breid de conflictcheck uit stap 2 van ronde 4 uit met een tweede vraag:

   ```
   PREMISSE: neemt het thema iets aan over het merk als feit (een resultaat,
   een samenwerking, een prestatie, een cijfer) dat niet in de brand narrative,
   de bewijsvelden of de research staat? Of is dat resultaat alleen te halen
   met een middel dat de regel verbiedt?
   Zo ja: neem die premisse niet over. Schrijf over de regel zelf: waarom het
   merk dit niet doet, en wat het in plaats daarvan doet. Vervang nooit alleen
   het middel terwijl je het resultaat uit het thema laat staan.
   ```

2. Voeg deze voorbeelden toe aan het fragment:
   - Fout: "Onze wachtlijst is in één maand opgelost, door één cliënt door te verwijzen." Dat is hetzelfde resultaat met een ander middel, en het klopt niet.
   - Goed: "We hebben een wachtlijst. Daarom hebben we vorige maand twee nieuwe aanvragen doorverwezen. Liever dat dan een vijfde gezicht aan de deur."
   - Fout: "Wij werken samen met een ERP-leverancier, zonder contract." Het merk heeft die samenwerking niet.
   - Goed: "Er werd ons een partnerschap aangeboden. We hebben nee gezegd, en dit is waarom."
3. **Geen verzonnen feiten.** Voeg aan de schrijfprompt toe:

   ```
   Verzin geen feiten over het merk: geen ervaring, samenwerkingen, aantallen,
   klanten of resultaten die niet in de brand narrative, de bewijsvelden of de
   research staan. Ontbreekt een concreet feit, schrijf dan over de werkwijze
   of de regel zoals die in de brand narrative staat.
   ```

4. Leg in `self_review` vast:
   - `themePremisse`: of het thema een premisse had die het merk niet heeft;
   - wat de schrijver daarmee gedaan heeft.
5. Draai de verleidingstest (V0) opnieuw voor alle drie de merken.

**Klaar als:**
- geen V0-tekst beweert een resultaat of samenwerking die niet in de brand narrative of de bewijsvelden staat;
- Stelwerk schrijft niet dat het met een leverancier samenwerkt;
- Duinroos schrijft niet dat de wachtlijst is opgelost of weggewerkt;
- Brandpunt blijft even goed als in v1.9;
- `self_review` vermeldt de premisse en wat ermee gedaan is.

### Stap 3: Hints vragen om feiten in plaats van ze te verzinnen (HBA-hint)

Doel: als een tekst sterker wordt van een concreet feit dat de app niet kent, verzint de hint dat feit niet. De hint vraagt de klant erom, en zegt erbij wat voor feit hij zoekt.

1. Voeg aan de hintprompt toe:

   ```
   Een herschreven passage (passageNa) mag alleen feiten bevatten die al in
   de tekst, de bewijsvelden of de research staan. Voeg nooit zelf nieuwe
   handelingen, aantallen, namen, voorbeelden of beloftes toe.

   Wordt de passage sterker met een concreet feit dat je niet hebt, zet dan
   op die plek een invulveld tussen rechte haken. Beschrijf in het invulveld
   welk feit je zoekt, in gewone taal en zo specifiek mogelijk, bijvoorbeeld:
   [hoeveel aanvragen jullie vorige maand hebben doorverwezen].
   Kies het soort feit bij voorkeur uit de bewijsvelden van het merk.
   Zeg in de aanwijzing in één zin welk feit je zoekt en waarom het de tekst
   sterker maakt, met één of twee voorbeelden van wat het kan zijn.
   Gebruik hoogstens twee invulvelden per hint.
   ```

2. Neem deze voorbeelden op in het fragment. Ze komen uit de V0-run van v1.9:

   | Merk | Wat de hint verzon | Wat de hint wel had moeten doen |
   | --- | --- | --- |
   | Duinroos | "waar we zelf het eerste gesprek voor hebben geregeld" | "...bij een aanbieder die wél ruimte had. [Wat jullie doen om iemand op weg te helpen bij die andere aanbieder.]" Aanwijzing: "Laat zien wat er gebeurt met iemand die jullie doorverwijzen, bijvoorbeeld of jullie zelf het contact leggen of de overdracht regelen." |
   | Brandpunt | "gebrand op hetzelfde moment als op elke andere dag" | "Op de drukste kortingsdag van het jaar verandert er bij ons dus niets. [Hoeveel kilo koffie er vorige maand naar de voedselbank ging.]" Aanwijzing: "Een cijfer maakt de regel tastbaar, bijvoorbeeld hoeveel kilo er vorige maand de winkel uit ging. Dat cijfer houden jullie al bij op de website." |
   | Stelwerk | (geen verzonnen feit, wel een abstracte slotzin) | "Die afstand heeft een prijs. [Een opdracht waarin jullie adviseerden dit systeem níet te kiezen, en wat dat de klant opleverde.]" Aanwijzing: "Eén echt voorbeeld van een advies dat jullie omzet kostte, zegt meer dan de uitleg." |

3. **Weergave in de app.** Een invulveld tussen rechte haken moet opvallen in de editor, bijvoorbeeld met een gekleurde markering. De klant kan een hint met een niet-ingevuld invulveld niet als "toegepast" markeren en niet publiceren zolang de haken er nog in staan. Toon bij publiceren een melding in gewone taal: "Er staat nog een stukje tussen haken dat je zelf moet invullen."
4. Leg in de hint-data vast:
   - `invulvelden`: de lijst met beschrijvingen;
   - `bewijsveld_bron`: welk bewijsveld als voorbeeld diende, of `null`.
5. Draai de drie V0-hints opnieuw en controleer ze met de hand.

**Klaar als:**
- geen passageNa bevat een feit dat niet in de tekst, de bewijsvelden of de research staat;
- waar een feit ontbreekt, staat een invulveld met een specifieke beschrijving, en noemt de aanwijzing een of twee voorbeelden;
- minstens één van de drie V0-hints haalt het voorbeeld uit een bewijsveld van het merk;
- een tekst met een niet-ingevuld invulveld kan op staging niet gepubliceerd worden.

### Stap 4: Scorer ziet niet-onderbouwde kernbeweringen (CE-03 scoring)

Doel: een tekst die het merk lijkt te verdedigen, maar waarvan de kernbewering niet klopt of niet onderbouwd is, krijgt niet ongemerkt op_merk met een hoge merkbijdrage.

1. Voeg aan de definitie van `proof_gap` in `hba-flags-definitie.txt` toe:

   ```
   Meld proof_gap ook als de kernbewering van de tekst (meestal de kop of de
   openingszin) een resultaat over het merk claimt dat de rest van de tekst
   niet verklaart, of dat logisch niet kan volgen uit wat de tekst beschrijft.
   ```

2. Voeg aan de definitie van `foe_vervaging` toe:

   ```
   Het vieren van een resultaat dat de tegenkracht meet (snelheid, volume,
   capaciteit, een weggewerkte wachtlijst) is foe_vervaging, ook als de rest
   van de tekst de regel verdedigt. Noem dan de zin die het resultaat viert.
   ```

3. Voeg aan de scoreprompt toe: een tekst met `proof_gap` op de kernbewering krijgt een merkbijdrage van hoogstens 5.
4. Voeg de twee oude V0-teksten (Stelwerk en Duinroos uit v1.9, zie het verslag §4) toe als Duinroos post 7 en Stelwerk post 6. Zet ze op `ambigu: true`, met deze verwachtingen:
   - Duinroos post 7: `proof_gap` op "Onze wachtlijst is in één maand opgelost", merkbijdrage hoogstens 5;
   - Stelwerk post 6: `proof_gap` op de samenwerkings- of ervaringszin, merkbijdrage hoogstens 6.
5. Verhoog de promptversie naar **v1.10**.

**Klaar als:**
- de twee nieuwe testposts krijgen in 5/5 runs de verwachte `proof_gap`, en de merkbijdrage blijft onder de grens;
- er is geen achteruitgang op de 16 posts die in v1.9 stabiel goed waren. Let vooral op post 1 en Brandpunt post 5: die mogen geen extra `proof_gap` krijgen op hun kernzin.

### Stap 5: Testset

1. **Brandpunt post 3.** Verander "alle zakken van vorige maand" in "alle zakken van twee maanden geleden". Koffie van vorige maand kan jonger zijn dan zes weken. In v1.9 moest het model in zijn uitleg de regel daarom verbuigen tot "koffie die de grens nadert". Dat staat niet in de regel.
2. **Regel goed weergeven.** Voeg aan de regelbreukdefinitie toe dat ook `constraint_breach_uitleg` de regel niet anders mag weergeven dan hij in de geloofwaardigheidstest staat. Dat is dezelfde eis als in het verbeteradvies.
3. **Functionele flags.** Controleer dat de PHP-fix voor de telling (CTA-zin na het merkdeel) in de nieuwe run 0 meldingen oplevert.

**Klaar als:**
- de testset-JSONs zijn bijgewerkt en opnieuw geïmporteerd;
- Brandpunt post 3 blijft 5/5 `regelbreuk`, met een uitleg die de zes-wekenregel correct weergeeft.

### Stap 6: Volledige stabiliteitsrun

Draai 5 runs × 19 posts op v1.10 (17 + 2 nieuwe). Draai daarna V0 en CE-04 opnieuw.

**Klaar-criteria voor de hele ronde:**

- [ ] De 16 posts die in v1.9 stabiel goed waren, zijn dat nog steeds (5/5).
- [ ] Geen enkele valse regelbreuk in alle scores.
- [ ] De controlecall: 5/5 "nee" op de drie valse gevallen, 5/5 "ja" op de drie echte.
- [ ] `constraint_breach_verificatie` staat in de ruwe JSON.
- [ ] De nieuwe testposts 7 (Duinroos) en 6 (Stelwerk) krijgen `proof_gap`, en hun merkbijdrage blijft onder de grens.
- [ ] De verleidingstest: geen tekst neemt een premisse over die het merk niet heeft, en geen tekst verzint feiten.
- [ ] De hints: geen verzonnen feiten in passageNa. Waar een feit ontbreekt, staat een invulveld met voorbeelden, en een tekst met een open invulveld kan niet gepubliceerd worden.
- [ ] CE-04 via het script: een feedbackregel met regel en citaat bij alle drie de post-3's.
- [ ] `hoofdoordeel_gecorrigeerd` blijft 0%.
- [ ] Er staan 0 flags op functionele onderdelen.
- [ ] De BC-spreiding is maximaal 1 op alle posts.

---

## Afronden

Na deel A: een kort verslag aan Erik met de uitkomst van de checklist "Klaar voor de pilot", de drie nieuwe V0-teksten met hun hints, en het bewijs van CE-04.

Na deel B: lever het volgende aan Rogier:

1. `stabiliteit-v1.10.md` plus `stabiliteit-v1.10-5runs.json`, met `constraint_breach_verificatie` per geval.
2. `hba-v1.10-verslag-roger.md`, met:
   - per stap wat er veranderd is, in gewone taal;
   - de uitkomst van de losse test van de controlecall (zes gevallen × 5 runs), en of de input is aangepast;
   - de oorzaak van de CE-04-uitkomst in de JSON van 13 oktober, en het bewijs dat het nu via het script werkt;
   - de drie nieuwe V0-teksten in volledige tekst, met self_review (inclusief `themePremisse`), de hint en de score;
   - wat er niet gelukt is, en waarom.
3. De bijgewerkte definitiebestanden: regelbreuk, flags, verbeteradvies en het nieuwe fragment voor de premissecheck.
4. Werkende klantpreview-links voor de drie nieuwe V0-posts.

Regels:

- Maak één commit per stap.
- Zet niets op staging zonder een "ja" van Erik.
- Zet niets op productie.
- Lukt een klaar-criterium niet na twee pogingen? Stop dan en meld het, met de ruwe output erbij. Ga niet verder schuiven aan de prompt.
