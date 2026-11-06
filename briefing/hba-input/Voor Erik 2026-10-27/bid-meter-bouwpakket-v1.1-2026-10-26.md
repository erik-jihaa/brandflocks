# Brand Identity Drift meter op brandidentitydrift.com: bouwpakket

Versie 1.1, 26 oktober 2026. Rogier Heijning, Hero Brands.

Dit document beschrijft de scan op brandidentitydrift.com, van formulier tot rapport. Het is compleet: alle prompts, schema's, rekenregels en tests staan erin. Versie 1.1 vervangt `bid-meter-bouwspec-2026-10-22.md` en neemt de stappen uit de GEO-specificatie en de specificatie van de snelle branche over die de driftmeter nodig heeft.

**Rolverdeling.** Rogier levert de inhoud: meetregels, prompts, teksten, branchebibliotheek en testsets. Erik bouwt de scan en ontwerpt de schermen. Het ontwerp is vrij, binnen de betekenisregels in deel D. De voorbeeldpagina's laten zien wat er op een scherm moet staan en hoe het werkt, niet hoe het eruit moet zien.

## Inhoud

- **Deel A.** Wat de scan doet, en het formulier
- **Deel B.** De stappen: website lezen, eigen verhaal, posts verzamelen, posts scoren, rekenen, teksten, beperkingen
- **Deel C.** Snelle branche: als een branche nog niet onderzocht is
- **Deel D.** Schermen en ontwerp
- **Deel E.** Opslag, rolverdeling en beheer
- **Deel F.** Test en acceptatie
- **Deel G.** Open punten

## Bijlagen (zip `bid-meter-bijlagen-2026-10-26.zip`)

| Bestand | Waarvoor |
|---|---|
| `voorbeeld-site-en-formulier.html` | Landingspagina met formulier: velden, teksten, voorbehouden |
| `voorbeeldrapport-brandzaak.html` | Rapport: onderdelen, teksten, grafieken en de tekstsjablonen in de code (`score()`, `verhaal()`) |
| `branchelijst.csv` | Alle branches met nummer, afbakening, markt en status |
| `begrippen-en-schema.md` | Begrippen en JSON-schema van de branchebibliotheek (bindend) |
| `branche-88-koffiebranderijen.json` | Onderzochte branche, voor de tests |
| `branche-34-warmtepompinstallateurs.json` | Onderzochte branche, voor de test van de snelle branche |
| `testset-brandzaak-25-posts.json` | De 25 nieuwsberichten van brandzaak.nl (datum en titel) |
| `testset-brandzaak-posts-gouden-scoring.json` | Verwachte scoring per post en verwachte scores |

---

# Deel A. Wat de scan doet

Een merk vult het formulier in. De scan:

1. leest de website en haalt het eigen verhaal letterlijk op;
2. zoekt de branche erbij: een onderzochte branche uit de bibliotheek, of anders een snelle branche die de scan zelf maakt;
3. verzamelt de posts: blog en nieuws haalt de scan zelf op, social posts plakt of uploadt de bezoeker;
4. scoort elke post tegen het eigen verhaal en de branche;
5. rekent de driftscore over de laatste 10 posts en het verloop over de tijd;
6. maakt het rapport en stuurt de link per e-mail.

De meetopzet: de website van het merk levert de maatstaf. De posts worden gemeten ten opzichte van die website. De website zelf wordt niet gemeten.

**Verschil met Brandflocks.** De HBA-laag in Brandflocks scoort tegen een volledige merkarchitectuur, met tegenkracht en regels. De scan heeft die niet: we kennen alleen wat het merk op zijn site zegt. De scan gebruikt daarom eigen, lichtere prompts. Gebruik de bevroren pilotprompt v1.13 hier niet, en raak die ook niet aan. De indeling in oordelen lijkt wel op die van Brandflocks, dus de resolverstructuur kun je hergebruiken.

**GEO is fase 2.** De GEO-meting (vindbaarheid in ChatGPT, met één productpagina) heeft een eigen pakket: `geo-module-bouwspec-2026-10-22.md`. Bouw eerst de driftmeter. Het formulierveld voor de productpagina en de GEO-kaart in het rapport kunnen tot fase 2 wachten.

## Het formulier

| Veld | `name` | Verplicht | Opmerking |
|---|---|---|---|
| Je naam | `naam` | ja | |
| Je e-mailadres | `mail` | ja | Hier gaat het rapport naartoe |
| Je website | `site` | ja | |
| Je branche | `branche` | ja | Keuzelijst uit `branchelijst.csv`, met ✓ achter de onderzochte branches, plus "Andere branche" |
| Link naar blog of nieuwspagina | `blog` | nee | |
| Plak je laatste posts | `posts` | nee | Vrije tekst, elk met datum |
| Upload een export | `export` | nee | Instagram- of LinkedIn-archief |
| Eén productpagina | `product` | nee | Alleen voor GEO, fase 2 |

Minstens één van `blog`, `posts` of `export` is verplicht.

De hulpteksten bij de velden, waaronder het voorbehoud bij de branchekeuze, staan in `voorbeeld-site-en-formulier.html`.

---

# Deel B. De stappen

Gebruik voor alle AI-stappen het Claude-model dat ook in Brandflocks wordt gebruikt.
- Leg model, versie en promptversie vast en sla ze per scan op.
- Wie het model of een prompt wijzigt, draait eerst test 1 uit deel F opnieuw.
- Laat elke AI-stap zijn uitkomst als JSON geven en valideer die tegen het schema. Mislukt de validatie, probeer het dan één keer opnieuw. Mislukt het weer, stop dan de scan en stuur Rogier een melding.

## Stap A. Website lezen

- Lees de homepage en maximaal vier pagina's over het merk, zoals over ons, werkwijze, duurzaamheid of missie.
- Kies die pagina's op linktekst en URL. Woorden als "over", "werkwijze", "verhaal", "missie", "duurzaam" en "about" wijzen erop.
- Bewaar per pagina de URL, de leesdatum en de platte tekst.
- Tekst in afbeeldingen of video's telt niet; zet die niet om.
- Respecteer robots.txt. Een pagina die niet laadt, komt in de beperkingen.

Bouw deze stap zo dat de GEO-module (fase 2) en de snelle branche (deel C) hem ook gebruiken.

## Stap B. Eigen verhaal

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
5. Geef ook de pay-off, de themaregel en andere vaste merkzinnen letterlijk, en de productnaam zoals die op de productpagina staat (als die is gelezen).
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

`aliassen` zijn de varianten van de merknaam en het domein, zoals "Brandzaak", "brandzaak.nl" en "Brand Zaak". De GEO-module gebruikt ze in fase 2 om het merk in antwoorden te herkennen.

**Onderscheidende citaten:** de citaten met branchestatus `minderheid` of `niet_in_branche`. Citaten met status `veelvoorkomend` gelden als "deelt het merk met een deel van de branche".

**In het rapport** wordt dit de kaart "Je eigen verhaal, volgens je website", met vier kopjes:
- wat je doet;
- hoe je werkt;
- wat je niet doet;
- waar je voor staat.

Elk kopje krijgt het sterkste onderscheidende citaat, met de pagina waar het staat. Daaronder komt de regel "Weggefilterd, omdat de meeste [meervoud_bedrijf] het ook zeggen", met de hygiëne-items en hun frequentie.

**Geen aannames.** De scan vult nooit een tegenkracht of regel in. De vaste zin daarover staat in het voorbeeldrapport.

## Stap C. Posts verzamelen

**C1. Ophalen en uitlezen**

| Bron | Hoe |
|---|---|
| Blog of nieuws | Eerst RSS of Atom zoeken (`<link rel="alternate">`, `/feed`, `/rss`). Anders de sitemap. Anders de overzichtspagina lezen en de artikelen volgen. Haal per bericht datum, titel, URL en tekst op. |
| Geplakte posts | Splitsen op datum. Herken de datumnotaties 22-10-2026, 22 oktober 2026 en 2026-10-22. Een stuk tekst zonder datum krijgt de markering `datum_onbekend`. |
| Instagram-export | Uit het JSON-archief: bijschrift en tijdstempel per post. |
| LinkedIn-export | Uit het CSV-archief met gedeelde berichten: tekst en datum. |

Controleer de actuele bestandsnamen en velden van beide exports. Die veranderen af en toe.

**C2. Opschonen**
- Sorteer alle posts uit alle bronnen op datum en neem de laatste 25.
- Ontdubbel posts met dezelfde tekst, bijvoorbeeld een LinkedIn-post die ook als blog staat.
- Posts zonder datum komen achteraan en worden in het rapport genoemd.
- Bewaar per post hoe hij gelezen is: `volledig`, of `titel en samenvatting` als de volledige tekst niet te laden was.

**Minimum: 10 posts met datum.** Bij minder maakt de scan geen driftscore. De bezoeker krijgt dan een bericht dat er meer posts nodig zijn.

## Stap D. Posts scoren

Score elke post apart. Dat mag parallel.

**Prompt D (systeem):**

```
Je scoort één post van een merk voor de Brand Identity Drift meter van Hero Brands. Je krijgt het eigen verhaal van het merk (letterlijke citaten van de eigen website, met branchestatus) en de branche-JSON uit de branchebibliotheek. Je beoordeelt alleen wat in de post staat.

1. oordeel en niveau. Kies precies één:
   - "neutraal": de post zegt niets over het merk en niets over het vak (sponsoring, een persoonlijk bericht, een vacature, een evenement buiten het vak). Telt niet mee.
   - "breuk": de post zegt of doet het tegendeel van een citaat onder wat_je_niet_doet of waar_je_voor_staat. Citeer beide.
   - anders een niveau:
     N1 (kern): een onderscheidend citaat uit het eigen verhaal is het onderwerp van de post, concreet.
     N2 (eigen, gemengd): een onderscheidend citaat komt voor, maar naast veel hygiëne en categorietaal.
     N3 (over jezelf): over het eigen bedrijf, de mensen of eigen nieuws, zonder onderscheidend citaat en met weinig branchetaal.
     N4 (eigen aanbod in branchetaal): over het eigen aanbod, de inkoop of het werk, maar verteld met hygiënefactoren en categorietaal die de meeste bedrijven in de branche ook gebruiken.
     N5 (categorie): algemene vakcontent, zoals tips, uitleg, reviews en gidsen. Elke concurrent kan deze post schrijven.
     N6 (ver weg): algemene content met weinig band met het vak of het merk, zoals gezondheid, lifestyle of trivia.
   Onderscheidend betekent: een citaat met branchestatus "minderheid" of "niet_in_branche".
2. as: "activatie" (aanbod, product, prijs, actie, nu kopen of bestellen, een review of test die verkoopt), "merkbouw" (wie het merk is, waarom, hoe het werkt, mensen, herkomst, waarden, lange termijn) of "midden" (beide of geen van beide).
3. algemeen: null, of een thema van één woord als de post over het onderwerp in het algemeen gaat en niet over het merk (gezondheid, tips, machines, lifestyle, recepten, nieuws). N5 en N6 hebben altijd een thema; N1 tot en met N3 nooit.
4. claim: true als de post een claim raakt uit verboden_of_beperkte_claims van de branche-JSON. Geef de claim. Dit is een signaal, geen juridisch oordeel.
5. kern: true als de post een onderscheidend citaat uit het eigen verhaal noemt.
6. branchetaal: de items uit hygienefactoren en categorietaal (status hygiene of veelvoorkomend) die in betekenis in de post voorkomen. Geef het item precies zoals het in de branche-JSON staat.
7. citaat: de zin uit de post waarop het oordeel rust, maximaal 25 woorden.
```

**Uitvoer (schema D):**

```json
{"nr": 2, "oordeel": "neutraal|breuk|niveau", "niveau": "N1|N2|N3|N4|N5|N6|null", "breuk_uitleg": "string|null", "as": "activatie|midden|merkbouw", "algemeen": "string|null", "claim": false, "claim_item": "string|null", "kern": true, "branchetaal": [{"item": "string"}], "citaat": "string"}
```

**Daarna in code:**
- **y uit het niveau:** N1 = 90, N2 = 70, N3 = 55, N4 = 35, N5 = 20, N6 = 8. Een breuk krijgt y = 0. Neutraal krijgt geen y.
- **Oordeel voor rapport en tabel:** `op_merk` bij y ≥ 50, `branche` bij y < 50, plus `breuk` en `neutraal`.
- **x voor de grafiek:** activatie = −0,6, midden = 0, merkbouw = +0,6. Geef elke stip een kleine vaste verschuiving op basis van het postnummer, zodat stippen niet op elkaar liggen (zie `jit()` in het voorbeeldrapport).
- **Branchetaal:** zet bij elk branchetaal-item de `frequentie` en `status` uit de branche-JSON. Komt een item daar niet in voor, dan vervalt het en wordt het gemarkeerd voor Rogier.
- **Controle:** N5 en N6 moeten een thema bij `algemeen` hebben; N1 tot en met N3 mogen er geen hebben. Klopt dat niet, score de post dan één keer opnieuw.

Vaste niveauwaarden maken de score herhaalbaar. Een vrij getal tussen 0 en 100 verschilt per run.

## Stap E. Rekenen (code, geen AI)

Dit is precies de functie `score(n)` in het voorbeeldrapport.

- **Venster:** de laatste 10 niet-neutrale posts tot en met post n. Een score bestaat pas vanaf 10 posts.
- **H** = het gemiddelde van y over het venster. Een breuk telt als 0.
- **S** = het percentage posts in het venster met y ≥ 50.
- **B**, de balans tussen merkbouw en activatie:
  - neem de posts in het venster zonder `algemeen`;
  - aandeel merkbouw = (aantal merkbouw + 0,5 × aantal midden) / aantal van die posts;
  - B = 100 × max(0, 1 − |aandeel − 0,6| / 0,6).
  - De norm 0,6 komt uit Binet & Field. Bij een B2B-branche blijft hij voorlopig 0,6, omdat het onderzoek daar niet eenduidig is.
- **Totaal** = round(0,5 × H + 0,3 × S + 0,2 × B).
- **Drift** = totaal − 50, op een schaal van −50 tot +50.
- **Woord bij de score:** vanaf +20 "weinig drift", van 0 tot +20 "duidelijke drift", onder 0 "je merk zit in de branche".
- **Verloop:** de drift na elke post vanaf post 10, met meetmomenten op 10, 15, 20 en 25.
- **Middelpunt** van het venster: het gemiddelde van x en y. In het voorbeeld is dat de gele ring, met het spoor dat het middelpunt aflegt.
- **Breuken** in het venster geven een waarschuwing bij de score.

## Stap F. Rapportteksten

De lopende teksten in het rapport worden met vaste sjablonen in code gemaakt, niet door AI:
- "Wat er gebeurde";
- "Waarom je score omlaag gaat";
- "Wat werkt".

De sjablonen staan in de functie `verhaal()` in `voorbeeldrapport-brandzaak.html`. Neem de logica en de zinnen over.

Twee aanpassingen voor de algemene versie:
- **"Branderijen" wordt een woord per branche.** Gebruik het veld `meervoud_bedrijf` ("branderijen", "installateurs", "kantoren"). Rogier vult het in voor de onderzochte branches; een snelle branche maakt het zelf (deel C, stap 0).
- **"Wat werkt" haalt de kernzin uit het eigen verhaal.** Gebruik het citaat waarop de meeste posts met `kern = true` zich beroepen. In het voorbeeld is dat de tekst tussen haakjes ("vóór 13:00 besteld, dezelfde dag gebrand, morgen in huis").

**Het blok "Wat nu?"** met de HBA-cijfers en de link naar brandflocks.com is een vaste tekst. Neem het letterlijk over uit het voorbeeldrapport.

## Stap G. Beperkingen

**Per scan, automatisch:**
- hoeveel posts volledig gelezen zijn en hoeveel op titel en samenvatting;
- welke bronnen zijn meegenomen (blog, geplakt, Instagram, LinkedIn) en welke niet;
- de periode die de posts beslaan, met een waarschuwing als 10 posts meer dan een jaar beslaan;
- pagina's die niet laadden;
- of de branche onderzocht is of snel gemeten (vaste tekst in deel C);
- als het merk in de branchesteekproef zat: dat het voor deze meting uit de telling is gehaald (deel C, "Het gemeten merk telt nooit mee").

**Altijd:**
- de maatstaf komt van de website;
- de branche is een steekproef;
- het oordeel per post komt van een AI-model met vaste regels;
- gezondheidsclaims zijn een signaal, geen juridisch oordeel.

De formuleringen staan in het blok "Beperkingen van deze meting" in het voorbeeldrapport.

---

# Deel C. Snelle branche

## Waarom

De driftmeter en de GEO-module meten een merk tegen de taal van zijn branche. Die taal staat in de branchebibliotheek, maar die is nog lang niet compleet. Een onderzochte branche kost Rogier een gesprek met de GPT en een controle. Iemand die zijn merk wil laten meten, mag daar niet op wachten.

Daarom maakt de scan zelf een **snelle branche** als er geen onderzochte branche is. Die is minder volledig, maar automatisch en herhaalbaar. In een uitgebreide analyse onderzoekt Rogier de markt later alsnog volledig.


## 1. Wanneer de scan een snelle branche maakt

| Situatie | Wat de scan doet |
|---|---|
| Branche staat in `branchelijst.csv` met status `gecontroleerd` | Gebruikt de volledige branche-JSON. Geen snelle branche. |
| Branche staat in de lijst, maar is nog niet onderzocht | Gebruikt een bestaande snelle branche als die jonger is dan 6 maanden. Anders maakt de scan er een, met de afbakening uit de lijst. |
| Bezoeker kiest "Andere branche" | Stap 0 bepaalt de branche. Daarna hetzelfde als hierboven. |

Een snelle branche wordt bewaard en hergebruikt. Zo meten merken in dezelfde branche tegen dezelfde maatstaf, en ontstaan later eerlijke ranglijsten.

Een snelle branche overschrijft nooit een volledige branche. Wordt de branche later gecontroleerd, dan gebruiken nieuwe scans de volledige versie.

## 2. Volledig tegenover snel

| Onderdeel | Volledige branche | Snelle branche |
|---|---|---|
| Bedrijven | minstens 12, bewust gespreid over grootte en segment | 8, gevonden via zoeken (minimum 6) |
| Pagina's per bedrijf | minstens 3 | 2 of 3 |
| Hygiënefactoren en categorietaal | ja | ja |
| Strawmen | ja | ja |
| Werk- en producttaal | ja | ja |
| Verplichte taal en verboden claims | ja, met regel en bron | alleen claims met een gevonden bron, gemarkeerd als niet gecontroleerd |
| Uitwijkformules, taboes, conventies, differentiatie-assen, debatten, archetype | ja | nee (lege lijsten) |
| Controle door Rogier | ja | nee, wel achteraf in te zien |

Voor de scan zijn alleen hygiëne, categorietaal, strawmen en werk- en producttaal nodig. Die maakt de snelle branche wel.

## 3. Stappen

Gebruik hetzelfde Claude-model en dezelfde regels voor JSON-validatie als in deel B. Voor het zoeken naar bedrijven is een webzoekfunctie nodig: de webzoektool van Claude, of een zoek-API. Erik kiest welke.

### Stap 0. Branche bepalen (alleen bij "Andere branche")

**Prompt 0 (systeem):**

```
Je krijgt de tekst van de homepage en de over-ons-pagina van een merk, en de lijst met branches uit branchelijst.csv (nummer, naam, afbakening, markt).
1. Bepaal in welke branche dit merk concurreert, vanuit de klant gezien: bij wie had de klant anders gekocht?
2. Past een bestaande branche uit de lijst, geef dan dat nummer.
3. Past er geen, stel dan een nieuwe branche voor: naam, afbakening in één zin (wat wel, wat niet), markt (B2B, B2C of beide), en het meervoud van het soort bedrijf ("branderijen", "installateurs").
Een categorie zoals "duurzame merken" of "circulaire producten" is geen branche. Een branche is een groep bedrijven die hetzelfde soort aanbod hebben voor hetzelfde soort klant.
```

**Uitvoer:** `{"bestaand_nummer": 34 | null, "naam": "string", "afbakening": "string", "markt": "B2B|B2C|beide", "meervoud_bedrijf": "string", "zoektermen": ["string"]}`

Een nieuwe branche krijgt een tijdelijk nummer, zoals `snel-0001`. Rogier ziet die in het overzicht en kent later een echt nummer toe.

### Stap 1. Bedrijven vinden

**Prompt 1 (systeem, met webzoekfunctie):**

```
Zoek 12 Nederlandse bedrijven die binnen deze branche vallen: [naam], [afbakening], markt [markt].
- Gebruik 3 tot 5 verschillende zoekopdrachten, zoals "[soort bedrijf] Nederland", "[soort bedrijf] [plaats van het gemeten merk]", "beste [soort bedrijf]" en de vaktermen van de branche.
- Kies een mix van groot, middelgroot en klein, en minstens een derde gewone mkb-bedrijven. Niet alleen de bekendste namen.
- Geen ketens of platforms buiten de afbakening, geen vergelijkingssites, geen bedrijven buiten Nederland.
- Geef per bedrijf de naam, de URL van de homepage en een inschatting van de grootte (groot, middelgroot, klein) en het segment (premium, middensegment, gewoon_mkb).
Neem het gemeten merk zelf niet op: [merknaam], [domein].
```

**Daarna in code:**
- Verwijder het gemeten merk (domein en naam).
- Verwijder dubbele domeinen.
- Houd de eerste 8 bedrijven waarvan de homepage laadt. Laden er minder dan 8, vul dan aan uit de rest van de 12.

### Stap 2. Pagina's lezen

Gebruik stap A uit deel B, per bedrijf: de homepage plus maximaal twee pagina's over het bedrijf of het aanbod. Bewaar per pagina de URL en de platte tekst.

Een bedrijf telt alleen mee als minstens 2 pagina's zijn gelezen.

### Stap 3. Beloftes per bedrijf ophalen

Doe dit **per bedrijf apart**. Dan staat elke waarneming vast vóór er wordt geteld, en kan een model geen aantallen verzinnen.

**Prompt 3 (systeem):**

```
Je leest de pagina's van één bedrijf in de branche [naam]. Haal letterlijk op wat het bedrijf over zichzelf belooft en hoe het over zichzelf praat.
1. beloftes: wat het bedrijf zegt te zijn, te doen of te garanderen. Geef per belofte een korte omschrijving in je eigen woorden (maximaal 8 woorden) en het letterlijke citaat (maximaal 20 woorden) met de URL. Geef ook de soort: "identiteit" (wat het bedrijf is of doet) of "service" (verzending, garantie, openingstijden, betaalgemak, gratis offerte).
2. woorden: woorden en frasen waarmee het bedrijf zichzelf beschrijft ("persoonlijk", "ontzorgen", "passie", "duurzaam"), letterlijk, met URL.
3. strawmen: zinnen in de vorm "geen ... maar ...", "niet zoals ...", "anders dan andere ...", "zonder gedoe", letterlijk, met URL.
4. vaktermen: technische termen voor product, materiaal, werkwijze of bereiding.
Alleen wat er staat. Geen klantreviews. Maximaal 15 beloftes en 15 woorden.
```

**Uitvoer per bedrijf:** `{"bedrijf_id": "string", "beloftes": [{"omschrijving": "string", "citaat": "string", "url": "string", "soort": "identiteit|service"}], "woorden": [{"woord": "string", "url": "string"}], "strawmen": [{"citaat": "string", "url": "string"}], "vaktermen": ["string"]}`

**Controle in code:** elk citaat en elk woord moet letterlijk in de opgehaalde tekst van die URL staan. Vergelijk na het normaliseren van witruimte, aanhalingstekens en hoofdletters. Wat niet letterlijk staat, vervalt.

Dit is de belangrijkste waarborg van de snelle branche: niets komt in de maatstaf dat niet echt op een site staat.

### Stap 4. Groeperen

Eén call over alle bedrijven samen.

**Prompt 4 (systeem):**

```
Je krijgt de beloftes, woorden en strawmen van [n] bedrijven in één branche. Groepeer ze op betekenis.
1. hygienefactoren: groepen beloftes die hetzelfde beloven met andere woorden. Geef elke groep een korte naam (item) en de soort (identiteit of service). Wees concreet: "vers gebrand" en "branddatum op de zak" zijn aparte items, ook al gaan beide over kwaliteit. Geef bij elke groep welke beloftes erin vallen (bedrijf_id plus citaat).
2. categorietaal: groepen woorden en frasen met dezelfde betekenis ("persoonlijk", "persoonlijke aandacht"). Geef het item en welke woorden erin vallen.
3. strawmen: groepen strawmen met dezelfde strekking.
4. werk_en_producttaal: de vaktermen die meerdere bedrijven gebruiken. Die tellen niet als hygiëne.
Een belofte hoort in hoogstens één groep. Een groep met maar één bedrijf mag, maar maak geen groepen om aantallen op te drijven.
```

**Daarna in code:**
- **Frequentie** per item = het aantal verschillende bedrijven in de groep.
- **Status**, met n = aantal bedrijven:
  - `hygiene` vanaf 60% van n, naar boven afgerond (bij n = 8: 5 of meer);
  - `veelvoorkomend` vanaf 35% van n, naar boven afgerond (bij n = 8: 3 of 4);
  - `minderheid` daaronder.
- **Voorbeelden:** per item twee of drie citaten van verschillende bedrijven, met URL.
- **Items met 1 bedrijf** vallen weg uit hygiëne en categorietaal. Ze bewijzen niets over de branche.
- **Ids** volgens het schema: `S{nummer}-H1`, `S{nummer}-C1`.

### Stap 5. Beperkte claims (optioneel per branche)

**Prompt 5 (systeem, met webzoekfunctie):**

```
Zoek voor de branche [naam] in Nederland welke claims wettelijk of door een toezichthouder of reclamecode beperkt zijn (bijvoorbeeld gezondheidsclaims, duurzaamheidsclaims, financiële of medische claims). Geef per claim de regel en de URL van een primaire bron (wetgeving, toezichthouder, Reclame Code Commissie of een beroepscode). Vind je geen primaire bron, laat de claim dan weg.
```

Zet bij elke claim `"zeker": false` en `"bron_type": "automatisch"`. In de scan geldt een claim alleen als signaal. Lege lijst mag.

### Stap 6. Opslaan

Sla de branche op in het schema van `begrippen-en-schema.md`, met deze aanvullingen:

```json
{
  "niveau": "snel",
  "status": "automatisch",
  "gemaakt_op": "2026-10-22",
  "geldig_tot": "2027-04-22",
  "gemaakt_bij_scan": "<scan-id>",
  "meervoud_bedrijf": "string",
  "model": "<model en versie>",
  "promptversie": "snel-1.0",
  "bronnen_kwaliteit": {
    "oordeel": "laag",
    "reden": "Automatisch gemaakt uit de websites van [n] bedrijven, gevonden via zoeken. Niet door Hero Brands gecontroleerd. Regels voor claims zijn niet volledig onderzocht.",
    "telmethode": "Eén waarneming per bedrijf per item. Alleen letterlijk gevonden citaten tellen.",
    "drempels": "hygiene: minimaal 60%; veelvoorkomend: minimaal 35%; minderheid: minder dan 35%."
  }
}
```

De velden die de snelle branche niet vult, zijn lege lijsten. Zet de branche in `branchelijst.csv` op status `automatisch`, of voeg een rij toe met het tijdelijke nummer.

## 4. Het gemeten merk telt nooit mee

Dit geldt voor snelle én volledige branches. Staat het gemeten merk in `onderzochte_bedrijven` (zelfde domein of naam), dan rekent de code de frequenties en statussen opnieuw uit zonder dat bedrijf. Dat gebeurt alleen voor deze scan, niet in het opgeslagen bestand.

Voorbeeld: Brandzaak staat in branche 88. In de scan van Brandzaak telt de branche dan 11 bedrijven in plaats van 12.


## 5. Kwaliteitsdrempels

| Uitkomst | Wat de scan doet |
|---|---|
| 8 bedrijven met elk minstens 2 pagina's, en minstens 5 items met status hygiene of veelvoorkomend | Normaal verder |
| 6 of 7 bedrijven, of 3 of 4 items | Verder, met de melding "dunne branche" in het rapport |
| Minder dan 6 bedrijven of minder dan 3 items | De scan meet het eigen verhaal en de posts, maar zonder branchetoets. Het rapport zegt dat. Rogier krijgt een melding. |

Rogier krijgt bij elke nieuwe snelle branche een melding met de naam, het aantal bedrijven en de vijf meest voorkomende items. Hij hoeft niets te doen, maar kan ingrijpen.

## 6. Wat de bezoeker te zien krijgt

**In het formulier**, onder de branchekeuze:

> Branches met ✓ hebben we zelf onderzocht: minstens 12 bedrijven, met de regels die in je branche gelden. Staat je branche er niet bij of heeft hij geen ✓, dan maken we automatisch een snelle branchemeting op basis van 8 vergelijkbare bedrijven. Dat is minder grondig. In een uitgebreide analyse onderzoeken we je markt volledig.

Zet in de keuzelijst een ✓ achter de branches met status `gecontroleerd`.

**In het rapport**, in de kop: "Branche: [naam] (onderzocht)" of "Branche: [naam] (snelle meting, 8 bedrijven)".

**In het beperkingenblok, bij een snelle branche:**

> **Je branche is snel gemeten.** We hebben de taal van je branche automatisch vastgesteld uit de websites van [n] vergelijkbare bedrijven, gevonden via zoeken. Dat is minder grondig dan onze onderzochte branches. Regels voor wat je branche mag claimen zijn niet volledig onderzocht. In een uitgebreide analyse onderzoeken we je markt volledig.

## 7. Doorlooptijd

Een snelle branche kost meer tijd dan de rest van de scan: zoeken, 16 tot 24 pagina's lezen en 8 extractiecalls. Doe het lezen en de extractie parallel. Laat de bezoeker na het versturen zien: "We meten je merk. Je rapport staat binnen [x] minuten in je mailbox." De waarde van x volgt uit test 3.

## Test en acceptatie

**Test 1: snel tegen volledig.** Maak voor branche 88 (koffiebranderijen) en branche 34 (warmtepompinstallateurs) een snelle branche, zonder de volledige versie te gebruiken. Doe dat drie keer per branche. Vergelijk met de volledige branche-JSON.

Klaar als:
- elk citaat in de snelle branche letterlijk op de opgegeven URL staat (100 procent, gecontroleerd in code);
- van de items met status `hygiene` in de volledige branche minstens de helft in de snelle branche terugkomt met status hygiene of veelvoorkomend (op betekenis, door Rogier beoordeeld);
- er geen item met status hygiene in de snelle branche staat dat in de volledige branche als minderheid staat of ontbreekt, of dat zo'n afwijking per branche hoogstens twee keer voorkomt en verklaarbaar is;
- de drie runs per branche minstens 5 bedrijven gemeen hebben.

**Test 2: effect op de score.** Draai de driftscan van Brandzaak (deel F, test 1) met de snelle branche 88 in plaats van de volledige. De drift op de meetmomenten 10, 15, 20 en 25 mag hoogstens 10 punten afwijken van de uitkomst met de volledige branche. Lever het verschil per post: welke posts kregen een ander niveau, en door welk item?

**Test 3: andere branche.** Een scan met "Andere branche" op een merk buiten de bibliotheek. Rogier levert dat merk aan. Lever:
- de uitkomst van stap 0;
- de 8 bedrijven;
- de snelle branche;
- de doorlooptijd en de kosten.

**Opleveren:** de snelle branches als JSON, de vergelijkingstabellen uit test 1 en 2, de resultaten van test 3 en de commit-hash.

## 9. Open punten

1. **Geldigheid.** Voorstel: een snelle branche is 6 maanden geldig en wordt daarna opnieuw gemaakt bij de eerstvolgende scan in die branche.
2. **Volgorde van het volledige onderzoek.** Branches waarin vaak gescand wordt, komen bovenaan Rogiers lijst om volledig te onderzoeken. Het overzicht van scans per branche maakt dat zichtbaar.
3. **Zoekfunctie.** De webzoektool van Claude of een losse zoek-API. Erik kiest op basis van kosten en kwaliteit.

---

# Deel D. Schermen en ontwerp

Erik ontwerpt alle schermen. Lay-out, huisstijl, lettertypen, kleuren en interactie zijn vrij. De voorbeeldpagina's tonen wat er moet staan en hoe het werkt; ze zijn geen ontwerp.

## Schermen

| Scherm | Wat er minstens op moet staan |
|---|---|
| **1. Landingspagina** | Wat brand identity drift is, wat de scan doet in vijf stappen, het formulier, en het voorbehoud bij de branchekeuze. De teksten staan in `voorbeeld-site-en-formulier.html`. |
| **2. Na het versturen** | Bevestiging, wat er nu gebeurt, en wanneer het rapport in de mailbox komt. Bij te weinig posts of een fout: wat de bezoeker kan doen. |
| **3. E-mail** | Korte tekst en de link naar het rapport. |
| **4. Rapport** | De onderdelen hieronder, in deze volgorde. |
| **5. Beheer** (alleen voor Rogier en Erik) | Lijst van scans met merk, branche, drift en status. De snelle branches met aantal bedrijven en hun belangrijkste items. Meldingen (mislukte stappen, gemarkeerde items). Bij de start: rapporten eerst laten controleren en daarna vrijgeven (zie deel G). |

**Onderdelen van het rapport:**
1. Kop met merk, branche (onderzocht of snel gemeten), aantal posts en periode.
2. "Je eigen verhaal, volgens je website".
3. De zwermgrafiek, met een manier om door de tijd te gaan (in het voorbeeld een schuif, afspeelknop en meetmomenten).
4. De score, het woord erbij en de opbouw in gewone taal (klinkt als je branche, posts onder de middellijn, balans).
5. Het verloop van de drift.
6. De teksten uit stap F.
7. In fase 2: de GEO-kaart.
8. "Wat nu?".
9. De beperkingen.
10. De tabel met alle posts: datum, titel, hoogte (of "telt niet" of "tegen merk"), as, en de branchetaal of het algemene thema.
11. De meetregel met datum, bronnen, branche en aantal bedrijven in de branche.

## Betekenisregels (vast)

Deze regels bepalen hoe de meter gelezen wordt. Houd ze aan, hoe het ontwerp er verder ook uitziet.

**Zwermgrafiek**
- Elke post is een stip. Alle gemeten posts zijn zichtbaar, zodat de bezoeker ziet dat alles gemeten is.
- Verticaal: je eigen merk boven, je branche onder. Hoe hoger, hoe beter. De middellijn ligt op y = 50.
- Horizontaal: activatie links (korte termijn: actie, aanbod, nu), merkbouw rechts (lange termijn: wie je bent).
- Stippen boven en onder de middellijn zijn duidelijk verschillend. In het voorbeeld is dat blauw tegenover rood.
- Een breuk, algemene content en een neutrale post zijn elk herkenbaar anders dan een gewone post. In het voorbeeld zijn dat een open rode stip, een stippellijn en een grijze stip.
- Het middelpunt van de laatste 10 posts is zichtbaar.

**Score**
- Schaal van −50 tot +50, met 0 in het midden.
- Op de schaal staat de branche links en je merk rechts.
- Hoe minder drift, hoe beter. Dat moet zonder uitleg duidelijk zijn.
- Bij het getal staat zichtbaar dat het om de laatste 10 posts gaat, en dat het een samenvatting is, geen trend.

**Verloop**
- Lager is slechter, ook in de verlooplijn.
- 0 is de middellijn; daaronder is het merk meer branche dan merk.

**Algemeen**
- Kleur is nooit het enige verschil. Er staat altijd ook een label, vorm of tekst bij.
- Alles werkt op een telefoon, zonder dat de pagina breder wordt dan het scherm. Een grafiek mag binnen zijn eigen vak schuiven.
- Het rapport is te lezen zonder interactie. De schuif en de afspeelknop zijn een extra.

## Teksten

De teksten in de voorbeeldpagina's komen van Rogier. Moet een tekst korter of anders voor het ontwerp, overleg dat dan met Rogier. De vaste teksten zijn de beperkingen, de voorbehouden en het blok "Wat nu?". Die wijzigen alleen in overleg.

---

# Deel E. Opslag, rolverdeling en beheer

**Opslag.** Bewaar per scan:
- de invoer;
- de paginateksten;
- de posts;
- de uitvoer van elke stap;
- de model- en promptversies;
- de berekende cijfers;
- de gebruikte branche, met versie: onderzocht of snel, en de datum.

Het rapport is een webpagina met een unieke, niet te raden link. De bezoeker krijgt die per e-mail.

**Voor de ranglijsten.** De ranglijsten per branche komen later. Bewaar nu al per scan het merk, de branche en de drift, zodat ze er later uit te maken zijn.

**Rogier levert:**
- de rekenregels en prompts in dit document;
- de branchebibliotheek, met `meervoud_bedrijf` voor de onderzochte branches;
- de testsets;
- de teksten.

**Erik bepaalt en bouwt:**
- het ontwerp van alle schermen;
- de techniek, de hosting, het formulier en de e-mail;
- het ophalen van feeds en het uitlezen van exports;
- de zoekfunctie voor de snelle branche;
- het beheer van API-sleutels, die niet in de repo staan;
- de bescherming tegen misbruik, zoals een limiet per e-mailadres.

---

# Deel F. Test en acceptatie

## Test 1: posts scoren (de belangrijkste)

Haal de 25 berichten uit `testset-brandzaak-25-posts.json` op uit het nieuwsarchief van brandzaak.nl. Gebruik branche 88 en het eigen verhaal hieronder.

```json
{
  "merknaam": "Brandzaak",
  "aliassen": [
    "Brandzaak",
    "brandzaak.nl"
  ],
  "payoff": "Door het vuur voor koffie",
  "vaste_merkzinnen": [
    "Roasting on Demand"
  ],
  "eigen_verhaal": {
    "wat_je_doet": [
      {
        "tekst": "Roasting on Demand noemen wij dat.",
        "bron": "brandzaak.nl, pagina 'Door het vuur voor koffie'",
        "branchestatus": "niet_in_branche"
      }
    ],
    "hoe_je_werkt": [
      {
        "tekst": "Iedere werkdag om 13:00 ontvlammen wij onze brander.",
        "bron": "brandzaak.nl, pagina 'Door het vuur voor koffie'",
        "branchestatus": "niet_in_branche"
      }
    ],
    "wat_je_niet_doet": [
      {
        "tekst": "Geen donker gebrande vlakke melanges waar bitterheid het podium eist",
        "bron": "brandzaak.nl, pagina 'Over ons'",
        "branchestatus": "veelvoorkomend"
      }
    ],
    "waar_je_voor_staat": [
      {
        "tekst": "We schuilen niet achter labels, certificeringen of keurmerken.",
        "bron": "brandzaak.nl, pagina 'Duurzaamheid'",
        "branchestatus": "niet_in_branche"
      }
    ]
  }
}
```

Draai stap D en E vijf keer en vergelijk met `testset-brandzaak-posts-gouden-scoring.json`.

**Klaar als, in elk van de vijf runs:**
- `neutraal` precies gelijk is (post 15 en 18);
- de kant (y ≥ 50 of < 50) gelijk is voor minstens 22 van de 23 niet-neutrale posts;
- het niveau gelijk is of één stap afwijkt voor minstens 21 van de 23 posts;
- `algemeen` (wel of geen thema) gelijk is voor minstens 22 van de 23 posts;
- de as gelijk is voor minstens 19 van de 23 posts;
- de drift op de meetmomenten 10, 15, 20 en 25 hoogstens 5 punten afwijkt van de gouden set: −20, +1, −5 en −19;
- de spreiding van de drift over de vijf runs per meetmoment hoogstens 4 punten is.

Leg bij elke afwijking het citaat naast de gouden scoring. Dan kan Rogier beslissen of de prompt of de gouden set moet worden aangepast. Een deel van de berichten in de gouden set is alleen op titel en samenvatting beoordeeld. Waar Erik de volledige tekst wel kan lezen, kan het oordeel terecht anders uitvallen.

## Test 2: verzamelen

Laat zien dat de scan:
- het nieuwsarchief van brandzaak.nl ophaalt;
- een geplakte lijst met drie datumnotaties goed splitst;
- een echte Instagram-export en een echte LinkedIn-export inleest. Rogier levert zijn eigen exports.

## Test 3: snelle branche

Zie deel C, "Test en acceptatie": snel tegen volledig (branche 88 en 34), het effect op de score van Brandzaak, en een merk buiten de bibliotheek.

## Test 4: einde tot einde

Een volledige scan op Brandzaak, en op twee merken die Rogier aanlevert: één in een onderzochte branche, één met "Andere branche". Meet per scan de doorlooptijd en de kosten.

## Opleveren

- de uitvoer van test 1 als JSON (vijf runs), met een vergelijkingstabel;
- de resultaten van test 2 en 3;
- de cijfers en de drie rapporten uit test 4;
- screenshots van de schermen op laptop en telefoon;
- de commit-hash.

---

# Deel G. Open punten

1. **Direct of na controle.** De site zegt nu "binnen twee werkdagen je rapport". Voorstel: de scan draait direct, maar de eerste 20 rapporten gaan eerst naar Rogier en worden in het beheerscherm vrijgegeven. Daarna kiezen we of het direct naar de bezoeker kan. De tekst op de site past zich daarop aan.
2. **Bewaartermijn** van scans en posts.
3. **Contact.** Waar "Maak een afspraak" en het telefoonnummer op de site en in het rapport naartoe gaan. Klanten uit de scan vallen onder Brandflocks.
4. **Domeinen.** brandidentitydrift.nl en brandidentitydrift.ai sturen door naar brandidentitydrift.com.
5. **Voorbeeldrapport.** Het voorbeeldrapport gebruikt nog handmatige hoogtes en wijkt daardoor 0 tot 2 punten af van de gouden set. Als de scan draait, maakt die het voorbeeld opnieuw.
