# Cursor-brief: Strategy — brand_goal categorie + LLM-probleemvragen

**Prioriteit: #1 · uitvoeren woensdag 29 juli 2026**

Twee velden op het Strategy-scherm die samen de basis leggen onder de onboarding van de eerste klant. Beide staan in `product-vision.md` als openstaand gat (vraag 1, 3, 5 en 9). Zonder deze velden mist de kickoff-sessie zijn belangrijkste twee gespreksonderwerpen en moet er met een workaround in `writing_guide` worden gewerkt.

**In BUILD-NOTES:** opnemen bovenaan § Volgende bouwvolgorde, vóór het huidige punt 12.

---

## 1. `brand_goal` — categorie naast vrije tekst

**Probleem:** `brand_goal` is nu vrij tekstveld. Levert "meer zichtbaarheid" op — een wens, geen doelstelling. En CE-07 weet niet waarvóór het meet, dus het rapport kan niet zeggen of iets werkt.

### Datamodel

Nieuwe kolom op `clients`:

```sql
ALTER TABLE clients
  ADD COLUMN brand_goal_category VARCHAR(32) NULL AFTER brand_goal;
```

Toegestane waarden (één hoofddoel):

| Waarde | Label (UI) |
|---|---|
| `awareness` | Naamsbekendheid |
| `leads` | Leads |
| `sales` | Verkoop |
| `recruitment` | Recruitment |
| `community` | Community bouwen |
| `service` | Klantenservice |
| `thought_leadership` | Thought leadership |
| `brand_preference` | Merkvoorkeur |

Optioneel in dezelfde migratie: `brand_goal_category_secondary` (zelfde enum, nullable) voor één secundair doel. Niet meer dan twee — anders is het geen keuze.

### UI (Strategy-scherm)

- Pulldown **Hoofddoel** boven het bestaande `brand_goal`-tekstveld.
- Bestaande vrije tekst blijft, met aangepaste placeholder: *"Hoe ziet succes er concreet uit? Bijv. 2 aanvragen per maand vanuit content."*
- Pulldown is verplicht bij nieuwe klanten; bestaande klanten met lege waarde blijven werken (nullable).

### Doorwerking

- **CE-01 prompt:** hoofddoel expliciet meegeven — awareness stuurt naar breed herkenbare thema's, leads naar probleem-oplossende thema's.
- **CE-03 prompt:** meenemen in Stap A (rationale) en Stap D (zelfcontrole).
- **CE-07 / Report:** KPI's ordenen op basis van categorie. Awareness → bereik/impressies/volgersgroei vooraan; leads → clicks/formulieren; sales → conversies; community → reacties/mentions. Rest dimmen, niet verbergen. *Mag in een tweede stap — het veld zelf is de blocker.*

---

## 2. LLM-probleemvragen — nieuw veld

**Doel:** 3-5 concrete probleemvragen die de doelgroep aan ChatGPT/Perplexity/Claude stelt. Zie `product-vision.md` § LLM-vindbaarheid voor de achtergrond.

### Datamodel

```sql
ALTER TABLE clients
  ADD COLUMN llm_problem_questions TEXT NULL AFTER brand_goal_category;
```

Opslag: JSON-array van strings, of één tekstveld met één vraag per regel. **Voorkeur: JSON-array** — dan is elke vraag later individueel adresseerbaar voor de "Do LLM's find us?"-check in CE-07 (briefing 4.5.6), zonder opnieuw te hoeven parsen.

### UI (Strategy-scherm)

- Eigen sectie onder Strategy: **Vragen die je doelgroep aan AI stelt**.
- 3 tot 5 regels, dynamisch bij te voegen (patroon zoals research-URL's op Fastlane).
- Hint eronder: *"Geen categorie-vragen ('wat is een goed bureau voor X') maar de vraag onder het probleem ('hoe zorg ik dat kennis niet in hoofden van medewerkers blijft')."*
- Placeholder-voorbeelden uit `product-vision.md` regel 260-263.

### Doorwerking

**CE-01 (Theme Director)**
Neemt de vragen mee bij thema-selectie. Per planningshorizon van vier weken **minimaal één thema** dat een van de probleemvragen direct beantwoordt — als inhoudelijk artikel, niet als merkpost. Leg vast wélke vraag bij het thema hoort (kolom `themes.source_llm_question` of index in het array), zodat het traceerbaar blijft.

**CE-03 (Artikel, Stap A)**
Als het thema uit een probleemvraag komt, begint de rationale expliciet met die vraag:

> "Dit artikel beantwoordt de vraag: [probleemvraag]. Vanuit het archetype van het merk is de invalshoek: [rationale]."

### Migratiepad workaround

Klanten waarbij de vragen tijdelijk onderaan `writing_guide` zijn geparkeerd (onder het kopje *"Vragen die onze doelgroep aan AI stelt:"*): handmatig overzetten naar het nieuwe veld en uit de schrijfwijzer verwijderen, anders staan ze dubbel in de prompt.

---

## Definition of done

- [ ] Migratie gedraaid op productie
- [ ] Beide velden zichtbaar en opslaanbaar op `/strategy`
- [ ] Waarden komen terug in `GET /api/strategy`
- [ ] CE-01 prompt bevat hoofddoel + probleemvragen (verifieerbaar in n8n-run)
- [ ] CE-03 Stap A gebruikt de probleemvraag als startpunt wanneer aanwezig
- [ ] Bestaande klanten zonder waarden blijven foutloos werken
- [ ] Onboarding-draaiboek bijwerken: workaround-blok in blok A vervangen door de echte velden

---

## Wat dit ontgrendelt

- Kickoff-sessie kan zoals bedoeld starten bij de probleemvragen, zonder uitleg over een workaround.
- Verkoopverhaal "we schrijven gericht op de vragen die je doelgroep aan AI stelt" wordt waar in plaats van bijna-waar.
- Fundament voor de "Do LLM's find us?"-check in CE-07: de ingevulde vragen zijn dan direct de testvragen.
- Rapport kan KPI's koppelen aan het doel — de stap van *meten* naar *beoordelen*.

**Gerelateerd:** `product-vision.md` (§ LLM-vindbaarheid, vragen 1/3/5/9), `onboarding-eerste-klant.md` (blok A), BUILD-NOTES § Productvisie-backlog.
