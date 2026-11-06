# Cursor-briefing ronde 3: één hoofdoordeel per post, en stabiel

9 oktober 2026 · Rogier Heijning

## Voor Erik

Ronde 2 heeft gewerkt. Post 2 krijgt in geen enkele run meer een regelbreuk, en post 3 krijgt hem bij alle drie de merken in beide runs. Het citatenfilter doet wat het moet doen.

Wat nu nog misgaat:

| Bevinding | Gevolg |
| --- | --- |
| `category_fallback` staat op 13 van de 16 posts, in beide runs. Ook op de regelbreuken en op een post met merkbijdrage 8. | De flag onderscheidt niets meer. Een klant krijgt bij bijna elke post "dit kan iedereen zeggen". |
| Run A en B verschillen bij 4 posts. Twee daarvan komen door de oordeelsregel "distinctiviteit ≥ post 1 − 1", niet door het model. | De matrix meet deels de oordeelsregel in plaats van de app. |
| Run B wordt gerapporteerd als dé stand. | Een post telt pas als goed als hij in alle runs goed is. |
| Brandpunt post 1 krijgt in beide runs `category_fallback`. | De app heeft een punt: een branddatum op de zak is bij specialty-branders gangbaar. De testpost toont de regel niet. |
| Stap 0 (CE-04), T2, T4 en T5 uit ronde 2 staan niet in het verslag. | Het belangrijkste open punt, CE-04, is nog onbekend. |

Wat Rogier besloten heeft:

- Elke post krijgt **één hoofdoordeel**, met een vaste voorrang. Dat hoofdoordeel is straks wat de klant ziet. De losse flags blijven voor ons.
- `category_fallback` geldt alleen als de kern van de tekst categorietaal is.
- De oordeelsregel in de matrix vergelijkt niet meer met post 1.
- Brandpunt post 1 wordt herschreven, de oude tekst blijft als extra testpost.
- Vijf runs per meting, en rapporteren wat in alle runs goed is.

**Hoe je dit gebruikt:** geef alles vanaf "Opdracht voor Cursor" in zijn geheel aan Cursor. Laat Cursor na elke stap stoppen en committen. Gebruik Opus 5 voor R2 en R3 (promptwerk), Composer 2.5 voor de rest.

**Wat jouw hand vraagt:**

- Stap 0 uit ronde 2 (CE-04) eerst afronden, als Cursor niet bij de n8n-executions kan.
- Een vraag van Rogier: op welke schaal loopt `distinctiviteit`? De beste posts komen niet boven 3 à 4. Is dat 0 tot 10, dan is de schaal samengedrukt.
- De klantweergave van het hoofdoordeel (R5) alleen op staging, en eerst laten zien aan Rogier.

---

## Opdracht voor Cursor: context

Dit is ronde 3, na `cursor-briefing-ronde-2-hba-flags.md`. Stand: promptversie v1.6, run A 9 goed / 5 fout / 2 review, run B 13 goed / 1 fout / 2 review (`docs/hba-testmerken-matrix-v1.6-runA.json` en `-runB.json`).

**Uitgangspunten:**

- Model niet wijzigen: `claude-sonnet-4-6`.
- `CE_HBA_PROMPT_VERSION` wordt `v1.7`.
- Alle definities blijven op de gedeelde plek uit ronde 2. Schrijf ze zo dat ze straks zonder aanpassing naar de configuratieopslag van de kalibratiemodule kunnen.
- Geen veldnamen of flagnamen in tekst die de klant ziet.
- Volgorde: R0 tot en met R6, dan het testprotocol. Eén commit per stap.

## R0. Afronden wat in ronde 2 openstaat

Lever deze vier punten op in het verslag, voordat je aan R1 begint:

1. **Stap 0, CE-04:** de oorzaak en het herstel. Dien post 3 van elk merk opnieuw in en leg de feedbackregel vast.
2. **T2:** de bestaande definitie van `foe_vervaging` naast de nieuwe, en of ze wezenlijk verschillen.
3. **T4:** het wizardvoorstel bij hetzelfde archetype (bestanden en schermen).
4. **T5:** het merkcijfer van de drie testmerken vóór en na delen door 10, en de andere plekken die door 5 deelden.

Zoek daarnaast op welke schaal `distinctiviteit` in de scoreprompt staat, en meld dat.

Leg ook vast welk citaat het model gaf bij `constraint_breach` op Duinroos post 6 in run A ("slimmere routes"). Staat dat niet meer in de data, meld dat.

## R1. Oordeelsregel in de matrix: geen vergelijking met post 1 meer (Composer 2.5)

Verwijder de regel "distinctiviteit op niveau post 1 (≥ post1 − 1)". Een post wordt niet meer beoordeeld ten opzichte van een andere post.

Vervang die door: een post met `verwacht_niveau: laag` is fout als `distinctiviteit` boven 2 ligt, of boven `max_distinctiviteit` als dat veld gezet is.

## R2. Definitie van categorietaal aanscherpen (Opus 5)

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

## R3. Eén hoofdoordeel per post (Opus 5 voor de prompt, Composer 2.5 voor de rest)

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

## R4. Testposts aanpassen (Composer 2.5)

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

## R5. Klantweergave voorbereiden, niet live (Composer 2.5)

Bouw op staging een variant van het artikelpaneel die alleen het hoofdoordeel toont, met één zin en, waar dat past, één verbetering. De losse flags blijven verborgen voor de klant.

| Hoofdoordeel | Klantzin |
| --- | --- |
| `regelbreuk` | Je overschrijdt hier een grens die je zelf hebt gezet. |
| `tegenkracht` | Hier redeneer je zoals je tegenstander. |
| `categorietaal` | Dit kan iedere concurrent zo zeggen. |
| `op_merk` | Dit is herkenbaar jij. |
| `neutraal` | Prima voor dit doel; het bouwt niet aan je merk, en dat hoeft ook niet. |

Zet hem niet live. Maak screenshots van de zestien testposts in deze weergave voor Rogier.

## R6. Stabiliteit over vijf runs (Composer 2.5)

Pas het matrixscript aan zodat het elke post vijf keer scoort (`--runs 5`, standaard 5).

Rapporteer per post:

- het hoofdoordeel in elke run;
- het aantal runs waarin het oordeel goed was (bijvoorbeeld 4/5);
- de spreiding van `brandContribution` (hoogste min laagste).

De samenvatting telt alleen posts die in alle vijf runs goed zijn als goed. Een post die tussen runs verschilt, is instabiel en wordt apart gemeld, met de citaten uit de afwijkende run. Rapporteer niet langer één run als dé stand.

## Testprotocol en klaar-criteria

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

## Afronden

- Draai `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap: R0 tot en met R6.
- Deploy naar staging pas na Eriks "ja". Niet naar productie.
- Kort verslag aan Erik met:
    - de vier punten uit R0, en de schaal van distinctiviteit;
    - de stabiliteitstabel over vijf runs, met de klaar-criteria afgevinkt;
    - het aantal posts met `category_fallback`, vóór en na R2;
    - de screenshots van de klantweergave uit R5.
