# Onboarding eerste klant — intern draaiboek

**Versie 1.0 · 28 juli 2026 · intern (niet naar klant sturen)**

Gefaseerd traject: **Fase 1 = snel live** (klant ziet binnen een week een echt artikel), **Fase 2 = merkarchitectuur-verdieping** (HBA/StoryBrand, pas als het systeem draait en de klant het waarom vóélt).

De volgorde is bewust. Wie begint met een sessie van twee uur over archetypen, verkoopt een adviestraject. Wie begint met een gepubliceerd artikel, verkoopt een systeem — en krijgt de archetype-sessie er later gratis bij, omdat de klant dan zelf vraagt waarom sommige stukken beter voelen dan andere.

---

## Voorbereiding (vóór kickoff)

Wat je zelf klaarzet, zodat de kickoff geen installatie-sessie wordt.

| Actie | Waar | Let op |
|---|---|---|
| Tenant aanmaken | `./scripts/create-client.sh` → `POST /api/admin/create-client` | Deploy-status checken; op studio gaf FTP eerder een timeout |
| Contact-/reviewer-e-mail invullen | Account-scherm, `contact_email` / `reviewer_email` | Dit adres krijgt de review-mails — verkeerd adres = stille flow |
| Login testen | `/login`, evt. `/forgot-password` | Vóór de kickoff, niet tijdens |
| `publish_mode` bepalen | Account | Start altijd op **manual_with_review**; automatic pas als er vertrouwen is |
| Posts per week + planning horizon | Account | Realistisch: 1/week. Liever opschalen dan teleurstellen |
| Bestaande content bekijken | Hun site/LinkedIn | Je hebt straks ammunitie nodig voor de tone-of-voice discussie |

**Valkuil:** verleiding om alvast Strategy in te vullen "om tijd te besparen". Niet doen — de klant moet die woorden zelf zeggen, anders herkent hij het artikel straks niet als het zijne.

---

## Fase 1 — Snel live (week 1)

Doel: **binnen zeven dagen één gepubliceerd artikel** waar de klant zich in herkent. Alles wat dat doel niet dient, schuift naar fase 2.

### Sessie 1 — Kickoff (90 min, samen achter het scherm)

Niet presenteren. Samen invullen. De klant moet het systeem zien reageren op zijn eigen input.

**Blok A — Strategy (40 min)**

**1. LLM-probleemvragen (15 min) — begin hier**

Dit is het eerste wat je invult, niet het laatste. Niet omdat het technisch het belangrijkste veld is, maar omdat het gesprek erover de rest van de strategie openlegt: zodra iemand hardop formuleert welke vraag zijn klant écht heeft, komen doel, tegenkracht en toon vanzelf boven tafel. En het is je onderscheidende verhaal — begin het traject waar je je van de rest onderscheidt.

Vraag: *"Stel, je ideale klant zit met een probleem en typt dat vanavond in ChatGPT. Wat typt hij precies?"* Doorvragen tot je 3 tot 5 concrete, probleem-gedreven vragen hebt — geen categorie-vragen ("wat is een goed bureau voor X"), maar de vraag onder het probleem.

Voorbeelden ter illustratie:

- "Hoe zorg ik dat kennis niet alleen in de hoofden van mijn medewerkers zit?"
- "Hoe maak ik mijn organisatie minder afhankelijk van de oprichter?"
- "Wat heb ik nodig voordat AI echt waarde toevoegt in mijn bedrijf?"

> **Let op — veld bestaat nog niet.** Er is nog geen apart Strategy-veld voor deze vragen (backlog, prioriteit Hoog). **Tijdelijke werkwijze:** zet ze onderaan `writing_guide` onder een kopje *"Vragen die onze doelgroep aan AI stelt:"*. CE-01 en CE-03 lezen dat veld mee in hun prompt, dus de vragen komen wél in de context terecht — alleen niet gestructureerd stuurbaar. Houd ze daarnaast apart bij in je eigen klantdossier, zodat je er maandelijks handmatig één kunt omzetten in een thema (via Fastlane of eigen thema op de Theme-pagina).
>
> Wat je de klant hierover vertelt: *"deze vragen nemen we mee bij het kiezen van thema's"* — niet *"elke maand wordt er automatisch één beantwoord"*. Dat laatste kan pas als het veld gebouwd is.

**2. De rest van Strategy (25 min)**

Vul samen in wat het minimum is om CE-01 zinnige thema's te laten voorstellen:

- **`brand_goal`** — waar het bedrijf naartoe wil. Doorvragen tot er iets staat dat geen holle frase is.
- **Archetype (voorlopig)** — kies er één uit de twaalf, zonder de volledige HBA-sessie. "Welke van deze twaalf voelt het meest als jullie?" Noteer dat dit een werkaanname is die in fase 2 wordt getoetst.
- **`adversary`** — waar vechten jullie tegen? Dit is het veld dat artikelen scherp maakt. Zonder tegenkracht wordt alles braaf.
- **`writing_guide`** — schrijfwijzer. Vraag ook expliciet naar **wat níet mag** (jargon, uitroeptekens, bepaalde claims). Plus de probleemvragen uit stap 1.

**Blok B — Sources (30 min)**

Minimaal één bron, liefst twee. Zonder bronnen zijn de eerste thema-voorstellen mager.

| Bron | Wanneer | Aandachtspunt |
|---|---|---|
| RSS-feed | Altijd — laagste drempel | Vakblad of nieuwsbron uit hun sector |
| Mailbox (IMAP) | Als ze nieuwsbrieven lezen | Wachtwoord/app-wachtwoord nodig; IMAP-host meestal auto-detect |
| Trello | Als ze al ideeën ergens parkeren | Snelle win: bestaand materiaal komt de flow in |
| Scraper (Apify) | Alleen bij expliciete behoefte | Kost budget per run — niet in week 1 |

Klik altijd op **Check** terwijl de klant kijkt. Een groen vinkje bij zijn eigen bron doet meer voor het vertrouwen dan een half uur uitleg.

**Blok C — Verwachtingen (20 min)**

Vertel expliciet hoe het ritme werkt, anders belt de klant woensdag dat er "niets gebeurt":

> Week N stelt het systeem thema's voor. Week N+1 verzamelt het de onderbouwing. Week N+2 ligt het artikel klaar. Dat lijkt traag, maar het is bewust — een thema dat een week mag rijpen levert een beter stuk op dan een thema dat direct wordt volgeschreven.

En daarna meteen: **"maar voor de eerste keer slaan we dat over."**

### Direct na sessie 1 — Fastlane

Gebruik **Fastlane** (`/fastlane`) om de weekgating te omzeilen: thema invullen, eventueel een research-URL, Write article. Binnen minuten ligt er een concept.

Waarom dit belangrijk is: de klant verlaat de kickoff met een echt artikel in plaats van een belofte. Dat is het moment waarop het systeem van "interessant" naar "dit werkt" gaat.

**Valkuil:** het eerste Fastlane-artikel is zelden meteen goed — de Strategy is nog dun. Zeg dat vóóraf: *"Dit is versie één. Wat je hier terugziet vertelt ons wat er nog aan de strategie ontbreekt."* Zo wordt een matig artikel diagnose in plaats van teleurstelling.

### Sessie 2 — Eerste redactie (45 min, 2-3 dagen later)

Loop samen het Article-scherm door:

- **Redacteursvragen** (de rode `→`-regels) — dit is het hart van je verhaal. Leg uit waarom ze er staan: AI schrijft technisch prima, maar zonder een echt cijfer, een echte anekdote of een echt tegengeluid prikt de lezer erdoorheen. Laat de klant er minstens één ter plekke beantwoorden en toon het verschil.
- **Aandachtspunten** (zelfcontrole uit CE-03) — het systeem geeft zelf aan waar de zwakste claim zit. Sterk moment: het is geen black box die zichzelf altijd goed vindt.
- **Tone en Angle** — laat zien dat dit per artikel te sturen is.
- **Beeld** — uploaden; de bredere beeldbibliotheek is nog toekomst, dus beloof niets.
- **Submit** → review-mail → **Approved** → **Plaats**.

### Einde week 1 — publiceren

Publiceer op WordPress. Klant plaatst zelf op LinkedIn (bewuste keuze: handmatig geeft beter bereik — verkoop dat als voordeel, want dat is het ook).

Vul daarna samen het **Archive**-formulier in: datum, promotiebudget, URL. Leg uit dat dit de basis is voor het rapport later — meten begint bij netjes afsluiten.

### Checkpoint einde fase 1

Alles hieronder moet groen zijn voordat je fase 2 inplant:

- [ ] Klant kan zelf inloggen
- [ ] Minimaal één bron staat op groen na Check
- [ ] Eén artikel gepubliceerd en gearchiveerd
- [ ] Klant heeft zelf minstens één redacteursvraag beantwoord
- [ ] Agenda is gevuld voor de komende weken
- [ ] Klant weet waar hij moet zijn: Home → to-do's

**Aandachtspunt:** de eenmalige onboarding-run die de rolling agenda volledig vult zodra de bronnen staan, is nog niet gebouwd (backlog #16). Tot die tijd handmatig triggeren via het admin theme-refill-endpoint, anders wacht de klant tot maandag en denkt hij dat er niets gebeurt. **Zet dit als taak in je agenda voor de dag na de kickoff.**

---

## Fase 2 — Merkarchitectuur (week 3-4)

Nu pas. De klant heeft inmiddels drie of vier artikelen gezien en voelt zelf dat sommige beter landen dan andere. Dat is het moment waarop de vraag "waarom klinkt dit soms niet als ons?" vanzelf komt — en dan verkoopt fase 2 zichzelf.

### Sessie 3 — HBA-werksessie (2 uur, met het team)

Gebruik de bestaande werkinstructie. Veertien velden, waaronder:

- Archetype (nu definitief toetsen, niet de werkaanname uit week 1)
- Doel en Foe, causaal verbonden — niet los van elkaar
- Spanning en mechanisme
- Geloofwaardigheidstest en verboden logica
- Bewijsvelden, goede en zwakke voorbeelden

**Regie:** dit is een sessie met het team, niet met één persoon. Als de eigenaar alleen zit, krijg je het gewenste zelfbeeld in plaats van het merk. Vraag door op het Foe-veld — daar zit de energie, en daar wordt het vaak vaag.

**Bewaken:** StoryBrand-taal ("de klant is de held", "wij zijn de gids") mag hier niet binnenkomen. Eerst merkarchitectuur, dan pas klanthelderheid. Als de klant zelf in die termen praat, parkeer het expliciet voor sessie 4.

**Licentie:** HBA is beschermd IP van Hero Brands. Check de licentiestatus vóór je dit inzet bij een betalende klant.

### Sessie 4 — StoryBrand-vertaling (2 uur)

Negentien velden: klantsegment, buying stage, kanaal, customer goal, extern/intern/filosofisch probleem, rol van het merk, plan, primaire en secundaire CTA, success picture, failure/stakes, one-liner.

Meerdere segmenten kunnen naast elkaar: één vaste HBA-kern, meerdere vertalingen per doelgroep. Begin met één segment — het belangrijkste. Uitbreiden kan altijd.

### Sessie 5 — Terugkoppeling (60 min)

Neem de artikelen uit fase 1 er weer bij en toets ze tegen wat er nu op tafel ligt. Dit is het meest overtuigende gesprek van het hele traject: dezelfde artikelen, maar nu kun je precies benoemen wáár ze afweken en waarom.

Werk daarna Strategy bij met de definitieve waarden en laat de volgende thema-ronde draaien.

---

## Fase 3 — Overdracht (week 5-6)

Doel: de klant draait zelfstandig, jij kijkt mee.

| Actie | Toelichting |
|---|---|
| Eerste rapport genereren | CE-07 → `/report`. Engagement, angle-mix, artikeltellingen |
| Ritme afspreken | Wie beoordeelt wat, wanneer, met welke doorlooptijd |
| Campagne-uitleg | `/campaign` voor thematische reeksen naast losse artikelen |
| Escalatiepad | Wie belt de klant als er iets stilstaat |
| Evaluatie inplannen | Over 4 weken; niet vrijblijvend, gewoon in de agenda |

---

## Wat je wél en niet belooft

**Wel:**

- Elk stuk wordt getoetst aan hetzelfde merkverhaal — dat is wat consistentie oplevert
- Het systeem stelt zelf vragen om er iets menselijks in te krijgen
- Thema's vertrekken vanuit de vragen die hun doelgroep aan AI-assistenten stelt
- Het scheelt een dag per week aan bedenken, zoeken en schrijven

**Niet:**

- Dat je meetbaar in AI-antwoorden verschijnt. Je schrijft er gericht naartoe, maar de meting bestaat nog niet
- Automatische LinkedIn-publicatie (backlog)
- AI-gegenereerde beelden (bewust buiten scope)
- Automatisch teruggekoppeld advertentiebudget (backlog)
- Volledig automatisch publiceren zonder review — technisch mogelijk, maar niet voor de eerste klant

---

## Risico's bij deze eerste klant

| Risico | Signaal | Actie |
|---|---|---|
| Agenda blijft leeg na Sources | Klant ziet niets gebeuren | Handmatig refill draaien; onboarding-run staat nog op de backlog |
| Klant vindt eerste artikel tegenvallen | Lauwe reactie in sessie 2 | Vooraf framen als diagnose; direct Strategy aanscherpen |
| Probleemvragen verdwijnen uit beeld | Geen enkel artikel beantwoordt er één | Veld bestaat nog niet — maandelijks handmatig één omzetten in thema; bijhouden in klantdossier |
| Redacteursvragen worden genegeerd | Artikelen blijven generiek klinken | Terugkomen op het vertrouwensverhaal; laat het verschil zien |
| Review-mail komt niet aan | Stille flow, niets in Pending | `reviewer_email` checken; `reviewUrl` als fallback |
| HBA-sessie te vroeg | Klant haakt af op abstractie | Vasthouden aan de fasering, ook onder druk |
| Testen op productiedata | — | Nooit. Gebruik een sandbox-tenant. Er is eerder editorial content verloren gegaan bij een delete-test |

---

## Tijdsbesteding (indicatie)

| Fase | Jouw uren | Klant-uren |
|---|---|---|
| Voorbereiding | 2 | 0 |
| Fase 1 | 5 | 3 |
| Fase 2 | 6 | 5 (team) |
| Fase 3 | 3 | 2 |
| **Totaal** | **16** | **10** |

Bij de eerste klant realistisch 1,5x — je bouwt het draaiboek terwijl je het uitvoert. Houd bij wat je tegenkomt; versie 2 van dit document schrijft zichzelf daarmee.
