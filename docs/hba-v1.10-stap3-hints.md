# HBA v1.10 — stap 3: hints vragen om feiten

Datum: 2026-10-13. Staging: PHP + frontend (invulvelden, oranje `[…]`, publish-blok).

## Klaar-criteria

| Criterium | Uitkomst |
| --- | --- |
| Geen `passageNa`/`ctaNa` bevat een feit dat niet in tekst, bewijsvelden of research staat | Gehaald |
| Waar een feit ontbreekt: invulveld `[…]` + voorbeeld in de aanwijzing | Gehaald — alle drie |
| Minstens één V0-hint haalt het voorbeeld uit een bewijsveld | Gehaald — alle drie hebben `bewijsveldBron` |
| Tekst met open invulveld kan op staging niet gepubliceerd/ingediend worden | Gehaald — HTTP 400: “Er staat nog een stukje tussen haken dat je zelf moet invullen.” |

JSON: `docs/hba-v1.10-stap3-v0-hints.json`.

## V0-hints (staging, na force)

| Merk | Veld | Buiten de haken | Invulveld |
| --- | --- | --- | --- |
| Brandpunt | `ctaNa` | Bestaande slot + website-kilo’s (bewijsveld) + bestaande proefochtend-CTA | `[Hoeveel kilo koffie er in de afgelopen maand naar de buurtvoedselbank is gegaan.]` |
| Stelwerk | `passageNa` | Alleen de bestaande slotzin | `[Een situatie waarin jullie adviseerden het bestaande systeem te houden…]` |
| Duinroos | `passageNa` | Alleen de bestaande slotzin | `[Wat jullie doen op het moment dat jullie iemand doorverwijzen…]` |

Eerste Duinroos-run voegde nog “Dat getal publiceren we elke maand” en later “niet met een naam en een telefoonnummer achter” toe. Prompt aangescherpt: buiten de haken alleen zinnen die al in het concept staan. Hertest is schoon.

## Publish-blok (live op staging)

Duinroos V0-slot, ingelogd als test-tenant:

1. `POST /articles/{slot}/submit` met `[open invulveld voor de stagingtest]` in de tekst → **400**, exacte melding.
2. Hint toepassen: `toegepast: false`, `openPlaceholders: true`.
3. Opnieuw indienen → **400**, dezelfde melding.
4. Artikeltekst beide keren teruggezet naar het origineel.

Toepassen mag de haken in de editor zetten; “toegepast” volgt pas als ze weg zijn. Open `[…]` is oranje gemarkeerd.
