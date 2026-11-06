# T13 versus Duinroos post 7 — veldvergelijking

Datum: 17 oktober 2026  
Voor: Rogier (aanleverlijst §5.1)  
Bron: `briefing/cursor-bouwvolgorde-besluiten-16-oktober.md` stap 6  
Prompt: **v1.10.8** (pilot, bevroren) · model **claude-sonnet-4-6**

**Geen bouw.** Ijk-narratief is niet gelijkgetrokken aan het testmerk.

---

## Vastgesteld

De tekst is **woord voor woord identiek**. Kop: *Onze wachtlijst is in één maand opgelost.*  
Bronnen: `HBA_ijkset_v0_3.json` T13.input.tekst = `Duinroos_Thuiszorg.json` testpost 7.

Het verschil zit in **welk narratief de scorer meekrijgt**, niet in de post.

| Route | Hoe het narratief ontstaat |
| --- | --- |
| **T13 (ijkrun)** | Acht ijkvelden → `ce_hba_ijk_narrative_from_merkarchitectuur()` (`web/api/hba-ijkrun-repository.php`) |
| **Duinroos post 7** | Veertien velden uit `brand_narrative` van het testmerk, 1:1 |

---

## Acht ijkvelden → veertien scoringvelden

T13 verwijst naar A18; de acht velden in T13.input.merkarchitectuur zijn dezelfde als A18.

| Scoringveld | Ijk T13 (afgeleid) | Testmerk Duinroos | Effect op deze post |
| --- | --- | --- | --- |
| **doel** | Dat ouderen thuis baas blijven over hun eigen dag. | identiek | — |
| **mechanisme** | `wat_anders` (kleine vaste teams…) | identiek | — |
| **geloofwaardigheidstest** | `regels` (max. vier zorgverleners…) | identiek | — |
| **bewijsvelden** | dezelfde vier regels | identiek | — |
| **foe** | *Dat het zorgrooster mag bepalen hoe de dag van een cliënt eruitziet.* (`niet_in_geloven`) | *Het zorgrooster dat de klok belangrijker maakt dan de mens die op zorg wacht.* | Ijk-foe is een **stelling**; testmerk-foe is een **actor + maatstaf** (klok vs mens). De kop viert een capaciteitsresultaat — dat ligt dichter bij de testmerk-foe. |
| **spanning** | Samengesteld: *Tussen [doel] en de kracht die zegt: [niet_in_geloven]* | *Een verzorger die de dag van de cliënt laat bepalen… in een sector waar het rooster de dag bepaalt.* | Ijk mist de Verzorger/hygiëne-spanning. |
| **archetype** | Afgeleid uit `branche_library.markt_archetype` op label *thuiszorg, ouderen*; fallback **Maker** | **Verzorger** (expliciet) | Andere stem + andere hygiene-bewijslast. |
| **verboden_logica** | Alleen de foe-zin | Vier regels, o.a. *wachtlijsten weggewerkt door extra inzet van invalkrachten* en *efficiëntie en productiviteit als prestatie* | **Sterkste spoor.** De kop is letterlijk “wachtlijst opgelost”. Zonder die verboden zin ziet de ijk-scorer geen verboden logica. |
| **tone_of_behaviour** | Generiek: *Schrijf concreet… Geen jargon…* | *Warm maar concreet. Vertelt wat er gebeurt op een gewone dag…* | Kalibratie zwakker. |
| **goede_voorbeelden** | `wat_anders`, `regels`, `doel` (herhaald tot 3) | Drie cliëntdagen (Sanne/half tien; 3,2 zorgverleners; teams van zes) | Ijk herhaalt de architectuur; testmerk kalibreert “zo klinkt Duinroos”. |
| **zwakke_voorbeelden** | Generieke categorietaal (*betrouwbare partner…*) | *Zorg met een hart*; *15 procent meer cliënten*; *liefdevolle zorg* | Ijk mist het anti-voorbeeld “volume/wachtlijst als prestatie”. |
| **woorden die (niet) passen** | Generiek (*concreet / betrouwbare partner*) | Afgeleid uit echte `verboden_logica` | — |
| **contextprofiel** | Hardcoded `volwassen` | `volwassen` | Gelijk. |

Ontbrekend t.o.v. het testmerk (zoals in de bouwvolgorde): echte **verboden_logica**, **tone_of_behaviour**, **goede_voorbeelden**, **zwakke_voorbeelden**. **contextprofiel** is ingevuld, maar stub.

---

## Wat de twee routes al scoorden (v1.10.8, geen extra run nodig)

Zelfde tekst, zelfde model, zelfde promptversie.

| | T13 ijkrun 17 okt | Duinroos post 7 (5×19) |
| --- | --- | --- |
| Bron | `docs/hba-ijkrun-nulmeting-v1.10.8.json` | `docs/hba-testmerken-stability-v1.10.8-5runs.json` |
| Hoofdoordeel | **op_merk** | **tegenkracht** (5/5; model zei eerst `op_merk`, PHP corrigeerde) |
| Flags | **geen** | **foe_vervaging** op de kop (5/5) |
| Merkbijdrage | **8** | **5** (cap) |
| Ijk-verwachting | tegenkracht + `proof_gap`/`foe_vervaging` op kop + BC ≤5 → **fout** | zelfde inhoudelijk; matrix noemt de post nog *review/ambigu* (import/judge, niet de score) |

De veldvergelijking sluit het verschil: zonder de echte **verboden_logica** (wachtlijst wegwerken) en met een zwakkere **foe** viert de kop geen “tegenstander” in de ijk-route. De rest van de tekst verdedigt de regel; de scorer beloont dat als **op_merk**.

---

## Wat we níet doen

Het ijk-narratief **niet** aanvullen tot het volledige testmerk. Dan toetst T13 niet meer Rogiers acht velden, maar Duinroos.

Als T13 dezelfde flags moet vangen als post 7, hoort dat in de **ijkset** (bijv. `niet_in_geloven` of een extra ijkveld), niet in een stille normalisatie in PHP.

---

## Voor Rogier

1. Het verschil is **narratief**, niet tekst of model.
2. De mapping acht → veertien velden **verdunt** foe en **verboden_logica**. Dat is genoeg om T13 te laten falen terwijl post 7 slaagt.
3. Vraag: moet T13 met alleen de acht velden al `foe_vervaging` op die kop geven, of is post 7 de “volle Duinroos”-variant en T13 de schrale ijkvariant? Dat bepaalt of de ijktekst of de verwachting moet wijzigen — niet de mapper.
