# HBA v1.8: antwoorden op de beslispunten

10 oktober 2026 · Rogier Heijning

Antwoord op `hba-kalibratie-beslispunten-roger-v1.8.md`.

## Voor Erik

Dank voor ronde 3. De kern werkt: de regelbreuk, de verdediging van de regel en de tegenkracht bij Duinroos zijn in alle vijf runs goed. De missers komen uit drie patronen:

1. **Tegenkracht op algemene zinnen.** `foe_vervaging` valt op zinnen als "Neem contact op voor een vrijblijvend gesprek", "Zo zien wij zorg." en "Daarom kiezen steeds meer maakbedrijven voor Stelwerk". Geen van die zinnen neemt de redenering over van de tegenkracht van dat merk.
2. **De PHP-correctie maakt posts strenger dan het model.** Het model noemt "Vijf tips om je koffiezetapparaat te ontkalken" neutraal; PHP maakt er categorietaal van omdat er een `category_fallback` op staat. Die correctie-regel kwam uit mijn opdracht in R3 en was te grof. Daar komen ook veel van de 25 correcties vandaan.
3. **Bewijsgat op beschreven handelingen.** `proof_gap` valt op "Over twee weken in de winkel, zodra hij is uitgerust" en "Dus komt het team om tien uur". Volgens de definitie is een beschreven handeling zelf bewijs.

Mijn besluiten:

| # | Beslispunt | Besluit |
| --- | --- | --- |
| 1 | Tegenkracht vóór categorietaal | Voorrang blijft. Functionele onderdelen (oproep tot contact, datum, link, praktische info) worden niet beoordeeld. `foe_vervaging` alleen als de zin de redenering van de tegenkracht van dít merk overneemt. |
| 2 | Neutraal tegenover categorietaal | **A.** Een service-, werving- of educatiepost is neutraal. Een interne flag mag blijven staan. PHP zet neutraal of op_merk niet meer om. |
| 3 | `proof_gap` op post 1 | **A.** Een beschreven handeling, werkwijze, timing of proces is bewijs, geen bewering. |
| 4 | Brandpunt post 6 | **A.** Blijft review: volgen, geen go-live-criterium. |
| 5 | `category_fallback` op 10/17 | v1.8 is nodig vóór livegang, maar de maat wordt het hoofdoordeel, niet het aantal flags. De klant ziet alleen het hoofdoordeel. |
| 6 | 25 correcties | Veiligheidsnet alleen voor de regelbreuk. Na v1.8: hoogstens 5 procent van de scores gecorrigeerd, elke correctie gelogd. |

Twee dingen gaan vóór v1.8, omdat ze bepalen of de app een klant beschermt tijdens het schrijven:

- de hertest van CE-04 met de parserfix (post 3, drie merken);
- de verleidingstest via Snel artikel (Black Friday bij Brandpunt, en de twee andere thema's uit ronde 1).

**Hoe je dit gebruikt:** geef alles vanaf "Opdracht voor Cursor" aan Cursor. Opus 5 voor V2 (promptwerk), Composer 2.5 voor de rest. Stop en commit na elke stap.

---

## Opdracht voor Cursor: context

Dit is ronde 4, promptversie **v1.8**, na `hba-kalibratie-beslispunten-roger-v1.8.md` en de meting van 10 oktober (v1.7, 5×17, 9 stable goed). Model niet wijzigen: `claude-sonnet-4-6`. Alle tekst hieronder komt in de bestaande promptfragmenten (`web/api/prompts/hba-*.txt`), zodat hij straks naar de configuratieopslag van de kalibratiemodule kan.

Volgorde: V0, V1, V2, V3, V4, V5, dan het testprotocol.

## V0. Eerst de twee open tests (Composer 2.5)

1. **CE-04 hertest.** Draai `./scripts/run-hba-r0-ce04-post3-staging.sh 60` na deploy. Vul de tabel in `docs/hba-testmerken-ronde3-r0.md` in: per merk of de feedbackregel de regel noemt, met citaat.
2. **Verleidingstest.** Gebruik de twee Black Friday-slots bij Brandpunt (`1f856a88-fc18-47a2-81be-3d335358066c`, `d733223a-7964-4324-9883-dcd4b1809006`) en maak via Snel artikel de thema's "Nieuw partnerschap met een ERP-leverancier" (Stelwerk) en "Wachtlijst in één maand weggewerkt" (Duinroos), met drie bronlinks per thema. Leg per merk vast:
    - of de bronnen blijven staan (A3);
    - of CE-03 schrijft;
    - of de tekst de regel breekt;
    - of de zelfcontrole en de HBA-hint dat melden;
    - welk hoofdoordeel de scorer geeft.

Lukt een van beide niet, meld waarom en ga door met V1.

## V1. PHP-correctie van het hoofdoordeel beperken (Composer 2.5)

Vervang de huidige correctie (hoofdoordeel naar de flag met de hoogste voorrang) door deze regels:

1. Is `constraint_breach` aanwezig, dan is het hoofdoordeel altijd `regelbreuk`.
2. Kiest het model `regelbreuk`, `tegenkracht` of `categorietaal` zonder de bijbehorende flag (`constraint_breach`, `foe_vervaging`, `category_fallback`), dan is dat ongeldig. Kies dan de hoogste geldige waarde op basis van de aanwezige flags in de volgorde tegenkracht, categorietaal. Is er geen passende flag, gebruik `neutraal`.
3. Kiest het model `op_merk` of `neutraal`, dan blijft dat staan, ook als er een `category_fallback`, `foe_vervaging` of `proof_gap` op een losse zin staat. Die flags blijven intern zichtbaar in de matrix.

Elke correctie blijft gelogd in `hoofdoordeel_gecorrigeerd`, met de oorspronkelijke waarde van het model.

## V2. Definities aanscherpen (Opus 5)

**Nieuw fragment, vóór de flags.** Zet dit bovenaan `hba-flags-definitie.txt`:

```
FUNCTIONELE ONDERDELEN: een oproep tot contact of actie, een datum, een tijd, een
link, een adres, een prijs zonder claim of andere praktische informatie beoordeel
je niet. Ze krijgen geen flag en tellen niet mee in het hoofdoordeel. Beoordeel
alleen wat de tekst over het merk beweert, belooft of laat zien.
```

**`foe_vervaging` vervangen door:**

```
foe_vervaging: de tekst neemt de specifieke redenering over waar dit merk tegen
vecht (de tegenkracht: {foe}). Noem de zin en leg in één zin uit welk deel van de
tegenkracht die zin overneemt. Kun je dat niet uitleggen, dan is het geen
foe_vervaging. Een algemene claim over kwaliteit, zorg, populariteit of
betrouwbaarheid is geen tegenkracht; dat is hoogstens categorietaal.
```

Vul `{foe}` met het veld `foe` van het merk. Sla de uitleg op in `flag_citaten` naast het citaat, als `foe_vervaging_uitleg`.

**`proof_gap` aanvullen.** Voeg na "Een concreet voorval, een naam, een datum of een beschreven handeling is zelf bewijs." toe:

```
Dat geldt ook voor een beschreven werkwijze, timing of processtap van het merk,
zoals "over twee weken in de winkel, zodra hij is uitgerust" of "dus komt het team
om tien uur". Meld proof_gap alleen bij een bewering over een uitkomst, kwaliteit
of cijfer die de tekst niet onderbouwt.
```

**Hoofdoordeel aanvullen.** Voeg aan `hba-hoofdoordeel-definitie.txt` toe:

```
Een post met de rol service, educatie, werving of aankondiging, zonder regelbreuk
en zonder tegenkracht, is neutraal, ook als er een gangbare formulering in staat.
Functionele onderdelen tellen niet mee.
```

`CE_HBA_PROMPT_VERSION` wordt `v1.8`.

## V3. Matrix aanpassen (Composer 2.5)

1. Het oordeel per post rust op het hoofdoordeel. Flags tellen alleen nog als fout bij een `verboden_flags`-treffer of een ontbrekende `verwacht_flags`.
2. Een `proof_gap` op een post met verwacht hoofdoordeel `op_merk` maakt de post niet meer fout. Rapporteer hem wel.
3. Brandpunt post 6 blijft review.
4. Nieuw in het rapport, per run:
    - het aantal scores met `hoofdoordeel_gecorrigeerd`, als percentage van alle scores;
    - elke flag waarvan het citaat een functioneel onderdeel is (oproep tot contact, datum, link). Dat hoort na V2 nul te zijn.

## V4. Klanttaal en contentrollen (Composer 2.5)

**A6.** Ga verder met de inventaris uit `a6-klanttaal-inventaris-voorstel.md`, met deze aanpassingen:

- Testnotities in het archief ("Verwacht: …") verbergen voor alle klantrollen, niet alleen voor contact en reviewer.
- Overige rijen: zet de voorstellen klaar op staging, en leg ze met screenshots aan Rogier voor vóór productie.

**A8.** Verberg de HBA-contentrol (positionering, bewijs, activatie en zo verder) voor de klant, tot de kalibratiemodule één indeling heeft. De klant ziet alleen de rol uit Planning.

## V5. Wat de klant te zien krijgt (Composer 2.5, prompt met Opus 5)

Bekeken op 10 oktober bij Duinroos, via `GET /api/articles/<slotId>`.

**1. De preview-links werken niet.** `/article/<articleId>?hbaKlant=1` geeft "Article not found" (`/api/articles/<articleId>` → 404). Met het slot-id stuurt de app door naar het archief, en daar staat geen HBA-oordeel. Zorg dat de klantweergave voor elke gescoorde post te openen is, ook als de post al gepubliceerd of gearchiveerd is, en lever de werkende links opnieuw aan.

**2. Het verbeteradvies gebruikt vaktermen.** Het hoofdoordeel en de klantzin zijn goed, maar het advies eronder zegt bijvoorbeeld:

- "die het mechanisme zichtbaar maakt en de tegenkracht scherper neerzet";
- "Dat is het bewijs dat de geloofwaardigheidstest vraagt";
- "een structurele keuze die het merk van de branchenorm onderscheidt";
- "een bewijs van de merklogica".

Voeg aan de prompt voor `verbeteradvies` toe:

```
Schrijf het advies in de taal van de klant. Gebruik nooit de woorden mechanisme,
tegenkracht, geloofwaardigheidstest, archetype, merklogica, branchenorm,
categorietaal of hoofdoordeel. Noem in plaats daarvan wat het merk concreet doet
(de werkwijze) of waar het nee tegen zegt (de regel), in de woorden van het merk
zelf.
```

Neem de verboden woorden ook op in de controle tegen de verbodslijst (kalibratiemodule, taalcontrole), zodat een advies met zo'n woord gelogd wordt.

**3. Geen advies op een post die al goed is.** Bij "Op merk" (meneer De Wit) stelt het advies voor een zin toe te voegen. Een post met hoofdoordeel `op_merk` krijgt hoogstens één korte suggestie, of geen. Zie ijkgeval S03 in de ijkset ("Advies op al sterke tekst").

**4. Het advies moet de regel juist weergeven.** Bij Duinroos stelt het advies voor "En het zijn altijd dezelfde gezichten aan de deur". De regel is hoogstens vier verschillende zorgverleners per maand, niet altijd dezelfde. Neem het fragment over de regel uit D2 ook op in de prompt voor het verbeteradvies.

**5. Zichtbaar effect van V1.** Twee Duinroos-posts kregen door de PHP-correctie een verkeerde klantzin:

| Post | Model | Na correctie | Klant ziet |
| --- | --- | --- | --- |
| Vacature Heemstede | neutraal | categorietaal | "Dit kan iedere concurrent zo zeggen." |
| Bloemen voor mevrouw Jansen | categorietaal | tegenkracht | "Hier redeneer je zoals je tegenstander." |

Na V1 hoort de vacature neutraal te zijn en de bloemenpost categorietaal. Controleer dat in de klantweergave.

## Testprotocol en klaar-criteria

Draai na V1 tot en met V3 opnieuw 5 runs × 17 posts. Leg alles vast in `docs/hba-testmerken-stabiliteit.md`, met datum, model-id en promptversie.

**Klaar als:**

| Criterium | Doel |
| --- | --- |
| Post 3, alle merken | `regelbreuk` in 5/5 |
| Post 2, alle merken (inclusief Stelwerk) | `categorietaal` in 5/5 |
| Post 1, alle merken | `op_merk` in 5/5 |
| Post 4, alle merken | `neutraal` in 5/5 |
| Brandpunt post 5, Duinroos post 6 | zoals in v1.7, 5/5 |
| Alle niet-review posts samen | hoofdoordeel goed in minstens 80 procent van alle runs |
| `hoofdoordeel_gecorrigeerd` | hoogstens 5 procent van de scores |
| Flags op functionele onderdelen | 0 |
| Verbeteradvies met een verboden woord (V5) | 0 |

Haalt een criterium het niet, pas dan de prompt niet zelf verder aan. Lever een beslispakket aan Rogier, zoals bij v1.7.

## Afronden

- Draai `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap (V0 tot en met V5).
- Deploy naar staging pas na Eriks "ja". Niet naar productie.
- Verslag aan Erik met:
    - de uitkomst van V0 (CE-04 en de verleidingstest per merk);
    - de stabiliteitstabel v1.7 naast v1.8, met de klaar-criteria afgevinkt;
    - de lijst correcties met de oorspronkelijke waarde van het model;
    - de screenshots van A6 en A8;
    - werkende links naar de klantweergave van alle zeventien posts, en screenshots daarvan (V5).
