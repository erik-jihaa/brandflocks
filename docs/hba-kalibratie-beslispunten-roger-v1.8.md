# HBA kalibratie — beslispunten voor Rogier (na 5×17, v1.7)

**Datum:** 10 oktober 2026  
**Aan:** Rogier (via Erik)  
**Model:** `claude-sonnet-4-6` · **Prompt:** `v1.7` · **Omgeving:** staging  
**Bron:** `docs/hba-testmerken-stabiliteit.md`, `docs/hba-testmerken-stability-v1.7-5runs.json`  
**Context:** Na A2 (volledige verboden logica) opnieuw **5 runs × 17 posts**. Geen prompt-tuning door de agent — alleen dit beslispakket.

## Kort

| Metric | Waarde |
| --- | ---: |
| Stable goed (5/5) | **9 / 17** |
| Instabiel | 3 |
| Consistent fout | 2 |
| Review (ambigu in testset) | 3 |
| `category_fallback` laatste run | **10 / 17** (was 13/16 v1.6) |
| `hoofdoordeel_gecorrigeerd` | **25** (model wijkt af; PHP past aan op flag-regels) |

**Enige falend klaar-criterium briefing:** Stelwerk post 2 (*Categorietaal*) — 0/5 goed.

---

## Overzicht: acht posts die niet stable goed zijn

| Merk | Post | Geaggregeerd | Kernconflict |
| --- | --- | --- | --- |
| Stelwerk | Categorietaal | **fout** 0/5 | Hoofdoordeel **tegenkracht**, flags wel `category_fallback` |
| Brandpunt | Neutraal / grensgeval | **fout** 0/5 | Hoofdoordeel **categorietaal**, verwacht **neutraal** |
| Duinroos | Neutraal / grensgeval | **fout** 0/5 | Idem (employer_culture / team) |
| Stelwerk | Neutraal / grensgeval | **fout** 0/5 | Wisselend categorietaal/tegenkracht vs **neutraal** |
| Brandpunt | Op merk | **instabiel** 3/5 | Hoofdoordeel stabiel `op_merk`, maar **`proof_gap`** → auto-fout |
| Duinroos | Archetype zonder bewijs | **instabiel** 3/5 | Runs 2+5: **tegenkracht** i.p.v. categorietaal |
| Stelwerk | Branchetaal (verboden logica) | **instabiel** 4/5 | Run 4: **tegenkracht** i.p.v. categorietaal |
| Brandpunt | Mechanisme / markthygiëne (post 6) | **review** 0/5 | Ambigu in JSON; split **categorietaal** vs **op_merk** |

De overige **9 posts** zijn 5/5 goed (o.a. alle *Breekt de regel*, Brandpunt/Duinroos categorietaal, Duinroos post 6 tegenkracht).

---

## Beslispunt 1 — Voorrang hoofdoordeel: `tegenkracht` vóór `categorietaal`

**Huidige prompt** (`hba-hoofdoordeel-definitie.txt`):

> regelbreuk → **tegenkracht** → **categorietaal** → op_merk → neutraal

**Stelwerk post 2** (5/5 identiek):

- Tekst: *«Wij zijn uw betrouwbare partner voor ERP-implementaties. Neem contact op voor een vrijblijvend gesprek.»*
- Verwacht: `categorietaal` + `category_fallback`, geen `constraint_breach`.
- Werkelijk: flags `category_fallback`, `foe_vervaging` (CTA-zin); hoofdoordeel **tegenkracht** (niet gecorrigeerd).
- Matrix: **fout** — «hoofdoordeel tegenkracht (verwacht: categorietaal)».

**Vraag:** Is «Neem contact op…» hier **tegenkracht-logica** (foe_vervaging) die het hele oordeel domineert, of **hygiëne-CTA** die onder `categorietaal` mag blijven als de kern partner-taal is?

| Optie | Gevolg |
| --- | --- |
| **A. Prioriteit behouden** | Testpost of verwachting aanpassen: post 2 Stelwerk = tegenkracht + flags, geen categorietaal-hoofdoordeel. |
| **B. Categorietaal wint bij dubbele flags** | Prompt/regel: als `category_fallback` op de kernzin staat én geen `constraint_breach`, dan hoofdoordeel **categorietaal** ook bij `foe_vervaging` op CTA. |
| **C. Foe alleen op kern** | Definitie `foe_vervaging`: alleen tellen als de *tegenkracht-redenering* in de kern staat, niet in slot-CTA → post 2 wordt categorietaal. |

**Geldt ook (instabiel):** Duinroos *Archetype zonder bewijs* (runs met «Zo zien wij zorg.»), Stelwerk *Branchetaal* run 4 («Daarom kiezen steeds meer…»).

---

## Beslispunt 2 — `neutraal` vs `categorietaal` bij service/educatie

**Brandpunt post 4** (*Vijf tips om je koffiezetapparaat te ontkalken*, blog):

- Verwacht: **neutraal** (educatie/service, lage architectuurbijdrage).
- 5/5: flag `category_fallback` op de titel/regel; hoofdoordeel **categorietaal** (PHP corrigeert model `neutraal` → categorietaal).
- Reden fout: «hoofdoordeel categorietaal (verwacht: neutraal)».

**Duinroos post 4** (team/employer_culture): zelfde patroon — **categorietaal** 5/5, verwacht **neutraal**.

**Stelwerk post 4** (webinar AI): 0/5 goed; hoofdoordeel wisselt **categorietaal / tegenkracht**, verwacht **neutraal** (ambigue trend in testset).

**Vraag:** Mag een post **neutraal** blijven als de contentrol educatie/service/werving is, **ook als** ergens een generieke zin `category_fallback` triggert?

| Optie | Gevolg |
| --- | --- |
| **A. Neutraal wint op rol** | Hoofdoordeel **neutraal** als contentrol educatie/service/employer_culture en geen regelbreuk/tegenkracht in de kern; flag mag wel blijven voor interne KPI. |
| **B. Generieke kop = categorietaal** | Huidige lijn: tips/webinar-kop is categorietaal; **testset verwachting** naar categorietaal/review zetten i.p.v. neutraal. |
| **C. Geen category_fallback op neutrale posts** | Scorer: bij pure utility/educatie geen `category_fallback` op de headline → neutraal haalbaar zonder definitie-wijziging. |

---

## Beslispunt 3 — `proof_gap` vs «op merk» (Brandpunt post 1)

**Tekst (post 1):** oogst, *«Over twee weken in de winkel, zodra hij is uitgerust»*, zes-wekenregel.

- Verwacht: `op_merk`, geen risicoflags (matrix: geen `category_fallback`).
- Hoofdoordeel: **op_merk** in alle 5 runs.
- Oordeel: **3/5 goed**, **2/5 fout** — flag **`proof_gap`** op de «twee weken»-zin (timing zonder bewijs in dezelfde zin?).

**Stelwerk post 1** staat `proof_gap` in **`toegestane_flags`** in JSON; Brandpunt niet.

**Vraag:** Is «uitrusten duurt twee weken» **bewijs van het mechanisme** (geen proof_gap) of **ongestaafde claim** (proof_gap)?

| Optie | Gevolg |
| --- | --- |
| **A. Geen proof_gap** | Prompt: proces/timing die het merkmechanisme *is*, telt niet als proof_gap. |
| **B. proof_gap toegestaan op post 1** | Alleen testset/matrix: `toegestane_flags` Brandpunt post 1, zoals Stelwerk. |
| **C. proof_gap blijft; oordeel review** | Klant ziet op_merk + interne flag; auto-matrix geen hard fout. |

---

## Beslispunt 4 — Brandpunt post 6 (ambigu, `ambigu: true` in JSON)

**Tekst:** oogst + twee weken + **branddatum op de zak** (geen zes-wekenzin in post 6).

- Verwacht (Rogier): categorietaal / lage distinctiviteit — branddatum = markthygiëne.
- Runs 1–2: hoofdoordeel **categorietaal** (corrigeert van op_merk), flags o.a. `category_fallback` «Vandaag nieuwe oogst…», `proof_gap` op timing.
- Runs 3–5: hoofdoordeel **op_merk**, vooral `proof_gap` zonder category_fallback.

**Vraag:** Is dit een **review-post** (geen auto goed/fout) of moet v1.8 één winnaar afdwingen?

| Optie | Gevolg |
| --- | --- |
| **A. Review houden** | Matrix blijft review; productie toont handmatige kalibratie. |
| **B. Verwacht op_merk** | Mechanisme + branddatum = merk; hygiëne-flag alleen secundair. |
| **C. Verwacht categorietaal** | Prompt scherper: branddatum zonder eigen regel = categorietaal (R2 hygiëne). |

---

## Beslispunt 5 — `category_fallback` nog op 10/17

Doel ronde 2 was minder brede fallback. **10/17** is beter dan 13/16, maar nog hoog.

**Vraag:** Is **10/17** acceptabel voor v1.7 productie-staging, of is **v1.8** verplicht vóór klant-Hoofdoordeel live?

- Indien **v1.8**: focus op definities (kern vs bijzin, neutrale posts, foe op CTA) vóór nieuwe modelruns.

---

## Beslispunt 6 — `hoofdoordeel_gecorrigeerd` (25×)

Het model kiest vaak een hoofdoordeel dat **niet** past bij de flag-prioriteit in PHP; de backend corrigeert (debugveld).

**Vraag:** Acceptabel als **veiligheidsnet**, of moet het model in ≥95% runs zelf de juiste waarde geven vóór productie?

---

## Wat Erik doet na jouw antwoord

1. Jij kiest per beslispunt **A/B/C** (of variant in eigen woorden).
2. Erik implementeert **alleen** wat je kiest als **v1.8** (promptfragmenten + matrix-regels + evt. test-JSON).
3. Opnieuw **5×17** op staging; opnieuw dit format als er missers blijven.
4. **Geen** productie-deploy HBA-hoofdoordeel tot jij groen geeft.

---

## Bijlagen in repo

| Bestand | Inhoud |
| --- | --- |
| `docs/rogier-hba-testmerken-pakket/` | Kopie voor delen ( `./scripts/build-rogier-hba-pakket.sh` ) |
| `docs/hba-testmerken-ronde3-rapport-roger.md` | Korte samenvatting ronde 3 |
| `briefing/hba-input/HBA_testmerken/*.json` | Verwachtingen + postteksten |
| `web/api/prompts/hba-*.txt` | v1.7 fragmenten |

**Antwoord graag:** per beslispunt 1–6 (letter of korte zin). Erik plant daarna v1.8-werk.
