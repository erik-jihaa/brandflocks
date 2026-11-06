# Besluiten Rogier, ronde 6

19 oktober 2026. Antwoord op `docs/terugkoppeling-rogier-ronde-6.md` (commit `c518a33`) en het pakket van 19 oktober.

Goed werk. Het hoofdoordeel komt nu in 100 procent van de gevallen uit de flags, de spreiding in merkbijdrage is overal hoogstens 1, en CE-04 geeft bij alle drie de merken de regel letterlijk terug. Hieronder de vier besluiten, plus wat mij opviel in de ruwe data.

## Eerst: het zijn geen zeven fouten

In `04-stabiliteit-v1.11-5runs.json` staan de zeven afwijkende posts zo:

| Post | Beoordeling in de run | Wat het is |
|---|---|---|
| Brandpunt, Duinroos, Stelwerk: neutraal / grensgeval | fout (3x) | Het echte probleem, zie besluit 1 |
| Brandpunt: mechanisme dat markthygiëne is | review | Verwachting was ambigu; de app is consistent |
| Duinroos: kernbewering zonder bewijs (post 7) | review | Verwachting stond op null, terwijl de briefing tegenkracht vraagt |
| Stelwerk: samenwerking zonder bewijs | review | Verwachting was ambigu; de app is consistent |
| Stelwerk: branchetaal uit de verboden logica (post 5) | instabiel, 1/5 | Staat als eigen klaar-criterium in de briefing |

Drie van de zeven zijn dus review: daar moest ik nog een verwachting vastleggen. Dat doe ik hieronder in besluit 5.

## Besluit 1 (§3): neutraal moet bereikbaar zijn

Jullie analyse klopt. Een post zonder merkbewering kan nooit `kern_categorietaal = nee` krijgen, dus valt hij altijd op trede 3. Dat gaat in tegen mijn eigen uitgangspunt: een tekst op basisniveau van de branche is neutraal, niet zwak. Tips om je apparaat te ontkalken, een vacature en een webinaraankondiging beweren niets over het merk. Ze dragen niet bij, maar breken ook niets af.

**Besluit: richting 2, in de code.**

1. Het model levert een extra veld: `merkbewering: ja/nee`. Ja als de tekst iets beweert over wat het merk is, doet of belooft, inclusief beloftes over product of dienst. Nee bij werk- en producttaal, service, uitleg, tips, vacatures en aankondigingen die niets over het merk beweren.
2. De volgorde in PHP wordt:
   1. `regelbreuk`: `constraint_breach` die de controlecall bevestigt;
   2. `tegenkracht`: `foe_vervaging` met citaat uit kop of openingszin;
   3. **nieuw:** `merkbewering = nee` gaat direct naar trede 5;
   4. `categorietaal`: `kern_categorietaal = ja`;
   5. `op_merk` of `neutraal` op merkbijdrage en contentrol, zoals nu.
3. `kern_categorietaal` beoordeelt het model alleen als `merkbewering = ja`.
4. Categorietaal gaat over beloftes over het merk, niet over service. Dat sluit aan op de branchebibliotheek, waar elke hygiënefactor nu het veld `soort` heeft: `identiteit` of `service`.

Regelbreuk en tegenkracht blijven vóór de nieuwe trede staan. Ook een tipspost kan de regel breken.

**Twee verzoeken bij de bouw:**
- **Logging.** In de ruwe JSON staan `kern_categorietaal` en `hoofdoordeel_volgt_uit_flags` bij alle 95 scores op `null`. Ik kan dus niet nagaan welk veld trede 3 heeft bepaald. Log `merkbewering` en `kern_categorietaal` per score.
- **Extra voorwaarde in trede 2.** In `hba-v1.11-stap1-hoofdoordeel-code.md` heeft trede 2 een voorwaarde die niet in §3 staat: `kern_categorietaal = nee`. In §3 wint een tegenkracht in kop of opening van categorietaal; alleen een tegenkracht in een bijzin verliest. Leg uit waarom die voorwaarde erin staat. Is er geen goede reden, haal hem dan weg. Meet het effect op Stelwerk post 5 en Duinroos post 7, want die twee raakt het.

## Besluit 2 (§2): `geen_bron` blokkeert niet hard

De klant mag een gemarkeerde zin bevestigen door zelf de bron aan te wijzen: een bewijsveld uit de merkarchitectuur, een URL of een document. Leg elke bevestiging vast, met de bron, zodat zichtbaar blijft hoe vaak de detector ernaast zat.

Wijst de klant geen bron aan, dan blijft de blokkade staan, tenzij hij de zin herschrijft. Een standpunt of mening is geen merkbewering en krijgt geen `geen_bron`. Dat hebben jullie in stap 3a al goed aangescherpt.

## Besluit 3 (§4): akkoord op A6, met zes restpunten

Akkoord. De klanttaal is sterk verbeterd: geen archetypelabel, geen HBA-versie in nieuwe rapporttitels, Nederlandse knoppen. De restpunten zijn weergave, vallen dus niet onder de bevriezing, en kunnen tijdens het wachten worden opgelost:

1. **Merkelementen:** de doelgroeplabels staan er als interne codes: "b to c", "personal", "b to b". Maak er "Consumenten", "Persoonlijk (ik-vorm)" en "Zakelijk" van.
2. **Terugkoppeling:** onder de CE-04-opmerking staat nog "okay | Verwijder". Wordt "Akkoord | Verwijderen". De kolom met de opmerking is afgekapt.
3. **Agenda:** de datums staan in het Engels ("18 October 2026", "5 October - 11 October"). Graag nl-NL.
4. **Overzicht:** "Ritme 0/1 per w…" en "Achter op ritm…" vallen weg aan de rechterkant.
5. **Rapport:** het oude rapport in de lijst heet nog "(HBA v1.7)". Hernoem het, of verberg de versie bij weergave.
6. **Artikel week 41:** de app zet `#versgebrand` onder een Brandpunt-post, terwijl Brandpunt "vers gebrand als hoofdclaim zonder de zes-wekenregel" bij "nooit over jezelf lezen" heeft staan. Hashtags vallen nu buiten de controle. Neem ze mee in de verbodenlijst van de generator.

## Besluit 4: de pilot start na één nieuwe 5 × 19

Bouw besluit 1, leg de verwachtingen uit besluit 5 vast, en draai daarna één 5 × 19. Dat is poging 2 van de 2 uit de briefing.

- **Haalt de run minstens 16 posts 5 van 5 goed,** en wijken alleen Stelwerk post 5 en Duinroos post 7 nog af: dan start de pilot. Die twee posts staan dan als bekende beperking in de pilotnotities en volgen we in de pilot.
- **Haalt de run de 16 niet:** stop, en stuur de ruwe output, zoals de briefing zegt.

Mijn verwachting: de drie neutrale posts gaan goed, en de twee reviewposts uit besluit 5 tellen mee. Dan kom je op 17.

## Besluit 5: verwachtingen voor de drie reviewposts

| Post | Verwachting | Waarom |
|---|---|---|
| Brandpunt: mechanisme dat markthygiëne is | `categorietaal` (niet meer ambigu) | "Nieuwe oogst binnen, branddatum op de zak" is wat een groot deel van de branche zegt. De zes-wekenregel, die Brandpunt onderscheidt, staat er niet in. De app geeft 5/5 categorietaal en heeft gelijk. |
| Stelwerk: samenwerking zonder bewijs | `op_merk`, met `proof_gap` als advies | De post draagt de regel (geen resellercontract), maar "jaren ervaring" is niet onderbouwd. Een bewijsgat is een advies, geen hoofdoordeel. De app geeft 5/5 op_merk met proof_gap en heeft gelijk. |
| Duinroos: kernbewering zonder bewijs (post 7) | `tegenkracht`, merkbijdrage hoogstens 5 | Zoals in de klaar-criteria van de briefing. Gebruik dit als verwachting in de testmerken; nu staat hij op null. De app geeft 4/5 tegenkracht en 1/5 op_merk, dus deze post is instabiel en geen review. |

Stelwerk post 5 (branchetaal, 1 van 5) is ook een expliciet klaar-criterium. Hij verandert de uitslag van 16 misschien niet, maar ik wil hem wel stabiel hebben voordat de pilot breed gaat.

## Tot slot: Vindbaarheid en de GEO-meter

Het scherm Vindbaarheid vraagt de klant om 3 tot 5 vragen aan AI, en Rapport checkt maandelijks of het merk in de antwoorden verschijnt. Dat lijkt sterk op de GEO Identity Gap meter die ik nu als losse dienst opzet. Laten we die twee na de pilot op elkaar afstemmen:
- dezelfde trechter: genoemd, bovenaan, met eigen verhaal;
- meten in een nieuw, uitgelogd gesprek;
- meerdere assistenten.

Het werkdocument stuur ik dan mee. Voor nu geen actie.
