# Besluiten Rogier (terugkoppeling ronde 6) — ingebouwd in v1.12

Bron besluiten: `briefing/hba-input/terugkoppeling-rogier-ronde-6-ingevuld.md` (sectie 3 en 4).  
Uitvoering: `docs/hba-v1.12-ronde6-besluiten-uitvoering.md` · prompt **v1.12**.

## Punt 3 — neutraal vs categorietaal (methodiek → code)

| Besluit Rogier | In v1.12 gebouwd | Waar / opmerking |
| --- | --- | --- |
| Nieuw veld `merkbewering` ja/nee | Ja | Model + raw score; PHP-treden |
| Volgorde: regelbreuk → tegenkracht (foe kop) → **merkbewering=nee → op_merk/neutraal** → categorietaal → op_merk/neutraal | Ja | `ce_hba_resolve_hoofdoordeel` (5 treden) |
| `kern_categorietaal` alleen als `merkbewering=ja` | Ja | Zelfde resolver |
| Log `merkbewering` en `kern_categorietaal` per score | Ja | Export stability/matrix (na fix ook in GET-matrix) |
| Trede-2-voorwaarde `kern_categorietaal=nee` verwijderen | Ja | Was niet in §3; **verwijderd** — effect o.a. Stelwerk post 5 nu **5/5** |
| Testset: Brandpunt markthygiëne, Stelwerk samenwerking expliciet | Ja | Geen `ambigu` meer op die posts |

### Verwachtingen reviewposts (Rogier) vs 5×19 poging 2

| Post | Verwachting Rogier | Uitkomst v1.12 (5×19) |
| --- | --- | --- |
| Brandpunt *Mechanisme dat markthygiëne is* | `categorietaal` | **5/5 categorietaal** |
| Stelwerk *Samenwerking zonder bewijs* (post 5) | `op_merk` (+ evt. `proof_gap` advies) | **5/5 op_merk** |
| Duinroos *Kernbewering zonder bewijs* (post 7) | `tegenkracht`, merkbijdrage ≤ 5 | **2/5** — 3× `op_merk`, 2× `tegenkracht` (instabiel) |

**Pilotdrempel:** ≥ 16/19 stable goed — **uitkomst 13/19**. Geen derde 5×19 (besluit Rogier).

## Punt 4 — A6 klanttaal (ronde 6)

| Restpunt | Status v1.12 / ronde 2 |
| --- | --- |
| Doelgroep-labels nl | Gebouwd (stap 6 ronde 6) |
| Akkoord \| Verwijderen | Gebouwd |
| Agenda nl-NL | **A6 ronde 2 (20 okt)** — staging + commit |
| Ritme Overzicht | Gebouwd |
| Rapport zonder (HBA v1.7) | Gebouwd |
| Hashtags in score + CE-03 | Gebouwd; **bewijs ronde 2 punt C** |

## Niet in v1.12 (bewust of later)

| Onderwerp | Status |
| --- | --- |
| `geen_bron` klantbevestiging (besluit §2) | **Wel gebouwd** na ronde 6 (`geen_bron` override UI/API) — zie `docs/hba-v1.12-stap3-geen-bron-override.md` |
| Flagtelling Implementation Kit | Open — bijlage in deze map |
| Valkuil-prioriteit go-live | Open — bijlage in deze map |
