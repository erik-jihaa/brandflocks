# Aanvulling op notitie 15 — stippen, signaalblok en kleurtokens

**Besluit:** Erik, 29 september 2026. Getoetst aan het CF-design system (`frontend/src/index.css`, `DesignSystemPage.tsx`).
**Hoort bij:** `hba-input/15-bouwnotitie-overzicht-rapport-3.md`, §1.2 (signaalblok) en §1.5 (Wat op je wacht)
**Status:** besloten; bouwen samen met de herindeling van Overzicht en Rapport

## 1. Nieuwe kleurtokens

Voeg toe aan `@theme` in `frontend/src/index.css`:

```css
/* Signaalblok (notitie 15 §1.2) — alleen als vlak, altijd met ce-black tekst */
--color-ce-yellow: #f5c400;
/* Leesbare beige tekst voor hints/aanwijzingen op offwhite (4,6:1). ce-beige blijft voor stippen, lijnen, randen. */
--color-ce-beige-text: #6b665c;
```

Waarom: `ce-beige` (#ada695) als tekst haalt op offwhite maar 1,9:1 (norm 4,5:1). `ce-beige-text` haalt 4,6:1. Dit volgt hetzelfde patroon als `ce-green-text` naast `ce-green`.

**Vervolg (niet in deze stap):** alle bestaande beige hint- en aanwijzingsteksten (`text-ce-beige`) omzetten naar `text-ce-beige-text`. Stippen, lijnen en randen blijven `ce-beige`. Neem alleen Home en Rapport mee in deze bouw; de rest van de app komt in een eigen ronde.

Voeg beide tokens toe aan `DesignSystemPage.tsx`, met de regel erbij.

## 2. Signaalblok (§1.2) — vol geel, alleen zwart

`--bg-warning` uit notitie 15 bestaat niet; gebruik `ce-yellow`.

- **Vlak:** `bg-ce-yellow`, zonder rand.
- **Tekst:** uitsluitend `text-ce-black` (9,6:1). **Geen** beige, blauw, rood of groen in het blok: die halen op vol geel geen 4,5:1 (blauw 4,1, beige-text 3,5, groen-text 3,3, rood 2,2).
- **Actielink:** zwart, onderstreept of met een pijl, bijvoorbeeld "Plan een positioneringsstuk →". Dus geen `ActionLink`/`ce-blue` binnen het blok. Maak een variant (bijvoorbeeld `ActionLink variant="onYellow"`) in plaats van losse klassen.
- De overige regels uit notitie 15 blijven: maximaal één signaal, het zwaarste; geen rood; het blok valt weg als er geen signaal is.

## 3. Stippen bij "Wat op je wacht" (§1.5)

Aansluitend op de bestaande betekenis in `AgendaCalendar` (blauwe stip = de stap waar het nu staat, beige = niet actief):

- **Blauwe stip** (`bg-ce-blue`, 8px, `rounded-full`, zoals de bestaande stippen): het wacht nu op de gebruiker en houdt de pijplijn tegen.
- **Beige stip** (`bg-ce-beige`) + "kan later" in `text-ce-beige-text`: uitstelbaar.
- **Niet `ce-grey`** gebruiken: die betekent bij de integraties "niet beschikbaar".
- De "Ga"-link blijft op elke regel blauw, want die is klikbaar. De stip zegt of het wacht; de link zegt dat je erheen kunt.
- Sorteer blokkerend boven.

## 4. Research: splitsen in twee todo's

Liken of vergeten van research start niets; alleen **Indienen** zet een slot door naar het schrijven. De research-pagina zegt zelf "advies, geen verplichting". De huidige todo telt de hele achterstand (`researchOpen` = alle ongelikete items van de tenant). Daarom:

| Todo | Wanneer | Stip |
|---|---|---|
| **Research indienen — [artikeltitel]** (link naar `/research/:slotId`) | er is een slot in de researchfase dat op indienen wacht | blauw |
| **Research doorkijken — [n] items** (link naar `/research`) | er zijn ongelikete items in de achterstand | beige, "kan later" |

**Backend** (`web/api/home-repository.php`, `ce_fetch_home_dashboard`): naast `researchOpen` een telling plus het eerste slot-id en de titel van slots die in de researchfase op de gebruiker wachten. Controleer in `mappers.php` en `agendaActions.ts` welke slotstatus dat precies is (`research_done`?). **Meld het als dat niet eenduidig is**, in plaats van een eigen definitie te verzinnen.

## 5. Indeling van alle todo's

Voeg per item in `todos[]` het veld `blocking: true|false` toe.

| Todo | Stip |
|---|---|
| Merkarchitectuur invullen | blauw |
| Bronnen toevoegen (nul bronnen) | blauw |
| Theme starten (geen thema's, geen agenda) | blauw |
| Theme beoordelen | blauw |
| Research indienen — [artikel] | blauw |
| Feedback verwerken | blauw |
| Artikel afmaken | blauw |
| Pending reviews | blauw |
| Artikelen plaatsen | blauw |
| Research doorkijken — [n] items | beige |
| Voeg een kern-bron toe | beige |
| Podcast toevoegen | geen todo meer; hoort in de balk "Nog niet gekoppeld" (§1.6) |

## 6. Acceptatie (aanvulling op §4 van notitie 15)

- `ce-yellow` en `ce-beige-text` staan in `index.css` en op de Design system-pagina.
- Het signaalblok is vol geel met uitsluitend zwarte tekst en een zwarte actielink.
- Op Home en Rapport gebruikt hint- en aanwijzingstekst `text-ce-beige-text`, niet `text-ce-beige`.
- De todo's hebben een blauwe stip als ze de pijplijn tegenhouden, en een beige stip met "kan later" als ze kunnen wachten. Nergens `ce-grey`.
- Research staat als twee aparte todo's: indienen (blauw) en doorkijken (beige).
