# v1.13 — runs 1–2, vier categorietaal-posts (tussentijds voor Rogier)

**Datum:** 21 oktober 2026  
**Prompt:** v1.13 op staging  
**Bron-run:** `docs/hba-v1.13-stap5-5x19-run.log` (afgebroken in run 3 @ Brandpunt post 5, Anthropic-tegoed)

## Beperking log

Het stabiliteitsscript logt alleen voortgang (`Run N/5`, `Merk post M…`), geen hoofdoordeel of flags per post. De JSON-export (`hba-testmerken-stability-v1.13-5runs.json`) wordt pas aan het einde geschreven — die bestaat **niet**. Daardoor zijn **per-run uitkomsten voor run 1 en 2 niet meer te reconstrueren** uit het logbestand.

Onderstaande tabel is **niet** “run 1 vs run 2”, maar de **laatste enige score per post** op staging (één matrix-snapshot, 21 okt 2026), na import + (deels) stabiliteitsruns. Brandpunt post 5 heeft **geen** score meer door de afgebroken run 3.

## Vier categorietaal-posts — snapshot staging (v1.13)

| Merk | Post | Hoofdoordeel | Flags | Tegen `tegenkracht`? |
| --- | --- | --- | --- | --- |
| Brandpunt Koffie | Categorietaal | `categorietaal` | category_fallback, proof_gap | nee |
| Duinroos Thuiszorg | Categorietaal | `categorietaal` | category_fallback, proof_gap | nee |
| Duinroos Thuiszorg | Archetype zonder bewijs | `categorietaal` | category_fallback, proof_gap | nee |
| Stelwerk | Branchetaal uit de verboden logica | `categorietaal` | category_fallback, proof_gap | nee |

**Lezing tussentijds:** op deze enkelvoudige meting scoren alle vier **niet** als `tegenkracht`; hoofdoordeel is overal `categorietaal` (Stelwerk branchetaal-post included). Dat sluit aan bij de v1.13-definitie rond categorietaal, maar **vervangt geen** volledige 5×19 of run 1/2-vergelijking.

## Na credit-top-up

Volledige `./scripts/run-hba-testmerken-matrix.sh --runs 5` opnieuw (zelfde code/prompt), daarna replay 1b — zie `briefing/cursor-reactie-rogier-ronde-7-uitvoeren.md` stap 4.
