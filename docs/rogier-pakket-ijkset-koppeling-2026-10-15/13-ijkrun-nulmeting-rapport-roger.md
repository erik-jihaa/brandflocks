# HBA ijkrun — rapport aan Rogier (nulmeting v1.10.6)

**Datum:** 16 oktober 2026  
**Omgeving:** staging · **Ijkset:** v0.2 · **Scoring prompt:** v1.10.6 · **Model:** claude-sonnet-4-6  
**Leidende run:** her-run 3 (runs 1–2 onbetrouwbaar door judge/infra)  
**Ruwe data:** `docs/hba-ijkrun-nulmeting-v1.10.6.json` · **Matrix:** `docs/hba-ijkrun-nulmeting-v1.10.6-samenvatting.md`

**Koppeldocument:** `docs/hba-ijkset-koppeling-v0.2.md` — **§7 heeft nu zeven besluiten** (7.6 en 7.7 toegevoegd na deze nulmeting).  
**Verzendpakket:** `docs/rogier-pakket-ijkset-koppeling-2026-10-15/` (+ zip), zie `00-LEESMIJ.md` toevoeging 16 oktober.

---

## Wat we van je vragen (na deze nulmeting)

1. **Besluit 7.6** — Wat moet de app doen met een merk **zonder tegenkracht** (A15)? Inhoudelijk beoordelen (*zwak*) vs weigeren vs andere keuze.
2. **Besluit 7.7** — Is `regel_is_wettelijke_plicht` een **eigen signaal**, of volstaat hygiëne op `regels`?
3. **Besluiten 7.1–7.5** — nog open waar niet afgevinkt (o.a. schrijfadvies 7.5).
4. **Inhoudelijke valkuil-fouten** waar de scorer wél draaide — met name **T13, T14, T04, T05** (zie onder).

We leveren apart een **A15-diagnose** (eenmalig, geen productwijziging) zodat je 7.6 met echte uitkomst kunt invullen. Briefing voor Erik/Cursor: `briefing/cursor-ijkrun-a15-en-aparte-routes.md` (commit `5f9b341`).

---

## Valkuil-score: drie getallen (niet één percentage)

De ijkset heeft **18 valkuil-gevallen**. Voor go-live tellen die apart; een enkel percentage misleidt als een deel **niet gedraaid** is.

| Groep | Aantal | IDs |
| --- | ---: | --- |
| **Niet gedraaid** (met reden) | **5** | **A15** (harde poort: lege tegenkracht), **V01** (CE-03 apart), **C02, C03, C06** (geen compressieroute) |
| **Gedraaid en goed** | **6** | A09, A10, A11, A12, T09, T12 |
| **Gedraaid en fout** | **5** | A13, T04, T05, T13, T14 |
| **Gedraaid, review** | **2** | S04, S05 (schrijfadvies 7.5 — deels handmatig) |

**13 van 18** valkuil-gevallen hebben een inhoudelijke run gehad. **6/13 goed** op wat de batch kon meten; **5/13 fout** waar we de app-output tegen je koppeling leggen.

*Herindeling t.o.v. JSON run 3:* A15 stond als *fout* geregistreerd maar hoort bij **niet gedraaid** — de scorer heeft het geval nooit bereikt.

---

## A15 — waarom dit geen “mapping-fix” is

**Jouw geval (Kinderopvang Zonnig):** verwacht *zwak*, met signalen **`hygienefactor_gedetecteerd`** en **`regel_is_wettelijke_plicht`**. `niet_in_geloven` is **bewust leeg**.

**Wat de app deed in de nulmeting:** de architectuur-wizard weigert met *“Vul eerst persoonlijkheid, doel en tegenkracht in”* → geen `totaaloordeel`, geen dimensies, geen van jouw signalen.

**Dat is geen uitzondering in jouw set.** Bij **A09** en **A12** is `regels` leeg; jij verwacht **`regel_ontbreekt`**. Die twee draaien in de ijkrun **goed**, omdat de harde poort alleen archetype, doel en tegenkracht checkt — niet `regels`.

**Conclusie:** bij jou is een **leeg veld een bevinding**, geen blokkade. Een melding “vul tegenkracht in” levert geen van de signalen die je bij A15 vraagt.

### Waarom de eerdere “drie opties” (placeholder / afleiden / ijk-uitzondering) wegvallen

| Optie | Waarom niet |
| --- | --- |
| Placeholder-foe invullen | Het model beoordeelt dan **onze** tekst, niet jouw ijk-input. |
| Tegenkracht afleiden uit toelichting | Zelfde probleem — en je **repareert de zwakte die getest wordt**. |
| Alleen ijkrun de poort overslaan | Daarna faalt vrijwel zeker de **identity gate** op lege tegenkracht; de inhoudelijke check draait alsnog niet. |

**Open productvraag (7.6):** wat hoort de app te doen met een merk zonder tegenkracht — in de **wizard voor echte klanten**, niet alleen in de ijkrun? Tot jij dat beslist, blijft A15 in de telling **niet gedraaid (poort)**, geen scorer-fout.

---

## Wat er klaarstaat (Erik / bouw)

Uit `briefing/cursor-ijkrun-a15-en-aparte-routes.md`:

- Ijk-velden **ongewijzigd** doorgeven (lege velden blijven leeg).
- Per architectuurcheck **`bereikt_tot`**: `harde_poort` | `identity_gate` | `inhoudelijke_check`.
- **`niet gedraaid`** scheiden van **`fout`** in runner en rapportage.
- **Eenmalige A15-diagnose** (poort overgeslagen, geen blijvend staging-gedrag) → `docs/hba-a15-diagnose.md` (nog te draaien).
- **V01** en **compressie** blijven buiten de batch; eigen resultaatbestand met **dezelfde kopvelden** (`ijkset_versie`, prompt, model, tijd) voor later één go-live-overzicht.

---

## Nulmeting batch (run 3) — kort

| Totaal 46 | goed | fout | review | niet gedraaid |
| --- | ---: | ---: | ---: | ---: |
| Alle soorten | 20 | 11* | 6 | 9 |

\*Na herindeling: **10 fout** als A15 uit de fout-telling gaat; overige fouten deels **inhoud** (T13/T14/T04/T05), deels **architectuur** (A02, A13, A16, A17), deels **S01**.

**Tekstscore valkuil (inhoudelijk):**

- **T13** — geen `proof_gap`/`foe_vervaging` op kop; BC 9 (ijk max 5).
- **T14** — `proof_gap` wel, BC 6 > 5; citaat kop/opening twijfel (handmatig).
- **T04** — `tegenkracht` i.p.v. `regelbreuk`; geen `constraint_breach`.
- **T05** — `categorietaal` i.p.v. zwak/`op_merk`-patroon.

**Nog buiten batch:** V01 (schrijver/premisse), compressie C01–C08 (route H4).

---

## Leesvolgorde voor jou

1. Dit rapport  
2. `01-ijkset-koppeling-v0.2.md` §7.6 en §7.7 (nieuw)  
3. `docs/hba-ijkrun-nulmeting-v1.10.6-samenvatting.md` (volledige matrix)  
4. Na levering: `docs/hba-a15-diagnose.md`

---

*Erik — Content Engine / HBA ronde 5*
