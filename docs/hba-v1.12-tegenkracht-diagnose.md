# HBA v1.12 — tegenkracht-diagnose (offline replay)

Gegenereerd: 2026-10-19 20:11:11 UTC
Bron: `docs/hba-testmerken-stability-v1.12-5runs.json` (95 scores, geen modelcalls).

Twee varianten van `ce_hba_resolve_hoofdoordeel()`:
- **Nu:** trede 2 = `foe_vervaging` + `foe_on_kern` (v1.12, zonder `&& !kernCat`).
- **Met voorwaarde terug:** trede 2 = `foe_vervaging` + `foe_on_kern` + **`!kern_categorietaal`**.

`foe_on_kern` en zone (kop / openingszin / verderop) zijn herberekend uit posttekst + foe-citaat.
`merkbewering`/`kern_categorietaal` in de export ontbraken; replay gebruikt label Neutraal → `nee`, anders `ja`, en `kern_categorietaal=ja` wanneer `category_fallback` in flags (zelfde fallback als in de filter).

## Samenvatting stable 5/5 (scorebare posts)

| Variant | Stable goed |
| --- | ---: |
| Opgeslagen in run (v1.12 live) | **13** / 19 |
| Replay **nu** (zelfde regels) | **13** / 19 |
| Replay **met `&& !kernCat` terug** | **14** / 19 |

Verschil live → replay nu: wijkt af als herberekende `foe_on_kern`/tool-reconstructie niet exact de score-run weerspiegelt.

**Leeswijzer:** vijf van de zes niet-stabiele posts falen vooral doordat `tegenkracht` wint waar `categorietaal` verwacht wordt (`foe_vervaging`). Met **`&& !kernCat` terug** zou replay **14/19** stable geven i.p.v. **13/19** — vooral Brandpunt categorietaal en Duinroos categorietaal. Stelwerk branchetaal blijft grotendeels fout: check zone-kolom (`foe_on_kern` / verderop). Geen terugzetten zonder besluit Rogier.

## Per post (5 runs)

| Merk | Post | Verwacht | Goed (live) | Goed (replay nu) | Goed (replay +voorwaarde) |
| --- | --- | --- | ---: | ---: | ---: |
| Brandpunt Koffie | Breekt de regel | regelbreuk | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Categorietaal | categorietaal | 3/5 | 3/5 | 5/5 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | categorietaal | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Neutraal / grensgeval | neutraal | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Op merk | op_merk | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Verdedigt de regel | op_merk | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Archetype zonder bewijs | categorietaal | 0/5 | 0/5 | 4/5 |
| Duinroos Thuiszorg | Breekt de regel | regelbreuk | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Categorietaal | categorietaal | 0/5 | 0/5 | 5/5 |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | tegenkracht | 2/5 | 2/5 | 2/5 |
| Duinroos Thuiszorg | Logica van de tegenkracht | tegenkracht | 5/5 | 5/5 | 3/5 |
| Duinroos Thuiszorg | Neutraal / grensgeval | neutraal | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Op merk | op_merk | 5/5 | 5/5 | 5/5 |
| Stelwerk | Branchetaal uit de verboden logica | categorietaal | 0/5 | 0/5 | 1/5 |
| Stelwerk | Breekt de regel | regelbreuk | 5/5 | 5/5 | 5/5 |
| Stelwerk | Categorietaal | categorietaal | 5/5 | 5/5 | 5/5 |
| Stelwerk | Neutraal / grensgeval | neutraal | 4/5 | 4/5 | 4/5 |
| Stelwerk | Op merk | op_merk | 5/5 | 5/5 | 5/5 |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | op_merk | 5/5 | 5/5 | 5/5 |

## Tegenkracht-cases (foe_vervaging in flags)

### Brandpunt Koffie — Breekt de regel

Kop (referentie): «Black Friday: alle zakken van twee maanden geleden voor de halve prijs!»
Openingszin: «Black Friday: alle zakken van twee maanden geleden voor de halve prijs!»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Black Friday: alle zakken van twee maanden geleden voor de halve prijs! |
| 2 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Black Friday: alle zakken van twee maanden geleden voor de halve prijs! |
| 3 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Black Friday: alle zakken van twee maanden geleden voor de halve prijs! |
| 4 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Black Friday: alle zakken van twee maanden geleden voor de halve prijs! |
| 5 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Black Friday: alle zakken van twee maanden geleden voor de halve prijs! |

### Brandpunt Koffie — Categorietaal

Kop (referentie): «Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt.»
Openingszin: «Kom langs!»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt. |
| 5 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij ons vind je versgebrande koffie van topkwaliteit, met liefde gemaakt. Kom la |

### Duinroos Thuiszorg — Archetype zonder bewijs

Kop (referentie): «Mevrouw Jansen kreeg vandaag een bos bloemen van haar verzorgster.»
Openingszin: «Zo zien wij zorg.»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | openingszin | ja | tegenkracht | tegenkracht | categorietaal | Zo zien wij zorg. |
| 2 | openingszin | ja | tegenkracht | tegenkracht | categorietaal | Zo zien wij zorg. |
| 3 | openingszin | ja | tegenkracht | tegenkracht | categorietaal | Zo zien wij zorg. |
| 4 | openingszin | ja | tegenkracht | tegenkracht | tegenkracht | Zo zien wij zorg. |
| 5 | openingszin | ja | tegenkracht | tegenkracht | categorietaal | Zo zien wij zorg. |

### Duinroos Thuiszorg — Breekt de regel

Kop (referentie): «Goed nieuws: door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst in een maand weggewerkt!»
Openingszin: «Goed nieuws: door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst in een maand weggewerkt!»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | regelbreuk | regelbreuk | regelbreuk | door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst  |
| 2 | kop | ja | regelbreuk | regelbreuk | regelbreuk | door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst  |
| 3 | kop | ja | regelbreuk | regelbreuk | regelbreuk | door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst  |
| 4 | kop | ja | regelbreuk | regelbreuk | regelbreuk | door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst  |
| 5 | kop | ja | regelbreuk | regelbreuk | regelbreuk | door de inzet van invalkrachten uit het uitzendbureau hebben we onze wachtlijst  |

### Duinroos Thuiszorg — Categorietaal

Kop (referentie): «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»
Openingszin: «Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals.»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals. |
| 2 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals. |
| 3 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals. |
| 4 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals. |
| 5 | kop | ja | tegenkracht | tegenkracht | categorietaal | Bij Duinroos krijgt u warme, persoonlijke zorg van betrokken professionals. |

### Duinroos Thuiszorg — Kernbewering zonder bewijs (V0 v1.9)

Kop (referentie): «Onze wachtlijst is in één maand opgelost. Maar niet zoals je misschien denkt.»
Openingszin: «We hebben geen extra mensen aangenomen.»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 3 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Onze wachtlijst is in één maand opgelost. |
| 4 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Onze wachtlijst is in één maand opgelost. |

### Duinroos Thuiszorg — Logica van de tegenkracht

Kop (referentie): «Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.»
Openingszin: «Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder onderweg.»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder ond |
| 2 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder ond |
| 3 | kop | ja | tegenkracht | tegenkracht | categorietaal | Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder ond |
| 4 | kop | ja | tegenkracht | tegenkracht | categorietaal | Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder ond |
| 5 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Dankzij slimmere routes zijn onze zorgverleners elke dag een half uur minder ond |

### Stelwerk — Branchetaal uit de verboden logica

Kop (referentie): «Wij zijn volledig onafhankelijk.»
Openingszin: «Daarom kiezen steeds meer maakbedrijven voor Stelwerk.»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor St |
| 2 | openingszin | ja | tegenkracht | tegenkracht | tegenkracht | Daarom kiezen steeds meer maakbedrijven voor Stelwerk. |
| 3 | openingszin | ja | tegenkracht | tegenkracht | tegenkracht | Daarom kiezen steeds meer maakbedrijven voor Stelwerk. |
| 4 | kop | ja | tegenkracht | tegenkracht | categorietaal | Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor St |
| 5 | kop | ja | tegenkracht | tegenkracht | tegenkracht | Wij zijn volledig onafhankelijk. Daarom kiezen steeds meer maakbedrijven voor St |

### Stelwerk — Breekt de regel

Kop (referentie): «Trots: Stelwerk is nu officieel gecertificeerd resellerpartner van een groot ERP-merk!»
Openingszin: «Trots: Stelwerk is nu officieel gecertificeerd resellerpartner van een groot ERP-merk!»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Stelwerk is nu officieel gecertificeerd resellerpartner van een groot ERP-merk! |
| 2 | kop | ja | regelbreuk | regelbreuk | regelbreuk | Stelwerk is nu officieel gecertificeerd resellerpartner van een groot ERP-merk! |

### Stelwerk — Neutraal / grensgeval

Kop (referentie): «Webinar volgende week: AI in de maakindustrie, wat kun je ermee?»
Openingszin: «Webinar volgende week: AI in de maakindustrie, wat kun je ermee?»

| Run | Zone foe | foe_on_kern | Live | Replay nu | +voorwaarde | Foe-citaat (trunc.) |
| ---: | --- | --- | --- | --- | --- | --- |
| 4 | kop | ja | tegenkracht | tegenkracht | tegenkracht | AI in de maakindustrie, wat kun je ermee? |

## Geen code- of volgorde-wijziging

Deze diagnose beschrijft alleen wat de verwijdering van `&& !$kernCat` zou hebben betekend op dezelfde 95 scores. Besluit over terugzetten ligt bij Rogier.

