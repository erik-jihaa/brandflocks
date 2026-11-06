# Cursor-briefing — Identity opnieuw indelen: drie tabs, één daarvan conditioneel

**Status:** besluit Erik, aug 2026 — dit is de definitieve indeling · **§4 opruim aug 2026:** done
**Vervangt:** `cursor-hba-scheiding-fix.md` §2 (het "één route, drie secties"-model) en
`cursor-hba-v2-meedenker.md` §3.2 en §7.6
**Blijft geldig:** alle andere paragrafen van beide documenten, in het bijzonder de databug
(`cursor-hba-scheiding-fix.md` §1) en de layout-fix (§3)

---

## 1. Waarom deze wijziging

Het invoegen van de HBA-laag heeft Identity rommelig gemaakt: HBA-velden en CF-eigen velden staan
door elkaar, en de vorige poging (alles op één route met conditionele secties) loste dat niet op —
het verplaatste de rommel alleen naar binnen.

De nieuwe indeling scheidt op **eigenaarschap van de inhoud**, niet op feature flag:

- Wat we voor Hero Brands hebben gebouwd, staat bij elkaar op één tab en is als geheel uit te zetten.
- Wat van Content Engine zelf is (hoe we schrijven), staat op een eigen tab en werkt altijd.
- Objectives blijft waar het staat.

Bijkomend voordeel: de HBA-tab is één afgebakende module. Dat past bij het feit dat het
gelicentieerd IP is.

---

## 2. De indeling

### Nav-sectie Identity

**Namen definitief vastgesteld (Erik, aug 2026):**

| Volgorde | Tab | Route | HBA uit | HBA aan |
| --- | --- | --- | --- | --- |
| 1 | **BrandArchitecture** | `/brandarchitecture` | **niet zichtbaar** | zichtbaar |
| 2 | **BrandElements** | `/styleguide` | zichtbaar | zichtbaar |
| 3 | **Objectives** | `/targets` | zichtbaar | zichtbaar |

Deze naamgeving lost de botsing uit §4 op: **BrandArchitecture** staat op de tab waar Rogier het
woord ook voor gebruikt (archetype, foe, doel, regels), en **BrandElements** dekt de CF-eigen
bouwstenen (BrandValues, schrijfwijzer, tones, auteurs). Engels CamelCase, consistent met de
bestaande nav (Overview, Objectives, Findability).

Bij HBA uit ziet de klant dus twee tabs, bij HBA aan drie. Er verdwijnt niets uit de nav bij het
aanzetten — er komt één tab bij. Dat is het verschil met de huidige bouw, waar `/styleguide` en
`/targets` juist uit de nav werden gefilterd.

### Tab 1 — BrandArchitecture (alleen bij `hba_enabled = true`)

Alles wat we voor Hero Brands hebben gebouwd, en niets anders. Dit is de inhoud van stap 1 tot en
met 8 uit `HBA_brandlaag_Merkarchitectuur_Wizard.docx`:

- Wie zijn jullie en in welke markt (identiteit + `branche_code`)
- Wat jullie willen bereiken (`doel`)
- Wat maakt jullie anders (`distinctiviteit`)
- Wat jullie bewust niet doen (`wat_bewust_niet`)
- Jullie regels (`regels`, max 5 rijen)
- Je persoonlijkheid + waar jullie tegen vechten + jullie toon (`archetype`, `foe`,
  `tone_of_behaviour`) — **afgeleid en ter bevestiging getoond**, nooit als leeg veld
- Welke voelt als jullie (`goede_voorbeelden` / `zwakke_voorbeelden`) — zie
  `cursor-hba-v2-meedenker.md` WP2.1
- Jullie situatie (`contextprofiel`)

Deze tab kent **twee toestanden**, zoals eerder afgesproken:

- **Leeg** → de begeleide flow van acht stappen, één doorlopende sessie, eindigend op het
  bevestigingsscherm plus het premium-bruggetje (§7.5 van de v2-briefing).
- **Ingevuld** → overzicht in mensentaal met een knop **Nog iets aanpassen** die dezelfde stappen
  opnieuw opent.

Dit is grotendeels de bestaande `IdentityPage.tsx`, ontdaan van `IdentityObjectivesSection` en met
de layout-fix uit `cursor-hba-scheiding-fix.md` §3.

### Tab 2 — BrandElements (altijd)

Dit is de huidige `StrategyPage.tsx` (`/styleguide`), hernoemd. **Geen redirect meer bij
`hbaEnabled`** — dat is de regressie die Schrijfwijzer, tone-styles en auteurs onbereikbaar maakte.

| Blok | HBA uit | HBA aan |
| --- | --- | --- |
| **BrandValues** (`brandstory`) | ✅ | ❌ **verdwijnt** — de merkidentiteit staat nu op BrandArchitecture |
| **Schrijfwijzer** (`writingGuide`) | ✅ | ✅ |
| **Schrijfstijl per tone** (`tone_styles`) | ✅ | ✅ |
| **Auteurs** (`editor_styles`) | ✅ | ✅ |

Eén conditie op de hele pagina: `{!hbaEnabled && <BrandValuesSection />}`. Verder verandert er
niets aan deze pagina — hij moet werken zoals hij vóór de HBA-bouw werkte.

Bij HBA aan bovenaan één regel die de klant de weg wijst, geen leeg gat:

> Je merkidentiteit staat op **BrandArchitecture**. Hieronder leg je vast hóé er geschreven wordt.

### Tab 3 — Objectives (altijd, ongewijzigd)

`/targets` blijft precies zoals het was, in beide standen, inclusief de percentage-sliders.

**Dit vervangt `cursor-hba-v2-meedenker.md` §7.6**, waarin stond dat Objectives bij HBA aan uit de
nav zou verdwijnen en naar Merkarchitectuur zou verhuizen. Dat blijkt de verkeerde keuze: het is
precies wat tot twee implementaties leidde en daarmee tot het dataverlies uit
`cursor-hba-scheiding-fix.md` §1.

**Concreet:**

- `IdentityObjectivesSection.tsx` wordt **verwijderd**. Niet uitgeschakeld — weg.
- `TargetsPage.tsx` is de enige objectives-implementatie, met sliders, in beide standen.
- De databug uit §1 van het fix-document verdwijnt daarmee vanzelf: er is geen tweede save-pad meer
  dat percentages gelijk verdeelt.

Wil je later alsnog dat de tool een verdeling voorstelt in plaats van vraagt (het idee uit §7.6):
bouw dat als **knop** op `/targets` — *"Stel een verdeling voor"* — die de sliders vult. Eén
component, één save-pad, geen tweede scherm.

### 2.1 Gebruikt de HBA-laag de objectives-data? Nee.

Gecontroleerd tegen de drie prompts die Rogier levert. Geen van drieën leest objectives:

| Prompt | Krijgt mee |
| --- | --- |
| Ingrijppunt 1 — thema-advies | archetype, doel, tegenkracht, distinctiviteit, wat_bewust_niet, regels, categorietaal, voorgestelde + recente thema's |
| Ingrijppunt 2 — bijbuigen | idem + toon + het concept |
| Scoring (Complete Kit h.4) | de volledige brand narrative + de post |

Nergens `objective_label`, `objective_id` of een percentage. **Voeg ze er ook niet aan toe.** De PBS
ontleent zijn gezag aan het feit dat hij objectief meet; laat je de klant zijn eigen doelstellingen
in de meetlat schuiven, dan meet je niet langer de HBA (Complete Kit 11.5, laag 2). De targetmix
komt uit `contextprofiel` en nergens anders — zie `cursor-hba-v2-meedenker.md` §7.6.1.

**Wel indirect verbonden, en dat is prima.** CE-03 stap A krijgt `objective_label`,
`objective_description` en `objectives.writing_style` mee bij het schrijven. Objectives beïnvloeden
dus wél de content die de HBA-laag daarna beoordeelt — via het artikel, niet via de meetlat. Dat is
het juiste kanaal.

**Let op één naderende botsing op het Article-scherm.** De HBA-scoring bepaalt per post een
`contentrol` uit een vaste lijst van acht (positionering, bewijs, educatie, activatie, community,
service, employer_culture, reactief). Dat beschrijft hetzelfde soort ding als een objective — "wat
voor post is dit" — maar dan methodisch afgeleid uit de tekst in plaats van vooraf gekozen door de
klant. Toon ze nooit naast elkaar zonder uitleg; dat zijn twee antwoorden op dezelfde vraag. Houd
`contentrol` voorlopig uit de klant-UI (het is bovendien vaktaal, WP1).

**Wat later wél interessant is, één richting op:** op Report de geplande objective-verdeling naast
de gemeten contentrol-mix leggen — *"je plande 40% brand awareness; de meting classificeerde 55% als
positionering"*. Dat is een nuttige spiegel en het raakt de meting niet, omdat de informatie van HBA
naar CF stroomt en niet andersom. Niet nu bouwen; noteren voor de Report-fase.

---

## 3. Wat er niet verandert

Deze punten uit `cursor-hba-scheiding-fix.md` blijven onverkort gelden en horen in dezelfde
refactor mee:

- **§3 Layout.** BrandArchitecture krijgt `mainLayout="grid2"` + `GRID2_REVIEW_MAIN_WIDTH` (500px), niet
  `max-w-[680px]`. Alle handgerolde `<select>` en `<input>` vervangen door `Pulldown` en `Field` —
  `border-0`, `px-[30px]`. Dit is de oorzaak van de velden die in elkaar lopen.
- **§4 Opruimen.** Dode `hbaEnabled`-tak in `StrategyPage`; `brandNarrative` alleen meesturen in de
  save als `hbaEnabled`; `BrandNarrativeFields.tsx` hergebruiken in de BrandArchitecture-tab of verwijderen.
- **De HBA-vlag mag nooit data aanraken.** Aan- en uitzetten verandert geen enkele opgeslagen
  waarde. `brandstory` blijft staan als HBA aangaat; de narrative blijft staan als HBA uitgaat.

---

## 4. Naamgeving — beslist, hier voor de achtergrond

**Uitkomst: BrandArchitecture / BrandElements / Objectives.** Onderstaande afweging is afgehandeld;
bewaard omdat de redenering nog van pas komt bij het opschonen van labels in WP1.

### 4.0 Nog wél aan te passen elders

De hernoeming raakt drie plekken buiten de nav — anders verwijzen ze naar schermen die niet meer zo
heten:

- **Support-systeemprompt** (`support-chat-repository.php`): noemt nu *"StyleGuide (merk:
  archetype, tone of voice, schrijfwijzer)"* in de onboarding-volgorde. Dat klopt straks dubbel
  niet — het scherm heet BrandElements en het archetype staat er niet meer op. Zie ook
  `cursor-fieldhint-support.md` §6, waar de WordPress-verwijzing in dezelfde prompt al fout stond.
- **`briefing/onboarding-master.md`**: de sessievolgorde noemt StyleGuide.
- **BUILD-NOTES § Navigatie**: de nav-tabel bevat nog StyleGuide → Objectives.

Route `/styleguide` mag blijven staan achter het label BrandElements — deeplinks en bookmarks
blijven zo werken. Wil je hem toch hernoemen, zet dan een redirect van `/styleguide` naar de nieuwe
route, net als `/strategy` nu al doet.

### 4.1 en 4.2 — de oorspronkelijke afweging

De structuur kan gebouwd worden zoals hierboven; labels zijn eenregelige wijzigingen. Maar er zitten
twee botsingen in die je bewust wilt maken.

**4.1 "Merkarchitectuur" betekent bij Rogier iets anders.** In zijn documenten is de
merkarchitectuur juist het HBA-geheel — archetype, foe, doel, regels. Zijn wizard-document heet
letterlijk *Merkarchitectuur-wizard*, en zijn labelvertaling (hoofdstuk 7) laat de klant dat woord
zien voor precies die velden. In deze indeling gebruiken we het woord voor de CF-eigen tab met
schrijfregels. Een klant die Rogier spreekt en dan de app opent, ziet het woord op de verkeerde plek
staan.

Alternatief dat de botsing wegneemt: noem tab 2 **Schrijfregels** of **Merk & schrijfwijze** — dat
dekt de inhoud (BrandValues, schrijfwijzer, tones, auteurs) beter dan "architectuur", en laat
"merkarchitectuur" vrij voor waar Rogier het voor gebruikt.

**4.2 "Herobrand" is een leveranciersnaam.** WP1 van de v2-briefing zegt: is een label alleen te
begrijpen door iemand die de methode kent, dan hoort het niet in de klant-UI. "Herobrand" valt
daaronder — het zegt de klant niets over wat er op die tab staat.

Tegenargument, en het is een geldig argument: als je de licentie zichtbaar wilt maken en Hero Brands
als kwaliteitsmerk wilt voeren, is de naam juist waardevol. Dat is een commerciële keuze die de
jargonregel mag overrulen. Wil je de inhoud voorop zetten, dan is **Positionering** de duidelijkste
klanttaal — dat is precies wat die tab doet.

Mijn voorkeur: tab 1 **Positionering**, tab 2 **Schrijfregels**. Maar bouw de structuur, welke namen
je ook kiest; ze zijn later in één regel te wijzigen.

---

## 5. Testgevallen

1. **HBA uit** — twee tabs: BrandElements (met BrandValues, Schrijfwijzer, tone-styles, auteurs)
   en Objectives (met sliders). Nergens een Story Triangle-veld, branche of contextprofiel.
2. **HBA aan** — drie tabs. BrandArchitecture toont de acht stappen of het overzicht. BrandElements toont
   Schrijfwijzer, tone-styles en auteurs, zónder BrandValues, mét de verwijzingsregel bovenaan.
   Objectives ongewijzigd, met sliders.
3. **Aanzetten met bestaande data** — objectives 40/30/20/10 blijft 40/30/20/10, ook na een save.
   `brandstory` blijft in de database staan, ook al is het veld verborgen.
4. **Uitzetten** — BrandArchitecture verdwijnt uit de nav, BrandValues komt terug met de oude inhoud, de
   narrative blijft in de database.
5. **Breedte** — leg alle drie de tabs naast Integrations. Velden beginnen op dezelfde x en zijn
   even breed.
6. **Grep** — `IdentityObjectivesSection` komt nergens meer voor.
