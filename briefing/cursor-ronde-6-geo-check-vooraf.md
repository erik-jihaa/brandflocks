# Uitvoerbriefing — ronde 6 campagne-assistent: HBA-check-vooraf ontbreekt

**Datum:** 2026-10-27
**Bron:** review van `https://staging.igisnext.com/campagnes` (volledige 8-stappen-wizard doorlopen met Brandpunt-testcasus uit `briefing/hba-input/bouwaddendum-ronde-8-strategieen-campagnes-geo-2026-10-27.md` §8) tegen de spec in die addendum.
**Status huidige build:** stappen 1-8 (wizard + planning-algoritme) werken correct. Het HBA-laag-deel (§5 van de addendum) ontbreekt volledig. Dit is het belangrijkste onderdeel van het addendum en moet nu gebouwd worden.

## Wat al goed werkt (geen actie nodig)

- Kanaalverdeling exact zoals ingevoerd (bv. 8 LinkedIn + 12 Instagram = 20 posts).
- Fase-percentages van sjabloon A kloppen precies (15/20/20/10/20/15% → 3/4/4/2/4/3 posts op 20).
- Kanaalregel "max 1 post per kanaal per dag" wordt gerespecteerd.
- Ankers worden toegepast op ~1-op-3 posts, in lijn met de richtwaarde uit §3.
- Na "Bevestig" landen alle posts correct in de agenda op datum/kanaal/fase.

## Gat 1 (hoofdprioriteit): `ce_hba_plan_check` bestaat niet

Ik heb de wizard tot en met stap 8 doorlopen en op "Bevestig" geklikt. Er is op geen moment een signalentabel getoond — niet vóór bevestigen (als gate), niet na bevestigen (als rapport). Dit is het kernonderdeel van addendum §5: de vooraf-check op Balans, Branchehygiëne, Regel, Algemene content, Dekking, Ankers te weinig, Ankers te veel, Doelpagina.

**Te bouwen:**
- Nieuwe, losstaande prompt `ce_hba_plan_check`, **niet** gekoppeld aan de bevroren v1.13-scoring (dat blijft puur voor losse artikel-hoofdoordelen). Zie addendum §5-tabel voor de volledige signalendefinitie per regel.
- Precedent in de code: `ce_hba_v2_run_theme_verdicts()` (`hba-v2-repository.php`, regel ~904) doet al batch-oordeel + gedeeld advies over meerdere items — qua structuur het dichtstbijzijnde bestaande patroon. Gebruik dat als uitgangspunt, niet als kopieer-sjabloon (andere input: een heel plan i.p.v. losse posts).
- Input: het volledige gegenereerde plan (alle posts met fase, kanaal, datum, anker) + de merkidentiteit/HBA-archetype van de klant + (indien aanwezig) de doelpagina-extract.
- Output: per signaal een status (bv. ok / aandachtspunt / blokkerend) + korte toelichting, getoond als stap tussen "plan gegenereerd" en "Bevestig" — dus als gate, niet als achteraf-rapport. Zie addendum §5 "vooraf" vs "achteraf" onderscheid: dit moet vóór bevestigen zichtbaar zijn.
- JSON-schema voor de plan-check-output: zie addendum §6.

**Test (verplicht, uit addendum §8):** bouw na implementatie twee versies van de Brandpunt-testcasus — de huidige (die geen regel breekt) en een tweede die bewust een merkregel breekt (bv. korting aanbieden op koffie die al weken geroosterd is). Bevestig dat versie 1 geen "Regel"-signaal krijgt en versie 2 wél. Zonder deze tweede test is dit gat niet als opgelost te beschouwen.

## Gat 2: feitenbank ontbreekt — alleen vragen zijn selecteerbaar als anker

Stap 4 (Verhaal en ankers) trekt ankers nu alleen uit bestaande GEO-vragen (Vindbaarheid). Addendum §3 vraagt een bank van **vragen én feiten** (10+10 voor de Brandpunt-test), en §9/ontwerp vraagt 1-3 vragen ÉN 1-3 feiten als ankers per campagne — niet alleen vragen.

**Te bouwen:**
- Nieuwe entiteit "feit" naast de bestaande GEO-vraag, met hetzelfde soort opslag/koppeling als vragen nu hebben (zie Vindbaarheid/Search-module als precedent, al is die nu beperkt tot 5 vragen zonder tracking — dat is een bekende beperking, zie codereview-document).
- Schema: zie addendum §6 "Vragen-/feitenbank".
- Stap 4 van de wizard moet feiten naast vragen tonen als ankeroptie.
- Voor de Brandpunt-test: vul een bank van 10 vragen + 10 feiten (zoals addendum §8 vraagt) om de wizard mee te testen.

## Gat 3: merk_onderdeel-selectie in stap 4 is incompleet en niet aanpasbaar

- Toont nu 4 van de 5 enum-waarden; `waar_je_tegen_bent` (de tegenkracht/foe-dimensie) ontbreekt.
- De sidebar meldt dat de fase het merk_onderdeel bepaalt, terwijl de addendum zegt "de klant mag wijzigen" — dit moet een bewerkbaar veld worden, geen afgeleide/vaste waarde.

**Te bouwen:** voeg de ontbrekende enum-waarde toe en maak het veld bewerkbaar (bureau-gebruiker kan de voorgestelde waarde per post overschrijven).

## Open vraag aan Erik — nog niet beantwoord, niet starten voor dit onderdeel

Stap 7 (fase-opbouw) ondersteunt nu alleen "weglaten". Addendum zegt dat de klant fases moet kunnen **verschuiven, weglaten of toevoegen**. Niet gespecificeerd: hoe herverdelen de percentages als je een fase toevoegt of de volgorde wijzigt? Schaalt het automatisch naar rato, of mag de bureau-gebruiker zelf percentages intypen?

Dit is een UX-ontwerpkeuze (ligt bij Erik, niet bij Rogier — addendum: "ontwerp is van Erik"). **Cursor: niet starten aan "verschuiven/toevoegen" tot deze keuze is gemaakt.** Weglaten (bestaande functionaliteit) blijft intussen gewoon werken.

## Volgorde van bouwen

1. Gat 1 (`ce_hba_plan_check`) — hoogste prioriteit, dit is de kern van het hele addendum.
2. Gat 2 (feitenbank) — nodig om Gat 1 volledig te kunnen testen met echte ankers (vragen + feiten).
3. Gat 3 (merk_onderdeel) — kleinere losse fix, kan parallel.
4. Fase-verschuiven/toevoegen — wacht op Eriks keuze.

---
🤖 Generated with [Claude Code](https://claude.com/claude-code)
