# Leesronde — bevindingen

**Datum:** 14 november 2026 · **Opdracht:** `docs/briefings/opdracht-0-leesronde-2026-11-14.md`
**Repo:** `erik-jihaa/brandflocks`, branch `main`, commit `81def5c`
**Uitgevoerd met:** Claude Opus 5, alleen lezen. Er is niets gewijzigd buiten dit bestand; `git status` is verder leeg.

Grondslag: `hba-pilotbesluit-v1.13-2026-10-22.md` §4 (Rogier), vier zoekpunten. Zoekpunt 5 is toegevoegd door Erik en is **niet** uitgevoerd — zie §7.

---

## 1. Samenvatting

| Ernst | Aantal | Kern |
|---|---|---|
| Moet vóór pilot | 3 | Verbodslijst-filter dekt één van de vijf AI-uitvoeren; "jullie" in zeven klantteksten; twee Engelse knoplabels in een gedeelde component |
| Kan later | 4 | Fase-aandelen dubbel, dagoffsets dubbel, twee dode routes, Engelse tekst op interne pagina's |
| Niets gevonden | 1 | Geheimen en sleutels in de repo |

---

## 2. Blokkeert livegang

Geen.

---

## 3. Moet vóór de pilot

### B1 — De verbodslijst wordt op één van de vijf AI-uitvoeren toegepast

**Zoekpunt:** 4 (ongecontroleerde tekst naar de klant)
**Bestand:** `web/api/hba-architecture-verbodslijst.php`; aangeroepen vanuit `web/api/hba-architectuur-check-repository.php:393, 416, 418, 423`

Het filter bestaat en werkt, in drie trappen: de architectuurcheck scant zijn eigen uitvoer; bij een treffer roept hij het model nog één keer aan met de correctie-instructie; staat de term er dan nog, dan wordt het **hele veld leeggemaakt** — `toelichting` en `verbetering` worden een lege string, een toon- of voorbeeldsuggestie verdwijnt uit de lijst. De term wordt dus niet vervangen en de zin niet herschreven. Geloggd wordt altijd wat het model de eerste keer zei.

Die aanroep staat alleen in de architectuurcheck. Deze vier produceren óók klantzichtbare AI-tekst en hebben geen enkele scan na afloop:

| Uitvoer | Bestand | Filter |
|---|---|---|
| Thema-oordelen | `hba-v2-repository.php` | nee |
| Artikel-hints | `hba-repository.php`, `hba-hint-verificatie.php` | nee |
| Plan-check campagne | `campagne-preview-plan-check.php` | nee |
| Research-oordelen | `hba-v2-repository.php` | nee |

Ze leunen alleen op de instructie in de prompt. Die is er wel — `campagne-preview-plan-check.php:30` verbiedt expliciet archetype, Foe, mechanisme, hoofdoordeel en slotId — maar niets controleert of het model zich eraan houdt. De Implementation Kit zegt over de verboden taal: *"Bij overtreding is de output ongeldig."* Dat kun je niet afdwingen zonder controle achteraf.

**Waarom nu:** dit is de enige bevinding waarvan de schade bij een pilotklant onomkeerbaar is. Eén thema-oordeel dat "je tegenkracht is vervaagd" zegt, is precies wat de klanttaalregels moeten voorkomen.

**Omvang van de fix:** `ce_hba_architecture_forbidden_hits_in_text()` is al generiek. Het gaat om een aanroep op het punt waar elk van de vier zijn tekst opslaat, plus dezelfde correctieronde.

### B2 — "Jullie" in zeven klantteksten

**Zoekpunt:** 3 · **Besluit:** `klanttaal-A6-beoordeling-rogier-2.md` §1 — "je" in de hele app.

| Bestand | Regel |
|---|---|
| `frontend/src/pages/SourcesPage.tsx` | 357 |
| `frontend/src/components/BrandWatcherSection.tsx` | 231 |
| `frontend/src/components/reports/ReportTrendChart.tsx` | 23 |
| `frontend/src/components/IdentityCalibrationStep.tsx` | 53, 116, 139 |
| `frontend/src/components/ThemeGenerationTips.tsx` | 21 |
| `frontend/src/components/ui/Field.tsx` | 309 |

Buiten beschouwing gelaten, omdat de tekst niet naar de klant gaat: `hbaStrategistReport.ts:56-62` (rapport voor de strateeg), `home.mock.ts:105` (testdata), `hba-architecture-verbodslijst.php:41` en `hba-architectuur-check-repository.php:355` (prompttekst), `campagne-preview-bank.php:310` (voorbeeldvraag).

**Twijfelgeval, niet zelf beslist:** `campagne-preview-templates.php:49, 79` en de spiegeling daarvan in `campagnePreview.ts:32, 50` gebruiken "jullie" in het `opdracht`-veld per fase. Of dat veld klantzichtbaar is of alleen naar de schrijfprompt gaat, is uit de code niet eenduidig op te maken. Zie §6.

### B3 — Engelse knoplabels in een gedeelde component

**Zoekpunt:** 3
**Bestand:** `frontend/src/components/StatusRow.tsx` — `>Edit<` en `>Delete<`

`StatusRow` wordt op klantpagina's gebruikt. De rest van de app zegt Bewerken en Verwijderen.

---

## 4. Kan later

### K1 — Fase-aandelen van Rogier staan op twee plekken

**Zoekpunt:** 2
**Bestanden:** `web/api/campagne-preview-templates.php` (leidend) en `frontend/src/api/campagnePreview.ts:23-55` (`CAMPAGNE_TEMPLATE_FALLBACKS`)

Alle drie de sjablonen staan compleet in beide lagen: fase-sleutels, aandelen (0,15 / 0,20 / 0,20 / 0,10 / 0,20 / 0,15 en 0,40 / 0,35 / 0,25 en 0,34 / 0,33 / 0,33), rol, merkonderdeel en de opdrachttekst. Het commentaar op regel 23 benoemt het eerlijk als fallback wanneer de API niet laadt.

Twee bezwaren. De aandelen zijn Rogiers getallen: wijzigt hij er één, dan moet dat op twee plekken en er is niets dat dat afdwingt. En de fallback is stil — laadt de API niet, dan ziet de gebruiker verouderde aandelen zonder enige melding.

**Suggestie, niet uitgevoerd:** de fallback laten vervallen en bij een mislukte API-aanroep melden dat het sjabloon niet geladen kon worden.

### K2 — Dagoffsets dubbel

**Zoekpunt:** 2
**Bestanden:** `web/api/publications-repository.php:67` (`ce_agenda_default_day_offsets`) en `frontend/src/utils/agendaCalendarGrid.ts:95`

Al bekend: het commentaar in de TypeScript verwijst naar de PHP-functie. Laagste prioriteit van de twee dubbelingen, want het gaat om weekdagen, niet om Rogiers methode.

### K3 — Twee routes zonder runtime-referentie

**Zoekpunt:** 1
`/admin/agenda-repair` en `/themes/generate-now` in `web/api/index.php`. Beide stonden al in `docs/inventaris-dode-code.md` van 6 november en hebben nog steeds nul verwijzingen in `frontend/src`, `scripts`, `json/n8n` en de overige PHP.

De andere zes routes uit die lijst hebben inmiddels wel verwijzingen; controleer vóór verwijderen alsnog de crontab en n8n op de server, zoals de inventaris zelf aangeeft.

### K4 — Engelse tekst op interne pagina's

**Zoekpunt:** 3
`frontend/src/pages/DesignSystemPage.tsx` (Submit, Edit, Back, "Editable field single line", "Example source", "Post name 1") en `Settings` in `frontend/src/pages/platform/PlatformSettingsView.tsx` en `frontend/src/components/platform/PlatformSidebarNav.tsx`.

Het design-systeem is een ontwikkelpagina en de platform-schil is de beheerderskant, niet de klant. Geen haast, wel opruimen vóór een externe demo.

---

## 5. Niets gevonden

### Zoekpunt 4a — geheimen en sleutels

Gescand op API-sleutelpatronen (`sk-ant-`, `AKIA`, `ghp_`, `xox[baprs]-`), privésleutelblokken en hardgecodeerde wachtwoorden van twaalf tekens of meer buiten voorbeeldbestanden. **Geen treffers.**

Alleen `scripts/reset-login-password.sh:6, 33` bevat een wachtwoord, en dat zijn twee regels gebruiksuitleg met een zelf in te vullen waarde.

Alle zeven `.env`-achtige bestanden in versiebeheer zijn `.example`. `.gitignore` sluit `.env` en `.env.*` uit en laat alleen de voorbeelden door. `web/api/credential-crypto.php` is versleutelingscode, geen opslag.

---

## 6. Niet zelf beslist

Voor Erik of Rogier, zoals de opdracht voorschrijft:

1. **Is het `opdracht`-veld per fase klantzichtbaar?** Bepaalt of B2 ook `campagne-preview-templates.php:49, 79` en `campagnePreview.ts:32, 50` raakt.
2. **Mag de fallback in `campagnePreview.ts` vervallen?** Zie K1.
3. **Krijgen de vier ongefilterde uitvoeren ook de herstelronde** (het model nog één keer aanroepen, kost een extra AI-call), of alleen scannen, leegmaken en loggen? Leegmaken is gratis maar laat een gat achter dat de klant ziet. Bij een thema-oordeel weegt dat zwaarder dan bij de architectuurcheck, want daar ís de toelichting het oordeel.

---

## 7. Wat niet is onderzocht

- **Zoekpunt 5 (afwijkingen van de specificatie) is overgeslagen** en uitbesteed aan **Grok**: `docs/briefings/opdracht-4-zoekpunt5-grok-2026-11-14.md`. Reden: dat vraagt de review van 13 november te bevestigen of te weerleggen, en die review is door dezelfde partij geschreven als deze leesronde.
- **`scripts/inventaris-dode-code.sh` is niet opnieuw gedraaid.** Het script loopt over de tijdslimiet van de shell heen. Zoekpunt 1 steunt daarom op de inventaris van 6 november plus gerichte controles op wat daarna is gewijzigd. Draai het script zelf voor een volledige nieuwe telling.
- **`./scripts/test-all.sh` is niet gedraaid** en de v1.13-replay dus ook niet: op deze machine is PHP niet beschikbaar via de koppeling. Dat raakt de leesronde niet, maar is wel nodig vóór elke bouwopdracht.
- **`gitleaks` is niet geïnstalleerd**; zoekpunt 4a is met eigen patronen gedaan. Een gitleaks-run over de volledige geschiedenis is nog aan te raden, want deze scan keek alleen naar de werkboom.
- **Dode kolommen in de database** zijn niet gecontroleerd; daarvoor is toegang tot een draaiende database nodig.
