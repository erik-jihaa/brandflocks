# PR-review screenshots — 14 nov 2026

Beelden om **PR #1** (kanaaleisen) en **PR #2** (navigatie) te reviewen zonder alles in de diff te openen.

| Bestand | Wat |
|---------|-----|
| [pr1-github.png](./pr1-github.png) | PR #1 overzicht + testplan |
| [pr1-kanaaleisen-diff.png](./pr1-kanaaleisen-diff.png) | Kern: `tests/php/kanaaleisen.php` |
| [pr2-github.png](./pr2-github.png) | PR #2 overzicht + testplan |
| [nav-oud-vs-nieuw.png](./nav-oud-vs-nieuw.png) | Zijbalk oud (prod) vs klikmodel na PR #2 |
| [pr2-files-tree.png](./pr2-files-tree.png) | PR #2 — bestandsboom (incl. §9) |
| [pr2-oplevering-diff.png](./pr2-oplevering-diff.png) | PR #2 — `oplevering` in diff |
| [nav-klikmodel-vs-pr2.html](./nav-klikmodel-vs-pr2.html) | Oud vs nieuw nav (open in browser) |

## Links

- [PR #1 — addendum §3](https://github.com/erik-jihaa/brandflocks/pull/1) — `+629 / -126`, 22 files
- [PR #2 — navigatie stap 6](https://github.com/erik-jihaa/brandflocks/pull/2) — 29 files (incl. §9-bundle)

## Review-checklist (kort)

**PR #1** — op `origin/main` mist nog veel van §3/§9; op prod staat §9 al gedeployed.

1. `GET /api/channel-constraints` + `useChannelConstraints` in artikel-sidebar
2. `tests/php/kanaaleisen.php` in `test-all.sh`
3. §9 period filters (`ChannelFilterBar`) — commit `070903f` zit in deze PR tot `main` is bijgewerkt

**PR #2**

1. Zijbalk vs [nav-klikmodel-vs-pr2.html](./nav-klikmodel-vs-pr2.html)
2. Klanttaal Kalender / Merktaal / Alle artikelen
3. Broodkruimel op Onderzoek, Thema, Snel artikel
4. Doelen uit nav; `/targets` blijft (bronmappen → `objective_id`)

## Git-basis (14 nov, uitgevoerd)

- `070903f` (§9) staat op `origin/main`.
- **PR #1:** alleen `123a201` · **PR #2:** gemerged (`78006e4` op `main`).
- Nieuwe nav op prod: na deploy van `main` (merge #2 staat op GitHub; nog geen deploy tenzij Erik ja zegt).