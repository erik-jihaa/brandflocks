# A6-restpunten, ronde 2

20 oktober 2026, Rogier. Reactie op `hba-v1.12-stap4-a6-restpunten.md` en de screenshots van 19 oktober.

Akkoord op restpunt 1, 2 (knoppen), 4 en 5. Hieronder twee punten om op te lossen, één bewijs en één vraag. Punten A en B zijn weergave en feedbacktekst, geen scoring. Ze vallen dus niet onder de bevriezing en vragen geen nieuwe 5 × 19.

## A. Agenda volledig in het Nederlands

**Wat er nu staat** (`11-agenda-signalen.png`): de kop "Agenda _ 19 October 2026" én de weekregels "5 October - 11 October", "26 October - 1 November". De notitie noemt de weekregels Nederlands, maar ze zijn Engels.

**Opdracht:**
1. Formatteer alle datums in de agenda met de Nederlandse locale (`nl-NL`, bijvoorbeeld `Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'long', year: 'numeric' })`). Geen hardgecodeerde Engelse maandnamen.
2. Kop: "Agenda _ 19 oktober 2026".
3. Weekregels: "5 - 11 oktober". Loopt de week over twee maanden: "26 oktober - 1 november".
4. Controleer ook de kalenderweergave (tab Kalender) en andere plekken waar datums als tekst staan: Overzicht, Rapport, Archief en Planning.

**Klaar als:** nergens in de klantschermen staat nog een Engelse maand- of dagnaam. Lever screenshots van Agenda in de lijst- en kalenderweergave.

## B. De regel letterlijk in de CE-04-opmerking

**Wat er nu staat** (`07-feedback-terugkoppeling.png`, slot `c8d60edd-…`):

> Dit stuk gaat in tegen je regel dat je geen korting geeft op oudere voorraad — koffie die langer dan zes weken geleden is gebrand wordt niet verkocht, ook niet met korting.

"Geen korting op oudere voorraad" is een parafrase die de regel verandert. De regel van Brandpunt gaat over het niet verkopen van koffie die langer dan zes weken geleden gebrand is. Korting is daar alleen een gevolg van.

**De regel in de merkarchitectuur:**

> Wij verkopen geen koffie die langer dan zes weken geleden is gebrand, ook niet met korting. Wat over is, gaat naar de buurtvoedselbank of wordt weggegooid.

**Opdracht:**
1. De CE-04-opmerking bij een regelbreuk noemt de regel **letterlijk**, zoals hij in het veld "Jullie regels" van de merkarchitectuur staat, tussen aanhalingstekens. Daarna mag een eigen uitleg volgen van wat in de tekst de regel breekt.
2. Vorm: `Dit stuk gaat in tegen je regel: "<regel letterlijk>". <Uitleg in één of twee zinnen, met het citaat uit de tekst.>`
3. Gebruik dezelfde controle als bij de hint in stap 2a: het fragment tussen aanhalingstekens moet letterlijk in het veld "Jullie regels" voorkomen. Zo niet, dan één keer opnieuw genereren, en anders de regel uit het veld zelf invullen in plaats van de gegenereerde tekst.
4. Heeft een merk meerdere regels, dan alleen de regel die gebroken wordt.

**Klaar als:** CE-04 bij HBA-test post 3 geeft voor Brandpunt, Duinroos en Stelwerk de regel woord voor woord terug, in 3 van 3 runs. Lever de JSON en één screenshot.

## C. Bewijs voor de hashtagcontrole (restpunt 6)

Lever één voorbeeld: een Brandpunt-post met `#versgebrand` als hashtag, waarbij de score of CE-03 de hashtag markeert. Een JSON-fragment met het citaat is genoeg.

## D. Vraag: het stopbericht van ronde 6

De notitie noemt v1.12, een "afronden-briefing", een "stopbericht ronde 6" en "geen derde 5 × 19". Rogier heeft de uitslag van de tweede 5 × 19 nog niet gezien. Stuur graag het stopbericht met:
- de tabel per post, zoals `04-stabiliteit-v1.11.md`;
- de ruwe JSON;
- welke besluiten uit `terugkoppeling-rogier-ronde-6-ingevuld.md` in v1.12 zijn gebouwd (merkbewering, volgorde, logging, de voorwaarde in trede 2, de verwachtingen voor de reviewposts).

Op basis daarvan beslist Rogier over de start van de pilot.
