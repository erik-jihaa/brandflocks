# HBA-testmerken voor de content-app

Drie verzonnen merken, opgebouwd volgens de Hero Brand-architectuur en goedgekeurd door Rogier als testmateriaal. Ze zijn bedoeld om de HBA-laag in de app te testen: de wizard, de kwaliteitscheck en de scoring van posts.

## Wat er in elk bestand staat

- `testmerk`: naam, branche, type en waarom dit merk in de testset zit. Dit is uitleg voor ons, geen invoer voor de app.
- `brand_narrative`: de invoer voor de app, volgens het BrandNarrative-schema uit de Implementation Kit v1.2 (archetype, doel, foe, spanning, mechanisme, geloofwaardigheidstest, verboden_logica, bewijsvelden, tone_of_behaviour, goede en zwakke voorbeelden, contextprofiel, platforms en postvolume).
- `testposts`: vier posts per merk met de uitkomst die we verwachten. Daarmee kun je controleren of de scoring doet wat hij moet doen.

`alle_drie_testmerken.json` bevat de drie merken in één bestand.

**Staging:** na tenant-provision `./scripts/import-hba-testposts-staging.sh` — vier posts per merk als **nulmeting** (`linkedin_posts` + `origin=baseline`, HBA-score bij import). Kop = label (Op merk / …), body = `post_tekst`. Agenda toont ze niet; Home toont de nulmeting-teller; Archief toont de vier posts.

## Waarom deze drie

| Merk | Wat het test |
| --- | --- |
| Brandpunt Koffie | Mkb, consument. Een concrete, kostbare regel in een markt waar "vers gebrand" en "duurzaam" iedereen zegt. |
| Stelwerk | B2B met lange verkoopcyclus. Een regel die marge kost, in een markt waar "onafhankelijk" en "partner" hygiëne zijn. |
| Duinroos Thuiszorg | Merk-archetype valt samen met het categorie-archetype (Verzorger in de zorg). Zonder de regel zou dit een hygiëne-merk zijn; de app moet hier de verzwaarde bewijslast toepassen. |

## De vier testposts per merk

1. **Op merk:** hoge bijdrage aan de architectuur, geen risico-signalen.
2. **Categorietaal:** zegt wat iedereen in de branche zegt. Verwacht: lage distinctiviteit en `category_fallback`.
3. **Breekt de regel:** verwacht `constraint_breach`. Dit is de belangrijkste test: herkent de app dat een post de geloofwaardigheidstest schendt?
4. **Neutraal of grensgeval:** service, educatie, werving of een trend. Verwacht een neutrale score of `opportunistische_trendcontent`.

Scoort de app post 2 of post 3 hoog, dan zit er iets mis in de scoring.

Alle merken, mensen en cijfers zijn verzonnen.

© Rogier Heijning, Hero Brands. Testmateriaal, niet voor publicatie.
