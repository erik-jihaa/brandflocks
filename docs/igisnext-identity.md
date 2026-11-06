# iGiSNext identity — goedgekeurde designbeslissingen

**Doel:** één referentie voor mens én agent — geen ad-hoc kleuren, geen beige knoppen, geen labels boven velden tenzij expliciet anders.

| Laag | Bestand | Gebruik |
|---|---|---|
| **CSS (canonical)** | `docs/igisnext-identity.css` | PHP-tools, standalone pagina's |
| **Kopie deploy** | `linkedin-analyse/assets/igisnext-identity.css` | sync vóór `./scripts/deploy-linkedin-analyse.sh` |
| **React / CE-app** | `frontend/src/index.css` + `components/ui/*` | Tailwind `@theme`, hergebruik componenten |
| **Live showcase** | `/design` → `DesignSystemPage.tsx` | Visuele check Artboard 20 |
| **Cursor rule CE-grid** | `.cursor/rules/content-engine-design.mdc` | Grid, scherm-specifiek |
| **Cursor rule identity** | `.cursor/rules/igisnext-identity.mdc` | Tokens + componenten (dit document) |

Wijzigingen: eerst hier + `igisnext-identity.css`, daarna React/Tailwind of tool-kopie.

---

## Kleuren

| Token | Hex | Gebruik |
|---|---|---|
| `--igis-blue` | `#033DFF` | Paginatitels, links, **tekst op groene pills** |
| `--igis-green` | `#52FFA8` | **Fill** pills, borders second, check-dots |
| `--igis-green-text` | `#1A7A4C` | Leesbare groene **tekst** op offwhite (Published, success) |
| `--igis-offwhite` | `#EAE6DC` | App-achtergrond |
| `--igis-black` | `#2E2014` | Body, ingevulde field-tekst |
| `--igis-pink` | `#F7EEF3` | Input-achtergrond |
| `--igis-beige` | `#ADA695` | Placeholders, hints, stippen, lijnen, separators `\|` |
| `--igis-beige-text` | `#6B665C` | Optioneel leesbaarder voor hints (nog niet overal ingezet) |
| `--igis-red` | `#C17156` | Fouten, Feedback-status |
| `--igis-grey` | `#89ADAA` | Neutraal / secundair |
| `--igis-white` | `#FFFFFF` | Donkere modi, neutrale dots |
| `--igis-dark-bg` | `#463B35` | Account-dark achtergrond (Settings-hoofdstuk) |

### Regels (hard)

- **Nooit** `#52FFA8` als tekstkleur op offwhite — onleesbaar.
- Groene pill (licht): **achtergrond groen, tekst blauw** — nooit groen-op-groen.
- **Geen beige pill-knoppen** — second = groene outline + blauwe tekst (licht).
- Status Published / scan-meta: `--igis-green-text`, niet mint.

---

## Typografie (Figtree 400/600)

| Element | Size / line | Kleur |
|---|---|---|
| Paginatitel (standalone tools) | 28px semibold | blauw |
| Paginatitel (CE grid) | 32/40 semibold | blauw |
| Sectiekop lijst | 24/32 semibold | zwart |
| Body / fields / lijstrij | 14/18 | zwart |
| Placeholder in field | 14px | beige |
| Actielinks (View, Delete) | 14/18 semibold | beige → hover zwart |
| Delete / emphasis link | 14/18 semibold | zwart |
| Bron-URL (Theme / Research) | 14/18 semibold | zwart + `→`, **geen underline** |

---

## Pill-knoppen

| Variant | CSS class | React |
|---|---|---|
| **Main** | `.igis-pill-main` / `.pill-primary` | `PillButton variant="main"` |
| **Second** | `.igis-pill-second` / `.pill-secondary` | `PillButton variant="second"` |
| **Disabled** | beige border + beige tekst, transparent bg | `disabled` op PillButton |

Specificaties: hoogte **40px**, radius **20px**, padding **0 30px**, border **1px** (outline), **content-breed** (nooit kolombreed).

### Breedte (hard)

- Pill-knoppen zijn **content-breed** — tekst + padding, nooit `w-full` of kolombreedte.
- In `flex-col`-forms: `self-start` op de knop (ingebouwd in `PillButton` / `pillButtonClass`).
- Gepaarde acties: horizontale rij via `pillActionsRowClass` (`items-start`).

### Pill-spacing (regelafstand)

Pill-vormen = **Button main/second** en **Pulldown** (radius 20, hoogte 40).

| Situatie | Waarde | React / CSS |
|---|---|---|
| Element → pill-vorm | **18px** (één bodyregel 14/18) | `pillLineGapBeforeClass` / `.ce-pill-line-gap-before` |
| **Copy/regel → afgerond beige kader** | **18px** (één witregel) | `frameLineGapBeforeClass` |
| Pill-vorm → lijn of volgend element | **18px** (één witregel onder elke Button) | `mb-[18px]` in `pillButtonClass` |
| **Lijn → sectie-header** (2e+ sectie in stack) | **18px** | wrapper `borderedSectionStackClass` — geen `pt` op elke sectie |
| Pill-knoppen op één rij | **18px** boven rij, **12px** tussen knoppen; knoppen zonder extra `mb` | `pillActionsRowClass` |
| Pill-rij **binnen StrategySection** | Geen `ce-pill-actions` / geen extra `mt` — wrapper levert al 18px regel→inhoud | `pillActionsRowInnerClass` |
| **Max één witregel tussen design-items** | **18px**, nooit gap + mt + mb stapelen | `designStackClass` (zet mt van de pill-rij op 0) |

Gebruik `frontend/src/design/identity.constants.ts` — gestapelde secties in `borderedSectionStackClass` of `designStackClass`, geen ad-hoc `mt-[20px]` vóór pills/pulldowns.

### Licht (offwhite `#EAE6DC`)

| Variant | Fill | Border | Tekst |
|---|---|---|---|
| **Main** | groen | groen | blauw |
| **Second** | transparent | groen | blauw |
| **Disabled** | transparent | beige | beige |

### Donker — Account-dark (`#463B35`, Settings-hoofdstuk)

Token-swap: zwart→wit, blauw→groen (accent). Roze velden blijven roze; copy in velden blijft `#2E2014`.

| Variant | Fill | Border | Tekst |
|---|---|---|---|
| **Main** | groen | groen | **`#2E2014` (donkere ink)** — nooit mint op groen |
| **Second** | transparent | groen | **groen (mint)** |
| **Disabled** | transparent | beige | beige |

React: override in `frontend/src/index.css` → `html.ce-theme-account-dark .bg-ce-green.text-ce-blue { color: #2e2014; }`.

### Wanneer second pill — en wanneer niet

| Situatie | Patroon | Voorbeeld |
|---|---|---|
| **Auth op licht** (login, forgot, platform-login) | **main + second pill** naast elkaar in `.actions` | Inloggen + **Forgot password** (verplicht); optioneel extra second (Terug naar …) |
| **Gepaarde form-acties** (ingelogd) | main + second pill | Upload logo + Delete |
| **Navigatie / hulp op donker** | text-link, geen pill | Forgot password op Account-dark |
| **Enige submit** | alleen main pill | Save, Scan starten |

#### Auth op licht (offwhite) — fout vs goed

| | Screenshot | Probleem / oplossing |
|---|---|---|
| **Fout** | `docs/design/auth-light-wrong-ce-login.png` | Login-knop **disabled** (beige outline) + Forgot password als **text-link eronder** — primaire CTA onzichtbaar |
| **Goed** | `docs/design/auth-light-good-linkedin-login.png` | **Inloggen** main (groen fill + blauwe tekst) + **Forgot password** second (groene outline + blauwe tekst) **op één rij** |

Regels auth licht:
- Login-knop **niet** disabled zolang velden leeg zijn — HTML `required` op velden; knop blijft groen (alleen disabled tijdens loading of na succes).
- Forgot password / Terug naar login = **second pill** (`pill-secondary`), geen `TextLink` onder de knop.
- **Elk login-scherm** (tenant én platform) heeft **Forgot password** als second pill naast de login-knop.

#### Auth / pills op donker (Account-dark)

| | Screenshot | Probleem / oplossing |
|---|---|---|
| **Fout** | `docs/design/pills-dark-wrong-login-forgot.png` | Login (beige/disabled) + Forgot password als groene second pill op **donkere** achtergrond |
| **Goed** | `docs/design/pills-dark-good-upload-delete.png` | Upload (main) + Delete (second) — gepaarde form-acties |

Login- en reset-schermen: **altijd licht** (`forceLightTheme`), geen Account-dark.

---

## Pulldown (menu)

| Regel | Waarde |
|---|---|
| Hoogte | 40px (zelfde als pill) |
| Radius | 20px |
| Padding | 0 30px |
| Achtergrond | transparent |
| **Outline** | **1px beige** (`border-ce-beige`) — geen 2px |
| Typografie | 14px semibold zwart |
| Selected optie | underline 1.5px |

React: `Pulldown` + `SourceTabs` — class `pulldownBoxClass` uit `identity.constants.ts`.

Spacing t.o.v. lijn/element: zelfde als pill-knoppen (**18px**, zie § Pill-spacing).

---

| Regel | Waarde |
|---|---|
| Hoogte | 40px |
| Radius | 20px |
| Padding | 0 30px |
| Achtergrond | pink |
| Placeholder | beige — **geen label boven veld** |
| Ingevoerde tekst | zwart |
| Focus | ring 1.5px blauw |
| Ruimte kop → eerste veld | 18px (`form-offset-header`) |
| Gap tussen velden | **18px** (één witregel) — `designStackClass` / `--igis-form-gap` |

React: `FieldSingle` met `placeholder` + `aria-label`, geen `label` prop tenzij strategy-velden.

---

## Strategy-secties (`StrategySection`)

Blauwe kop (24px) + optionele regel eronder + inhoud (pulldown, pills, velden).

| Regel | Waarde |
|---|---|
| Kop | 24/28 semibold blauw |
| Regel onder kop | 14/18 — **kleur hangt af van functie** (zie hieronder) |
| Kop → regel | 8px (`mt-[8px]`) |
| Regel → inhoud | 18px (één witregel) |
| Sectie-onderlijn | `borderedSectionClass` (beige lijn) |

**Lijn ↔ kop (altijd 18px):**

```
[blauwe kop]
[inhoud]
18px
── lijn (border-b) ──
18px
[blauwe kop]
```

| Richting | Class |
|---|---|
| Kop → lijn | `borderedSectionClass` op elke sectie (`pb-[18px]` vóór lijn) |
| Lijn → kop | `borderedSectionStackClass` — `[&>section+section]:pt-[18px]` |

Stack opeenvolgende `StrategyField` / `StrategySection` in één wrapper met `borderedSectionStackClass`. **Geen** extra `div` met kleinere gap ertussen — dat breekt de lijn→kop-spacing.

Tussen stacks in een pagina: `designStackClass` (18px gap) — geldt voor lijn→eerste kop van volgende blok.

**Twee soorten regel onder de kop:**

| Type | Kleur | Wanneer |
|---|---|---|
| **Leestekst** | **zwart** (`hintTone="black"`) | Uitleg/instructie die de gebruiker leest — blijft staan, geen placeholder |
| **Invoerhint** | beige (default) | Richting voor leeg veld, document-suggestie, of tekst die verdwijnt zodra ingevuld |

React: `StrategySection` met `hint` + `hintTone="black"` voor leestekst; `StrategyField` gebruikt beige alleen voor placeholders en document-suggesties.

---

## Checkbox / radio-indicator

Zelfde bolletje als `RadioOption` / `CheckboxOption`:

- 9×9px cirkel, border 1.5px zwart
- Checked: volledig zwart gevuld
- Unchecked: alleen outline

Classes: `.ce-checkbox-option` + `.ce-radio-indicator` (PHP) — zie CSS.

---

## Roze keuze-kader (1 kader)

Voor het kiezen uit bronnen, citaten of passages (o.a. Article Aandachtspunten):

| Regel | Detail |
|---|---|
| **1 kader** | Eén `bg-ce-pink` container — **geen** wit kader, schaduw of nested pink erin |
| **Copy** | Zwart (`text-ce-black`) |
| **Inspringing** | Geen — keuze-items flush met intro |
| **Radio** | Onderaan elk item: **`gebruik dit artikel`** / **`gebruik deze tekst`**; bij bronsuggesties ook **`voeg toe aan bronnen`** (beide tegelijk mogelijk) |
| **Actie** | Buiten het kader: één main pill **Verwerk in artikel** |

React: `PinkChoiceFrame` + `PinkChoiceOption` (`frontend/src/components/ui/PinkChoice.tsx`). Showcase: `/design`.

---

## Lijsten (Agenda-patroon)

| Element | Spacing / stijl |
|---|---|
| Sectie top | 10px |
| Header → eerste rij | 20px |
| Tussen rijen | 20px |
| Sectie-onderborder | 1px zwart 10% — **één lijn onder body, niet per rij** |
| Rij layout | titel links, acties rechts, **baseline** |
| Titel link | beige, hover zwart |
| Status (bijv. posts · datum) | `--igis-green-text` |
| Acties | `View` (beige) `\|` `Delete` (zwart) → tweede klik: `Confirm delete` (rood, underline bij hover) |

**Delete-bevestiging:** altijd inline in de rij — geen browser-popup. Eerste klik toont rode **Confirm delete** op dezelfde plek; pas de tweede klik voert de actie uit. React: `DeleteConfirmAction`; PHP: `data-delete-confirm` + `delete-confirm.js`.

Classes: `.list-section`, `.list-row`, `.action-link`, `.status-label`.

---

## Flash-feedback (SaveNotice)

| Type | Stijl | Fade |
|---|---|---|
| Success | groene tekst, **geen kader** | 5s optioneel |
| Error | rode tekst, geen kader | 5s optioneel |
| Info | zwarte tekst, geen kader | — |

Informatieblokken (cache-tip, Apple Mail-hint): wel `.notice` / `.notice-info` met licht kader.

---

## Twee designlagen (LinkedIn Scan)

| Laag | Stijl | Bron |
|---|---|---|
| **App** | iGiSNext identity (dit document) | `igisnext-identity.css` + `app.css` |
| **E-mailrapport** | GrowthElements document | `report-renderer.php`, beige `#EAE6DC`, Arial, tabel-layout |

Niet mixen: geen CE-grid op e-mailfragment; geen GrowthElements-underlines in app (bewust verwijderd).

---

## Logo

| Context | Bestand | Opmerking |
|---|---|---|
| **Canonical bron (beige/offwhite)** | `SVG/iGiSNext_Blue-main.svg` | Platform-logo — zelfde als ce.igisnext.com sidebar/login |
| App licht (offwhite) | `SVG/iGiSNext_Blue-main.svg` | Standaard sidebar + auth (licht); class `.platform-logo` |
| **Account-dark** (Settings-hoofdstuk) | `SVG/iGiSNext_Blue-dark.svg` | Support, Account, Integrations — **altijd iGiSNext Dark**, geen klantlogo |
| E-mail inline | `assets/iGiSNext_Blue.png` | LinkedIn Scan rapport (PNG voor mail-clients) |
| Wit op gekleurde vlakken | `SVG/iGiSNext_White.svg` | Bv. marketing, geen CE Account-dark |

**Subdomeinen (platform-logo op beige)**

| Site | Pad | Code |
|---|---|---|
| ce.igisnext.com | `/svg/iGiSNext_Blue-main.svg` | `frontend/src/components/Logo.tsx` |
| linkedin-analyse.igisnext.com | `assets/iGiSNext_Blue-main.svg` | `li_brand_logo()` in `bootstrap.php` |
| Legacy web PHP | `/svg/iGiSNext_Blue-main.svg` | `web/includes/layout.php` |

Sync na wijziging aan het master-SVG: `./scripts/sync-platform-logo.sh` (draait ook vóór deploy).

**Styling:** `.platform-logo` — max-height 32px, max-width 200px, object-fit contain (`docs/igisnext-identity.css`).

**Account-dark regel:** zodra `activeNav` in het Settings-hoofdstuk zit (`account`, `integrations`, `support`, `logout`), toont de sidebar het **platform-logo** in dark-variant — niet het geüploade klantlogo. Implementatie: `GridShell` + `Logo` met `variant="platform"` `tone="dark"`.

React-paden: `frontend/public/svg/iGiSNext_Blue-main.svg`, `iGiSNext_Blue-dark.svg`.

---

## Checklist vóór nieuwe UI

- [ ] Tokens uit `igisnext-identity.css` — geen nieuwe hex in componenten
- [ ] Main/second pills — geen beige buttons; outline **1px** (second); **content-breed**, nooit kolombreed
- [ ] Pulldowns — outline **1px beige**; **18px** spacing t.o.v. lijn/element
- [ ] Auth licht: Inloggen + Forgot password als main/second pills op één rij; login niet beige-disabled bij lege velden; Forgot password op **alle** login-schermen
- [ ] Dark mode: main = groen + donkere ink; second = groen outline + groene tekst (Upload/Delete)
- [ ] Fields: placeholder beige, typ zwart, geen label erboven
- [ ] StrategySection: leestekst onder kop **zwart**; beige alleen invoerhint
- [ ] Strategy-stack: lijn→kop **18px** (`borderedSectionStackClass`, directe `<section>`-kinderen)
- [ ] Bron-URL: zwart 14/18 + pijl →, geen underline
- [ ] Lijsten: LIST_ROW spacing, groene statustekst, View \| Delete
- [ ] Feedback: SaveNotice-stijl (typo only) vs info-box (met kader)
- [ ] CE React: hergebruik `components/ui/*` i.p.v. kopieren CSS

---

## Changelog identity

| Datum | Beslissing |
|---|---|
| 2026-08-09 | Document + CSS aangemaakt; LinkedIn Scan als eerste consumer |
| 2026-08-09 | Pills: groen fill / groen outline (geen beige second) |
| 2026-08-09 | Fields: geen labels, placeholder in beige |
| 2026-08-09 | Lijsten: Agenda LIST_ROW + View/Delete |
| 2026-08-09 | Flash notices: typo only, fade 5s |
| 2026-08-09 | Account-dark pills: main groen+ink, second groen outline+groen tekst; refs in `docs/design/` |
| 2026-08-09 | Auth licht: main+second pills op één rij (LinkedIn Scan); geen disabled login + text-link eronder |
| 2026-08-16 | Pulldown + pill second: outline **1px**; pill/pulldown spacing **18px** t.o.v. lijn/element |
| 2026-08-16 | Bron-URL Theme/Research: zwart 14/18 + pijl →, geen underline |
| 2026-08-17 | **Max één witregel (18px) tussen design-items** — geen gap+mt+mb stapelen; form-gap 18px (`designStackClass`) |
| 2026-08-09 | Forgot password verplicht op tenant- én platform-login; apart reset-flow voor platform_users |
| 2026-08-20 | StrategySection: leestekst onder kop **zwart** (`hintTone="black"`); beige alleen invoerhint/placeholder |
| 2026-08-20 | Strategy-stack: **18px** lijn↔kop via `borderedSectionStackClass`; geen nested div-gaps |
