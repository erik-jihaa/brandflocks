# Antwoord aan Rogier — ijkset, hoofdoordeel en pilot (16 oktober 2026)

Rogier,

Dank voor je besluiten en voor de scherpte rond Stelwerk post 5 en Duinroos. Hieronder onze reactie: wat we overnemen, wat de stand is op je twee extra punten, en wanneer de pilot volgens jouw voorwaarden mag.

---

## 1. Ijkset-koppeling (jouw §7.1–7.5)

We hebben je keuzes verwerkt in **`docs/hba-ijkset-koppeling-v0.2.md`** (status: bevestigd 16 oktober).

| Punt | Jouw besluit | Onze uitvoering |
| --- | --- | --- |
| **7.1** | C beslist, B als waarschuwing, alle tekstscores | Ijkrun: valkuil/flags + hoofdoordeel + BC-cap = **goed/fout**; positionering 0–100 buiten band = **review/waarschuwing**, geen automatische fail. |
| **7.2** | Geen numerieke band bij architectuurcheck | Alleen `totaaloordeel`, dimensies en verplichte/verboden signalen. |
| **7.3** | Contentrollen + employer_culture voor engagement | Definitieve mapping in koppeldoc; vuistregel “wat laat de post de lezer doen of zien” als toelichting. |
| **7.4** | Nulmeting zonder compressie; H4 daarna; go-live mét C02/C03/C06 | Runner rapporteert C01–C08 expliciet als *niet gedraaid*; go-live-criterium blijft 100% op alle valkuilen inclusief compressie zodra H4 er is. |
| **7.5** | Invulveld = bewijs van de regel, geen los feit; verplicht signaal in ijkset | Signaal toegevoegd aan **S05** in `HBA_ijkset_v0_2.json`; plaatsing `passageNa`/`ctaNa` telt niet mee, inhoud wel. |

Daarna: **ijkrun-runner + nulmeting** (38 gevallen + aparte compressie-tabel), zoals afgesproken.

---

## 2. Hoofdoordeel: volgorde en meerdere flags

We nemen je volgorde over, in **prompt** (`hba-hoofdoordeel-definitie.txt`, `hba-flags-definitie.txt`) en waar nodig in de **filter** (v1.10.4):

1. **Regelbreuk** — alleen een handeling die tegen de regel ingaat.  
2. **Tegenkracht** — alleen als de tekst de **specifieke** logica van de tegenkracht van **dit merk** overneemt (zelfde systeem, zelfde gevolg). Populariteit (“steeds meer kiezen”), warme zorgtaal of algemene betrouwbaarheid is **geen** tegenkracht.  
3. **Categorietaal** — taal die elke concurrent kan gebruiken, ook in de buurt van een tegenkracht-thema.

**Stelwerk post 5:** je analyse klopt: commissielogica is de echte tegenkracht; “steeds meer kiezen” is categorietaal. In v1.10.2 gaf het model ten onrechte `foe_vervaging` + hoofdoordeel tegenkracht (0/5 vs verwacht categorietaal). v1.10.3 fixte post 5 via de filter; v1.10.4 scherpt de **definitie** aan zodat het model minder vaak verkeerde `foe_vervaging` zet, en de filter **categorietaal wint** als `category_fallback` en `foe_vervaging` op **dezelfde zin** rusten (typisch Duinroos “warme, persoonlijke zorg”).

**5×19 op v1.10.4:** ja — na deploy staging draaien we de volledige matrix opnieuw. Doel: groen op de set, **`hoofdoordeel_gecorrigeerd` onder 5%** (was 7,4% op v1.10.2).

---

## 3. Brandpunt post 6 — “Mechanisme dat markthygiëne is”

**Stand v1.10.2:** 0/5 op verwachting — scorer gaf **5× `op_merk`**, terwijl de testset **`categorietaal`** verwacht (review/ambigu: branddatum/oogst is gangbaar specialty-gedrag; de zes-wekenregel staat niet in de post).

**Hoe we dit zien:** dit is precies markthygiëne — iets wat de branche normaal doet, geen onderscheid. De app herkent het nog niet betrouwbaar genoeg als **`category_fallback` + categorietaal** (of lage distinctiviteit zonder `op_merk`). Dat is **scorer-kalibratie**, geen schrijver-lek; post 6 blokkeert de pilot niet apart, maar hoort wel **groen** in de 5×19.

**Plan:** in v1.10.4 prompt/flags expliciet: “transparantie over gangbare praktijk (branddatum, oogst, traceability) zonder de merkregel in de tekst = categorietaal/mechanisme als hygiëne, niet op_merk.” Daarna meten in de rerun.

---

## 4. Stelwerk V0 — research en verzonnen concurrentiefeit

**Research-URL’s:** je observatie klopt. Twee van de drie Stelwerk-URL’s in `run-hba-v0-staging.py` gaven **404**; de rest was te generiek. We vervangen ze door **werkende** bronnen (Consumentenbond MKB-software, Rijksoverheid ICT-overzicht, Consumentenbond digitale transformatie MKB) en valideren met HTTP vóór elke V0-run. Volgende stap blijft: research uit **merkbronnen** in de testmerk-config (niet alleen hardcoded lijsten).

**“Helft van hun marge”:** CE-03 **self_review** markeert dit correct als `unsupported_assumption`; de **scorer** gaf nog **op_merk, BC 9, geen flag**. Dat is te mild voor de pilot.

**Pilot-maatregel (snelste route):** bij **indienen/publiceren** blokkeren we als `self_review.unsupported_assumption` niet leeg is (zelfde patroon als open invulvelden), met klanttaal in de trant van: *De schrijver heeft een aanname gemarkeerd die niet in je bronnen staat. Corrigeer die eerst.* Parallel werken we aan scorer/prompt voor feiten over **derden/concurrenten** zonder bron (`proof_gap`).

---

## 5. Mag de pilot starten?

**Deel A (research, versieregistratie):** akkoord met jou — punt 1 is afgerond, geen tenantlek, isolatietest gedaan.

**Pilot-start volgens jouw drempel — nog niet vandaag.** We starten zodra op staging:

| Voorwaarde | Status |
| --- | --- |
| Volledige **5×19 groen**, correcties **&lt; 5%** | Nog te draaien op **v1.10.4** (na hoofdoordeel-update) |
| Onbewezen aanname uit self_review **blokkeert** submit/publish | In bouw (deze week) |
| Stelwerk V0 met **bruikbare ERP-research** | URL-fix + opnieuw V0-run |

Daarna: **ijkrun-runner**, nulmeting 38 gevallen, daarna **H4 compressie** (C02/C03/C06 mee in go-live). We houden je op de hoogte na de 5×19 v1.10.4.

---

## 6. Bijlagen in repo

- Koppeling (bevestigd): `docs/hba-ijkset-koppeling-v0.2.md`  
- Staplog hoofdoordeel/run: `docs/hba-v1.10-stap8-ijkset-koppeling.md` (+ commit v1.10.4 volgt)  
- Laatste stabiliteit: `docs/stabiliteit-v1.10.2.md` (referentie vóór rerun)

Groet,  
Erik

*(Concept — mag zo naar Rogier; technische commits: v1.10.4 + URL-fix + submit-guard.)*
