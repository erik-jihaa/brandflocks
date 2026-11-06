# HBA — stap 3: verbodslijst op architectuur-output (§5.3)

## Inventarisatie (eerst)

**Vraag:** ziet de klant de toelichting per dimensie?

**Antwoord: ja.** `HbaArchitectureCheckPanel` → `DimensionBlock` toont `toelichting` en (bij aanscherpen) `verbetering` letterlijk. Plus `toonSuggesties` en `voorbeeldSuggesties`.

Daarmee was de ijkrun-fix van run 3 (alleen klantsuggesties) **te smal**. Methodiektermen in dimensie-tekst zijn ook fout.

## Gedaan

- **`web/api/hba-architecture-verbodslijst.php`** — scan, retry-correctie, strip na 2e poging, `ce_log_event` (`hba` / `architectuur_verbodslijst`).
- **`hba-architectuur-check-repository.php`** — filter op hard + soft na apply_poort; retry beide LLM-calls; strip i.p.v. blokkeren.
- **Prompts** — expliciete verbodslijst in hard- en soft-prompt; geen “HBA-strateeg” meer in modeltekst.
- **Ijkrun-judge** — methodische term geldt ook voor dimensie-toelichting/verbetering.

**Niet in lijst (bewust):** `tegenkracht` — staat al als wizard-veldlabel; hernoemen = stap 4.

## Klaar als

- Geen klantzichtbare suggestie/toelichting met verboden term (na strip).
- Elke eerste-treffer in `event_log` (`architectuur_verbodslijst`), met `stripped` als de 2e poging faalde.
