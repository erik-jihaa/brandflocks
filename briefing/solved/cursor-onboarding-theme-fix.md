# Onboarding: nieuwe tenant loopt vast bij Themes

## Het probleem

Account, Sources en Strategy zijn ingevuld, maar er verschijnen geen thema's.
CE-01 draait via de maandag-scheduler (briefing v9.0) — een nieuwe tenant die
vandaag klaar is met instellen, moet dus wachten tot de eerstvolgende maandag
voordat er ooit een thema verschijnt. Dat is waarschijnlijk de kernoorzaak,
niet een bug.

CE-01 heeft wel een webhook naast de schedule-trigger (zie BUILD-NOTES,
2026-06-22: "CE-01 Theme Director n8n (schedule + webhook)"), maar die is
mogelijk niet bereikbaar vanuit de webapp voor een nieuwe tenant.

---

## Diagnose: check de `eva@igis...`-tenant concreet

Loop dit door in de n8n-instance en de database:

### 1. Is er wachtdata, geen bug?
```sql
SELECT id, name, created_at FROM clients WHERE contact_email LIKE 'eva@igis%';
-- Noteer het client_id
SELECT COUNT(*) FROM themes WHERE client_id = '<client_id>';
-- Als dit 0 is: nog nooit een CE-01-run geweest voor deze klant
```

### 2. Heeft CE-01 deze klant ooit gezien?
Check in n8n de executions van de CE-01-flow (schedule-trigger):
zoek naar de meest recente maandag-run en controleer of `<client_id>`
in de klant-loop voorkwam. Als de klant na de laatste maandag-run is
aangemaakt, is dit verwacht gedrag — geen bug.

### 3. Ontbrekende verplichte velden?
CE-01's prompt heeft minimaal nodig: `archetype`, `adversary`,
`default_tone`, `default_angle`, en (na de Objectives-uitbreiding)
minstens één objective. Check:
```sql
SELECT archetype, adversary, default_tone, default_angle,
       default_image_ratio, default_platform
FROM clients WHERE id = '<client_id>';

SELECT COUNT(*) FROM objectives WHERE client_id = '<client_id>';
SELECT COUNT(*) FROM sources WHERE client_id = '<client_id>' AND active = TRUE;
```
Als `archetype` of `adversary` leeg is, of er zijn 0 actieve sources,
kan CE-01 wel draaien maar met een lege of zwakke prompt — dat kan ook
leiden tot 0 gegenereerde thema's als de AI-call zelf faalt op ontbrekende
context (afhankelijk van hoe defensief de prompt is geschreven).

### 4. Faalt de CE-01-call stil?
Check in n8n of de laatste execution voor deze klant een error gaf
(bijvoorbeeld Claude tool-call die niets teruggeeft bij lege sources) of
gewoon succesvol 0 thema's opleverde.

---

## Diagnose-resultaat Eva (29 jul 2026)

| Check | Resultaat |
|-------|-----------|
| **client_id** | `18fad202-7a0d-bac0-7df6-47da8d4ebd89` |
| **contact** | `eva@igisnext.com` (company in DB: iGiSNext) |
| **Oorzaak** | **Wachtdata** — geen themes tot handmatige CE-01-trigger; 17 sources, strategy ingevuld |
| **CE-01 webhook** | ✅ `POST admin/theme-refill` → `themes_created: 1` (week 31, cycle `2026-07-27`) |
| **Home na fix** | `nextAction`: Theme beoordelen · 1 thema proposed |
| **Theme API** | Week 31 zichtbaar, `needsThemeGeneration: false` na generatie |

Handmatige trigger bevestigt: geen stille fout — alleen maandag-scheduler + ontbrekende onboarding-knop.

---

## Structurele fix: handmatige "Start thema's"-trigger in onboarding

Dit is het eigenlijke ontbrekende stuk. Voeg een directe trigger toe zodat
een nieuwe tenant niet hoeft te wachten op de eerstvolgende maandag.

### Waar: Home-scherm, als onderdeel van de nextAction-logica

Als een tenant Account + Sources (≥1 actieve bron) + Strategy (archetype,
adversary) heeft ingevuld, maar nog 0 thema's heeft:

```
[Home-scherm, nextAction-kaart]

Klaar om te beginnen
Je hebt je merk en bronnen ingesteld. Genereer je eerste
themavoorstellen om de contentcyclus te starten.

[Genereer thema's nu]
```

### API: endpoint (live)

```
POST /api/themes/generate        ← primair
POST /api/themes/generate-now    ← alias (zelfde handler)
Body: { week?: "2026-07-27" }   ← optioneel; default = huidige maandag
```

PHP: `web/api/themes-generate.php` → `ce_generate_themes_for_week()` →
`ce_trigger_ce01_theme_director()` met CE-01-webhook uit `ce.local.php`.

### n8n: CE-01 webhook-tak los van de schedule-tak

Controleer of de CE-01-flow al een aparte webhook-entry heeft die
**niet** door de hele klant-loop van de schedule-trigger gaat, maar
direct voor één `client_id` themas genereert. Als dat nog niet zo is:

1. Voeg een `Webhook`-node toe naast de bestaande `Schedule Trigger`
2. Beide monden uit in dezelfde `Get Active Clients`-node, maar de
   webhook-tak filtert op het meegegeven `client_id` in plaats van
   alle actieve klanten te doorlopen
3. De rest van de flow (rationale-generatie, opslaan in `themes`)
   blijft ongewijzigd

**Status jul 2026:** webhook werkt (`trigger: webhook` in response).

### Guard: voorkom dubbele generatie

Als er al proposed themes zijn voor de actieve week, blokkeer opnieuw genereren:

```php
// theme-repository.php — ce_generate_themes_for_week()
if ($proposedThemeCount > 0) {
    throw new InvalidArgumentException('Er staan al theme-voorstellen voor deze week …');
}
```

Theme-pagina toont knop alleen als `meta.needsThemeGeneration === true`.

Horizon-guard (toekomst): knop verbergen als alle horizon-weken themes hebben.

---

## Implementatiestatus (jul 2026)

| Item | Status |
|------|--------|
| Default week zonder planning (`ce_default_active_theme_week`) | ✅ |
| Home todo + kaart "Genereer thema's nu" | ✅ `HomePage.tsx` |
| Theme-pagina knop "Genereer theme-voorstellen" | ✅ |
| `POST /api/themes/generate` (+ alias `generate-now`) | ✅ |
| CE-01 webhook per client_id | ✅ getest Eva |
| Guard dubbele generatie (zelfde week) | ✅ jul 2026 |
| Objectives verplicht in Home-guard | 🔲 backlog (CE-01 kan zonder) |
| Horizon vol = knop verbergen | 🔲 backlog |

---

## Checklist

- [x] Diagnose uitgevoerd op `eva@igis...`-tenant (stappen 1-4 hierboven)
- [x] Vastgesteld: **wachtdata** (verwacht) — geen stille fout
- [x] `POST /api/themes/generate` endpoint (alias `generate-now`)
- [x] n8n CE-01: webhook-tak voor losse client_id (live getest)
- [x] Home-scherm: kaart "Genereer thema's nu" bij 0 thema's + setup klaar
- [x] Guard tegen dubbele generatie per week
- [x] `eva@igis...`-tenant: thema's handmatig getriggerd — **1 theme live** (week 31)
