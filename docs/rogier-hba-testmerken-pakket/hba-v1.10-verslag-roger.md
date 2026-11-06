# HBA v1.10 — verslag voor Rogier (staging, pilot-deel A)

**Datum:** 13 oktober 2026  
**Omgeving:** https://staging.igisnext.com (niet productie)  
**Branch:** `hba-ronde-4-v1.9` (commits `532a3fc` … `6fc0208`)  
**Scoring-model:** `claude-sonnet-4-6`  
**Prompt scoring:** **v1.10** (schrijver/hints/CE-04 ongewijzigd model)  
**Opdracht:** `briefing/hba-input/hba-ronde-5-v1.10.md`

---

## Mail aan Rogier (concept)

Rogier,

**Deel A (pilot)** staat op staging: CE-04 levert weer regel + citaat op post 3, de verleidingstest schrijft geen valse premisses meer, en hints vragen om feiten via `[invulveld]` in plaats van ze te verzinnen. Open invulvelden blokkeren indienen/publiceren.

**Deel B** is gestart: scorer **v1.10** + 5×19 matrix. Geen achteruitgang op de 16 stabiele posts (16/16 × 5/5). De twee oude V0-foutteksten zitten nu als kalibratieposts in de set; daar is de scorer **nog niet groen** (Duinroos: nog geen `proof_gap` op de kop, merkbijdrage 8; Stelwerk: wel `proof_gap`, merkbijdrage 7 i.p.v. ≤6).

We wachten op jouw feedback: **mag de pilot starten op basis van deel A**, en **wil je dat we eerst stap 4 scorer afronden** of parallel stap 5 (testset) doen?

Start in het pakket: `LEESMIJ-ROGIER-v1.10.md`. Zip: `docs/rogier-hba-testmerken-pakket-2026-10-13.zip` (na `./scripts/build-rogier-hba-pakket.sh`).

Erik

---

## 1. Executive summary

| Onderdeel | Status |
| --- | --- |
| **Deel A — CE-04 post 3** (stap 1.1) | Groen — regel + citaat, geen “Review mislukt” |
| **Deel A — CE-03 premissecheck** (stap 2) | Groen — V0 verdedigt regel, geen valse samenwerking/wachtlijst weg |
| **Deel A — hints invulvelden** (stap 3) | Groen — `[…]` + bewijsveld; publish/submit geblokkeerd |
| **Deel B — scorer kernbewering** (stap 4) | Deels — geen regressie 16/16; kalibratieposts 6/7 nog review/fout |
| **Deel B — controlecall 6×5** (stap 1.2–4) | Groen in eerdere run (zie stap 1-doc); niet opnieuw in deze mail |
| **Stap 5–6** | Nog niet gestart — wacht op jouw prioriteit |

---

## 2. Klaar voor pilot (deel A)

| Criterium (ronde 5) | Bewijs |
| --- | --- |
| CE-04 via script, post 3 × 3 merken | `docs/hba-v1.10-stap1-ce04-post3-staging-run.json` |
| Verleidingstest: geen valse premisse/feiten in **nieuwe** V0-teksten | `docs/hba-v1.10-stap2-v0-verleiding.json` |
| Hints: geen verzonnen feiten; invulveld waar nodig | `docs/hba-v1.10-stap3-v0-hints.json` |
| Open invulveld niet publiceren | Getest op staging (HTTP 400, vaste melding) |
| Geen achteruitgang scorer (steekproef) | **16/16** stable in 5×19 op v1.10 |

### V0 klantpreview (nieuwe teksten na premissecheck)

| Merk | Klantpreview |
| --- | --- |
| Brandpunt Koffie | https://staging.igisnext.com/article/41cac903-c72d-11f1-9456-9c6b00b7cd02?hbaKlant=1 |
| Stelwerk | https://staging.igisnext.com/article/768ef8a7-c72d-11f1-9456-9c6b00b7cd02?hbaKlant=1 |
| Duinroos Thuiszorg | https://staging.igisnext.com/article/a7b482f1-c72d-11f1-9456-9c6b00b7cd02?hbaKlant=1 |

---

## 3. Per stap (v1.10)

### Stap 1 — CE-04 (+ controlecall, eerder afgerond)

- Post 3 Brandpunt/Stelwerk/Duinroos: `regelFeedbackOk: true`, regel genoemd + zin geciteerd.
- `constraint_breach_verificatie` in stabiliteits-JSON; 6×5 testcases 6/6 (documentatie stap 1).

### Stap 2 — Premissecheck schrijver (CE-03)

- Tweede conflictvraag **PREMISSE** in CE-03; `self_review.themePremisse` in de app.
- **Stelwerk:** geen partnerschap als feit — “We hebben het partnerschap afgeslagen.”
- **Duinroos:** geen opgeloste/wggewerkte wachtlijst — doorverwijzen i.p.v. prestatie.
- **Brandpunt:** ongewijzigd sterk (Black Friday / geen korting).

### Stap 3 — Hints vragen om feiten

- Geen nieuwe merkfeiten buiten haken; invulveld + `bewijsveld_bron`.
- Toepassen mag haken zetten; **toegepast** pas als haken weg zijn.

### Stap 4 — Scorer (deel B, eerste meting)

Prompt **v1.10**: scherpere `proof_gap` / `foe_vervaging`; merkbijdrage ≤5 bij `proof_gap` op kern (in prompt).

**5×19 op staging** (13 okt 2026):

| Metric | v1.9 (referentie) | **v1.10** |
| --- | ---: | ---: |
| Testposts | 17 | **19** (+2 V0-kalibratie) |
| Stable goed (5/5) | 16 / 17 | **16 / 19** |
| Review | 1 | **3** |
| Instabiel / fout (matrix) | 0 | **0** |
| Flags functionele onderdelen | 0 (na PHP-fix) | **0** |

**Kalibratieposts (oude V0-foutteksten uit jouw §4 v1.9):**

| Post | Verwacht | 5 runs |
| --- | --- | --- |
| Duinroos 7 — “wachtlijst in één maand opgelost” | `proof_gap` op kern, merkbijdrage ≤5 | Geen `proof_gap`, BC **8**, `op_merk` |
| Stelwerk 6 — samenwerking/ervaring | `proof_gap`, merkbijdrage ≤6 | `proof_gap` **5/5**, BC **7** |

Conclusie: schrijver/hints lossen het klantprobleem op; **scorer herkent Duinroos-kern nog niet** zoals bedoeld; Stelwerk **flag wel, cap niet**.

---

## 4. Wat we van jou nodig hebben

1. **Pilot:** akkoord om eerste klanten op staging/productie-pilot te zetten op basis van **deel A** (schrijver + hints + CE-04)?
2. **Stap 4:** eerst scorer fixen (prompt + evt. harde cap) vóór stap 5, of parallel?
3. **Brandpunt post 6** blijft **review** (markthygiëne) — ongewijzigd uit v1.9; nog meenemen in kalibratiemodule?

Geen productie-deploy v1.10 zonder jouw “ja”.

---

## 5. Bestanden in dit pakket

| Bestand | Rol |
| --- | --- |
| `LEESMIJ-ROGIER-v1.10.md` | Ingang |
| `hba-v1.10-verslag-roger.md` | Dit document |
| `stabiliteit-v1.10.md` + `stabiliteit-v1.10-5runs.json` | 5×19 matrix |
| `hba-v1.10-stap2-v0-verleiding.json` | Nieuwe V0 na premissecheck |
| `hba-v1.10-stap3-v0-hints.json` | V0 hints |
| `hba-v1.10-stap1-ce04-post3-staging-run.json` | CE-04 post 3 |
| `hba-flags-definitie.txt` | v1.10 flags |
| `hba-ronde-5-v1.10.md` | Opdrachttekst |
| `Brandpunt_Koffie.json` … | Testset **19** posts |

Vernieuwen: `./scripts/build-rogier-hba-pakket.sh`
