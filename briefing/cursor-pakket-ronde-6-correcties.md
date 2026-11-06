# Cursor-briefing — pakket ronde 6 corrigeren en drie fixes die van ons zijn

> **Afgehandeld / vervallen (19 oktober 2026).** Vervangen door `briefing/cursor-ronde-6-afronden-en-stoppen.md`. Niet meer als werkopdracht gebruiken.

**Eigenaar:** Erik. **Datum:** 19 oktober 2026.
**Pakket:** `docs/rogier-pakket-ronde-6-pilot-2026-10-19/`.
**Model:** Composer 2.5.

Je vragenlijst A–H is grotendeels terecht, maar hij gaat niet in deze vorm naar Rogier. Vierentwintig vragen levert geen antwoord op. De begeleidende mail stelt er zes; die schrijf ik. Hieronder staat wat dat voor het pakket en voor de bouw betekent.

## Deel 1 — het pakket kloppend maken (vóór verzenden)

1. **Datum A6 syncen.** `01-pilotversie-eindmeting.md` regel 53 verwijst naar `docs/a6-screenshots-staging-2026-10-17/`, terwijl het pakket de set van 18 oktober meestuurt (elf screenshots in `screenshots-a6/`). Zet de verwijzing op 18 oktober.
2. **Stroomtestvragen in `02` §7**, niet in een los bestand. Eén document; twee documenten is precies wat Rogier in §4 van 16 oktober aanwees.
3. **Loginregel eruit.** `06-stroomtest-samenvatting.md` noemt op regel 5 en 47 het adres van de stroomtest-tenant. Rogier heeft dat niet nodig om de stroomtest te beoordelen. Haal beide regels weg; wil hij inloggen, dan regelen we dat apart.
4. **`docs/hba-v1.11-stap5-klanttaal.md` toevoegen** aan `build-rogier-pakket-ronde-6.sh`. De A6-LEESMIJ verwijst er twee keer naar, dus zonder dat bestand klopt het pakket niet.
5. **`03-bouwrapport-2026-10-17.md` committen.** Alles wat in het pakket zit hoort in de repo te staan.

Bouw de zip daarna opnieuw en meld de nieuwe bestandsgrootte en het aantal bestanden.

## Deel 2 — drie dingen die wij repareren, niet aan Rogier vragen

### 2a. De drie A6-restpunten

De Engelse sidebar (Tone, Platform), de kopdatum "18 October" in de agenda en de oude `(HBA v…)` in de rapportlijst gaan eruit. Dat vragen we hem niet; we melden dat het gerepareerd is. Maak nieuwe screenshots van alleen de drie gewijzigde schermen en voeg die toe aan `screenshots-a6/`.

### 2b. Engagement op checkpoint 25

Het rapport toont 21 posts terwijl de database er 25 heeft. Dat is een bug van ons, geen vraag voor Rogier. Zoek uit waar de vijf- of vierdelige afwijking ontstaat — filtert de query op status, op datumvenster, of op gescoorde posts? — en repareer het. Dit raakt weergave, niet scoring, dus het valt onder Rogiers bevriezingsregel en maakt de 5 × 19 niet ongeldig.

### 2c. De flagtelling: een voorstel, geen open vraag

De app telt nu elke `architecture_risk_flags`, ook op posts die op merk zijn. Daardoor loopt het getal in rapport en agenda op zonder dat er een patroon is, en wijkt het af van wat de Implementation Kit bedoelt.

Werk één voorstel uit — alleen patroonposts tellen, dus flags op posts waarvan het hoofdoordeel niet `op_merk` is — met in beeld wat dat doet met de stroomtestcijfers op checkpoint 10, 15, 20 en 25. Lever die vergelijking als tabel. **Bouw het nog niet in**; het gaat als voorstel mee naar Rogier, want de drempels zelf zijn zijn domein.

## Deel 3 — wat er niet gebeurt

- **Geen tweede 5 × 19** voordat Rogier §3 heeft beslist. Zeven van de acht afwijkende posts staan op 0 van 5 zonder spreiding; een herhaling reproduceert ze.
- **Geen scoringswijziging** vooruitlopend op dat besluit, ook geen "alvast voorbereiden" van optie 1 in de code. Zodra het besluit er is: één aanpassing, dan één hermeting, zoals de briefing voorschrijft.
- **Geen zesde hoofdoordeel.** "Grensgeval" wordt geen waarde naast de vijf uit §3; dat zou de ijkset, de klantlabels en de aggregaties breken. Als het ergens moet landen is het een toelichting, niet een oordeel.
- **Geen matrixwijziging.** De verwachtingen bij de drie neutrale posts blijven staan. Ons standpunt is dat de scorer fout zit, niet de matrix: een vacature en een webinar-aankondiging beweren niets over het merk en zijn dus geen categorietaal.

## Deel 4 — wat er na Rogiers besluit gebeurt

In deze volgorde, pas na zijn antwoord:

1. één aanpassing aan de volgorde of de flagdefinitie, volgens zijn richting;
2. 5 × 19 opnieuw;
3. kijken welke van de overige afwijkingen overblijven. Verwachting: Brandpunt markthygiëne heeft dezelfde oorzaak en verdwijnt mee; Duinroos kernbewering en Stelwerk samenwerking staan waarschijnlijk los en gaan dan pas, met data, terug naar Rogier.

Begin daar niet aan voordat het besluit er is.

## Afronden

- `php -l`, `py_compile` en `npm run build` op wat je raakt in deel 2.
- Eén commit voor deel 1 (pakket), één voor 2a, één voor 2b, één voor 2c.
- Geen staging zonder een expliciete "ja staging" van mij. Niets op productie.
