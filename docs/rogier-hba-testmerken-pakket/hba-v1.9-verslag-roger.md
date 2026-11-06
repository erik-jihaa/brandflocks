# HBA v1.9 — verslag voor Rogier (staging)

**Datum:** 2026-10-13  
**Omgeving:** https://staging.igisnext.com  
**Branch:** `hba-ronde-4-v1.9`  
**Scoring-model:** `claude-sonnet-4-6`  
**Prompt:** **v1.9** (ronde 4: leidingen, thema-conflict, regelbreuk-verificatie)  
**Opdracht:** `briefing/hba-input/hba-ronde-4-v1.9.md`

Bijlagen: `docs/stabiliteit-v1.9.md`, `docs/stabiliteit-v1.9-5runs.json`, `docs/rogier-hba-testmerken-pakket/` (definitiebestanden).

---

## 1. Executive summary

| Onderdeel | Status |
| --- | --- |
| **Stap 1** — self_review, HBA-hint, CE-04 | Groen op staging (V0 + post-3 hertest) |
| **Stap 2** — schrijver kiest regel (thema-conflict) | Groen — V0 alle drie **op_merk**, keuze **a** |
| **Stap 3** — regelbreuk + verificatie | Groen op matrix — **0** valse breuken verworpen in 5×17 |
| **Stap 4** — testset opschonen | Groen — **1 review** (Brandpunt post 6), koffie-branche |
| **Stap 5** — 5×17 stabiliteit v1.9 | **16/17** stable goed, **85/85** scores, **0%** PHP-hoofdoordeel-correctie |

**Open ronde-criterium:** **3×** flag-citaat op **functioneel onderdeel** (Stelwerk post 2 — CTA in hetzelfde citaat als `category_fallback`). Geen scoring-bug; wel meetlat vs. Rogiers “0 functionele flags”.

**Kalibratie (geen bug):** Brandpunt post 6 blijft **review** (5/5 `op_merk`, ambigu verwacht).

---

## 2. Per stap (gewone taal)

### Stap 1 — Leidingen

- **Oorzaak lege velden:** CE-04 las `$input` van de verkeerde node (`Clear Old Feedback` i.p.v. `Claude Review`); self_review/hint-slot-endpoint en parsing ontbraken op enkele paden.
- **Fix:** Build Feedback Rows → `$('Claude Review')`; CE-03 self_review + hbaHint-endpoint; duidelijke fout als tool-output ontbreekt (`stop_reason` + raw snippet).
- **V0:** `self_review` en `hbaHint` gevuld voor alle drie merken (`docs/hba-v0-testresultaat.json`).

### Stap 2 — Schrijver en regel

- Thema-conflictcheck vóór schrijven in CE-03 (n8n), standaard keuze **a** (regel verdedigen).
- Fix circular ref in Prepare Theme Conflict (`Format Data` i.p.v. Merge).
- V0 na stap 2: geen `regelbreuk`; conflict + keuze **a** in self_review.

### Stap 3 — Regelbreuk scherper

- Prompt **v1.9**; verplicht `constraint_breach_uitleg` in code; tweede verificatie-call (temp 0).
- Verificatie krijgt **geloofwaardigheidstest + verboden logica** (Duinroos wachtlijst/invalkrachten blijft echte breuk).
- 5×17: **15** behouden breuken, **0** verworpen, **0** zonder uitleg.

### Stap 4 — Testset

- Duinroos post 6 → `verwacht_hoofdoordeel: tegenkracht`.
- Post 4 Stelwerk/Duinroos niet meer ambigu (`neutraal`).
- Brandpunt → branche **koffie** + markthygiëne (o.a. vers gebrand, duurzaam).
- Stabiliteitsrapport: constraint_breach-telling + V0-leidingen.

### Stap 5 — Stabiliteit

| Metric | v1.8.1 (referentie) | **v1.9** (13 okt 2026) |
| --- | ---: | ---: |
| Stable goed (5/5) | 12 / 17 | **16 / 17** |
| Instabiel | 2 | **0** |
| Review | 3 | **1** |
| Fout | 0 | **0** |
| Scores | 85 | **85** |
| `hoofdoordeel_gecorrigeerd` | 0% | **0%** |
| Valse regelbreuk (verificatie nee) | — | **0** |
| Flags functionele onderdelen | 0 | **3** (Stelwerk post 2) |

Post 2 Stelwerk en post 6 Duinroos: **5/5** (was instabiel in v1.8.1).

---

## 3. Verworpen `constraint_breach` (5×17)

Geen enkele verworpen breuk in de definitieve run.

| # | Merk | Post | Reden |
| --- | --- | --- | --- |
| — | — | — | *Leeg — `verworpenDoorVerificatie`: 0* |

---

## 4. V0 — drie verleidingstest-posts

### Brandpunt Koffie — Black Friday-actie

**Klantpreview:** https://staging.igisnext.com/article/b0692de8-c6f0-11f1-9456-9c6b00b7cd02?hbaKlant=1

**Volledige tekst (staging):**

Wij doen niet mee aan Black Friday. En dat is geen marketingtruc.

Koffie die langer dan zes weken geleden is gebrand, zetten wij niet in de schappen. Niet voor de volle prijs, en ook niet met een vette korting erop. Dat is geen principe voor de bühne — het is gewoon hoe wij werken.

Wat er op een gegeven moment over is, gaat naar de buurtvoedselbank. Geen uitverkoop, geen oude voorraad opruimen onder het mom van een actie.

Black Friday draait voor veel merken om het wegruimen van wat blijft liggen. Begrijpelijk, maar het past niet bij wat wij onze klanten beloven: dat wat er in de zak zit, vers is. Dat er iemand heeft nagedacht over wanneer het gebrand is, hoe het bewaard wordt en wanneer het de deur uitgaat.

Op de drukste kortingsdag van het jaar verandert er bij ons dus niets. Dat is precies het punt.

**self_review:** themeConflict=True, keuze **a** — Een Black Friday-actie draait per definitie om kortingen en het wegwerken van voorraad. Beide zijn verboden: het merk geeft geen kortingen, en oudere voorraad gaat naar de voedselbank of wordt weggegooid — niet met korting verkocht. De schrijver wordt bijna automatisch verleid om een aanbieding of stapelkorting te beloven, wat de regel direct breekt.

**HBA-hint:** De slotzin 'Dat is precies het punt' is een gemiste kans: hij sluit af met een vage bewering in plaats van te laten zien wat de lezer wél krijgt als er niets verandert. Maak concreet wat die onveranderlijkheid voor de koffie in de zak betekent.

**Scorer (achteraf):** hoofdoordeel `op_merk`, flags `None`

### Stelwerk — Nieuw partnerschap met een ERP-leverancier

**Klantpreview:** https://staging.igisnext.com/article/e2b10b96-c6f0-11f1-9456-9c6b00b7cd02?hbaKlant=1

**Volledige tekst (staging):**

Wij werken met een ERP-leverancier samen — en we hebben bewust geen resellercontract getekend. Geen commissie, geen verkoopquota, geen reden om iets aan te raden dat voor ons rendeert.

Wat we wél hebben: jaren ervaring met hoe dit systeem zich gedraagt als het echt wordt ingezet. Niet in een demo, maar in een maakbedrijf met legacy-data, wisselende orderstromen en een IT-afdeling van twee man.

Dat verschil is niet klein. Een leverancier verkoopt mogelijkheden. Wij stellen eerst de vraag wat een bedrijf over tien jaar nodig heeft — en kijken dan of dit systeem daarbij past. Soms is het antwoord ja. Soms ook niet.

Die afstand is geen gebrek aan betrokkenheid. Het is precies wat een opdrachtgever nodig heeft op het moment dat verkooplogica het hardst trekt.

**self_review:** themeConflict=True, keuze **a** — Een post over een nieuw partnerschap met een ERP-leverancier verleidt de schrijver bijna onvermijdelijk tot verboden logica: het woord 'partnerschap' staat letterlijk op de verboden lijst (partnerschap en ontzorgen), en de context roept de suggestie op van een commerciële band met een softwareleverancier — wat direct botst met de regel dat het merk nooit commissie ontvangt en geen resellercontracten heeft. Zonder die context klinkt de post als precies het tegenovergestelde van wat het merk belooft.

**HBA-hint:** De slotzin ('Die afstand is geen gebrek aan betrokkenheid. Het is precies wat een opdrachtgever nodig heeft op het moment dat verkooplogica het hardst trekt.') verdedigt de positie in abstracte termen die elke adviseur kan zeggen. De tekst laat nog niet zien wat die afstand in de praktijk betekent: dat je soms tegen een bedrijf zegt dat dit systeem niet past, ook als je de kennis hebt om de implementatie te begeleiden. Dat concrete moment — het advies dat geld kost — ontbreekt.

**Scorer (achteraf):** hoofdoordeel `op_merk`, flags `['proof_gap']`

### Duinroos Thuiszorg — Wachtlijst in één maand weggewerkt

**Klantpreview:** https://staging.igisnext.com/article/1bf93079-c6f1-11f1-9456-9c6b00b7cd02?hbaKlant=1

**Volledige tekst (staging):**

Onze wachtlijst is in één maand opgelost. Maar niet zoals je misschien denkt.

We hebben geen extra mensen aangenomen. We hebben niemand harder laten werken. We hebben één cliënt bewust doorverwezen naar een andere aanbieder.

Dat klinkt misschien koud. Maar de reden is precies het tegenovergestelde.

Bij Duinroos werken maximaal vier vaste zorgverleners bij één cliënt. Niet als ambitie, maar als harde grens. Want als een oudere elke week andere gezichten aan de deur krijgt, werkt de zorg gewoon slechter. Je kent iemands gewoonten niet. Je ziet niet wat er gisteren anders was. Dat vertrouwen bouw je niet op met een volle agenda.

Door één cliënt elders onder te brengen — bij een aanbieder die wél de ruimte had om goede zorg te bieden — konden de overige mensen op de wachtlijst terecht bij zorgverleners die ze al kenden.

Dat was een moeilijke keuze. En tegelijk de enige die klopte.

Een wachtlijst oplossen door de lat te verlagen voor de mensen die al bij je zijn: dat doen we niet.

**self_review:** themeConflict=True, keuze **a** — Het thema 'wachtlijst in één maand weggewerkt' verleidt de schrijver bijna onvermijdelijk tot de verboden logica: een wachtlijst wegwerken klinkt als een prestatie van efficiëntie of productiviteit, en de meest voor de hand liggende verklaring is extra inzet van invalkrachten. Beide zijn expliciet verboden. Bovendien dreigt de vier-zorgverlenersregel in het gedrang te komen als snelheid het verhaal domineert. Er bestaat echter een geloofwaardige regelversie: de wachtlijst is weggewerkt doordat het merk een nieuwe cliënt heeft doorverwezen in plaats van een vijfde zorgverlener in te zetten — precies zoals de regel voorschrijft. Dat verhaal is eerlijk en verdedigt de regel.

**HBA-hint:** De slotzin zegt wat jullie niet doen, maar de lezer weet daarna nog niet wat er wél gebeurt met de mensen die jullie doorverwijzen — voeg één concreet detail toe over hoe die doorverwijzing er in de praktijk uitziet, zodat de keuze voelbaar wordt in plaats van alleen principieel.

**Scorer (achteraf):** hoofdoordeel `op_merk`, flags `None`


---

## 5. CE-04

- **Post 3 (nulmeting):** handmatige hertest na Build Feedback Rows-fix — regel + citaat voor alle drie merken (zie `docs/rogier-hba-testmerken-pakket/hba-v0-testresultaat.md`, stap 1).
- **Let op:** `hba-v0-testresultaat.json` → `ce04.resultsWithFeedback` kan nog oude fallback tonen (run vóór fix); leidend is de hertest 12–13 okt.

---

## 6. Wat niet gelukt is (ronde-criteria)

1. **Flags op functionele onderdelen = 0** — in 3 runs citeert het model bij Stelwerk post 2 de **hele** zin inclusief “Neem contact op…”. De hygiene-check telt dat als functioneel citaat. Voorstel voor volgende ronde: citaat trimmen op CTA-zinnen vóór de functionele-flag-telling, of prompt: alleen merkzin citeren.
2. **Brandpunt post 6** — blijft bewuste **review** (kalibratie markthygiëne vs. op_merk).

---

## 7. Bestanden

| Bestand | Rol |
| --- | --- |
| `docs/stabiliteit-v1.9.md` | Leesbaar stabiliteitsrapport |
| `docs/stabiliteit-v1.9-5runs.json` | Ruwe 5×17 aggregatie |
| `web/api/prompts/hba-regelbreuk-definitie.txt` | Definitie regelbreuk |
| `web/api/prompts/hba-flags-definitie.txt` | Flags + uitleg |
| `docs/hba-v0-testresultaat.json` | V0 machine-leesbaar |
