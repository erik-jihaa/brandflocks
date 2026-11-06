# Cursor-briefing — ronde 6 afronden: diagnose, restpunten, stopbericht

> **Deels vervangen (20 oktober 2026).** De werkopdracht loopt verder in `briefing/cursor-ronde-7-v113-en-a6-ronde-2.md`. Alleen stap 3b (productiebevindingen) blijft hier gelden.

**Eigenaar:** Erik. **Datum:** 19 oktober 2026.
**Vervangt:** `briefing/cursor-pakket-ronde-6-correcties.md` en `briefing/cursor-ronde-6-besluiten-rogier-uitvoeren.md`. Die zijn afgehandeld of achterhaald; werk niet verder op basis van die twee.
**Model:** Composer 2.5.

## Waar we staan

De 5 × 19 op v1.12 gaf 13 van de 19 stabiel goed. Dat was poging 2 van de 2. Rogiers besluit 4 is duidelijk: **stoppen, en de ruwe output sturen.** Er komt dus geen derde run en er wordt niet aan de prompt geschoven. Deze briefing maakt de ronde af en bereidt het stopbericht voor.

Wat wél gelukt is, en in het bericht hoort: besluit 1 werkt. Brandpunt neutraal en Duinroos neutraal zijn stabiel, Stelwerk neutraal staat op 4 van 5, en Stelwerk post 5 — een eigen klaar-criterium van Rogier — staat op 5 van 5.

---

## Stap 1 — de tegenkracht-diagnose (meten op bestaande data, geen nieuwe run)

Vijf van de zes niet-stabiele posts falen op dezelfde manier: het oordeel wordt `tegenkracht` waar iets anders verwacht werd. Duinroos archetype 5 van 5, Duinroos categorietaal 5 van 5, Stelwerk branchetaal 5 van 5, en Brandpunt categorietaal 2 van 5.

Vermoeden: dit komt door stap 3, het verwijderen van `&& !$kernCat` uit trede 2. Die voorwaarde onderdrukte `tegenkracht`; zonder hem wint een `foe_vervaging` op de kop nu overal.

**Dat is te toetsen zonder opnieuw te scoren.** De flags, citaten en `kern_categorietaal` van alle 95 scores staan in `docs/hba-testmerken-stability-v1.12-5runs.json`. Schrijf een losse replay die per score het hoofdoordeel opnieuw afleidt met `ce_hba_resolve_hoofdoordeel()`, één keer zoals de code nu is en één keer met de oude voorwaarde terug in trede 2. Geen modelcalls, geen staging.

Lever `docs/hba-v1.12-tegenkracht-diagnose.md` met:

- per post en per run: het oordeel nu, het oordeel met de voorwaarde terug, en de verwachting;
- hoeveel posts stabiel goed worden in beide varianten — dus: wat was de uitslag geweest met de voorwaarde erin;
- per geval van onterechte `tegenkracht` het citaat waarop `foe_vervaging` viel, met de kop of openingszin eromheen. Rogier moet kunnen zien óf de foe daar werkelijk staat;
- of het citaat in de kop of de openingszin staat, of verderop. Bij "verderop" is niet de voorwaarde het probleem maar de bepaling van `foe_on_kern`; dat is een andere conclusie en dat onderscheid moet in de tabel staan.

**Niets aanpassen op grond van de uitkomst.** De volgorde is Rogiers domein en hij heeft het verwijderen zelf gevraagd. Wij leveren wat het gedaan heeft.

## Stap 2 — de loggingfix alsnog, en terugwerkend toepassen

De fix in `ce_hba_testmerken_matrix_payload` en het stability-script landde ná de run, dus juist deze meting mist `merkbewering` en `kern_categorietaal` in de matrix-snapshot.

1. Controleer dat de fix er nu in zit en dat een nieuwe matrix-GET de vier velden vult.
2. Haal voor de v1.12-run de ontbrekende velden alsnog uit de opgeslagen ruwe scores en vul ze aan in de export, zodat het bestand dat naar Rogier gaat compleet is. Lukt dat niet omdat de raw ze niet bevat, meld dat dan expliciet in plaats van het stil te laten.
3. Trek daarbij de neutrale posts apart: wat gaf `merkbewering` daar, en kwam er op trede 5 `neutraal` of `op_merk` uit? Dat is de controle die in de vorige briefing stond en nog niet beantwoord is.

## Stap 3 — besluit 2 bouwen: de `geen_bron`-override

Dit is overgeslagen en het is een besluit van Rogier, geen optie.

- Een gemarkeerde zin blokkeert indienen en publiceren niet langer hard. De klant kan de zin bevestigen door zelf de bron aan te wijzen: een bewijsveld uit de merkarchitectuur, een URL of een document.
- Leg elke bevestiging vast, met de aangewezen bron, de zin en het moment, zodat meetbaar blijft hoe vaak de detector ernaast zat.
- Wijst de klant geen bron aan, dan blijft de blokkade staan tenzij hij de zin herschrijft.
- Een standpunt of mening krijgt geen `geen_bron`; dat is in stap 3a al aangescherpt en hoeft niet opnieuw.

Dit raakt de redactieflow, niet de scoring, dus het maakt de v1.12-meting niet ongeldig.

**Klaar als:** een zin met `geen_bron` te bevestigen is met een bron, publiceren daarna doorgaat, en de bevestiging terug te vinden is in de opslag.

## Stap 3b — twee bevindingen op productie (20 oktober)

Gevonden op `https://ce.igisnext.com/article/44190153-c336-4666-9324-5c173a76f786` (tenant iGiSNext, week 44). Beide raken de klant rechtstreeks.

### 3b-1. De merkbeoordeling ontbreekt op productie

Het paneel Merkarchitectuur toont "De merkbeoordeling voor deze post ontbreekt nog", met twee keer "Beoordeling volgt". Er is dus geen hoofdoordeel, geen merkbijdrage en geen flags op dit artikel. De zelfcontrole werkt er wel.

Dit is hetzelfde beeld als de V0-run op staging, waar `/api/hba/score` bij de Duinroos-fastlane een 404 gaf op een net aangemaakt artikel. Dat hebben we toen als timing weggezet. Hier staat het op productie en de beoordeling is er nog steeds niet, dus timing is het niet.

1. Stel eerst vast of dit artikel via Snel artikel (`/fastlane`) is aangemaakt. Zo ja, dan is het exact hetzelfde pad als de Duinroos-fastlane en hebben we een reproduceerbare fout in plaats van een incident.
2. Zoek uit waar de score blijft: is hij nooit aangevraagd, afgebroken met een fout, of wel opgeslagen maar niet opgehaald door het paneel. Kijk in de logs van dit artikel-id.
3. Tel hoeveel artikelen op productie in dezelfde staat staan — met zelfcontrole maar zonder merkbeoordeling. Dat getal bepaalt of dit een los geval is of een stille achterstand.
4. Repareer het pas als duidelijk is wat de oorzaak is, en meld de oorzaak eerst aan Erik.

Dit raakt de scoring van echte klantcontent, niet de testmerken, dus het maakt de v1.12-meting niet ongeldig.

### 3b-2. Methodiektaal lekt via `self_review` naar de klant

Bij Aandachtspunten, onder Gemist tegengeluid, staat letterlijk in de klanttekst:

> Het research-item met stance=spreekt_tegen (reliability=low) stelt dat AI een versterkende kracht is…

Ruwe veldnamen en -waarden in een tekst die de klant leest. Dat is in strijd met §5.3 en §6, en het staat op productie.

De verbodslijst uit ronde 5 kijkt kennelijk alleen naar de suggesties, niet naar de teksten die `self_review` oplevert. Breng alle klantzichtbare `self_review`-velden onder dezelfde controle: zwakste claim, ongefundeerde aanname, gemist tegengeluid, en de toelichting bij bron per merkbewering. Filteren en opnieuw vragen, in hetzelfde patroon als bij de suggesties; blijft de term na een tweede poging staan, laat het punt dan weg en log de treffer.

Neem in de verbodslijst ook de veldnamen en enumwaarden zelf op: `stance`, `reliability`, `spreekt_tegen`, `low`, en de overige waarden uit het researchschema. Een aandachtspunt dat naar research verwijst doet dat in gewone taal: "een bron die het tegenspreekt, met beperkte betrouwbaarheid".

**Klaar als:** geen enkel aandachtspunt op dit artikel nog een veldnaam of enumwaarde bevat, en een grep over de klantzichtbare `self_review`-teksten van de laatste twintig artikelen op productie geen treffers meer geeft.

### Wat hier goed ging — meenemen in het stopbericht

De zelfcontrole doet op dit artikel precies wat ze moet doen: bron per merkbewering met twee zinnen op Bewijsveld, plus zwakste claim, ongefundeerde aanname en gemist tegengeluid, elk met de drie knoppen. De positioneringshint wijst de slotzin aan als sterkste plek, geeft een herschrijving, en zet de invulvelden tussen haken oranje met de regel dat publiceren geblokkeerd blijft zolang ze er staan. Dat is de goede afhandeling van placeholders — als expliciete invulvraag, niet als tekst die meeloopt. Rogier mag dat zien.

## Stap 4 — de A6-restpunten afmaken en vastleggen

Stap 6 van de vorige briefing is gebouwd. Maak het af:

- controleer dat alle zes punten van Rogier erin zitten, inclusief de hashtagcontrole in zowel de scoring als de CE-03-prompt;
- maak nieuwe screenshots van alleen de gewijzigde schermen (Merkelementen, Terugkoppeling, Agenda, Overzicht, Rapport) en zet ze in een nieuwe map met datum;
- meld per punt klaar of afwijkend.

## Stap 5 — het stopbericht samenstellen

Eén map, `docs/rogier-stopbericht-ronde-6-<datum>/`, met:

1. `00-LEESMIJ.md` — wat er gebeurd is in vijf regels, en wat we van hem nodig hebben;
2. de eindmeting `hba-v1.12-stap5-5x19-eindmeting.md`;
3. de ruwe output `hba-testmerken-stability-v1.12-5runs.json`, aangevuld volgens stap 2;
4. de tegenkracht-diagnose uit stap 1;
5. de nieuwe A6-screenshots.

Ik schrijf de LEESMIJ en de begeleidende tekst zelf; lever jij de artefacten en meld wanneer ze compleet zijn.

**Twee dingen die mee moeten en die Rogier nog niet beantwoord heeft:** de prioriteit van de valkuilassen voor go-live (signaal 15, oordeel 8, meetlat 3) en ons voorstel voor de flagtelling met de drempels van de Implementation Kit. Zet die als losse bijlage klaar; ze zijn in de vorige ronde ondergesneeuwd.

---

## Wat er niet gebeurt

- **Geen derde 5 × 19.** Niet "even kijken of het nu wel lukt", ook niet op één merk.
- **Geen wijziging aan de volgorde, de treden of de flagdefinities** zolang Rogier niet heeft gereageerd op de diagnose. Ook niet het terugzetten van `&& !$kernCat`, hoe aannemelijk de uitkomst van stap 1 ook is.
- **Geen matrixwijziging** en geen bijstelling van verwachtingen.
- **Geen pilotstart.** De drempel is niet gehaald.

## Afronden

- `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap, met het stapnummer in de message.
- Geen staging zonder een expliciete "ja staging" van mij. Niets op productie.
- Verslag aan Erik per stap: klaar / gewijzigd / waar je een keuze moest maken.
