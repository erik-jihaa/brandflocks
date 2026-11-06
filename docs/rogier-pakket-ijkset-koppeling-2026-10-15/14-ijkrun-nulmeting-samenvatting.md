# HBA ijkrun — nulmeting v1.10.6 (samenvatting)

**Doel:** eerste betrouwbare nulmeting ijkset v0.2 op staging (Rogier koppeling 7.1–7.5).  
**Run:** her-run 3 (infra/judge fixes in commits `b3e357b`, `501e426`).  
**Tijdstip:** 2026-10-16 18:16:33 UTC  
**Omgeving:** staging (`staging.igisnext.com`)  
**Ijkset:** v0.2  
**Scoring prompt:** `v1.10.6` (`CE_HBA_PROMPT_VERSION`)  
**Model scoring:** `claude-sonnet-4-6` (staging modelconfig)

**Ruwe data:** `docs/hba-ijkrun-nulmeting-v1.10.6.json`  
**Log:** `docs/hba-ijkrun-nulmeting-v1.10.6.log`

Runs 1–2 zijn **niet** leidend (judge-bugs, wizard-daglimiet); alleen run 3 telt voor pilot-interpretatie.

---

## Telling (46 gevallen)

| Uitslag | Aantal | Toelichting |
| --- | ---: | --- |
| goed | 20 | Judge C (tekst) + `totaaloordeel` (architectuur) |
| fout | 11 | Zie valkuil + overige fouten |
| review | 6 | Schrijfadvies (7.5) + 2× architectuur signalen |
| niet gedraaid | 9 | C01–C08 (geen route) + V01 (CE-03 apart) |

**LLM-calls in run:** 37 (38 runnable minus V01).

---

## Go-live valkuil — drie getallen (18 gevallen in ijkset)

Rogier: valkuil telt voor go-live. **Geen enkel percentage** — vijf gevallen zijn **niet gedraaid** (A15, V01, C02, C03, C06). Zie `docs/hba-v1.10-ijkrun-rapport-roger.md`.

| Groep | Aantal | Toelichting |
| --- | ---: | --- |
| **Niet gedraaid** | **5** | A15 harde poort · V01 CE-03 · C02/C03/C06 geen route |
| **Gedraaid, goed** | **6** | A09, A10, A11, A12, T09, T12 |
| **Gedraaid, fout** | **5** | A13, T04, T05, T13, T14 |
| **Gedraaid, review** | **2** | S04, S05 |

*Herindeling run 3:* A15 stond in JSON als `fout` (“vul tegenkracht in”) → **`niet_gedraaid`** (scorer niet bereikt). Overige 13 valkuil-gevallen met route wel gedraaid.

| ID | V | Uitslag (herindeling) | Kort |
| --- | --- | --- | --- |
| A09–A12 | V | goed | o.a. leeg `regels` → bevinding, geen blokkade |
| A13 | V | fout | methodiek in klantsuggesties |
| A15 | V | **niet gedraaid** | harde poort; besluit §7.6 |
| T04, T05, T13, T14 | V | fout | scorer wel gedraaid |
| T09, T12 | V | goed | |
| S04, S05 | V | review | 7.5 handmatig |
| V01 | V | niet gedraaid | CE-03 |
| C02, C03, C06 | V | niet gedraaid | compressie-route |

---

## Per soort

| Soort | goed | fout | review | niet gedraaid |
| --- | ---: | ---: | ---: | ---: |
| architectuurcheck (18) | 11 | 5 | 2 | 0 |
| tekstscore (14) | 9 | 5 | 0 | 0 |
| schrijfadvies (5) | 0 | 1 | 4 | 0 |
| compressietest (8) | — | — | — | 8 |
| verleidingsthema (1) | — | — | — | 1 |

---

## Volledige matrix (run 3)

| ID | V | Uitslag | Verwacht (ijk) | Eerste reden / opmerking |
| --- | --- | --- | --- | --- |
| A01 | | goed | sterk | |
| A02 | | fout | sterk | methodische term in klantsuggesties |
| A03 | | goed | sterk | |
| A04 | | review | aanscherpen | signalen handmatig |
| A05 | | goed | aanscherpen | |
| A06 | | goed | zwak | |
| A07 | | goed | aanscherpen | |
| A08 | | goed | aanscherpen | |
| A09 | V | goed | zwak | |
| A10 | V | goed | zwak | |
| A11 | V | goed | zwak | |
| A12 | V | goed | zwak | |
| A13 | V | fout | zwak | methodische term in klantsuggesties |
| A14 | | review | aanscherpen | signalen handmatig |
| A15 | V | niet gedraaid | zwak | harde poort (lege tegenkracht); §7.6 |
| A16 | | fout | zwak | `meetlat_bruikbaar_met_waarschuwing` vs zwak |
| A17 | | fout | sterk | `identity_gate_blocked` |
| A18 | | goed | sterk | |
| T01 | | goed | sterk | |
| T02 | | goed | sterk | |
| T03 | | goed | neutraal | |
| T04 | V | fout | regelbreuk | tegenkracht; geen constraint_breach |
| T05 | V | fout | zwak | categorietaal vs op_merk |
| T06 | | goed | neutraal | |
| T07 | | goed | sterk | |
| T08 | | goed | neutraal | |
| T09 | V | goed | regelbreuk | |
| T10 | | fout | middelmatig | tegenkracht vs categorietaal |
| T11 | | goed | neutraal | |
| T12 | V | goed | tegenkracht | |
| T13 | V | fout | zwak | geen kern-flag op kop; BC 9 |
| T14 | V | fout | zwak | BC 6; cap/proof_gap op kop |
| S01 | | fout | signalen | “betrouwbare partner” in hint |
| S02 | | review | signalen | 7.5 handmatig |
| S03 | | review | signalen | 7.5 handmatig |
| S04 | V | review | signalen | 7.5 handmatig |
| S05 | V | review | signalen | 7.5 handmatig |
| C01–C08 | * | niet gedraaid | — | geen compressie-route |
| V01 | V | niet gedraaid | — | CE-03 apart |

*V = valkuil in ijkset.*

---

## Valkuil tekstscore — detail (T13/T14)

**T13 (Duinroos wachtlijst-kop):** hoofdoordeel `op_merk`, flags `[]`, merkbijdrage **9**. Ijk verwacht `proof_gap` of `foe_vervaging` op kop + lage BC. Waarschuwing: positionering 86 buiten band [20–50] (7.1).

**T14 (Stelwerk ervaring):** hoofdoordeel `op_merk`, flags `proof_gap`, merkbijdrage **6**. Ijk: BC ≤ 5; citaat proof_gap niet op kop/opening (review-waarschuwing).

---

## Thema’s voor Rogier / vervolg

1. **§7.6 A15** — merk zonder tegenkracht: weigeren vs inhoudelijk *zwak* (wizard + ijk); diagnose `docs/hba-a15-diagnose.md`.
2. **§7.7** — `regel_is_wettelijke_plicht` vs hygiëne op `regels`.
3. **Scorer vs ijk (T13/T14/T04/T05/T10)** — inhoudelijke valkuil-fouten waar de scorer wél draaide.
4. **Architectuur klantsuggesties (A02/A13)** — methodiek in voorbeeldteksten.
5. **Schrijfadvies** — review (7.5); S01 concrete fout.
6. **V01 + compressie** — eigen uitkomstbestand; C02/C03/C06 go-live open.

**Rapport Rogier:** `docs/hba-v1.10-ijkrun-rapport-roger.md` · briefing `briefing/cursor-ijkrun-a15-en-aparte-routes.md`.

---

## Runner / infra (stap 10)

| Item | Status |
| --- | --- |
| Endpoint | `GET /api/admin/hba-ijkrun` |
| Script | `scripts/run-hba-ijkrun-staging.py` |
| Sandbox | client *HBA Ijkrun sandbox*; wizard-cap uitgezonderd |
| Spec | `docs/hba-v1.10-stap10-ijkrun.md` |
