# HBA ronde 4 (v1.9): leidingen, schrijver en regelbreuk

Rogier Heijning, 12 oktober 2026

---

## Voor Erik

Erik, v1.8.1 is een flinke stap vooruit. Wat goed gaat:

- 12 van de 17 posts zijn 5/5 stabiel.
- Er zijn 0 PHP-correcties, en dus 0 overrules.
- Functionele onderdelen zoals CTA, datum en link krijgen geen flags meer.
- De neutrale posts en de bloemenpost (Duinroos post 5) kloppen nu.
- `category_fallback` staat nog op 6 van de 17 posts, waar het eerst 13 waren.

Er staan drie dingen open. Die pakken we in deze ronde aan, in deze volgorde.

**1. De leidingen werken nog niet.** Bij de verleidingstest (V0) waren `self_review` en de HBA-hint in alle drie de gevallen `null`. CE-04 geeft in n8n nog steeds "tool-output niet gelezen". Zolang die leidingen leeg blijven, weten we niet of de schrijver zichzelf controleert. Dit gaat vóór alle promptwerk. In ronde 3 is de volgorde omgedraaid: het promptwerk voor v1.8 begon voordat V0 groen was. Dat willen we nu niet nog een keer.

**2. De schrijver kiest nog niet voor de regel.** In de verleidingstest kregen de drie merken elk een thema dat botst met hun eigen regel:

- Brandpunt verdedigde de regel ("Wij doen daar niet aan mee"). Dat is precies goed.
- Stelwerk kondigde het ERP-partnerschap gewoon aan.
- Duinroos schreef dat de wachtlijst was weggewerkt.

De scoring zag beide fouten wel, maar de schrijver had ze niet mogen maken. Een merk dat zijn eigen regel breekt omdat het thema dat vraagt, is precies wat de HBA-laag moet voorkomen.

**3. Er komt soms nog een valse regelbreuk uit.** Twee posts krijgen in 2 van de 5 runs een regelbreuk die er niet is:

- **Stelwerk post 2:** "Wij zijn uw betrouwbare partner voor ERP-implementaties." Dit is categorietaal, geen regelbreuk. Het woord "partner" is geen partnerschap met een leverancier.
- **Duinroos post 6:** "Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg." Dit is redeneren als de tegenkracht (de klok bepaalt de dag), geen regelbreuk. Duinroos doet hier niets wat de regel verbiedt.

Het patroon is in beide gevallen hetzelfde. Het model ziet een woord of claim die in de buurt van de regel komt, en noemt dat een breuk. Een regelbreuk is echter altijd een handeling: iets wat het merk doet, aanbiedt, tekent of als werkwijze heeft. De oplossing heeft twee delen: een verplichte uitleg, en een korte tweede controle als er een breuk wordt gemeld.

Daarnaast zijn er een paar kleine fixes in de testset.

Je kunt dit hele document aan Cursor geven. Werk stap voor stap en maak per stap één commit. Zet het pas op staging nadat ik "ja" heb gezegd, en niet op productie.

Gebruik Opus 5 voor het promptwerk (stap 2 en 3) en Composer 2.5 voor de rest.

---

## Opdracht voor Cursor

Context: dit is de HBA-laag in ContentFlow. De huidige promptversie is v1.8.1 en het model is claude-sonnet-4-6. Na deze ronde wordt de versie **v1.9**. Het model blijft hetzelfde.

Lees eerst deze bestanden:

- `hba-regelbreuk-definitie.txt`
- `hba-flags-definitie.txt`
- `hba-hoofdoordeel-definitie.txt`
- `stabiliteit-v1.8.1.md`
- `hba-v0-testresultaat.md`

Werk de stappen in volgorde af. Ga pas naar de volgende stap als de klaar-criteria van de huidige stap gehaald zijn. Maak per stap één commit, met het stapnummer in de commit-message.

### Stap 1: Leidingen eerst (self_review, HBA-hint, CE-04)

Doel: de drie controles draaien echt en vullen hun velden.

1. **CE-03 self_review en hbaHint.** Zoek uit waarom deze velden `null` zijn bij de drie V0-runs. Controleer deze vier punten:
   - Wordt de self_review-stap aangeroepen?
   - Komt de output van het model terug?
   - Wordt die output geparsed?
   - Wordt het resultaat opgeslagen op het artikel?

   Log per stap wat er gebeurt. Repareer de plek waar het misgaat.
2. **CE-04 in n8n (workflow `yasAXtlH4cjWGWKF`).** De melding "tool-output niet gelezen" moet weg. Erik opent eerst zelf een mislukte run in n8n en kijkt naar drie dingen:
   - de ruwe modeloutput: is er een `tool_use`-blok, en wat is de `stop_reason`?
   - wat er in de node Build Feedback Rows binnenkomt;
   - of de parser het juiste pad in de response leest.

   Veelvoorkomende oorzaken, om te checken:
   - `stop_reason` is `max_tokens`, dus het tool-blok is afgekapt;
   - het model antwoordt in tekst in plaats van via de tool, dus `tool_choice` staat niet geforceerd;
   - de parser leest `content[0]` terwijl het tool-blok op een andere index staat.
3. Voeg een duidelijke foutmelding toe voor het geval de tool-output toch ontbreekt, met daarin de `stop_reason` en de eerste 300 tekens van de ruwe response. Een stille `null` mag niet meer voorkomen.
4. Draai de verleidingstest (V0) opnieuw voor Brandpunt, Stelwerk en Duinroos, met dezelfde thema's als vorige keer.

**Klaar als:**
- `self_review` en `hbaHint` zijn gevuld bij alle drie de V0-runs;
- CE-04 levert bij alle drie de V0-runs feedback op, zonder "tool-output niet gelezen";
- de feedback van CE-04 noemt bij Stelwerk en Duinroos de regel en citeert de zin die hem breekt.

### Stap 2: De schrijver kiest voor de regel (CE-03 en de themakeuze)

Doel: als een thema botst met de geloofwaardigheidstest of de verboden logica van het merk, schrijft CE-03 geen post die de regel breekt.

1. Voeg vóór het schrijven in CE-03 een conflictcheck toe. Leg het thema naast `geloofwaardigheidstest` en `verboden_logica`, en vraag: "Kan dit thema alleen goed uitgewerkt worden door iets te doen of te beloven wat de regel verbiedt?"
2. Als het antwoord ja is, heeft CE-03 twee mogelijkheden. Er moet altijd één van de twee gebeuren:
   - **a. Schrijf de versie die de regel verdedigt.** Gebruik hetzelfde thema, maar vanuit de regel. Brandpunt deed dit goed bij Black Friday: "Wij doen daar niet aan mee."
     - Voor Duinroos betekent dit bij het wachtlijst-thema: schrijf over de doorverwijzingen, niet over een weggewerkte wachtlijst.
     - Voor Stelwerk betekent dit bij het ERP-thema: schrijf over waarom Stelwerk geen partnerschappen met leveranciers aangaat, niet over een nieuw partnerschap.
   - **b. Meld het conflict vóór het schrijven.** Toon de gebruiker in gewone taal: "Dit thema botst met een grens die je zelf hebt gezet: [regel in klanttaal]. Wil je dat ik het schrijf vanuit je regel?" Gebruik geen jargon (zie de woordenlijst van V5).

   Kies standaard voor a. Gebruik b alleen als er geen geloofwaardige versie vanuit de regel te maken is.
3. Leg in `self_review` vast:
   - of er een conflict was;
   - welke keuze is gemaakt (a of b);
   - waarom.
4. Laat dezelfde conflictcheck ook draaien bij **Snel artikel** en bij **eigen thema's** van de klant, en niet alleen bij thema's die CE-01 voorstelt. Het advies vooraf (de themakeuze) moet op alle drie de routes gelden.
5. Draai de verleidingstest (V0) opnieuw.

**Klaar als:**
- bij alle drie de merken verdedigt de post de regel (a), of is het conflict vooraf gemeld (b);
- geen enkele V0-post krijgt hoofdoordeel `regelbreuk`;
- `self_review` vermeldt het conflict en de gemaakte keuze;
- een test via Snel artikel, met het Duinroos-wachtlijst-thema, geeft hetzelfde gedrag.

### Stap 3: Regelbreuk scherper (CE-03 scoring)

Doel: geen valse regelbreuk meer op woorden of claims.

1. **Verplichte uitleg.** Voeg het veld `constraint_breach_uitleg` toe. Als `constraint_breach` gezet wordt, moet dit veld beantwoorden: "Welke handeling uit de regel doet het merk volgens deze zin toch?" Zonder ingevulde uitleg vervalt de flag. Doe dit in de code, niet alleen in de prompt.
2. **Definitie aanscherpen** in `hba-regelbreuk-definitie.txt`. Voeg toe:
   - Een regelbreuk is altijd een **handeling**: iets wat het merk doet, aanbiedt, tekent of als werkwijze heeft, en wat de regel verbiedt.
   - Een woord of een claim is nooit een regelbreuk. "Partner", "snel", "efficiënt" of "betrouwbaar" breken geen regel. Ze kunnen wel categorietaal zijn, of logica van de tegenkracht.
   - Voorbeeld fout: "Wij zijn uw betrouwbare partner" is geen regelbreuk bij Stelwerk. Er wordt geen partnerschap met een leverancier gemeld. Dit is categorietaal.
   - Voorbeeld fout: "Dankzij slimmere routes zijn onze zorgverleners minder onderweg" is geen regelbreuk bij Duinroos. Duinroos doet niets wat de regel verbiedt. Dit is de logica van de tegenkracht.
   - Voorbeeld goed: "Wij zijn trots op ons nieuwe partnerschap met [ERP-leverancier]" is wel een regelbreuk bij Stelwerk. Het merk gaat een partnerschap aan.
3. **Verificatie-call.** Als `constraint_breach` gezet is, doe dan een tweede korte LLM-call met alleen:
   - de regel (`geloofwaardigheidstest`);
   - de geciteerde zin;
   - de `constraint_breach_uitleg`.

   De vraag aan het model is: "Doet het merk volgens deze zin aantoonbaar wat de regel verbiedt? Antwoord ja of nee, met één zin reden." Bij nee:
   - vervalt `constraint_breach`;
   - wordt het hoofdoordeel opnieuw bepaald via de bestaande V1-regel (eerst tegenkracht, dan categorietaal, anders neutraal);
   - wordt de reden gelogd in `constraint_breach_verificatie`.

   Gebruik hetzelfde model, temperatuur 0 en een korte max_tokens.
4. Verhoog de promptversie naar **v1.9**.

**Klaar als:**
- Stelwerk post 2 is 5/5 `categorietaal`;
- Duinroos post 6 is 5/5 `tegenkracht`;
- alle "Breekt de regel"-posts (post 3 van elk merk) blijven 5/5 `regelbreuk`, dus de verificatie gooit geen echte breuken weg;
- elke `constraint_breach` in de 5×17-run heeft een gevulde uitleg en een verificatie met "ja".

### Stap 4: Testset opschonen

1. **Duinroos post 6:** zet `verwacht_hoofdoordeel` op `tegenkracht`. Nu staat er `null`.
2. **Neutrale posts:** maak post 4 van Duinroos en Stelwerk niet-ambigu, met `verwacht_hoofdoordeel: neutraal`. Ze scoren al 5/5 neutraal en tellen dan gewoon mee.
3. **Brandpunt branche:** zet de branche in de markthygiëne-instellingen van `retail` naar `koffie`. Controleer dat de branche_library voor koffie geladen wordt, met de hygiënefactoren "vers gebrand" en "duurzaam".
4. **Brandpunt post 6** (Mechanisme dat markthygiëne is): die blijft `review`. Hij scoort 5/5 `op_merk`, en dat is een kalibratievraag voor Rogier, geen bug.
5. Werk het stabiliteitsscript bij zodat het rapport ook toont:
   - het aantal geverifieerde en het aantal verworpen `constraint_breach`;
   - per V0-run of self_review en hint gevuld zijn.

**Klaar als:**
- de testset-JSONs zijn bijgewerkt;
- het script draait zonder fouten;
- het rapport heeft 2 review-posts minder dan v1.8.1.

### Stap 5: Volledige stabiliteitsrun

Draai 5 runs × 17 posts op v1.9.

**Klaar-criteria voor de hele ronde:**

- [ ] Post 2 en post 3 van alle drie de merken zijn 5/5 goed.
- [ ] Geen enkele valse regelbreuk in 85 scores.
- [ ] Duinroos post 6 is 5/5 `tegenkracht`.
- [ ] De verleidingstest is voor alle drie de merken goed: de post verdedigt de regel of het conflict is vooraf gemeld.
- [ ] `self_review` en `hbaHint` zijn gevuld in alle V0-runs.
- [ ] CE-04 noemt de regel met een citaat bij een regelbreuk en geeft geen "tool-output niet gelezen".
- [ ] `hoofdoordeel_gecorrigeerd` blijft 0%.
- [ ] Er staan 0 flags op functionele onderdelen.
- [ ] De BC-spreiding is maximaal 1 op alle posts.
- [ ] Er is geen achteruitgang op de 12 posts die in v1.8.1 al stabiel waren.

---

## Afronden

Lever het volgende aan Rogier:

1. `stabiliteit-v1.9.md` plus `stabiliteit-v1.9-5runs.json`.
2. `hba-v1.9-verslag-roger.md`, met:
   - per stap wat er veranderd is, in gewone taal;
   - de oorzaak van de lege self_review, hint en CE-04-output, en hoe die is opgelost;
   - de drie V0-posts van nu, in volledige tekst, met self_review en CE-04-feedback erbij;
   - een lijst van alle verworpen `constraint_breach` met de reden;
   - wat er niet gelukt is, en waarom.
3. De bijgewerkte definitiebestanden: `hba-regelbreuk-definitie.txt` en `hba-flags-definitie.txt`.
4. Werkende klantpreview-links (`/article/{articleId}?hbaKlant=1`) voor de drie V0-posts.

Regels:

- Maak één commit per stap.
- Zet niets op staging zonder een "ja" van Erik.
- Zet niets op productie.
- Lukt een klaar-criterium niet na twee pogingen? Stop dan en meld het, met de ruwe output erbij. Ga niet verder schuiven aan de prompt.
