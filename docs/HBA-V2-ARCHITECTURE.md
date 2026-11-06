# HBA v2 — architectuurhandoff voor correcties

**Doel:** kompas voor correcties aan de bestaande HBA-v2-code. Identity-tabs en save-regels staan in dit document (§2–§5). CE-pilotbriefings staan in **CE-Cursor**.

**Actieve specs:** `briefing/spec/README.md` (klikmodel, Core Logic v2.3, addenda, branchebibliotheek). Bouwstatus: `docs/BUILD-NOTES.md`.

---

## 1. Het ene architectuurprincipe

> **De grens ligt op het onderwerp, niet op de feature flag.**

`clients.hba_enabled` kiest **alleen** welk antwoord op “wie zijn wij?” getoond wordt. Schrijfregels en objectives horen **nooit** achter die vlag te verdwijnen of te dupliceren.

| Onderwerp | HBA uit | HBA aan | Waar |
|---|---|---|---|
| **Merkidentiteit (HBA)** | — | wizard + overzicht | `/brandarchitecture` — tab **BrandArchitecture** |
| **Merkidentiteit (CF)** | `BrandValuesSection` | verborgen (staat op BrandArchitecture) | `/styleguide` — tab **BrandElements** |
| **Schrijfregels** | schrijfwijzer, tones, auteurs | identiek | `/styleguide` — **BrandElements** |
| **Objectives** | sliders | sliders | `/targets` — **Objectives** (altijd in nav) |

**Harde eis:** aan-/uitzetten van `hba_enabled` wijzigt **geen** opgeslagen waarde. `brandstory` en `brand_narratives` blijven in de database.

**Objectives vs HBA:** de drie HBA-prompts lezen **geen** objective-percentages. Objectives beïnvloeden content via CE-03, niet via de PBS-meetlat. Zie §2 hieronder.

---

## 2. Frontend — drie Identity-tabs

### Nav (sectie Identity)

| Volgorde | Label | Route | HBA uit | HBA aan |
|---|---|---|---|---|
| 1 | BrandArchitecture | `/brandarchitecture` | verborgen | zichtbaar |
| 2 | BrandElements | `/styleguide` | zichtbaar | zichtbaar |
| 3 | Objectives | `/targets` | zichtbaar | zichtbaar |

Deeplinks: `/identity` → `/brandarchitecture`; `/strategy` → `/styleguide`.

### BrandArchitecture — `BrandArchitecturePage.tsx`

- Host: `MerkarchitectuurSection` (wizard 8 stappen + overzicht)
- Layout: `GridShell mainLayout="grid2"`, `maxWidth: GRID2_REVIEW_MAIN_WIDTH` (500px)
- Controls: `StrategyField` / `Pulldown` (geen handgerolde inputs)
- **Auto-save:** bij elke **Volgende** → `PATCH /strategy` (`brandNarrative`, incomplete OK)
- **Definitief:** **Vastleggen en beginnen** → zelfde patch + `POST /hba/architecture/complete`
- Bij `!hbaEnabled`: redirect naar `/styleguide`

### BrandElements — `StrategyPage.tsx`

```
GridShell (grid2, 500px)
├── hbaEnabled ? verwijzingsregel naar BrandArchitecture : BrandValuesSection
├── SchrijfregelsSection (writingGuide, tone_styles, editor_styles)
└── Save / Undo (geen brandNarrative in patch)
```

Save stuurt `brandstory` alleen als `!hbaEnabled`.

### Objectives — `TargetsPage.tsx`

- Enige objectives-implementatie: `ObjectivesSection showPercentages`
- Geen tweede component, geen objectives op BrandElements

### Tenant-vlag

`useTenant()` → `hbaEnabled`, `refreshTenant()` na Account-save. Consumenten: `SidebarNav`, `StrategyPage`, `BrandArchitecturePage`, `HomePage`, `ReportPage`.

---

## 3. Backend

- Tabellen: `brand_narratives`, `branche_library`, `hba_theme_verdicts`, `hba_article_hints`
- API: `hba-v2.php`, `hba-v2-repository.php`, `hba-repository.php`
- `ce_save_brand_narrative`: incomplete narrative → UPDATE huidige rij; complete → nieuwe versie
- Progress / PBS: `GET /hba/progress` → `BrandWatcherSection` op Overview + Report

---

## 4. Verwijderd — niet opnieuw bouwen

| Bestand | Reden |
|---|---|
| `IdentityPage.tsx` | Vervangen door `BrandArchitecturePage` + `MerkarchitectuurSection` |
| `IdentityObjectivesSection.tsx` | Objectives alleen op `/targets` |
| `BrandNarrativeFields.tsx` | Wizard inline in `MerkarchitectuurSection` |

---

## 5. Anti-patterns

| Fout | Gevolg | Juist |
|---|---|---|
| Wizard op `/styleguide` | BrandElements onbereikbaar bij HBA | Eigen route `/brandarchitecture` |
| Objectives op BrandElements bij HBA aan | Dubbele save, % overschrijven | Alleen `/targets` |
| `/styleguide` redirecten bij HBA | Schrijfregels kwijt | BrandElements altijd open |
| Alleen opslaan op stap 8 | Logout = data kwijt | Auto-save per Volgende |
| `brandNarrative` meesturen zonder HBA | Narrative per ongeluk wissen | Alleen vanuit BrandArchitecture |

---

## 6. Testgevallen (kort)

1. HBA uit — nav: BrandElements + Objectives; geen Story Triangle-velden.
2. HBA aan — drie tabs; BrandElements zonder BrandValues, mét verwijzingsregel; Objectives met sliders.
3. Toggle — objectives % en `brandstory` / narrative blijven in DB.
4. Wizard — halverwege Volgende, logout, terug → voortgang herladen uit `brand_narratives`.
5. Breedte — BrandArchitecture naast Integrations: zelfde kolom-x (500px).
