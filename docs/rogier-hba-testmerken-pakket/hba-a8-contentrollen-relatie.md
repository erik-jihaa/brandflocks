# A8 — Twee sets contentrollen (melding)

**Datum:** 10 oktober 2026 · **Opdracht:** ronde 3 — niet samenvoegen zonder Rogier.

## Planning (CE-01 ritme)

Rollen in weekcyclus / themakop: **autoriteit**, **inzicht**, **product-of-dienst** (en varianten in `content-rhythm-repository.php`).

Doel: welk type bericht deze week aan de beurt is in het vaste ritme.

## HBA-scoring (CE-score / merkarchitectuur)

Rollen in score-output: **positionering**, **bewijs**, **activatie**, **service**, **employer_culture**, enz. (`ce_hba_contentrol_keys()`).

Doel: wat de post doet voor het merk (architectuur, risico, balans).

## Relatie

| Planning-rol | HBA-rol (typisch) |
| --- | --- |
| Autoriteit | bewijs / positionering |
| Inzicht | positionering / activatie |
| Product of dienst | service / activatie |

Geen 1:1-mapping in code; CE-01 schrijft ritmrol in slot/thema, scorer kiest HBA-contentrol uit de tekst.

## Gevolg voor de klant

Op **Thema**, **Artikel** en **Report** kunnen beide termen voorkomen. Dat is **bewust** tot de kalibratiemodule één taxonomie kiest.

## Actie

Geen bouw in ronde 3. Documenteer in kalibratiemodule-backlog: één klanttaal-veld “soort bericht” met mapping naar interne HBA-rol.
