# Testset artikelen — nulmeting (stap 1)

**Bron:** `briefing/cursor-betere-artikelen-bronnencoaching.md`  
**Omgeving:** staging (`https://staging.igisnext.com/`)  
**Status:** stap 1 — freeze + CE-03 op kopieën (2 okt); blind score door Erik/Rogier open.  
**Datum:** 1 oktober 2026 (samengesteld); uitvoering 2 oktober 2026

## Staging-realiteit (okt 2026)


| Tenant                   | Client-id                              | Bronnen | Opmerking                                                            |
| ------------------------ | -------------------------------------- | ------- | -------------------------------------------------------------------- |
| **iGiSNext**             | `016384af-b26e-4415-aa9c-abc46b804798` | 21      | Veel slots; bevat het gilden-/eigen-aanzet-voorbeeld uit de briefing |
| **Axiom Strategy Group** | `fc767acf-c099-be6f-38a7-4a42f25f28d4` | 22      | 2 gepubliceerde artikelen (beide in testset)                         |


**Nog niet op staging:** Doornvogel, een tenant met weinig bronnen, een tenant met vooral eigen documenten.  
Stap 1 start met **deze twee tenants**. Extra tenants later toevoegen; de nulmeting blijft de vaste 6 ids.

**Beperking om te onthouden:** beide tenants zijn zakelijke dienstverleners met ruim twintig bronnen. Wat deze testset laat zien, zegt nog weinig over een klant met weinig bronnen of een heel ander soort merk. Trek tot die tenants erbij zijn geen bredere conclusies.

---

## Vaste 6 slots (2 Axiom + 4 iGiSNext)


| #   | Tenant   | Slot-id                                | Headline (nu)                             | Waarom in de set                                    |
| --- | -------- | -------------------------------------- | ----------------------------------------- | --------------------------------------------------- |
| 1   | Axiom    | `b6a729dc-6a18-420e-89a1-f9a5fbf5693b` | De bestuurskamer die zichzelf gelooft     | Axiom; groepsdenken / bestuur                       |
| 2   | Axiom    | `51322a62-1a0d-478f-ae9e-ba5bc69fc351` | Zeventig procent van alle reorganisaties… | Axiom; symptoombestrijding, claims + bronnen        |
| 3   | iGiSNext | `90964f0e-0093-4301-953f-4f2f52823179` | De oprichter als flessenhals…             | Briefing-voorbeeld: eigen aanzet → feitelijke claim |
| 4   | iGiSNext | `e1dcab0b-129f-42c9-8c20-e86a4432a97d` | De kennis die nergens staat…              | Published; “geen kern-bron”-pad                     |
| 5   | iGiSNext | `415f356b-9f9f-4313-84b2-1833d7f9a5ef` | Wat een nieuwe medewerker in week één…    | Archived; week-één / kennis                         |
| 6   | iGiSNext | `2a8929ed-bed0-411a-84bb-f9c5f4d5e251` | Voordat je AI inzet: de drie vragen…      | Research_done + archived artikel                    |


**Niet in de set (reserve iGiS):** `47d21be4-57a0-434d-95c5-0047c08341fa` (Groei zonder structuur…) — vervangbaar als één van de vier iGiS-slots te zwak of te dubbel blijkt.

**Bevestiging Erik:** [x] 2 Axiom + 4 iGiS — [x] freeze + CE-03 (2 okt)

---

## Bevriezing (per slot, na bevestiging)

Per slot vastleggen vóór herdraai CE-03:

- `theme_id` + thema-titel  
- research-item ids (titel, URL/eigen aanzet, reliability, stance)  
- huidig model (uit CE-03 / event_log)  
- snapshot: `self_review`, HBA-score (indien aanwezig), headline + content-lengte

**Altijd op kopieën (aanscherping 2 okt, akkoord Erik).** Kopieer thema + research + leeg artikel-stub; draai CE-03 alleen op de **kopie-slot-id**. Gepubliceerde/gearchiveerde originelen blijven intact.

**Implementatie 2 okt:** kopie **binnen dezelfde tenant** (Axiom/iGiSNext), gemarkeerd met `input_human.testsetFreeze` en thematitel `[nulmeting] …`. Reden: merk/strategie/bronnen horen bij de bron-tenant; één gemengde testtenant zou CE-03-context vervuilen. Aparte testtenant blijft optioneel later.

API: `POST /api/admin/testset-freeze` (reset-token). Runbook: `./scripts/run-testset-stap1.sh`. **Geen productie.**


| #   | Bron-slot                              | Kopie-slot                             |
| --- | -------------------------------------- | -------------------------------------- |
| 1   | `b6a729dc-6a18-420e-89a1-f9a5fbf5693b` | `b61307cd-5e3e-4faf-ba17-966dd2019cdc` |
| 2   | `51322a62-1a0d-478f-ae9e-ba5bc69fc351` | `033ecfa5-f89b-4376-9c34-7b6c24333979` |
| 3   | `90964f0e-0093-4301-953f-4f2f52823179` | `15527f45-fc30-4fd0-b814-01e1f826dd30` |
| 4   | `e1dcab0b-129f-42c9-8c20-e86a4432a97d` | `32f45d1c-0024-4c55-9ee6-c73b22b5c6f0` |
| 5   | `415f356b-9f9f-4313-84b2-1833d7f9a5ef` | `aeeffc42-e45f-4a68-ae5d-d7ba504b1ee3` |
| 6   | `2a8929ed-bed0-411a-84bb-f9c5f4d5e251` | `5d482772-5bf8-4fd2-82a2-d33fb45194b5` |


**CE-01-scheduler (maandag):** kopieën tellen niet mee — thema’s `[nulmeting] …`, slots met `input_human.testsetFreeze`. Toekomstige **testtenant:** zet `clients.ce01_weekly_schedule = 0` (admin `POST /api/admin/update-client` met `ce01WeeklySchedule: false`) of schakel de n8n-schedule tijdelijk uit. Zie `scripts/generate-ce01-workflow.py` + migratie `add_ce01_weekly_schedule.sql`.

---

## Nulmeting — runlog


| Slot           | Draai-datum | Model   | self_review (kort) | HBA | Notitie     |
| -------------- | ----------- | ------- | ------------------ | --- | ----------- |
| 1              | 2026-10-02  | (CE-03) | zie `3B.md`        | —   | code 3B     |
| 2              | 2026-10-02  | (CE-03) | zie `5G.md`        | —   | code 5G     |
| 3              | 2026-10-02  | (CE-03) | zie `62.md`        | —   | code 62     |
| 4              | 2026-10-02  | (CE-03) | zie `7N.md`        | —   | code 7N     |
| 5              | 2026-10-02  | (CE-03) | zie `BU.md`        | —   | code BU     |
| 6              | 2026-10-02  | (CE-03) | zie `CM.md`        | —   | code CM     |
| 2 (tweede run) | 2026-10-02  | (CE-03) | zie `GJ.md`        | —   | toevalsmaat |
| 3 (tweede run) | 2026-10-02  | (CE-03) | zie `HO.md`        | —   | toevalsmaat |


Detail + blind codes: `docs/testset/stap1-nulmeting/*.md`, sleutel `docs/testset/sleutel.md`.

**Altijd een verse run (aanscherping 2 okt).** De nulmeting is de **ruwe uitvoer van een nieuwe CE-03-run** op de kopieën, voor alle zes. Gebruik niet de huidige tekst: slot 4 is gepubliceerd en slot 5 en 6 zijn gearchiveerd, dus die kunnen door een redacteur bewerkt zijn en zijn met oudere prompts gemaakt. Leg `content_ai` vast (wat het model schreef), niet de bewerkte `content`.

**Twee slots dubbel.** Draai slot 2 en slot 3 twee keer met exact dezelfde invoer. Het verschil tussen die twee runs is de toevalsmaat: een latere verbetering die kleiner is dan dat verschil, telt niet als verbetering.

**Modellen ongemoeid.** Tijdens stap 1 tot en met 4 blijven het schrijfmodel (CE-03) en het model van de HBA-score gelijk aan nu. Noteer beide model-ids in het runlog.

**Opslag.** Bewaar per run de volledige tekst, `self_review` en HBA-uitkomst als losse bestanden in `docs/testset/<stap>/<code>.md`. De teksten zijn klein; ze mogen in git, zodat elke vergelijking later te herhalen is.

---

## Beoordelingsblad (blind, 1–5)

Scorers: Erik + Rogier. Versie-label verbergen tot na score.

**Procedure (aanscherping 2 okt).** Elke versie van elk slot krijgt een willekeurige code (bijvoorbeeld `K7`, `M2`), zonder volgorde of stapnaam erin. De sleutel (code → slot, stap, run) staat in een apart bestand `docs/testset/sleutel.md` dat Erik en Rogier pas na het scoren openen.

**Lezen (niet via Agenda).** De app-Agenda toont je normale posts; nulmeting-kopieën horen daar niet thuis. Blind lezen kan via:

- **Staging:** [https://staging.igisnext.com/testset-nulmeting/](https://staging.igisnext.com/testset-nulmeting/) — tekst + **invulformulier** (Erik / Rogier / Eva, 1–5 per vraag; opslag via `/api/testset-nulmeting/scores`)
- **Lokaal:** `web/testset-nulmeting/index.html` (genereer met `./scripts/build-testset-blind-html.py`)

Het blad hieronder wordt **per versie** ingevuld. Erik en Rogier scoren los van elkaar en leggen hun scores daarna naast elkaar.


| Vraag                          | Slot1 | Slot2 | Slot3 | Slot4 | Slot5 | Slot6 |
| ------------------------------ | ----- | ----- | ----- | ----- | ----- | ----- |
| Eén duidelijke bewering?       |       |       |       |       |       |       |
| Onderbouwd met iets concreets? |       |       |       |       |       |       |
| Herkenbaar dit merk?           |       |       |       |       |       |       |
| Natuurlijk Nederlands?         |       |       |       |       |       |       |
| Zou ik dit posten?             |       |       |       |       |       |       |


Gemiddelde per slot + gemiddelde per vraag bij elke volgende stap herhalen.

---

## Wat níet in deze nulmeting hoort

UI-verbeteringen van 1 okt (radio → Verwerk in artikel, blauwe diff per correctie, PinkChoice) meten we **niet** in dit blad — die zijn redacteur-tools. De nulmeting meet **CE-03-output** vóór stappen 2–5 van de bronnencoaching-briefing.

---

## Volgende stap

1. **Blind score** — Erik + Rogier: codes `3B`, `5G`, `62`, `7N`, `BU`, `CM`, `GJ`, `HO` (geen sleutel openen tot na score).
2. **Daarna stap 2** briefing — bronnencoaching (2a/2b), één wijziging tegelijk t.o.v. nulmeting.

