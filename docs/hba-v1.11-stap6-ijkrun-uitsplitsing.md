# HBA ronde 6 — stap 6: ijkrun-uitsplitsing (v1.11)

**Run:** 2026-10-18 14:45:06 UTC · **Ijkset:** v0.3 · **Prompt:** v1.11  
**Omgeving:** staging · **Geen wijziging** aan ijkset of judge (meting).  
**Ruwe data:** `hba-ijkrun-nulmeting-v1.11.json` · **Log:** `hba-ijkrun-nulmeting-v1.11.log`  
**Baseline:** hba-ijkrun-nulmeting-v1.10.8.json

**Vergelijking v1.10.8 → v1.11:** goed None→6, fout None→26, review None→5. Judgment-wijziging: **T10** goed→fout (hoofdoordeel v1.11), **S01** fout→review.

**A09–A13:** nog steeds fout; meetlat-as **goed** (afgeleide status klopt), falen op signaal/oordeel (hygiëne/regel-signalen, geen `meetlat_geblokkeerd`-signaal in output).

## Telling

| Uitslag | Aantal |
| --- | ---: |
| goed | 6 |
| fout | 26 |
| review | 5 |
| niet gedraaid | 9 |

## Fouten per as (primair)

| As | Aantal |
| --- | ---: |
| oordeel | 8 |
| meetlat | 3 |
| signaal | 15 |

## Fout door judge-regel v0.3 vs inhoud

| Categorie | Aantal fout-gevallen |
| --- | ---: |
| Alleen judge-regel (v0.3-rubric) | 3 | A12, A15, A16 |
| Mix judge + inhoud | 12 | A04, A05, A06, A07, A08, A09, A10, A11, A13, A14, A17, T13 |
| Inhoud (model/score/architectuur) | 11 | A01, A02, A03, A18, T04, T05, T06, T08, T10, T11, T14 |

**Meetlat afleiding** (verwacht actief/geblokkeerd ≠ `meetlat_status`): 3 — A07, A14, A17.

Heuristiek judge-regel: meetlat-verwacht mismatch, `meetlat_geblokkeerd`-signaal, “alleen melding zonder oordeel”, T13/T14-rubric.

## Alle fout-gevallen

| ID | As | Judge/inhoud | Verwacht | App | Redenen |
| --- | --- | --- | --- | --- | --- |
| A01 | signaal | inhoud | oordeel=sterk; meetlat=actief | totaal=meetlat_klaar; meetlat=actief | verplicht signaal ontbreekt: onderscheid_herkend; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend |
| A02 | signaal | inhoud | oordeel=sterk; meetlat=actief | totaal=meetlat_klaar; meetlat=actief | verplicht signaal ontbreekt: onderscheid_herkend; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend |
| A03 | signaal | inhoud | oordeel=sterk; meetlat=actief | totaal=meetlat_klaar; meetlat=actief | verplicht signaal ontbreekt: onderscheid_herkend; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend |
| A04 | signaal | mix | oordeel=aanscherpen; meetlat=geblokkeerd | totaal=meetlat_bruikbaar_met_waarschuwing; meetlat=geblokkeerd | verplicht signaal ontbreekt: hygienefactor_gedetecteerd op wat_anders en niet_in_geloven; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend; verplicht signaal ontbreekt: advies_wijst_regel_aan_als_onderscheid; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A05 | signaal | mix | oordeel=aanscherpen; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd | verplicht signaal ontbreekt: doel_generiek; verplicht signaal ontbreekt: regel_zonder_commerciele_prijs; verplicht signaal ontbreekt: deels_onderscheid_herkend; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A06 | signaal | mix | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd | verplicht signaal ontbreekt: doel_generiek; verplicht signaal ontbreekt: hygienefactor_gedetecteerd; verplicht signaal ontbreekt: regel_zonder_commerciele_prijs; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A07 | meetlat | mix | oordeel=aanscherpen; meetlat=geblokkeerd | totaal=meetlat_klaar; meetlat=actief | totaaloordeel meetlat_klaar (verwacht aanscherpen); verplicht signaal ontbreekt: onderscheid_herkend; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend; verplicht signaal ontbreekt: meetlat_geblokkeerd met oordeel en advies; verboden signaal: alleen een melding zonder inhoudelijk oordeel; meetlat actief (verwacht: geblokkeerd) |
| A08 | signaal | mix | oordeel=aanscherpen; meetlat=geblokkeerd | totaal=meetlat_bruikbaar_met_waarschuwing; meetlat=geblokkeerd | verplicht signaal ontbreekt: regel_is_geen_grens; verplicht signaal ontbreekt: advies_herformuleer_regel_als_grens; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A09 | signaal | mix | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd; signalen=regelOntbreekt | verplicht signaal ontbreekt: hygienefactor_gedetecteerd; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A10 | signaal | mix | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd | verplicht signaal ontbreekt: regel_zonder_commerciele_prijs; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A11 | signaal | mix | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd | verplicht signaal ontbreekt: regel_zonder_commerciele_prijs; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A12 | signaal | judge-regel (v0.3) | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd; signalen=regelOntbreekt,identityDoelWrongLevel | verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A13 | signaal | mix | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd | verplicht signaal ontbreekt: hygienefactor_gedetecteerd; verplicht signaal ontbreekt: regel_zonder_commerciele_prijs; verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A14 | meetlat | mix | oordeel=aanscherpen; meetlat=geblokkeerd | totaal=meetlat_klaar; meetlat=actief | totaaloordeel meetlat_klaar (verwacht aanscherpen); verplicht signaal ontbreekt: hygienefactor_gedetecteerd; verplicht signaal ontbreekt: regel_zonder_commerciele_prijs; verplicht signaal ontbreekt: meetlat_geblokkeerd met oordeel en advies; verboden signaal: alleen een melding zonder inhoudelijk oordeel; meetlat actief (verwacht: geblokkeerd) |
| A15 | signaal | judge-regel (v0.3) | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd; signalen=tegenkrachtOntbreekt | verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A16 | signaal | judge-regel (v0.3) | oordeel=zwak; meetlat=geblokkeerd | totaal=meetlat_te_zwak; meetlat=geblokkeerd | verboden signaal: alleen een melding zonder inhoudelijk oordeel |
| A17 | meetlat | mix | oordeel=sterk; meetlat=actief | totaal=meetlat_bruikbaar_met_waarschuwing; meetlat=geblokkeerd; signalen=identityTegenkrachtWrongLevel | verplicht signaal ontbreekt: onderscheid_herkend; verplicht signaal ontbreekt: methodische_breuk_met_branche_herkend; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend; meetlat geblokkeerd (verwacht: actief) |
| A18 | signaal | inhoud | oordeel=sterk; meetlat=actief | totaal=meetlat_klaar; meetlat=actief | verplicht signaal ontbreekt: markt_archetype_gelijk_aan_merk_herkend; verplicht signaal ontbreekt: hygienefactor_gedetecteerd op doel; verplicht signaal ontbreekt: regel_draagt_bewijslast; verplicht signaal ontbreekt: regel_met_commerciele_prijs_herkend |
| T04 | oordeel | inhoud | oordeel=regelbreuk | hoofdoordeel=neutraal; BC=0; flags=foe_vervaging | hoofdoordeel neutraal (verwacht: regelbreuk); Ontbreekt verwachte flag: constraint_breach |
| T05 | oordeel | inhoud | oordeel=zwak | hoofdoordeel=categorietaal; BC=1; flags=category_fallback,proof_gap | hoofdoordeel categorietaal (verwacht: op_merk) |
| T06 | oordeel | inhoud | oordeel=neutraal | hoofdoordeel=categorietaal; BC=1; flags=category_fallback | hoofdoordeel categorietaal (verwacht: neutraal) |
| T08 | oordeel | inhoud | oordeel=neutraal | hoofdoordeel=categorietaal; BC=1; flags=category_fallback,foe_vervaging | hoofdoordeel categorietaal (verwacht: neutraal) |
| T10 (was goed op baseline) | oordeel | inhoud | oordeel=middelmatig | hoofdoordeel=op_merk; BC=3; flags=proof_gap,category_fallback | hoofdoordeel op_merk (verwacht: categorietaal) |
| T11 | oordeel | inhoud | oordeel=neutraal | hoofdoordeel=categorietaal; BC=1; flags=category_fallback,proof_gap | hoofdoordeel categorietaal (verwacht: neutraal) |
| T13 | oordeel | mix | oordeel=zwak | hoofdoordeel=op_merk; BC=9; flags= | hoofdoordeel op_merk (verwacht: tegenkracht); Geen van verwachte flags: proof_gap / foe_vervaging; merkbijdrage 9 boven max 5; verwacht_niveau laag: distinctiviteit 5 boven 2; Geen proof_gap/foe_vervaging op kop (T13 valkuil) |
| T14 | oordeel | inhoud | oordeel=zwak | hoofdoordeel=op_merk; BC=6; flags=proof_gap | merkbijdrage 6 boven max 5; verwacht_niveau laag: distinctiviteit 4 boven 2 |

## A17 — Ter Horst (dimensie aanscherpen)

**Judgment:** fout (oordeel-as: fout, meetlat-as: fout)

- **relevantieTegenkracht** — Beschrijf een overtuiging of aanname uit jullie vak, geen concurrent of toezichthouder.
