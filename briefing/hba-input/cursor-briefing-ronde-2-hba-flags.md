# Cursor-briefing ronde 2: HBA-flags scherper op de testmerken

9 oktober 2026 · Rogier Heijning

## Voor Erik

De scoring herkent de regelbreuk (post 3) bij alle drie de merken in alle runs. Deze ronde maakt de overige flags betrouwbaar en zorgt dat de CE-04-review de regelbreuk ook echt benoemt.

Rogier heeft de vragen uit de vorige ronde beantwoord:

| Vraag | Besluit |
| --- | --- |
| Merkcijfer: delen door 5 of door 10? | Door 10 (T5). |
| Drempels voor flags | Geen cijferdrempels. Elke risicoflag moet een letterlijke zin uit de tekst aanwijzen; zonder zin geen flag (T3). |
| Duinroos: zwaardere bewijslast | Promptregel als merk-archetype gelijk is aan markt-archetype (T4). Een wizardregel alleen als voorstel, niet bouwen. |
| Grensgevallen | Vier extra testposts die de valkuilen testen (T6). |
| proof_gap op post 1 | Te streng. De verwachting blijft; de definitie in de prompt wordt aangepast (T2). |
| constraint_breach op post 2 | Komt door een te brede definitie van regelbreuk. Nieuwe definitie (T1). |

**Hoe je dit gebruikt:** geef alles vanaf de kop "Opdracht voor Cursor" tot het einde in zijn geheel aan Cursor. Het deel hierboven is voor jou.

**Wat jouw hand vraagt:**

- Stap 0 (CE-04) vraagt de n8n-executions. Kan Cursor daar niet bij, kijk dan zelf en geef Cursor de bevinding.
- Voor de CE-03-verleiding (testprotocol stap 5) zijn per thema-slot minimaal drie research-items nodig. Zet die handmatig klaar, of koppel per testmerk een echte bron en draai CE-02.
- Deploy naar staging alleen na jouw "ja". Niet naar productie.

---

## Opdracht voor Cursor: context

Dit is ronde 2 na `cursor-hba-testmerken-feedback.md` (9 oktober 2026). S1 tot en met S4 zijn gebouwd. De stand in `docs/hba-testmerken-resultaat.md`, promptversie v1.5: 7 goed, 3 fout, 2 review.

**Wat de analyse van v1.5 laat zien:**

1. **Post 3 (breekt de regel) is goed en stabiel.** `constraint_breach` bij alle drie de merken, in alle runs. Daar niets aan veranderen.
2. **Post 2 (categorietaal) krijgt ook `constraint_breach`.** De oorzaak: `verboden_logica` bevat drie soorten punten door elkaar. Branchetaal ("partner", "persoonlijke zorg", "vers gebrand"), logica van de tegenkracht ("koffie als brandstof", "features als hoofdargument") en handelingen tegen de geloofwaardigheidstest ("korting op oudere voorraad", "wachtlijst weg met invalkrachten"). De huidige definitie noemt elk verboden punt een regelbreuk, dus ook branchetaal. Alleen de derde soort is een regelbreuk.
3. **Post 1 (op merk) krijgt `proof_gap`.** Het model lijkt sinds S2 te verwachten dat elke tekst de geloofwaardigheidstest toont, en behandelt het ontbreken ervan als bewijsgat. Bij Brandpunt (branddatum op de zak) en Duinroos (het schaakvoorval) is de tekst zelf het bewijs.
4. **CE-04 gaf bij alle drie de merken exact dezelfde fallback** ("Geen specifieke feedback."), terwijl de scorer met dezelfde definitie de breuk wel vond. Dat wijst eerder op de verwerking dan op de prompt.

**Uitgangspunten voor deze ronde:**

- Model niet wijzigen: `claude-sonnet-4-6` voor schrijven, scoren en review.
- `CE_HBA_PROMPT_VERSION` wordt `v1.6`.
- Alle definities (regelbreuk, flags, archetype-regel) staan op één plek, in de bestaande gedeelde constante of `prompt_fragments.py`. Score, zelfcontrole, review en HBA-hint gebruiken diezelfde tekst.
- Geen veldnamen of flagnamen in tekst die de klant ziet.
- Volgorde: stap 0, dan T1 tot en met T6, dan het testprotocol. Eén commit per stap.
- Model per stap: Composer 2.5 voor stap 0, T3, T5 en T6. Opus 5 voor het promptwerk in T1, T2 en T4.

## Stap 0: CE-04 eerst diagnosticeren, dan pas de prompt aanpassen

Stel vast waar de regelbreuk in CE-04 verdwijnt voordat je iets aan de prompt verandert.

1. Open de n8n-executions van de drie CE-04-runs van 9 oktober op staging: slots `3633e7a3-cb92-4030-aabf-85ba9004c072` (Brandpunt), `9a294736-4097-4ea4-82f7-e5a190fe8488` (Stelwerk) en `eb743077-0eaf-405e-8222-ae65d4097a55` (Duinroos).
2. Controleer de gerenderde prompt: stonden de geloofwaardigheidstest en de verboden logica er echt in, met inhoud?
3. Controleer de ruwe modeloutput: bevat die een `regelbreuk`-veld, en is dat gevuld?
4. Is het veld gevuld: zoek waar het wegvalt (parser, mapping naar `article_feedback`, de fallback-tak) en herstel dat.
5. Is het veld leeg terwijl de input compleet was: dan pas de prompt aanpassen, met de definitie uit T1.
6. Laat de fallback "Geen specifieke feedback." alleen verschijnen als de review echt niets vond. Valt hij terug door een fout of een leeg veld, log dat als fout.

**Klaar als:** CE-04 op post 3 van alle drie de merken een feedbackregel geeft in klanttaal, in de vorm "Dit stuk gaat in tegen je regel dat …", met het citaat uit de tekst. Schrijf in het verslag wat de oorzaak was.

## Wat te bouwen

### T1. Nieuwe definitie van regelbreuk (Opus 5)

Vervang de huidige tekst in de gedeelde constante en in `hba-regelbreuk-definitie.txt` door de tekst hieronder, letterlijk. Score, zelfcontrole (CE-03 `self_review`), review (CE-04) en HBA-hint gebruiken alle vier deze ene tekst.

```
REGELBREUK: wanneer gaat een tekst in tegen een regel van het merk?

De regel van het merk is de geloofwaardigheidstest: wat het merk aantoonbaar wel
of juist niet doet, ook als dat geld of groei kost. Een tekst breekt de regel als
hij iets aanbiedt, aankondigt, belooft of als vanzelfsprekend neerzet dat het merk
volgens die test juist niet doet, ook als de regel zelf nergens in de tekst staat.
Kijk naar wat de lezer na het lezen gaat doen of geloven, niet naar losse woorden.

De verboden logica telt alleen mee als het verboden punt een handeling of aanbod
beschrijft dat tegen de geloofwaardigheidstest ingaat, zoals korting op oudere
voorraad of een wachtlijst wegwerken met invalkrachten. Verboden punten die
gangbare branchetaal zijn (zoals partner, persoonlijke zorg, of vers gebrand
zonder de regel) zijn geen regelbreuk: meld die als category_fallback. Verboden
punten die de redenering van de tegenkracht overnemen (zoals koffie als brandstof,
features als hoofdargument, efficiëntie als prestatie) meld je als foe_vervaging.

Wel een regelbreuk:
- een aanbod, actie of aankondiging die de geloofwaardigheidstest ondergraaft;
- een nieuw feit over het merk dat met de test in strijd is: een contract, een
  samenwerking, een werkwijze.

Geen regelbreuk:
- de regel noemen, uitleggen, verdedigen of erop voortbouwen, ook als de tekst
  daarbij het verboden onderwerp noemt ("Geen Black Friday bij ons, en dit is
  waarom");
- branchetaal of vlakke claims (dat is category_fallback);
- een onderwerp dat de regel niet raakt.

Meld alleen een regelbreuk als je een concrete zin uit de tekst kunt aanwijzen en
de specifieke handeling uit de geloofwaardigheidstest waar die zin tegenin gaat.
Bij twijfel: geen regelbreuk.
```

### T2. Definities van de overige flags (Opus 5)

Zet dit fragment op dezelfde gedeelde plek en gebruik het in de scoreprompt (`hba-prompt.php`). Lees eerst de bestaande omschrijving van `foe_vervaging`. Betekent die iets wezenlijk anders dan hieronder, behoud dan de bestaande, laat de verwijzing naar `foe_vervaging` in T1 staan en meld het verschil aan Erik.

```
FLAGS: wanneer meld je wat?

proof_gap: de tekst beweert iets over het merk (een kwaliteit, een resultaat, een
cijfer) dat niet onderbouwd wordt door de tekst zelf of door de bewijsvelden. Een
concreet voorval, een naam, een datum of een beschreven handeling is zelf bewijs.
Dat een tekst de geloofwaardigheidstest niet noemt, is geen bewijsgat; dat telt
hoogstens mee in een lagere merkbijdrage.

category_fallback: de tekst zegt wat elke concurrent in de branche kan zeggen.
Daaronder vallen ook verboden punten die gangbare branchetaal zijn.

foe_vervaging: de tekst neemt de redenering van de tegenkracht over, of laat de
tegenkracht vervagen. Daaronder vallen verboden punten die de logica van de
tegenkracht beschrijven.

constraint_breach: alleen volgens de definitie van regelbreuk.

Voor elke flag geldt: noem de zin uit de tekst waarop de flag rust. Kun je geen
zin aanwijzen, meld de flag dan niet.
```

### T3. Elke flag met citaat, anders geen flag (Composer 2.5)

- Voeg aan het outputschema van de score (`hba-prompt.php` ~221-225) het veld `flag_citaten` toe: een object met per gemelde flag de letterlijke zin uit de posttekst.
- Controleer na het parsen in PHP per flag in `architecture_risk_flags`: is er een citaat, en komt dat in de posttekst voor (hoofdletterongevoelig, witruimte genormaliseerd, aanhalingstekens genegeerd)?
- Zo niet: verwijder de flag en zet hem met reden in `flags_verwijderd`. Dat veld is alleen voor debug en de matrix, niet voor de klant.
- Neem `flags_verwijderd` op als kolom in de matrix en in `docs/hba-testmerken-resultaat.md`.
- De frontend blijft deze ronde ongewijzigd.

### T4. Zwaardere bewijslast bij hetzelfde archetype (Opus 5)

Als het archetype van het merk gelijk is aan `markt_archetype` van de branche in `branche_library`, voeg dan dit fragment toe aan de scoreprompt en aan de HBA-hint. `{archetype}` wordt het archetype van het merk.

```
Het archetype van dit merk ({archetype}) is ook het gangbare archetype van de
branche. Passen bij het archetype maakt een tekst hier dus niet onderscheidend.
Geef distinctiviteit boven 2 alleen als de tekst een concreet element van het
mechanisme of de geloofwaardigheidstest laat zien: een handeling, een getal of
een keuze die geld of groei kost. Een zorgzame, warme of betrokken toon zonder
zo'n element is categorietaal: distinctiviteit hoogstens 2, en category_fallback.
```

**Wizard: niet bouwen.** Beschrijf in het verslag hoe deze regel in de wizard zou werken: bij hetzelfde archetype is de brand narrative pas af als de geloofwaardigheidstest is ingevuld en er minstens één bewijsveld is dat meetbaar en openbaar is. Noem welke bestanden en schermen dat raakt.

### T5. Merkcijfer delen door 10 (Composer 2.5)

- Pas `ce_hba_v2_post_positioning_score()` (`hba-v2-repository.php` ~1997) aan: delen door 10 in plaats van door 5.
- Zoek of elders scores van 0 tot 10 door 5 gedeeld worden, en pas die ook aan. Meld welke plekken je vond.
- Leg het merkcijfer van de drie testmerken in het overzicht vast, vóór en na de wijziging.

### T6. Testposts en oordeelslogica uitbreiden (Composer 2.5)

**Oordeelslogica.** Voeg per testpost deze velden toe en laat expliciete velden voorgaan op wat S1 uit de vrije tekst afleidt:

- `verwacht_flags`: moeten aanwezig zijn (bestaat al);
- `verboden_flags`: mogen niet aanwezig zijn;
- `toegestane_flags`: alleen voor post 1, risicoflags die daar wel mogen;
- `max_distinctiviteit`: optioneel plafond.

Een post is **fout** als een verwachte flag ontbreekt, een verboden flag aanwezig is, de distinctiviteit boven `max_distinctiviteit` ligt, of post 1 een risicoflag heeft die niet in `toegestane_flags` staat. De bestaande regel dat post 2 en 3 niet hoog mogen scoren, blijft. Ambigue posts blijven review.

**Bestaande posts aanvullen:**

| Post | Aanvulling |
| --- | --- |
| Post 1, Brandpunt en Duinroos | `toegestane_flags: []` |
| Post 1, Stelwerk | `toegestane_flags: ["proof_gap"]` (het cijfer "een op de drie" wordt in de post niet onderbouwd) |
| Post 2, alle merken | `verboden_flags: ["constraint_breach"]` |
| Post 3, alle merken | ongewijzigd |

**Nieuwe posts.** Voeg deze toe aan `testposts` in de JSON van het betreffende merk, in `briefing/hba-input/HBA_testmerken/` en in `alle_drie_testmerken.json`. Rogier keurt ze met deze briefing goed. Importeer ze daarna met `import-hba-testposts-staging.sh`, als nulmeting zoals de eerste vier.

Brandpunt Koffie, post 5:

```json
{
  "post_tekst": "Geen Black Friday bij ons. Koffie van zeven weken prijzen we niet af, die gaat naar de voedselbank.",
  "platform": "instagram",
  "label": "Verdedigt de regel",
  "verwacht": "hoge brand_contribution, geen constraint_breach: noemt het verboden onderwerp, maar verdedigt de regel",
  "verwacht_flags": [],
  "verboden_flags": ["constraint_breach"],
  "verwacht_niveau": "hoog"
}
```

Stelwerk, post 5:

```json
{
  "post_tekst": "Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor Stelwerk.",
  "platform": "linkedin",
  "label": "Branchetaal uit de verboden logica",
  "verwacht": "lage distinctiviteit, flag category_fallback, geen constraint_breach: onafhankelijk zonder de commissieregel is branchetaal",
  "verwacht_flags": ["category_fallback"],
  "verboden_flags": ["constraint_breach"],
  "verwacht_niveau": "laag"
}
```

Duinroos Thuiszorg, post 5:

```json
{
  "post_tekst": "Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster. Zo zien wij zorg.",
  "platform": "facebook",
  "label": "Archetype zonder bewijs",
  "verwacht": "distinctiviteit hoogstens 2, flag category_fallback: warm en concreet, maar zonder mechanisme of regel",
  "verwacht_flags": ["category_fallback"],
  "verboden_flags": ["constraint_breach"],
  "verwacht_niveau": "laag",
  "max_distinctiviteit": 2
}
```

Duinroos Thuiszorg, post 6:

```json
{
  "post_tekst": "Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.",
  "platform": "linkedin",
  "label": "Logica van de tegenkracht",
  "verwacht": "flag foe_vervaging, geen constraint_breach: efficiëntie als prestatie, maar geen handeling tegen de vier-zorgverlenersregel",
  "verwacht_flags": ["foe_vervaging"],
  "verboden_flags": ["constraint_breach"],
  "verwacht_niveau": "laag"
}
```

## Testprotocol en klaar-criteria

Draai na stap 0 en T1 tot en met T6 deze stappen in volgorde en leg alles vast in `docs/hba-testmerken-resultaat.md`, met datum, model-id en promptversie bij elke run.

1. **Matrix v1.6, run A:** alle 16 testposts (12 bestaande, 4 nieuwe).
2. **Matrix v1.6, run B:** dezelfde 16 posts nog een keer, om de stabiliteit te zien.
3. **Vergelijk** run A en B met v1.5, per post: score, distinctiviteit, flags, `flags_verwijderd`, oordeel.
4. **CE-04:** dien post 3 van elk merk opnieuw in en leg de feedbackregel vast.
5. **CE-03-verleiding:** zodra Erik per thema-slot de research-items heeft klaargezet, laat CE-03 schrijven op de drie thema's uit ronde 1 (Black Friday-actie, nieuw partnerschap met een ERP-leverancier, wachtlijst in één maand weggewerkt). Leg vast of de tekst de regel breekt, of de zelfcontrole het meldt, en welke score en flags de scorer geeft. Noteer dat hetzelfde model schrijft en beoordeelt.

**Klaar als, in beide runs:**

| Post | Criterium |
| --- | --- |
| Post 3, alle merken | `constraint_breach` aanwezig |
| Post 2, alle merken | `category_fallback` aanwezig, `constraint_breach` afwezig |
| Post 1, Brandpunt en Duinroos | geen risicoflags |
| Post 1, Stelwerk | hoogstens `proof_gap` |
| Brandpunt post 5 | `constraint_breach` afwezig |
| Stelwerk post 5 | `category_fallback` aanwezig, `constraint_breach` afwezig |
| Duinroos post 5 | distinctiviteit hoogstens 2, `category_fallback` aanwezig |
| Duinroos post 6 | `foe_vervaging` aanwezig, `constraint_breach` afwezig |
| CE-04, post 3 alle merken | feedbackregel benoemt de regel, met citaat |
| Alle posts | geen flag zonder citaat in de output |

Een post die in één van de twee runs afwijkt, telt als instabiel. Meld hem met beide uitkomsten en de citaten. Haalt een criterium het niet, pas de prompt dan niet zelf verder aan, maar meld het aan Erik.

## Afronden

- Draai `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap: stap 0, T1, T2, T3, T4, T5, T6.
- Deploy naar staging pas na Eriks "ja". Niet naar productie in deze ronde.
- Kort verslag aan Erik met:
    - de oorzaak en het herstel bij CE-04 (stap 0);
    - de tabel v1.5 naast v1.6 run A en run B, met de klaar-criteria afgevinkt;
    - de lijst `flags_verwijderd` uit T3, per post;
    - de bevinding over de bestaande definitie van `foe_vervaging` (T2);
    - het wizardvoorstel uit T4;
    - het merkcijfer van de drie testmerken vóór en na T5, en de andere plekken die door 5 deelden.
