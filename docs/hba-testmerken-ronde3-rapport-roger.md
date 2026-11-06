# HBA testmerken — ronde 3 rapport voor Rogier

**Datum meting:** 10 oktober 2026  
**Omgeving:** staging (`https://staging.igisnext.com`)  
**Model:** `claude-sonnet-4-6`  
**Promptversie:** `v1.7`

## Waarom ronde 3

- Eén **hoofdoordeel** per post (klantzin), flags blijven intern.
- **`category_fallback`** alleen als kern = categorietaal; Brandpunt branche **retail** + hygiëne *branddatum op de verpakking*.
- Matrix **vergelijkt niet meer met post 1**; `verwacht_niveau: laag` → distinctiviteit max 2.
- **Vijf runs**; alleen posts die **5/5 goed** zijn tellen als stabiel goed.
- Brandpunt post 1 herschreven; oude tekst = **post 6** (17 posts totaal).

## Wat is gebouwd (samenvatting)

| Onderdeel | Status |
| --- | --- |
| Hoofdoordeel in score + PHP-validatie + matrix | live op staging |
| Testposts + `verwacht_hoofdoordeel` | 17 posts geïmporteerd |
| Klantpreview artikel | staging, query **`?hbaKlant=1`** (flags verborgen) |
| Stabiliteitsscript | `./scripts/run-hba-testmerken-matrix.sh` (standaard 5 runs) |

Commits ronde 3: `90cba73` … `1a0a415` (+ stabiliteitsmeting in latere commit).

## Resultaat 5×17 stabiliteit

**Laatste run:** 10 okt 2026, 17:50 UTC — na volledige verboden logica (A2) + staging deploy.

| Metric | Waarde |
| --- | ---: |
| Stable goed (5/5) | **9** |
| Instabiel | **3** |
| Fout | **2** |
| Review | **3** |
| `category_fallback` laatste run | **10 / 17** (was **13 / 16** v1.6 run B) |
| `hoofdoordeel_gecorrigeerd` (debug) | **25** over alle runs |

**BC-spreiding ≤ 1:** gehaald (alle niet-review posts).

### Klaar-criteria briefing (alle 5 runs)

- [x] Post 3 — alle merken → `regelbreuk`
- [x] Post 2 — Brandpunt, Duinroos → `categorietaal`, geen `constraint_breach`
- [ ] Post 2 — **Stelwerk** → model houdt **tegenkracht** als hoofdoordeel (5/5), wel flags `category_fallback`
- [x] Post 1 — alle merken → `op_merk`, geen `category_fallback` (matrix-criterium; sommige runs extra `proof_gap`)
- [x] Brandpunt post 5 → `op_merk`
- [x] Duinroos post 6 → `tegenkracht`

### Sterke punten

- Regelbreuk-posts (post 3) en Duinroos post 6 **stabiel**.
- Brandpunt post 2 categorietaal **5/5**.
- Minder brede `category_fallback` dan v1.6, maar nog **10/17**.

### Aandachtspunten voor jouw besluit

Uitgewerkt met opties A/B/C: **`docs/hba-kalibratie-beslispunten-roger-v1.8.md`** (beslispakket na matrix-missers).

1. **Stelwerk post 2** — voorrang **tegenkracht** vóór **categorietaal** + `foe_vervaging` op CTA.
2. **Neutraal-posts** (Brandpunt/Duinroos/Stelwerk post 4) — `category_fallback` vs verwacht **neutraal**.
3. **Brandpunt post 6** — ambigu; split categorietaal/op_merk + `proof_gap`.
4. **`proof_gap`** Brandpunt post 1 — 2/5 fout bij stabiel `op_merk`.
5. **`hoofdoordeel_gecorrigeerd` 25×** — acceptabel of v1.8 prompt scherper?

Geen prompt-tuning na meting (conform briefing). **v1.8 pas na jouw keuzes.**

## Klantweergave (R5)

Op staging, ingelogd op testmerk-artikel:

`https://staging.igisnext.com/…/article/…?hbaKlant=1`

Toont één klantzin + eventueel verbeteradvies; geen flagnamen. **Screenshots:** nog door Erik (17 posts).

## Nog open (Erik)

- **CE-04** post 3 opnieuw triggeren → feedbackregel vastleggen (`docs/hba-testmerken-ronde3-r0.md`).
- Productie: **niet** deployen; hoofdoordeel-preview blijft staging.

## Bestanden in dit pakket

Zie `docs/rogier-hba-testmerken-pakket/README.md` — o.a. `stabiliteit-v1.7-5runs.json`, bijgewerkte test-JSON (17 posts), prompts v1.7.

## Voorgestelde vervolgstappen

1. Jij: **`hba-kalibratie-beslispunten-roger-v1.8.md`** (6 beslispunten) + citaten in `stabiliteit-v1.7.md`.
2. Erik: v1.8 prompt/matrix alleen naar jouw antwoord; daarna opnieuw 5×17.
3. Na akkoord: klantweergave hoofdoordeel productieklaar maken (feature flag).
4. Optioneel: Tony’s referentiemerk / kalibratiemodule koppelen aan gedeelde promptfragmenten.
