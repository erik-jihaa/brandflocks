# Cursor-briefing — broninspiratie: waarom de scope-slider zichzelf tegenwerkt

**Status:** bugfix + verbetering
**Datum:** aug 2026
**Raakt:** `sources-inspiration-repository.php`, `SourcesPage.tsx`
**Aanleiding:** suggesties voor Bieze blijven middle-of-the-road, ook bij hoge zoekscope

---

## 1. Diagnose — een survivorship-filter

De feature is goed opgezet: scope-slider, temperatuurschaling, scope-guidance die bij hoge waarden
expliciet om contrarian stemmen en adjacent domains vraagt. Toch komt er braafheid uit. De oorzaak
zit niet in de prompt maar in de keten erna.

**Wat er gebeurt in `ce_sources_inspiration_run()`:**

1. `ce_hba_v2_call_json()` wordt aangeroepen **zonder websearch**. Het model verzint de URL's uit
   zijn geheugen.
2. Elke suggestie gaat door `ce_sources_inspiration_probe_suggestion()`. Resolvet de URL niet, dan
   `continue` — stilzwijgend weggegooid.
3. **Er is geen aanvul-lus.** Eén call, filteren, klaar. Sneuvelen er vier van de vijf, dan houd je
   er één over.

En nu de klem: **precies de avontuurlijke suggesties zijn de suggesties waarvan het model de URL
niet goed onthoudt.** Een niche-substack, een onderbelicht YouTube-kanaal, een buitenlandse denker —
daarvan is de exacte feed-URL zeldzaam in de trainingsdata. Van `nu.nl/rss` of een groot vakblad
klopt de URL wél.

Het gevolg is een filter dat systematisch op mainstream selecteert. Zet je de scope hoger, dan stelt
het model gedurfdere bronnen voor, die vaker de probe niet halen, waardoor er relatief méér
brave suggesties overblijven. **De slider werkt zichzelf tegen bij hoge waarden.**

Het bewijs staat in de code zelf — de foutmelding luidt:

> Geen bereikbare bronnen gevonden — verhoog de scope of probeer opnieuw

Dat advies is omgekeerd. Scope verhogen maakt het probleem groter.

Dit alles is onzichtbaar omdat een weggegooide suggestie geen spoor achterlaat: geen log, geen
teller, niets in de UI.

---

## 2. De lat — wat een goede suggestie is

Erik's handmatige set voor Bieze is de norm. Vijf voorbeelden, en wat ze gemeen hebben:

> **Adam Ragusea — koken zonder mythes**
> Ragusea is een voormalig journalist die kookvideo's maakt vanuit één obsessie: waarom werkt dit
> eigenlijk? … **Verrassend omdat** hij de overlap raakt tussen kritisch denken en alledaags eten —
> een perfecte contramal voor Bieze's anti-drempel positie zonder in gezondheidspreek te vervallen.

Vier eigenschappen die de huidige output mist:

| Eigenschap | Nu | In Erik's set |
| --- | --- | --- |
| **Naam + stelling** | alleen een titel | `Adam Ragusea — koken zonder mythes` |
| **Wie/wat is dit** | ontbreekt | twee zinnen karakterisering van de redactionele houding |
| **Waarom verrassend** | opgegaan in "waarom past dit" | eigen zin, begint met *Verrassend omdat* |
| **Afstand tot het merk** | direct relevant | schuin ernaast: datajournalistiek, klassebewust foodschrijven, gedragsdesign |

Dat laatste is de kern. Geen van Erik's vijf is een Nederlands FMCG-marketingblog. Ze raken Bieze
via de *foe* (de kloof tussen wat kookbladen zeggen en wat mensen thuis eten), niet via het
onderwerp.

En let op de vraagstelling die het verschil maakt: *"waarom past dit bij het merk"* is convergent en
levert het midden op. *"Waarom is dit verrassend én verdedigbaar"* dwingt het model de sprong te
rechtvaardigen — dat is een heel andere opdracht.

---

## 3. Wat er moet gebeuren

### 3.1 Websearch aanzetten — dit is de hoofdfix

Zonder search verzint het model URL's. Met search vindt het echte feeds, en overleeft een gedurfde
suggestie de probe. Geef de inspiratie-call een searchtool (of een aparte zoekstap vóór de
JSON-call: eerst kandidaten zoeken, dan pas kiezen en beschrijven).

Alles hieronder helpt ook zonder deze stap, maar dit is de oorzaak.

### 3.2 Overvragen en aanvullen

Vraag **twaalf** suggesties in plaats van vijf. Valideer ze, houd de eerste vijf die slagen én
verschillende invalshoeken bestrijken. Haal je er geen vijf, doe dan **één** aanvullende call met de
al gevonden URL's als uitsluitlijst.

Dit is de goedkoopste ingreep met het grootste effect: de probe mag dan blijven filteren, maar er is
genoeg aanbod om vijf overlevers te houden die niet allemaal mainstream zijn.

Harde grens: maximaal twee LLM-calls per klik, anders loopt dit kostentechnisch weg.

### 3.3 Log wat er sneuvelt

Nu verdwijnen suggesties spoorloos. Schrijf per afgekeurde suggestie een regel naar `event_log`:
categorie, URL, reden (`probe_failed`, `duplicate`, `category_not_allowed`). Zonder dat kun je nooit
vaststellen of het filter of het model het probleem is — en dat is precies waarom dit zo lang
onopgemerkt bleef.

Toon in de UI onder de resultaten één regel: *"3 suggesties vielen af omdat de bron niet bereikbaar
was."* Eerlijk, en het verklaart waarom er soms maar twee kaarten staan.

### 3.4 Diversiteit als eis, niet als hint

De prompt zegt nu *"5 concrete bronnen die bestaande sources aanvullen (andere invalshoek, ander
medium, andere spreker)"*. Dat is een suggestie tussen haakjes. Maak er een eis van, met een
gesloten lijst — zelfde patroon als in `cursor-ce02-invalshoeken.md`:

```
De vijf suggesties moeten vijf VERSCHILLENDE invalshoeken bestrijken. Kies uit:
  tegenkracht      — benoemt precies waar dit merk tegen vecht
  vakgenoot        — een scherpe stem binnen de eigen sector
  aanpalend        — ander vakgebied, zelfde mechanisme
  data             — cijfers of onderzoek over wat mensen werkelijk doen
  praktijk         — de dagelijkse realiteit van de doelgroep
  cultuur          — het onderwerp als maatschappelijk of cultureel verschijnsel
  buitenland       — een stem van buiten het eigen taalgebied

Elke invalshoek maximaal één keer. Spreid ook over categorieën: niet vijf keer URL/RSS.
```

Erik's Bieze-set dekt precies dit: `praktijk` (Ragusea), `cultuur` (Vittles), `aanpalend`
(gedragsdesign), `data` (The Pudding), `tegenkracht` (Kids Eat in Color).

### 3.5 Het antwoordschema aanpassen

Nu: `category`, `title`, `url`, `description`. Splits `description` op, zodat het model gedwongen
wordt beide dingen apart te doen:

```json
{
  "category": "YouTube",
  "title": "Adam Ragusea",
  "stelling": "koken zonder mythes",
  "invalshoek": "praktijk",
  "wat": "Voormalig journalist die kookvideo's maakt vanuit één obsessie: waarom werkt dit eigenlijk? Demystificeert keukenconventies met bronnen en zelfspot.",
  "verrassend_omdat": "Raakt de overlap tussen kritisch denken en alledaags eten — een contramal voor de anti-drempelpositie, zonder in gezondheidspreek te vervallen.",
  "url": "https://www.youtube.com/feeds/videos.xml?channel_id=UC9_p50tH3WmMslWRWKnM7dQ"
}
```

`verrassend_omdat` als **verplicht** veld is de belangrijkste wijziging na de websearch. Een model
dat moet opschrijven waarom iets verrassend is, kan niet wegkomen met het meest voor de hand
liggende vakblad — dan staat er een lege zin.

Voeg toe aan de prompt: *"Kan `verrassend_omdat` niet overtuigend ingevuld worden, laat de suggestie
dan weg en kies een andere bron."*

### 3.6 Twee kleine dingen

- **Foutmelding omdraaien.** *"Geen bereikbare bronnen gevonden — verhoog de scope"* wordt
  *"We vonden wel suggesties, maar de bronnen bleken niet bereikbaar. Probeer opnieuw, of verlaag de
  scope voor bekendere bronnen."*
- **Bestaande bronnen als uitsluiting én als signaal.** De prompt krijgt de bestaande sources al mee
  om duplicaten te vermijden. Voeg toe: *"Kijk waar de bestaande set blind is — welk medium, welke
  invalshoek, welke geografie ontbreekt — en vul dáár aan."*

---

## 4. UI

De kaart toont nu titel + description. Volg Erik's opmaak:

```
YouTube
Adam Ragusea — koken zonder mythes          [ Toevoegen ]
Voormalig journalist die kookvideo's maakt vanuit één obsessie…
Verrassend omdat: raakt de overlap tussen kritisch denken en alledaags eten.
```

- Titel + stelling op één regel, gescheiden door een em-dash, `text-ce-blue font-semibold`.
- `wat` in `text-ce-black`, `verrassend_omdat` in `text-ce-beige` met het voorvoegsel vet.
- Het invalshoek-label rechtsboven als outline-pill, in mensentaal (*Uit de praktijk*, *Uit een
  andere sector*) — nooit de systeemsleutel.

Bestaande suggesties zonder de nieuwe velden moeten blijven renderen; val terug op `description`.

---

## 5. Volgorde

1. **Overvragen + aanvullen** (§3.2) en **logging** (§3.3). Klein, en je ziet meteen hoeveel er
   sneuvelt — dat cijfer bepaalt hoe urgent stap 3 is.
2. **Schema + `verrassend_omdat` + invalshoek-eis** (§3.4, §3.5). Grootste kwaliteitssprong in de
   tekst zelf.
3. **Websearch** (§3.1). Meeste werk, lost de oorzaak op.
4. UI en foutmelding.

---

## 6. Acceptatiecriteria

- Draai de inspiratie drie keer voor Bieze op scope 80. Elke run levert vijf suggesties met vijf
  verschillende invalshoeken en minstens drie verschillende categorieën.
- Geen enkele suggestie is een generiek Nederlands vakblad of een bekend marketingplatform.
- Elke suggestie heeft een `verrassend_omdat` die naar de foe, distinctiviteit of doelgroep van het
  merk verwijst — niet naar het onderwerp in het algemeen.
- Alle vijf URL's openen en leveren items op.
- Het aantal afgevallen suggesties staat in `event_log` en wordt in de UI genoemd.
- Scope 20 levert aantoonbaar bravere suggesties dan scope 80 — de slider doet weer wat hij belooft.
