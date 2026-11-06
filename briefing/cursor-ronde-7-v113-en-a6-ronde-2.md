# Cursor-briefing — ronde 7: v1.13-reparatie, A6 ronde 2 en het complete stopbericht

**Eigenaar:** Erik. **Datum:** 20 oktober 2026.
**Bron:** `hba-besluiten-stopbericht-ronde-6-2026-10-20-1` en `hba-a6-restpunten-ronde-2-2026-10-20-1` (Rogier).
**Vervangt als werkopdracht:** `briefing/cursor-ronde-6-afronden-en-stoppen.md`, behalve stap 3b (de twee productiebevindingen); die blijft staan en loopt door.
**Model:** Opus 5 voor spoor 1 (promptwerk), Composer 2.5 voor spoor 2 en 3.

## De uitspraak in één regel

**De pilot start niet.** Eerst v1.13. Rogier wijst één oorzaak aan voor vijf van de zes instabiele posts: `foe_vervaging` vuurt te ruim, waardoor gewone branchetaal als Positiebreuk bij de klant landt. Dat is de reparatie van deze ronde.

Er zijn drie sporen. **Spoor 1 is het kritieke pad en blokkeert de pilot. Spoor 2 en 3 lopen parallel en raken de scoring niet.**

---

# Spoor 1 — v1.13: `foe_vervaging` aanscherpen

### 1a. De flagdefinitie

In `web/api/prompts/hba-flags-definitie.txt`: `foe_vervaging` valt alleen als de tekst het gedrag van de tegenkracht **doet of aanprijst**.

Uitdrukkelijk géén `foe_vervaging` bij:

- categorietaal op zichzelf (dat is `category_fallback`);
- een ontbrekende onderbouwing (dat is `proof_gap`);
- het weglaten van het eigen mechanisme of de eigen regel;
- een vraag of een retorische opening;
- een tekst die zich juist tégen de tegenkracht keert.

Neem voorbeelden op van allebei: een geval waar de flag wél valt en een geval waar hij niet valt. Gebruik daarvoor de posts uit de v1.12-run die nu onterecht `tegenkracht` kregen — Duinroos archetype, Duinroos categorietaal, Stelwerk branchetaal — zodat de definitie tegen echte tekst is geijkt en niet tegen een bedacht voorbeeld.

Deze wijziging telt mee als werk aan de as **signaal** (zie 2 hieronder).

### 1b. Trede 2 blijft zoals hij is

**Zet `&& !kern_categorietaal` níét terug.** Rogier beslist dat op getallen, niet op redenering.

Na de 5 × 19 van 1e draai je de replay die al in de vorige briefing stond: leid per score het hoofdoordeel opnieuw af uit de opgeslagen flags, één keer zoals de code is en één keer met de voorwaarde terug in trede 2. Geen modelcalls, geen staging. Lever beide uitslagen naast elkaar, met het aantal stabiel goede posts per variant.

### 1c en 1d. Twee verwachtingen bijstellen

| Post | Nieuwe verwachting | Let op |
| --- | --- | --- |
| Duinroos — kernbewering zonder bewijs (post 7) | **`op_merk`** met `proof_gap` | Dit draait besluit 5 van 19 oktober terug, waar `tegenkracht` stond. Bewust; niet "corrigeren" naar de oude waarde. |
| Stelwerk — branchetaal uit de verboden logica | **`categorietaal`** met `proof_gap` | Ongewijzigd ten opzichte van de ijkset, nu expliciet bevestigd. |

Leg beide vast in de testmerken vóór de run.

### 1e. Eén derde 5 × 19 — en dat is de laatste

Promptversie naar **v1.13**. Vóór de run moet de logging kloppen: `merkbewering` en `kern_categorietaal` staan in de export, per score. Dat ging de vorige keer mis en dat mag nu niet.

**De pilot start als alle drie waar zijn:**

1. minstens 16 van de 19 posts 5 van 5 goed;
2. **geen** van de vier categorietaal-posts krijgt in **enige** run `tegenkracht`;
3. de spreiding in merkbijdrage is overal hoogstens 1.

Voorwaarde 2 is nieuw en strenger dan stabiliteit alleen: één enkele uitschieter in één run is al een afkeuring.

**Haalt v1.13 het niet: stoppen.** Geen vierde poging, geen prompttuning, ruwe output naar Rogier.

---

# Spoor 2 — A6 ronde 2 (parallel, geen scoring)

Rogier is akkoord op restpunt 1, 2, 4 en 5. Drie staan open.

### A. Agenda en datums in het Nederlands

Alle datums `nl-NL`, geen Engelse maanden of dagen, op **alle** klantschermen: Agenda, Kalender, Overzicht, Rapport, Archief en Planning.

- kop: "Agenda — 19 oktober 2026";
- weekbereik: "5 – 11 oktober" en, over een maandgrens, "26 oktober – 1 november".

**Klaar als:** geen Engelse maand- of dagnaam meer op een klantscherm, met screenshots van zowel de lijst- als de kalenderweergave.

### B. CE-04 geeft de regel letterlijk

Vast formaat voor de regelfeedback:

```
Dit stuk gaat in tegen je regel: "<letterlijk uit Jullie regels>". <uitleg>
```

De regel wordt letterlijk uit het veld overgenomen — geen parafrase, geen afgeleide grens. Gebruik **dezelfde controle als bij de hint in stap 2a**: komt de geciteerde regel niet letterlijk voor in de geloofwaardigheidstest, dan één keer opnieuw, of vul hem rechtstreeks uit het veld in. Alleen de gebroken regel wordt genoemd, niet de hele lijst.

**Klaar als:** 3 van de 3 runs goed bij Brandpunt, Duinroos en Stelwerk, met JSON en een screenshot.

### C. Hashtagcontrole aantonen

De hashtagcontrole is gebouwd; Rogier wil het bewijs. Lever een JSON-fragment met het citaat waaruit blijkt dat `#versgebrand` onder een Brandpunt-post wordt opgepikt, in de scoring of in CE-03.

**Klaar als:** het fragment met citaat er ligt.

---

# Spoor 3 — het complete stopbericht- en artefactenpakket

Rogier vraagt hier twee keer om: als punt D van het A6-document en als §3 van de besluiten. Het is één levering. Hij wil dit vóór het pilotbesluit.

Samenstellen in `docs/rogier-pakket-ronde-7-<datum>/`:

1. **de tabel per post** — alle 19, met per post de vijf runs, het oordeel, de flags en de verwachting;
2. **de ruwe JSON** van de v1.12-run, aangevuld met `merkbewering` en `kern_categorietaal` waar die nu ontbreken; lukt dat niet uit de opgeslagen raw, meld dat dan expliciet in plaats van het stil te laten;
3. **de mapping besluit → bouw**: per besluit van 19 oktober waar het in v1.12 terechtgekomen is, met bestand en functie;
4. **`hba-a6-ronde2-stap-A-B.md` en `-C-`**, die in de vorige zip ontbraken;
5. **de nieuwe screenshots van 20 oktober**: agenda, CE-04 met de letterlijke regel, hashtag.

Ik schrijf de begeleidende tekst; lever jij de artefacten en meld wanneer ze compleet zijn.

---

# Afgesloten punten — niet opnieuw openen

- **Valkuilen:** de volgorde is signaal (15) → oordeel (8) → meetlat (3). Punt 1a telt als werk aan signaal.
- **Flagtelling:** de Implementation Kit is leidend. Tellen doe je over **patroonposts**, dus posts waarvan het hoofdoordeel niet `op_merk` is. Flags op een `op_merk`-post zijn advies, geen patroon. Dit moet in rapport en agenda zitten vóór het eerste klantrapport, niet per se vóór de pilotstart.

---

# De opruimronde met Fable — nog niet beginnen

Akkoord van Rogier, onder vier voorwaarden. Die zijn bindend.

1. **Eerst spoor 1 af.** Geen opruiming midden in een scoringswijziging.
2. **Aparte branch**, met de scoring bevroren: `hba-prompt.php`, `ce_hba_resolve_hoofdoordeel()` en alles onder `web/api/prompts/` worden in die ronde niet inhoudelijk gewijzigd.
3. **Bewijs vóór de merge:** een offline replay van de 95 scores moet identieke uitkomsten geven, plus een geslaagde smoke. Pas dan mergen.
4. **Eerst de lijst, dan de wijzigingen.** Doel is dode code, dubbele regels, Engelse klanttekst, secrets en ongefilterde klanttekst. Lever die lijst ter beoordeling voordat er iets verandert.

---

# Wat er niet gebeurt

- Geen pilotstart voordat v1.13 alle drie de criteria haalt.
- Geen vierde 5 × 19.
- Trede 2 blijft ongewijzigd tot Rogier op de replaycijfers heeft beslist.
- Geen scoringswijziging in spoor 2 of 3; die twee sporen raken weergave, CE-04 en documentatie.

## Afronden

- `php -l`, `py_compile` en `npm run build`.
- Eén commit per genummerd punt, met het nummer in de message.
- Geen staging zonder een expliciete "ja staging" van mij. Niets op productie.
- Verslag aan Erik per punt: klaar / gewijzigd / waar je een keuze moest maken.
