# Cursor-briefing — A15-mapping, eerlijke telling en de twee aparte routes

**Eigenaar:** Erik · **Datum:** 16 oktober 2026 · **Model:** Composer 2.5
**Context:** nulmeting run 3 (`docs/hba-ijkrun-nulmeting-v1.10.6.*`), ijkset v0.2, koppeldocument `docs/hba-ijkset-koppeling-v0.2.md`.

**Wat dit wel is:** de telling eerlijk maken en één diagnose draaien.
**Wat dit niet is:** beslissen wat de app moet doen met een merk zonder tegenkracht. Dat is methodiek; die vraag ligt bij Rogier (§7.6 van het koppeldocument). Bouw daar niets op vooruit.

---

## Waarom

A15 (Kinderopvang Zonnig) telt in run 3 als **fout**, met als reden `"Vul eerst persoonlijkheid, doel en tegenkracht in"`, `output: null` en `model/promptVersion: null`. De scorer heeft het geval nooit gezien. Een infrastructuurfout wordt zo geteld als een inhoudelijke fout, waardoor het aantal fouten (11) te hoog is en de valkuil-score te laag.

Dat is geen uitzondering. Drie valkuil-gevallen hebben een bewust leeg veld: A09 en A12 (`regels` leeg, verwachten `regel_ontbreekt`) en A15 (`niet_in_geloven` leeg). In alle drie is leeg een **bevinding**, geen reden om te stoppen. A09 en A12 draaien al goed, omdat de harde poort alleen naar archetype, doel en tegenkracht kijkt.

---

## 1. Lege velden blijven leeg

In `scripts/run-hba-ijkrun-staging.py` en de route erachter: geef de ijk-velden door **precies zoals ze in de JSON staan**. Vul een leeg veld nooit aan met een placeholder, en leid geen tegenkracht af uit de toelichting of de signalen. De ijkset test wat Rogier heeft geschreven; alles wat we zelf invullen beoordeelt het model onze tekst in plaats van de zijne.

Leg dit ook vast als commentaar bij de mapping, zodat het later niet alsnog "opgelost" wordt.

## 2. Registreer hoe ver een geval kwam

Voeg per geval een veld toe, bijvoorbeeld `bereikt_tot`, met drie mogelijke waarden:

- `harde_poort` — geweigerd op lege velden (`ce_hba_v2_run_architecture_check`, regel ~325)
- `identity_gate` — door de harde poort, gestopt op de identity gate; `architectuur_check_result` heeft dan een rij met `identity_gate_pass = 0`
- `inhoudelijke_check` — de LLM-check heeft gedraaid

Dit geldt alleen voor architectuurchecks; bij de andere soorten blijft het leeg.

## 3. Scheid "niet gedraaid" van "fout"

Een geval dat de scorer nooit heeft bereikt, is geen scorerfout. Pas de telling aan:

- `runStatus` krijgt de waarde `niet_gedraaid` met een `reden` als de run strandde vóór de inhoudelijke check (harde poort, geen route, aparte route).
- `fout` blijft voor gevallen waar de app wél een oordeel gaf en dat oordeel afwijkt.

Rapporteer de valkuil-score vanaf nu in **drie** getallen in plaats van één percentage: gedraaid en goed, gedraaid en fout, niet gedraaid met reden. Nu vallen vijf van de achttien valkuil-gevallen in die laatste groep: A15 (harde poort), V01 (eigen route) en C02, C03 en C06 (geen compressieroute). Dat moet zichtbaar blijven, anders oogt een percentage beter dan het is.

Werk de samenvatting (`docs/hba-ijkrun-nulmeting-*-samenvatting.md`) in dezelfde vorm bij, en herbereken run 3 met de nieuwe indeling. Draai run 3 **niet** opnieuw; de uitkomsten blijven gelijk, alleen de indeling verandert.

## 4. Eén diagnose voor A15 — geen productwijziging

Rogier moet beslissen wat de app met een merk zonder tegenkracht hoort te doen. Die keuze is makkelijker met bewijs, dus draai A15 **één keer** buiten de reguliere run, met de harde poort overgeslagen en `niet_in_geloven` leeg:

1. Komt het geval door de identity gate, of stopt het daar? Leg de vastgelegde rij uit `architectuur_check_result` erbij.
2. Als de inhoudelijke check draait: welk `totaaloordeel`, welke dimensies, en komen `hygienefactor_gedetecteerd` en iets dat lijkt op "de regel is een wettelijke plicht" erin voor?

Schrijf de uitkomst naar `docs/hba-a15-diagnose.md`, met de ruwe JSON. Dit is een eenmalige diagnose met een tijdelijke schakelaar of een los script; het mag **niet** in de normale route blijven staan en niet naar staging als gedrag van de app.

Mijn verwachting, ter controle: de identity gate krijgt een lege tegenkracht en faalt, waardoor de inhoudelijke check niet draait en de twee verwachte signalen er niet uit komen. Klopt dat, dan is dat precies het antwoord dat Rogier nodig heeft. Komt er iets anders uit, meld dat dan expliciet.

## 5. V01 en de compressietest houden hun eigen uitkomstbestand

Beide blijven buiten de batch van 37 LLM-calls; dat is terecht. Zorg wel dat ze later naast de batch te leggen zijn:

- Geef de V0-run (V01) en straks de compressierun een eigen resultaatbestand met **dezelfde kopvelden** als de batch: `ijkset_versie`, `promptVersion`, `model`, tijdstip en omgeving.
- Gebruik per geval dezelfde uitslagwaarden (`goed` / `fout` / `review` / `niet_gedraaid` met reden), zodat één go-live-overzicht de drie bronnen kan optellen.
- Bouw de compressieroute nog niet. Die wacht op besluit §7.4 van Rogier.

## Afronden

- `python3 -m py_compile` op de gewijzigde scripts.
- Eén commit per punt.
- Niets naar productie; de diagnose uit punt 4 ook niet naar staging als blijvend gedrag.
- Kort verslag aan Erik: de herberekende telling van run 3 in de nieuwe indeling, en de uitkomst van de A15-diagnose.
