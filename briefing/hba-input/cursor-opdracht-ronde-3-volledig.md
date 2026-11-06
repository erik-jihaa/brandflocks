# Opdracht voor Cursor: HBA-laag ronde 3, volledig

10 oktober 2026 · Rogier Heijning

Dit document bevat alles wat in ronde 3 gebouwd en getest moet worden, in één bestand. Lees het helemaal voordat je begint.

## Werkwijze

- Werk de stappen af in deze volgorde: R0, A3, A2, R1 tot en met R6, A1 en D1, A4, A5, D2, A6, A7, A8, en tot slot de meldingen A9, D3 en D4.
- R0 eerst, omdat het openstaande punten uit ronde 2 afrondt. A3 en A2 daarna, omdat ze de invoer en het schrijven herstellen waar de rest op test.
- Stop na elke stap, commit, en vat in twee zinnen samen wat je deed voordat je verdergaat.
- Gebruik Opus 5 voor R2, R3, A1, D1, A4 en D2 (promptwerk). Gebruik Composer 2.5 voor de rest.
- Model in de pijplijn niet wijzigen: `claude-sonnet-4-6`. `CE_HBA_PROMPT_VERSION` wordt `v1.7`.
- Vervang geen teksten die de klant ziet zonder akkoord van Rogier (A6), en voeg de twee sets contentrollen niet samen (A8).
- Haalt een klaar-criterium het niet, pas de prompt dan niet zelf verder aan, maar meld het aan Erik.
- Deploy naar staging pas na Eriks "ja". Niet naar productie.

## Deel 1. Hoofdoordeel en stabiliteit (R0 tot en met R6)

### Context

Dit is ronde 3, na `cursor-briefing-ronde-2-hba-flags.md`. Stand: promptversie v1.6, run A 9 goed / 5 fout / 2 review, run B 13 goed / 1 fout / 2 review (`docs/hba-testmerken-matrix-v1.6-runA.json` en `-runB.json`).

**Uitgangspunten:**

- Model niet wijzigen: `claude-sonnet-4-6`.
- `CE_HBA_PROMPT_VERSION` wordt `v1.7`.
- Alle definities blijven op de gedeelde plek uit ronde 2. Schrijf ze zo dat ze straks zonder aanpassing naar de configuratieopslag van de kalibratiemodule kunnen.
- Geen veldnamen of flagnamen in tekst die de klant ziet.
- Volgorde: zie Werkwijze bovenaan. Draai het testprotocol hieronder pas als ook A2 en A3 klaar zijn, zodat de scores met de volledige verboden logica tot stand komen.

### R0. Afronden wat in ronde 2 openstaat

Lever deze vier punten op in het verslag, voordat je aan R1 begint:

1. **Stap 0, CE-04:** de oorzaak en het herstel. Dien post 3 van elk merk opnieuw in en leg de feedbackregel vast.
2. **T2:** de bestaande definitie van `foe_vervaging` naast de nieuwe, en of ze wezenlijk verschillen.
3. **T4:** het wizardvoorstel bij hetzelfde archetype (bestanden en schermen).
4. **T5:** het merkcijfer van de drie testmerken vóór en na delen door 10, en de andere plekken die door 5 deelden.

Zoek daarnaast op welke schaal `distinctiviteit` in de scoreprompt staat, en meld dat.

Leg ook vast welk citaat het model gaf bij `constraint_breach` op Duinroos post 6 in run A ("slimmere routes"). Staat dat niet meer in de data, meld dat.

### R1. Oordeelsregel in de matrix: geen vergelijking met post 1 meer (Composer 2.5)

Verwijder de regel "distinctiviteit op niveau post 1 (≥ post1 − 1)". Een post wordt niet meer beoordeeld ten opzichte van een andere post.

Vervang die door: een post met `verwacht_niveau: laag` is fout als `distinctiviteit` boven 2 ligt, of boven `max_distinctiviteit` als dat veld gezet is.

### R2. Definitie van categorietaal aanscherpen (Opus 5)

Vervang in het flagsfragment uit ronde 2 de omschrijving van `category_fallback` door:

```
category_fallback: meld dit alleen als de kern van de tekst categorietaal is:
wat de tekst over het merk beweert, kan elke concurrent in de branche ongewijzigd
zeggen. Bevat de tekst een concreet element van het mechanisme of de regel dat
niet tot de gangbare praktijk van de branche hoort, dan geen category_fallback,
ook als er daarnaast een gangbare formulering in staat. Een element dat in de
branche gangbaar is (zie de hygiënefactoren van de branche) telt niet als
onderscheidend.
```

Voeg in `branche_library` bij de koffiebranche onder de hygiënefactoren toe: "branddatum op de verpakking". Bestaat de koffiebranche nog niet als aparte branche, meld welke branche Brandpunt gebruikt.

### R3. Eén hoofdoordeel per post (Opus 5 voor de prompt, Composer 2.5 voor de rest)

Voeg aan het outputschema van de score het veld `hoofdoordeel` toe, met één van deze waarden:

| Waarde | Wanneer |
| --- | --- |
| `regelbreuk` | De tekst breekt de regel (definitie regelbreuk). |
| `tegenkracht` | De tekst neemt de redenering van de tegenkracht over. |
| `categorietaal` | De kern van de tekst is categorietaal. |
| `op_merk` | De tekst draagt het mechanisme of de regel uit. |
| `neutraal` | Service, werving, product of educatie zonder bijdrage aan het merk, en zonder risico. |

Zet dit fragment in de scoreprompt:

```
HOOFDOORDEEL: kies één oordeel voor de hele tekst, in deze volgorde van voorrang:
regelbreuk, tegenkracht, categorietaal, op_merk, neutraal. Kies het eerste dat
van toepassing is. Een tekst die het merk uitdraagt en daarnaast een gangbare
formulering bevat, is op_merk. Een tekst zonder bijdrage aan het merk en zonder
risico is neutraal, niet categorietaal.
```

Controleer na het parsen in PHP of het hoofdoordeel past bij de flags:

- `regelbreuk` vereist `constraint_breach`;
- `tegenkracht` vereist `foe_vervaging`;
- `categorietaal` vereist `category_fallback`;
- is `constraint_breach` aanwezig, dan moet het hoofdoordeel `regelbreuk` zijn.

Klopt het niet, zet dan het hoofdoordeel op de flag met de hoogste voorrang en log de afwijking in `hoofdoordeel_gecorrigeerd`. Dat veld is alleen voor debug en de matrix.

Neem `hoofdoordeel` en `hoofdoordeel_gecorrigeerd` op als kolommen in de matrix.

### R4. Testposts aanpassen (Composer 2.5)

**Oordeelslogica.** Voeg per testpost `verwacht_hoofdoordeel` toe. Een post is **fout** als het hoofdoordeel afwijkt. De regels voor verwachte en verboden flags uit ronde 2 blijven gelden. Ambigue posts blijven review.

| Post | `verwacht_hoofdoordeel` |
| --- | --- |
| Post 1, alle merken | `op_merk` |
| Post 2, alle merken | `categorietaal` |
| Post 3, alle merken | `regelbreuk` |
| Post 4, alle merken | `neutraal` (Stelwerk en Duinroos blijven review) |
| Brandpunt post 5 | `op_merk` |
| Stelwerk post 5 | `categorietaal` |
| Duinroos post 5 | `categorietaal` |
| Duinroos post 6 | `tegenkracht` |

**Brandpunt post 1 herschrijven.** Vervang de posttekst door:

```
Vandaag nieuwe oogst binnen uit Huila. Over twee weken in de winkel, zodra hij is uitgerust. Wat na zes weken niet verkocht is, gaat de winkel uit.
```

**De oude tekst wordt Brandpunt post 6:**

```json
{
  "post_tekst": "Vandaag nieuwe oogst binnen uit Huila. Over twee weken in de winkel, zodra hij is uitgerust na het branden. Je ziet de branddatum op de zak.",
  "platform": "instagram",
  "label": "Mechanisme dat markthygiëne is",
  "verwacht": "categorietaal of lage distinctiviteit: de branddatum op de zak is gangbaar bij specialty-branders; de zes-wekenregel staat er niet in",
  "verwacht_hoofdoordeel": "categorietaal",
  "verboden_flags": ["constraint_breach"],
  "verwacht_niveau": "laag",
  "ambigu": true
}
```

Werk de JSON bij in `briefing/hba-input/HBA_testmerken/` en `alle_drie_testmerken.json`, en importeer opnieuw.

### R5. Klantweergave voorbereiden, niet live (Composer 2.5)

Bouw op staging een variant van het artikelpaneel die alleen het hoofdoordeel toont, met één zin en, waar dat past, één verbetering. De losse flags blijven verborgen voor de klant.

| Hoofdoordeel | Klantzin |
| --- | --- |
| `regelbreuk` | Je overschrijdt hier een grens die je zelf hebt gezet. |
| `tegenkracht` | Hier redeneer je zoals je tegenstander. |
| `categorietaal` | Dit kan iedere concurrent zo zeggen. |
| `op_merk` | Dit is herkenbaar jij. |
| `neutraal` | Prima voor dit doel; het bouwt niet aan je merk, en dat hoeft ook niet. |

Zet hem niet live. Maak screenshots van de zestien testposts in deze weergave voor Rogier.

### R6. Stabiliteit over vijf runs (Composer 2.5)

Pas het matrixscript aan zodat het elke post vijf keer scoort (`--runs 5`, standaard 5).

Rapporteer per post:

- het hoofdoordeel in elke run;
- het aantal runs waarin het oordeel goed was (bijvoorbeeld 4/5);
- de spreiding van `brandContribution` (hoogste min laagste).

De samenvatting telt alleen posts die in alle vijf runs goed zijn als goed. Een post die tussen runs verschilt, is instabiel en wordt apart gemeld, met de citaten uit de afwijkende run. Rapporteer niet langer één run als dé stand.

### Testprotocol en klaar-criteria

Draai na R0 tot en met R6 de matrix met vijf runs op alle zeventien posts (5 + 6 + 6). Leg alles vast in `docs/hba-testmerken-resultaat.md`, met datum, model-id en promptversie.

**Klaar als, in alle vijf runs:**

| Post | Criterium |
| --- | --- |
| Post 3, alle merken | hoofdoordeel `regelbreuk` |
| Post 2, alle merken | hoofdoordeel `categorietaal`, nooit `constraint_breach` |
| Post 1, alle merken | hoofdoordeel `op_merk`, geen `category_fallback` |
| Brandpunt post 5 | hoofdoordeel `op_merk`, geen `category_fallback` |
| Duinroos post 6 | hoofdoordeel `tegenkracht`, nooit `constraint_breach` |
| Alle posts | spreiding `brandContribution` hoogstens 1 punt |

Rapporteer daarnaast, zonder criterium:

- op hoeveel posts `category_fallback` staat (was 13 van de 16);
- hoe vaak `hoofdoordeel_gecorrigeerd` voorkwam.

Haalt een criterium het niet, pas de prompt dan niet zelf verder aan, maar meld het aan Erik.


---

## Deel 2. Bevindingen uit de app (A1 tot en met A9, D1 tot en met D4)

### Context

De bevindingen komen uit een rondgang door de staging-omgeving op 10 oktober 2026, met de tenants Brandpunt Koffie (`54aeb554-562d-0f0d-9c48-381681a2eb84`) en Duinroos Thuiszorg (`a4b504fe-1b0c-b758-fcfd-575398b51eff`). Uitgangspunten zijn dezelfde als in deel 1.

### A1. Het merkrapport gebruikt het HBA-oordeel niet (Opus 5)

**Wat er gebeurt.** Het merkrapport over 9 september tot 9 oktober noemt "HBA-test: Breekt de regel" (Black Friday, oude zakken voor de halve prijs) het best presterende artikel. Het adviseert: "Geef [dit artikel] een tweede kans. Herplaats het artikel met een sterke distributiepush." Het rapport zet de post bij de algemene posts en oordeelt op de kop, niet op de inhoud. De HBA-scorer geeft dezelfde post `constraint_breach`.

Aanbeveling 4 stuurt bovendien naar de tegenkracht van het merk: schrijven over "de concentratie die een slechte koffie wegneemt". Dat is koffie als brandstof.

Het rapport noemt de toon "volledig B2B" (zie A7) en gebruikt "de Maker" als term.

**Wat te doen.**

1. Zoek welke prompt en welke data het merkrapport (het blok "Inzicht") gebruikt. Leg vast of het HBA-oordeel per post (flags, merkbijdrage, en straks het hoofdoordeel) erin zit.
2. Geef het rapport per post het HBA-hoofdoordeel mee uit R3, en de geloofwaardigheidstest en tegenkracht van het merk.
3. Voeg aan de rapportprompt toe:

```
Een post met hoofdoordeel regelbreuk wordt nooit aanbevolen om opnieuw te
plaatsen, te promoten of als voorbeeld te gebruiken, ongeacht het bereik. Noem
zo'n post in het rapport als aandachtspunt: wat de post deed en welke grens van
het merk hij raakte. Een aanbeveling mag nooit de redenering van de tegenkracht
van het merk volgen. Controleer elke aanbeveling tegen de tegenkracht voordat je
hem opneemt.
```

4. "Op strategie" in het rapport moet hetzelfde betekenen als het HBA-oordeel: een post telt als op strategie als het hoofdoordeel `op_merk` is. Een eigen classificatie die daarvan afwijkt, vervalt.

**Klaar als:** een nieuw merkrapport voor Brandpunt de Black Friday-post als aandachtspunt noemt en niet aanbeveelt, en geen aanbeveling bevat die koffie als brandstof of energie neerzet.

### A2. De merkarchitectuur komt deels verkeerd binnen (Composer 2.5)

**Wat er gebeurt.** Op de pagina Merkarchitectuur van Brandpunt staat:

| Veld in de app | Wat erin staat | Wat erin hoort |
| --- | --- | --- |
| Woorden die passen | De bewijsvelden (3 van de 4) | Woorden die passen; de bewijsvelden horen bij "Mechanisme & bewijs" |
| Woorden die niet passen | 3 van de 5 punten uit de verboden logica | Alle punten. Juist "aanbiedingen en stapelkorting op oudere voorraad" ontbreekt. |
| Wat jullie bewust niet doen | Leeg | De verboden logica, of wat de wizard daar bedoelt |
| Kernformuleringen 1 tot 3 | Leeg | Het mechanisme, in drie tot vijf zinnen |

Bij Duinroos precies hetzelfde patroon: 3 van de 4 bewijsvelden onder "Woorden die passen", en 3 van de 4 punten verboden logica onder "Woorden die niet passen". Ook daar valt juist het punt weg dat een handeling tegen de regel beschrijft: "wachtlijsten weggewerkt door extra inzet van invalkrachten". Het lijkt een vaste limiet van drie, en het belangrijkste punt staat in beide merken achteraan in de lijst.

**Wat te doen.**

1. Controleer in de provisioning van de testmerken hoe `bewijsvelden`, `verboden_logica` en `mechanisme` op de velden van de app worden gezet, en herstel de koppeling.
2. Zoek of er een limiet van drie items is op deze lijsten, in de provisioning, de database of de prompt. Zo ja, hef die op, of meld waarom hij er is.
3. Controleer of de scoreprompt zijn verboden logica uit dezelfde bron leest. Zo ja, dan scoorde de app in ronde 2 en 3 met een onvolledige lijst. Meld dat in het verslag.
4. Provision Brandpunt, Stelwerk en Duinroos opnieuw en controleer de pagina Merkarchitectuur van alle drie.

### A3. "Snel artikel" verliest de bronnen (Composer 2.5)

**Wat er gebeurt.** Twee keer getest met thema "Black Friday-actie" en drie onderzoekslinks. De eerste keer was dat een folder en twee onderzoeksberichten, de tweede keer drie gewone Nederlandse nieuwsartikelen.

- `POST /api/fastlane` antwoordt `researchUrlCount: 3`, `ce02Triggered: true`, `webhookTriggered: false`.
- Bij de eerste poging verschenen de drie bronnen even, met "Deze bron kiezen / vergeten" en "Indienen", terwijl bovenin "Meer bronnen worden opgehaald…" stond. Daarna waren ze weg.
- `GET /api/research/<slot>` geeft `slotStatus: research_done` met `items: []`.
- Daardoor blijft "Positionering beoordelen…" hangen, en start het schrijven nooit.

Vermoeden: CE-02 overschrijft de opgegeven bronnen met een lege lijst als het zelf niets vindt, of de fastlane-items worden niet als research-items opgeslagen.

**Wat te doen.**

1. Zoek in de n8n-executions van CE-02 voor de slots `1f856a88-fc18-47a2-81be-3d335358066c` en `d733223a-7964-4324-9883-dcd4b1809006` wat er met de drie URL's gebeurde.
2. Zorg dat bronnen die de gebruiker zelf opgeeft altijd blijven staan, ook als CE-02 niets extra's vindt.
3. Een slot mag niet op `research_done` komen met nul bronnen. Geef dan een melding in klanttaal, bijvoorbeeld: "We konden deze pagina's niet lezen. Probeer andere links of plak de tekst bij Extra gedachten."
4. Zoek uit waarom `webhookTriggered` false is.

**Klaar als:** de twee slots na een hertest drie bronnen hebben, het positioneringsadvies verschijnt en CE-03 een artikel schrijft. Voer daarna de verleidingstest uit ronde 2 uit op dat artikel.

### A4. Archieftitels sturen de themavoorstellen (Opus 5)

**Wat er gebeurt.** De themavoorstellen voor week 41 zijn inhoudelijk sterk, maar de toelichting zegt:

- "Gebaseerd op ... HBA-patroon 'Op merk'";
- "bewezen HBA-patroon 'Verdedigt de regel'";
- "HBA-patroon 'Breekt de regel'", bij een thema dat de regel juist niet breekt.

De generator gebruikt de titels van de testposts in het archief ("HBA-test: Op merk" enzovoort) als bewezen patronen.

Bij thema 2 staat ook: "De regel hier: smaak verandert met temperatuur." Dat is niet de regel van het merk.

**Wat te doen.**

1. Zoek waar de themagenerator archiefposts of "HBA-patronen" meekrijgt. Geef hem het HBA-oordeel per post mee (straks het hoofdoordeel), niet de titel.
2. Sluit testposts (`origin=baseline` met label "HBA-test") uit van de input voor themavoorstellen.
3. Voeg aan de themaprompt toe:

```
Het woord "regel" gebruik je alleen voor de regel van het merk (de
geloofwaardigheidstest). Gebruik het niet voor feiten, wetmatigheden of
vakkennis. Noem in de toelichting geen interne labels, patroonnamen of rollen;
leg in gewone taal uit waarom het thema het merk versterkt.
```

### A5. De rollen in de themavoorstellen schuiven één plek op (Composer 2.5)

**Wat er gebeurt.**

| Kop van het thema | Rol in de toelichting |
| --- | --- |
| Autoriteit (thema 1) | product-of-dienstrol |
| Inzicht (thema 2) | autoriteitsrol |
| Product of dienst (thema 3) | inzichtrol |

**Wat te doen.** Zoek waar de kop en de toelichting hun rol vandaan halen, en herstel de koppeling. Controleer het met een nieuwe set voorstellen.

### A6. Vaktermen en interne namen voor de klant (Composer 2.5)

De verbodslijst uit de kalibratiemodule moet ook voor de interface gelden, niet alleen voor de output van het model.

| Scherm | Wat de klant nu ziet |
| --- | --- |
| Overzicht, Merkarchitectuur | "HBA-scoring", "merkarchetype Maker" |
| Snel artikel, Onderzoek | "Positioneringsadvies op basis van merkarchetype Maker" |
| Themavoorstellen | "fasecyclus", "Themarationale", "HBA-patroon", "De Maker legt uit" |
| Merkelementen | "BrandArchitecture", "Theme", "Article → Auteur", "CE-03 via write_directive" |
| Vindbaarheid, Planning | "Content Engine", "Report", "Overview", "SerpAPI" |
| Snel artikel | "Theme of the article", "Upload image", "Research URL", "Extra thoughts", "Continue to research" |
| Doelen | "+ Add objective" |
| Archief | De interne notitie "Verwacht: flag constraint_breach" |

**Wat te doen.**

1. Maak een lijst van alle teksten in de interface met een methodische term, een interne naam, een technische veldnaam of Engels. Leg hem aan Rogier voor met een voorstel per tekst.
2. Vervang niets zonder akkoord van Rogier. De klanttaal is methodiek.
3. Verberg notities met "Verwacht:" en testlabels voor accounts met de rol `klant`.

### A7. Schrijfstijlen staan standaard op B2B (Composer 2.5)

Bij Merkelementen staan voor Brandpunt vijf algemene schrijfstijlen, met "b to b" als eerste. Brandpunt is een koffiebranderij met een eigen winkel. Het rapport noemt de toon daardoor "volledig B2B".

**Wat te doen.** Leid de standaardschrijfstijl bij provisioning af uit het type merk (`mkb, consument` tegenover `B2B`), of laat de klant er één kiezen in de wizard. Meld hoe het rapport aan "B2B" kwam: via de schrijfstijl, via het platform LinkedIn (zie S4), of via beide.

### A8. Twee sets contentrollen (Composer 2.5)

Planning werkt met "autoriteit, inzicht, product-of-dienst". De HBA-scoring gebruikt contentrollen als positionering, bewijs, activatie en service. De klant ziet zo twee systemen.

**Wat te doen.** Leg beide sets naast elkaar en meld hoe ze zich verhouden. Niet samenvoegen zonder akkoord van Rogier.

### A9. Geen instelling voor hoe streng de app waarschuwt (alleen melden)

Bij Instellingen staan Mijn account, Koppelingen en Help. De kit kent een klantinstelling voor het alertniveau (ontspannen, alert, zeer alert). Meld of die is gebouwd, en zo ja waar.

---

### Duinroos Thuiszorg

Bij Duinroos (`a4b504fe-1b0c-b758-fcfd-575398b51eff`) vallen merk-archetype en branche-archetype samen (Verzorger). Dat maakt het de test voor de zwaardere bewijslast uit T4 van ronde 2. Wat bij Brandpunt al gevonden is, geldt hier ook: A2 (invoer), A4 (testlabels als "HBA-les"), A5 (rollen schuiven op), A6 (taal) en A7 (B2B). Hieronder alleen wat extra is.

### D1. Het rapport van Duinroos raadt categorietaal aan als "het merk" (Opus 5, samen met A1)

**Wat er gebeurt.** Het rapport "Merkrapport 2026-09-10 – 2026-10-10":

- noemt "HBA-test: Categorietaal" het best presterende artikel;
- telt 1 van de 6 posts als op strategie, maar zegt niet welke;
- noemt de regelbreuk (de wachtlijst weggewerkt met invalkrachten) nergens;
- geeft als aanbeveling 2: "een kort cliëntmoment, een dag-in-het-leven van een verzorgende ... gewoon een mens die voor een ander mens zorgt. Dat is het merk."

Dat laatste is precies wat de zwaardere bewijslast moet tegenhouden. Bij een Verzorger in de zorg is warm en menselijk zonder mechanisme of regel categorietaal. Het rapport zegt het omgekeerde.

Daarnaast: "100% B2B-toon" (zie A7), en "het archetype de Verzorger" als term in de tekst.

**Wat te doen.** Neem dit mee in A1. Geef de rapportprompt ook het fragment uit T4 mee als het merk-archetype gelijk is aan het markt-archetype. Een aanbeveling voor content moet dan een concreet element van het mechanisme of de regel noemen, zoals de vier zorgverleners per maand, teams van hoogstens zes, of de doorverwijzing.

**Klaar als:** een nieuw rapport voor Duinroos de wachtlijstpost als aandachtspunt noemt, en geen aanbeveling geeft die alleen warm en menselijk is zonder de regel of het mechanisme.

### D2. De zwaardere bewijslast werkt nog niet in de themavoorstellen (Opus 5)

**Wat er gebeurt.** Alle drie de themavoorstellen voor week 41 krijgen "versterkt":

| Thema | Probleem |
| --- | --- |
| 1. "Vaste verzorgende, vertrouwd gezicht" | Het oordeel zegt dat dit "de vier-zorgverlenersregel tastbaar" maakt. De toelichting belooft echter "dat dezelfde persoon terugkomt. Niet een roulerend team." Dat is een andere belofte: Duinroos werkt juist met kleine teams van hoogstens zes en hoogstens vier gezichten per maand. "Vaste verzorgende" is bovendien gangbare taal in de thuiszorg. |
| 2. "Wat een goede thuiszorgplanning écht inhoudt" | Raakt het mechanisme (planning samen met de cliënt) en de tegenkracht (het rooster). Terecht versterkt. |
| 3. "Waarom 'iemand komt langs' niet hetzelfde is als thuis de baas zijn" | Ondersteunen tegenover overnemen is een algemene zorggedachte zonder regel of mechanisme. Bij hetzelfde archetype hoort dit hoogstens neutraal. |

Het advies erboven is wel goed: "De agenda laat nog niet zien wat Duinroos doet als de eigen regel botst met de vraag. Een thema over wat er gebeurt als een nieuwe cliënt wordt doorverwezen zou laten zien dat de grens echt bestaat."

**Wat te doen.**

1. Pas het fragment uit T4 ook toe op het oordeel per themavoorstel, niet alleen op de score van posts.
2. Voeg aan de themaprompt toe:

```
Een thema mag de regel van het merk niet anders weergeven dan hij is. Beschrijf
de regel zoals hij in de geloofwaardigheidstest staat. Een belofte die op de
regel lijkt maar iets anders zegt, is geen uitwerking van de regel.
```

3. Controleer met een nieuwe set voorstellen voor Duinroos. Verwacht: thema's die alleen warm en menselijk zijn, krijgen niet meer "versterkt".

### D3. Het advies vooraf werkt goed (geen actie)

Bij beide merken ziet het "Advies voor je agenda" wat ontbreekt: bij Brandpunt de zes-wekenregel, bij Duinroos de doorverwijzing als de regel botst met de vraag. Dit is het sterkste onderdeel van de HBA-laag dat we in de app zagen. Gebruik deze twee adviezen als referentie in de ijkset (soort `themakeuze`).

### D4. Het overzicht toont een vreemd ritme (alleen melden)

Op het Overzicht staat bij Ritme "5/1 per week, op schema" voor beide merken, terwijl Planning 1 post per week aangeeft. Meld waar "5/1" vandaan komt en wat het betekent voor de klant.


---

## Afronden

- Draai `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap.
- Kort verslag aan Erik met:
    - de vier punten uit R0 en de schaal van distinctiviteit;
    - de stabiliteitstabel over vijf runs, met de klaar-criteria afgevinkt;
    - het aantal posts met `category_fallback`, vóór en na R2;
    - per A- en D-punt de oorzaak en wat is gewijzigd;
    - de nieuwe merkrapporten voor Brandpunt en Duinroos (A1, D1);
    - screenshots van de pagina Merkarchitectuur van alle drie de testmerken (A2);
    - de hertest van de twee Black Friday-slots, inclusief de verleidingstest (A3);
    - een nieuwe set themavoorstellen voor Duinroos (D2);
    - de screenshots van de klantweergave (R5) en de lijst met teksten uit A6, met voorstellen.
