> **Achterhaald door `cursor-linkedin-scan-unieke-positionering.md` (18 aug).** De pitch is
> opnieuw aangescherpt: niet tegenstander/consistentie als hoofdclaim, maar unieke positionering
> (vinden + vasthouden). De intro-pitch-tekst hieronder in §2 is niet meer wat er in de code staat.
> Gebruik dit document alleen nog voor de deploy-stappen in §3-4, niet voor de tekst in §2.

# LinkedIn Scan-tool — tegenstander-framing in de intro-pitch + deploy

**Vervolg op** `briefing/cursor-linkedin-scan-narrative.md`.
**Raakt:** `linkedin-analyse/api/report-renderer.php` (`li_render_intro_html()`), productie op
`https://linkedin-analyse.igisnext.com`.
**Status:** code al aangepast in deze branch/werkkopie — nog niet gedeployed.

---

## 1. Waarom

De scan-mail en de sales-pitch (zie `briefing/pitch-contentflow.md`) moeten dezelfde taal spreken.
De pitch is scherp gezet op één mechaniek: een merk wordt door AI opgenomen in antwoorden als het
een duidelijke tegenstander kiest **en aantoonbaar laat zien dat het die aanpakt** — niet alleen
benoemt. De scan-mail claimde tot nu toe alleen het bredere "consistent en onderscheidend zijn",
zonder die tegenstander-mechaniek expliciet te maken. Dat is een gemiste kans: de scan-mail is het
eerste contactmoment met een prospect, en moet dezelfde haak zetten als het gesprek dat erop volgt.

## 2. Wat er is aangepast

In `li_render_intro_html()` is de pitch-alinea herschreven. Was:

> "...AI-modellen en zoekmachines filteren steeds harder op wat gemiddeld is — en herkennen en
> bevelen juist aan wat consistent en onderscheidend blijft. Een merk dat overal hetzelfde,
> herkenbare verhaal vertelt, wordt daardoor zichtbaarder, niet onopvallender. Daar bouwt
> ContentFlow op."

Wordt (herschreven op 18 aug — de eerste versie las te stroef, te veel bijzinnen in te weinig
zinnen):

> "...AI-modellen en zoekmachines filteren het gemiddelde juist weg, en herkennen wat opvalt:
> merken met een scherpe tegenstander, die ze niet alleen benoemen maar aantoonbaar aanpakken.
> Zo'n merk vertelt overal hetzelfde herkenbare verhaal, en wordt daardoor zichtbaarder, niet
> onopvallend. Daar bouwt ContentFlow op: merkconsistentie."

Alleen deze alinea is gewijzigd. `li_render_case_line()` en `li_narrative_benefit_block()` zijn
bewust ongemoeid gelaten — die zijn al gebaseerd op de eigen data van de klant (kernboodschap,
consistency-score) en hebben de tegenstander-taal niet nodig om te werken. Als je die er later ook
scherper op wil zetten: `li_narrative_benefit_block()` (regel ~549) is de plek, maar dat is een
losse beslissing, niet iets wat automatisch uit deze wijziging volgt.

## 3. Testen vóór deploy

```bash
cd linkedin-analyse
php scripts/test-analyzer.php     # ongewijzigd — puur controle dat er niets brak
php scripts/test-narrative.php    # genereert een voorbeeldmail met de nieuwe intro-tekst
```

Lees de output van `test-narrative.php` terug: de eerste alinea moet de nieuwe tegenstander-zin
bevatten. Check ook visueel via **Opnieuw ophalen** op de GrowthElements-testcase in de live tool
na deploy (§4) — dat is de canonieke testcase uit `BUILD.md`.

## 4. Deploy

```bash
# vanaf CE-Cursor/
cp docs/igisnext-identity.css linkedin-analyse/assets/   # alleen als identity-CSS ook gewijzigd is — hier niet, maar staande instructie uit BUILD.md
./scripts/deploy-linkedin-analyse.sh production
```

Na deploy: BUILD.md's changelog-tabel krijgt een regel (datum + "Tegenstander-framing in
intro-pitch, zelfde taal als sales-pitch").

## 5. Acceptatiecriteria

- Een nieuwe scan-mail (of "Opnieuw ophalen" op een bestaande) toont de nieuwe pitch-alinea met de
  tegenstander-zin, woordelijk zoals in §2.
- `test-analyzer.php` en `test-narrative.php` draaien beide zonder fouten.
- De rest van de mail (cijfers, "Wat opvalt", narrative-blok, benefits, onderwerpregel) is
  ongewijzigd — dit is een tekstwijziging in één functie, geen structuurwijziging.
- BUILD.md changelog bijgewerkt.
