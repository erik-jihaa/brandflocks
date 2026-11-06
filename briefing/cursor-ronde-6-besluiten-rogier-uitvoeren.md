# Cursor-briefing — besluiten Rogier ronde 6 uitvoeren

> **Afgehandeld / vervallen (19 oktober 2026).** Vervangen door `briefing/cursor-ronde-6-afronden-en-stoppen.md`. Niet meer als werkopdracht gebruiken.

**Eigenaar:** Erik. **Datum:** 19 oktober 2026.
**Bron:** `briefing/hba-input/hba-besluiten-ronde-6-2026-10-19.md` en `terugkoppeling-rogier-ronde-6-ingevuld.md`.
**Branch:** `hba-ronde-6-pilotklaar`.
**Model:** Opus 5 voor stap 2 en 3 (promptwerk), Composer 2.5 voor de rest.

## De belangrijkste randvoorwaarde

**Er is nog één 5 × 19 beschikbaar.** Dat is poging 2 van de 2 uit de briefing. Alles wat de scoring raakt moet er dus ín zitten vóór die run: stap 2, 3 en 4 hieronder. Stap 1 is meetwerk dat de run interpreteerbaar maakt. Stap 6 is weergave en mag parallel of erna.

Haalt de run geen 16 posts 5 van 5 goed — met Stelwerk post 5 en Duinroos post 7 als toegestane uitzondering — dan stoppen en de ruwe output sturen. Niet opnieuw aan de prompt schuiven.

---

## Stap 1 — eerst eerlijk tellen en loggen

### 1a. Een lege verwachting is geen fout

Rogier heeft de ruwe JSON nagekeken en vastgesteld dat drie van onze "zeven consistente fouten" op `verwacht = null` stonden. Daar viel niets tegen af te meten; onze matrix telde ze als fout. We hebben het probleem dus groter gerapporteerd dan het was.

Splits in de testmerkenmatrix en in `hba-testmerken-stabiliteit.md` de uitkomst in **goed / fout / niet beoordeeld**, net zoals bij de ijkrun al gebeurt. Een post zonder verwachting komt in de derde kolom en telt niet mee in het foutgetal.

### 1b. De logging is leeg

In `hba-testmerken-stability-v1.11-5runs.json` staan `kern_categorietaal` en `hoofdoordeel_volgt_uit_flags` bij alle 95 scores op `null`, terwijl `hoofdoordeel` wél gevuld is.

Dit is een exportfout, geen scoringsfout: `hba-prompt.php:302` zet `kern_categorietaal` altijd op ja of nee. De mapping in `hba-testmerken-repository.php` (rond regel 1113 en 1304) pakt de waarde niet op — `$raw['kern_categorietaal'] ?? $dto['kernCategorietaal']` levert in beide takken niets. Zoek uit welke van de twee de waarde zou moeten dragen en repareer het.

Log vanaf nu per score: `merkbewering`, `kern_categorietaal`, `hoofdoordeel` en `hoofdoordeel_volgt_uit_flags`. Zonder die vier is de komende run niet te duiden, en we hebben er maar één.

**Klaar als:** een proefscore van één post alle vier de velden gevuld in de export zet, en de matrix drie kolommen heeft.

---

## Stap 2 — besluit 1: `merkbewering` en de nieuwe volgorde

### 2a. Nieuw modelveld

Het model levert er één veld bij: `merkbewering: ja/nee`.

- **Ja** als de tekst iets beweert over wat het merk is, doet of belooft, inclusief beloftes over product of dienst.
- **Nee** bij werk- en producttaal, service, uitleg, tips, vacatures en aankondigingen die niets over het merk beweren.

Zet die definitie in `web/api/prompts/hba-hoofdoordeel-definitie.txt`, naast de bestaande tekst, en neem het veld op in het outputschema in `hba-prompt.php`.

`kern_categorietaal` beoordeelt het model **alleen nog als `merkbewering = ja`**. Bij nee levert het model daar `null`.

### 2b. De volgorde in PHP

`ce_hba_resolve_hoofdoordeel()` krijgt vijf treden:

1. `regelbreuk` — `constraint_breach` die de controlecall bevestigt;
2. `tegenkracht` — `foe_vervaging` met citaat uit de kop of de openingszin;
3. `merkbewering = nee` → direct door naar trede 5;
4. `categorietaal` — `kern_categorietaal = ja`;
5. `op_merk` of `neutraal` op merkbijdrage en contentrol, ongewijzigd.

Regelbreuk en tegenkracht blijven vóór de nieuwe trede: ook een tipspost kan de regel breken.

**Controleer dit expliciet en meld het:** een post met `merkbewering = nee` komt op trede 5 terecht en kan daar in principe `op_merk` worden, terwijl hij niets over het merk beweert. Meet bij de drie neutrale posts (ontkalken, vacature, webinar) wat eruit komt. Is het `op_merk` in plaats van `neutraal`, meld dat dan — dat is een vraag voor Rogier, geen eigen aanpassing.

### 2c. Categorietaal gaat over identiteit, niet over service

Sluit aan op het veld `soort` (`identiteit` of `service`) in de branchebibliotheek. Een hygiënefactor van het soort `service` maakt een tekst niet tot categorietaal.

---

## Stap 3 — trede 2 opschonen

Rogier vraagt waarom trede 2 in de code de voorwaarde `kern_categorietaal = nee` heeft, die niet in §3 staat. **Antwoord: er is geen goede reden; haal hem weg.**

In `ce_hba_resolve_hoofdoordeel()` staat nu `if (foe_vervaging && $foeOnKern && !$kernCat)`. Zijn §3 zegt dat een tegenkracht in de kop of de openingszin wint van categorietaal, en dat alleen een tegenkracht in een bijzin verliest. De extra voorwaarde draait dat om en laat categorietaal winnen van een foe op de kop. Met de nieuwe trede 3 erbij is hij ook overbodig geworden.

Verwijder `&& !$kernCat` uit trede 2 en werk de definitietekst bij, zodat prompt en code weer één volgorde beschrijven.

**Meet het effect apart op Stelwerk post 5 en Duinroos post 7**, de twee posts die hieraan hangen, en leg dat vast vóór de 5 × 19.

---

## Stap 4 — besluit 5: de drie verwachtingen vastleggen

In de testmerken, vóór de run:

| Post | Verwachting |
| --- | --- |
| Brandpunt — mechanisme dat markthygiëne is | `categorietaal` |
| Stelwerk — samenwerking zonder bewijs | `op_merk`, met `proof_gap` als advies |
| Duinroos — kernbewering zonder bewijs (post 7) | `tegenkracht`, merkbijdrage hoogstens 5 |

Duinroos post 7 stond op `null` en is daardoor als review geteld; hij is in werkelijkheid instabiel (4 van 5 tegenkracht, 1 van 5 op_merk). Met deze verwachting komt hij in de juiste kolom.

---

## Stap 5 — de 5 × 19 (poging 2 van 2)

Draai pas als stap 1 tot en met 4 klaar zijn. Promptversie verhogen.

**Klaar als:** minstens 16 posts 5 van 5 goed, en de enige afwijkingen zijn Stelwerk post 5 en Duinroos post 7. Dan start de pilot en gaan die twee als bekende beperking in de pilotnotities.

**Haalt de run de 16 niet:** stoppen en de ruwe output sturen, met per afwijkende post de vier gelogde velden uit stap 1b. Niet nog een keer draaien.

---

## Stap 6 — de zes A6-restpunten (weergave, mag parallel)

Rogier is akkoord op de klanttaal, met zes punten. Die vallen niet onder de bevriezing.

1. **Merkelementen:** doelgroeplabels "b to c", "personal", "b to b" worden "Consumenten", "Persoonlijk (ik-vorm)" en "Zakelijk".
2. **Terugkoppeling:** "okay | Verwijder" wordt "Akkoord | Verwijderen". De kolom met de opmerking wordt nu afgekapt; laat hem volledig zien.
3. **Agenda:** datums in nl-NL ("18 oktober 2026", "5 oktober – 11 oktober").
4. **Overzicht:** "Ritme 0/1 per w…" en "Achter op ritm…" vallen rechts weg. Repareer de breedte.
5. **Rapport:** het oude rapport heet nog "(HBA v1.7)". Hernoem het of verberg de versie bij weergave.
6. **Hashtags vallen buiten de controle.** De app zet `#versgebrand` onder een Brandpunt-post, terwijl "vers gebrand als hoofdclaim zonder de zes-wekenregel" bij Brandpunt onder "nooit over jezelf lezen" staat. Neem hashtags mee in de verbodenlijst van de generator en in de scoring van de tekst.

Punt 6 raakt de generator, niet de scoring van de testmerken. Bouw het, maar draai de 5 × 19 er niet opnieuw voor.

Maak na afloop nieuwe screenshots van alleen de gewijzigde schermen.

---

## Niet in deze ronde

- De Vindbaarheid-meter afstemmen op Rogiers GEO Identity Gap. Hij stuurt daar na de pilot een werkdocument voor; nu geen actie.
- De flagtelling en de drempels van de Implementation Kit. Ons voorstel ligt klaar, maar Rogier heeft er nog niet op gereageerd; dat gaat mee in de volgende terugkoppeling, samen met de prioriteit van de valkuilassen.

## Afronden

- `php -l`, `py_compile` en `npm run build`.
- Eén commit per stap, met het stapnummer in de message.
- Geen staging zonder een expliciete "ja staging" van mij. Niets op productie.
- Verslag aan Erik per stap: klaar / gewijzigd / waar je een keuze moest maken.
