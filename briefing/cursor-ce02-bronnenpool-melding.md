# CE-02 — niet verzinnen bij een lege bronnenpool, maar een melding tonen

**Vervolg op** `briefing/cursor-ce02-invalshoeken.md`.
**Aanleiding:** staging-tenant igisnext toonde 7 van de 7 researchitems als "Eigen aanzet — geen
bron, wel een richting." — CE-02 had vrijwel niets echts om aan te verankeren en vulde de hele set
zelf in plaats van dat te melden.
**Raakt:** `scripts/generate-ce02-workflow.py`, `JSON/CE-02 - Research & Inspiratie.json`,
`ResearchItemRow.tsx` (of het scherm dat de researchset toont), `research_items`.

---

## 1. Twee bugs, één principe

Bij onderzoek naar de melding bleken er twee dingen mis:

**Bug A — de "gegrond"-check telt verkeerd.** De huidige check in `PREPARE_INSERTS_JS`:

```js
const grounded = out.filter(row => row.url.trim() !== '' || row.source_id != null).length;
if (grounded < 2) { throw new Error(...); }
```

Telt een item als gegrond zodra er een `source_id` aan hangt — ook als de bijbehorende `url` leeg
is. Nieuwsbrief/mailbox-kandidaten worden altijd met `link: ''` aangemaakt, dus een item dat aan
zo'n kandidaat verankerd is haalt de check, maar toont in de UI alsnog als "Eigen aanzet" (die
render puur op `url` leeg/niet-leeg). Zo kan een run "geldig" zijn volgens de backend en toch 7 van
de 7 bronloos ogen.

**Fix:** tel alleen items met een niet-lege `url` als gegrond:

```js
const grounded = out.filter(row => row.url.trim() !== '').length;
```

**Bug B — de ondergrens staat op 2, de briefing zei 3.** Verhoog terug naar `grounded < 3` (§8 van
`cursor-ce02-invalshoeken.md`).

Beide zijn nodig, maar lossen het onderliggende probleem niet op: ze zorgen er alleen voor dat een
run met te weinig bronnen een foutmelding gooit in n8n — niet dat de redacteur iets bruikbaars te
zien krijgt. Vandaar principe hieronder.

## 2. Het principe: geen fake set, een duidelijke melding

Zelfde patroon als `cursor-aandachtspunten-onderbouwing.md` §1 koos voor CE-03 bij lege research:
**een tool die te weinig heeft om op te bouwen, moet dat zeggen — niet doen alsof.** Claude onder
druk zetten om vijf of zeven invalshoeken te vullen terwijl er nauwelijks kandidaten zijn, levert
precies dit op: een set die op het eerste gezicht compleet oogt maar inhoudsloos is.

**Verplaats de check naar vóór de Claude-call, niet erna.** Na de stap "Collect Candidates":

```js
const realCandidates = candidates.filter(c => (c.url || '').trim() !== '').length;
if (realCandidates < 3) {
  // Geen Claude-call. Zet het thema/slot op een status die de redacteur ziet.
  return { status: 'insufficient_sources', real_candidates: realCandidates };
}
```

Dit voorkomt niet alleen de zwakke output, het bespaart ook de LLM-call.

## 3. Wat de redacteur ziet

Op het researchscherm, in plaats van een lege of half-verzonnen lijst:

> **Te weinig bronnen voor dit thema.** We vonden {realCandidates} bruikbare bron(nen) — te weinig
> om een gevarieerde research op te bouwen. Voeg sources toe bij dit thema, of kies een ander
> thema.
>
> [ Naar Sources ]

- Knop linkt naar de Sources-tab van de tenant.
- Dezelfde melding-stijl als de bestaande "Te weinig research om een artikel op te baseren"-tekst
  uit `cursor-aandachtspunten-onderbouwing.md` §1 — consistent patroon, niet twee verschillende
  soorten waarschuwingen voor hetzelfde probleem.
- Sla dit op als een status op het thema/slot (niet als een losse foutmelding die verdwijnt na
  reload) — de redacteur moet het nog zien als hij een dag later terugkomt.

## 4. Waarom de ondergrens 3 is, en niet 0

Onder de 3 gegronde kandidaten kan CE-02 geen vijf verschillende invalshoeken met een reële bron
vullen — dan is elke "aanvulling" per definitie verzonnen. 3 is ook de bestaande eis uit de
invalshoeken-briefing (§3.2: "minimaal drie ervan moeten op een echte kandidaat-bron rusten").
Verander die 3 niet zonder de invalshoeken-briefing ook bij te werken — ze moeten gelijk blijven.

## 5. Testen

1. Tenant zonder actieve Sources (of met een Sources-tab die je tijdelijk leegmaakt): draai CE-02
   → verwacht de melding, geen Claude-call, geen researchitems.
2. Tenant met 2 actieve bronnen: verwacht dezelfde melding (onder de grens van 3).
3. Tenant met voldoende bronnen (igisnext na sources toevoegen): verwacht een normale set met
   minimaal 3 items met een echte `url`.
4. Controleer specifiek de mailbox-casus: een candidate met `source_id` gevuld en `url: ''` mag
   niet meer meetellen als gegrond (test met een tenant die alleen een nieuwsbrief-source heeft).

## 6. Acceptatiecriteria

- `grounded`-check in `PREPARE_INSERTS_JS` telt op `url`, niet op `source_id`.
- Ondergrens is 3, consistent met `cursor-ce02-invalshoeken.md`.
- Bij minder dan 3 echte kandidaten wordt Claude niet aangeroepen; het thema/slot krijgt een
  zichtbare, blijvende melding met een link naar Sources.
- Geen enkele CE-02-run levert nog een set op waarin alle items "Eigen aanzet" zijn zonder dat de
  redacteur een duidelijke reden en vervolgstap ziet.
