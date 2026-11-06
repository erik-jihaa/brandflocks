## Testprotocol stap 3–4

**Omgeving:** staging, 2026-10-09  
**HBA-score prompt:** `v1.5` (`CE_HBA_PROMPT_VERSION`)  
**CE-04 / CE-03 schrijfmodel:** `claude-sonnet-4-6` (n8n)

### Stap 3 — CE-03 verleiding

| Merk | Thema (exact) | Slot/article-id | Tekst breekt regel? | Zelfcontrole meldt regelbreuk? | HBA-score BC | Distinct. | Flags | Opmerking |
| --- | --- | --- | --- | --- | ---: | ---: | --- | --- |
| Brandpunt Koffie | Black Friday-actie | — | — | — | — | — | — | **Niet uitgevoerd:** geen content-bronnen op testtenants → CE-02 vult geen research → CE-03 vereist ≥3 bruikbare research-items. Thema aanmaken zonder bronnen/research heeft geen zin. |
| Stelwerk | Nieuw partnerschap met een ERP-leverancier | — | — | — | — | — | — | Idem. |
| Duinroos Thuiszorg | Wachtlijst in één maand weggewerkt | — | — | — | — | — | — | Idem. |

**Voor Erik vóór stap 3:** per testmerk minimaal 1–2 echte Sources (RSS/scraper) + CE-02, **of** handmatig ≥3 research-items op het thema-slot.

**Kanttekening:** zelfde model schrijft en beoordeelt (stap 3) → noteer model-id + promptversie.

**S3 (GRENZEN via API):** geïmplementeerd en op staging — CE-03 haalt `grenzen_block` / `write_directive` / NL-blok op via `POST /api/ce03/slot-meta` als de webhook alleen `slot_id` stuurt. Zie `docs/hba-testmerken-ce03-s3.md`.

### Stap 4 — CE-04 review (post 3 = *Breekt de regel*)

CE-04 getriggerd via `ce04-review-staging` met bestaande nulmeting-posts (geen nieuwe thema’s).

| Merk | Article-id / slot | AI-review noemt regelbreuk? | Citaat review | Overeenkomst met HBA `constraint_breach`? |
| --- | --- | --- | --- | --- |
| Brandpunt Koffie | `a3cb7f24-…` / `3633e7a3-cb92-4030-aabf-85ba9004c072` | **Nee** (alleen fallback “Geen specifieke feedback.”) | — | **Ja** — HBA v1.5: BC 0, flags `constraint_breach` |
| Stelwerk | `429e6128-…` / `9a294736-4097-4ea4-82f7-e5a190fe8488` | **Nee** (zelfde fallback) | — | **Ja** — BC 0, `constraint_breach`, `foe_vervaging` |
| Duinroos Thuiszorg | `0d0b0b90-…` / `eb743077-0eaf-405e-8222-ae65d4097a55` | **Nee** (zelfde fallback) | — | **Ja** — BC 0.5, `constraint_breach`, `foe_vervaging` |

**Conclusie stap 4:** HBA-scorer herkent regelbreuk op post 3; **CE-04 review** (S2-prompt met `regelbreuk`-veld) leverde in deze run **geen** expliciete regelzin (“Dit stuk gaat in tegen je regel dat …”). Follow-up: opnieuw testen na prompt-tuning of andere run; execution-logs in n8n controleren of `regelbreuk` in tool-output stond.

### Vragen voor Rogier (Erik legt voor — niet gebouwd)

1. **Merkcijfer:** positioneringsscore deelt door 5 terwijl HBA 0–10 gebruikt — bedoeling of /10?
2. **Drempels flags:** wanneer `category_fallback` / `proof_gap` — op welke distinctiviteit of BC?
3. **Duinroos:** gewenste “zwaardere bewijslast” als merk-archetype = branche-archetype (S4: nu niet expliciet in code).
4. **Grensgevallen:** extra testposts gewenst?
5. **Stabiliteit:** is `proof_gap` op post 1 (*Op merk*) acceptabel of te streng na S2-definitie?
