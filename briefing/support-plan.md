# Content Engine — supportplan voor niet-technische klanten

**Status:** voorstel
**Datum:** aug 2026
**Raakt:** alle schermen (microcopy), `/support` (chat), onboarding
**Zie ook:** `docs/BUILD-NOTES.md` § Support-chatbot, `briefing/onboarding-master.md`,
`briefing/cursor-integrations-ordening.md`

---

## 1. Wie we voor ons hebben

Onze klant is een ondernemer of marketeer die haar vak uitstekend verstaat en dagelijks met
computers werkt. Ze is niet onhandig. Ze mist alleen één specifiek vocabulaire: API-key, token,
OAuth, IMAP-host, application password, Property ID.

Dat onderscheid bepaalt alles aan dit plan. We leggen niet uit hoe een browser werkt. We vertalen
één laag jargon naar gewone taal, en we doen dat zonder één seconde neerbuigend te zijn.

**Wat er in haar hoofd omgaat bij het veld "WordPress app-wachtwoord":**

- *Is dit mijn gewone wachtwoord? Dat ga ik hier toch niet intypen?*
- *Waar moet ik zijn — is dat het scherm van mijn websitebouwer?*
- *Als ik hier iets fout doe, gaat mijn website dan stuk?*
- *Ik wil dit niet aan Erik vragen, dan lijk ik dom.*

Die vierde is de belangrijkste en de duurste. Klanten die zich schamen, vragen niets, stellen uit,
en haken drie weken later stil af. Ons supportplan moet vooral **de drempel om te vragen slopen**.

---

## 2. Uitgangspunten

**1. Zij doet het, wij wijzen de weg.**
Nooit "dit is eenvoudig". Als het eenvoudig was, stond er geen uitlegballetje.

**2. Eerst waarom, dan wat.**
"We hebben dit nodig zodat CE artikelen op je site kan zetten" komt vóór de klikroute. Zonder
waarom is een instructie een bevel.

**3. Noem de plek, niet het begrip.**
Niet "ga naar je WP-admin", wel "log in op je eigen website — hetzelfde scherm waar je normaal een
pagina aanpast".

**4. Normaliseer het vastlopen.**
"Dit is het lastigste veld van de hele app" is waar, en het haalt de schaamte weg. Zeg het gerust.

**5. Eén ding per keer.**
Een uitlegballetje met vijf stappen is een handleiding. Toon één stap, met een `Volgende` erin.

**6. Altijd een uitweg.**
Elke hint eindigt met een manier om verder te komen zonder het zelf op te lossen. Geen enkel
scherm mag een doodlopende weg zijn.

**7. Vastlopen is nooit haar fout.**
Foutmeldingen beschrijven wat er gebeurde en wat nu te doen. Nooit "ongeldige invoer".

### Verbodslijst

Deze woorden komen niet meer voor in klantgerichte tekst:

| Nooit | Wel |
| --- | --- |
| gewoon, simpelweg, eenvoudig, uiteraard | *weglaten* |
| even | *weglaten* |
| je hoeft alleen maar | "twee stappen: …" |
| ongeldige invoer | "dit veld verwacht een webadres, bijvoorbeeld https://…" |
| er is een fout opgetreden | "de verbinding met je website lukte niet — meestal klopt het wachtwoord niet meer" |
| credentials, endpoint, payload, token *(onuitgelegd)* | "inloggegevens", "adres", "sleutel" |
| API-key | "sleutel — een lange reeks letters en cijfers die je van {dienst} krijgt" |

Uitzondering: als de klant het woord op het scherm van de andere partij ziet staan (LinkedIn toont
letterlijk *Community Management*), noemen we het net zo. Anders zoekt ze zich rot.

---

## 3. De stem

Warm, rustig, aanwezig. De toon van iemand die naast je zit met een kop koffie en zegt: *ik weet
waar dit staat, ik wijs het je aan.* Niet de toon van een handleiding en niet die van een
klantenservice-script.

Geen naam, geen avatar, geen gezicht. Wel volstrekt consistent, zodat de app als één persoon
klinkt. De reden om geen naam te kiezen: klanten gaan een naam als een mens behandelen, en dan
voelt "ik ben een AI-assistent" halverwege het gesprek als een streek. Bovendien is Eva al een echt
persoon binnen iGiSNext.

**Vier stemkenmerken, concreet toetsbaar:**

1. **Eerste persoon meervoud voor onszelf, tweede enkelvoud voor haar.** "We hebben dit nodig",
   "jij vindt het hier."
2. **Korte zinnen, geen opsomming waar een zin volstaat.**
3. **Erken het gedoe.** "Deze zit verstopt" mag. Het is eerlijk en het scheelt frustratie.
4. **Sluit af met openheid, niet met een punt.** Elke uitleg eindigt met de uitweg:
   *Kom je er niet uit? → **Help me verder***

**Let op de persoonswisseling bij die knop.** `Help me verder` is de klant die iets vraagt, niet de
app die iets belooft. Daarom spreekt de app zelf nergens als "ik" — dat zouden twee verschillende
ikken zijn in één zin. De app is altijd "we", de klant altijd "je".

**Zo klinkt het verschil:**

> **Nu:** WordPress: Application Password aanmaken in WordPress onder Gebruikers → Profiel →
> Application Passwords.

> **Straks:** Dit is níét je gewone wachtwoord. WordPress kan een apart wachtwoord maken speciaal
> voor programma's zoals Content Engine — zo hoef je je eigen wachtwoord nergens in te vullen, en
> kun je de toegang later intrekken zonder dat er iets anders stukgaat.
>
> Je vindt het door op je eigen website in te loggen en naar je profielpagina te gaan. Helemaal
> onderaan staat een kopje **Application Passwords**. Vul daar een naam in — "Content Engine" —
> en klik op de knop. Je krijgt dan een reeks van vier keer vier tekens. Die plak je hier.
>
> *Kom je er niet uit? → Help me verder*

---

## 4. Drie lagen support

De kern van dit plan: hulp die dichterbij staat naarmate je vaster zit.

### Laag 1 — Het uitlegballetje (waar het probleem is)

Ter plekke, bij het veld, in gewone taal. Bereikt iedereen, kost niets per keer. Dit is waar het
meeste werk in gaat zitten en waar de meeste winst zit.

Vaste opbouw in drie stukjes, in deze volgorde:

1. **Wat en waarom** — één of twee zinnen. Wat is dit, waarom vraagt CE het.
2. **Waar vind je het** — de klikroute, met de woorden die op het scherm van de andere partij
   staan. Bij de vier zwaarste velden uitklapbaar per stap.
3. **De uitweg** — `Kom je er niet uit? → Help me verder`

### Laag 2 — De support-chat, mét context (één klik verder)

We hebben de chat al op `/support`. Hij staat alleen op de verkeerde plek: je moet hem opzoeken op
het moment dat je juist ergens anders vastzit, en je moet je vraag zelf formuleren terwijl je het
vocabulaire mist. Dat is precies twee keer te veel gevraagd.

**Wat er verandert:** de knop `Help me verder` in elk uitlegballetje opent de chat met de vraag al
ingevuld:

> *Ik sta op Integrations bij het veld WordPress app-wachtwoord en kom er niet uit.*

Zij hoeft niets te formuleren en niets te weten. Ze klikt en er komt hulp. De chat weet uit de
meegestuurde veldsleutel waar ze staat en kan meteen doorvragen op het juiste ding.

De spend-cap van €5 per tenant blijft, maar wordt **maandelijks** in plaats van totaal (staat al in
de backlog). Een klant die in maand vier vastloopt moet niet tegen een op-budget aanlopen.

### Laag 3 — Een mens (het vangnet, bewust smal)

**Laag 2 lost het op, niet Erik.** De chat is geen doorgeefluik naar de mailbox; hij beantwoordt de
vraag zelf. Erik komt er alleen aan te pas als er iets kapot is, en dat is een kleine categorie.

**De scheidslijn — leg deze regel ook in de system prompt vast:**

| Soort vraag | Wie | Voorbeeld |
| --- | --- | --- |
| *Ik snap niet wat dit veld is / waar vind ik het* | **de chat** | "Wat is een app-wachtwoord?" |
| *Ik heb het ingevuld en het werkt niet* | **de chat eerst** — die loopt de bekende oorzaken langs | "Publiceren geeft een foutmelding" |
| *Er is aantoonbaar iets stuk aan onze kant* | **Erik** | koppeling staat op `Niet beschikbaar`, platform-config ontbreekt, API-fout die de klant niet kan verhelpen |
| *Inhoudelijk over merk of redactie* | **Eva** | "Is dit een goed thema voor ons?" |

De statuskaart uit het integrations-endpoint beslist die derde rij grotendeels automatisch: status
`unavailable` is per definitie iets wat de tenant niet kán oplossen en mag meteen door. Status
`not_set` of `action_required` is uitleg, en dus de chat.

**Wanneer de chat een mens aanbiedt:**

- De klant vraagt er zelf om — altijd honoreren, geen drempel opwerpen.
- Er is een `unavailable`-status of een echte serverfout in het spel.
- Pas na **vier** beurten waarin de klant blijft aangeven dat het niet lukt.

Niet dus: na twee beurten uit beleefdheid. Dat maakt van elke moeilijke vraag een mail.

De knop `Stuur mijn vraag door` staat wél altijd zichtbaar onder de chat. Hem verstoppen om mail te
vermijden werkt averechts: dan mailt ze je alsnog, maar zonder context en met meer irritatie.

Wat er meegaat: chatverloop, tenantnaam, scherm, `fieldKey` en de koppelstatus. Erik hoeft niets
terug te vragen en kan in één keer helpen.

**Als je toch te veel mail krijgt, is dat een signaal over de chat — niet over de klant.** De
`fieldKey`-logging (§8) laat zien welk veld het is. Verbeter dáár de uitleg; dat lost het probleem
structureel op in plaats van per mail.

---

## 5. De zeven momenten waar het misgaat

Support is geen kanaal maar een reeks momenten. Deze zeven, op volgorde van hoe vaak ze pijn doen:

| # | Moment | Wat er gebeurt | Wat we doen |
| --- | --- | --- | --- |
| 1 | **WordPress app-wachtwoord** | Ze vult haar gewone wachtwoord in, publiceren faalt | Hint met "dit is níét je gewone wachtwoord" als eerste zin; stap-voor-stap; `Test verbinding` vóór ze verdergaat |
| 2 | **Eerste koppeling** | Onduidelijk of het gelukt is | Groene bevestiging mét naam: "Gekoppeld als Erik Hoogendorp" — niet alleen een vinkje |
| 3 | **Aangezet maar niet gekoppeld** | LinkedIn `on`, geen OAuth (zie integrations-briefing) | Rood met directe knop, niet een grijs zinnetje op een ander scherm |
| 4 | **Eerste publicatie** | Angst: waar komt dit terecht, kan ik terug | Bevestigingsscherm dat de bestemmingen bij naam noemt; achteraf de directe link naar het gepubliceerde artikel |
| 5 | **Token verloopt** (na weken) | Het werkte, nu niet meer, geen idee waarom | "Je LinkedIn-koppeling is verlopen — dat gebeurt automatisch na een tijd, het ligt niet aan jou" + één knop |
| 6 | **Property ID / IMAP-host / actor-URL** | Ze weet niet wat het is en waar het staat | Hint met een **voorbeeld van de vorm**: "ziet eruit als 123456789" — herkenbaarheid gaat sneller dan uitleg |
| 7 | **Stilte na onboarding** | Alles staat aan, ze durft niet te beginnen | Proactieve mail in week 1 (zie §6) |

---

## 6. Wat we proactief doen

Drie momenten, meer niet. Klanten die overladen worden, lezen niets.

**Bij oplevering — een half uur samen.** Erik of Eva deelt het scherm en doet de koppelingen sámen
met de klant. Niet vóór haar: zij klikt, wij wijzen aan. Dat half uur bespaart drie weken
mailverkeer en het is het moment waarop ze leert dat vragen mag.

**Week 1 — één mail.** Kort, persoonlijk, geen nieuwsbrief: "Je eerste thema staat klaar. Wil je
dat we er samen naar kijken?" Doel is niet informeren maar de deur openhouden.

**Bij "actie nodig" — een mail vanuit het systeem.** Zodra een koppeling rood wordt (token
verlopen, wachtwoord afgekeurd) gaat er automatisch een mail uit in dezelfde warme toon, met een
directe link naar de juiste kaart op Integrations. Niet wachten tot zij het merkt bij een mislukte
publicatie.

---

## 7. Wie doet wat

| Wie | Rol |
| --- | --- |
| **Uitlegballetje** | Vangt de standaardvraag. Doel: 8 van de 10 klanten komen hier verder |
| **Support-chat** | Vangt de vervolgvraag en de paniek buiten kantooruren |
| **Erik** | Techniek, koppelingen die echt stuk zijn, alles wat de chat doorzet |
| **Eva** | Inhoud en redactie — thema's, tone of voice, "is dit een goed artikel" |

Eén adres naar buiten: **support@igisnext.com**. Klanten hoeven niet te weten wie wat doet.

**Reactietijd die we beloven:** binnen één werkdag, en dat halen we ook. Beloof geen twee uur.

---

## 8. Hoe we weten of het werkt

Klein houden, dit is geen supportafdeling. Vier signalen:

1. **Hoeveel klanten komen door de koppelfase heen zonder mail te sturen.** Het echte cijfer.
2. **Welke velden triggeren de meeste `Help me verder`-klikken.** Log alleen de veldsleutel, geen
   inhoud. Dat is meteen de prioriteitenlijst voor betere microcopy.
3. **Hoe vaak biedt de chat een mens aan, en hoe vaak wordt dat geaccepteerd.** Vaak aanbieden en
   zelden accepteren = de chat geeft te snel op. Zelden aanbieden en veel losse mail = te laat.
4. **Chatgesprekken loggen voor kwaliteit** (staat al in de backlog) — met melding aan de klant dat
   we meelezen om de uitleg te verbeteren.

---

## 9. Wat we bewust niet doen

- **Geen kennisbank of helpcenter.** Niemand zoekt daarin. De uitleg hoort bij het veld.
- **Geen video's.** Duur om te maken, verouderd zodra een scherm verandert, en je kunt er niet in
  zoeken terwijl je vastzit.
- **Geen chatbubbel rechtsonder op elk scherm.** Opdringerig en het leidt af tijdens redactiewerk.
  Hulp komt vanuit het veld waar je staat.
- **Geen tickets, geen ticketnummers.** Bij deze schaal is dat kille bureaucratie.
- **De chat lost niets zelfstandig op.** Geen acties uitvoeren namens de klant — uitleggen en
  doorverwijzen. De veiligheidsregels in de system prompt blijven ongewijzigd.

---

## 10. Volgorde van aanpakken

1. Microcopy voor de zeven pijnvelden herschrijven (grootste effect, laagste kosten).
2. `Help me verder`-knop + veldcontext naar de chat.
3. System prompt bijwerken: toon + de verhuizing naar Integrations (de prompt verwijst nog naar
   *Account → Publiceren* voor WordPress — dat klopt niet meer).
4. Chat biedt na twee beurten een mens aan, met doorstuurknop.
5. Mail bij "actie nodig", gekoppeld aan het statusendpoint uit de integrations-briefing.
6. Maandelijkse budget-reset in plaats van totaal.

Stap 1 en 3 kunnen deze week. De rest hangt aan het statusendpoint.

**Bouwdetails en de letterlijke teksten:** `briefing/cursor-fieldhint-support.md`.
