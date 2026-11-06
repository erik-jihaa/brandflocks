# HBA testmerken — ronde 2 implementatieverslag

**Datum:** 9 oktober 2026  
**Promptversie:** `v1.6` (`CE_HBA_PROMPT_VERSION`)

## CE-04 (stap 0)

Zie [hba-testmerken-ce04-stap0.md](./hba-testmerken-ce04-stap0.md).

## T2 — bestaande `foe_vervaging`

In v1.5 stond **geen** aparte FLAGS-sectie; `foe_vervaging` kwam alleen als enum-waarde in het tool-schema voor. De nieuwe definitie (*tegenkracht-redenering overnemen / tegenkracht vervagen*) sluit daar inhoudelijk bij aan — geen afwijking gemeld, T1-verwijzing naar `foe_vervaging` blijft.

## T4 — wizardvoorstel (niet gebouwd)

**Regel:** Als merk-archetype gelijk is aan `markt_archetype` in `branche_library`, is brand narrative pas “af” wanneer de geloofwaardigheidstest is ingevuld **en** minstens één bewijsveld meetbaar én openbaar is.

**Waar dit zou landen:**

| Laag | Bestanden / schermen |
|------|----------------------|
| Completeness-gate | `ce_brand_narrative_completeness()` / HBA identity gate (`hba-identity-gate.php`) |
| Wizard UX | Merkarchitectuur-wizard (frontend secties rond narrative + bewijsvelden), vergelijkbaar met bestaande “narrative incomplete”-blokkades op score |
| Branche | `branche_library.markt_archetype` (al gebruikt in score/hint via `ce_hba_archetype_market_match_block`) |

## T5 — merkcijfer (/10)

- **Aangepast:** `ce_hba_v2_post_positioning_score()` — basis `(bc+consistency)/2 / 10 * 100`, risk-aftrek `risk/10 * 20`.
- **Niet gewijzigd:** `ce_hba_v2_round_pbs_display()` deelt nog door 5 voor afronding naar stappen van 5 (display-helper, geen schaal 0–10 → PBS-formule).

**Merkcijfer vóór/na op staging:** nog niet gemeten — na deploy + herimport/score invullen in [hba-testmerken-resultaat.md](./hba-testmerken-resultaat.md).

## T6 — testposts

16 posts: 12 bestaande (aangevuld met `verboden_flags` / `toegestane_flags`) + 4 nieuwe (Brandpunt 5, Stelwerk 5, Duinroos 5+6). JSON in `briefing/hba-input/HBA_testmerken/`.

## Testprotocol (matrix v1.6)

Nog uit te voeren na staging-deploy:

1. Matrix run A + B (16 posts)
2. Vergelijk met v1.5
3. CE-04 post 3 × 3 merken
4. CE-03-verleiding (research door Erik)

Resultaten → `docs/hba-testmerken-resultaat.md` (inclusief kolom `flags_verwijderd`).
