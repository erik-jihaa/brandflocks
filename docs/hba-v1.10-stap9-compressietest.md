# HBA — stap 9: compressietest (geen runner)

**Bron:** Erik §9 in `briefing/hba-input/hba-reactie-v1.10-en-ijkset-v0.2.md`.  
**Besluit Rogier (7.4, 16 okt):** nulmeting **zonder** compressie; route daarna; go-live **mét** C02/C03/C06.

## Klaar-criterium

- [x] Bewezen: er is geen compressietest-runner in de app.
- [x] Drie lookalikes zijn geen vervanging (CE-03 posterwoord, addendum H4, architectuurcheck).
- [x] C01–C08 + valkuilen C02/C03/C06 staan in één tabel.
- [x] Geen bouw — dat is stap ná de ijkrun (38 gevallen).

## Wat de ijkset vraagt

Input per geval: `branche` + `merkbeschrijving`.  
Uitvoer: oordeel `positionerend` | `opgelost` | `grensgeval`, plus signalen of een **samenvatting** het onderscheid behoudt of oplost in categorietaal.

Dat is een **eigen route**: merkbeschrijving comprimeren en toetsen of de positionering overleeft. Geen tekstscore, geen architectuurcheck, geen poster.

## Wat er wél in de code zit (en waarom het dit niet is)

| Plek | Wat het doet | Waarom geen C01–C08 |
| --- | --- | --- |
| CE-03 `poster_keyword_pair` | “Extreemste compressie tot twee woorden” op een **artikelkop** (titelposter) | Andere input, andere output, andere bedoeling |
| Addendum **H4** (`cursor-hba-architectuurcheck-branche-afleider.md`) | Gefaseerde **branche-afleider**: onbekende branche → `branche_library` (`bron=afgeleid`) | Leidt hygiëne/definitie af; beoordeelt geen merkbeschrijving-samenvatting |
| Architectuurcheck (addendum H3) | Meetlat op wizardvelden (`totaaloordeel`, dimensies) | Andere input (`wie`/`doel`/`regel`), andere labels (`sterk`/`aanscherpen`/`zwak`) |
| `CE_HBA_FN_BRANCHE_AFLEIDER` | Alleen model-config-constante | Geen runner, geen prompt, geen endpoint |

Zoek in `web/api`: geen `compressietest`, geen `samenvatting_behoudt_*`, geen oordeel `positionerend`/`opgelost`/`grensgeval` als ijk-route.

## Gevallen C01–C08

| Id | Merk | Verwacht | Valkuil | Wat het toetst |
| --- | --- | --- | --- | --- |
| C01 | Veldwerk | `positionerend` | nee | Isolatie-eerst overleeft compressie |
| C02 | De Boer | `opgelost` | **ja** | Volledig categorietaal |
| C03 | Doornvogel origineel | `opgelost` | **ja** | “Loyaliteitsbureau” = de branche |
| C04 | Doornvogel aangescherpt | `grensgeval` | nee | Alleen toelatingseis overleeft |
| C05 | Ter Horst | `positionerend` | nee | Geen pakketten / prijsconventie |
| C06 | Nexa IT | `opgelost` | **ja** | Partner/maatwerk = IT-categorie |
| C07 | Brander Oost | `grensgeval` | nee | Onderscheid staat erin maar verdwijnt |
| C08 | Hoeve Stil | `positionerend` | nee | Afwezigheid als aanbod |

**Go-live:** 18 valkuilen, waarvan 3 zonder route (C02, C03, C06). Draaibaar nu: **15**. Totaal draaibaar: **38 / 46**.

## Besluit (Rogier 7.4)

1. Eerste ijkrun = 38 gevallen; C01–C08 als *niet gedraaid*.
2. Daarna pas de compressie-route bouwen (niet addendum H4 hergebruiken).
3. Go-live pas als C02/C03/C06 100% goed gaan.

## Bewust niet gedaan

Geen prompt, geen endpoint, geen import, geen ijkrun-code.
