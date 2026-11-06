# Claude — voorgestelde prompts voor Rogier

## 1. Validatie testposts (kern)

Plak in Claude:

1. De drie JSON-bestanden (`Brandpunt_Koffie.json`, `Stelwerk.json`, `Duinroos_Thuiszorg.json`)
2. `matrix-v1.5-na-s2.json` (werkelijke app-scores)
3. `LEESMIJ-testmerken.md`

**Prompt:**

> Je bent de auteur van de HBA-testmerken. Per merk zijn vier posts gedefinieerd met een veld `verwacht`.  
> Vergelijk per post de verwachting met de werkelijke scores in de matrix (brandContribution, distinctiviteit, contentrol, flags, judgment).  
> Focus op post 2 (categorietaal) en post 3 (regelbreuk).  
> Geef per afwijking: was de app te streng, te mild, of is de verwachting ambigu?  
> Gebruik geen code — alleen de JSON.

## 2. Stabiliteit (proof_gap op post 1)

Plak `matrix-v1.3-nulmeting.json`, `matrix-v1.5-na-s2.json` en de sectie stabiliteit uit `hba-testmerken-resultaat.md`.

**Prompt:**

> In v1.3 scoorde post 1 (*Op merk*) bij Brandpunt en Stelwerk goed zonder proof_gap; in v1.5 krijgen alle drie de merken proof_gap.  
> Is dat een redelijke interpretatie van “op merk zonder risicoflags”, of moeten we de scoreprompt aanpassen?  
> Geef een korte aanbeveling.

## 3. Duinroos / Verzorger = branche

Plak S4-sectie uit `hba-testmerken-resultaat.md` + Duinroos JSON (merk + testposts).

**Prompt:**

> Het merkarchetype valt samen met het branche-archetype. Rogier verwacht zwaardere bewijslast.  
> De app doet dat volgens S4 niet expliciet. Welke concrete prompt- of productregel zou jij toevoegen — zonder generieke “wees strenger”?

## 4. Regelbreuk-definitie

Plak `hba-regelbreuk-definitie.txt` + post 3 uit elke JSON + matrix flags voor post 3.

**Prompt:**

> Klopt de definitie van regelbreuk met wat jij bedoelde bij post 3? Zou constraint_breach op post 2 (categorietaal) soms ten onrechte meekomen?
