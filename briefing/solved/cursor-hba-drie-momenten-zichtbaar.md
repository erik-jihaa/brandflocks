# Cursor-briefing — de drie HBA-momenten zichtbaar maken in CF-design

**Status:** presentatie-fix op bestaande, werkende code — plus één functioneel gat
**Datum:** aug 2026
**Aanleiding:** feedback Rogier — de drie ingrijppunten (adviseur vooraf, co-auteur tijdens,
bewaker achteraf) zijn niet herkenbaar in de app
**Raakt:** `ThemePage.tsx`, `ArticleHbaHintSection.tsx`, `BrandWatcherSection.tsx`

---

## 0. Eerst dit: het is al gebouwd

Alle drie de ingrijppunten draaien:

| Moment | Waar | Bestaat |
| --- | --- | --- |
| 1 — adviseur vooraf | `ThemePage.tsx` → `runThemeVerdicts()`, `verdicts`, `watOntbreekt` | ✅ |
| 2 — co-auteur tijdens | `ArticlePage.tsx` → `generateArticleHint()` / `applyArticleHint()`, `ArticleHbaHintSection` | ✅ |
| 3 — bewaker achteraf | `BrandWatcherSection` — compact op Overview via `BrandProgressCard`, expanded op Report | ✅ |

Dit is dus **geen bouwopdracht maar een vormgevingsopdracht**. De laag doet zijn werk; de klant ziet
alleen niet dát er iets gebeurt, omdat het oordeel eruitziet als willekeurige bodytekst. Eén
uitzondering: §2.2 is een echt functioneel gat.

**Rogier's schermen zijn schetsen.** Neem de *informatiehiërarchie* over, niet de styling. Gebruik
uitsluitend bestaande CF-tokens uit `index.css` en de klassen uit `grid.constants.ts` — geen nieuwe
kleuren, geen nieuwe radii, geen handgerolde controls. Dat is precies wat op IdentityPage misging.

### CF-tokens die je hier nodig hebt

| Token | Waarde | Gebruik hier |
| --- | --- | --- |
| `ce-black` | `#2e2014` | bodytekst, oordeeltekst |
| `ce-beige` | `#ada695` | secundaire tekst, disclaimers |
| `ce-red` | `#c17156` | accentbalk advies, regel-overtreding |
| `ce-orange` | `#d4845c` | pill "verwatert" |
| `ce-green-text` | `#1a7a4c` | pill "versterkt" (tekst, niet vulling) |
| `ce-pink` | `#f7eef3` | zachte blokachtergrond, citaat |
| `ce-offwhite` | `#eae6dc` | pill "neutraal" |
| `ce-blue` | `#033dff` | links, PBS-cijfer |

Verder: `rounded-[20px]` voor blokken, `rounded-[12px]` voor zachte binnenblokken,
`text-[14px] leading-[18px]` voor body, `border-b border-ce-black/10` voor scheidingslijnen,
`PillButton` voor knoppen.

---

## 1. Moment 1 — adviseur vooraf (`/theme`)

### Wat er nu staat

```tsx
{watOntbreekt ? (
  <p className="m-0 mb-[16px] text-[14px] leading-[18px] text-ce-black">{watOntbreekt}</p>
) : null}
```

Een kale alinea. Geen label, geen kader, niets dat zegt dat dit een advies is. De klant leest er
overheen.

En per thema:

```tsx
<div className="mb-[8px] flex flex-col gap-[4px]">
  <span className={`… ${verdictPillClass(verdict.label)}`}>{verdict.label}</span>
  <p className="… text-[13px] text-ce-beige">{verdict.toelichting}</p>
</div>
```

De pill staat bóven de titel in plaats van ernaast, en de toelichting staat in beige — de kleur die
in CF "minder belangrijk" betekent. Het oordeel oogt dus als bijzaak terwijl het de kern is.

### Wat er moet veranderen

**1.1 Het agenda-advies wordt een blok met accentbalk.** Zelfde vorm als Rogier's schets, in
CF-tokens:

```tsx
<div className="mb-[20px] border-l-2 border-ce-red bg-ce-pink px-[16px] py-[12px]">
  <p className="m-0 mb-[4px] text-[14px] leading-[18px] text-ce-beige">Advies voor je agenda</p>
  <p className="m-0 text-[14px] leading-[18px] text-ce-black">{watOntbreekt}</p>
</div>
```

Let op: **geen `rounded`** op dit blok. CF-regel — een enkelzijdige rand gaat niet samen met
afgeronde hoeken.

**1.2 De pill gaat naar rechts, op de titelregel.** `ThemeReviewRow` heeft al een metakolom
(`GRID_META.widthClass`, absolute positionering rechts op `top-[20px]`). Zet de pill daar, op één
lijn met de titel. Dat is precies wat Rogier tekende én het bestaande CF-patroon.

**1.3 De toelichting wordt gewone bodytekst**, niet beige: `text-[14px] leading-[18px] text-ce-black`
onder de motivatie. Het is een oordeel, geen voetnoot.

**1.4 De pill-kleuren moeten rustiger.** Nu:

```
versterkt: bg-ce-green-fill text-ce-blue    ← #52ffa8, felgroene vulling
verwatert: bg-ce-pink text-ce-black         ← bijna onzichtbaar
```

Dat felle groen is precies het goed-fout-alarm waar Rogier voor waarschuwt, en "verwatert" valt
juist weg. Draai het om naar outline-pills, gelijk gewicht, verschil zit in de kleur van rand en
tekst:

```
versterkt: border border-ce-green-text text-ce-green-text bg-transparent
neutraal:  border border-ce-beige      text-ce-beige      bg-transparent
verwatert: border border-ce-orange     text-ce-orange     bg-transparent
```

Alle drie `rounded-full px-[10px] py-[4px] text-[12px] font-semibold`. Geen vinkje, geen icoon.

**1.5 Sluitregel toevoegen**, onder de laatste themarij:

```tsx
<p className="m-0 pt-[20px] text-[14px] leading-[18px] text-ce-beige">
  Je kiest zelf welk thema je pakt — dit is advies, geen verplichting.
</p>
```

Die zin is niet decoratief. Hij is de reden dat de klant de laag als meedenker ervaart en niet als
controleur.

---

## 2. Moment 2 — co-auteur tijdens (`/article`, `/fastlane`)

### 2.1 Presentatie

Nu staat er `border-2 border-ce-green` — een felgroene rand van 2px rond het hele blok. Dat schreeuwt,
en groen betekent in CF "gelukt", terwijl dit een aanwijzing is.

Vervang door hetzelfde patroon als moment 1, zodat de klant de laag herkent:

```tsx
<section className="mb-[16px] border-l-2 border-ce-red bg-ce-white p-[16px]">
  <p className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-red">
    Positionering scherper maken
  </p>
  {hint.regelOvertreden && (
    <p className="m-0 mb-[10px] text-[14px] font-semibold leading-[18px] text-ce-red">
      {hint.regelOvertreden}
    </p>
  )}
  <p className="m-0 text-[14px] leading-[18px] text-ce-black">{hint.aanwijzing}</p>
  …
</section>
```

De kop **Positionering scherper maken** ontbreekt nu volledig. Zonder die kop weet de klant niet wie
er praat of waarover.

Onder de knoppen de sluitregel, in `text-ce-beige`:

> Eén aanwijzing per keer — de belangrijkste. Jij houdt de regie.

### 2.2 Functioneel gat — de klant klikt nu blind op "Pas toe"

**Dit is de belangrijkste bevinding van deze briefing.**

`HbaArticleHint` levert `passageVoor`, `passageNa`, `headlineVoor`, `headlineNa`, `ctaVoor`,
`ctaNa`. `ArticleHbaHintSection` rendert daar **niets** van — alleen `aanwijzing`. De klant drukt
dus op **Pas toe** zonder te zien welke zin wordt vervangen en waardoor.

Dat ondermijnt de belofte "jij houdt de regie", en het is de reden dat het moment niet als
co-auteurschap voelt. Twee dingen bouwen:

**Toon de voorgestelde tekst vóór de knoppen**, als citaatblok:

```tsx
{hint.passageNa && (
  <blockquote className="m-0 mt-[12px] rounded-[12px] bg-ce-pink px-[12px] py-[10px]
                         text-[14px] italic leading-[18px] text-ce-black">
    {hint.passageNa}
  </blockquote>
)}
```

Idem voor `headlineNa` en `ctaNa` als die gevuld zijn, elk met een klein label in `ce-beige`
(*Kop* / *Call to action*) zodat duidelijk is wát er vervangen wordt.

**Markeer `passageVoor` in de editor.** Rogier tekent de te vervangen zin met een zachte
onderstreping in de tekst. Dat is de helft van de waarde van dit moment: je ziet waar het misgaat,
niet alleen dat het misgaat. Gebruik `bg-ce-pink` met `underline decoration-ce-red` op de
gevonden passage in `ArticleEditorBodyField`. Lukt de match niet exact, sla de markering dan over —
nooit gokken en de verkeerde zin markeren.

### 2.3 Knoppen

`Pas toe` is al een `PillButton variant="main"`. Laat `Negeer` een `PillButton variant="second"`
worden in plaats van een kale onderstreepte link — twee gelijkwaardige keuzes horen er ook
gelijkwaardig uit te zien, en dat onderstreept dat negeren legitiem is.

---

## 3. Moment 3 — bewaker achteraf (Overview + Report)

Dit staat er het dichtst bij. `BrandWatcherSection` heeft de ring, de opbouwtellers, de PBS in
48px `ce-blue` en de trendgrafiek. Drie kleine dingen:

**3.1 Het signaal krijgt dezelfde accentbalk.** De erosion alert staat nu in
`rounded-[12px] bg-ce-pink` — visueel gelijk aan een gewoon informatieblok. Geef hem
`border-l-2 border-ce-red` en haal de `rounded` weg, zodat "iets vraagt aandacht" er anders uitziet
dan "hier is een cijfer". Nu zijn ze niet te onderscheiden.

**3.2 De duidingzin naast het cijfer.** Rogier zet onder de 72 in gewone taal *"Je merk staat
scherp"*. Die zin is er (regel ~217) — controleer dat hij ook in de `compact`-variant op Overview
verschijnt en niet alleen op Report. Het cijfer alleen zegt de klant niets; de zin is de hele
boodschap.

**3.3 Eén weg naar de diepte.** Onderaan het Overview-blok:
*Meer details en de volledige merkanalyse → open het rapport*. Alle detailtrackers blijven op
Report. Overview stelt gerust, Report legt uit.

---

## 4. Waar de drie momenten samen zichtbaar worden

Rogier's punt is niet alleen dat elk moment goed oogt, maar dat de klant **herkent dat het dezelfde
laag is** die op drie plekken meedenkt. Daarom één visuele handtekening, consequent:

- **Rode linkeraccentbalk (`border-l-2 border-ce-red`, geen afronding)** = de HBA-laag zegt iets.
  Op Themes het agenda-advies, op Article de aanwijzing, op Overview het signaal.
- **Outline-pill** = een oordeel over één ding.
- **Sluitregel in `ce-beige`** = jij beslist.

Meer niet. Geen logo, geen aparte kleur, geen "HBA"-label — dat zou vaktaal zijn (WP1).

---

## 5. Acceptatiecriteria

- Op `/theme` staat het agenda-advies in een blok met rode accentbalk en het label *Advies voor je
  agenda*; elk thema heeft rechts een outline-pill op de titelregel en een toelichting in zwarte
  bodytekst; onderaan staat de zin over eigen keuze.
- Geen felgroene vulling meer op pills of hint-kaders.
- Op `/article` staat de kop *Positionering scherper maken*, de voorgestelde tekst is **zichtbaar
  vóór** je op Pas toe klikt, en `Negeer` is een gelijkwaardige knop.
- `Pas toe` vervangt exact wat in het citaatblok stond.
- Op Overview staat het cijfer mét duidingzin, en een eventueel signaal heeft de accentbalk.
- De drie schermen naast elkaar: dezelfde accentbalk, dezelfde pill-vorm, dezelfde sluitregelstijl.
- Grep: geen nieuwe hex-kleuren, geen `border-2 border-ce-green`, geen handgerolde knoppen.
