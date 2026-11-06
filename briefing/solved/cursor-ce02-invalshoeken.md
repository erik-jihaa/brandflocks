# Cursor-briefing — CE-02: vijf uiteenlopende invalshoeken in plaats van vijf keer hetzelfde

**Status:** voorstel
**Datum:** aug 2026
**Raakt:** `scripts/generate-ce02-workflow.py`, `JSON/CE-02 - Research & Inspiratie.json`,
`research_items`, `ResearchItemRow.tsx`
**Zie ook:** `cursor-hba-v2-meedenker.md` (de HBA-laag beoordeelt wat hieruit voortkomt)

---

## 1. Waarom research nu voorspelbaar is

De systeemprompt van CE-02 zegt:

> Kies de **meest relevante** artikelen voor het thema en schrijf korte AI-samenvattingen…

En de user-prompt:

> Kies minimaal 3 en maximaal `{max_items}` items voor dit merk/thema.

"Meest relevant" is een **convergente** instructie. Het model rangschikt kandidaten op nabijheid tot
het thema en pakt de bovenste vijf — die per definitie allemaal rond hetzelfde middelpunt liggen. Je
krijgt dus vijf variaties op één invalshoek, en het artikel dat eruit volgt kan alleen nog het
midden van het thema beschrijven.

Diversiteit ontstaat niet vanzelf uit "wees relevant". Het moet een **harde eis in de output** zijn,
met een gesloten lijst om uit te putten — anders krijg je willekeur in plaats van spreiding.

Er zit al een opening in de bestaande prompt die we kunnen uitbouwen:

> bij weinig bronnen mag je aanvullende relevante invalshoeken toevoegen (url leeg laten)

Dat is nu een noodgreep bij te weinig bronnen. Wij maken er een vast onderdeel van.

---

## 2. De zeven invalshoeken

Een gesloten lijst, want "wees creatief" levert ruis. Per run moet CE-02 **vijf verschillende**
invalshoeken afdekken. Welke vijf mag het model kiezen, maar niet twee keer dezelfde.

| Sleutel | Invalshoek | Wat het oplevert |
| --- | --- | --- |
| `tegenkracht` | Het thema vanuit waar het merk tegen vecht | Scherpte. Dit is de as die als eerste vervaagt |
| `bewijs` | Een concreet geval, cijfer of meting | Geloofwaardigheid; iets dat een concurrent niet kan naschrijven |
| `contra_intuitief` | De stelling die de meeste vakgenoten níét zouden innemen | Aandacht, en het dwingt tot positie kiezen |
| `aanpalend` | Hetzelfde mechanisme in een andere sector | Verreweg de rijkste bron van originele content |
| `praktijk` | Hoe het er op een gewone werkdag uitziet, voor één mens | Herkenbaarheid; haalt het thema uit de abstractie |
| `tijd` | Historische parallel of wat er over drie jaar anders is | Perspectief; werkt goed voor merkbouwende content |
| `systeem` | Uitzoomen naar de bredere keten of maatschappelijke context | Autoriteit; past bij een Wijze- of Leider-archetype |

`aanpalend` is de belangrijkste toevoeging. "Dit probleem speelt ook in de luchtvaart, en daar
lossen ze het zo op" is precies het soort artikel dat een concurrent niet toevallig ook schrijft.

---

## 3. Wat er in de prompt verandert

### 3.1 Systeemprompt — vervang de convergente zin

Nu:

```
Kies de meest relevante artikelen voor het thema en schrijf korte AI-samenvattingen (snippet)
plus item_rationale (1-2 zinnen).
```

Wordt:

```
Stel een gevarieerde researchset samen voor het thema. Niet de vijf meest voor de hand
liggende bronnen, maar vijf verschillende INVALSHOEKEN op hetzelfde thema.

BESCHIKBARE INVALSHOEKEN (kies er precies vijf, elk maximaal één keer):
  tegenkracht      — het thema vanuit waar dit merk tegen vecht
  bewijs           — een concreet geval, cijfer of meting
  contra_intuitief — de stelling die de meeste vakgenoten niet zouden innemen
  aanpalend        — hetzelfde mechanisme in een andere sector of vakgebied
  praktijk         — hoe dit er op een gewone werkdag uitziet, voor een mens
  tijd             — een historische parallel, of wat hier over drie jaar anders is
  systeem          — de bredere keten of maatschappelijke context

REGELS:
- Elke invalshoek precies één keer. Vijf verschillende, geen dubbele.
- Gebruik een kandidaat-bron waar die past bij de invalshoek. Past er geen enkele bron bij
  een invalshoek die het thema sterker maakt, formuleer die invalshoek dan zelf en laat url
  leeg — dat is een aanzet voor de redactie, geen bron.
- Avontuurlijk in invalshoek, streng in merk. Een verrassende hoek die niet bij dit
  archetype, dit merkdoel of deze tegenkracht past, is geen goede hoek. Schrap hem dan.
- Vermijd invalshoeken die de afgelopen weken al gebruikt zijn (zie RECENT).
```

De regel *"avontuurlijk in invalshoek, streng in merk"* is niet decoratief. Zonder die grens levert
"wees avontuurlijk" content op die van elk merk had kunnen komen — precies wat de HBA-laag straks
als *verwatert* aanmerkt.

### 3.2 User-prompt — twee toevoegingen

Onder de kandidatenlijst:

```
RECENT GEBRUIKTE INVALSHOEKEN (laatste 6 weken):
  {recente_invalshoeken}
```

Zonder dit wordt "divers" binnen een maand zijn eigen sleur: elke week dezelfde vijf favorieten.
Zelfde principe als `recente_themas` bij HBA-ingrijppunt 1.

En vervang de slotzin:

```
Lever exact vijf items, elk met een andere invalshoek. Minimaal drie ervan moeten op een
echte kandidaat-bron rusten; de rest mag een zelf geformuleerde aanzet zijn.
```

De ondergrens van drie gegronde items voorkomt dat de set in verzonnen richtingen verandert.

### 3.3 Output-schema — één veld erbij

In de tool `pack_research_items`, per item:

```json
"invalshoek": {
  "type": "string",
  "enum": ["tegenkracht","bewijs","contra_intuitief","aanpalend","praktijk","tijd","systeem"]
}
```

Toevoegen aan `required`. Zonder enum krijg je vrije tekst en kun je niet op herhaling filteren.

---

## 4. Datamodel

Eén kolom:

```sql
ALTER TABLE research_items ADD COLUMN invalshoek VARCHAR(20) NULL;
```

Volg het bestaande auto-schema-patroon (idempotent, zoals `ce_ensure_sources_columns`). `NULL` voor
alle bestaande rijen — geen migratie van historie nodig.

`{recente_invalshoeken}` is dan één query:

```sql
SELECT DISTINCT invalshoek FROM research_items
WHERE client_id = :client_id AND invalshoek IS NOT NULL
  AND created_at > DATE_SUB(NOW(), INTERVAL 6 WEEK);
```

---

## 5. UI — twee dingen op `/research`

**5.1 Toon de invalshoek.** Een klein label boven de titel in `ResearchItemRow`, in `text-ce-beige`
`text-[12px]`, in mensentaal — niet de systeemsleutel:

| Sleutel | Label voor de klant |
| --- | --- |
| `tegenkracht` | Waar je tegen vecht |
| `bewijs` | Bewijs |
| `contra_intuitief` | Tegendraads |
| `aanpalend` | Uit een andere sector |
| `praktijk` | Uit de praktijk |
| `tijd` | Toen en straks |
| `systeem` | Het grotere geheel |

Zo ziet de redacteur in één blik dát de set gespreid is, en kan hij kiezen op hoek in plaats van op
volgorde.

**5.2 Markeer wat niet op een bron rust.** Nu toont `ResearchItemRow` alleen een link als `url`
gevuld is — een zelfbedachte invalshoek is visueel niet te onderscheiden van een echt artikel. Dat
is misleidend. Zet er waar `url` leeg is een regel bij:

> Eigen aanzet — geen bron, wel een richting.

Zelfde beige, zelfde grootte als de rationale.

---

## 6. Waarom dit de HBA-score omhoog hoort te duwen

Even expliciet, want "avontuurlijk" klinkt als een risico voor de merkzuiverheid en dat is het niet.

De HBA-scoring rekent `category_fallback` af: taal die iedereen in de categorie had kunnen
gebruiken. Dat is precies wat je krijgt uit vijf bronnen rond het middelpunt van een thema. De
`tegenkracht`-as is bovendien statistisch de eerste die vervaagt (Complete Kit, axis-erosion alert
met een strengere drempel).

Door `tegenkracht`, `bewijs` en `aanpalend` structureel in elke researchset te forceren, voed je
precies de assen die anders wegzakken. **Verwachting: Brand Contribution en Story Triangle Coverage
gaan omhoog, niet omlaag.** Dat is meteen de manier om te toetsen of deze wijziging werkt — vergelijk
de PBS-componenten over vier weken vóór en na.

---

## 7. Uitrollen en meten

1. Kolom + prompt + schema, op één tenant met genoeg bronnen.
2. Draai CE-02 handmatig op drie thema's en lees de vijf items. Toets: zou een concurrent deze set
   ook hebben? Zo ja, staat de invalshoek-eis niet strak genoeg.
3. UI-labels erbij.
4. Na vier weken: welke invalshoeken worden geliked en welke geforget?
   `SELECT invalshoek, COUNT(*) FROM research_items WHERE liked = 1 GROUP BY invalshoek;`
   Dat is de eerlijkste kwaliteitsmeting die je hebt, en hij kost niets — Like/Forget bestaat al.

Blijft één hoek structureel ongeliked, dan zegt dat iets over dit merk, niet over de methode. Laat
de lijst dan per tenant wegen in plaats van hem in te korten.

---

## 8. Acceptatiecriteria

- Elke CE-02-run levert vijf items met vijf verschillende `invalshoek`-waarden uit de enum.
- Minimaal drie items hebben een gevulde `url`.
- Een invalshoek die de afgelopen zes weken al gebruikt is, komt niet automatisch terug.
- Op `/research` staat per item een leesbaar hoeklabel, en items zonder bron zijn als zodanig
  gemarkeerd.
- Geen systeemsleutels (`contra_intuitief`) zichtbaar in de klant-UI.
- Bestaande research zonder `invalshoek` rendert ongewijzigd, zonder label.
