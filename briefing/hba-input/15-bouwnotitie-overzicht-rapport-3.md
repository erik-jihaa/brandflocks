# ContentFlow — bouwnotitie Overzicht en Rapport

**Voor:** Erik / zijn AI
**Datum:** september 2026
**Scope:** de twee schermen Overzicht (landingspagina) en Rapport. Herindeling, geen nieuw
datamodel: alle cijfers bestaan al, ze staan alleen op de verkeerde plek of in de verkeerde vorm.
**Aard:** concrete bouwnotitie. Verifieer component- en veldnamen tegen de codebase; kom terug bij
een conflict in plaats van een variant naast een bestaande te maken.

---

## 0. De regel die beide schermen ordent

Eén principe bepaalt waar elk element hoort. Pas dit toe op alles wat nu op het overzicht staat:

> Op het **Overzicht** staat alleen wat je vertelt **hoe je ervoor staat** of **wat er moet
> gebeuren**. Een cijfer dat je alleen kunt begrijpen door het uit te zoeken, hoort in het
> **Rapport**. Een koppeling die je één keer regelt, hoort in **Instellingen** — en verdwijnt van
> het overzicht zodra ze gelegd is.

Concreet, toegepast op het huidige scherm:

| Element nu op Overzicht | Waar het heen gaat |
|---|---|
| Vier positioneringsgrafieken (radar's, staaf, lijn) | Rapport |
| GA4-weergaven, LinkedIn per week / 90 dagen, vindbaarheidsscore + GEO | Rapport (uitgesplitst) |
| Totale engagement als grafiek | Overzicht als één getal; grafiek naar Rapport |
| Bronnenlijst | Instellingen / Bronnen |
| Trello, mailbox, podcast koppelen | Instellingen; op Overzicht alleen als nog niet gelegd |

Het overzicht wordt daarmee een auto-dashboard: bij het starten zie je je meters en één
waarschuwingslampje, niet de motorhistorie.

---

## 1. Overzicht (landingspagina)

Dit is het eerste scherm na inloggen. De gebruiker logt soms pas na weken weer in en moet in enkele
seconden grip hebben: hoe sta ik ervoor, wat is er veranderd, wat moet ik doen.

Opbouw van boven naar beneden:

### 1.1 Terugkeerregel — "Sinds je laatste bezoek"

- Verschijnt **alleen als de gebruiker langer dan 7 dagen niet is ingelogd**. Anders weglaten.
- Eén of twee zinnen in gewone taal: wat is er gepubliceerd, hoeveel engagement leverde dat op, en
  wat het merkcijfer deed. Sluit af met wat er nog op de gebruiker wacht.
- **Bevat een link naar het Rapport** ("Bekijk het volledige rapport"), zodat wie meer wil weten
  meteen doorklikt.
- Voorbeeld: "Er zijn 4 artikelen gepubliceerd, samen goed voor 38 reacties. Je merkcijfer liep
  licht op, maar positionering kwam 3 weken niet aan bod, en er wachten 2 dingen op jou."

### 1.2 Signaalblok — alleen als er iets aan de hand is

- De zwaarste actieve portfoliosignaal (uit briefing 10, §8: rol staat stil, scheve verdeling,
  dalende lijn, gedragsregel verdwenen). Toon er **maximaal één**, de zwaarste.
- Kleur: **geel (`--bg-warning`)**, niet rood. Rood is voorbehouden aan wat kapot is (mislukte
  publicatie). Een wegzakkende rol is een uitnodiging, geen alarm.
- Eén regel probleem, één regel wat te doen.
- Is er geen signaal, dan **valt het blok weg**. Geen probleem is ook informatie; het scherm mag
  dan rustig zijn.

### 1.3 Statusmeters — "Hoe je merk ervoor staat"

Vijf kleine kaarten, elk een afleesbaar woord of getal met richting. **Geen grafieken** — die lezen
niet in één blik.

| Meter | Toont | Voorbeeld |
|---|---|---|
| Merkarchitectuur | staat / incompleet | "Staat", toets actief |
| Merkcijfer | Brand Contribution + richting | "6,4" ↑ licht op |
| Balans | goed / scheef | "Scheef", positionering laag |
| Engagement | totaal recente periode + richting | "114" (8 wk) ↑ stijgend |
| Ritme | posts per week t.o.v. doel | "2 /wk", op schema |

Onder de rij één regel: "Uitsplitsing per kanaal en verloop over tijd staan in het rapport."

**Let op het merkcijfer:** zolang de determinismemeting (briefing 10, §10) niet is gedraaid, geen
decimaal tonen. Dan "voldoende / zwak / sterk" of een balkje in plaats van "6,4". Zie briefing 11.

### 1.4 Engagement hoort erbij, niet eronder

Engagement is voor contentmakers belangrijk en mag niet verdwijnen. Het staat als **vijfde meter**,
gelijkwaardig aan het merkcijfer, niet als aparte grafiek en niet bovenaan. Zo is het zichtbaar
zonder het merkcijfer te verdringen. De grafiek (verloop over 8 weken) en de uitsplitsing per kanaal
gaan naar het Rapport.

### 1.5 "Wat op je wacht"

- De openstaande acties, met de **blauwe stip alleen voor wat de pijplijn blokkeert** (thema
  goedkeuren, research liken, concept akkoord). Research-items en ander uitstelbaar werk krijgen een
  **grijze stip** en "kan later". Zo blijft blauw betekenen: dit wacht echt op jou.
- Gesorteerd op urgentie: blokkerend boven, uitstelbaar onder.

### 1.6 Koppelingen — alleen wat nog niet gelegd is

- Eén rustige balk onderaan: "Nog niet gekoppeld: podcast, mailbox, Trello" met "Instellen".
- **Verdwijnt zodra alles gekoppeld is.** Een gelegde koppeling hoort nooit meer op het overzicht.

### 1.7 Lege staat (nieuwe klant, < ~10 gescoorde posts)

Bij een nieuwe tenant is er nog geen merkcijfer, balans of verloop. Toon dan **niet** de meters met
nullen — een lege balans of een 0-cijfer leest als een slechte score. In plaats daarvan:

- Wat er wél al staat: "Je merkarchitectuur staat", aantal beoordeelde thema's, "de nulmeting van je
  eerdere LinkedIn-posts loopt".
- De voortgang naar de eerste meting: "9 van 20 posts" met de bolletjesreeks.
- De meters Merkcijfer en Balans verschijnen pas zodra ze iets te zeggen hebben. Engagement en Ritme
  mogen wel meteen staan.
- **Koptekst niet "Je merk wordt opgebouwd"** — dat is een onbewezen belofte. Gebruik "Zo staat je
  merk ervoor" of "Je merk in opbouw".

---

## 2. Rapport

Het Rapport beantwoordt de vraag die het Overzicht oproept: **waarom** sta ik er zo voor, en **wat
kan ik eraan doen**. Hier landen de vier grafieken die van het Overzicht af zijn gehaald — maar niet
als kaal dashboard. Bij elke grafiek hoort de bevinding in tekst, want de doelgroep leest een radar
niet vanzelf.

### 2.1 Samenvatting bovenaan

Eén blok, twee tot drie zinnen, dat de diagnose stelt voordat er een grafiek in beeld komt.
Voorbeeld: "Je merk werd dit kwartaal licht sterker, maar leunt zwaar op bewijs en service.
Positionering blijft achter, en je tegenkracht komt in de teksten minder scherp terug dan in je
architectuur."

### 2.2 De grafieken, elk met diagnose

Per grafiek: de visual **klein** aan één kant, de bevinding in gewone taal ernaast, en een doorklik
naar waar je het oplost. De tekst is het belangrijkste, de grafiek is het bewijs erbij.

| Grafiek | Bevinding (voorbeeld) | Doorklik |
|---|---|---|
| Story Triangle (radar) | "Je mechanisme komt het sterkst terug, je Foe het zwakst (4,1). Je zegt goed hóe je anders bent, minder waartegen je je verzet." | naar de architectuur |
| Contentrol-verdeling (staaf) | "Positionering staat op 16%, ruim onder je doel van 30. Bewijs en service nemen samen 60% in." | plan een positioneringsstuk |
| Merkcijfer over tijd (lijn) | "Licht stijgend, van 5,8 naar 6,4. Vlakt af sinds positionering wegzakte." | — |
| Portfolio Balance (radar) | zie contentrol-verdeling; toon beide alleen als ze iets verschillends zeggen, anders één | — |

Let op: Story Triangle en Portfolio Balance overlappen deels. Toon ze niet allebei als ze hetzelfde
vertellen; kies de scherpste voor deze klant.

### 2.3 Engagement, uitgesplitst

Hier hoort het detail dat van het Overzicht af is: LinkedIn per 90 dagen (reacties, likes,
opmerkingen), website-weergaven via GA4 met richting, en het verloop over tijd.

### 2.4 De regel die alles bij elkaar brengt

Onderaan, expliciet: **de vergelijking tussen op-strategie en generiek**. "Je posts op-strategie
kregen gemiddeld méér engagement dan je generieke posts." Dit is de plek waar merkcijfer en
engagement samenkomen, en het is het sterkste bewijs dat merkvast werken geen bereik kost.

- Label dit expliciet als **jouw content op-strategie versus generiek**, niet als een branche- of
  industryvergelijking. Die vergelijking bestaat niet in het product (zie bouwnotitie Home).
- Toon deze regel alleen als er genoeg posts in beide categorieën zijn; anders weglaten, niet raden.

### 2.5 Lege staat

Net als het Overzicht: bij te weinig gescoorde posts geen grafieken met ruis en geen diagnose die
nergens op leunt. Toon dat het rapport er nog niet is omdat er nog niet genoeg gemeten is, met de
voortgang naar de eerste volledige meting.

---

## 3. De knip tussen de twee schermen

| | Overzicht | Rapport |
|---|---|---|
| Vraag | hoe sta ik ervoor, wat moet ik doen | waarom, en wat kan ik eraan doen |
| Vorm | meters (woord/getal), één signaal, actielijst | grafieken mét diagnose in tekst |
| Grafieken | geen | de vier positioneringsgrafieken + verloop |
| Engagement | één getal (5e meter) | uitgesplitst per kanaal + op-strategie-vergelijking |
| Diepte | drie seconden grip | uitleg, kwartaalverloop, doorkliks |

Elk cijfer staat op precies één van de twee, nooit op allebei. Het overzicht wijst naar het rapport
(§1.1), het rapport wijst terug naar de plek waar je iets oplost (§2.2).

---

## 3b. Waar de interne links heen gaan

Elke doorklik in beide schermen, met de bestemming en of die al bestaat. Verifieer de routenamen
tegen `App.tsx` / `SidebarNav.tsx` voordat je ze gebruikt — de labels hieronder zijn de bedoeling,
niet per se de exacte padnaam.

### Overzicht

| Link / knop | Bestemming | Status |
|---|---|---|
| "Bekijk het volledige rapport" (§1.1) | Report | bestaat |
| Signaalblok, actie-link (§1.2) | Agenda, gefilterd op de betreffende rol | bestaat |
| Meter "Balans" → detail | Report, sectie Contentrol-verdeling | bestaat (na §2) |
| "Uitsplitsing … in het rapport" (§1.3) | Report, sectie Engagement | bestaat (na §2) |
| "Concept wacht op akkoord" (§1.5) | Article, specifiek artikel via deep link | bestaat |
| "5 thema's beoordelen" (§1.5) | Theme | bestaat |
| "66 research-items" (§1.5) | Research | bestaat |
| "Alles bekijken" (§1.5) | Agenda | bestaat |
| "Instellen" — koppelingen (§1.6) | Integrations (onder Settings) | bestaat |

### Rapport

| Link / knop | Bestemming | Status |
|---|---|---|
| "Waar dit vandaan komt" onder Story Triangle (§2.2) | Identity / StyleGuide, bovenaan | bestaat |
| "Plan een positioneringsstuk" (§2.2) | Theme / Agenda, aanmaakpagina zonder voorinvulling | bestaat |

### De twee minder voor de hand liggende links — besloten

Geen van beide vereist een nieuw scherm. Allebei wijzen naar een bestaande pagina:

- **"Plan een positioneringsstuk"** → de bestaande aanmaakpagina (Theme / Agenda). **Zonder**
  voorinvulling van de rol: de gebruiker komt op de aanmaakpagina en kiest daar zelf positionering.
  Eén klik meer, geen nieuwe feature. Voorinvulling is een latere verfijning, niet nu.
- **"Waar dit vandaan komt"** onder een grafiek → het bestaande architectuurscherm
  (Identity / StyleGuide), bovenaan geopend, **niet** toegespitst op één as. De grafiek in het
  rapport heeft net verteld welke as zwak is; de gebruiker ziet dat in de architectuur terug.
  Uitleg-per-as is een latere verfijning.

### Regel voor de rest

**Geen enkele link in deze twee schermen leidt naar een nieuw scherm.** Alles wijst naar een
bestaande pagina, hooguit met een filter of een deep link naar een specifiek item. Kom je een link
tegen waarvan de bestemming niet bestaat, meld het in plaats van een pagina te verzinnen.

---

## 4. Acceptatie

- Het Overzicht toont geen enkele grafiek; het Rapport toont er geen enkele zonder bijbehorende
  bevinding in tekst.
- Engagement is op het Overzicht zichtbaar als één meter, niet als grafiek en niet bovenaan.
- De terugkeerregel verschijnt alleen na > 7 dagen afwezigheid en bevat een link naar het Rapport.
- Blauwe stip alleen bij pijplijn-blokkades; uitstelbaar werk is grijs.
- Een gelegde koppeling staat niet meer op het Overzicht.
- Beide schermen hebben een lege staat die geen nul-grafieken toont.
- Nergens de kop "Je merk wordt opgebouwd".
- Geen decimaal op het merkcijfer zolang de determinismemeting niet is gedraaid.
- Elke doorklik wijst naar een bestaande pagina, of is in §3b expliciet als nieuw/te-bepalen
  gemarkeerd. Geen enkele link loopt dood of leidt naar een verzonnen scherm.

---

## 5. Buiten scope

- Nieuwe metingen of kolommen. Alles hierboven draait op bestaande data, anders herverdeeld.
- Een branche-/industryvergelijking in de engagementcijfers. Bestaat bewust niet.
- Het tonen van de rubriek of de scoringlogica aan de klant.
