# HBA ronde 6 — stap 1: hoofdoordeel in de code (v1.11)

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 1  
Branch: `hba-ronde-6-pilotklaar`  
Prompt: **`v1.11`** · model scoring blijft **`claude-sonnet-4-6`**

## Wat erin zit

Het model levert **geen hoofdoordeel** meer. Het levert flags met citaten en `kern_categorietaal` (ja/nee). PHP bepaalt het oordeel.

| Stap | Voorwaarde |
| --- | --- |
| 1 `regelbreuk` | `constraint_breach` nog aanwezig ná controlecall |
| 2 `tegenkracht` | `foe_vervaging` + citaat op kop/openingszin + `kern_categorietaal` = nee |
| 3 `categorietaal` | `kern_categorietaal` = ja (ook bij foe in een bijzin) |
| 4 `op_merk` / `neutraal` | neutrale contentrol (service, educatie, employer_culture, activatie, reactief) én merkbijdrage **&lt; 6** → `neutraal`; anders `op_merk` |

**Keuze (grens 6):** de briefing zegt “dezelfde grens als nu”; numeriek stond die alleen in de merkbijdrage-caps. Vastgelegd in `ce_hba_op_merk_of_neutraal()`.

**Foe-definitie:** tekst die zich tégen de tegenkracht keert krijgt geen `foe_vervaging` (Brandpunt Black Friday-voorbeeld in `hba-flags-definitie.txt`). PHP stript die flag als het citaat de bestaande afzet-heuristiek raakt.

**Meting:** `hoofdoordeel_gecorrigeerd` telt niet meer. Nieuw: `hoofdoordeelVolgtUitFlagsPercent` (doel **100%**).

## Bestanden

- `web/api/prompts/hba-hoofdoordeel-definitie.txt` — model levert geen oordeel
- `web/api/prompts/hba-flags-definitie.txt` — geen foe bij afzetten
- `web/api/prompts/hba-verbeteradvies-definitie.txt` — advies op flags, niet op hoofdoordeel
- `web/api/hba-prompt.php` — schema + `ce_hba_resolve_hoofdoordeel`
- `web/api/hba-repository.php` / `hba-baseline-repository.php` / `hba-testmerken-repository.php`
- `scripts/test-hba-hoofdoordeel-v1.11.php`

## Klaar-criteria stap 1 (commit + deploy)

- [x] Code: model geen `hoofdoordeel`; PHP-volgorde; v1.11; meting `hoofdoordeelVolgtUitFlags`
- [x] Lokaal: `php scripts/test-hba-hoofdoordeel-v1.11.php`
- [x] Git: `a7f1213` op `hba-ronde-6-pilotklaar` (gepusht)
- [x] Staging: `./scripts/deploy-ftp.sh staging` — smoke **6 pass, 0 fail** (18 okt 2026)

**5×19 (16 stable, Stelwerk 5, Duinroos 7, …)** hoort bij **stap 9** — eindmeting op de pilotversie ná stap 1–5 (`briefing/hba-input/hba-ronde-6-pilotklaar.md`).

Rogiers tekst onder stap 1 noemt dezelfde getallen als *klaar als*; wij volgen Erik: **één commit per stap**, geen volledige 5×19 tot stap 9. Tussendoor mag staging nodig zijn voor stappen 2–4 (hints, CE-03, thema-advies).

**Ijkrun-uitsplitsing (stap 6)** draait ook pas op v1.11 ná stap 1–4, niet na alleen stap 1.
