# HBA — pakket voor Rogier, 15 oktober 2026

**Omgeving:** staging (niet productie) · **Promptversie scoring:** v1.10.2 · **Model:** `claude-sonnet-4-6`
**Aanleiding:** jouw reactie van 15 oktober (v1.10 + ijkset v0.2).

---

## Wat we van je vragen

1. **Zeven besluiten** in document `01-ijkset-koppeling-v0.2.md`, §7. (7.6 en 7.7 zijn toegevoegd op 16 oktober, na de nulmeting: wat moet de app doen met een merk zonder tegenkracht, en bestaat `regel_is_wettelijke_plicht` als signaal?) Bij elk besluit staat een voorstel met de reden; je kunt ja zeggen of de afwijking noemen.
2. **Mag de pilot starten?** Jouw voorwaarde was punt 1 (research). Die is opgelost, zie hieronder.
3. **Eén oordeel over een terugval** die we zelf vonden: Stelwerk post 5, zie "Wat niet groen is".

---

## Leesvolgorde

| # | Bestand | Waarvoor |
| --- | --- | --- |
| 01 | `01-ijkset-koppeling-v0.2.md` | **De zeven besluiten (§7), incl. 7.6–7.7.** De rest is koppeling ijkset ↔ app: scoreschaal, labels, contentrollen, signalen. |
| 02 | `02-research-koppeling.md` | Jouw punt 1 — waar de zorgresearch bij Stelwerk vandaan kwam |
| 03 | `03-versieregistratie.md` | Jouw punt 2 — promptversie en model bij elke uitkomst |
| 04 | `04-merkbijdrage-cap.md` | Jouw punt 3 — de vaste grens in de code |
| 05 | `05-scorer-v1.10.1.md` | Wat er daarna in de scorer veranderde |
| 06 | `06-testset-aanpassingen.md` | Jouw punt 3.2 — aangepaste verwachtingen |
| 07–09 | `07-volledige-run-5x19.md`, `08-stabiliteit-v1.10.2.md`, `09-…-5runs.json` | De volledige stabiliteitsrun |
| 10 | `10-controlecall-verificatie-5x6.json` | Jouw punt 4 — de losse test van de controlecall (6 × 5) |
| 11 | `11-v0-en-ce04-testresultaat.json` | V0-verleiding, hints en CE-04 in één run |
| 12 | `12-klantpreview-urls-staging.md` | De posts zoals een klant ze ziet |
| 13 | `13-ijkrun-nulmeting-rapport-roger.md` | **Nulmeting ijkset v0.2** (16 okt) — valkuil in drie getallen, A15, §7.6–7.7 |
| 14 | `14-ijkrun-nulmeting-samenvatting.md` | Volledige matrix run 3 (herindeling A15) |

---

## Wat is opgelost

**Punt 1 — research.** Geen tenantlek. Stelwerk kreeg in het testscript URL's die niet bij ERP horen: twee uit de lijst van Brandpunt en één zorg-URL van Duinroos. Daarnaast zat er een echte fout in de code: `ce_fetch_slot_research()` bepaalde de klant uit de sessie in plaats van uit het slot, waardoor hij onder het servicetoken "Slot niet gevonden" gaf. Beide zijn gerepareerd, en er is nu een isolatietest die bewijst dat een Stelwerk-slot geen research van Duinroos of Brandpunt oplevert. Jouw voorwaarde voor de pilot is daarmee wat ons betreft rond.

**Punt 2 — versieregistratie.** `prompt_version` en `model` staan nu op thema-advies, research-advies, schrijfhint en architectuurcheck, naast de artikelscore. Elke pilotrun is dus later terug te plaatsen.

**Punt 3 — de vaste grens.** In PHP, niet in de prompt: citeert `proof_gap` of `foe_vervaging` de kop of de openingszin, dan is de merkbijdrage hoogstens 5. Wat als kop en openingszin telt, staat in document 04. Het resultaat op de twee kalibratieposts: Duinroos post 7 krijgt 5 van de 5 keer `foe_vervaging` op de kop met merkbijdrage 5, Stelwerk post 6 krijgt `proof_gap` met 5 tot 6. De grens doet dus wat je vroeg.

**Punt 4 — bewijs.** `constraint_breach_verificatie` staat nu per geval in de ruwe JSON (15 behouden, 0 verworpen, 0 vervallen zonder uitleg). De losse test van de controlecall (6 gevallen × 5 runs) zit als bestand 10 in dit pakket.

**Punt 5 — hints.** De dubbele invulvelden en de plaatsing in `cta_na` zijn gerepareerd; V0 stap 3 is daarna voor alle drie de merken opnieuw gedraaid (bestand 11).

---

## Wat niet groen is

**Stelwerk post 5 (branchetaal uit de verboden logica) is teruggevallen: 5 van de 5 fout.** Dit ging eerder goed. Het model meldt nu een extra `foe_vervaging` op "Daarom kiezen steeds meer maakbedrijven voor Stelwerk", en ons filter zet het hoofdoordeel daarmee op `tegenkracht` in plaats van het verwachte `categorietaal`. Inhoudelijk is de redenering van het model niet gek — die zin verkoopt op populariteit. De vraag aan jou: is dit een fout in de testset-verwachting, of moet `category_fallback` zwaarder wegen dan `foe_vervaging` bij het bepalen van het hoofdoordeel?

**De twee kalibratieposts tellen als *review*, niet als *goed*.** De flag en de grens kloppen (zie punt 3), maar het hoofdoordeel wijkt af van de verwachting in de testset. Dat raakt dezelfde vraag als hierboven: welke flag bepaalt het hoofdoordeel als er meerdere zijn.

**Twee instabiele posts.** Duinroos "archetype zonder bewijs" en Duinroos "categorietaal" gaan 4 van de 5 keer goed; in de afwijkende run slaat het hoofdoordeel om naar `tegenkracht`. Citaten staan in document 08.

**`hoofdoordeel_gecorrigeerd` zit op 7,4 procent**, boven de 5 procent die we als criterium hadden. Dat is het aantal keren dat ons PHP-filter het oordeel van het model bijstelt. Zakt dat getal niet, dan betekent het dat de regels in de prompt en de regels in de code uit elkaar lopen.

Stand van de hele run: 13 van de 19 posts 5 van de 5 goed, 2 instabiel, 1 fout, 3 review.

---

## Wat er daarna gebeurt

Na jouw vijf besluiten bouwen we de ijkrun-runner en draaien we één nulmeting over de 38 gevallen die een route hebben (46 min de 8 compressietests), met `ijkset_versie`, promptversie en model per uitkomst. De valkuil-gevallen rapporteren we apart, omdat die voor go-live tellen.


---

## Toevoeging 16 oktober — eerste nulmeting gedraaid

De nulmeting over ijkset v0.2 is inmiddels gedraaid op staging (promptversie v1.10.6). Twee dingen uit die run horen hierbij.

**A15 kreeg geen oordeel.** De app weigerde de check omdat `niet_in_geloven` leeg is. Het geval stond daardoor als *fout* in de telling, terwijl de scorer het nooit heeft gezien. We corrigeren dat naar *niet gedraaid* en leveren een losse diagnose van A15, zodat je besluit 7.6 met de echte uitkomst voor je kunt nemen.

**De valkuil-score krijgt drie getallen in plaats van één.** Vijf van de achttien valkuil-gevallen zijn niet gedraaid: A15 (de poort), V01 (eigen route via CE-03) en C02, C03 en C06 (nog geen compressieroute). Die blijven apart zichtbaar, zodat een percentage niet beter oogt dan de werkelijkheid.
