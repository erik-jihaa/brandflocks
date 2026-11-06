# Begeleidende mail bij het pakket ronde 6

*Concept, 19 oktober 2026. Erik verstuurt.*

---

Rogier,

Ronde 6 is af. Alle negen stappen zijn gebouwd en gemeten; de pilotversie is v1.11, met git-anker `b9c8e03`. Het pakket zit erbij. Hieronder staat wat we nodig hebben, en waarom het er zes dingen zijn in plaats van een lijst.

**Waar het op vastloopt.** De 5 × 19 geeft 12 van de 19 posts stabiel goed, tegen 16 op v1.10.6. Zeven posts staan op 0 van 5 — steeds hetzelfde oordeel, geen spreiding tussen de runs. Dat is geen instabiliteit maar een verschil van inzicht, en het komt uit de volgorde zelf.

De code doet wat §3 voorschrijft: trede 3 kijkt naar `kern_categorietaal`, en trede 4 wordt alleen bereikt als die op nee staat. `Nee` vereist volgens de definitie dat er ergens een concreet, niet-gangbaar element van het mechanisme of de regel in de tekst staat. Een werkelijk neutrale post heeft dat per definitie niet, en valt dus op trede 3. **Zoals de volgorde nu staat is `neutraal` vrijwel onbereikbaar.** Dat verklaart waarom juist de neutrale en grensgevallen bij alle drie de merken op 0 van 5 staan.

Een tweede run doen we daarom niet. Die reproduceert hetzelfde.

## Wat we van je nodig hebben

**1. De richting bij §3.** Onze voorkeur: `kern_categorietaal` beperken tot teksten die iets over het merk beweren. Een post die niets claimt, claimt ook niets gangbaars, en valt door naar trede 4. Het alternatief — een post zonder merkbewering vóór trede 3 afvangen als neutraal — werkt ook, maar maakt de volgorde langer in plaats van scherper. Regels per contentvorm zouden we niet doen.

**2. De drie testposts** (ontkalken, vacature, AI-webinar). Wij denken dat de matrix klopt en de scorer fout zit: een vacature en een webinar-aankondiging beweren niets over het merk, ze zijn activatie en employer culture. Deel je dat, dan laten we de verwachtingen staan.

**3. `geen_bron` blokkeren of niet.** Stap D blokkeert nu indienen en publiceren. Deze ronde liet zien dat die detector zich kan vergissen: hij labelde een vervolgzin en een standpunt als onderbouwde bewering die ontbrak. Ons voorstel is een override — de klant wijst de bron aan of bevestigt dat het een standpunt is, en wij leggen dat vast zodat we kunnen meten hoe vaak het misging. Blijft het hard, dan weten we dat ook.

**4. Akkoord op de klanttaal (A6).** Elf screenshots in `screenshots-a6/`. Drie restpunten die je er nog in ziet — een Engelse sidebar, een kopdatum en een oude versieaanduiding in de rapportlijst — repareren wij sowieso; daar hoef je niets over te zeggen. We vragen alleen of de labels zelf kloppen.

**5. Het criterium na de hermeting.** Zodra je §3 beslist hebt doen we één aanpassing en meten we opnieuw. Je klaar-lijst vraagt 16 van de 19. Wat is de grens als we daar net onder landen, op 14 of 15? We vragen geen uitzondering, we willen weten waar de streep ligt voordat we meten.

**6. Prioriteit bij de valkuilen.** De ijkrun staat op 26 fout, 6 goed, uitgesplitst per as: 15 signaal, 8 oordeel, 3 meetlat. Voor de pilot hoeft dat volgens je eigen lijst niet op 100 procent; voor go-live wel. Wij zouden met signaal beginnen, omdat dat de grootste groep is en de andere twee assen voedt. Zie je dat anders?

**7–8. Stroomtest en vier overige testposts.** In `02-terugkoppeling-besluiten.md` §7–§8 staan de concrete vragen over drempels, flag-telling, alerts en de vier 5×19-posts naast neutraal/grensgeval.

## Twee dingen die we je melden, geen vraag

**De flagtelling.** De app telt nu elke risicoflag, ook op posts die op merk zijn, en wijkt daarmee af van wat de Implementation Kit bedoelt. We hebben een voorstel uitgewerkt — alleen posts tellen waarvan het hoofdoordeel niet op merk is — met in beeld wat dat doet met de stroomtestcijfers. Dat zit in het pakket. De drempels zelf zijn aan jou.

**Een rekenfout in het rapport.** Op checkpoint 25 toont engagement 21 posts terwijl er 25 gescoord zijn. Dat is onze bug, hij raakt alleen de weergave, en hij is in reparatie. Onder je eigen bevriezingsregel maakt hij de 5 × 19 niet ongeldig.

## Verder in het pakket

Twee criteria haalden hun doel niet omdat de controle zich vergiste, niet de generator: de hint citeerde een zin die niet in het artikel stond, en stap D labelde twee goede zinnen als onderbouwing-ontbreekt. Beide keren hebben we de prompt met rust gelaten en de controle aangescherpt. Dat staat uitgewerkt in `02-terugkoppeling-besluiten.md`, met een voorstel voor volgende rondes: bij een gefaald criterium eerst vaststellen of het een schrijffout of een meetfout is.

Gehaald zijn: het hoofdoordeel volgt in 100 procent van de scores uit de flags, de spreiding in merkbijdrage is overal hoogstens 1, CE-04 geeft bij alle drie de merken de regel letterlijk terug, het thema-advies is zichtbaar, en de stroomtest is gedraaid.

Groet,
Erik
