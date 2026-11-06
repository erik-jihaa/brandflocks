# Reactie Rogier op pakket ronde 7 — uitvoering

**Bron:** `briefing/hba-input/hba-reactie-ronde-7-2026-10-21.md`
**Branch:** `hba-ronde-6-pilotklaar`
**Niets op productie.** Eén commit per stap, apart per onderwerp — niet samenvoegen.

## Volgorde

### Stap 1 — Log uitlezen (eerst, want gratis en niets geblokkeerd)

Uit `docs/hba-v1.13-stap5-5x19-run.log`, runs 1 en 2: voor alle vier categorietaal-posts per run hoofdoordeel + flags rapporteren. Dit is pure log-analyse op al uitgevoerde runs, geen nieuwe API-calls, geen credit nodig. Lever een kort rapport (geen nieuwe briefing-doc nodig, een paar regels per post volstaat) zodat we dit meteen naar Rogier kunnen doorsturen terwijl de rest nog loopt.

### Stap 2 — CE-04 Duinroos-fix (bug, geen cosmetiek)

Scope, letterlijk uit Rogiers reactie:

- `ce_hba_resolve_literal_brand_regel` citeert **alleen** uit het klantveld "Jullie regels" — nooit uit de geloofwaardigheidstest of de verboden logica.
- Duinroos-narrative nalopen; regelveld corrigeren als dat nodig is.
- `regelFeedbackOk` wordt alleen `true` als het citaat **letterlijk** in dat regelveld staat.
- Opmaakfix: punctuatie na het citaat corrigeren (punt vóór het sluitende aanhalingsteken, niet erna).

**Twee voorwaarden, niet onderhandelbaar:**

1. **Eigen commit**, los van de screenshot-fixes (stap 3) en los van alles wat met v1.13/scoring te maken heeft.
2. **Bevestig expliciet, in de commit-boodschap of een regel eronder, dat de scoring-flow (hoofdoordeel, `foe_vervaging`, trede-logica) ongewijzigd blijft.** Rogier vraagt dit zelf ook al. Met het v1.13-traject nog open wil ik zeker weten dat niemand per ongeluk twee dingen in dezelfde branch raakt.

**Klaar als:** post 3, 3/3 merken, juiste regeltekst, en de bevestiging hierboven.

### Stap 3 — Screenshots opnieuw

- `12-agenda-kalender.png`: verkeerd scherm (Terugkoppeling "Laden…") — opnieuw op de echte kalender-tab.
- `07-feedback-terugkoppeling.png`: op 1280px breedte, zodat de hele opmerkingenkolom zichtbaar is (de huidige opname op 957px sneed de tekst rechts af — dat gold ook voor de versie die ik gisteren ververste, dus deze keer breder opnemen).

Agenda-lijst (nl-NL) en hashtags (punt C) zijn al akkoord — daar hoeft niets aan te gebeuren.

### Stap 4 — Pas na credit-top-up

v1.13 5×19 opnieuw (dezelfde run, geen prompt/code-wijziging), daarna offline replay met en zonder `&& !kern_categorietaal` (spoor 1b). Pilotcriteria ongewijzigd: ≥16/19, de vier categorietaal-posts nooit `tegenkracht`, BC-spread ≤1. Dit staat los van stap 1-3 en wacht op het tegoed.

## Niet in deze reactie

Bijlagen flagtelling/valkuilen: geen nieuw werk, sluiten al aan op `hba-besluiten-stopbericht-ronde-6-2026-10-20.md` §2.
Stap 3b (productie-onderzoek missende merkbeoordeling): blijft los, staat niet in Rogiers reactie van 21 okt.

## Zip ronde-7-pakket

**Gedaan 21 okt** na stap 3: `docs/rogier-pakket-ronde-7-2026-10-20.zip` met nieuwe kalender/feedback-shots + poll-JSON (`1dc37bb`). Checklist: `docs/rogier-pakket-ronde-7-2026-10-20/00-LEESMIJ.md`.

## Backlog (niet nu)

`scripts/run-hba-r0-ce04-post3-staging.sh`: logregels vóór JSON op stdout; `wait=0` triggert onterecht opnieuw CE-04 i.p.v. alleen poll. Workaround: poll-only via API/`pollOnly=1`. Zie `docs/BUILD-NOTES.md` (HBA-tooling).
