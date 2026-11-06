# Cursor-briefing — van aandachtspunt naar onderbouwing

**Status:** één bug, twee features
**Datum:** aug 2026
**Raakt:** CE-02, `research_items`, CE-03 stap D, `sources-inspiration-repository.php`,
`ArticleSelfReviewSection.tsx`
**Aanleiding:** stap D op een Bieze-artikel meldt drie keer "geen onderbouwing in de research"

---

## 1. Eerst de oorzaak: de research was leeg

Alle drie de aandachtspunten wijzen naar hetzelfde:

> …die slechts als 'medium' betrouwbaar en **inhoudsloos ('Untitled')** is gemarkeerd
> De beschikbare research is te summier (**'Untitled', geen inhoud zichtbaar**)

Het artikel is geschreven op researchitems zonder titel en zonder snippet. Stap D doet zijn werk
prima — hij meldt terecht dat er niets onder ligt. Maar het probleem zit een stap eerder.

**Onderzoek dit voordat je iets bouwt:**

```sql
SELECT id, source_type, title, LENGTH(snippet) AS snip, url
FROM research_items
WHERE client_id = '<bieze>' AND slot_id = '<slot>'
ORDER BY created_at;
```

Mogelijke oorzaken, in volgorde van waarschijnlijkheid:

- De bronfetch haalde geen `og:description` of `<meta name="description">` op
  (`sources-repository.php` ~1492) en viel terug op leeg, zonder dat iemand het merkte.
- De pagina blokkeerde de bot en er kwam HTML terug in plaats van inhoud.
- CE-02 selecteerde kandidaten waarvan het snippet al leeg was.

**Bouw een harde ondergrens in.** Een researchitem zonder titel óf met een snippet korter dan
ongeveer 80 tekens is geen researchitem. Twee dingen:

- CE-02 mag zulke kandidaten niet aanbieden.
- Is er na filtering minder dan drie bruikbare items, laat CE-03 dan **niet** draaien maar zet het
  slot op een status die de redacteur ziet: *"Te weinig research om een artikel op te baseren —
  vul je bronnen aan of kies een ander thema."*

Een artikel schrijven op lege research en het daarna door stap D laten afkeuren, kost twee
LLM-calls en levert een onbruikbaar artikel op.

---

## 2. Stap D vraagt naar velden die niet bestaan

De promptvraag van stap D luidt letterlijk:

> …geen onderbouwing, of gebaseerd op een item met **reliability = low**
> …een item met **stance = contrasts** dat niet verwerkt is

Die twee velden bestaan nergens in `research_items`. Het model krijgt dus een vraag over metadata
die het niet heeft, en gaat gissen — vandaar het "als 'medium' betrouwbaar gemarkeerd" in de
output, dat nergens vandaan komt.

Twee wegen. Ik zou de tweede nemen.

**Weg 1:** haal `reliability` en `stance` uit de stap D-prompt. Snel, maar dan kan stap D vraag 3
(gemist tegengeluid) principieel nooit beantwoorden.

**Weg 2 — aanbevolen:** voeg de velden toe in CE-02. Dat maakt stap D pas echt bruikbaar en het
sluit aan op `cursor-ce02-invalshoeken.md`, waar `invalshoek` al bijkomt. Doe het in één migratie:

```sql
ALTER TABLE research_items ADD COLUMN invalshoek  VARCHAR(20) NULL;
ALTER TABLE research_items ADD COLUMN stance      ENUM('bevestigt','nuanceert','spreekt_tegen') NULL;
ALTER TABLE research_items ADD COLUMN reliability ENUM('low','medium','high') NULL;
```

CE-02 vult ze per item:

- `stance` — verhoudt dit item zich bevestigend, nuancerend of tegensprekend tot het thema?
- `reliability` — `high` bij onderzoek, cijfers of een primaire bron; `medium` bij vakmedia;
  `low` bij een mening, blogpost of ongedateerde pagina.

En geef CE-02 de instructie: **minstens één item met `stance = spreekt_tegen`**, mits aanwezig in de
kandidaten. Dan kan stap D vraag 3 daadwerkelijk toetsen in plaats van gissen.

---

## 3. Feature A — aandachtspunt wordt bronsuggestie

Dit is de goedkoopste van de twee en de veiligste.

Stap D benoemt heel concreet wat ontbreekt: *"gedragsclaim over het effect van vasten op
keuzegedrag"*, *"vezels en verzadiging als preventie van overeten"*. Dat is geen vaag gevoel maar
een bruikbare zoekopdracht.

**Wat je bouwt:** onder elk aandachtspunt in `ArticleSelfReviewSection` een knop
**Zoek een bron hiervoor**. Die roept de bestaande inspiratie-engine aan, maar met een gerichte
opdracht in plaats van de algemene:

```
Dit artikel doet een claim die niet onderbouwd is:
"{weakest_claim}"

Welke terugkerende bron zou dit soort claims structureel onderbouwen voor dit merk?
Geef 3 suggesties volgens het gebruikelijke schema.
```

Waarom dit beter werkt dan de algemene inspiratie: de vraag is specifiek, dus de suggesties zijn
specifiek. "Welke bron dekt gedragsonderzoek over eetmomenten" levert scherpere resultaten dan
"welke bronnen passen bij dit merk".

Belangrijk: dit levert een **bron**, geen onderbouwing voor dít artikel. Het lost het volgende
artikel op, niet dit artikel. Wees daar eerlijk over in de knoptekst — noem hem
*Zoek een bron hiervoor* en niet *Onderbouw dit*.

Combineer met §3.2 uit `cursor-broninspiratie-avontuurlijker.md`: overvragen en aanvullen, anders
sneuvelen de suggesties opnieuw in de probe.

---

## 4. Feature B — onderbouwing buiten de bronnen om

Dit is de wens "research ook buiten de sources zoeken". Kan, maar met grenzen, want het raakt de
kern van het product.

**Waarom grenzen nodig zijn.** De productvisie zegt: de klant kiest bronnen die hij vertrouwt. Dat
is de belofte. Haal je er ongevraagd willekeurige webresultaten bij, dan is die belofte weg en weet
de redacteur niet meer waar iets vandaan komt. Dus:

| Regel | Waarom |
| --- | --- |
| **Alleen op verzoek**, nooit automatisch in CE-02 of CE-03 | De vaste bronnen blijven de basis |
| Resultaten komen in een **apart blok**, niet tussen de gewone research | Herkomst moet zichtbaar blijven |
| De redacteur moet er expliciet één **accepteren** voordat hij meetelt | Menselijke poort |
| Geaccepteerde items krijgen `source_type = 'adhoc_web'` en `source_id = NULL` | Traceerbaar in het rapport |
| Maximaal 3 resultaten per klik, maximaal een paar keer per artikel | Kosten en scope |

**Flow:** knop **Onderbouw deze claim** onder het aandachtspunt → websearch op de geciteerde zin →
per resultaat titel, bron, datum en het citaat dat de claim raakt → redacteur kiest → item landt in
`research_items` bij dit slot, met `stance` en `reliability` ingevuld → optioneel CE-03 stap B
opnieuw draaien met de aangevulde research.

Toon per resultaat **het letterlijke citaat**, niet alleen een samenvatting. De redacteur moet
kunnen zien of de bron werkelijk zegt wat de claim beweert. Dat is het hele punt van onderbouwen.

---

## 5. Let op: dit is een voedingsmerk

Het voorbeeld dat je stuurde gaat niet toevallig over vezels en verzadiging. Twee van de drie
aandachtspunten zijn **gezondheidsclaims over voeding**:

> "Als je de hele dag weinig eet … kom je met een lege maag aan tafel"
> "Een lunch met wat groente, wat vezels, iets dat vult zonder te zwaar te zijn"

Voedingsclaims van een levensmiddelenproducent vallen in de EU onder de claimsverordening. Een
merk mag niet zomaar een gezondheidseffect suggereren, ook niet terloops in een blogpost. Dit is
dus niet alleen een redactionele kwestie maar een aansprakelijkheidskwestie.

**Consequentie voor het ontwerp:** bij een claim die stap D niet onderbouwd vindt, is "zoek er een
bron bij" niet altijd het juiste antwoord. Vaak is het juiste antwoord: haal de claim eruit of maak
hem zachter. Geef daarom **drie** knoppen onder een aandachtspunt, niet één:

- **Zoek een bron hiervoor** (feature A — structureel)
- **Onderbouw deze claim** (feature B — voor dit artikel)
- **Formuleer voorzichtiger** — laat het model de zin herschrijven zonder de causale claim

Die derde is de goedkoopste en vaak de verstandigste. Zet hem niet weg als noodoplossing.

Overweeg daarnaast een merkregel voor voedingstenants (`regels` in de merkarchitectuur, waar de
HBA-scoring al op toetst): *"We doen geen uitspraken over gezondheidseffecten zonder bron."* Dan
vangt ingrijppunt 2 het al af tijdens het schrijven, vóór stap D.

---

## 6. Volgorde

1. **Onderzoek waarom de research leeg was** (§1) en zet de ondergrens erop. Zonder dit is de rest
   symptoombestrijding.
2. **`stance` + `reliability` + `invalshoek`** in één migratie (§2), CE-02 vult ze.
3. **Feature A** — aandachtspunt naar bronsuggestie (§3). Klein, hergebruikt bestaande engine.
4. **Formuleer voorzichtiger** (§5). Eén extra LLM-call, geen nieuwe infrastructuur.
5. **Feature B** — ad-hoc websearch met acceptatiestap (§4). Meeste werk en het meeste risico op
   sluipende scope; doe hem als laatste.

---

## 7. Acceptatiecriteria

- Een slot met minder dan drie bruikbare researchitems produceert géén artikel maar een leesbare
  melding aan de redacteur.
- Geen enkel `research_item` heeft een lege titel of een snippet korter dan 80 tekens.
- Stap D verwijst alleen naar velden die bestaan; `stance` en `reliability` zijn gevuld.
- CE-02 levert minstens één item met `stance = spreekt_tegen` als de kandidaten dat toelaten.
- Onder elk aandachtspunt staan drie acties, en de knoptekst belooft niet meer dan hij levert.
- Ad-hoc gevonden bronnen zijn in de UI en in de database te onderscheiden van vaste bronnen
  (`source_type = 'adhoc_web'`), en komen er nooit in zonder dat een mens ze heeft geaccepteerd.
