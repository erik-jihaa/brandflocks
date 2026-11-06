# Tabel per post — 19 × 5 runs (v1.12)

Gegenereerd uit `03-ruwe-json-v1.12-5runs.json` · 2026-10-19 17:41:32 UTC · prompt v1.12

**Samenvatting:** {'stable_goed': 13, 'instabiel': 3, 'review': 0, 'fout': 3}

**Logging `merkbewering` / `kern_categorietaal`:** Reconstructie: geen raw_llm in export; merkbewering/kern zoals in diagnose-replay. (veld `loggingBackfill` in JSON).

## Brandpunt Koffie — Op merk (post 1)

Verwacht hoofdoordeel: **op_merk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | op_merk | — | — | — |
| 2 | goed | op_merk | — | — | — |
| 3 | goed | op_merk | — | — | — |
| 4 | goed | op_merk | — | — | — |
| 5 | goed | op_merk | — | — | — |

## Brandpunt Koffie — Categorietaal (post 2)

Verwacht hoofdoordeel: **categorietaal** · stable: **3/5** · judgment rij: **instabiel**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | fout | tegenkracht | category_fallback, proof_gap, foe_vervaging | — | — |
| 2 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 3 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 4 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 5 | fout | tegenkracht | category_fallback, proof_gap, foe_vervaging | — | — |

## Brandpunt Koffie — Breekt de regel (post 3)

Verwacht hoofdoordeel: **regelbreuk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 2 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 3 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 4 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 5 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |

## Brandpunt Koffie — Neutraal / grensgeval (post 4)

Verwacht hoofdoordeel: **neutraal** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | neutraal | category_fallback | — | — |
| 2 | goed | neutraal | category_fallback | — | — |
| 3 | goed | neutraal | category_fallback | — | — |
| 4 | goed | neutraal | category_fallback | — | — |
| 5 | goed | neutraal | — | — | — |

## Brandpunt Koffie — Verdedigt de regel (post 5)

Verwacht hoofdoordeel: **op_merk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | op_merk | — | — | — |
| 2 | goed | op_merk | — | — | — |
| 3 | goed | op_merk | — | — | — |
| 4 | goed | op_merk | — | — | — |
| 5 | goed | op_merk | — | — | — |

## Brandpunt Koffie — Mechanisme dat markthygiëne is (post 6)

Verwacht hoofdoordeel: **categorietaal** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | categorietaal | category_fallback | — | — |
| 2 | goed | categorietaal | category_fallback | — | — |
| 3 | goed | categorietaal | category_fallback | — | — |
| 4 | goed | categorietaal | category_fallback | — | — |
| 5 | goed | categorietaal | category_fallback | — | — |

## Duinroos Thuiszorg — Op merk (post 1)

Verwacht hoofdoordeel: **op_merk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | op_merk | — | — | — |
| 2 | goed | op_merk | — | — | — |
| 3 | goed | op_merk | — | — | — |
| 4 | goed | op_merk | — | — | — |
| 5 | goed | op_merk | — | — | — |

## Duinroos Thuiszorg — Categorietaal (post 2)

Verwacht hoofdoordeel: **categorietaal** · stable: **0/5** · judgment rij: **fout**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | fout | tegenkracht | category_fallback, foe_vervaging, proof_gap | — | — |
| 2 | fout | tegenkracht | category_fallback, proof_gap, foe_vervaging | — | — |
| 3 | fout | tegenkracht | category_fallback, foe_vervaging, proof_gap | — | — |
| 4 | fout | tegenkracht | category_fallback, proof_gap, foe_vervaging | — | — |
| 5 | fout | tegenkracht | category_fallback, foe_vervaging, proof_gap | — | — |

## Duinroos Thuiszorg — Breekt de regel (post 3)

Verwacht hoofdoordeel: **regelbreuk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | regelbreuk | foe_vervaging, constraint_breach | — | — |
| 2 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 3 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 4 | goed | regelbreuk | foe_vervaging, constraint_breach | — | — |
| 5 | goed | regelbreuk | foe_vervaging, constraint_breach | — | — |

## Duinroos Thuiszorg — Neutraal / grensgeval (post 4)

Verwacht hoofdoordeel: **neutraal** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | neutraal | category_fallback | — | — |
| 2 | goed | neutraal | category_fallback | — | — |
| 3 | goed | neutraal | category_fallback | — | — |
| 4 | goed | neutraal | category_fallback | — | — |
| 5 | goed | neutraal | category_fallback | — | — |

## Duinroos Thuiszorg — Archetype zonder bewijs (post 5)

Verwacht hoofdoordeel: **categorietaal** · stable: **0/5** · judgment rij: **fout**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | fout | tegenkracht | foe_vervaging, proof_gap, category_fallback | — | — |
| 2 | fout | tegenkracht | foe_vervaging, category_fallback, proof_gap | — | — |
| 3 | fout | tegenkracht | foe_vervaging, category_fallback, proof_gap | — | — |
| 4 | fout | tegenkracht | foe_vervaging, proof_gap | — | — |
| 5 | fout | tegenkracht | foe_vervaging, category_fallback, proof_gap | — | — |

## Duinroos Thuiszorg — Logica van de tegenkracht (post 6)

Verwacht hoofdoordeel: **tegenkracht** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | tegenkracht | foe_vervaging, proof_gap | — | — |
| 2 | goed | tegenkracht | foe_vervaging, proof_gap | — | — |
| 3 | goed | tegenkracht | foe_vervaging, category_fallback | — | — |
| 4 | goed | tegenkracht | foe_vervaging, category_fallback | — | — |
| 5 | goed | tegenkracht | foe_vervaging | — | — |

## Duinroos Thuiszorg — Kernbewering zonder bewijs (V0 v1.9) (post 7)

Verwacht hoofdoordeel: **tegenkracht** · stable: **2/5** · judgment rij: **instabiel**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | fout | op_merk | — | — | — |
| 2 | fout | op_merk | — | — | — |
| 3 | goed | tegenkracht | foe_vervaging | — | — |
| 4 | goed | tegenkracht | foe_vervaging | — | — |
| 5 | fout | op_merk | — | — | — |

## Stelwerk — Op merk (post 1)

Verwacht hoofdoordeel: **op_merk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | op_merk | — | — | — |
| 2 | goed | op_merk | — | — | — |
| 3 | goed | op_merk | proof_gap | — | — |
| 4 | goed | op_merk | proof_gap | — | — |
| 5 | goed | op_merk | proof_gap | — | — |

## Stelwerk — Categorietaal (post 2)

Verwacht hoofdoordeel: **categorietaal** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 2 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 3 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 4 | goed | categorietaal | category_fallback, proof_gap | — | — |
| 5 | goed | categorietaal | category_fallback, proof_gap | — | — |

## Stelwerk — Breekt de regel (post 3)

Verwacht hoofdoordeel: **regelbreuk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 2 | goed | regelbreuk | constraint_breach, foe_vervaging | — | — |
| 3 | goed | regelbreuk | constraint_breach | — | — |
| 4 | goed | regelbreuk | constraint_breach | — | — |
| 5 | goed | regelbreuk | constraint_breach | — | — |

## Stelwerk — Neutraal / grensgeval (post 4)

Verwacht hoofdoordeel: **neutraal** · stable: **4/5** · judgment rij: **instabiel**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | neutraal | opportunistische_trendcontent, category_fallback | — | — |
| 2 | goed | neutraal | opportunistische_trendcontent, category_fallback | — | — |
| 3 | goed | neutraal | opportunistische_trendcontent, category_fallback | — | — |
| 4 | fout | tegenkracht | foe_vervaging, category_fallback | — | — |
| 5 | goed | neutraal | opportunistische_trendcontent, category_fallback | — | — |

## Stelwerk — Branchetaal uit de verboden logica (post 5)

Verwacht hoofdoordeel: **categorietaal** · stable: **0/5** · judgment rij: **fout**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | fout | tegenkracht | foe_vervaging, proof_gap | — | — |
| 2 | fout | tegenkracht | foe_vervaging, proof_gap | — | — |
| 3 | fout | tegenkracht | foe_vervaging, proof_gap | — | — |
| 4 | fout | tegenkracht | foe_vervaging, proof_gap, category_fallback | — | — |
| 5 | fout | tegenkracht | foe_vervaging, proof_gap | — | — |

## Stelwerk — Samenwerking/ervaring zonder bewijs (V0 v1.9) (post 6)

Verwacht hoofdoordeel: **op_merk** · stable: **5/5** · judgment rij: **goed**

| Run | Oordeel | Hoofdoordeel | Flags | merkbewering | kern_categorietaal |
| ---: | --- | --- | --- | --- | --- |
| 1 | goed | op_merk | proof_gap | — | — |
| 2 | goed | op_merk | proof_gap | — | — |
| 3 | goed | op_merk | proof_gap | — | — |
| 4 | goed | op_merk | proof_gap | — | — |
| 5 | goed | op_merk | proof_gap | — | — |

