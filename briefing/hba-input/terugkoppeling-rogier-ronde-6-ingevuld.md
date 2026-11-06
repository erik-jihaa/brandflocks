# Terugkoppeling aan Rogier — na stap 9 van ronde 6

**Stap 9 is afgerond; dit document gaat nu naar Rogier.** Bijgewerkt 19 oktober 2026 met de eindmeting. Dit document verzamelt bevindingen die niet in één stap thuishoren maar wel een besluit van Rogier vragen. Vul het aan zodra er iets bijkomt; stuur het pas als de eindmeting van stap 9 er ligt.

## 1. Twee klaar-criteria die een controlefout bleken, geen bouwfout

Twee keer deze ronde haalde een stap zijn criterium niet, en bleek bij nader inzien niet de generator maar de controle de fout te maken. Beide keren hebben we de prompt met rust gelaten en de controle aangescherpt.

**Stap 2 — de hint citeerde een zin die niet in het artikel staat.** De hint gaf de regel letterlijk weer, zoals gevraagd, maar presenteerde daarbij een verzonnen citaat uit de tekst en corrigeerde dat vervolgens. In alle vijf runs woord voor woord hetzelfde, dus deterministisch. Opgelost met een verificatie: elk fragment tussen aanhalingstekens moet letterlijk in het artikel voorkomen, anders één keer opnieuw en daarna geen hint. Zelfde patroon als de controlecall bij `constraint_breach`.

**Stap 3 — stap D labelde twee goede zinnen als `geen_bron`.** De rewrite zelf slaagde: de verzonnen procesfeiten zijn weg, de aanspreekvorm klopt, en vijf van de zeven merkbeweringen staan op de merkarchitectuur of een bewijsveld. De twee overgebleven gevallen waren labelfouten: één zin werkte een onderbouwde bewering uit en verloor de bron doordat stap D per losse zin oordeelt; de andere was geen feitelijke bewering maar het standpunt van het artikel. Opgelost door de definitie van een merkbewering aan te scherpen en in context te laten beoordelen.

**Wat dit betekent voor de criteria.** Allebei de criteria waren goed gekozen — ze legden een echte fout bloot. Maar ze meten de uitkomst zonder onderscheid te maken tussen een schrijffout en een meetfout. Voorstel voor volgende rondes: als een criterium faalt, eerst vaststellen welke van de twee het is, voordat er aan de prompt wordt geschoven.

## 2. Besluit gevraagd: blokkeert `geen_bron` hard?

Stap 3 laat `geen_bron` indienen en publiceren blokkeren. Dat is streng en op zichzelf goed, maar punt 1 hierboven laat zien dat de detector zich kan vergissen. Een klant kan dan een correct artikel niet publiceren, en dat is bij een pilotklant een groter probleem dan de zin die het ten onrechte markeerde.

**Vraag:** blijft het blokkeren hard, of mag de klant een gemarkeerde zin bevestigen door zelf de bron aan te wijzen? In het tweede geval leggen we de bevestiging vast, zodat zichtbaar blijft hoe vaak de detector ernaast zat.

> **Besluit Rogier:** [x] Niet hard blokkeren. De klant mag een gemarkeerde zin bevestigen door zelf de bron aan te wijzen (bewijsveld, URL of document). Leg elke bevestiging met bron vast. Zonder bron blijft de blokkade, tenzij de zin wordt herschreven. Een standpunt of mening is geen merkbewering en krijgt geen `geen_bron`.

## 3. Besluit gevraagd: waar ligt de grens tussen neutraal en categorietaal?

Dit is het punt dat de eindmeting tegenhoudt. De 5 × 19 op v1.11 geeft 12 van de 19 posts stabiel goed, tegen 16 op v1.10.6. Zeven posts staan op 0 van 5 — consistent hetzelfde verkeerde oordeel, geen spreiding tussen de runs. Dat is geen instabiliteit maar een systematisch verschil van inzicht, en het raakt vooral de posts die als neutraal of grensgeval in de ijkset staan.

De oorzaak zit in de volgorde zelf, niet in de bouw. De code doet precies wat §3 voorschrijft: trede 3 kijkt naar `kern_categorietaal`, trede 4 wordt alleen bereikt als `kern_categorietaal = nee`. En `kern_categorietaal = nee` vereist volgens de definitie dat ergens in de tekst een concreet element van het mechanisme of de regel staat dat niet tot de gangbare praktijk hoort.

Een werkelijk neutrale post heeft dat element per definitie niet. Daarmee valt hij op trede 3 en wordt hij categorietaal. **Zoals de volgorde nu staat is `neutraal` vrijwel onbereikbaar.** Dat verklaart de drie merken die op dit punt allemaal 0 van 5 scoren.

**Vraag:** hoe onderscheiden we een post zonder onderscheidend element dat ook geen categorietaal is? Twee richtingen die wij zien:

1. `kern_categorietaal` beperken tot teksten die wél iets over het merk beweren. Een post die niets claimt, claimt ook niets gangbaars, en valt door naar trede 4.
2. Een post zonder enige merkbewering vóór trede 3 afvangen als `neutraal`.

Wij hebben hier geen voorkeur zonder jouw oordeel; het is een methodiekvraag, geen bouwkeuze.

> **Besluit Rogier:** [x] Richting 2, in de code.
> 1. Nieuw veld van het model: `merkbewering: ja/nee` (beweert de tekst iets over wat het merk is, doet of belooft; nee bij tips, uitleg, service, vacatures en aankondigingen zonder merkbewering).
> 2. Volgorde in PHP: regelbreuk → tegenkracht (foe op kop/opening) → **`merkbewering = nee` gaat direct naar op_merk/neutraal** → categorietaal (`kern_categorietaal = ja`) → op_merk/neutraal op merkbijdrage en contentrol.
> 3. `kern_categorietaal` alleen beoordelen als `merkbewering = ja`.
> 4. Log `merkbewering` en `kern_categorietaal` per score; die staan nu bij alle 95 scores op null.
> 5. Trede 2 heeft in de code de extra voorwaarde `kern_categorietaal = nee`, die niet in §3 staat. Licht toe waarom; is er geen goede reden, haal hem weg en meet het effect op Stelwerk post 5 en Duinroos post 7.
>
> Verwachtingen reviewposts: Brandpunt *Mechanisme dat markthygiëne is* = `categorietaal` (niet meer ambigu); Stelwerk *Samenwerking zonder bewijs* = `op_merk` met `proof_gap` als advies; Duinroos *Kernbewering zonder bewijs* (post 7) = `tegenkracht`, merkbijdrage ≤ 5, zoals in de klaar-criteria.
>
> Daarna één 5 × 19 (poging 2 van 2). Bij ≥ 16 stabiel goed, met hooguit Stelwerk post 5 en Duinroos post 7 als afwijking: pilot start, die twee als bekende beperking. Anders stoppen en ruwe output sturen.

## 4. Akkoord gevraagd op de klanttaal (A6)

De screenshots voor akkoord staan in `docs/a6-screenshots-staging-2026-10-18/` (in het Rogier-pakket: `screenshots-a6/`). Jouw goedkeuring van de labeltabel is het laatste open vinkje van §6.

> **Akkoord Rogier:** [x] Akkoord, met zes restpunten (weergave, vallen niet onder de bevriezing): doelgroeplabels "b to c / personal / b to b" → Consumenten / Persoonlijk (ik-vorm) / Zakelijk; "okay | Verwijder" → "Akkoord | Verwijderen" en kolom niet afkappen; agendadatums nl-NL; afgekapte ritmetekst op Overzicht; oud rapport zonder "(HBA v1.7)"; hashtags meenemen in de controle (nu `#versgebrand` onder een Brandpunt-post).

## 5. Wat er verder uit de eindmeting kwam

Gehaald: het hoofdoordeel volgt in 100 procent van de scores uit de flags (0 van 95 gecorrigeerd), de spreiding in merkbijdrage is overal hoogstens 1, CE-04 geeft bij alle drie de merken de regel letterlijk terug, het thema-advies is zichtbaar, en de stroomtest is gedraaid.

Eén post is werkelijk instabiel (Stelwerk branchetaal, 1 van 5). Twee losse defecten in de V0-run — een 404 op de score van een net aangemaakt artikel bij Duinroos, en een hint die bij Stelwerk niet startte — zijn weergave en timing, geen scoring. Die repareren we tijdens de bevriezing; onder jouw eigen regel maken ze de 5 × 19 niet ongeldig.

De ijkrun staat op 26 fout, 6 goed, 5 review en 9 niet gedraaid, uitgesplitst per as: 15 signaal, 8 oordeel, 3 meetlat. Zie `docs/hba-v1.11-stap6-ijkrun-uitsplitsing.md`.

## 6. Waarom we geen tweede run hebben gedaan

Zeven van de acht afwijkende posts staan op 0 van 5 zonder enige spreiding. Een tweede 5 × 19 reproduceert die en kost drie kwartier. Alleen de ene instabiele post zou anders kunnen uitvallen, en dat verandert de uitslag niet. Wij wachten liever op jouw besluit bij punt 3, want dat raakt naar verwachting alle drie de merken tegelijk.

## 7. Toelichting

De volledige onderbouwing staat in `hba-besluiten-ronde-6-2026-10-19.md` (Rogier, 19 oktober 2026).

## 8. Nog aan te vullen

Vul hier aan wat er tijdens stap 4 tot en met 9 nog bijkomt.
