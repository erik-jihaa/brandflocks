# HBA — stap 2: hoofdoordeel-volgorde en positiebreuk (§3)

**Prompt scoring:** `v1.10.7` (`CE_HBA_PROMPT_VERSION`) — definitie en PHP-filter delen dezelfde tekst.  
**Runtime-model:** `claude-sonnet-4-6` (ongewijzigd).

## Gedaan

- **`web/api/prompts/hba-hoofdoordeel-definitie.txt`** — canonieke volgorde: regelbreuk → tegenkracht (foe op kop/opening) → categorietaal → op_merk/neutraal. Tekst die zich afzet tegen de eigen tegenkracht is geen tegenkracht.
- **`ce_hba_resolve_hoofdoordeel` / `ce_hba_apply_hoofdoordeel_filter`** — één PHP-pad; context `foe_on_kern` + `keert_zich_tegen_tegenkracht`.
- **Positiebreuk** — alleen presentatie: `ce_hba_hoofdoordeel_klantlabel` / `hbaHoofdoordeelKlantlabel`. Intern blijft `tegenkracht`. Klantzin: “Deze tekst gaat in tegen waar je merk voor staat.”
- **Duinroos post 7** — zelfde verwachting als T13: `tegenkracht`, flag op de kop, merkbijdrage ≤ 5, niet meer `ambigu`.
- **Ijkrun T13** — `verwacht_hoofdoordeel` = `tegenkracht`.

## Controlepunt 5×19

Duinroos 7 vs Brandpunt-kalibratie van v1.10.6: zelfde filter. Als Brandpunt post 1 (regel later in de tekst) terugvalt, noteer dat in de 5×19 — niet in deze stap herstellen.

## Klaar als

| Check | Hoe |
| --- | --- |
| T10 → `categorietaal` | ijkrun `?execute=1&id=T10` |
| T13 = Duinroos 7 | beide `tegenkracht` + flag op kop + BC ≤ 5 |
| `hoofdoordeel_gecorrigeerd` ≤ 5% | 5×19-samenvatting (stap 5) |
