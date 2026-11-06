# Cursor-fix — HBA en niet-HBA uit elkaar trekken

**Status:** bugfix, gaat vóór nieuwe WP's
**Datum:** aug 2026
**Raakt:** `StrategyPage.tsx`, `IdentityPage.tsx`, `TargetsPage.tsx`,
`IdentityObjectivesSection.tsx`, `BrandNarrativeFields.tsx`, `SidebarNav.tsx`
**Hoort bij:** `briefing/cursor-hba-v2-meedenker.md` §3.2 en §7.1

---

## Wat er misging

De HBA-laag is als **parallelle pagina** gebouwd (`/identity`) in plaats van als extra laag over de
bestaande schermen. Daardoor bestaan er nu van drie dingen twee versies, die uit elkaar lopen:

| Onderdeel | Versie A (niet-HBA) | Versie B (HBA) | Probleem |
| --- | --- | --- | --- |
| Merkarchitectuur-velden | `BrandNarrativeFields.tsx` | inline in `IdentityPage.tsx` | A is nu **wees** — nergens meer geïmporteerd, maar wel 250 regels onderhoud |
| Objectives | `TargetsPage.tsx` (413 regels) | `IdentityObjectivesSection.tsx` (158) | **schrijven naar dezelfde tabel met andere logica** — zie §1 |
| Invoervelden | `Field` / `Pulldown` | handgerolde `<select>` | verschillende padding en border — zie §3 |

De uitgangsregel die we hadden afgesproken is losgelaten: **de HBA-laag is additief.** Hij vervangt
geen bestaande schermen, hij hangt erboven. Alles hieronder is daarop terug.

---

## 1. Eerst dit: stille dataverlies bij objectives

`IdentityObjectivesSection.tsx` regel 32 zegt het zelf:

```
/** HBA objectives — labels only; percentages split equally on save. */
```

Beide componenten roepen dezelfde `saveObjectives()` aan. Gevolg: een tenant die op `/targets`
zorgvuldig 40 / 30 / 20 / 10 heeft ingesteld en daarna HBA aan krijgt, ziet bij de eerste save
vanuit Identity zijn verdeling **overschreven naar 25 / 25 / 25 / 25**. Zonder waarschuwing, zonder
undo.

**Fix:** `equalPercentages()` mag nooit bestaande percentages overschrijven. Verdeel alleen gelijk
bij objectives die **nieuw** zijn en nog geen percentage hebben; laat bestaande waarden staan.

En structureel: **één objectives-component, twee weergaven.** Trek de rijlogica uit `TargetsPage`
in een gedeelde component en laat beide schermen die gebruiken, met een prop
`showPercentages: boolean`. Twee implementaties van hetzelfde formulier lopen gegarandeerd uit
elkaar — dit is al de eerste keer.

Zie ook de v2-briefing §7.6.1: de percentages die de klant instelt zijn de *operationele* verdeling.
Ze verdwijnen niet bij HBA aan, ze worden alleen niet meer als slider gevraagd.

---

## 2. Bij HBA aan zijn Schrijfwijzer, Tone-styles en Auteurs onbereikbaar

`StrategyPage.tsx` regel 233:

```tsx
if (hbaEnabled) {
  return <Navigate to="/identity" replace />
}
```

De hele pagina wordt weggeredirect, inclusief drie blokken die **niets met HBA te maken hebben** en
die CE-03 wél nodig heeft:

- **Schrijfwijzer** (`writingGuide`) — algemene spelregels voor alle content
- **Schrijfstijl per tone** (`tone_styles`) — voedt Article → Tone en CE-03
- **Auteurs** (`editor_styles`) — voedt `write_directive` en Theme

Een tenant met HBA aan kan deze dus niet meer bewerken. Dat is de regressie waar de v2-briefing §3.2
voor waarschuwde: *"StyleGuide bevat meer dan de merkarchitectuur."*

**Fix — één route, drie secties, alleen de eerste is conditioneel.**

Dit is de kern en de plek waar de vorige briefing dubbelzinnig was. "Twee parallelle werelden" klopt
wél voor de merkidentiteit, en níét voor de rest. In dit scherm zitten drie onderwerpen door elkaar:

| Sectie | HBA uit | HBA aan | Parallel? |
| --- | --- | --- | --- |
| **1. Merkidentiteit** | BrandValues (vrij tekstveld) | Merkarchitectuur + begeleide flow | **ja — twee losse componenten** |
| **2. Schrijfregels** | schrijfwijzer, tone-styles, auteurs | identiek | nee, nooit splitsen |
| **3. Objectives** | met percentage-sliders | zonder sliders | nee — één component, prop `showPercentages` |

De fout was de flag over de héle pagina leggen. Daardoor werden sectie 2 en 3 meegesleurd in een
splitsing waar ze niet in horen: de schrijfwijzer werd onbereikbaar en objectives kreeg twee
save-implementaties.

**Zo bouw je het:**

```tsx
<GridShell pageTitle="Merkarchitectuur" mainLayout="grid2" main={
  <div style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
    {hbaEnabled ? <MerkarchitectuurSection /> : <BrandValuesSection />}
    <SchrijfregelsSection />
    <ObjectivesSection showPercentages={!hbaEnabled} />
  </div>
} />
```

`MerkarchitectuurSection` houdt zijn eigen twee toestanden (leeg → acht stappen, ingevuld →
overzicht met *Nog iets aanpassen*). Het parallellisme zit dus binnen één sectiegrens, niet over de
app. Dat heeft ook een commercieel voordeel: de HBA-componentboom blijft één uittilbare module, en
dat past bij gelicentieerd IP van Hero Brands.

Wat er per sectie zichtbaar is:

| Blok | HBA uit | HBA aan |
| --- | --- | --- |
| BrandValues (`brandstory`) | ✅ | vervangen door de merkarchitectuur-samenvatting |
| Schrijfwijzer | ✅ | ✅ |
| Schrijfstijl per tone | ✅ | ✅ |
| Auteurs | ✅ | ✅ |
| Story Triangle / narrative-velden | ❌ **nooit tonen** | ✅ |
| Kalibratie-voorbeelden | ❌ | ✅ |
| Branche / contextprofiel | ❌ | ✅ |
| Objectives | op `/targets`, met sliders | in dit scherm, zonder sliders |

De begeleide flow (acht stappen) is een **toestand van dit scherm**, niet een aparte route — zie
v2-briefing §3.2. Leeg → flow. Ingevuld → overzicht met *Nog iets aanpassen*.

---

## 3. Waarom de velden in elkaar lopen

Twee oorzaken, allebei mechanisch.

**3.1 Verkeerde kolombreedte.** `IdentityPage` gebruikt `max-w-[680px]` en geeft `GridShell` géén
`mainLayout`. De rest van de app doet:

```tsx
<GridShell mainLayout="grid2" main={
  <div style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>   // 740 − 220 − 20 = 500px
```

Identity is dus **180px breder** dan elk ander formulier, in een kolom die daar niet op gerekend is.
Neem het patroon van `IntegrationsPage.tsx` letterlijk over.

**3.2 Handgerolde form-controls.** `IdentityPage` schrijft eigen selects:

```tsx
className="h-[40px] w-full rounded-[20px] border-2 border-ce-beige bg-ce-pink px-[16px] …"
```

De bestaande `Field`/`Pulldown` gebruiken `border-0` en `px-[30px]`. Twee verschillen die precies
het "in elkaar lopen"-effect geven: de tekst in Identity-velden begint 14px verder naar links dan in
alle andere velden, en de 2px rand maakt de vakken optisch groter. Vervang alle handgerolde
`<select>` en `<input>` door `Pulldown` en `Field`. Geen nieuwe styling schrijven — er is een
designsysteem.

---

## 4. Opruimen

- **`StrategyPage.tsx` regel 246:** `{hbaEnabled ? (…) : (…)}` is onbereikbaar, want regel 233
  redirect al bij `hbaEnabled`. Dode tak, weg (of de redirect weg — zie §2, dan wordt deze tak juist
  weer de goede).
- **`StrategyPage.tsx` regel 187–194:** bij HBA **uit** wordt nog steeds
  `brandNarrative: brandNarrativeToPayload(savedBrandNarrative)` meegestuurd in de save. Een tenant
  zonder HBA hoort de narrative niet aan te raken. Stuur het veld alleen mee als `hbaEnabled`.
- **`BrandNarrativeFields.tsx`** is verweesd. Óf hergebruiken in het samengevoegde scherm (§2), óf
  verwijderen. Niet laten staan.
- **Nav-labels:** nu staat er `StyleGuide` naast `Brand architecture` naast `Objectives` — twee
  talen door elkaar. Beide routes mogen blijven, maar het zichtbare label wordt in beide gevallen
  **Merkarchitectuur** (WP1 van de v2-briefing). De gebruiker ziet altijd precies één item.

---

## 5. Testgevallen

Zet `clients.hba_enabled` heen en weer en controleer:

1. **HBA uit** — Merkarchitectuur toont BrandValues, Schrijfwijzer, Tone-styles, Auteurs. Geen
   enkel Story Triangle-veld, geen branche, geen contextprofiel, geen kalibratie-voorbeelden.
   `/targets` bestaat met sliders. Precies zoals vanochtend.
2. **HBA aan** — dezelfde pagina toont bovendien de merkarchitectuur; Schrijfwijzer, Tone-styles en
   Auteurs zijn er nog steeds. Objectives staat in dit scherm, zonder sliders. `/targets` is uit de
   nav.
3. **Aanzetten met bestaande data** — een tenant met 40/30/20/10 op objectives houdt na aanzetten
   én na een save vanuit Identity exact 40/30/20/10.
4. **Uitzetten** — geen HBA-veld meer zichtbaar, en de opgeslagen narrative blijft in de database
   staan (niet wissen — aanzetten moet terugbrengen wat er was).
5. **Breedte** — leg Merkarchitectuur naast Integrations. Velden beginnen op dezelfde x en zijn
   even breed.

---

## 6. Volgorde — niet alles tegelijk

De refactor uit §2 is substantieel; de databug uit §1 is klein en richt nu schade aan. Splits:

**Vandaag, los uitrollen (klein, laag risico):**

- §1 — `equalPercentages()` mag bestaande percentages niet overschrijven. Twee regels, geen
  refactor nodig, stopt het dataverlies meteen.
- §4 — `brandNarrative` alleen meesturen bij `hbaEnabled`. Eén conditie.

**Daarna, als één samenhangende refactor:**

- §2 — drie secties op één route, `MerkarchitectuurSection` en `BrandValuesSection` als losse
  componenten, gedeelde `ObjectivesSection`.
- §3 — grid2 + `GRID2_REVIEW_MAIN_WIDTH`, alle controls via `Field` / `Pulldown`.
- §4 rest — dode tak weg, `BrandNarrativeFields` hergebruiken in `MerkarchitectuurSection` of
  verwijderen, nav naar één item **Merkarchitectuur**.

Doe de refactor op staging en loop de vijf testgevallen uit §5 af met `hba_enabled` in beide
standen vóór je naar productie gaat. Het aan- en uitzetten van de vlag mag geen enkele bestaande
waarde in de database veranderen.
