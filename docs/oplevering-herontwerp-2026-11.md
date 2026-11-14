# Oplevering herontwerp — november 2026

## Opdracht 1 — addendum §3 (branch `addendum-s3-kanaaleisen`, PR #1)

### Al op `main` vóór deze branch (delta t.o.v. opdrachttekst 14 nov)

| Opdrachtregel | Stand op `main` |
|---------------|-----------------|
| Tabel letterlijk in PHP | `ce_channel_copy_constraint_catalog()` 13 rijen ≈ addendum §3 |
| `content_textvorm` schrijven + UI | PATCH `article-repository.php`; keuze in `ArticleMetaSidebar` |
| Harde telling tekens/hashtags | `ce_channel_hard_validation_*` + melding op artikel |
| Lichte check postvoorstel | `ce_channel_soft_hints_for_proposal` + copy-modus CTA-hint |
| `hba-open-koppelpunten.php` | Bestaat met null-catalogus (≠ kale comment-only uit opdracht) |

### Gebouwd op branch `addendum-s3-kanaaleisen`

- `GET /api/channel-constraints` — tekstvorm-labels/opties en `hardLimits` uit PHP (één bron).
- Frontend: `useChannelConstraints` + tekstvorm uit API; veld alleen bij >1 keuze per kanaal.
- `tests/php/kanaaleisen.php` + opname in `scripts/test-all.sh`.

### Open gelaten (bewust)

- Zachte structuur/toon-check (niet telbaar) — niet gebouwd (opdracht).
- Oproep-tot-actie vs copy-modus — geen voorrangsregel (B7); beide regels blijven in directive.
- Woord-richtlijnen alleen als soft hints, geen harde telling.

## Opdracht 2 — Navigatie (gemerged op `main`, PR #2)

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

## Vragen aan Rogier

- **Merktaal of Merkelementen:** klikmodel = Merktaal; voorstel 31 okt. / weekplan = Merkelementen. Gebouwd als **Merktaal**.
- **B7** Voorrang copy-modus vs kanaaltabel bij CTA.
- **Opdrachtveld `opdracht` per campagnefase** — klantzichtbaar? (leesronde B2-twijfel).
- **hba-open-koppelpunten** — catalogus met null-keys voldoende of strikt leeg bestand?

## Test

- `./scripts/test-all.sh` groen vóór merge (lokaal 14 nov 2026).

## Overige opdrachten (nog open)

- **Stap 3 §9 kanaalfilters:** `main`, commit `070903f` (prod).
- **Opdracht 3** kalender/help — `docs/briefings/opdracht-3-*.md`.