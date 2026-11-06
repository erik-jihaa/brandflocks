# HBA: reactie op v1.10 en ijkset v0.2

Rogier Heijning, 15 oktober 2026

---

## Voor Erik

Erik, deel A is wat mij betreft klaar voor de pilot, op één voorwaarde (punt 1 hieronder). Deel B is dichtbij: de scorer ziet het probleem bij Duinroos post 7 wel, alleen onder een andere flag. Dat los je op in de code, niet met nog een promptronde.

Daarnaast zit de ijkset weer in dit pakket. Je AI had gelijk: het getal 41 was de ijkset, geen lijst sectoren. Ik heb hem nagelopen en bevestigd, en er vijf gevallen uit de testmerken aan toegevoegd. Het zijn er nu 46.

Je kunt dit hele document aan Cursor geven. Werk stap voor stap en maak per stap één commit. Zet niets op productie.

---

## Deel 1: reactie op v1.10

### 1. Researchkoppeling (vóór de pilot)

In alle drie de V0-runs in `hba-v1.10-stap2-v0-verleiding.json` staat bij `fetchSlotResearch`: `"Slot niet gevonden"`. Bij Stelwerk zegt de self_review dat de research over zorgwachttijden gaat (Rijksoverheid, Consumentenbond). Dat is research die bij Duinroos hoort.

Zoek uit waar die research vandaan komt:

- Komt het door de testopzet (bijvoorbeeld een gedeelde fixture), dan is het geen blokkade. Leg dan uit hoe het zit.
- Kan research van de ene tenant bij een andere terechtkomen, dan start de pilot niet voordat dat dicht is.

**Klaar als:** bij elke V0-run de research van het eigen merk wordt opgehaald, en `fetchSlotResearch` geen fout meer geeft.

### 2. Versieregistratie (vóór de pilot)

Leg bij elke HBA-uitkomst vast met welke promptversie en welk model hij is gemaakt: thema-advies, research-advies, schrijfhint, architectuurcheck en artikelscore. Bij de artikelscore gebeurt dat al. Elke pilotrun zonder deze registratie kunnen we later niet meer plaatsen.

**Klaar als:** elke opgeslagen HBA-uitkomst een promptversie en een model heeft.

### 3. Scorer: vaste grens in de code (deel B, stap 4)

Duinroos post 7 krijgt in 5 van de 5 runs `foe_vervaging` op precies de kop: "Onze wachtlijst is in één maand opgelost." De scorer ziet het probleem dus wel. Maar de grens van merkbijdrage 5 hangt alleen aan `proof_gap`, dus blijft het cijfer 8.

Stelwerk post 6 krijgt wel `proof_gap`, maar soms op de ervaringszin en soms op de samenwerkingszin. Het model ziet die zin dan blijkbaar niet als kernbewering, en de merkbijdrage blijft 7.

Pas dit aan:

1. **Vaste grens in PHP, niet in de prompt.** Citeert een risicoflag (`proof_gap` of `foe_vervaging`) de kop of de openingszin, dan is de merkbijdrage hoogstens 5. Leg vast welke zinnen als kop en openingszin tellen.
2. **Verwachting Duinroos post 7.** Verander in de testset: `proof_gap` óf `foe_vervaging` op de kop, merkbijdrage hoogstens 5. Stelwerk post 6 blijft hoogstens 6; met de vaste grens komt die daar vanzelf onder.

**Klaar als:** beide kalibratieposts 5/5 de verwachte flag krijgen en onder de grens blijven, zonder achteruitgang op de 16 stabiele posts. Let vooral op post 1 en Brandpunt post 5.

### 4. Bewijs dat nog ontbreekt (deel B)

- `constraint_breach_verificatie` staat niet per geval in de ruwe JSON, alleen als totaal (15 behouden, 0 verworpen). Zet het per geval erin, met ja/nee en de reden.
- De losse test van de controlecall (6 gevallen × 5 runs) zit niet in het pakket. Het verslag verwijst naar een stap-1-document dat er niet bij zit. Lever dat mee.

### 5. Kleine punten in de hints

- Brandpunt: hetzelfde invulveld staat twee keer in `invulvelden`. De hint staat in `ctaNa` en niet in `passageNa`.
- Duinroos: twee invulvelden in de lijst, maar maar één in de tekst.
- Controleer in de klantpreview of de hints uit stap 3 zichtbaar zijn en niet die uit stap 2.

### 6. Brandpunt post 6

Blijft review en blokkeert niets. Die gaat naar de kalibratiemodule.

### 7. Documentatie

`docs/BUILD-NOTES.md` is sinds 30 september niet bijgewerkt. Zet de actuele stand erin, zodat die en de rondedocumenten hetzelfde zeggen.

---

## Deel 2: ijkset v0.2

### Bestanden

- `HBA_ijkset_v0_2.json`: 46 ijkgevallen in het formaat van het datamodel (tabel `ijkgeval`).
- `HBA_ijkset_v0_2.xlsx`: dezelfde gevallen om te lezen, met mijn oordeel per geval.

Zet de JSON terug in `briefing/hba-input/`. Daar stond v0.1 eind september nog; nu staat hij er niet meer. Alle oordelen zijn bevestigd door mij.

### Wat er veranderd is ten opzichte van v0.1

| Geval | Was | Is | Waarom |
| --- | --- | --- | --- |
| A04 Doornvogel aangescherpt | sterk | aanscherpen (50-65) | Aanbevelingen meten en afstand nemen van punten en korting zijn gangbaar in de branche, ook bij Nederlandse bureaus. Het onderscheid zit alleen in de regel (alleen merken die promoties afbouwen). |
| C04 Doornvogel aangescherpt | positionerend | grensgeval | Volgt uit A04. Alleen de toelatingseis overleeft de compressie. |
| A06 Fysio Loop | aanscherpen | zwak (20-40) | Doel, niche en regel zijn alle drie gangbaar. |
| A08 Brander Oost | interne tegenspraak | regel is geen grens | De regel zegt wat het merk wél doet ("we leveren ook aan..., mits"). Het advies moet vragen om een regel in de vorm "wij leveren nooit ..., ook niet als ...". |
| T06 tipspost, T11 review | zwak | neutraal | Basisniveau van de branche breekt niets af. |
| T12 wifi bij Hoeve Stil | tegenstrijdig | tegenkracht | Spreekt het onderscheid tegen, niet de regel. |
| T04, T09 | tegenstrijdig | regelbreuk | Dezelfde term als in de app. |
| Alle gevallen | "risico_flag" in Mag niet | "risico_flag op de kernbewering" | Na v1.10 mag de scorer elders een flag zetten; alleen een flag op de kop of openingszin is fout. |

### Nieuwe gevallen uit de testmerken

| Geval | Soort | Wat het test |
| --- | --- | --- |
| A18 Duinroos | architectuurcheck | Merk valt samen met de branche; de regel moet alle bewijslast dragen. |
| T13 Duinroos | tekstscore | Kernbewering die niet klopt ("wachtlijst in één maand opgelost"). Dit is Duinroos post 7. |
| T14 Stelwerk | tekstscore | Samenwerking en ervaring die het merk niet heeft. Dit is Stelwerk post 6. |
| V01 Duinroos | verleidingsthema | De schrijver neemt de premisse van het thema niet over. Nieuw soort geval. |
| S05 Brandpunt | schrijfadvies | De hint vraagt om een feit met een invulveld. Feiten uit de bewijsvelden mogen wel, zoals de proefochtend op de eerste donderdag van de maand. |

### Definities

- **Zwak:** breekt iets af voor het merk.
- **Neutraal:** draagt niet bij, maar breekt niets af (basisniveau van de branche, service, engagement).
- **Regelbreuk:** alleen als de regel zelf wordt overtreden. Tegenspraak met wat_anders of niet_in_geloven valt onder tegenkracht. Anders lopen de termen voor de klant door elkaar.

### Wat jij moet koppelen

1. **Scoreschaal.** De ijkset rekent in 0-100 (`score_min`, `score_max`), de app geeft een merkbijdrage van 0-10. Leg vast hoe die zich tot elkaar verhouden, ook in het licht van de merkcijferformule die nu door 10 deelt.
2. **Oordeellabels.** Bij tekstscores gebruikt de ijkset sterk, neutraal, zwak, middelmatig, regelbreuk en tegenkracht. Maak een koppeltabel naar het hoofdoordeel van de app (op_merk, categorietaal, regelbreuk, tegenkracht, neutraal) plus de merkbijdrage. Bij architectuurchecks zijn het de oordelen van de check zelf (sterk, aanscherpen, zwak), bij compressietests positionerend, opgelost en grensgeval.
3. **Contentrollen.** De ijkset noemt positionering, bewijs, engagement, verkoop, kennis, service en aankondiging. Controleer of die overeenkomen met de contentrollen in de app.
4. **Signaalnamen.** Dat zijn voorstellen. Koppel ze aan de velden die de app teruggeeft. Bestaat een veld nog niet, controleer het dan via de redenering, zoals in de ijkset staat.
5. **Verleidingsthema (V01).** Koppel dit aan de route van de V0-verleidingstest.

### Wanneer gebruiken

Nog geen volledige ijkrun. Eerst deel 1 punt 2 (versieregistratie) en punt 3 (vaste grens). Daarna één ijkrun als nulmeting, met per uitkomst `ijkset_versie`, promptversie en model. De gevallen met `valkuil: true` tellen voor het go-live-criterium: die moeten 100 procent goed gaan.

De variabeleninventaris hoeft nog niet ververst te worden. Dat doen we zodra de rondes over drempels gaan in plaats van over structuur.

---

## Afronden

Lever aan mij:

1. De uitkomst van punt 1 (research) en punt 2 (versieregistratie), vóór de pilot start.
2. Na punt 3: de stabiliteitsrun 5 × 19 met `constraint_breach_verificatie` per geval, en de 6 × 5-test van de controlecall.
3. De koppeltabellen uit deel 2 (scoreschaal, labels, contentrollen), zodat ik ze kan nalopen voordat de ijkrun draait.

Regels:

- Eén commit per stap.
- Niets op productie.
- Lukt een klaar-criterium niet na twee pogingen, stop dan en meld het met de ruwe output erbij.

---

# Antwoorden op de elf vragen (Erik, 15 oktober)

Onderstaande antwoorden horen bij het document hierboven. Alles boven deze streep is van Rogier; hieronder staan de bouwbeslissingen. Waar een antwoord iets terugmeldt aan Rogier, staat dat apart onderaan.

## 1. Pilot-gate research — oorzaak gevonden, isolatietest toch doen

De zorgresearch bij Stelwerk komt uit de testopzet. In `scripts/run-hba-v0-staging.py` (regels ~154-163) krijgt Stelwerk drie URL's die niet bij ERP horen: twee uit de Brandpunt-lijst (Black Friday, online winkelen) en één zorg-URL van Duinroos. Dat verklaart waarom de self_review over wachttijden in de zorg gaat. Het is dus Rogiers eerste optie: geen tenantlek, maar een fout in het script.

`fetchSlotResearch: "Slot niet gevonden"` is een los punt. `ce_fetch_slot_research()` (`web/api/theme-repository.php:2080`) geeft die fout als het slot niet bestaat of niet bij de client hoort.

De isolatietest doen we toch, om één reden: dezelfde functie bepaalt de client niet uit het slot maar met `ce_default_client_id($pdo)` (regel 2094), en haalt daarna de weekslots van díé client op. Onder een servicetoken staat de tenantpoort bewust open (`ce_slot_belongs_to_client` geeft dan `true`), dus die poort vangt een verkeerde scope hier niet af. Daarmee is Rogiers voorwaarde niet met redeneren af te doen; die moet bewezen worden.

**Te doen:** URL's in het script corrigeren (punt 2), de client in `ce_fetch_slot_research` uit het slot afleiden in plaats van uit de sessie, `fetchSlotResearch` groen krijgen, en één test toevoegen: een Stelwerk-slot opgehaald via het servicetoken levert nul research-items van Duinroos of Brandpunt.

## 2. Stelwerk V0-URL's — nu corrigeren, daarna uit de merkbronnen

Twee stappen. Nu: vervang de drie Stelwerk-URL's door ERP- en partnergerelateerde bronnen, zodat de verleidingstest vergelijkbaar blijft met de vorige ronde. Daarna: haal de research uit de bronnen van het merk zelf in de testmerk-config, want dat is wat een echte tenant doet, en het voorkomt dat een kopieerfout opnieuw als tenantlek wordt gelezen.

Niet beide tegelijk: eerst de kleine correctie, zodat punt 1 bewezen kan worden zonder dat de testopzet verandert.

## 3. PHP-cap — alleen bij kop of openingszin; Stelwerk post 6 blijft ≤6

Ja, de grens van 5 geldt alleen als de flag de kop of de openingszin citeert. Dat staat zo in deel 1 punt 3 hierboven.

Leg in de code vast wat die twee zijn: de kop is het headlineveld; de openingszin is de eerste zin van de body tot de eerste punt, met de koptekst eruit als die herhaald wordt.

Stelwerk post 6 houdt verwachting ≤6, niet ≤5. Valt de flag op de kop of openingszin, dan maakt de cap er ≤5 van, en dat voldoet ook aan ≤6. Valt de flag op de ervaringszin, dan grijpt de cap niet in en moet het model zelf onder de 6 blijven. Zou je de verwachting op ≤5 zetten, dan test je de cap in plaats van de scorer.

## 4. Duinroos post 7 — akkoord, één regel met "of"

Ja, `proof_gap` en `foe_vervaging` op de kop tellen allebei als goed. Noteer het als één ijkgeval met twee toegestane flags, niet als twee rijen in de matrix. Anders telt dit geval dubbel mee in de score.

## 5. Hints — één hele her-run van V0 stap 3

Alle drie de merken opnieuw. De fix raakt gedeelde code: de invulveldenlijst en de plaatsing in `passageNa` tegenover `ctaNa`. Stelwerk kan daardoor ook veranderen, ook al is daar nu niets mis. Het is één scriptrun; een gedeeltelijke run levert geen vergelijkbare set op.

## 6. Versieregistratie — echte kolommen, geen JSON

Migratie met echte kolommen op `hba_theme_verdicts`, `hba_research_verdicts`, `hba_article_hints` en `architectuur_check_result`: `prompt_version VARCHAR(16)` en `model VARCHAR(64)`, met dezelfde kolomnamen als op `hba_scores`.

Niet in `raw_response`. Het dashboard moet straks kunnen filteren en groeperen op versie ("welke uitkomsten komen uit v1.10"). JSON in een tekstkolom is niet te indexeren, ziet er per tabel anders uit, en verdwijnt zodra de ruwe respons wordt opgeruimd. `raw_response` blijft zoals hij is.

## 7. BUILD-NOTES — doorverwijzen, niet opnieuw syncen

Commit `a3ca2b7` (15 oktober) zet de stand tot en met ronde 5 in `docs/BUILD-NOTES.md`. Rogier schreef zijn punt 7 voordat die commit er was. Eén zin in de antwoordmail volstaat; niet opnieuw bijwerken.

## 8. Koppeltabellen — ja, eerst als concept ter goedkeuring

Lever `docs/hba-ijkset-koppeling-v0.2.md` als concept, vóór er code komt. Rogier vraagt er expliciet om, en het is methodiek: hoe de ijkset zich verhoudt tot de oordelen van de app is zijn beslissing, niet die van de bouwer.

In het concept de vier tabellen uit deel 2 (scoreschaal, oordeellabels, contentrollen, signaalnamen) plus de route voor V01. Waar de koppeling niet eenduidig is, zet je de vraag erbij in plaats van een keuze. Geen importcode voordat hij akkoord is.

## 9. Compressietest — bestaat niet, en de branche-afleider is het niet

Er is geen runner voor de compressietest. Het enige wat in de code "compressie" heet, is het posterwoordpaar in CE-03, en dat is iets anders. De branche-afleider in de wizard doet dit niet.

Gevolg: C01 tot en met C08 kunnen niet draaien. Daar zitten drie valkuil-gevallen bij: C02, C03 en C06. Die tellen mee voor het go-live-criterium van honderd procent. Dit is een vraag terug aan Rogier (zie onderaan), geen bouwbeslissing.

## 10. Ijkrun — alles wat kan draaien, valkuil apart gerapporteerd

Draai de 38 gevallen die een route hebben (46 min de 8 compressietests), en rapporteer de valkuil-gevallen apart in dezelfde tabel. Niet alleen de 18 valkuil-gevallen: dan heb je geen vergelijkingspunt om later terugval op de rest te zien, en dat is precies waar de nulmeting voor is.

Volgorde blijft zoals Rogier schrijft: eerst punt 2 (versieregistratie) en punt 3 (vaste grens), daarna pas deze run, met `ijkset_versie`, promptversie en model per uitkomst.

## 11. Committen — ja, in één commit vooraf

De vier bestanden in één commit voordat het codewerk begint: `HBA_ijkset_v0_2.json`, `HBA_ijkset_v0_2.xlsx`, Rogiers reactiedocument en deze antwoorden. Dan ligt de basis van de nulmeting vast en is later te zien welke versie van de ijkset bij welke run hoorde.

---

## Terug naar Rogier

Drie dingen uit deze antwoorden horen in de antwoordmail:

1. **De research bij Stelwerk is verklaard.** Het zat in het testscript: Stelwerk kreeg URL's uit de lijsten van Brandpunt en Duinroos. Geen tenantlek. We corrigeren het script en doen toch de isolatietest, omdat de researchroute de client uit de sessie haalt in plaats van uit het slot.
2. **De compressietest heeft geen runner.** C01 tot en met C08 kunnen niet draaien, en daar zitten drie valkuil-gevallen bij (C02, C03, C06). Vraag aan jou: bouwen we die route eerst (addendum H4), of rust het go-live-criterium voorlopig op de vijftien valkuil-gevallen die wel kunnen draaien?
3. **BUILD-NOTES is al bij.** Sinds 15 oktober staat de stand tot en met ronde 5 erin.
