# LinkedIn Scan-mail — triage van GPT-review (aug 2026)

**Bron:** Erik + ChatGPT, review van de Doornvogel-scan.
**Status:** §1, §3.2, §6, §7 doorgevoerd; §3.1/§5 (mail/rapport splitsen, timeline, zachte CTA) nu ook gebouwd — deploy volgt.
**Raakt:** `linkedin-analyse/api/report-renderer.php`, `linkedin-analyse/api/analyzer.php`,
`linkedin-analyse/api/narrative-analyzer.php`.

---

## 1. Wat direct is doorgevoerd (code-niveau)

| Punt uit de review | Wat er stond | Wat er nu staat |
| --- | --- | --- |
| Afzender/product dubbelzinnig (iGiSNext/ContentFlow/HeroBrand naast elkaar) | "uitgevoerd door ContentFlow" + "De HeroBrand-laag is..." | "ContentFlow, een product van iGiSNext" in de kop; "HeroBrand" als merknaam eruit — functioneel omschreven zonder een derde productnaam te introduceren |
| Synthese onderscheid + consistentie ontbrak | `distinctiveness: ja` en `consistency` werden los getoond | Bij `ja` + lage/gemiddelde consistentie: één zin die beide combineert ("onderscheidend verhaal, maar wisselt nog per post") |
| Te stellige AI-claim | "helpt AI-modellen... juist te herkennen" | "vergroot de kans dat..." — geen garantie, een waarschijnlijkheid |
| Te harde toon bij niet-herhaalbare topposts | risico op "inhoudsloos"/"leunt op sentimentaliteit" | expliciete toon-instructie + FOUT→GOED-voorbeeld in de prompt: neutraal benoemen wat de post wél was |
| "Engagement onder verwachting" als harde norm | claim zonder benchmark | herschreven als vuistregel, expliciet genoemd dat het geen benchmark tegen vergelijkbare pagina's is |
| "Meer reacties" als kernbelofte | los engagement-advies | herschreven naar de eigenlijke businesscase: positionering vasthouden met meerdere makers, niet verwateren |
| Geen concreet "wat gebeurt er na de scan" | "bespreken we graag in een kort gesprek" | één zin extra over wat ContentFlow concreet doet (toetsen vóór publicatie, aanwijzing bij terugzakken), CTA blijft daarna staan |

Alle zeven zijn tekst-/prompt-only wijzigingen, geen structuurwijziging — geen migratie, geen
nieuwe velden.

---

## 2. Wat ik NIET heb doorgevoerd — en waarom

**Punt 7 (concreet voorbeeld van een herschreven post in de scan-mail).** De review stelt voor om
één van hun eigen posts te tonen zoals ContentFlow die zou beoordelen/verbeteren. Inhoudelijk een
sterk idee, maar ik zou het **niet** in de koude eerste scan-mail zetten: een vreemde prospect
ongevraagd voordoen hoe zijn eigen, al gepubliceerde post beter had gekund, kan overkomen als "wij
weten het beter" nog vóór er enige relatie is — dat is een ander register dan de rest van de mail,
die bewust voorzichtig en niet-belerend is (zie punt 3/4 hierboven). Beter passend bij het gesprek
zelf, niet bij de koude opener. Wel bouwen waard als demo-materiaal vóór een salesgesprek — dat is
een ander stuk werk dan deze mail.

---

## 3. Twee structurele voorstellen — wachten op een besluit

### 3.1 Mail en rapport elk hun eigen taak geven

De review stelt voor: **mail** = hoofdbevinding in 2-3 zinnen; **rapport** = dezelfde bevinding met
onderbouwing. Dat veronderstelt twee aparte artefacten. Onze werkelijkheid: er is er maar één — één
doorlopende HTML-mail die beide rollen tegelijk probeert te vervullen. Twee opties:

1. **Één mail houden, herstructureren.** Voorop de scherpste, persoonlijke bevinding (§3.2), dan
   de cijfers als onderbouwing, dan wat ContentFlow doet. Kleinste ingreep, geen nieuwe
   infrastructuur.
2. **Echt splitsen.** Korte mail (hoofdbevinding + CTA) + los rapport (link naar een gehoste
   pagina, of PDF-bijlage) met de volledige onderbouwing. Grotere bouwklus (hosting/link-flow of
   PDF-generatie), maar sluit dichter aan bij hoe zakelijke cold outreach vaak werkt.

Ik zou zelf optie 1 kiezen — kleinere ingreep, en de kern van het bezwaar (te lang, te veel
herhaling) is daarmee al opgelost. Optie 2 is pas de moeite waard als blijkt dat mensen de mail
wél openen maar niet doorscrollen — dat weet je nu nog niet.

### 3.2 Beginnen met Doornvogel, niet met de algemene ContentFlow-pitch

De review stelt voor: open met de merk-specifieke bevinding, niet met de algemene "AI beloont geen
gemiddelde merken"-uitleg. Dat is een reordening van `li_render_intro_html()`: case-line eerst,
dan pas de korte ContentFlow-uitleg. Inhoudelijk sterk — het is ook precies waarom we een paar
dagen geleden de intro al eenmaal hebben ingekort op ditzelfde punt (USP sneller, minder omweg).
Dit is de volgende, logische stap daarin.

**Waarom ik dit niet blind heb doorgevoerd:** het raakt dezelfde functie waar we net vier
verschillende dingen in hebben laten samenkomen (distinctiveness, description-alignment,
consistency, kwantitatieve fallback) — een reorder wil ik in één keer goed doen, niet als vijfde
laag boven op een net afgeronde reeks wijzigingen. Kleine losstaande vervolgtaak, geen groot risico
— vraag alleen even een go, dan bouw ik hem meteen.

---

## 4. Vraagt Erik's eigen antwoord, niet iets wat ik kan invullen

**Prijsindicatie in het rapport.** De review stelt een afgebakende pilot voor (merkprofiel, drie
contentlijnen, beoordeling van een aantal posts, vast bedrag) in plaats van "bespreken we graag in
een kort gesprek". Sterk idee om de drempel te verlagen — maar het bedrag en de precieze scope van
zo'n pilot zijn een prijsbeslissing, niet iets waar ik een getal bij kan verzinnen.

**Van gratis scan naar betaald product — wat IS ContentFlow?** Sluit rechtstreeks aan op de vraag
waarmee de review eindigt: is ContentFlow vooral analyse (zoals deze scan), of doet het ook het
bedenken/schrijven/plannen/toetsen zelf? Dat bepaalt niet alleen de scherpte van de propositie
(waar de review naar vraagt) maar ook wat er letterlijk in een pilot-aanbod zou moeten staan (punt
hierboven) én hoe stellig sectie 3.2's opening mag zijn. Drie beslissingen die op elkaar wachten.

---

## 5. Second opinion van Gemini — bevestigt de review, verschuift het advies op §3.1

Erik heeft de GPT-review laten checken door Gemini. Samengevat:

- **Onderschrijft alle zeven punten uit §1** als terecht — met name punt 4 (toon bij niet-herhaalbare
  topposts: "inhoudsloos" is riskant, neutraal herformuleren is beter) en punt 1 (afzender/merk-
  verwarring) worden expliciet aangehaald als sterk.
- **Kiest zelf voor "echt splitsen" (§3.1, optie 2)**, niet voor de kleinere herstructurering
  (optie 1) die ik zelf aanbeval. Argument: de mail is "far too long for a prospect to read in
  their inbox" — een korte mail als haak, een los rapport als onderbouwing. Twee onafhankelijke
  reviews (ChatGPT en Gemini) wijzen nu dus naar optie 2, niet optie 1.
- **Drie concrete aanvullingen**, los van wat al in §1 staat:
  1. **Hook → Insight → Pivot** als vaste opbouw voor de korte mail: compliment over hun aanpak →
     de concrete stilte/inconsistentie-observatie → ContentFlow als oplossing zonder hun eigen stem
     te verliezen.
  2. **Visuele indicators in het rapport** in plaats van tekstblokken — simpele stoplicht- of
     grafiek-achtige weergave van de engagement-dips tijdens stille periodes.
  3. **Lagere drempel-CTA**, concreet voorbeeld: "Can I share a quick 3-step outline of how we
     would translate your core message into a recurring format?" — kleiner gevraagd dan "een kort
     gesprek".
- **Herhaalt de eindvraag uit §4** (analyse vs. ook doen) letterlijk als eigen afsluitende vraag —
  bevestigt dat dit inderdaad de kernbeslissing is waar de rest op wacht, niet iets wat een tweede
  review kan oplossen.

**Wat dit verandert aan §3 en §4:** de weegschaal voor §3.1 slaat nu door naar optie 2 (echt
splitsen) — dat is een grotere bouwklus (hosting/link-flow of PDF-generatie) dan ik eerder
voorstelde, maar wel met nu twee onafhankelijke adviezen in dezelfde richting. Optie 2 blijft
open (bouwbeslissing, zie hieronder). §4 blijft openstaan — Gemini beantwoordt de analyse-vs-doen-
vraag niet, herhaalt hem alleen.

---

## 6. §3.2 alsnog doorgevoerd — data-selectie-advies gaf de ontbrekende invulling

Een derde stuk advies (data-selectie: welke cijfers uit de scan hoort de hook wél/niet te
gebruiken) maakte concreet wat er in §3.2 nog ontbrak: niet alleen "case-line eerst" was het
advies, maar ook **welk pijnpunt-cijfer** daarbij hoort. Kern van dat advies:

1. **Ego-boost eerst**: kernboodschap zonder overlap met branche-clichés (bestond al, was al
   leidend in `li_render_case_line()`'s `distinctLabel === 'ja'`-tak).
2. **Pijnpunt direct daarna, en concreet**: een stilte van bijv. 106 dagen is feitelijker en
   minder betwistbaar dan een oordeel als "de boodschap wisselt" — dus dát cijfer moet voorrang
   krijgen wanneer het er is, in plaats van een kwalitatieve consistency-uitspraak.
3. **Wat je juist NIET in de hook zet**: ruwe gemiddeldes ("0,1 posts/week", "3 van de 5 posts
   zonder comments") — te confronterend als opener, prospect gaat in de verdediging. Die horen in
   "De cijfers" verderop, niet in de eerste twee zinnen.
4. **Missed-opportunity-contrast** (topspost vs. kernbedrijfspost) hoort bij de overgang naar de
   oplossing, niet in de hook zelf — dat is al waar de herhaalbaarheid-observatie ('peak_unused')
   staat, verderop in de mail. Geen wijziging nodig.

**Doorgevoerd in code (`report-renderer.php`):**

- `li_render_intro_html()`: volgorde omgedraaid — case-line (hook) staat nu vóórop, de generieke
  ContentFlow-pitch volgt daarna als pivot. Voorheen stond de generieke uitleg vooraan en de
  merk-specifieke zin pas als derde alinea.
- `li_render_case_line()`, tak `distinctLabel === 'ja'`: als er een stilte > 21 dagen is, wordt nu
  eerst die stilte als pijnpunt genoemd (concreet cijfer), vóór de eerder bestaande
  ritme-/consistency-formulering. Drempel sluit aan bij de bestaande `no_rhythm`-observatie.
- Confirmed: geen ruwe engagement-gemiddeldes in de hook — die stonden al alleen in de "De
  cijfers"-tabel verderop, geen wijziging nodig.

**Wat nog niet is gedaan:** ~~het "echt splitsen" van §3.1/§5~~ **Gebouwd (aug 2026):** korte outreach-mail + publiek rapport via `report.php?t=…`, zachte CTA, postritme-timeline in het volledige rapport. De eerdere herordening binnen één mail (§6) blijft in het volledige rapport; de koude e-mail is nu apart en kort.

---

## 7. Redactieronde op de live Doornvogel-mail (aug 2026) — acht punten, allemaal doorgevoerd

Erik's contact bij Everaldo (AI-assistent) heeft de daadwerkelijk verstuurde Doornvogel-mail
geredigeerd — na §5/§6 hierboven was de mail inhoudelijk sterker, maar had nog acht concrete
redactieproblemen. Alle acht zijn nu gefixt:

| # | Probleem | Fix |
| --- | --- | --- |
| 1 | Grammaticaal foute eerste zin (`alignmentLabel === 'deels'`-tak) + typo "verwaterd" | Herschreven naar "X positioneert zich rond '...', maar die positionering komt slechts in een deel van de posts terug" + "verwatert" (zonder d) |
| 2 | "De twee dingen waar dit op beoordeeld is" boven 3 rijen | Titel nu dynamisch: 2 → "De twee dingen...", 3 → "De drie criteria...", 1 → "Waar dit op beoordeeld is" |
| 3 | "Dat onderscheid is wat AI beloont" — te stellig | Herschreven naar "Ook voor zoek- en AI-systemen helpt een duidelijke, onderscheidende positionering om beter te begrijpen waar een merk voor staat" — ook de onderwerpregel (`li_mailto_subject()`) meegenomen, die dezelfde claim maakte |
| 4 | "Weinig reacties t.o.v. volgers" zonder benchmark | Observatie + bijbehorende benefit ("Positionering vasthouden, ook met meerdere makers") volledig verwijderd uit `analyzer.php` — geen vuistregel meer die niet te onderbouwen is |
| 5 | "Onbenutte piek" en "Herhaal wat werkt" vertelden letterlijk hetzelfde verhaal (dubbele tekst) | `li_metrics_apply_repeatable_best_post()` overschrijft nu alleen de benefit ("Herhaal wat werkt"); de observatie ("Onbenutte piek") houdt zijn eigen, kortere tekst |
| 6a | Grammaticale bug in diezelfde herhaalbaarheid-tekst: "De sterkste post is post 1 ... werkt sterk emotioneel" (dubbel onderwerp) | Zin losgekoppeld van de lengte van `$excluded_reason` — werkt nu ongeacht of het model een kort zinsdeel of een volledige zin teruggeeft |
| 6b | "elk artikel" i.p.v. "elke post" (LinkedIn-scan, geen CE-artikelen) | Alle 5 voorkomens gefixt |
| 6c | "productpromotes", "bedrijfsfeestje-inhoud", ontbrekende punten tussen zinnen | Twee lagen: (1) code dwingt nu een afsluitend leesteken af vóór het samenvoegen van `inconsistency_examples`; (2) prompt in `narrative-analyzer.php` uitgebreid met een regel tegen verzonnen samengestelde woorden + twee FOUT→GOED-voorbeelden met precies deze twee fouten |
| 7 | Slotclaim "herkennen en aanbevelen" (ChatGPT) — te specifiek | Exacte formulering uit de feedback overgenomen: "Een consistente kernboodschap helpt mensen en digitale systemen beter begrijpen waar het merk voor staat" |
| 8 | Belofte-mismatch: "sneller content maken" vs. alleen toetsing in "Tot slot" | Opgelost via punt 4 — de benefit met die claim is verwijderd; overgebleven beloftes gaan allemaal over toetsen/aanscherpen, niet over content produceren |

**Nog niet gedaan, bewust:** de gevraagde algehele lengte-reductie (25-35% schrappen) is niet als
aparte losse actie uitgevoerd — punt 4 en 5 samen schelen al een observatie + een hele dubbele
alinea, dat is een groot deel van die reductie. Een volgende ronde kan beoordelen of er na deze
fix nog verder ingekort moet worden op basis van een nieuwe test-mail.

**Beslist (Erik, aug 2026):** abonnementsinhoud, prijs en opzegvoorwaarden komen niet in deze
eerste scan-mail. De huidige CTA ("bespreken we graag in een kort gesprek") noemt toch al geen
bedrag of voorwaarden, dus dit vergt geen codewijziging — alleen bevestiging dat de mail zo moet
blijven en dat dit onderwerp bewust pas in het gesprek zelf aan bod komt, niet ervoor. De
pilotprijs-vraag uit §4 blijft in dezelfde categorie: relevant voor het gesprek/pilot-aanbod
zelf, niet voor de openingsmail.
