# Oplevering herontwerp — november 2026

## Opdracht 2 — Navigatie (branch `herontwerp-navigatie`)

### Nieuwe zijbalk (klikmodel stap 6)

| Groep | Items (volgorde) |
|---|---|
| Overzicht | Rapport · Te doen |
| Identiteit | Merkarchitectuur · Merktaal |
| Kennis | Bronnen · SEO/GEO · Beeldbank |
| Publicatie | Artikel · Campagne · Kalender · Alle artikelen |
| Instellingen | Planning · Mijn account · Koppelingen · Help · Uitloggen |

**Niet meer in de nav:** Thema's, Onderzoek, Snel artikel, Doelen, Rapport onder Publicatie. Routes blijven; broodkruimel op Onderzoek/Thema/Snel artikel (Publicatie › Artikel › …), `activeNav` = Artikel.

### Oud nav-label / pad → nieuw (routes ongewijzigd)

| Was | Nu (label) | Pad |
|---|---|---|
| Merkelementen | Merktaal | `/styleguide`, `/strategy` |
| Campagnes | Campagne | `/campagnes` |
| Agenda | Kalender | `/agenda` |
| Archief | Alle artikelen | `/archive` |
| Vindbaarheid (copy) | SEO/GEO | `/search` |
| Planning (onder Kennis) | Planning (Instellingen, eerste item) | `/planning` |
| Beeldbank (onder Publicatie) | Beeldbank (Kennis) | `/gallery` |
| Rapport (onder Publicatie) | Rapport (Overzicht) | `/report` |
| Thema's | — (via Artikel-flow) | `/theme`, `/themes/…` |
| Onderzoek | — (via Artikel-flow) | `/research`, … |
| Snel artikel | — (via Artikel-flow) | `/fastlane` |
| Doelen | — (nav-item weg) | `/targets` |

Geen URL-redirects toegevoegd: bestaande bookmarks werken.

### Doelen

Nav-item verwijderd. **`/targets` en API `/objectives` blijven.** `source-folders-repository.php` koppelt bronnenmappen nog aan `objective_id` — daarom geen verdere opschoning van Doelen in deze opdracht.

### Technisch

- `NavBreadcrumb.tsx`, optionele `breadcrumb` op `GridShell`.
- Klanttaal: Merktaal, Kalender, Campagne, Alle artikelen; archief-flow uit Engels naar NL.

### Vragen aan Rogier

- **Merktaal of Merkelementen:** klikmodel = Merktaal; voorstel 31 okt. / weekplan = Merkelementen. Gebouwd als **Merktaal**.

## Opdracht 3 — Kalender, deel A (branch `herontwerp-kalender-help`)

### Gebouwd / aangescherpt

- Weergave **Overzicht | Lijst** (klikmodel-label Overzicht i.p.v. dubbele “Kalender”).
- **Kanaal- en maandfilter** in beide weergaven; tenant-kanalen uit agenda-data (niet hardcoded).
- Lijst **Op campagne**: inklapbare campagne/thema-groepen (bestaand, `AgendaCampaignList`).
- Lijst **Op datum**: nieuw `AgendaDateList` — per dag, reeksnaam + post (één bron, zelfde filters).
- **Lege maand:** `Geen posts gepland in deze maand.` (klikmodel, zonder demo-zin).
- **Op campagne / Op datum** alleen in Lijst (zoals klikmodel); kalender gebruikt wel `groupMode` voor campagne-only raster (bestaand gedrag).

### Niet gebouwd (opdracht)

- **Campagnekleuren uit DB:** geen `color`-veld op `campaigns` — legenda gebruikt vaste palette-rotatie (`colorIndex`), zoals al in UI.
- **Kanaalverschuiving addendum §5** — alleen datamodelnotitie hieronder.

### Datamodel — één artikel, meerdere kanalen (voor §5)

Elk kanaal is een **eigen `content_slots`-rij** met eigen `platform` / `publish_date` (en vaak eigen `article_id` per slot). Er is geen gedeeld slot met meerdere kanalen op één datum in het huidige schema; campagneplanning maakt meerdere slots (zie campagne-repositories).

### Deel B — Help

Nog niet in deze commit (aparte sessie).

## Eerdere opdrachten

- **Stap 3 §9 kanaalfilters:** `main`, commit `070903f` (prod).
- **Opdracht 2 navigatie:** gemerged op `main` (PR #2).
- **Opdracht 1 §3 kanaaleisen:** PR #1 open.