# GEO-module voor de scan op brandidentitydrift.com: bouwspecificatie

22 oktober 2026, Rogier. Versie 1.0.

Dit document beschrijft alles wat de GEO-module moet doen, behalve het ontwerp. Het ontwerp volgt later; als referentie voor inhoud en volgorde dient `voorbeeldrapport-brandzaak.html`. De meetregels staan in `geo-werkwijze.md`. Bij twijfel geldt die werkwijze.

## Bijlagen in deze map

| Bestand | Waarvoor |
|---|---|
| `geo-module-bouwspec-2026-10-22.md` | Dit document: stappen, prompts, schema's, rekenregels, tests |
| `geo-werkwijze.md` | De meetregels van de GEO Identity Gap meter (kennisbestand) |
| `branche-88-koffiebranderijen.json` | Branche-JSON voor de testcase |
| `testset-brandzaak-houseblend-chatgpt-antwoorden.txt` | De 12 antwoorden die Rogier op 22-10-2026 handmatig in ChatGPT heeft verzameld |
| `testset-brandzaak-houseblend-gouden-scoring.json` | Rogiers en Claude's scoring van die 12 antwoorden: de verwachte uitkomst |
| `voorbeeldrapport-brandzaak.html` | Voorbeeld van het rapport, kaart "Vindbaarheid in AI" |

## 1. Wat de module doet

De bezoeker vult in de scan naast zijn website ook één productpagina in. De module:

1. leest de website en de productpagina;
2. haalt het eigen verhaal van het merk letterlijk van de site;
3. maakt 10 waardevragen en 2 controlevragen, zonder merknaam;
4. kijkt per vraag of de productpagina zelf antwoord geeft;
5. stelt de 12 vragen aan ChatGPT via de API van OpenAI, met webzoekfunctie;
6. scoort elk antwoord;
7. rekent de trechter, de AI-identiteitsscore en de matrix uit;
8. levert de gegevens voor de kaart "Vindbaarheid in AI" in het rapport.

De bezoeker wil direct een uitslag. Alles gebeurt in één run, zonder tweede meetronde.

**Buiten de scope:** de driftmeting van de posts, het formulier en de hosting. Die worden apart beschreven.

## 2. Invoer

| Veld | Verplicht | Opmerking |
|---|---|---|
| `website_url` | ja | Komt uit het scanformulier |
| `product_url` | ja | Veld "Eén productpagina" in het formulier |
| `branche_id` | ja | Gekozen uit `branchelijst.csv` |
| `merknaam` | ja | Uit het formulier |
| `email` | ja | Voor het rapport |

Is de branche niet onderzocht, of kiest de bezoeker "Andere branche", dan gebruikt de meting een snelle branche (zie `../Snelle-branche/snelle-branche-bouwspec-2026-10-22.md`). Het rapport zegt dan dat de branche snel is gemeten.

## 3. Stappen

Gebruik voor de stappen A, B, C, E en G het Claude-model dat ook in Brandflocks wordt gebruikt. Leg het model en de versie vast in de configuratie en sla ze bij elke scan op. Er geldt dezelfde bevriezingsregel als bij de HBA-pilot: wie het model of een prompt wijzigt, draait eerst de test uit paragraaf 7 opnieuw.

Laat elke Claude-stap zijn uitkomst als JSON geven en valideer die tegen het schema. Gaat de validatie mis, probeer het dan één keer opnieuw. Gaat het nog een keer mis, stop dan de scan met een foutmelding voor Rogier.

### Stap A. Pagina's lezen

- Lees de homepage, maximaal vier pagina's over het merk (zoals over ons, werkwijze, duurzaamheid of missie) en de productpagina.
- Kies die pagina's op linktekst en URL. Woorden als "over", "werkwijze", "verhaal", "missie", "duurzaam" en "about" wijzen erop.
- Bewaar per pagina de URL, de leesdatum en de platte tekst.
- Zet geen tekst uit afbeeldingen of video's om. De pagina-check telt alleen tekst.
- Respecteer robots.txt. Een pagina die niet laadt, komt in de beperkingen.

### Stap B. Eigen verhaal

**Prompt B (systeem):**

```
Je leest de website van een merk en haalt eruit wat het merk zelf over zichzelf zegt. Je werkt alleen met letterlijke tekst. Je vult niets aan, je leidt niets af en je doet geen aannames.

Deel elk citaat in onder een van vier kopjes:
- wat_je_doet: wat het merk maakt of levert, en voor wie;
- hoe_je_werkt: hoe het merk dat concreet doet, herkenbaar en herhaalbaar;
- wat_je_niet_doet: wat het merk bewust niet doet of afwijst;
- waar_je_voor_staat: waar het merk voor staat of naartoe wil.

Regels:
1. Elk citaat is letterlijk, maximaal 20 woorden, met de URL waar het staat.
2. Geef bij elk citaat de branchestatus volgens de meegegeven branche-JSON:
   - "hygiene": het citaat zegt in betekenis hetzelfde als een hygiënefactor of categorietaal met status hygiene;
   - "veelvoorkomend": idem voor status veelvoorkomend;
   - "minderheid": idem voor status minderheid;
   - "niet_in_branche": het staat niet in de branche-JSON.
3. Werk- en producttaal (technische termen uit werk_en_producttaal) is geen citaat.
4. Een citaat blijft onderscheidend als de concrete invulling verder gaat dan de hygiënefactor. "Vers gebrand" is hygiëne; "pas branden als jij bestelt, elke werkdag om 13:00" niet.
5. Geef ook de pay-off, de themaregel en andere vaste merkzinnen letterlijk, en de productnaam zoals die op de productpagina staat.
6. Maximaal 5 citaten per kopje. Kies de meest concrete.
```

**Invoer:** de paginateksten met hun URL's, de branche-JSON en de merknaam.

**Uitvoer (schema B):**

```json
{
  "merknaam": "string",
  "aliassen": ["string"],
  "productnaam": "string",
  "payoff": "string | null",
  "vaste_merkzinnen": ["string"],
  "eigen_verhaal": {
    "wat_je_doet":        [{"tekst": "string", "url": "string", "branchestatus": "hygiene|veelvoorkomend|minderheid|niet_in_branche"}],
    "hoe_je_werkt":       [],
    "wat_je_niet_doet":   [],
    "waar_je_voor_staat": []
  }
}
```

`aliassen` zijn de varianten van de merknaam en het domein, zoals "Brandzaak", "brandzaak.nl" en "Brand Zaak". Stap E gebruikt ze om te zoeken.

**Onderscheidende citaten:** de citaten met branchestatus `minderheid` of `niet_in_branche`. Citaten met status `veelvoorkomend` gelden als "deelt het merk met een deel van de branche".

### Stap C. Vragen maken

**Prompt C (systeem):**

```
Je maakt 10 waardevragen en 2 controlevragen voor de GEO Identity Gap meter van Hero Brands. Volg paragraaf 5 van geo-werkwijze.md.

Waardevragen:
1. Klanttaal: zoals een klant de vraag typt. Geen marketingtaal.
2. Geen merknaam, geen alias, geen productnaam, geen woorden uit de pay-off of vaste merkzinnen.
3. Vragen over wat de klant belangrijk vindt: eerlijk, duurzaam, veilig, zeker, gezond, betrouwbaar, zelf kunnen kiezen.
4. Precies 6 vragen van soort "eigen_verhaal": elk toetst één onderscheidend citaat uit het eigen verhaal (geef het kopje en de citaattekst). Precies 4 vragen van soort "productvraag": brede keuze- of productvragen in de categorie, liefst over iets waar dit product een antwoord op heeft.
5. Minstens drie verschillende intenties uit: orienteren, vergelijken, kiezen, twijfel, waarde, na_aankoop.
6. Niet sturend: een concurrent moet de vraag ook redelijk kunnen beantwoorden.
7. Nederlandse context: "in Nederland" erbij waar een klant dat zou doen.
8. Zijn er minder dan 6 onderscheidende citaten, gebruik dan citaten met status veelvoorkomend en zet "zwak": true bij die vraag.

Controlevragen:
- Twee vragen die de exacte woorden van de pay-off of een vaste merkzin gebruiken, zonder merknaam.
- Vorm: "Welke [soort bedrijf] [merkzin]?" of "Welke Nederlandse [soort bedrijf] werkt met [merkzin]?"
```

**Invoer:** de uitvoer van stap B, de tekst van de productpagina en de branche-JSON.

**Uitvoer (schema C):**

```json
{
  "waardevragen": [
    {"nr": 1, "vraag": "string", "soort": "eigen_verhaal|productvraag", "test": {"kopje": "string", "citaat": "string"} , "intentie": "string", "zwak": false}
  ],
  "controlevragen": [
    {"nr": 11, "vraag": "string", "merkwoorden": "string"}
  ]
}
```

**Controle in code, na de prompt:**
- er zijn precies 10 waardevragen en 2 controlevragen;
- precies 6 waardevragen zijn `eigen_verhaal` en 4 zijn `productvraag`;
- geen waardevraag bevat de merknaam, een alias, de productnaam, de volledige pay-off of een volledige vaste merkzin (hoofdletterongevoelig);
- elke controlevraag bevat de letterlijke merkwoorden.

Faalt een controle, maak de vragen dan één keer opnieuw. Faalt het weer, stop dan.

### Stap D. Pagina-check

**Prompt D (systeem):**

```
Je krijgt één productpagina en 10 vragen. Geef per vraag aan of de pagina zelf antwoord geeft:
- "ja": de pagina beantwoordt de vraag direct, in tekst;
- "deels": het antwoord staat er impliciet, half of alleen in algemene termen;
- "nee": de pagina geeft geen antwoord.
Geef bij ja en deels het letterlijke zinsdeel waarop je oordeel rust (maximaal 25 woorden). Geef bij elke vraag in één zin waarom.
Alleen de tekst van deze pagina telt, niet de rest van de site.
```

**Uitvoer (schema D):** `[{"nr": 1, "pagina": "ja|deels|nee", "citaat": "string|null", "waarom": "string"}]`

### Stap E. Vragen aan ChatGPT

Stel de 12 vragen elk in een eigen, losse call op de Responses API van OpenAI. Dat mag parallel.

```json
{
  "model": "<vast te leggen, zie paragraaf 8>",
  "input": "<de vraag, letterlijk>",
  "tools": [{"type": "web_search", "user_location": {"type": "approximate", "country": "NL"}}],
  "include": ["web_search_call.action.sources"],
  "store": false
}
```

- Geef geen `instructions` of systeemprompt mee. De vraag gaat er kaal in, zoals een klant hem typt.
- Gebruik geen `previous_response_id`. Elke vraag is een nieuw gesprek.
- Bewaar per antwoord: vraagnummer, de volledige tekst, de `url_citation`-annotaties (URL en titel), de bronnen, het model, de response-id en een tijdstempel.
- Lees de uitvoer op `type` en niet op positie. Bij redeneermodellen zit er een `reasoning`-item tussen.
- Faalt een call, probeer hem dan twee keer opnieuw. Lukt het niet, dan telt die vraag als "niet gemeten" en komt dat in de beperkingen. Bij meer dan twee mislukte vragen stopt de scan.

Controleer de veldnamen tegen de actuele documentatie van OpenAI. Die heb ik nagelopen op 22-10-2026, maar ze kunnen veranderen.

### Stap F. Antwoorden scoren

**Eerst in code: is het merk genoemd?**
Zoek in de antwoordtekst, hoofdletterongevoelig, naar de merknaam en de aliassen. Zoek los daarvan ook naar de productnaam.
- Komt de naam alleen voor in een URL of bronvermelding, en niet in de lopende tekst, dan is de uitkomst `alleen_bron`.
- `alleen_bron` telt niet als genoemd. Toon het wel apart.

**Daarna prompt F (systeem), per antwoord:**

```
Je scoort één antwoord van ChatGPT voor de GEO Identity Gap meter. Volg paragraaf 7 van geo-werkwijze.md.

Velden:
- genoemd: wordt het merk of het product in de tekst met naam genoemd als aanbieder of oplossing? (true/false)
- positie: de plaats van het merk in de volgorde waarin het antwoord aanbieders aanraadt (1 = eerste), of null.
- bovenaan: true als het merk als eerste wordt genoemd of als beste wordt aangeraden. Noemt het antwoord maar één aanbieder en is dat het merk: true.
- verhaal:
  - "eigen": de beschrijving van het merk bevat in betekenis minstens één onderscheidend citaat uit het eigen verhaal, en het antwoord geeft dat element niet ook aan een andere aanbieder;
  - "branche": het merk wordt beschreven met hygiëne, categorietaal, productkenmerken, prijs of algemene kwaliteiten, of het onderscheidende element wordt in hetzelfde antwoord ook aan een concurrent toegeschreven;
  - "geen": genoemd zonder beschrijving.
  Niet genoemd: verhaal is "geen".
- onderdeel: bij verhaal = eigen het kopje van het citaat dat herkenbaar is.
- risico: true als het merk wordt afgeraden, foutief wordt beschreven (zoals een verkeerde plaats, prijs of werkwijze) of wordt verward met een ander merk. Beschrijf het risico in één zin.
- citaat: de zin uit het antwoord waarop het oordeel rust, maximaal 25 woorden.
- concurrenten: de aanbieders die het antwoord aanraadt, in volgorde. Geen bronnen, geen winkels die alleen als voorbeeld in een wedervraag staan.
- concurrent_woorden: per concurrent de korte beschrijving die het antwoord geeft (maximaal 12 woorden).
```

**Uitvoer (schema F):**

```json
{"nr": 3, "genoemd": true, "alleen_bron": false, "product_genoemd": false, "positie": 1, "bovenaan": true, "verhaal": "eigen|branche|geen", "onderdeel": "string|null", "risico": false, "risico_uitleg": "string|null", "citaat": "string", "concurrenten": [{"naam": "string", "woorden": "string"}]}
```

`genoemd`, `alleen_bron` en `product_genoemd` komen uit de controle in code. Verschillen code en prompt over `genoemd`, dan geldt de code, en wordt het antwoord gemarkeerd voor Rogier.

### Stap G. Rekenen (code, geen AI)

Over de 10 waardevragen. Vragen die niet gemeten zijn, tellen niet mee.

| Grootheid | Regel |
|---|---|
| uitkomst per vraag | `niet_genoemd` als genoemd = false; `voor_je_merk` als genoemd en verhaal = eigen; anders `voor_je_product` |
| x (genoemd) | aantal vragen met genoemd = true |
| y (bovenaan) | aantal vragen met bovenaan = true |
| z (eigen verhaal) | aantal vragen met uitkomst `voor_je_merk` |
| product_genoemd | aantal vragen waarin de productnaam voorkomt |
| AI-identiteitsscore | `round((x + y + z) / (3 × n) × 100)`, met n = aantal gemeten waardevragen (normaal 10) |
| matrix | per vraag een cel: kolom = pagina (nee, deels, ja), rij = uitkomst (niet_genoemd onder, voor_je_product midden, voor_je_merk boven); vulling = soort (eigen_verhaal gevuld, productvraag open) |
| controlevragen | a van 2 genoemd, apart; telt niet mee in de score |
| terugkerende concurrenten | concurrenten die in 2 of meer waardevragen worden aangeraden, met de vraagnummers en hun woorden |
| risico's | alle antwoorden met risico = true, ook bij controlevragen |

### Stap H. Teksten voor het rapport

**Prompt H (systeem):**

```
Je schrijft twee teksten voor de kaart "Vindbaarheid in AI" in een rapport voor een merkeigenaar. Toon: rustig, direct, met "je". Geen gedachtestreepjes. Gebruik alleen de meegegeven gegevens; voeg niets toe.

1. uitkomst: één of twee zinnen. Wat zegt ChatGPT bij de eigen woorden van het merk, en wat bij de vragen van klanten? Noem het product als het nergens wordt genoemd.
2. wat_opvalt: drie tot vijf punten, elk met een vetgedrukte kop van maximaal acht woorden en een of twee zinnen. Kies uit:
   - een eigen_verhaal-vraag waarop het merk niet wordt genoemd en concurrenten wel;
   - vragen waarop de pagina ja zegt, maar ChatGPT het merk niet noemt;
   - een onderscheidend element dat ChatGPT aan concurrenten toeschrijft;
   - terugkerende concurrenten en de branchewoorden waarmee ze worden beschreven;
   - risico's: foutieve beschrijvingen die het merk kan corrigeren.
Noem altijd de vraagnummers.
```

**Uitvoer:** `{"uitkomst": "string", "wat_opvalt": [{"kop": "string", "tekst": "string"}]}`

## 4. Wat het rapport toont

In deze volgorde, zoals in `voorbeeldrapport-brandzaak.html`:

1. kop en uitleg van de drie uitkomsten (niet genoemd, voor je product, voor je merk);
2. AI-identiteitsscore met uitleg, de uitkomst van de controlevragen en de legenda;
3. de matrix;
4. vier kerncijfers: genoemd, als eerste, met eigen verhaal, product genoemd (alle "x van 10");
5. de uitkomst-zin;
6. de tabel per vraag: nr, vraag, soort, pagina geeft antwoord, uitkomst, wie ChatGPT noemt (met het citaat over het merk als het genoemd is);
7. "Je eigen woorden": uitkomst van de controlevragen;
8. "Wat opvalt";
9. meetregel: datum, model, "één keer per vraag".

**De beperkingen staan in het beperkingenblok van het rapport.** Neem de vaste tekst uit paragraaf 12 van `geo-werkwijze.md` over, plus deze regel, omdat we via de API meten:

> **Gemeten via de API van OpenAI.** We stellen de vragen via de programmeerinterface van OpenAI, met webzoekfunctie. Dat komt dicht bij wat een klant in ChatGPT ziet, maar is niet precies hetzelfde.

Noem daarnaast de vragen die niet gemeten konden worden, de pagina's die niet laadden en de status van de branche.

## 5. Opslag

Bewaar per scan: de invoer, de paginateksten, de uitvoer van elke stap, de ruwe API-antwoorden, de modelversies, de promptversies en de berekende cijfers. Dan kunnen we elke uitslag later navertellen. Hoe lang we dit bewaren, spreken we samen af (zie paragraaf 8).

## 6. Wat Rogier aanlevert en wat Erik zelf bepaalt

**Rogier levert:**
- de meetregels (`geo-werkwijze.md`) en de prompts en schema's in dit document;
- de branchebibliotheek (JSON per branche en `branchelijst.csv`);
- de testset met gouden scoring;
- de rapportteksten en de vaste beperkingen;
- later het ontwerp.

**Erik bepaalt zelf:**
- de techniek, de hosting en de opslag;
- het parallel uitvoeren van de calls, time-outs en retries;
- het beheer van API-sleutels (niet in de repo);
- de bescherming tegen misbruik, zoals een limiet per e-mailadres.

## 7. Test en acceptatie

**Test 1: scoren van opgeslagen antwoorden (de belangrijkste).**
Draai stap F en G vijf keer op `testset-brandzaak-houseblend-chatgpt-antwoorden.txt`, met het eigen verhaal en de vragen uit de gouden scoring. Vergelijk met `testset-brandzaak-houseblend-gouden-scoring.json`.

Klaar als:
- `genoemd` en `bovenaan` in alle vijf runs gelijk zijn aan de gouden set, voor alle 12 vragen;
- de uitkomst per waardevraag in alle vijf runs gelijk is voor minstens 9 van de 10 vragen, en bij een afwijking in geen enkele run verder gaat dan één rij in de matrix;
- vraag 11 in elke run een risico krijgt (Brandzaak "uit Rotterdam"; het merk zit in Vierpolders);
- de AI-identiteitsscore uitkomt op 13 als alle uitkomsten gelijk zijn.

Vraag 9 is het lastigste geval: Brandzaak wordt genoemd, maar het onderscheidende element ("branden op bestelling") wordt in hetzelfde antwoord ook aan Bocca gegeven. Volgens de regel is dat `voor_je_product`.

**Test 2: vragen maken.** Draai stap A tot en met D op brandzaak.nl en brandzaak.nl/houseblend. De vragen hoeven niet gelijk te zijn aan de handmatige set, maar moeten door de controles uit stap C komen. Rogier beoordeelt de set.

**Test 3: API tegen app.** Stel de 12 handmatige vragen uit de gouden scoring via stap E en scoor ze. Lever per vraag naast elkaar: genoemd en uitkomst via de API, tegenover de handmatige meting in de app. Dit is geen slagingscriterium. Het laat zien hoe groot het verschil tussen API en app is, en dat bepaalt hoe we de beperking formuleren.

**Test 4: doorlooptijd en kosten.** Meet de tijd van invoer tot rapport en de kosten per scan (Claude en OpenAI apart, inclusief web search-calls).

**Opleveren:** de uitvoer van test 1 als JSON (vijf runs), de vragen uit test 2, de tabel uit test 3, de cijfers uit test 4 en de commit-hash.

## 8. Open punten om samen te beslissen

1. **Welk OpenAI-model.** Liefst het model dat het dichtst bij de standaard van ChatGPT zit. Erik zoekt uit welk model dat via de API is, en we leggen het vast.
2. **Bewaartermijn** van scans en antwoorden.
3. **Wanneer de GEO-meting draait:** altijd bij de scan, of alleen als er een productpagina is ingevuld. Voorstel: alleen met productpagina.
4. **Hoe Rogier meekijkt** in de eerste weken: een wekelijks overzicht van gemarkeerde antwoorden (verschil tussen code en prompt bij `genoemd`, mislukte calls, risico's).
