# HBA ronde 3 — Deel 2 (app-bevindingen A/D)

**Datum:** 10 oktober 2026  
**Opdracht:** `briefing/hba-input/cursor-opdracht-ronde-3-volledig.md`

## Uitgevoerd in code

| Stap | Samenvatting |
|------|----------------|
| **A3** | CE-02 wist geen `manual_fastlane`-bronnen meer; fastlane-API meldt `ce02Triggered` vs webhook-ack; lege research na fastlane → `researchNotice` in klanttaal. **Deploy:** `generate-ce02-workflow.py` opnieuw naar n8n (`push-ce02-n8n.sh`). Hertest Black Friday-slots: nog door Erik op staging. |
| **A2** | Provisioning mapt bewijs/verboden/mechanisme/kernformuleringen/`wat_bewust_niet` correct; geen limiet van 3 op verboden meer via woorden-velden. **Herprovision:** `provision-hba-testmerken-staging.sh` met `reuseExisting`. Ronde 2/3 scores gebruikten deels onvolledige verbodenlijst → melding: herimport/score na A2 op staging. |
| **A1 + D1** | Nieuwe merkrapporten 10 okt 2026 (HBA v1.7) op staging. Brandpunt: 6 posts, 3 op_merk; Black Friday als aandachtspunt, geen herplaatsing, geen koffie-als-brandstof. Duinroos: 6 posts, 1 op_merk; wachtlijst/regelbreuk als aandachtspunt; aanbevelingen noemen team van zes, vier gezichten, planning met cliënt. CE-07-join op `branche_library` hersteld (`branche_code` / `markt_archetype`). Bij gelijke engagement wint `op_merk` als beste post. |
| **A4 + D2** | Nieuwe Duinroos-thema’s week 41 (10 okt 2026): (1) cliënt bepaalt wastijd, (2) max. vier gezichten + doorverwijzen, (3) zes per wijk. Alle drie `versterkt` terecht — elk noemt mechanisme of regel. Geen “alleen warm/menselijk”. T4+D2-fragment in thema-oordeelprompt en CE-01. A5: ritmrol-prefix wordt nu altijd overschreven vanuit het slot. |
| **A7** | Bij testmerk-provision: tone-volgorde `b to c` eerst bij consument-type. Rapport “B2B” komt deels door default tone + LinkedIn-platform in posts — geen apart rapportfilter gewijzigd. |

## Alleen gemeld (geen bouw)

### A8 — Twee sets contentrollen

| Planning (CE-01 ritme) | HBA-scoring |
|------------------------|-------------|
| autoriteit, inzicht, product-of-dienst | positionering, bewijs, activatie, service, … |

De klant ziet beide naast elkaar. **Niet samenvoegen** zonder Rogier — relationeel documenteren in kalibratiemodule later.

### A9 — Alertniveau kit

In Instellingen (tenant): geen veld voor ontspannen/alert/zeer alert gevonden. Kit-idee uit briefing **niet gebouwd** in CE-app.

### D3 — Advies vooraf agenda

Werkt goed (Brandpunt zes-wekenregel, Duinroos doorverwijzing). Referentie voor ijkset `themakeuze` — geen codewijziging.

### D4 — Ritme “5/1 per week”

**Oorzaak:** ritmemeter telde alle gepubliceerde posts deze week, inclusief HBA-nulmeting (`origin=baseline`, theme `HBA-test:%`) — vaak 5+ posts vs `posts_per_week=1`. **Fix:** `ce_home_slot_excluded_from_ritme_meter()` in `home-repository.php` (deploy staging).

## A6 — Klanttaal (Rogier)

Inventaris met voorstellen: `docs/a6-klanttaal-inventaris-voorstel.md`. **Geen UI-teksten gewijzigd** (opdracht).

## Testprotocol na A2+A3

5×17 matrix gedraaid 10 okt 2026 (~32 min, `claude-sonnet-4-6`, v1.7): **9 stable goed**, 3 instabiel, 2 fout, 3 review; `category_fallback` 10/17; klaar-criterium **Post 2 Stelwerk** niet gehaald. Zie `docs/hba-testmerken-stabiliteit.md` en `hba-testmerken-stability-v1.7-5runs.json`.

## Open R0

CE-04 post 3 per merk op staging opnieuw triggeren (n8n) — nog handmatig.
