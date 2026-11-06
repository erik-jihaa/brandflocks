# Content Engine — Strategische productvisie
## Raadpleeg dit document bij grote wijzigingen aan de app

Dit document beschrijft de strategische richting van de Content Engine op lange termijn.
Het is geen technische specificatie maar een kompas: als we nieuwe features bouwen,
schermen herontwerpen of de onboarding aanpassen, moet dit document leidend zijn.

---

## Kerngedachte

De Content Engine is geen tool die posts genereert.
Het is een systeem dat helpt om een herkenbaar merk op te bouwen — week na week,
post na post — door strategie te vertalen naar consistente contentuitvoering.

Het verschil met generieke AI-tools: wij beginnen bij het fundament, niet bij de prompt.

---

## Het fundament: tien vragen die elk bedrijf moet beantwoorden

Voordat de Content Engine goed kan werken, moet een klant deze vragen hebben
beantwoord. Dit zijn de strategische basisvragen voor elk socialmediafundament.
De app begeleidt klanten bij het beantwoorden ervan — via onboarding, Strategy-scherm
en Account-instellingen.

Bij het bouwen van nieuwe features: check altijd of de feature bijdraagt aan het
beantwoorden of *toepassen* van deze tien vragen. Features die dat niet doen,
horen waarschijnlijk niet in de kern van de app.

---

### 1. Waarom zitten we op socials?

Wat willen we bereiken?

Mogelijke doelen: naamsbekendheid, leads, verkoop, recruitment, community bouwen,
klantenservice, thought leadership, merkvoorkeur.

**Waar dit in de app zit:** `brand_goal` in het Strategy-scherm.
**Toekomstige richting:** onboarding vraagt dit expliciet als eerste stap,
vóór archetype en schrijfwijzer. Het doel bepaalt namelijk welke KPI's later
in het Report-scherm leidend zijn.

---

### 2. Voor wie maken we content?

Wie is de doelgroep?

Denk aan: leeftijd, functie, interesses, problemen, koopgedrag, taalgebruik,
platformgebruik en waar ze gevoelig voor zijn.

**Waar dit in de app zit:** impliciet in `tone_of_voice` en de Tone-as
(b2b / b2c / partners / internal / personal).
**Toekomstige richting:** een apart doelgroepveld in Strategy, zodat CE-01
(themavoorstellen) en CE-03 (artikelschrijven) de doelgroep expliciet kunnen
meenemen in de prompt — nu is dit nog impliciet.

---

### 3. Wat willen zij van ons zien?

Niet: "Wat willen wij posten?"
Maar: "Welke content helpt, inspireert of activeert onze doelgroep?"

Voorbeelden: tips, behind the scenes, klantcases, how-to's, inspiratie,
bewijs, aanbiedingen, meningen, entertainment.

**Waar dit in de app zit:** gedeeltelijk in `writing_guide` en in de
research-items die de redacteur liket of vergeet.
**Toekomstige richting:** contentpijlers (zie vraag 7) als apart veld,
zodat CE-01 thema's kan voorstellen die over de pijlers zijn verdeeld
in plaats van steeds hetzelfde type content te genereren.

---

### 4. Waar staan we als merk voor?

Wat is onze merkpersoonlijkheid?

Deskundig, speels, premium, rebels, toegankelijk, activistisch, humoristisch,
nuchter? Dit bepaalt toon, visuals en onderwerpen.

**Waar dit in de app zit:** `archetype` + `archetype_traits` in Strategy,
gebaseerd op de Hero Brand-methode (Rogier Heijning, herobrands.nl).
**Toekomstige richting:** dit is al goed ingebakken. Versterken via het
leerprofiel (CE-06): naarmate er meer gepubliceerd is, leert het systeem
welke archetype-uitingen het beste werken voor deze klant.

---

### 5. Welke boodschap moet blijven hangen?

Wat moeten mensen over ons onthouden?

Voorbeelden: "Wij zijn de snelste", "Wij maken het makkelijker",
"Wij zijn de duurzame keuze", "Wij begrijpen jouw wereld".

**Waar dit in de app zit:** `adversary` (de tegenstander definieert indirect
de kernboodschap) + `brand_goal`.
**Toekomstige richting:** een expliciet "kernboodschap"-veld naast de adversary,
zodat CE-03 per artikel kan checken of de kernboodschap erin terugkomt —
nu doet de zelfcontrole-stap (Stap D) dat impliciet via de adversary.

---

### 6. Welke kanalen gebruiken we?

Niet elk platform hoeft.

| Kanaal | Mogelijke rol |
|--------|--------------|
| Instagram | merk, inspiratie, community |
| TikTok | bereik, entertainment, cultuur |
| LinkedIn | autoriteit, B2B, recruitment |
| Facebook | lokale community, events, oudere doelgroep |
| YouTube | uitleg, long-form, vindbaarheid |
| Pinterest | inspiratie, planning, shopping |

**Waar dit in de app zit:** `platform_suggested` / `platform_final` per artikel,
`default_platform` per klant, en `platform_publish_mode` (auto/handmatig per kanaal).
**Toekomstige richting:** per kanaal een eigen contentstrategie definiëren
(LinkedIn = thought leadership, Instagram = visueel/persoonlijk) zodat CE-03
dezelfde rationale kan omzetten naar platformspecifieke tekstvarianten.

---

### 7. Welke contentpijlers gebruiken we?

Kies 3 tot 5 vaste thema's waar je steeds op terugkomt.

| Contentpijler | Voorbeeld |
|--------------|-----------|
| Educatie | tips, uitleg, how-to's |
| Bewijs | reviews, cases, resultaten |
| Merkverhaal | missie, waarden, team |
| Product/dienst | features, voordelen, demo's |
| Community | klanten, vragen, user-generated content |

**Waar dit in de app zit:** nog niet als apart veld — nu impliciet via de
fasecyclus (`phase_key`) en de Angle-as (archetype/adversary/general).
**Toekomstige richting:** contentpijlers als configureerbaar veld in Strategy,
zodat CE-01 thema's distribueert over de pijlers en de redacteur in het
Report-scherm kan zien of de verdeling klopt.

---

### 8. Hoe vaak posten we?

Bepaal een haalbaar ritme. Beter drie goede posts per week consequent
dan twee weken fanatiek en daarna stil.
Leg ook vast wie content maakt, goedkeurt en publiceert.

**Waar dit in de app zit:** `posts_per_week` per klant, de week-gating scheduler
(CE-01 vult de planning-horizon automatisch aan), `contact_role` bepaalt wie goedkeurt.
**Toekomstige richting:** een zichtbaar ritme-overzicht in Home of Agenda,
zodat de redacteur in één oogopslag ziet of het ritme op schema ligt.

---

### 9. Hoe ziet succes eruit?

Welke KPI's horen bij je doel?

| Doel | KPI's |
|------|-------|
| Awareness | bereik, impressies, volgersgroei |
| Engagement | likes, reacties, shares, saves |
| Leads | clicks, formulieren, DM's |
| Sales | conversies, omzet, kortingscodes |
| Community | reacties, mentions, UGC |

**Waar dit in de app zit:** het Report-scherm (CE-07) meet nu engagement
op-strategie versus generiek, en site-traffic. De KPI's zijn nog niet
gekoppeld aan het doel uit vraag 1.
**Toekomstige richting:** als `brand_goal` een categorie heeft
(awareness / leads / sales / community), kan het Report-scherm automatisch
de relevante KPI's vooraan zetten en de rest dimmen.

---

### 10. Wat doen we wel en niet?

Maak duidelijke spelregels.

Denk aan: tone of voice, onderwerpen die je vermijdt, reactiebeleid,
crisisreacties, huisstijl, beeldgebruik, humorgrenzen en wie mag posten.

**Waar dit in de app zit:** `writing_guide` (Schrijfwijzer) in Strategy —
dit is de plek waar spelregels leven.
**Toekomstige richting:** de Schrijfwijzer opsplitsen in een vrij tekstveld
(nu) én een gestructureerd "niet doen"-blok, zodat CE-03 en de zelfcontrole-stap
(Stap D) expliciet kunnen checken of een artikel iets doet wat niet mag.

---

## Samenvatting voor bij nieuwe features

Elke nieuwe feature of grote wijziging in de app zou een van deze vragen
moeten versterken:

> Waarom posten we — voor wie — met welke boodschap — op welke kanalen —
> in welke vorm — hoe vaak — door wie — en hoe meten we succes?

Als een feature geen van deze vragen beter beantwoord of beter toepast,
hoort het waarschijnlijk niet in de kern van de Content Engine.

---

## Nog niet gebouwd — toekomstige prioriteiten

Op basis van de tien vragen zijn dit de grootste gaten in de huidige app:

| Prioriteit | Wat | Raakt vraag |
|-----------|-----|-------------|
| Hoog | Doelgroepveld in Strategy (expliciet, niet impliciet in tone) | 2 |
| Hoog | Contentpijlers als configureerbaar veld | 3, 7 |
| Hoog | LLM-probleemvragen in Strategy (zie sectie hieronder) | 3, 5 |
| Middel | KPI-koppeling aan brand_goal in Report | 1, 9 |
| Middel | Kernboodschap-veld naast adversary | 5 |
| Laag | Platformspecifieke tekstvarianten per artikel | 6 |
| Laag | Schrijfwijzer opsplitsen met "niet doen"-blok | 10 |

---

## LLM-vindbaarheid — strategische uitbreiding

### Achtergrond

Steeds meer mensen stellen een zoekvraag aan ChatGPT, Perplexity of Claude
in plaats van aan Google. De AI noemt een handjevol namen. Wie genoemd wordt,
wint. Wie niet genoemd wordt, bestaat niet in dat gesprek.

LLM's leren niet van SEO-techniek, maar van tekst die breed geciteerd en
besproken wordt op het open web. De Content Engine draagt hier al aan bij
via consistente merkpositionering en WordPress-publicatie op het hoofddomein.
Maar er zijn drie soorten zoekvragen die bepalen of een organisatie gevonden
wordt — en de huidige setup adresseert er bewust maar één:

| Type zoekvraag | Voorbeeld | Gedekt door CE? |
|---------------|-----------|-----------------|
| Branded | "Wat doet iGiSNext?" | ✅ Als de naam ergens publiek staat |
| Categorie | "Wat is een goed AI-bureau voor groeiende bedrijven?" | ✅ Via consistente positionering over tijd |
| Probleem-gedreven | "Hoe zorg ik dat kennis niet in hoofden van medewerkers blijft?" | ⚠️ Nog niet structureel |

Het probleem-gedreven type is het waardevollste: de potentiële klant stelt
de vraag die hij of zij echt heeft, zonder een specifiek bureau al in gedachten.
Als de Content Engine die vraag structureel beantwoordt in gepubliceerde tekst,
vergroot dat de kans dat een LLM de naam van de klant noemt als relevant antwoord.

### Wat er in de app moet komen

**Nieuw veld in Strategy: LLM-probleemvragen**

Een veld (of subsectie onder `brand_goal`) waar de klant 3-5 concrete
probleemvragen invult die hun doelgroep stelt aan een LLM. Bijvoorbeeld
voor iGiSNext:

- "Hoe zorg ik dat kennis niet alleen in de hoofden van mijn medewerkers zit?"
- "Hoe maak ik mijn organisatie minder afhankelijk van de oprichter?"
- "Wat heb ik nodig voordat AI echt waarde toevoegt in mijn bedrijf?"
- "Hoe blijf ik herkenbaar als mijn organisatie sneller groeit?"

**Hoe CE-01 dit gebruikt**

CE-01 (Theme Director) neemt de LLM-probleemvragen mee bij het voorstellen
van thema's. Per planningshorizon van vier weken wordt minimaal één thema
gesuggereerd dat een van de probleemvragen direct beantwoordt — niet als
merkpost ("wij doen X") maar als inhoudelijk artikel dat de vraag oplost.

Dat type tekst is precies wat LLM's oppikken als relevant antwoord bij een
probleem-gedreven zoekvraag: concreet, vraag-beantwoordend, en gepubliceerd
op een gezaghebbend domein.

**Hoe CE-03 dit gebruikt**

Als een artikel gebaseerd is op een LLM-probleemvraag (via het thema),
verwerkt CE-03 Stap A (rationale) expliciet de vraag als startpunt:
"Dit artikel beantwoordt de vraag: [probleemvraag]. Vanuit het archetype
van het merk is de invalshoek: [rationale]."

Dit zorgt dat de tekst niet alleen de vraag beantwoordt, maar dat ook
merkbaar doet vanuit een herkenbaar standpunt — zodat het zowel LLM-vindbaar
als merkonderscheidend is.

**Relatie met "Do LLM's find us?" in het Report-scherm**

Dit veld sluit aan op het bewust opengelaten punt in CE-07 (briefing 4.5.6):
een periodieke check of het merk verschijnt in LLM-antwoorden op de
ingevulde probleemvragen. De probleemvragen zijn dan direct de testvragen
voor die check — geen handmatige opzet nodig.

### Implementatieprincipe bij nieuwe features

Bij elke nieuwe feature die content-richting of thema-selectie beïnvloedt:
check of de LLM-probleemvragen als input kunnen meelopen. Het gaat niet om
een aparte "SEO-modus" maar om een structureel onderdeel van de merkstrategie
dat doorwerkt in de normale weekelijkse contentagenda.

