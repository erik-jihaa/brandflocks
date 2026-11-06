# HBA-bouwaddendum inbouwen — architectuur-check + gefaseerde branche-afleider

**Bron:** `HBA_bouwaddendum_check_en_afleider.docx` en `HBA_Lees_dit_eerst_addendum.docx`
(Rogier Heijning / Hero Brands, aug 2026). Zet beide in `briefing/hba-input/` naast de bestaande
bouwkit en branche-bibliotheek.
**Status:** voorstel. Twee onafhankelijke bouwblokken, één acceptatietest.
**Raakt:** `web/api/hba-branche-seed.php`, `web/api/hba-v2-repository.php`,
`web/api/hba-v2-prompts.php`, `web/api/hba-v2.php`,
`frontend/src/components/MerkarchitectuurSection.tsx`, `frontend/src/api/hbaV2.ts`,
nieuw: tabel `architectuur_check_result`, kolommen op `branche_library`.

---

## 0. Wat het addendum toevoegt

Twee dingen, in deze volgorde:

1. **Gefaseerde branche-afleider.** Nu (in Rogier's model) gokt het systeem direct naar
   hygiënefactoren. De nieuwe versie definieert eerst de branche — essentie, kernaanname,
   vakliteratuur, klassieke debatten — en leidt daar pas de vier bibliotheekvelden uit af.
2. **Architectuur-check op stap 8.** Direct na de wizard, vóór "vastleggen en beginnen":
   beoordeelt of de meetlat scherp genoeg is om content tegen te meten.

De onderliggende redenering is scherp en raakt precies onze eigen praktijk: *als de klant een
zwakke merkarchitectuur invult, meet de tool daarna keurig tegen een zwakke meetlat — en dan is de
hele belofte van de app leeg.* Rogier's eigen testcase is bovendien letterlijk onze casus: een
loyaliteitsbureau dat "samen bouwen aan lange relaties" als unieke positionering krijgt
toegekend, terwijl dat de definitie van de branche is. **Dat is Doornvogel** — zie
`cursor-linkedin-scan-unieke-positionering.md`.

---

## 1. Reality-check: drie plekken waar het addendum afwijkt van onze codebase

Het addendum is geschreven tegen de bouwkit, niet tegen onze feitelijke implementatie. Drie
verschillen die de bouwvolgorde beïnvloeden — **lees dit vóór je aan hoofdstuk 4 begint.**

### 1.1 Er is geen bestaande branche-afleider om te "vervangen"

Het addendum (H4, en stap 2 van het leesmij) zegt: *"vervang de bestaande branche-afleider door de
gefaseerde versie."* Die bestaat bij ons niet. Onze werkelijkheid:

- `branche_library` wordt gevuld door een **vaste seed van 6 rijen**
  (`ce_hba_branche_seed_rows()` in `hba-branche-seed.php`), allemaal met `bron = 'gecureerd'`.
- De wizard toont een **gesloten Pulldown** over die 6 (`GET /hba/branches`). Een klant kán geen
  onbekende branche invoeren, dus er is nooit iets af te leiden.

**Gevolg:** dit is nieuwbouw, niet vervanging — en er hoort een UI-wijziging bij die het addendum
niet noemt: de branche-keuze in wizard-stap 1 moet een **vrij tekstveld met suggesties** worden
(bestaande 6 als opties, maar eigen invoer toegestaan). Zonder die wijziging kan de afleider nooit
triggeren en is de hele gefaseerde prompt dode code.

Dit lost meteen een openstaand punt op: bij de LinkedIn-scanner liepen we vast op Codam College
(onderwijs staat niet in de 6) en kozen we noodgedwongen een "Overig / geen goede match"-optie —
zie `cursor-linkedin-scan-unieke-positionering.md` §2. Met een werkende afleider is die
noodoplossing niet meer nodig: onbekende branche → afleiden → bibliotheek groeit vanzelf.

### 1.2 De kolommen die het addendum aanneemt bestaan grotendeels nog niet

`branche_library` heeft nu: `branche_code`, `branche_label`, `markt_archetype`,
`hygienefactoren`, `categorietaal`, `bron`. Goed nieuws: **`bron` bestaat al** (nu hardcoded
`'gecureerd'`), dus het afgeleid/gecureerd-onderscheid uit H4.3 past zonder migratie op dat punt.

Toe te voegen (het leesmij noemt er twee, de JSON-output van de prompt vraagt er meer — volg de
prompt, niet de samenvatting):

| Kolom | Uit prompt-output | Type |
| --- | --- | --- |
| `branche_definitie` | `branche_definitie` | TEXT |
| `bronnen_kwaliteit` | `bronnen_kwaliteit` | ENUM/VARCHAR: mager, voldoende, rijk |
| `vakcontext` | `vakcontext` (3 sublijsten) | JSON |
| `differentiatie_assen` | `differentiatie_assen` | JSON |
| `waar_onderscheid_zit` | `waar_onderscheid_zit` | TEXT |

Volg het bestaande idempotente auto-schema-patroon (zoals `ce_ensure_*_columns` elders), niet een
losse migratie — dat is hoe de rest van dit datamodel groeit.

### 1.3 De architectuur-check landt op een scherm dat al bestaat

Goed nieuws hier: stap 8 (`STEPS[7]`, "Bevestigen") bestaat al in
`MerkarchitectuurSection.tsx`, met `saveArchitecture()` → PATCH `/strategy` + POST
`/hba/architecture/complete`. De check hangt dus vóór die bestaande knop, niet in een nieuw scherm.

---

## 2. Overlap met de document-upload-briefing — niet allebei bouwen

`cursor-brandarchitecture-document-upload-hba-advies.md` (gisteren geschreven) beschrijft een
zijbalk die tijdens het invullen per veld beoordeelt of een formulering scherp of generiek is. Dat
is **hetzelfde idee als Rogier's architectuur-check**, op een ander moment en losser gespecificeerd.

Aanbeveling: **Rogier's versie wint.** Zijn check is methodisch preciezer (vier benoemde dimensies,
strawman-detectie, expliciete hygiënefactoren-toets, vastgelegd totaaloordeel), hij komt van de
licentiehouder van de methodiek, en hij is kant-en-klaar geprompt. Concreet:

- **Vervalt** uit die andere briefing: §2 (de per-veld HBA-adviessidebar). Bouw dat niet apart.
- **Blijft staan**: §1, het uploaden van een strategiedocument om per stap suggesties te genereren.
  Dat is een ander probleem (invullen makkelijker maken) en botst niet — sterker nog, het versterkt
  deze check: sneller ingevuld, en de check erna vangt af of het document alleen maar generieke
  managementtaal heeft ingebracht.
- Werk die briefing bij met een verwijzing hiernaar zodra dit besluit vaststaat.

---

## 3. Bouwblok A — gefaseerde branche-afleider (addendum H4)

Volgorde: **eerst dit, dan de check.** De check gebruikt `branche_definitie` en
`hygienefactoren` als meetlat voor de uniciteitsdimensie; bouw je de check eerst, dan mist hij zijn
belangrijkste input en test je hem in feite blind.

- Systeemprompt: neem de driefasenprompt uit H4.2 **letterlijk over**. Niet herschrijven, niet
  inkorten — daar zit de methodische kern in (zie ook §6, licentie).
- Nieuwe functie naast de bestaande derive-functies in `hba-v2-repository.php`, op
  `ce_hba_v2_call_json()` gebouwd zoals de rest.
- Trigger: klant vult in wizard-stap 1 een branche in die nog niet in `branche_library` staat.
- Schrijf weg met `bron = 'afgeleid'`.
- UI bij `bron = 'afgeleid'`: klein, rustig teken — *"deze branche is automatisch afgeleid; een
  strateeg kan de definitie nog aanscherpen."* Bij `gecureerd` geen teken. (H4.3.)
- Optioneel uit H4.3, de moeite waard: vraag de klant bij een afgeleide branche om de
  `branche_definitie` te bevestigen of aan te vullen. Derde verbeterbron zonder Rogier's tijd.

**Let op — dit raakt ook de LinkedIn-scanner.** Die gebruikt dezelfde `branche_library`
(als statische kopie, zie `cursor-linkedin-scan-unieke-positionering.md` §2). Groeit de bibliotheek
nu automatisch, dan loopt die kopie sneller achter dan we destijds aannamen ("6 rijen die zelden
veranderen"). Bij oplevering van dit bouwblok: heroverweeg of de scanner alsnog een live-call of
een sync-knop moet krijgen. Niet nu bouwen, wel opnieuw wegen.

---

## 4. Bouwblok B — architectuur-check (addendum H3)

**Twee aparte calls, bewust verschillend van aard** (H3.1):

| Velden | Behandeling |
| --- | --- |
| Hard: Story Triangle (archetype, doel, tegenkracht) + regels | **Beoordeling** op vier dimensies, met concrete verbeteradviezen |
| Zacht: tone of behaviour + voorbeelden | **Suggesties**, geen oordeel, geen score |

Beide systeemprompts staan kant-en-klaar in H3.3 en H3.4 — letterlijk overnemen.

De vier dimensies: kracht van de triangle, relevantie tegenkracht t.o.v. doel (mét
strawman-detectie), relevantie rol, en scherpte + uniciteit (mét hygiënefactoren-toets).
Totaaloordeel volgt mechanisch uit het aantal "aanscherpen"-dimensies: 0 → `meetlat_klaar`,
1 → `meetlat_bruikbaar_met_waarschuwing`, 2+ → `meetlat_te_zwak`.

**Opslag:** nieuwe tabel `architectuur_check_result`, met kolommen voor de vier dimensies
(oordeel + toelichting + verbetering elk), `strawman_gedetecteerd`, `zit_in_hygienefactoren`,
`regels_oordeel`, `totaaloordeel`, `premium_laag_aangeraden`. Leid het exacte datamodel af uit de
JSON-output in H3.3. Versiebeheer: bewaar per run een rij (niet overschrijven) — dan zie je later
of aanscherpen daadwerkelijk heeft geholpen.

**Klantscherm** (H3.5): per dimensie een label (sterk / voldoende / aanscherpen) met toelichting in
gewone taal; bij "aanscherpen" de concrete verbetering direct eronder met een knop **aanscherpen**
die het bijbehorende wizard-veld heropent. Zachte suggesties onderaan, elk met **overnemen**.

**Drie dingen die expliciet níét mogen** (leesmij H4, addendum H3.5):

- **Geen harde blokkade.** "Vastleggen en beginnen" blijft altijd beschikbaar. Bij
  `meetlat_te_zwak` of een laag uniciteitsoordeel komt er alleen een rustige regel boven te staan:
  *"deze positionering is nog vrij algemeen. De tool kan wel meten, maar de metingen worden minder
  betekenisvol. Wil je een HBA-strateeg laten meekijken?"*
- **Geen vaktermen naar de gebruiker.** Staat letterlijk in de systeemprompt; controleer het ook in
  de UI-labels (dus niet "strawman gedetecteerd" tonen — dat is een intern veld).
- **Wizard-stappen 1 t/m 7 en de drie ingrijppunten blijven ongewijzigd.** Dit bouwt erop, verandert
  er niets aan.

Het premium-bruggetje sluit aan op wat al in `cursor-hba-v2-meedenker.md` §7.5 is afgesproken —
volg die bestaande spec voor de concrete uitwerking (mailto/Calendly), verzin geen tweede variant.

---

## 5. Acceptatietest — dit is de test, niet een leuk-om-te-doen

Uit het leesmij, stap 4. Zonder deze test weet je niet of de keten werkt.

```
Test-invoer:
  branche:         "loyaliteitsmarketing"   (bewust niet in de startset van 6)
  archetype:       Verzorger
  doel:            "klanten aan ons binden voor de lange termijn"
  tegenkracht:     "snelle verkoop en oppervlakkige relaties"
  distinctiviteit: "wij bouwen aan echte relaties"

Verwacht:
  scherpte_en_uniciteit.oordeel : "aanscherpen"
  strawman_gedetecteerd         : true    (niemand claimt oppervlakkige relaties)
  zit_in_hygienefactoren        : true    (dit ís de definitie van de branche)
  premium_laag_aangeraden       : true
```

Komt er "sterk" of "voldoende" uit op uniciteit, dan is er iets mis. **Kijk dan eerst naar
`bronnen_kwaliteit` van de afgeleide branche** — staat die op "mager", dan moet die branche eerst
door Rogier gecureerd worden voordat de check betrouwbaar is. Dat is geen bug in de check maar een
tekort aan branche-input, en het onderscheid is belangrijk: sleutel niet aan de prompt als het
probleem in de bibliotheek zit.

Daarna, vóór livegang: **de uitkomst aan Rogier laten zien** (leesmij stap 5).

---

## 6. Licentie en promptdiscipline

Het addendum draagt een expliciete copyrightvermelding: de HBA en de daarin beschreven
systeemprompts en beoordelingslogica zijn IE van Rogier Heijning; implementatie onder schriftelijke
licentieafspraak met Hero Brands. Twee praktische consequenties:

- **Prompts letterlijk overnemen, niet "verbeteren".** Het leesmij is er expliciet over: loopt een
  test anders dan beschreven, vraag Rogier vóór je zelf aan de systeemprompts sleutelt — daar zit de
  methodische kern in en die willen we niet per ongeluk verzachten.
- Bewaar de prompts als herkenbaar afgebakende constanten (zoals de bestaande
  `hba-v2-prompts.php`-structuur), niet verweven door applicatielogica — dan blijft zichtbaar wat
  gelicentieerd materiaal is.

---

## 7. Bouwvolgorde

1. **Implementatieplan eerst, geen code** (leesmij stap 1) — met de drie brondocumenten samen:
   dit addendum, de bouwkit en de branche-bibliotheek. Losse aanlevering laat de AI gokken.
2. **Datamodel**: kolommen op `branche_library` (§1.2) + tabel `architectuur_check_result` (§4).
3. **Bouwblok A** — gefaseerde afleider (§3), **inclusief de UI-wijziging naar vrije branche-invoer**
   (§1.1), anders kan hij niet triggeren.
4. **Bouwblok B** — architectuur-check op stap 8 (§4), beide calls.
5. **Acceptatietest loyaliteitsbranche** (§5).
6. **Rogier laten meekijken**, dan pas live.
7. Heroverweeg daarna de branche-sync van de LinkedIn-scanner (§3, slot).

## 8. Acceptatiecriteria

- Een onbekende branche invoeren in wizard-stap 1 triggert de gefaseerde afleider en levert een rij
  met `bron = 'afgeleid'`, gevulde `branche_definitie` en `bronnen_kwaliteit`.
- Afgeleide branches tonen het rustige "automatisch afgeleid"-teken; gecureerde niet.
- Stap 8 toont vier dimensie-oordelen in gewone taal, zonder vakjargon, met werkende
  "aanscherpen"-knoppen naar het juiste wizard-veld.
- Zachte velden krijgen suggesties met "overnemen", nooit een oordeel of score.
- "Vastleggen en beginnen" is altijd klikbaar, ook bij `meetlat_te_zwak`.
- De loyaliteits-testcase (§5) levert exact de vier verwachte uitkomsten.
- Wizard-stappen 1 t/m 7 en de drie ingrijppunten zijn functioneel ongewijzigd.
- Elke check-run wordt als aparte rij bewaard, niet overschreven.
