# HBA ronde 6: pilotklaar

Rogier Heijning, 17 oktober 2026

---

## Voor Erik

Erik, dank voor het bouwrapport en de screenshots. Alle besluiten van 16 oktober zijn gebouwd, en het koppeldocument v0.3 is nu de enige bron. Dat is precies wat ik wilde.

Maar de metingen laten zien dat de pilotversie v1.10.8 slechter is dan v1.10.6: 11 in plaats van 16 posts stabiel, 5 instabiel of fout, en 18,9 procent correcties door de code. In de screenshots zitten daarnaast drie inhoudelijke fouten die een pilotklant direct ziet. Deze ronde maakt de app pilotklaar.

**De bevriezing van v1.10.8 vervalt.** Na deze ronde komt er een nieuwe pilotversie, en die bevriezen we pas als hij de klaar-criteria onderaan haalt.

Je kunt dit hele document aan Cursor geven. Werk stap voor stap, één commit per stap. Zet niets op productie. Gebruik Opus 5 voor het promptwerk (stap 1 tot en met 4) en Composer 2.5 voor de rest.

### In het kort

| Stap | Wat | Waarom |
| --- | --- | --- |
| 1 | Hoofdoordeel in de code | Stabiliteit terug op minstens het niveau van v1.10.6 |
| 2 | Hint geeft de regel letterlijk weer | "De grens nadert" staat niet in de regel |
| 3 | Geen verzonnen feiten in het artikel | "Op bestelling" en "uitverkocht" staan niet in de merkarchitectuur |
| 4 | Advies over het thema zichtbaar | De vraag "Past dit onderwerp bij je merk?" krijgt geen antwoord |
| 5 | Klanttaal (A6) | Rest-Engels en interne namen weg |
| 6 | Uitsplitsing ijkrun | 26 fouten zonder oorzaak |
| 7 | Ijkset krijgt de velden van de wizard | T13 tegenover Duinroos post 7 |
| 8 | Stroomtest Brandpunt | Eerste volledige analyse van één merk |
| 9 | Pilotversie vastzetten en eindmeting | Klaar-criteria voor de pilot |

---

## Opdracht voor Cursor

Context: dit is de HBA-laag in ContentFlow (iGiSNext). Branch `hba-ronde-4-v1.9`, scoring prompt v1.10.8, model `claude-sonnet-4-6`. Het model blijft hetzelfde. Na stap 1 wordt de promptversie **v1.11**.

Lees eerst:

- `docs/hba-ijkset-koppeling-v0.3.md` (de enige bron voor besluiten 7.1 tot en met 7.7);
- `docs/hba-v1.10-bouwrapport-2026-10-17.md`;
- `docs/hba-t13-vs-duinroos-7.md`;
- `web/api/prompts/hba-regelbreuk-definitie.txt` en `hba-verbeteradvies-definitie.txt`;
- `briefing/hba-input/Voor Erik 2026-10-16/hba-besluiten-en-nulmeting-2026-10-16.md` §3 (volgorde hoofdoordeel) en §6 (klanttaal).

Ga pas naar de volgende stap als de klaar-criteria van de huidige stap gehaald zijn. Maak per stap één commit, met het stapnummer in de commit-message.

---

### Stap 1: Hoofdoordeel in de code

**Probleem.** De volgorde van het hoofdoordeel staat nu twee keer in het systeem: in de prompt en in het PHP-filter. Die twee lopen uit elkaar. `hoofdoordeel_gecorrigeerd` ging van 7,4 naar 18,9 procent, en de stabiliteit van 16 naar 11 posts.

**Oplossing.** Het hoofdoordeel volgt uit een vaste volgorde van flags. Laat het model dus alleen de flags met citaten geven, en laat de code het hoofdoordeel bepalen.

1. Haal de keuze van het hoofdoordeel uit de scoreprompt. Het model levert per tekst: de flags (`constraint_breach`, `foe_vervaging`, `proof_gap`, `category_fallback`), per flag het citaat, en of de kern van de tekst categorietaal is (`kern_categorietaal: ja/nee`). Het model levert geen hoofdoordeel meer.
2. Bepaal het hoofdoordeel in PHP, in deze volgorde:
   1. `regelbreuk`: `constraint_breach` die de controlecall bevestigt;
   2. `tegenkracht`: `foe_vervaging` met een citaat uit de kop of de openingszin;
   3. `categorietaal`: `kern_categorietaal = ja`, ook als er in een bijzin `foe_vervaging` staat;
   4. `op_merk` of `neutraal`: op basis van merkbijdrage en contentrol, met dezelfde grens als nu.
3. Een tekst die zich tegen de tegenkracht keert, krijgt geen `foe_vervaging`. Zet dat in de flagdefinitie, met het voorbeeld van Brandpunt: "Black Friday laat zien wat er gebeurt als prijs het enige argument is" is op merk.
4. `hoofdoordeel_gecorrigeerd` vervalt als meting. Vervang hem door een controle: het hoofdoordeel volgt in 100 procent van de scores uit de flags.
5. Verhoog de promptversie naar **v1.11**.

**Klaar als:**
- 5 × 19 op v1.11 geeft minstens 16 posts 5 van 5 goed, en verder alleen review (zoals v1.10.6);
- geen instabiele posts, en de spreiding in merkbijdrage is hoogstens 1 op alle posts;
- Stelwerk post 5 is 5 van 5 `categorietaal`; Duinroos post 7 is 5 van 5 `tegenkracht` met merkbijdrage hoogstens 5;
- het hoofdoordeel volgt in 100 procent van de scores uit de flags.

Lukt dat niet na twee pogingen, stop dan en meld het met de ruwe output.

---

### Stap 2: De hint geeft de regel letterlijk weer

**Probleem.** In het zijpaneel van het Brandpunt-artikel (screenshot 6) schrijft de hint: "koffie die de grens van zes weken *nadert* naar de voedselbank gaat". De regel is: "ouder dan zes weken". Dezelfde fout zat in ronde 5 in de uitleg van de regelbreuk, en is daar opgelost. In de hint nog niet.

1. Voeg aan de hintprompt dezelfde eis toe als in de regelbreukdefinitie en het verbeteradvies: noemt de hint de regel, dan geeft hij hem weer zoals hij in de geloofwaardigheidstest staat. Geen afgeleide grens ("nadert", "rond", "bijna"), geen andere getallen.
2. Doe hetzelfde voor de adviezen over thema en onderzoek, en voor de toelichting in het zijpaneel "Merkarchitectuur".

**Klaar als:** de hint op het Brandpunt-artikel en de drie V0-hints de regel letterlijk weergeven, in 5 van 5 runs.

---

### Stap 3: Geen verzonnen feiten in het artikel

**Probleem.** Het artikel "Waarom wij geen Black Friday doen" (week 41, Brandpunt, screenshot 6) zegt: "Wij branden in kleine batches, op bestelling", "je verstuurt het snel" en "soms is een batch uitverkocht". Dat staat niet in de merkarchitectuur, de bewijsvelden of de research.

1. Zoek uit met welke versie van CE-03 dit artikel is geschreven (vóór of na de premissecheck van ronde 5, stap 2).
2. Schrijf het artikel opnieuw met de huidige CE-03, op hetzelfde thema en dezelfde research.
3. Bevat de nieuwe versie nog feiten die nergens staan, dan werkt de regel "Verzin geen feiten over het merk" niet. Laat `self_review` dan per bewering over het merk vastleggen waar die vandaan komt (merkarchitectuur, bewijsveld, research of geen bron), en laat de schrijver beweringen zonder bron weghalen of vervangen door de werkwijze uit de merkarchitectuur.
4. **Aanspreekvorm.** Het artikel spreekt de lezer aan met "u". Maak de aanspreekvorm een veld in de merkarchitectuur ("je" of "u"), en laat de schrijver dat volgen. Brandpunt is "je".

**Klaar als:** het opnieuw geschreven artikel geen bewering over het merk bevat zonder bron, `self_review` de bron per bewering laat zien, en het artikel "je" gebruikt.

---

### Stap 4: Advies over het thema zichtbaar

**Probleem.** Op Onderzoek (screenshot 5) staat boven "Black Friday-actie" de vraag "Past dit onderwerp bij je merk?", maar zonder antwoord. Voor Brandpunt is dit precies het thema dat met de regel botst. Het eerste moment waarop de app meedenkt, bij de keuze van het thema, is voor de klant onzichtbaar.

1. Toon onder de vraag het oordeel over het thema (versterkt, neutraal of botst), met één zin waarom, in gewone taal.
2. Botst het thema met de regel, zeg dat dan, noem de regel letterlijk (zie stap 2), en geef de invalshoek die wél past: schrijven over waarom het merk dit niet doet.
3. Hetzelfde op Thema's, zodra er themavoorstellen zijn.

**Klaar als:** bij "Black Friday-actie" voor Brandpunt staat dat het thema botst met de regel, met de regel letterlijk en de invalshoek die past; screenshots van Onderzoek en Thema's (na "Genereer thema-voorstellen").

---

### Stap 5: Klanttaal (A6)

Interne waarden en Engelse woorden die niet gangbaar zijn in het Nederlandse vak, ziet de klant niet. "Community", "service" en "engagement" mogen blijven.

| Scherm | Aanpassen |
| --- | --- |
| Overzicht | "Toets actief" wordt "Merkcontrole aan". |
| Merkarchitectuur | "Je persoonlijkheid: Maker" weg; het doel staat er al als "Wat jullie willen bereiken". "Wat jullie bewust niet doen" wordt "Wat jullie nooit over jezelf willen lezen". "Tone of behaviour" wordt "Toon". "Kernformulering 1, 2, 3" wordt "Hoe jullie werken" (één veld, met de mogelijkheid er een toe te voegen). Overal "jullie", niet afwisselend "je" en "jullie". Save en Rewrite in het Nederlands. Geen "HBA" in de uploadtekst. |
| Merkelementen | De Schrijfwijzer herhaalt de toon van Merkarchitectuur; toon hem op één plek. "Schrijfstijl per tone" wordt "Schrijfstijl per doelgroep", met "Consumenten", "Persoonlijk" en "Zakelijk". "Verwijder" wordt "Verwijderen", zoals bij de terugkoppeling. |
| Thema's | "Eigen theme" wordt "Eigen thema". "Enter title" wordt "Titel". "optional URL to research article" wordt "Link naar een bron (optioneel)". |
| Onderzoek | "Review" wordt "Beoordelen". Toon bij een bron alleen de naam van de site, niet de volledige Google News-link. |
| Artikel en Terugkoppeling | "Upload image" wordt "Afbeelding toevoegen". "Re-edit" en "Post name" in het Nederlands. |
| Vindbaarheid | "GEO-vragen" wordt "Vragen aan AI", "SEO-vragen" wordt "Vragen aan Google". "+ Add question" wordt "+ Vraag toevoegen". Save en Undo worden "Opslaan" en "Ongedaan maken". |
| Planning | "Planning horizon", "Post per week" en "Checks per week" in het Nederlands. "Overview" wordt "Overzicht". Het voorgestelde ritme noemt nu "autoriteit, inzicht, product-of-dienst". Gebruik dezelfde contentrollen als Overzicht en Rapport (Positionering, Bewijs, Kennis delen, enzovoort). |
| Rapport | "(HBA v1.7)" en elke andere interne versie weg uit de titels. Toon "Verschilpercentage op-strategie vs. generiek" pas vanaf tien posts, en met een uitleg in één zin. |
| Agenda | Datums en maanden in het Nederlands ("17 oktober 2026", "5 oktober tot 11 oktober"). "kies een theme" wordt "kies een thema". |

**Klaar als:** nieuwe screenshots van Merkarchitectuur, Merkelementen, Thema's, Onderzoek, Artikel, Vindbaarheid, Planning, Rapport en Agenda, plus Terugkoppeling op een artikel **met** open terugkoppeling, zodat de knop Verwijderen in beeld is. Geen enkel Engels woord dat niet gangbaar is, en geen interne waarde.

---

### Stap 6: Uitsplitsing van de ijkrun

**Probleem.** De nulmeting v0.3 op v1.10.8 geeft 7 goed en 26 fout. Bij v0.2 waren het er 20 goed. A09 tot en met A13 gingen eerder goed en nu fout. Zonder oorzaak kan ik niets beslissen.

Dit is een meting, geen fix. Pas niets aan in ijkset of judge.

1. Draai de ijkrun op v1.11 (na stap 1 tot en met 4).
2. Geef per fout geval:
   - op welke as het misgaat: **oordeel**, **meetlat** of **signaal**;
   - wat de ijkset verwachtte en wat de app gaf (oordeel, meetlat-status, dimensies, signalen);
   - bij signalen: welk signaal ontbrak of verboden was, en het citaat uit de output.
3. Tel apart: hoeveel fouten komen alleen door de nieuwe judge-regels van v0.3 (meetlat, `meetlat_geblokkeerd met oordeel en advies`), en hoeveel door de inhoud.
4. **A17 (Ter Horst):** welke dimensie staat op aanscherpen, met de volledige toelichting van de check.

**Klaar als:** er een tabel ligt met alle fouten per as, en een aparte telling "fout door judge-regel" tegenover "fout door inhoud".

---

### Stap 7: De ijkset krijgt de velden van de wizard

**Uitkomst T13 tegenover Duinroos post 7:** de testmerken geven veertien velden mee, de ijkset acht. Een echte klant komt binnen via de wizard. De ijkset moet meten wat een echte klant krijgt.

1. Maak een lijst van alle velden die de wizard nu vastlegt, met per veld de naam in de app, de vraag aan de klant en of het verplicht is.
2. Zet daarnaast welke velden de ijkset meegeeft, en welke de testmerken.
3. Laat de runner de ijkset-input doorgeven in precies de velden van de wizard. Velden die de ijkset niet heeft, blijven leeg.
4. Pas de ijkset zelf niet aan. Ik vul de ontbrekende velden in, in v0.4.

**Klaar als:** de vergelijkingstabel (wizard, ijkset, testmerken) er ligt, en de runner de wizardvelden gebruikt.

---

### Stap 8: Stroomtest Brandpunt

Gebruik de bestanden van 16 oktober: `brandpunt-onboarding.md`, `brandpunt-content-25-posts.md` en `Brandpunt_stroomtest_25.json`.

1. Nieuwe testklant via de echte wizard. Poging 1 (half ingevuld) moet geblokkeerd worden, met oordeel en advies. Poging 2 (volledig) moet `meetlat_klaar` en de status actief geven.
2. Daarna de 25 posts in volgorde van datum, op v1.11.
3. Leg na post 10, 15, 20, 21 en 25 het Overzicht en het Rapport vast (screenshot plus ruwe data).
4. Meld waar de drempels van de app afwijken van die in de verwachtingen.

**Klaar als:** beide onboardingpogingen en de vijf checkpoints vastliggen, met per checkpoint wat de app liet zien naast wat ik verwachtte.

---

### Stap 9: Pilotversie vastzetten en eindmeting

1. Zet de versie na stap 1 tot en met 5 vast als pilotversie, en noteer het versienummer.
2. Draai op die versie: 5 × 19, de ijkrun (met de uitsplitsing van stap 6), V0 en CE-04.

---

## Klaar voor de pilot

- [ ] 5 × 19: minstens 16 posts 5 van 5 goed, geen instabiele posts, spreiding merkbijdrage hoogstens 1.
- [ ] Het hoofdoordeel volgt in 100 procent van de scores uit de flags.
- [ ] Hints, adviezen en zijpaneel geven de regel letterlijk weer.
- [ ] Geen bewering over het merk zonder bron in nieuwe artikelen.
- [ ] Advies over het thema zichtbaar op Onderzoek en Thema's.
- [ ] Klanttaal: nieuwe screenshots zonder Engels of interne waarden, en goedgekeurd door mij.
- [ ] Stroomtest gedraaid en door mij bekeken.
- [ ] Ijkrun uitgesplitst per as. De valkuilen hoeven voor de pilot nog niet 100 procent te zijn; voor go-live wel.

Wanneer de pilot start, met welke partijen en op welke omgeving, beslissen jij en ik samen.

---

## Afronden

Lever aan mij:

1. Na stap 1: de 5 × 19 op v1.11.
2. Na stap 5: de nieuwe screenshots.
3. Na stap 6 en 7: de tabel met de fouten per as, en de vergelijking wizard, ijkset en testmerken.
4. Na stap 8: de onboarding en de vijf checkpoints van de stroomtest.
5. Na stap 9: de pilotversie met het versienummer en de eindmeting.

Regels:

- Eén commit per stap.
- Staging alleen na een "ja" van jou; niets op productie.
- Lukt een klaar-criterium niet na twee pogingen, stop dan en meld het met de ruwe output erbij. Ga niet verder schuiven aan de prompt.

---

## Antwoorden op de openstaande vragen

**Erik, 18 oktober 2026.** Onderbouwd met de code; de regelverwijzingen staan erbij. Twee punten gaan door naar Rogier (B3 en de kanttekening bij B5); de rest kan Cursor als besluit lezen.

### A. Uitvoering

**1. Branch `hba-ronde-6-pilotklaar` aanmaken — ja.** Vertak vanaf de huidige `hba-ronde-4-v1.9` en doe stap 1 daarop, één commit, geen staging zonder een expliciete "ja staging". Twee dingen vooraf:

- er staan nog ongecommitte wijzigingen op `hba-ronde-4-v1.9` (onder meer `docs/BUILD-NOTES.md`); commit of stash die eerst, zodat de vertakking een schoon punt is;
- de contextregel in dit document noemt nog `hba-ronde-4-v1.9` als werkbranch. Vanaf stap 1 is dat `hba-ronde-6-pilotklaar`.

### B. Scope en product

**2. Kernformulering 1–3 → één veld: UI-samenvoeging, geen DB-migratie.** Het is nu al één JSON-kolom: `web/migrations/add_kernformuleringen.sql` voegt `kernformuleringen JSON NULL` toe, een array. De drie velden in het scherm zijn dus alleen een weergave. Twee dingen moeten wel mee, anders breekt een variabel aantal:

- `web/api/hba-v2-prompts.php:220` eist "precies 3 korte zinnen". Maak daar 2 tot 5 van;
- `kernformulering_hit` is een 1-based index in die array (`hba-prompt.php:706`, kolom `kernformulering_hit TINYINT`). Valideer die index tegen de werkelijke lengte in plaats van tegen 3.

De import van de testmerken knipt nu al af op vijf (`hba-testmerken-repository.php:1616`), dus vijf is een logisch maximum.

**3. Schrijfwijzer op Merkelementen: laten staan en bewerkbaar houden** — maar de scopevraag gaat naar Rogier.

Feitelijk: `SchrijfregelsSection` wordt alleen gebruikt door `StrategyPage` (Merkelementen). Er is dus geen tweede exemplaar dat je kunt weghalen. Wat Rogier ziet is geen dubbel component maar overlap in inhoud: Merkarchitectuur heeft "Tone of behaviour" (straks "Toon"), Merkelementen heeft de Schrijfwijzer. Dat zijn twee verschillende dingen met één onderwerp.

Voorstel: Toon op Merkarchitectuur is hoe het merk zich gedraagt — HBA, Rogiers domein. De Schrijfwijzer op Merkelementen is de praktische spelregel voor de schrijver — spelling, taboes, aanpak — en CE-03 heeft hem nodig. Beide blijven bewerkbaar; we halen alleen de toonherhaling uit de hint van de Schrijfwijzer. Read-only maken zou het veld onbruikbaar maken zonder dat er een tweede plek is om het te bewerken.

> **Bevestiging Rogier:** [ ] Toon en Schrijfwijzer blijven twee velden met een afgebakende scope.

**4. Stap 3 op CF_Staging, geen nieuwe Brandpunt-tenant.** Stap 3 schrijft het artikel opnieuw "op hetzelfde thema en dezelfde research", dus het moet draaien op de tenant waar dat week 41-slot staat. Zoek dat slot op en gebruik die omgeving; dat is CF_Staging. Maak er geen aparte Brandpunt-tenant voor: stap 8 zet er al één op via de echte wizard, en Rogiers testmateriaal mag niet buiten hem en mij, dus het hoort hoe dan ook niet op productie. Lever screenshots.

**5. Bevestigd: de pilotversie is de code na stap 1 tot en met 5.** Stap 9.1 zegt het letterlijk ("de versie na stap 1 tot en met 5"). Stap 6, 7 en 8 zijn metingen en gereedschap op die versie, geen wijzigingen eraan — stap 7 raakt de runner, niet de app. Stap 8 telt mee voor "pilotklaar" (het staat als vinkje in de klaar-lijst), maar de formele bevriezing en de eindmeting zijn stap 9.

Eén regel die daarbij hoort en nu nergens staat: **komt er uit stap 6, 7 of 8 een fout die de scoring raakt, dan verandert de pilotversie en moet de 5 × 19 van stap 1 over.** Een fout die alleen de weergave raakt niet. Dat is geen vrijheid die we zelf nemen; ik leg het aan Rogier voor.

> **Bevestiging Rogier:** [ ] Een scoringsfix na stap 5 maakt de 5 × 19 ongeldig en de run gaat over.

### C. Technisch (stap 1)

**6. `kern_categorietaal` komt erbij, hij vervangt `category_fallback` niet.** Ze beantwoorden twee verschillende vragen: `category_fallback` zegt dát er categorietaal in de tekst zit, `kern_categorietaal` zegt of dat de kern is. Alleen de tweede bepaalt trede 3 in de volgorde; de eerste blijft nodig als signaal voor de dimensie en voor wat de judge bij T10 verwacht. Rogier noemt in stap 1.1 dan ook allebei.

Wat wél weg moet: `web/api/hba-prompt.php:139`, de regel dat een hoofdoordeel `categorietaal` een `category_fallback` vereist. Na stap 1 leidt PHP het oordeel af, en `kern_categorietaal = ja` moet dan `categorietaal` geven ook zonder die flag. Blijft die regel staan, dan zijn er weer twee bronnen.

**7. Ja, de judge blijft ongewijzigd, en 7.1 ook.** De judge leest het veld `hoofdoordeel` uit de score-DTO (`hba-ijkrun-repository.php:922` en `929`) en kijkt niet waar die waarde vandaan komt. Zolang PHP de afgeleide waarde in datzelfde veld schrijft, merkt de judge niets. Voeg wel één controle toe aan stap 1: het veld is na afleiding nooit leeg. Een `null` zou de judge stilzwijgend als afwijking tellen.

### D. A6 en screenshots

**8. Ja, staging gebruiken voor de terugkoppeling-screenshot.** Het is Rogiers eigen testmateriaal uit het HBA-archief, er staat geen klantdata in, en op productie hoort het niet. Dat is precies waar staging voor is.

**9. Ja, meenemen in stap 5.** `frontend/src/components/FeedbackCard.tsx:47-48` heeft `deleteLabel="delete"` en `confirmLabel="Confirm delete"`. Dat worden "Verwijderen" en "Verwijderen bevestigen". Rogier vraagt in dezelfde tabel al om "Verwijder" → "Verwijderen" op Merkelementen; zelfde woord, zelfde ronde.
