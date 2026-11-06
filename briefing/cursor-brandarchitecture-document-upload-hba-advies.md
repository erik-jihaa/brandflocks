# BrandArchitecture-wizard — strategiedocument uploaden + HBA-advies tijdens invullen

> **§2 (HBA-adviessidebar) is vervallen — zie `cursor-hba-architectuurcheck-branche-afleider.md`.**
> Rogier's bouwaddendum (aug 2026) beschrijft dezelfde toets methodisch preciezer en met
> kant-en-klare systeemprompts, als architectuur-check op stap 8 in plaats van een sidebar per
> veld. Bouw §2 hieronder niet. **§1 (documentupload → suggesties per stap) blijft wél staan** —
> ander probleem, en het versterkt die check eerder dan dat het ermee botst.

**Status:** §1 gebouwd (aug 2026); §2 vervallen — architectuur-check op stap 8 is leidend.
**Raakt:** `frontend/src/components/MerkarchitectuurSection.tsx`,
`frontend/src/components/ui/Field.tsx` (`StrategySection`), `frontend/src/pages/BrandArchitecturePage.tsx`,
nieuw: `web/api/hba-wizard-document-upload.php`, `web/api/hba-v2-prompts.php`,
`web/api/hba-v2-repository.php`, `web/api/source-document-repository.php` (extractiehelpers
hergebruiken/uitbreiden).

---

## 0. Twee features, één flow

1. **Document uploaden → suggesties per stap.** Bij aanvang van de wizard een strategiedocument
   (PDF, Word of PowerPoint) uploaden; de app leest het en zet er per stap een concept-suggestie
   bij, zichtbaar als beige hint-tekst — zelfde stijl als de rest van de app.
2. **HBA-advies tijdens het invullen.** Nadat iemand een stap zelf heeft ingevuld, een
   zijbalk-paneel (zoals op de artikelpagina) dat beoordeelt of de formulering scherp genoeg is, of
   dat hij nog generiek is — "dit had elk ander merk ook kunnen zeggen."

Die twee horen bij elkaar, niet toevallig: het document geeft een startpunt, de zijbalk toetst of
de gebruiker daar iets scherps van heeft gemaakt of het gewoon heeft overgetypt. **Dat laatste is
een reëel risico** — strategiedocumenten staan vaak vol van precies het soort generieke
managementtaal ("wij zijn een strategische partner", "kwaliteit staat voorop") die de HBA-laag
elders in de app actief probeert te vermijden (zie `cursor-linkedin-scan-unieke-positionering.md`,
zelfde probleem, andere plek). Behandel de documentsuggestie daarom expliciet als **bewijsmateriaal
om op te reageren**, niet als een kant-en-klaar antwoord om te kopiëren — zie §1.4.

---

## 1. Feature A — document uploaden, suggesties per stap

### 1.1 Waar in de wizard

Vóór stap 0 (of als onderdeel van stap 0, boven de bestaande velden): een optioneel upload-blok.
*"Heb je een strategiedocument, pitch deck of positioneringsdocument? Upload 'm — we gebruiken hem
om per stap een suggestie te doen. Overslaan kan ook, dan vul je alles zelf in."* Nooit verplicht —
de wizard moet identiek blijven werken voor wie niets uploadt (bestaand gedrag = fallback).

### 1.2 Tekst-extractie — PDF bestaat al, Word en PowerPoint niet

Er is al een document-uploadpad gebouwd voor de Sources-feature
(`web/api/sources-document-upload.php` + `source-document-repository.php`), maar **alleen voor
PDF** (`ce_source_document_store_upload()` wijst alles behalve `.pdf` expliciet af). De
extractiehelpers zelf zijn generiek en herbruikbaar zonder aan de Sources-tabellen vast te zitten:
`ce_source_document_extract_pdf_text()`, `ce_source_document_normalize_extracted_text()`,
`ce_source_document_split_text()`.

**Voor Word en PowerPoint moet nieuwe extractie gebouwd worden.** Beide formaten zijn in de kern
een zip met XML erin — dat is met PHP's ingebouwde `ZipArchive` te lezen zonder een nieuwe
Composer-dependency toe te voegen:

- **DOCX:** open het zip-bestand, lees `word/document.xml`, strip de XML-tags en houd de tekst in
  `<w:t>`-elementen over.
- **PPTX:** lees elk `ppt/slides/slideN.xml`-bestand (aflopend genummerd), houd de tekst in
  `<a:t>`-elementen over, per slide een blok.

Dat is bewust de lichte route — geen `PhpOffice/PhpWord`/`PhpPresentation` erbij installeren voor
wat in de kern tekst-uit-XML-halen is. Mocht opmaakbehoud ooit nodig zijn, dan is dat een latere
uitbreiding, geen blocker nu.

Nieuw, generiek bestand (niet gekoppeld aan Sources): iets als
`web/api/strategy-document-extract.php` met drie functies,
`ce_extract_text_from_pdf()`/`_docx()`/`_pptx()`, die de bestaande PDF-helper hergebruiken/aanroepen
en er `_docx`/`_pptx`-varianten naast zetten.

### 1.3 Eén Claude-call voor alle stappen, niet acht

Stuur de geëxtraheerde tekst (getrimd/gechunkt zoals nu al gebeurt bij Sources, zelfde
`CE_SOURCE_DOCUMENT_MAX_CHUNKS`/`_CHUNK_SIZE`-aanpak) in één keer naar
`ce_hba_v2_call_json()` en vraag een suggestie per veld terug:

```json
{
  "identity_intro": "concept-tekst of null als het document dit niet beschrijft",
  "doel": "...",
  "distinctiviteit": "...",
  "wat_bewust_niet": "...",
  "regels": ["...", "..."]
}
```

Regel: **null toestaan per veld** — een strategiedocument beschrijft zelden alle vijf dingen even
expliciet. Verzin niets, net als overal elders in deze prompts (zelfde discipline als
`LI_NARRATIVE_SYSTEM_PROMPT`'s "verzin niets — parafraseer alleen wat er staat").

Één call is niet alleen goedkoper dan acht, het is ook inhoudelijk beter: de vijf velden horen
onderling consistent te zijn (je "wat bewust niet" moet passen bij je "doel"), en dat lukt beter
als het model ze in één keer, met elkaar in beeld, opstelt.

### 1.4 Waar de suggestie landt — en waarom het geen invulveld is

Gebruik het bestaande `StrategySection`-hint-patroon (`frontend/src/components/ui/Field.tsx`),
**niet** de `StrategyField`-placeholder. Verschil is belangrijk: een placeholder verdwijnt zodra
iemand begint te typen; de `StrategySection`-hint (`hint`/`hintTone="beige"`, een echte `<p
className="text-ce-beige">`) blijft zichtbaar tot het veld is ingevuld. Dat is precies wat hier
nodig is — een suggestie die er blijft staan als referentie terwijl iemand zijn eigen formulering
schrijft, niet iets dat wegpoept zodra je begint.

**Geen "gebruik deze tekst"-knop die het veld één-op-één vult.** Dat zou het copy-paste-risico uit
§0 juist faciliteren. In plaats daarvan: toon de suggestie met een duidelijk kader, bijvoorbeeld
*"Uit je document: '...'. Schrijf hieronder in je eigen woorden wat dit voor jullie merk
betekent."* De suggestie is een aanwijzing waar het document al iets bruikbaars zegt, geen
eindtekst.

### 1.5 Stap 5 (archetype/tegenstander) — bestaande flow verrijken, niet vervangen

Stap 5 heeft al een AI-derive-knop (`runDerive()` → `deriveHbaArchitecture()`) die archetype, foe
en toon voorstelt. Raak die flow niet aan — voeg de geëxtraheerde documenttekst gewoon toe als
extra context aan diezelfde call, zodat het archetype-voorstel ook rekening houdt met wat er in het
document staat. Dat is een kleine aanpassing van een bestaande, werkende functie, geen nieuwe.

---

## 2. Feature B — HBA-advies tijdens het invullen

### 2.1 Layout — de wizard heeft nu geen zijbalk

De artikelpagina gebruikt `GridShell` met `mainLayout="grid2"` (hoofdkolom +
`ArticleMetaSidebar`). De BrandArchitecture-wizard heeft dat patroon nu niet. Eerste bouwstap: de
wizard in dezelfde grid2-indeling zetten, met een nieuwe sidebar-sectie ernaast — zelfde
layoutprimitief hergebruiken, niet een nieuwe uitvinden.

### 2.2 Triggermoment — bij verlaten van een veld, niet per toetsaanslag

Mirror het patroon van `ArticleHbaHintSection`: die triggert automatisch zodra er content is (niet
op iedere toetsaanslag, en niet handmatig via een knop tenzij er een fout was — dan een "Opnieuw"
retry). Voor de wizard: trigger de check wanneer de gebruiker het veld verlaat (blur) én er tekst in
staat, met dezelfde retry-knop bij een mislukte call. Niet live-tijdens-het-typen — dat is nerveus
en kost onnodig calls.

### 2.3 Backend — nieuw promptblok, hergebruikt de branche-clichébibliotheek

Nieuwe functie in `hba-v2-repository.php`, bijvoorbeeld `ce_hba_v2_wizard_field_hint()`, op
`ce_hba_v2_call_json()` gebouwd zoals de rest. Geef mee: het stap-veld en zijn tekst, de eerder
ingevulde stappen (voor onderlinge consistentie — zie §1.3), en — **hergebruik hier dezelfde
`branche_library.categorietaal`/`hygienefactoren` die ook de LinkedIn-scanner gebruikt** (zie
`cursor-linkedin-scan-unieke-positionering.md` §4). Zelfde databron, twee plekken in de app die
allebei "is dit generiek of eigen" toetsen — dat is precies waarom hij als losse bibliotheektabel
bestaat in plaats van hardcoded in één feature.

Promptvraag, kort samengevat: *is dit een specifieke, voor dit merk unieke formulering, of is het
een cliché/basisverwachting uit de branche — en zo ja, welke, en hoe zou een scherpere versie
klinken?*

### 2.4 Response-vorm — mirror `HbaArticleHint`

Zelfde schema-stijl als de artikel-hints, zodat frontend-logica hergebruikt kan worden:

```json
{
  "aanwijzing": "korte, concrete beoordeling",
  "formuleringVoor": "het huidige antwoord",
  "formuleringNa": "een scherpere versie, optioneel — alleen als er echt een concrete verbetering is",
  "toegepast": false
}
```

Met een "Pas toe"-actie die `formuleringNa` in het veld zet, zelfde interactiepatroon als
`ArticleHbaHintSection`'s "Pas toe" — maar ook hier geldt: de gebruiker moet zelf op "Pas toe"
klikken, niets wordt automatisch overschreven.

---

## 3. Kosten — er is geen bestaande grens in de CE-backend

Anders dan de LinkedIn-scan-tool (`narrative_max_cost_usd_per_scan`) heeft `ce_hba_v2_call_json()`
in de hoofd-CE-app **geen enkele cost- of rate-limiter** — alleen een feature-flag-check
(`hba_enabled`). Dat was tot nu toe te overzien omdat HBA-calls gekoppeld zijn aan contentproductie
(één call per artikel, beperkt volume). Deze twee nieuwe features voegen calls toe aan een moment
dat een tenant zelf kan herhalen (opnieuw uploaden, elk veld opnieuw laten checken) — dus een
lichte grens is hier verstandiger dan nergens. Voorstel: een eenvoudige per-tenant-per-dag-teller
op de wizard-specifieke endpoints (niet de bestaande article-hint-flow aanraken), analoog aan
`daily_scan_limit` in de scanner-tool. Geen ingewikkeld budgetsysteem nodig — alleen een plafond
tegen onbedoeld herhaald klikken.

## 4. Opslag van de documentsuggesties

Geen nieuwe DB-kolom nodig als het niet per se moet: laat de suggesties client-side in de
wizard-draft-state leven (dezelfde autosave die de wizard al gebruikt voor `draft.*`-velden), zodat
ze verdwijnen zodra de wizard is afgerond — dit is eenmalige onboarding-hulp, geen blijvend
merkgegeven. Blijkt de bestaande draft-autosave geen ruimte te hebben voor extra vrije velden, dan
is een nullable JSON-kolom op `brand_narratives` (bijv. `wizard_doc_suggestions`) de fallback — pas
dan toevoegen, niet vooraf.

Het geüploade document zelf: sla het bestand net als bij Sources op onder
`web/uploads/hba-wizard-docs/{clientId}/{timestamp}.{ext}`, maar **niet** in de
`sources`/`source_document_chunks`-tabellen — dit is geen doorlopende contentbron, alleen invoer
voor de wizard.

---

## 5. Bouwvolgorde

1. **DOCX/PPTX-extractie** (§1.2) — technisch fundament, op zichzelf te testen los van de wizard-UI.
2. **Eén-call-suggestieprompt + upload-UI in stap 0** (§1.1, §1.3, §1.4) — Feature A compleet,
   te toetsen zonder Feature B.
3. **Grid2-layout + sidebar-skelet** (§2.1) — structuur, nog zonder AI-inhoud.
4. **Wizard-hint-prompt + "Pas toe"** (§2.2-§2.4) — Feature B compleet.
5. **Kostengrens** (§3) — vóór livegang, niet erna.

## 6. Testen

1. Upload een PDF-strategiedocument met een duidelijke "wij vechten tegen X"-passage → stap 5's
   archetype-voorstel refereert daaraan.
2. Upload een Word-document → tekst wordt geëxtraheerd, minstens één stap krijgt een suggestie.
3. Upload een PowerPoint met tekst verspreid over meerdere slides → alle slides worden meegenomen,
   niet alleen de eerste.
4. Document dat over een veld niets zegt (bijv. geen regels/dealbreakers genoemd) → dat veld krijgt
   `null`, geen verzonnen suggestie.
5. Wizard zonder upload → identiek gedrag aan vandaag, geen suggesties, geen fouten.
6. Vul een stap in met evident generieke taal ("wij bieden kwaliteit") in een branche waar dat op
   de clichélijst staat → zijbalk signaleert dat expliciet, met een scherpere `formuleringNa`.
7. Vul een stap in met een specifieke, onderscheidende formulering → zijbalk bevestigt dat kort,
   geen overbodige kritiek op iets dat al goed is.

## 7. Acceptatiecriteria

- PDF, DOCX en PPTX worden alle drie geaccepteerd en geven bruikbare geëxtraheerde tekst.
- Eén Claude-call per document, niet één per stap.
- Suggesties verschijnen als persistente beige hint (`StrategySection`-patroon), niet als
  wegpoependeplaceholder, en nooit als automatisch ingevulde veldwaarde.
- Sidebar-advies triggert op veld-verlaten, niet per toetsaanslag; retry bij fout, net als de
  artikelpagina.
- Sidebar-prompt gebruikt dezelfde `branche_library`-clichés als de LinkedIn-scanner — geen tweede,
  losse clichélijst.
- Wizard zonder document-upload en zonder sidebar-interactie werkt exact zoals vóór deze wijziging.
- Een per-tenant-per-dag-limiet bestaat op de nieuwe endpoints.

---

## 8. Antwoorden op Cursor's vragen (aug 2026)

Cursor heeft de briefing tegen de codebase afgezet en negen vragen gesteld voordat er verder
gebouwd wordt. Antwoorden hieronder, genummerd zoals gevraagd.

**8.1 — §1.4 Suggestie-UX: alignen met de briefing.** Ja. Persistente beige hint
(`StrategySection`-patroon), geen klik-om-te-kopiëren, geen auto-invul. De huidige
"klik/focus vult automatisch in"-implementatie wijkt af van §1.4 en van het hele copy-paste-
risico dat in §0 wordt beschreven — dat risico is niet hypothetisch, dus dit moet gefixt worden
vóór livegang, niet erna.

**8.2 — §2 definitief vervallen: bevestigd.** Grid2 + blur-sidebar-per-veld wordt niet meer
gebouwd. Acceptatietest 6 en 7 uit §6 hierboven lopen voortaan via de architectuur-check op
stap 8 (`cursor-hba-architectuurcheck-branche-afleider.md`), niet via een sidebar tijdens het
invullen. Dat document is leidend voor hoe die toets er precies uitziet (Rogier's methodiek).

**8.3 — Kostengrens: ja, vóór productie.** Zelfde risico als eerder benoemd in §3 hierboven —
geen enkele bestaande grens op `ce_hba_v2_call_json()`. Voorstel voor een redelijk plafond,
analoog aan de scanner-tool se `daily_scan_limit`: **5 document-uploads + 20 wizard-veld-checks
per tenant per dag.** Ruim genoeg voor normaal gebruik (één document, acht velden, een paar
retries), krap genoeg om onbedoeld herhaald klikken of misbruik af te vangen. Geen budget-in-
euro's nodig zoals bij de scanner — dit zijn kortere, goedkopere calls dan een volledige
narrative-analyse.

**8.4 — Her-upload: ja, vervangen toestaan.** De briefing noemt "opnieuw uploaden" expliciet als
scenario (§0, "niet toevallig"), dus de huidige blokkade ("Er is al een strategiedocument
geüpload") klopt niet met de bedoeling. Bij een nieuwe upload: oud bestand + oude
documentsuggesties (§4) vervangen, nieuwe extractie + nieuwe Claude-call. Geen bevestigingsdialoog
nodig — het is niet-destructief voor al ingevulde wizard-velden, alleen de suggesties zelf
ververen.

**8.5 — Upload later nog bereikbaar: ja.** Volgt direct uit 8.4 — als vervangen mag, moet de
upload ook na de eerste keer bereikbaar blijven. Voeg op stap 0 een link/knop toe ("Ander
document uploaden") die het huidige geüploade bestand (indien aanwezig) toont en vervangen
mogelijk maakt, in plaats van het blok volledig te laten verdwijnen na de eerste upload.

**8.6 — Server-side `meta.json`: acceptabel, mag zo blijven.** De briefing had een voorkeur voor
client-side, maar het argument daarvoor (geen blijvend merkgegeven) weegt hier niet op tegen het
praktische voordeel dat suggesties een page-refresh overleven — een wizard van acht stappen
wordt niet altijd in één sessie afgerond. Geen wijziging nodig; dit is een verbetering t.o.v. de
oorspronkelijke aanname, geen afwijking om te herstellen.

**8.7 — Extractiescope: terugschroeven naar PDF/DOCX/PPTX.** Alleen moderne Office-formaten,
zoals de briefing zegt. Legacy `.doc`/`.ppt` via shell-tools niet toevoegen: dat is een extra
system-dependency (binaire parsers buiten PHP) voor een randgeval dat de briefing niet vraagt, en
het past niet bij de bewust lichte `ZipArchive`-aanpak uit §1.2 — die is expliciet gekozen om
géén nieuwe dependencies toe te voegen. Als iemand alleen een oud `.doc`-bestand heeft: converteer
zelf naar PDF/DOCX vóór upload, dat is geen onredelijke vraag voor een eenmalige onboardingstap.

**8.8 — Volgorde: A) eerst §1.4 UX-fix + kostengrens, dan deploy.** Niet B: deployen zonder
kostengrens zet een onbegrensde Claude-call-flow live op een endpoint dat een tenant zelf
makkelijk kan herhalen — precies het risico dat §3 beschrijft. Niet C: "alles committen, dan
gezamenlijk testen" is prima als testvolgorde binnen A, maar de UX-fix en kostengrens horen bij
de dingen die getest worden, niet bij dingen die je erna nog toevoegt. Dus: 1) §1.4 UX-fix,
2) kostengrens (8.3), 3) deploy, 4) gezamenlijk testen met de Doornvogel-case.

**8.9 — Doornvogel-acceptatietest: hoofdtest voor stap 8, niet per veld.** Nu §2 vervallen is
(8.2), is er geen per-veld-toets meer die dit tijdens het invullen kan signaleren — dat is een
bewuste keuze, geen gat. De architectuur-check op stap 8 is de plek waar generieke taal
("samen bouwen aan lange relaties") gevangen moet worden, vóór de gebruiker de wizard afrondt.
Zie `cursor-hba-architectuurcheck-branche-afleider.md` §5 voor de exacte acceptatietest.
