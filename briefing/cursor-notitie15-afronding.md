# Cursor-briefing — notitie 15 afronden (build, merkcijfer, klanttaal, scores, lege staat Rapport)

**Branch:** `feature/overview-report-15` (bouw verder op `3e2fd0e`)
**Model:** Composer 2.5
**Achtergrond:** evaluatie van notitie 15 op 30 sep (cijfer 5/8). Home en Rapport zijn goed ingedeeld; deze vijf punten moeten opgelost zijn vóór de case op staging.
**Regel:** kom terug als iets niet klopt met wat hieronder staat, in plaats van een eigen variant te bouwen.

---

## 1. Build repareren

`npm run build` (= `tsc -b && vite build`) faalt:

```
src/components/StatusRow.tsx(236,31): error TS6133: 'id' is declared but its value is never read.
```

Haal `id` uit de destructuring van `HomeTodoRow` (en uit `HomeTodoRowProps` als het nergens anders gebruikt wordt). De `key` in `HomePage.tsx` blijft `item.id`.

**Klaar als:** `cd frontend && npm run build` slaagt zonder fouten.

## 2. Terugkeerregel: het juiste cijfer als "merkcijfer"

`ce_build_home_return_summary()` in `web/api/home-repository.php` (~1088) zegt "je merkcijfer liep licht op / zakte iets / bleef stabiel". Die richting komt nu uit `strategyTrend`: het aandeel posts op strategie, niet het merkcijfer. Daardoor kan de zin iets anders zeggen dan de meter Merkcijfer eronder.

- Gebruik dezelfde bron en richting als de meter **Merkcijfer** in `frontend/src/utils/homeOverviewMeters.ts` (PBS-verloop / `pbsHistory` uit `/hba/progress`). Home en terugkeerregel moeten altijd hetzelfde zeggen.
- Is die richting in PHP niet beschikbaar zonder de HBA-berekening opnieuw te draaien? Laat de merkcijfer-zin dan weg. Verzin geen andere bron.
- Twee kleine tekstfixes in dezelfde functie:
  - "wel N reacties op eerdere posts" klopt niet: de query telt alleen posts uit de afwezigheidsperiode. Laat dat deel weg.
  - "er wachten N dingen op jou": tel alleen todo's met `blocking: true`.

**Klaar als:** bij een testtenant geven de terugkeerregel en de meter Merkcijfer dezelfde richting, of de terugkeerregel noemt geen merkcijfer.

## 3. Geen methodiektaal voor de klant

Deze termen mogen **nergens** in klantzichtbare tekst staan: *archetype, Foe, Story Triangle, contextprofiel, held, gids, PBS, HBA, Brand Contribution*. Gevonden:

| Plek | Nu | Doen |
|---|---|---|
| `components/reports/ReportDiagnosis.tsx:95` en `ReportRadarChart.tsx:135` | titel "Story Triangle" | "Hoe je merkverhaal terugkomt" |
| `components/HomeStrategyTrend.tsx:39-40, 96` | "Brand Contribution ≥ 6,0" en "archetype/tegenkracht" | Weg. De niet-HBA-tak (`archetype of tegenkracht`) is dode code sinds HBA standaard is: verwijder die tak helemaal. Gebruik als uitleg "posts die je merk versterken". |
| Aslabels van de merkverhaal-radar en de bevinding in `utils/reportFindings.ts` (`storyTriangleFinding`) | de labels uit `trackerDetails.storyTriangle`, zoals de API ze stuurt | **Eerst melden** welke labels de API stuurt. Staat daar Foe, held of archetype in: gebruik dezelfde woorden die de klant ziet in zijn eigen Merkarchitectuur-scherm (de labels in `MerkarchitectuurSection.tsx`), zodat het rapport de taal van de architectuur spreekt. |

Daarna: `grep -rniE "archetype|\bfoe\b|story triangle|contextprofiel|\bheld\b|\bgids\b|\bPBS\b|brand contribution" frontend/src` en loop elke treffer na. Alleen code-identifiers, commentaar en niet-klantzichtbare strings mogen blijven. Doe hetzelfde voor strings die `home-repository.php` en `agenda-portfolio-signals-repository.php` naar de klant sturen.

Neem hierbij ook de Engelse restjes op Home en Rapport mee:
- "Theme starten/beoordelen" → "Thema's starten/beoordelen"
- "thema(s) wachten op review" → "… wachten op je beoordeling"
- "Reviews afhandelen"
- "Geen daily breakdown"
- "Strategy/General" (`ReportDailyChart.tsx`)
- de tone-labels BtoC/BtoB/Personal/Internal/Partners (`ReportRadarChart.tsx`)
- "Findability laden…"

En de tekstfouten:
- "Je merk werd deze periode stabiel" → "Je merk bleef deze periode stabiel".
- Getallen in signaalteksten met een komma, niet met een punt: `%.1f` in `agenda-portfolio-signals-repository.php:152` geeft "5.8"; gebruik `number_format($x, 1, ',', '')`.
- "gemiddeld" alleen als het ook echt een gemiddelde is; bij een mediaan "doorgaans" of "de middelste post".
- Weg: "komt in de teksten minder scherp terug dan in je architectuur" (`reportFindings.ts` ~154). Er staat geen vergelijking met de architectuur achter.

**Klaar als:** de grep hierboven geen klantzichtbare treffers meer oplevert.

## 4. Geen exacte merkscores zolang de determinismemeting niet gedraaid is

Rogier (notitie 15 §1.3): zonder determinismemeting geen exact cijfer. Home doet dat goed (band). Het Rapport niet: `reportFindings.ts` (~74) schrijft "van 58 naar 64 op een schaal van 100".

- Lees `scoreDisplayMode` (`'decimal' | 'band'`, al in `api/hbaV2.ts:77`).
- Bij `band`: alleen richting en band in woorden, bijvoorbeeld "Licht stijgend over drie maanden; je merk staat op koers." Geen getallen, geen schaal.
- Bij `decimal`: de huidige zin mag blijven.
- Loop ook de andere bevindingen en de summary na op exacte merkscores. De merkverhaal-radar (0–5 per as) valt eronder: toon bij `band` geen cijfers in de bevinding, alleen sterkste/zwakste in woorden.

**Klaar als:** met een tenant op `band` staat nergens op het Rapport een exact merkcijfer of een schaalgetal.

## 5. Lege staat van het Rapport volledig maken

Notitie 15 §2.5: bij te weinig gemeten posts geen grafieken met ruis en geen diagnose die nergens op leunt. Nu:

- **Engagement en "Verloop over tijd" staan onder de lege-staatmelding.** Toon in de lege staat alleen de melding met de voortgang (dezelfde teller als Home), en verder niets. De rapportgenerator (scope/datums/Genereer) mag blijven als die bruikbaar is zonder metingen.
- **De lege staat knippert kort** tijdens het laden, omdat `hbaProgress` met `null` begint. Maak onderscheid tussen "laden" (toon "Laden…") en "geladen, te weinig data" (toon de lege staat).
- **De drempels moeten gelijk zijn.** Home laat meters zien vanaf 10 gescoorde posts, maar de bolletjesreeks telt naar `targetPosts` (20). Gebruik één drempel voor Home én Rapport, uit één constante. Meld welke waarde je kiest en waarom.

**Klaar als:** een testtenant met 0 posts ziet op het Rapport alleen de melding met de voortgang, zonder flits, en Home en Rapport noemen dezelfde drempel.

---

## Klein, zelfde bestanden — meenemen

- **`/api/home` mag niet stuk gaan op de koppelingenbalk.** `ce_integrations_status_for_client()` (`home-repository.php` ~1138) gooit bij een onbekende tenant een exception, waardoor heel Home een 500 geeft. Zet er een try/catch om; bij een fout blijft `pendingConnections` leeg.
- **Positionering op 0% wordt niet gesignaleerd** (`home-repository.php` ~1102: `> 0 && < 22`). Gebruik dezelfde norm als de frontend (30%, `contentrolBalance.ts`) en neem 0 mee.

## Niet in deze ronde

- Bevindingen bij de overige Rapport-grafieken (trend, dagelijks, tone-radar, sparklines).
- Todo-volgorde volgens de pijplijn en de link "Alles bekijken".
- Het Rapport dat `/home` ophaalt.
- Dode code (`HomeResultSection`, `HomePipelineSection`, `BrandProgressCard`).
- Het ontwerp van het signaalblok (Erik beslist).

Die komen in een volgende ronde.

## Afronden

1. `npm run build` en `php -l` op de gewijzigde PHP-bestanden.
2. Eén commit per punt, zodat Erik kan terugdraaien.
3. Werk de acceptatietabel in `docs/BUILD-NOTES.md` bij.
4. Geef Erik een kort verslag per punt: klaar / gewijzigd / waar je een keuze moest maken.
5. Niet deployen. Staging komt pas bij de volledige case-run, na Eriks go.
