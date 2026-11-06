# Navigatie — aantekeningen (aug 2026)

Analyse van de huidige sidebar-navigatie (`frontend/src/components/SidebarNav.tsx` +
routes in `frontend/src/App.tsx`), plus toetsing van een voorgesteld nieuw
menu-concept (Identity / Knowledge / Content / Settings).

---

## Samenvatting — navigatiestructuur

### Huidige situatie

- **Home**
  - Overview
  - Agenda
  - Article
  - Research *(typo: Reseach)*
  - Theme
  - Report
- **Extra**
  - Fastlane
  - Gallery
  - Archive
  - *(Campaigns ontbreekt — route bestaat nog)*
- **Account**
  - Account
  - StyleGuide
  - Objective
  - Planning
  - Search
  - Sources
  - Support
  - Logout

### Voorgestelde situatie (concept + gaten ingevuld, workflow-volgorde)

- **Overview**
  - Overview
- **1 Identity**
  - BrandValues *(nieuw scherm)*
  - StyleGuide
  - Objectives
- **2 Knowledge**
  - Sources
  - Themes
  - Research
  - Findability *(huidige Search — GEO/SEO-vragen)*
  - Planning
- **3 Content**
  - Fastlane
  - Articles
  - Campaigns
  - Agenda
  - Gallery
  - Archive
  - Report
- **4 Settings**
  - Account
  - Integrations *(nieuw scherm — OAuth/GA4/LinkedIn)*
  - Team *(nieuw scherm — multi-user)*
  - Support
  - Logout

---

## 1. Huidige structuur (drie secties)

- **Home** — Overview, Agenda, Article, Research, Theme, Report
- **Extra** — Fastlane, Gallery, Archive
- **Account** — Account, StyleGuide, Objective, Planning, Search, Sources, Support, Logout

### Concrete inconsistenties

1. **Typo**: "Reseach" i.p.v. "Research" — `SidebarNav.tsx` regel 52.
2. **"StyleGuide"** is samengevoegd (camelCase); alle andere labels zijn losse
   woorden (Objective, Planning, Search, Sources, Support).
3. **Sectie-icoon "Account" bevat óók een item genaamd "Account"** — verwarrend,
   alsof een map "Documenten" een bestand "Documenten" bevat.
4. **"Search" is misleidend** — geen zoekfunctie, maar een instellingenscherm
   voor GEO/SEO-probleemvragen. Redacteur verwacht een zoekbalk, geen
   config-pagina.
5. **Volgorde in Home-sectie volgt niet de echte workflow**: nu
   Overview → Agenda → Article → Research → Theme → Report, terwijl de
   contentpijplijn Theme → Research → Article → Agenda → Report is
   (CE-01 → CE-02 → CE-03).
6. **"Extra"** is een nietszeggende restcategorie voor drie functioneel
   verschillende tools (Fastlane = snelle bypass, Gallery = beeldbank,
   Archive = geschiedenis).

### Ontbrekend item (regressie)

**Campaigns staat niet meer in de zichtbare nav.** `docs/BUILD-NOTES.md`
documenteert expliciet "Campaigns in Extra-sidebar" als gebouwde feature
(regel 18, 509), en de routes (`/campaign`, `/campaigns`, `/campaigns/:id`) en
actieve-status-logica (`EXTRA_KEYS`) bestaan nog steeds — maar de items zelf
zijn op een gegeven moment uit `NAV_SECTIONS.extra.items` verdwenen
(waarschijnlijk tijdens de nav-herstructurering van 3 augustus). Nu is
Campaign alleen bereikbaar via de "Campaign"-actie op Agenda, niet via het
menu.

### Categorie-logica: Account-sectie is de breedste/vaagste bucket

Acht items onder één icoon, met heel verschillende aard: profiel (Account),
merkstrategie (StyleGuide), contentverdeling (Objective), operationele cadans
(Planning), vindbaarheid-input (Search), databronnen (Sources), hulp
(Support), actie (Logout). Functioneel prima gescheiden van de
contentpijplijn (Home) en tools (Extra), maar wel de meest heterogene groep.

---

## 2. Getoetst concept: Identity / Knowledge / Content / Settings

Voorgesteld door gebruiker (mockup-afbeelding, donker thema, genummerde
categorieën met icoon per item):

1. **IDENTITY** — BrandValues, StyleGuide, Objectives
2. **KNOWLEDGE** — Sources, Research, Planning, Themes
3. **CONTENT** — Fastlane, Articles, Gallery, Archive
4. **SETTINGS** — Account, Team, Integrations

### Wat dit oplost t.o.v. de huidige nav

| Probleem | Opgelost? |
|---|---|
| Sectie-icoon "Account" bevatte ook een item "Account" | ✅ Sectienaam wordt "SETTINGS", "Account" is alleen nog een item erin |
| "Extra" was een vage restcategorie | ✅ Vervangen door "CONTENT" |
| Account-sectie was één te brede bucket met 8 items | ✅ Opgesplitst in IDENTITY / KNOWLEDGE / SETTINGS |
| Geen visuele hiërarchie tussen items | ✅ Genummerde categorieën (1-4) + icoon per item |

De groepering sluit goed aan op het `briefing/product-vision.md`-fundament:
IDENTITY dekt vragen 1/4/5/7 (doel, merkpersoonlijkheid, kernboodschap,
contentpijlers/aandeel), KNOWLEDGE dekt de input-kant, CONTENT is de
output-pijplijn, SETTINGS is puur beheer.

### Wat ontbreekt of onduidelijk is in dit concept

1. **Report ontbreekt volledig.** Kernscherm (CE-07, vraag 9 "hoe meten we
   succes"). Moet landen in CONTENT (na Archive) of als eigen top-level item.
2. **Agenda ontbreekt.** Samengevoegd met "Planning" (KNOWLEDGE), of
   weggevallen? Agenda (kalenderoverzicht geplande slots) en Planning
   (instellingen: horizon, posts/week) zijn nu twee losse schermen.
3. **Support en Logout ontbreken.** Moeten ergens zichtbaar blijven (evt.
   avatar-menu), maar niet stilzwijgend verdwijnen.
4. **Search (GEO/SEO-vragen) niet te zien.** Mogelijk opgegaan in "Research"
   — let op: `/research` (content-kandidaten uit bronnen) en `/search`
   (GEO/SEO-vragen) zijn nu losstaande features met andere data. Als ze
   samenkomen onder één item, moet die pagina beide functies scheiden
   (bv. tabs), anders raakt GEO-functionaliteit onvindbaar.
5. **Campaign/Campaigns** nog steeds niet opgenomen (zelfde gat als hierboven).
   Zou logisch in CONTENT passen, naast Articles.
6. **Drie nieuwe items bestaan nog niet als scherm/databron:**
   - **BrandValues** los van StyleGuide — nu zit merkpersoonlijkheid
     (`archetype`, `archetype_traits`, `adversary`, `brand_goal`) in één
     Strategy-formulier. Opsplitsen is inhoudelijk goed (sluit aan op
     "toekomstige richting" in product-vision), maar vraagt een nieuw
     datamodel/scherm, geen simpele nav-wijziging.
   - **Team** — er bestaat nog geen multi-user/rollen-systeem. Nieuwe
     feature, geen bestaande pagina die verplaatst wordt.
   - **Integrations** los van Sources — OAuth-koppelingen (GA4, LinkedIn)
     zitten nu verspreid over Account/Sources. Bundelen is een goed idee,
     maar ook nieuw werk.
7. **Volgorde binnen KNOWLEDGE** (Sources → Research → Planning → Themes)
   volgt niet de echte workflow. Thema's (CE-01) worden normaliter vóór het
   onderzoek gegenereerd; logischer: Sources → Themes → Research → Planning.

---

## 3. Openstaande vragen voordat implementatie

- Waar landen Report, Agenda, Support en Logout in de nieuwe indeling?
- Blijft Search een apart item, of gaat het op in Research (en hoe blijft
  GEO/SEO dan vindbaar)?
- Is BrandValues een nieuw apart scherm/datamodel, of een herverdeling van
  bestaande StyleGuide-velden?
- Is Team een geplande roadmap-feature (multi-user) of alleen een
  placeholder in de mockup?
- Wordt Integrations een nieuw scherm, of een herlabeling van bestaande
  OAuth-knoppen in Account/Sources?

---

*Status: **geïmplementeerd** (4-5 aug 2026). Nav-herstructurering in
`frontend/src/components/SidebarNav.tsx` — Overview los +
Identity/Knowledge/Content/Settings, workflow-volgorde, typo gefixt,
Campaigns hersteld, Search → Findability. Dark-mode-chapter
(`ACCOUNT_NAV_KEYS`) bewust ongewijzigd qua schermen, wel uitgebreid met
`integrations`. Integrations-scherm gebouwd (5 aug) — bundelt GA4 + LinkedIn
OAuth-status onder Settings, zie changelog in `docs/BUILD-NOTES.md`.
BrandValues en Team bestaan nog niet — vragen nieuw datamodel/features, geen
nav-wijziging.*
