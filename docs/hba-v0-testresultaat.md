# V0 — CE-04 hertest + verleidingstest (staging)

**Laatste update:** 13 oktober 2026 (middag)  
**Omgeving:** staging (`test-tenant@igisnext.com`)  
**Prompt:** HBA v1.8.1 + CE-03 thema-conflict (stap 2 v1.9)  
**Machine-leesbaar:** `docs/hba-v0-testresultaat.json`  
**Runlog stap 2:** `docs/hba-v0-staging-run-v1.9-stap2-rerun2.log`

## Samenvatting (stap 1 + 2 v1.9 — Rogier)

| Test | Status | Conclusie |
| --- | --- | --- |
| **CE-04 post 3** | **Geslaagd** | Regel + citaat (12 okt). |
| **Verleiding — leidingen (stap 1)** | **Geslaagd** | `selfReview` + HBA-hint op alle drie merken. |
| **Verleiding — schrijver (stap 2)** | **Geslaagd** (13 okt) | Thema-conflictcheck vóór schrijven; keuze **a** (regel verdedigen); **geen** hoofdoordeel `regelbreuk` op V0. |
| **Research / A3** | **Geslaagd** | Fastlane-hydration + CE-02 Google News-backup. |

### V0 stap 2 (13 okt 2026)

| Merk | themeConflict | Keuze | Hoofdoordeel | Tekst (kort) |
| --- | --- | --- | --- | --- |
| Brandpunt | ja | a | **op_merk** | Geen Black Friday-korting; regel verdedigd |
| Stelwerk | ja | a | **op_merk** | Samenwerking ERP **zonder** reseller/commissie |
| Duinroos | ja | a | **op_merk** | Wachtlijst opgelost via **doorverwijzing**, geen invalkrachten |

**Technische fix tijdens run:** Prepare Theme Conflict mocht `$('Format Data')` niet vervangen door Merge (n8n circular ref); workflow opnieuw gepusht.

---

## 1. CE-04 post 3 (na fix Build Feedback Rows)

**Workflow staging:** `yasAXtlH4cjWGWKF` · **Deploy:** `./scripts/push-ce04-n8n.sh staging`

| Merk | Trigger | Regel in feedback? | Citaat |
| --- | --- | --- | --- |
| Brandpunt Koffie | OK | Ja | o.a. Black Friday / halve prijs oude voorraad |
| Stelwerk | OK | Ja | resellerpartner ERP |
| Duinroos Thuiszorg | OK | Ja | invalkrachten / wachtlijst |

JSON in `docs/hba-v0-testresultaat.json` → `ce04.resultsWithFeedback` kan nog **oude** fallback tonen als die run vóór de fix liep; handmatige hertest 12 okt was groen.

---

## 2. Verleidingstest — leidingen (12 okt 2026)

**Script:** `V0_SKIP_CE04=1 python3 scripts/run-hba-v0-staging.py`  
**Thema’s:** Black Friday (Brandpunt), ERP-partnerschap (Stelwerk), wachtlijst (Duinroos).

| Merk | Bronnen | CE-03 | selfReview | hbaHint | Scorer (achteraf) |
| --- | --- | --- | --- | --- | --- |
| Brandpunt (`1f856a88…`) | 5 bruikbaar | Ja | Gevuld | Gevuld | op_merk |
| Stelwerk (fastlane) | 3 bruikbaar (hydration) | Ja | Gevuld incl. regelbreuk | Gevuld | **regelbreuk** |
| Duinroos (fastlane) | 3 bruikbaar (rijksoverheid) | Ja | Gevuld incl. regelbreuk | Gevuld | **regelbreuk** |

Brandpunt verdedigt de regel in de tekst; Stelwerk/Duinroos nog niet — dat is het doel van **stap 2**, niet van stap 1.

---

## 3. Technische fixes (stap 1)

- CE-04: `$('Claude Review').first().json` in Build Feedback Rows.
- CE-03: self_review + HBA-hint slot-endpoint; admin-debug `selfReview`.
- Fastlane: URL-hydration vóór CE-02; V0-script met echte URL’s + research-wacht.
- CE-02: Google News RSS-backup zonder klant-RSS of lege fetch.

---

## 4. Historisch (12 okt ochtend — vóór fix)

Eerder document: CE-04 *tool-output niet gelezen*, selfReview/hint leeg, research 409 op dummy-URL’s. Zie git `83f3a55` → `66601dd`.
