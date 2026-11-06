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

> **Besluit Rogier:** [ ]

## 3. Besluit gevraagd: waar ligt de grens tussen neutraal en categorietaal?

Dit is het punt dat de eindmeting tegenhoudt. De 5 × 19 op v1.11 geeft 12 van de 19 posts stabiel goed, tegen 16 op v1.10.6. Zeven posts staan op 0 van 5 — consistent hetzelfde verkeerde oordeel, geen spreiding tussen de runs. Dat is geen instabiliteit maar een systematisch verschil van inzicht, en het raakt vooral de posts die als neutraal of grensgeval in de ijkset staan.

De oorzaak zit in de volgorde zelf, niet in de bouw. De code doet precies wat §3 voorschrijft: trede 3 kijkt naar `kern_categorietaal`, trede 4 wordt alleen bereikt als `kern_categorietaal = nee`. En `kern_categorietaal = nee` vereist volgens de definitie dat ergens in de tekst een concreet element van het mechanisme of de regel staat dat niet tot de gangbare praktijk hoort.

Een werkelijk neutrale post heeft dat element per definitie niet. Daarmee valt hij op trede 3 en wordt hij categorietaal. **Zoals de volgorde nu staat is `neutraal` vrijwel onbereikbaar.** Dat verklaart de drie merken die op dit punt allemaal 0 van 5 scoren.

**Vraag:** hoe onderscheiden we een post zonder onderscheidend element dat ook geen categorietaal is? Twee richtingen die wij zien:

1. `kern_categorietaal` beperken tot teksten die wél iets over het merk beweren. Een post die niets claimt, claimt ook niets gangbaars, en valt door naar trede 4.
2. Een post zonder enige merkbewering vóór trede 3 afvangen als `neutraal`.

Wij hebben hier geen voorkeur zonder jouw oordeel; het is een methodiekvraag, geen bouwkeuze.

> **Besluit Rogier:** [ ]

## 4. Akkoord gevraagd op de klanttaal (A6)

De screenshots voor akkoord staan in `docs/a6-screenshots-staging-2026-10-18/` (in het Rogier-pakket: `screenshots-a6/`). Jouw goedkeuring van de labeltabel is het laatste open vinkje van §6.

> **Akkoord Rogier:** [ ]

## 5. Wat er verder uit de eindmeting kwam

Gehaald: het hoofdoordeel volgt in 100 procent van de scores uit de flags (0 van 95 gecorrigeerd), de spreiding in merkbijdrage is overal hoogstens 1, CE-04 geeft bij alle drie de merken de regel letterlijk terug, het thema-advies is zichtbaar, en de stroomtest is gedraaid.

Eén post is werkelijk instabiel (Stelwerk branchetaal, 1 van 5). Twee losse defecten in de V0-run — een 404 op de score van een net aangemaakt artikel bij Duinroos, en een hint die bij Stelwerk niet startte — zijn weergave en timing, geen scoring. Die repareren we tijdens de bevriezing; onder jouw eigen regel maken ze de 5 × 19 niet ongeldig.

De ijkrun staat op 26 fout, 6 goed, 5 review en 9 niet gedraaid, uitgesplitst per as: 15 signaal, 8 oordeel, 3 meetlat. Zie `docs/hba-v1.11-stap6-ijkrun-uitsplitsing.md`.

## 6. Waarom we geen tweede run hebben gedaan

Zeven van de acht afwijkende posts staan op 0 van 5 zonder enige spreiding. Een tweede 5 × 19 reproduceert die en kost drie kwartier. Alleen de ene instabiele post zou anders kunnen uitvallen, en dat verandert de uitslag niet. Wij wachten liever op jouw besluit bij punt 3, want dat raakt naar verwachting alle drie de merken tegelijk.

## 7. Besluit gevraagd: stroomtest Brandpunt (stap 8)

De run staat in het pakket (`06-stroomtest-brandpunt.md`, screenshots, samenvatting). Onboarding poging 1 geblokkeerd / poging 2 actief klopt met je script; checkpoints wijken vooral af op **telling** en **alerts**.

**Drempels.** Implementation Kit (3/5 flags in 10 posts, PBS −8/−15) vs app (`erosionAlert` −10, skew 4/6, geen 3/5-teller). Is de **huidige app** leidend voor de pilot, of alignen we kit en code vóór start?

**Flag-telling.** Wij tellen in JSON elke niet-lege `architecture_risk_flags` (ook op een `op_merk`-post). Jij telde waarschijnlijk **patroon-posts** (hoofdoordeel ≠ op merk). Welke definitie moet in **rapport en agenda**?

**Alerts post 20–25.** Veel categorietaal/regelbreuk in het window, maar **geen** zware PBS-/portfolio-signalen zoals in je verwachting. Productiebug, bewust andere drempel, of verwachting kit bijstellen?

**Rapport cp25.** Engagement-blok toont **21** posts; DB heeft **25** scores. Blokkeert dat jouw beoordeling, of secundair (import/telverschil)?

> **Besluit Rogier:** [ ]

## 8. Overige 5×19-posts (naast neutraal/grensgeval)

Zeven posts staan 0/5; drie zijn neutraal/grensgeval (§3). Voor de overige vier graag jouw oordeel: **matrix verouderd** of **scorer moet anders**:

| Post | Uitslag 5×5 | Vraag |
| --- | --- | --- |
| Brandpunt — Mechanisme markthygiëne | categorietaal | Verwachting nog steeds anders? |
| Duinroos — Kernbewering zonder bewijs (V0) | mix op_merk/tegenkracht | Blijft verwachting **tegenkracht**? |
| Stelwerk — Branchetaal | 1/5 (foe vs categorietaal) | Zelfde vraag als v1.10: popularity-zin = testset of regel? |
| Stelwerk — Samenwerking zonder bewijs | op_merk | Verwachting review/fout — matrix of strenger scorer? |

> **Besluit Rogier:** [ ]
