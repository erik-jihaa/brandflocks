# Bouwaddendum ronde 8: contentstrategieën, campagnes en de vragen- en feitenbank

27 oktober 2026, Rogier. Versie 1.0. Voor Erik en zijn AI.

Dit addendum beschrijft een uitbreiding van Brandflocks in drie delen:
1. **Contentstrategieën en campagnes:** de manieren waarop bureaus en klanten content plannen. Een campagne heeft een periode, kanalen, aantallen en een opbouw.
2. **De vragen- en feitenbank:** per merk 10 waardevragen en 10 merkfeiten. Ze verbinden de content met de vindbaarheid in AI (GEO) en met de website.
3. **De verbinding tussen de twee,** zonder dat de app dogmatisch wordt.

## Waarom

Bureaus maken content niet post voor post. Ze werken met vaste strategieën: pijlers, series, een lancering, een groot stuk dat ze opknippen, een themaperiode. Dat hebben de meeste content-tools ook. Het is dus geen onderscheid, maar een klant verwacht het wel.

Het onderscheid van Brandflocks zit in wat de app erbij doet. Bij elke strategie bewaakt de app of het merk erin blijft staan, vooraf, tijdens en achteraf. Zo kan een bureau verkopen en campagnes maken zonder dat het merk wegzakt in de taal van de branche.

**Uitgangspunten**
- **Het bureau kiest.** Thema's, standpunten en wat oppakbaar is, blijft het werk van het bureau. De app levert de structuur, stelt voor en bewaakt. Hij neemt het werk van het bureau niet over.
- **Geen vaktaal in beeld.** De klant ziet geen HBA-termen. Waar dit voorstel "rol", "doel", "tegenkracht", "mechanisme" of "regel" zegt, gebruikt de app klanttaal: wat je doet, waarom je het doet, waar je tegen bent, hoe je werkt, waar je nee tegen zegt.
- **De scoring blijft bevroren.** Campagnes voegen alleen gegevens toe aan een post: campagne, fase en rol. De bestaande scoring (v1.13) verandert niet. De nieuwe check vooraf (paragraaf 4) is een aparte prompt met een eigen versie.
- **Het ontwerp is van Erik.** Dit addendum beschrijft wat de app doet, niet hoe het eruitziet.
- **Adviseren, niet afdwingen.** Elke check in dit addendum is een signaal met een voorstel, nooit een blokkade. Het bureau mag altijd anders kiezen. De app legt alleen vast dat er anders is gekozen, net als bij Negeer. Een app die alles afdwingt, maakt steeds dezelfde posts en raakt snel uitgeput. Een bureau haakt dan af.

---

## 0. Wat er al in de app zit

Op basis van de screenshots van staging (ronde 6 en 7) zit er al een basis in de app.

| Onderdeel | Wat het nu doet | Wat het voor campagnes betekent |
|---|---|---|
| **Planning** | Planhorizon in weken, posts per week, broncontroles per week. Een vast ritme van rollen: Positionering, Kennis delen, Bewijs. | Het ritme is een eenvoudige vorm van pijlers. Wat ontbreekt: een periode met begin en eind, aantallen per kanaal, en fases die over weken lopen. |
| **Thema's** | Per week één thema. De app stelt thema's voor, met een oordeel ("versterkt") en een "Advies voor je agenda" over wat nog ontbreekt in het merkverhaal. | Dit is al de check vooraf op één thema, plus de dekking. Campagnes kunnen dit hergebruiken. |
| **Ritmrol en fasecyclus** | Een thema krijgt een rol ("Autoriteit") en een plek in een fasecyclus. | Dit lijkt op de fases in dit voorstel. Mogelijk kunnen de sjablonen hierop worden gebouwd. |
| **Agenda** | Per week een thema en een artikel, in kalender- en lijstweergave. | Hier komen de campagneperiodes bij. |
| **Artikel en Snel artikel** | Eén post per thema, met beeld, hashtags en terugkoppeling op het merk. | Dit blijft de schrijffunctie per geplande post. |

**Wat ontbreekt:**
- een campagne als eenheid, met eigen periode, doel en opbouw over meerdere weken;
- aantallen per kanaal;
- sjablonen voor een lancering, een serie of een opgeknipt groot stuk;
- de check op branchehygiëne bij een themaperiode;
- het markeren van een actieperiode.

Dit voorstel is daarom een **laag bovenop wat er al is**, geen nieuwe module. De bestaande onderdelen blijven werken voor wie gewoon per week een thema kiest.

## 1. De campagne-assistent

Een klant of bureau maakt een campagne aan in een paar stappen.

| Stap | Vraag | Invoer |
|---|---|---|
| **1. Soort** | Wat voor campagne wil je maken? | Een van de zes sjablonen (paragraaf 2), of vrij |
| **2. Onderwerp** | Waar gaat het over? | Afhankelijk van het sjabloon: een of meer productpagina's (URL's), een eigen thema of een thema uit de voorstellen, een serienaam, een geüpload stuk (essay, whitepaper, webinar-transcript), een doelgroep |
| **3. Doel** | Wat moet de campagne doen? | Verkopen, je merk laten zien, of allebei |
| **4. Wat het moet laten zien** | Welk deel van je verhaal laat deze campagne zien? | De app stelt een of twee onderdelen voor uit de merkarchitectuur. De klant mag wijzigen. |
| **4b. Ankers** | Welke klantvragen en feiten neemt deze campagne mee? | De app stelt 1 tot 3 vragen en 1 tot 3 feiten voor uit de bank (paragraaf 3). De klant kiest, wijzigt of slaat over. |
| **5. Periode** | Wanneer? | Startdatum en einddatum, of een aantal weken. Bij een serie: welke dag en hoe vaak. |
| **6. Kanalen en aantallen** | Waar, en hoeveel? | Per kanaal het aantal posts in de periode, of per week. Bijvoorbeeld LinkedIn 8, Instagram 12, nieuwsbrief 2, blog 1. |
| **7. Opbouw** | In welke fases? | De app stelt de fases van het sjabloon voor, met per fase een aandeel. De klant kan fases verschuiven, weglaten of toevoegen. |
| **8. Plan bekijken** | Klopt het? | De app toont het plan in de kalender, met de uitkomst van de check vooraf (paragraaf 4). Daarna bevestigen. |

**Na het bevestigen** maakt de app per geplande post een korte opdracht:
- datum en kanaal;
- fase en rol (verkopen of merk laten zien);
- het onderdeel van het merk dat de post moet laten zien;
- als de post een anker meekrijgt: de vraag of het feit (zie paragraaf 3);
- de kern in één zin.

Die opdracht gaat naar de bestaande schrijffunctie (Article of Fastlane). Elke post wordt daarna gescoord zoals nu.

---

## 2. De zes sjablonen

Per sjabloon staan de fases, het voorgestelde aandeel per fase, en wat de app bewaakt.

### A. Lancering van een product of productlijn

Voor een nieuw product, een productlijn of een nieuwe dienst. Een lancering is activatie, maar dan zo dat het product het merk bewijst.

**De productpagina is het uitgangspunt.** Het bureau geeft één of meer productpagina's op. De app leest ze (zoals stap A in het bouwpakket van de driftmeter) en haalt er per product uit:
- wat het is;
- wat het anders maakt;
- welk bewijs er staat.

Bij een productlijn met meerdere pagina's:
- De app maakt één rode draad: waarom deze lijn bestaat.
- De producten worden over de posts verdeeld. Het bureau kan per product aangeven hoeveel posts het krijgt.
- Mist een pagina iets wat de posts nodig hebben, bijvoorbeeld bewijs of het waarom, dan meldt de app dat. Dat is meteen een tip voor de pagina zelf.

Hetzelfde werkt zonder lancering, voor een product dat al bestaat. Kies dan sjabloon B (een serie over een productgroep) met de productpagina's als bron.

| Fase | Aandeel | Wat de post doet |
|---|---|---|
| Aankondiging | 15% | Wat het is en voor wie |
| Waarom we dit maken | 20% | Het doel, of de tegenkracht die het product oplost |
| Hoe het werkt | 20% | Wat dit product anders maakt dan dat van de branche |
| Wat we bewust niet doen | 10% | Een keuze rond het product die laat zien waar je nee tegen zegt |
| Bewijs | 20% | Een klant, een maker, een test |
| Actie | 15% | Nu bestellen, proberen, aanmelden |

**De app bewaakt:**
- dat het product niet alleen met kenmerken en branchetaal wordt verteld;
- dat het product niet strijdt met een regel van het merk;
- dat de productpagina de waardevragen beantwoordt (koppeling met de GEO-meter).

**Ankers:** feiten over het product (wat het anders maakt, bewijs) en vragen uit de fases vergelijken en kiezen. De posts wijzen naar de productpagina.

### B. Serie of vaste rubriek

Een herkenbaar terugkerend format, zoals "Elke dinsdag: zo branden wij", "Maak kennis met", of een reeks over één productgroep.

| Instelling | Invoer |
|---|---|
| Naam van de serie | Vrij |
| Ritme | Vaste dag, hoe vaak, aantal afleveringen of doorlopend |
| Format | Vaste opbouw van een aflevering, bijvoorbeeld vraag, antwoord, voorbeeld |
| Wat de serie laat zien | Eén onderdeel van het merk |

**De app bewaakt:**
- dat elke aflevering hetzelfde onderdeel laat zien;
- dat de serie niet verandert in algemene tips die elke concurrent kan schrijven.

**Ankers:** één feit als rode draad van de serie, of één vraag per aflevering. Een serie "Jullie vragen, ons antwoord" is de natuurlijke plek voor de waardevragen.

### C. Eén groot stuk opknippen

Het bureau levert of schrijft één groot stuk: een essay, een standpunt, een whitepaper, een eigen onderzoek, of het transcript van een webinar of podcast. De app knipt het op in losse posts over de periode.

| Fase | Aandeel | Wat de post doet |
|---|---|---|
| Vooraankondiging | 10% | Nieuwsgierig maken naar het standpunt |
| Kernstellingen | 40% | Elke post één stelling uit het stuk |
| Verdieping | 25% | Een voorbeeld, cijfer of verhaal bij een stelling |
| Vraag aan het publiek | 10% | Een vraag of poll die reactie uitlokt |
| Samenvatting | 15% | De hoofdlijn, met een link naar het hele stuk |

**Werkwijze:**
- Het bureau uploadt het stuk (tekst, Word, PDF of een transcript) of plakt de tekst.
- **Het bureau bepaalt zelf hoeveel posts eruit komen**, per kanaal (bijvoorbeeld 6 LinkedIn en 10 Instagram), en over welke periode.
- De app haalt de stellingen uit het stuk, ten minste zoveel als nodig voor dat aantal posts. Het bureau kiest welke erin gaan.
- Daarna verdeelt de app ze over de posts en de fases.
- Is het stuk te dun voor het gevraagde aantal, dan zegt de app dat en stelt een kleiner aantal voor. Hij vult niet aan met algemene content.
- Elke post verwijst naar het hele stuk.

**De app bewaakt:**
- dat elke post de kern van het stuk draagt, en niet een los feitje;
- dat het standpunt van het merk is, en geen algemene mening die de hele branche deelt.

Een eigen standpunt is ook het soort content dat anderen oppakken. Daarom laat de app hier ook de pickup power zien.

**Ankers:** feiten als bewijs bij de stellingen. Het hele stuk hoort ook op de eigen website te staan, als artikel. Dan kan AI het lezen, en kunnen anderen ernaar verwijzen.

### D. Themaperiode of moment

"De maand van...", "de week van...", een seizoen, een feestdag, een internationale dag of een branche-event.

| Instelling | Invoer |
|---|---|
| Thema en datum | Vrij, of uit een lijst met vaste momenten |
| Eigen invalshoek | Het verband tussen het thema en het merk |

**De app bewaakt:**
- **Of het thema branchehygiëne is.** Gebruikt de meerderheid van de branche het thema of moment al, dan geeft de app een signaal, met het percentage uit de branchebibliotheek. Bijvoorbeeld: "Duurzaam: 11 van de 12 koffiebranderijen."
- **Een eigen invalshoek.** De app stelt een hoek voor die het thema verbindt met het merk. Zonder eigen hoek telt de campagne als algemene content.

**Ankers:** één vraag of feit dat het thema aan het merk koppelt. Dat is vaak de beste eigen invalshoek.

### E. Contentpijlers (doorlopend)

Drie tot vijf vaste pijlers waar alle gewone content onder valt.

**Werkwijze:**
- De app stelt de pijlers voor vanuit de merkarchitectuur, één per onderdeel. Het bureau mag ze hernoemen of samenvoegen.
- Per pijler een frequentie, bijvoorbeeld twee keer per maand op LinkedIn.
- Pijlers vullen de kalender tussen de andere campagnes.

**De app bewaakt** of alle pijlers aan bod komen, en laat zien welke achterblijft. Dit is de bestaande tracker voor de onderdelen van het merk uit de Implementation Kit, nu gekoppeld aan de planning.

### F. Doelgroep en klantreis

Content voor één doelgroep, verdeeld over de fases van de klantreis.

| Fase | Aandeel | Wat de post doet |
|---|---|---|
| Oriënteren | 30% | Het probleem of de behoefte herkennen |
| Vergelijken | 25% | Waar je op moet letten bij het kiezen |
| Kiezen | 25% | Waarom dit merk, met bewijs |
| Klant blijven | 20% | Gebruik, service, gemeenschap |

**De app bewaakt** dat de vragen in de fases oriënteren en vergelijken de waardevragen van de klant raken.

**Ankers:** vooral vragen, verdeeld over de fases.

### Eigen thema

Bij elk sjabloon, en ook bij de gewone weekplanning, kan het bureau **zelf een thema invoeren** in plaats van te kiezen uit de voorstellen. Het eigen thema krijgt hetzelfde oordeel als een voorstel: versterkt het het merk, is het branchetaal, of botst het met een regel? Bij een zwak oordeel stelt de app een invalshoek voor. Het bureau mag het thema altijd houden.

---

## 2b. Indeling van de kalender

Naast losse campagnes kiest het bureau een indeling voor de hele kalender. Die bepaalt welke soort content in welke verhouding terugkomt. Campagnes en pijlers vallen daarin.

| Indeling | Lagen | Voorgestelde verhouding |
|---|---|---|
| **Merkritme** (de huidige) | Positionering, Kennis delen, Bewijs | Elke week dezelfde rollen |
| **Hero, Hub, Help** | Hero: grote momenten (lancering, campagne, eigen onderzoek). Hub: terugkerende content waar mensen voor terugkomen (series, pijlers). Help: antwoorden op vragen die klanten stellen. | Hero af en toe, hub het grootste deel, help doorlopend |
| **Klantreis** | Zien, denken, doen, blijven (See, Think, Do, Care) | Per doelgroep in te stellen |
| **Eigen indeling** | Het bureau benoemt zelf de lagen | Vrij |

**Over de naam.** In het oorspronkelijke model van Google heet de derde laag "Hygiene". In Brandflocks gebruiken we **"Help"**, omdat "hygiëne" in de app al iets anders betekent: wat de hele branche zegt. Dat moet niet door elkaar gaan lopen.

**Wat de HBA-laag per indeling doet:**
- Per laag laat de app zien welk deel van het merkverhaal erin terugkomt. Een indeling waarin één laag nooit het merk draagt, krijgt een signaal.
- **Hero:** grote momenten moeten het merk het sterkst laten zien. Een hero-moment in branchetaal is de duurste vorm van drift.
- **Help:** de help-laag beantwoordt de waardevragen van klanten, in het eigen verhaal en niet in branchetaal. De vragen komen uit de vragenbank (paragraaf 3). Dit is de laag die het meest bijdraagt aan vindbaarheid in AI.
- De balans tussen merkbouw en activatie geldt over de hele kalender, ongeacht de indeling.

---

## 3. De vragen- en feitenbank

### Waarom

Brandflocks kijkt nu vanuit het merk. Een merk wordt in AI pas gevonden als het ook antwoord geeft op wat klanten vragen, en als zijn feiten overal hetzelfde staan. De bank verbindt de content met de website en met de GEO-meter.

Een kanttekening over earned media. Volgens Muck Rack (mei 2026) komt 84 procent van de bronnen die AI-assistenten aanhalen uit earned media: journalistiek, vakmedia, encyclopedieën en content van anderen. Social media is klein: YouTube en Reddit elk ongeveer 2 procent, en LinkedIn wordt niet genoemd. Een repost helpt AI dus vooral indirect. De app stuurt daarom op twee dingen die AI wel leest:
- de eigen pagina's;
- content die anderen kunnen aanhalen op het open web.

### Wat erin zit

| Bank | Inhoud | Bron | Omvang |
|---|---|---|---|
| **Vragenbank** | Waardevragen die klanten stellen, in klanttaal, zonder merknaam | De GEO-meter, of de app maakt ze uit de website en de branche (zelfde regels als prompt C in de GEO-specificatie). Het bureau kan vragen toevoegen, bijvoorbeeld uit klantenservice of reviews. | 10 om te beginnen |
| **Feitenbank** | Controleerbare merkfeiten, letterlijk geformuleerd. Bijvoorbeeld "Elke werkdag om 13:00 branden we wat die ochtend is besteld" of "Koffie ouder dan zes weken gaat naar de voedselbank". | De app haalt ze uit de website (stap B uit het bouwpakket van de driftmeter, alleen citaten met een concreet gegeven). Het bureau bevestigt elk feit voordat het gebruikt wordt. | 10 om te beginnen |

**Per vraag:**
- de pagina die het antwoord geeft;
- of die pagina het antwoord ook echt geeft (ja, deels of nee);
- de uitkomst in de GEO-meting, als die er is.

**Per feit:**
- de vaste formulering;
- de bron-URL;
- het onderdeel van het merk dat het bewijst.

De vaste formulering is belangrijk. AI herkent een merk aan claims die overal hetzelfde terugkomen.

### Hoe de content de bank gebruikt, zonder dogma

**Per campagne, niet per post.** Een campagne kiest 1 tot 3 vragen en 1 tot 3 feiten als ankers (stap 4b). Losse posts en de gewone weekplanning hoeven niets.

**Richtwaarde, geen eis.** Binnen een campagne draagt ongeveer **een op de drie posts** een anker. De app verdeelt de ankers over de fases waar ze passen:
- vragen in de fases oriënteren, vergelijken en kiezen, en in de help-laag;
- feiten in de fases "hoe het werkt", "bewijs" en "waarom".

De andere posts zijn vrij.

**Het feit staat vast, het verhaal varieert.** Een feit komt steeds in dezelfde woorden terug, maar het verhaal eromheen wisselt: een klant, een maker, een cijfer, een vraag, achter de schermen. Zo blijft het consistent zonder eentonig te worden.

**Rust en afwisseling.**
- Een feit komt hoogstens één keer per week per kanaal terug.
- Een vraag die is beantwoord, rust een maand, tenzij het bureau hem bewust herhaalt.
- De app houdt bij welke vragen en feiten weinig zijn gebruikt, en stelt die eerder voor.

**Ook te veel krijgt een signaal.** Dragen in een periode meer dan 6 van de 10 posts een anker, dan meldt de app dat de content eentonig dreigt te worden. Het gevaar zit in twee richtingen: te weinig verbinding met GEO, en te veel herhaling.

**De bank groeit mee.** Een bank van 10 en 10 is een begin, geen grens.
- De app stelt nieuwe feiten voor uit nieuwe bronnen, zoals een nieuwe productpagina, een opgeknipt stuk of een klantverhaal.
- Nieuwe vragen komen uit een nieuwe GEO-meting of uit wat het bureau toevoegt.
- Elk kwartaal stelt de app voor de bank te verversen: vragen die goed scoren in de GEO-meting kunnen rusten, nieuwe komen erbij.

### Wat de app met de website doet

- **Per campagne één pagina.** Elke campagne met ankers heeft één doelpagina, meestal de productpagina of een landingspagina. De posts met een anker verwijzen daarnaar.
- **De pagina eerst.** Geeft de doelpagina geen antwoord op de vragen van de campagne, dan krijgt het bureau een taak: pagina aanvullen. Dat is de snelste winst voor vindbaarheid, want de eigen pagina's leest AI wel.
- **Citeerbaar maken.** Bij sjabloon C (opknippen) stelt de app voor om het hele stuk als artikel op de eigen site te zetten, met de feiten erin. Dan kan het een bron worden voor anderen.

### Meten

Is de GEO-meter gekoppeld, dan kan het bureau voor en na een campagne de GEO-meting draaien, met dezelfde vragen. Het campagneverslag toont dan of het merk vaker wordt genoemd, en met het eigen verhaal. Dit is een keuze, geen standaard; een GEO-meting kost API-calls.

---

## 4. Het plan maken

De app zet de campagne om in een plan. Voorstel voor de regels:

1. **Verdelen.** Per kanaal de posts verdelen over de periode, zo gelijkmatig mogelijk.
2. **Fases op volgorde.** Elke fase krijgt zijn aandeel, afgerond op hele posts. Een fase met een aandeel krijgt minstens één post.
3. **Kanaalregels.** Hoogstens één campagnepost per kanaal per dag. Per kanaal een minimale tussentijd die de klant kan instellen.
4. **Andere campagnes.** Valt het plan samen met een andere campagne, dan toont de app de overlap. Hij stelt voor om te schuiven of te verminderen.
5. **Dubbele aandacht.** Een lancering en een themaperiode in dezelfde week geeft een waarschuwing, want het publiek ziet dan twee verhalen tegelijk.
6. **Opnieuw plannen.** Verandert de klant de periode of het aantal posts, dan rekent de app het plan opnieuw uit. Posts die al geschreven of geplaatst zijn, blijven staan.

De kalender toont elke campagne als een periode, met de posts erin en per post de fase.

---

## 5. Wat de HBA-laag doet

Dit sluit aan op de drie ingrijppunten die de app al heeft.

### Vooraf: de check op het plan

Voordat de klant bevestigt, beoordeelt de app het plan als geheel. De uitkomst staat in klanttaal bij stap 8 van de assistent. Elk signaal heeft een voorstel en een knop om het te negeren.

| Check | Signaal als |
|---|---|
| **Balans in de kalender** | Over de komende tien posts, inclusief andere campagnes, is het aandeel merkbouw veel lager dan ongeveer 60 procent. Een gemarkeerde lancering of actieperiode mag tijdelijk afwijken. |
| **Branchehygiëne** | Het thema, of de manier waarop het product wordt neergezet, is wat de meerderheid van de branche ook zegt. |
| **Regel** | Het product of de actie strijdt met een regel van het merk, bijvoorbeeld een korting bij een merk dat nooit afprijst. |
| **Algemene content** | Meer dan twee van de tien geplande posts zijn algemene content zonder merk. |
| **Dekking** | Een onderdeel van het merk komt in de hele kalender niet voor. |
| **Ankers te weinig** | Een campagne met ankers heeft er minder dan één op de vijf posts. |
| **Ankers te veel** | Meer dan 6 van de 10 posts in een periode dragen een anker, of hetzelfde feit staat twee keer in een week op hetzelfde kanaal. |
| **Doelpagina** | De doelpagina beantwoordt de vragen van de campagne niet. |

Dit is een nieuwe prompt (`ce_hba_plan_check`) met een eigen versie. De scoring per post blijft v1.13.

### Tijdens: per post

Zoals nu. De post krijgt daarnaast de opdracht uit de campagne mee: fase, rol, het onderdeel van het merk en eventueel het anker. Daardoor kan de bijsturing gerichter zijn. Bijvoorbeeld: "Deze post hoort te laten zien hoe je werkt, maar noemt alleen de prijs." Of: "Het feit staat er anders dan op je website. Zo staat het daar: ..."

### Achteraf: het campagneverslag

Na de einddatum maakt de app een kort verslag:
- het aantal geplaatste posts per kanaal en per fase;
- de drift tijdens de campagne, naast de drift ervoor;
- welke posts het merk het best droegen, en welke het minst;
- bij een opgeknipt stuk: de pickup power per post;
- bij een lancering: of de productpagina de waardevragen beantwoordt;
- welke vragen en feiten zijn gebruikt, en hoe vaak;
- als de GEO-meting voor en na is gedraaid: de uitkomst naast elkaar.

**Campagneperiodes in de driftmeting.** Een gemarkeerde lancering of actieperiode verschuift tijdelijk de balans naar activatie. In het rapport staat dan "dit was je lancering van [datum] tot [datum]", in plaats van "je merk drijft af". Na de periode geldt de gewone balans weer. De berekening van de drift zelf verandert niet; alleen de uitleg krijgt de context mee.

---

## 6. Gegevens

**Campagne**

```json
{
  "campagne_id": "string",
  "klant_id": "string",
  "naam": "string",
  "sjabloon": "lancering|serie|opknippen|themaperiode|pijlers|klantreis|vrij",
  "onderwerp": {"type": "product|thema|serie|stuk|doelgroep", "waarde": "string", "url": "string|null", "bestand": "string|null"},
  "doel": "verkopen|merk|beide",
  "merk_onderdelen": ["wat_je_doet|waarom|waar_je_tegen_bent|hoe_je_werkt|waar_je_nee_tegen_zegt"],
  "periode": {"start": "2026-11-02", "eind": "2026-11-29"},
  "markeer_als_actieperiode": true,
  "kanalen": [{"kanaal": "linkedin", "aantal": 8, "min_dagen_tussen": 2}],
  "fases": [{"naam": "Waarom we dit maken", "aandeel": 0.20, "merk_onderdeel": "waarom", "rol": "merk"}],
  "plan_check": {"versie": "string", "signalen": [{"soort": "string", "tekst": "string"}]},
  "status": "concept|bevestigd|loopt|afgerond"
}
```

**Vragen- en feitenbank**

```json
{
  "vragen": [{"vraag_id": "string", "vraag": "string", "bron": "geo_meting|app|bureau", "doelpagina": "url|null", "pagina_antwoord": "ja|deels|nee|onbekend", "geo_uitkomst": "niet_genoemd|voor_je_product|voor_je_merk|null", "laatst_gebruikt": "datum|null", "aantal_gebruikt": 0, "actief": true}],
  "feiten": [{"feit_id": "string", "formulering": "string", "bron_url": "string", "merk_onderdeel": "string", "bevestigd_door_bureau": true, "laatst_gebruikt": "datum|null", "aantal_gebruikt": 0, "actief": true}]
}
```

**Campagne (aanvulling)**

```json
{"ankers": {"vragen": ["vraag_id"], "feiten": ["feit_id"]}, "doelpagina": "url|null", "geo_voor_na": false}
```

**Post (uitbreiding)**

```json
{"campagne_id": "string|null", "fase": "string|null", "rol": "verkopen|merk|null", "merk_onderdeel": "string|null", "anker": {"soort": "vraag|feit", "id": "string"} , "opdracht": "string|null"}
```

---

## 7. Fasering

De pilot gaat voor. Niets hiervan raakt de scoring.

| Fase | Wat | Waarom eerst |
|---|---|---|
| **1** | De assistent, het plan maken, de kalenderweergave, eigen thema, sjablonen A (lancering, met productpagina's als bron) en B (serie). De vragen- en feitenbank (10 en 10), ankers per campagne, de doelpagina, en de checks op te weinig en te veel ankers. | Hiermee werkt de app zoals een bureau werkt. De bank is klein en heeft geen GEO-meter nodig: de app maakt de vragen en feiten zelf uit de website. |
| **2** | Sjablonen C (opknippen, met zelf te kiezen aantal posts) en E (pijlers), de indeling Hero, Hub, Help, het campagneverslag | Sterkste merkbouw. Gebruikt pickup power en de bestaande tracker. |
| **3** | Sjablonen D (themaperiode) en F (klantreis), de check vooraf op branchehygiëne, de koppeling met de GEO-meter (vragen uit de meting, GEO voor en na een campagne) | Heeft de branchebibliotheek en de GEO-meter nodig |

---

## 8. Test

**Voor fase 1:**
- Maak met Brandpunt (het testmerk) een lancering van een nieuwe blend: vier weken, LinkedIn 8 en Instagram 12.
- Lever het plan, de opdrachten per post en de uitkomst van de check vooraf.
- Maak daarna een tweede versie waarin het product bewust een regel van Brandpunt breekt, bijvoorbeeld een korting op koffie die al weken ligt. Die versie moet een signaal geven.
- Maak de bank voor Brandpunt: 10 vragen en 10 feiten. Elk feit moet letterlijk op de website van Brandpunt staan. Rogier beoordeelt de vragen.
- Kies voor de lancering 2 vragen en 2 feiten als anker. Lever het plan met per post het anker. Ongeveer een op de drie posts heeft een anker, en geen feit staat twee keer in één week op hetzelfde kanaal.
- Zet daarna alle 20 posts op "anker". Dat moet het signaal "te veel" geven.

**Voor fase 2:**
- Knip een essay van ongeveer 1.500 woorden op in 10 posts.
- Rogier beoordeelt of elke post de kern draagt.

---

## 9. Vragen aan Erik

1. Kunnen de sjablonen voortbouwen op de bestaande ritmrollen en de fasecyclus, of is een aparte structuur handiger?
2. Maakt een artikel nu al varianten per kanaal (LinkedIn, Instagram en andere)? Zo ja, dan is "aantal posts per kanaal" vooral een uitbreiding van Planning.
3. Kunnen de themavoorstellen met hun oordeel en het "Advies voor je agenda" worden hergebruikt als check vooraf voor een hele campagne?
4. Kan een bureau nu al een eigen thema invoeren, of alleen kiezen uit de voorstellen?
5. Wat doet "Bronnen" nu precies? Kan een productpagina of een geüpload document daar al als bron dienen, en kunnen we die bronnen gebruiken voor sjabloon A en C?
6. Wat doet het onderdeel "Vindbaarheid" nu? Kan de vragenbank daar komen?
7. Hoe noemen we dit in de app? "Campagnes" is een voorstel.
