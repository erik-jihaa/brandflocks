# Voorstel: eenmalige onboarding-run (backlog #16)

**Doel:** zodra een nieuwe tenant Sources + Strategy heeft ingevuld, in één
actie de hele rolling agenda vullen — thema's, research én artikelen over
de volledige `planning_horizon_weeks` — in plaats van te wachten op de
maandag-scheduler die per week maar één fase ophoogt.

Dit is geen nieuw idee — het staat al in `docs/BUILD-NOTES.md` § Onboarding
programma (#16) en § Sources onboarding. Dit brief maakt het concreet: wat
er vandaag al werkt, wat de exacte technische lacune is, en hoe je die
dicht met een minimale wijziging in bestaande code.

---

## 1. Wat er vandaag al bestaat (hergebruiken, niet herbouwen)

| Onderdeel | Bestand | Status |
|---|---|---|
| Multi-week theme-refill | `web/api/admin-theme-refill.php` | ✅ Accepteert al een array `weeks` en roept `ce_trigger_ce01_theme_director($clientId, $cycleWeeks)` aan met meerdere weken tegelijk. |
| CE-01-webhook met refill-payload | `ce_trigger_ce01_theme_director()` in `theme-repository.php:1836` | ✅ Stuurt `refill_cycle_weeks` (array) + `proposal_count` naar de n8n-webhook. |
| n8n CE-01 cascade naar CE-02/CE-03 | `scripts/generate-ce01-workflow.py`, nodes "Get Themes For CE-02" → "Trigger CE-02 Phase" → "Get Slots For CE-03" → "Trigger CE-03 Phase" | ✅ Bestaat en werkt, inclusief `auto_publish`-vlaggen voor klanten met `publish_mode = automatic`. |
| Zelfde-week self-service trigger | `POST /api/themes/generate` (+ alias `generate-now`) → `ce_generate_themes_for_week()` | ✅ Wat de knop "Genereer thema's nu" op Home/Theme gebruikt. |

Conclusie: de bouwstenen voor "meerdere weken thema's genereren" en "na
thema's automatisch research + artikel triggeren" bestaan **allebei al**,
apart. Het ontbrekende stuk is dat ze nooit voor dezelfde run worden
gecombineerd.

---

## 2. De exacte lacune

In `scripts/generate-ce01-workflow.py` bepaalt de node **"Route After
Client"** → **"If Webhook"** / **"If Schedule"** wat er na het aanmaken van
thema's gebeurt:

```
"If Webhook": { true → "Respond OK" (stopt hier)
"If Schedule": { true → "Get Themes For CE-02" → … → CE-02/CE-03-cascade
```

**De CE-02/CE-03-cascade draait alleen als `trigger === 'schedule'`** — dus
alleen bij de wekelijkse maandag-cron. Elke webhook-aanroep (inclusief
`admin-theme-refill.php` en de self-service "Genereer thema's nu"-knop)
stopt na het aanmaken van de thema's zelf en respondeert direct. Dit is
geverifieerd door de node-connecties in het gegenereerde workflow-JSON te
lezen, niet aangenomen.

**Praktisch gevolg:** `POST /api/admin/theme-refill` met bijvoorbeeld 4
weken maakt vandaag al 4 weken aan thema-voorstellen aan — maar er wordt
voor geen van die weken automatisch research of een artikel geschreven.
Dat gebeurt nu pas als de wekelijkse scheduler er later overheen loopt,
één week per keer. Voor een kickstart die "de hele agenda in één klap
vult" is dat te traag — precies het probleem dat #16 beschrijft.

---

## 3. Voorstel: één vlag, geen nieuwe workflow

**Niet doen:** een aparte n8n-workflow of een tweede kopie van de
CE-02/CE-03-cascade bouwen. Die logica bestaat al en werkt (incl.
`automatic`-publish-detectie); dupliceren betekent twee plekken die uit
elkaar kunnen lopen.

**Wel doen:** de bestaande gate verbreden met een expliciete `cascade`-vlag,
zodat een kickstart-run dezelfde cascade gebruikt als de scheduler, zonder
dat het gedrag van de bestaande self-service knop of de gewone
`admin-theme-refill`-aanroepen verandert.

### 3.1 n8n — `generate-ce01-workflow.py`

In `Parse Run Input` (regel ~359) een veld toevoegen:

```js
const cascade = Boolean(body.cascade ?? false);
return {
  json: {
    client_id: String(clientId || '').trim(),
    trigger: isWebhook ? 'webhook' : 'schedule',
    refill_cycle_weeks: refillCycleWeeks,
    proposal_count: proposalCount,
    cascade,
  },
};
```

In `If Schedule` (regel ~560) de conditie verbreden van
`trigger == 'schedule'` naar `trigger == 'schedule' OR cascade == true`
(n8n IF-node: combinator `or`, twee condities).

Effect: de scheduler-tak verandert niet (stuurt geen `cascade` mee → blijft
`false` → gedrag identiek). De webhook-tak cascadeert **alleen** als de
aanroeper expliciet `cascade: true` meestuurt.

### 3.2 PHP — nieuwe parameter op de trigger-functie

`ce_trigger_ce01_theme_director()` in `theme-repository.php` krijgt een
vierde, optionele parameter:

```php
function ce_trigger_ce01_theme_director(
    string $clientId,
    array $refillCycleWeeks = [],
    ?int $proposalCount = null,
    bool $cascade = false,
): array {
    // ...
    $payload = ['client_id' => $clientId];
    if ($weeks !== []) {
        $payload['refill_cycle_weeks'] = $weeks;
        $payload['proposal_count'] = max(1, $proposalCount ?? ce_theme_proposal_count());
    }
    if ($cascade) {
        $payload['cascade'] = true;
    }
    // ...
}
```

Bestaande aanroepen (`ce_generate_themes_for_week()`,
`admin-theme-refill.php`) blijven ongewijzigd werken — `$cascade` default
`false`, dus geen gedragswijziging tenzij een aanroeper het expliciet aanzet.

### 3.3 Nieuw endpoint: kickstart

`POST /api/onboarding/kickstart` (nieuw bestand, zelfde patroon als
`admin-theme-refill.php`):

```php
require_once __DIR__ . '/theme-repository.php';

// auth: tenant-sessie (ingelogde klant) OF CE_RESET_TOKEN (admin/support)
$clientId = ce_require_client_id($pdo); // of ce_reset_token_valid() + body clientId

$horizonWeeks = ce_client_planning_horizon_weeks($pdo, $clientId); // clients.planning_horizon_weeks, default 4
$cycleWeeks = ce_upcoming_cycle_weeks($horizonWeeks); // huidige + komende N-1 maandagen

$result = ce_trigger_ce01_theme_director($clientId, $cycleWeeks, null, cascade: true);
```

Auth bewust **tenant-sessie primair**: dit moet ook door de klant zelf (of
Eva/redacteur) te starten zijn vanaf Home, niet alleen door een admin met
reset-token — anders is het geen zelfstandige onboarding-stap maar weer een
handmatige actie voor jou.

---

## 4. Eenmalig-guard

Zonder guard kan iemand die twee keer op "Sources opslaan" klikt (of twee
keer op een toekomstige "Start planning"-knop) per ongeluk **dubbele**
CE-01→CE-02→CE-03-runs triggeren — dubbele Claude-kosten, dubbele thema's.

Nieuwe kolom, zelfde auto-schema-patroon als de rest van de API
(`ce_table_has_column` + `ALTER`, geen aparte migratie-afhankelijkheid):

```php
// in ce_ensure_account_columns() of een nieuwe ce_ensure_onboarding_columns()
'onboarding_kickstart_at' => 'DATETIME NULL',
```

Gedrag:

- Vóór de kickstart-call: als `onboarding_kickstart_at` al gezet is, weiger met een duidelijke fout ("Onboarding-run is al gestart op {datum}") — tenzij `force: true` wordt meegestuurd (voor herstelscenario's, zelfde geest als `admin-theme-refill.php`'s `force`-parameter).
- Na een geslaagde trigger: `onboarding_kickstart_at = NOW()`.

---

## 5. Trigger-momenten (UX)

Twee paden, niet één — zodat het ook werkt als de automatische trigger
gemist wordt:

1. **Automatisch:** na het opslaan van de eerste actieve bron op
   `/sources`, als Strategy al minstens `archetype` + `adversary` heeft én
   `onboarding_kickstart_at` nog leeg is. Toon daarna een duidelijke
   statusmelding ("We vullen je planning — dit duurt een paar minuten"),
   geen stille achtergrondactie.
2. **Handmatig:** knop **"Start planning"** op Home, zichtbaar zodra
   Sources + Strategy klaar zijn maar de agenda nog leeg is (hergebruikt de
   bestaande `nextAction`-heuristiek in `ce_build_home_todos()`
   in `home-repository.php`).

Na een geslaagde run: Home `nextAction` wijst naar Theme ("N thema's om te
beoordelen").

---

## 6. Openstaande beslissing — theme-status vóór CE-02

De bestaande cascade-query (`Get Themes For CE-02`) selecteert thema's met
status `IN ('proposed', 'draft', 'submitted')` — dus **zonder** dat een
mens ze eerst hoeft te "liken" of expliciet in te dienen via Submit Theme.
Dat is kennelijk al zo voor de wekelijkse scheduler-tak (die dezelfde query
gebruikt), maar bij een kickstart die in één klap meerdere weken vult,
betekent dit dat er tussentijds geen redacteur meer naar de
thema-voorstellen kijkt vóór CE-02/CE-03 ermee aan de slag gaan.

Twee opties, allebei technisch klein:

- **A — alles automatisch doorzetten.** Consistent met hoe
  `manual_without_review` al werkt: snelheid boven controle, passend bij
  het "snel op gang"-gevoel dat Light/Medium-tiers nastreven
  (`briefing/onboarding-master.md` § 1). Geen extra code nodig — dit is het
  gedrag zoals hierboven beschreven.
- **B — alleen week 1 cascadeert.** Latere horizon-weken blijven op
  `proposed` staan tot de redacteur ze via Theme beoordeelt en submit; pas
  dan pakt de gewone rolling flow ze op. Vereist een kleine aanpassing:
  `cascade` alleen laten gelden voor de eerste meegegeven week in
  `refill_cycle_weeks`.

**Aanbeveling:** optie A voor Light/Medium-onboarding (de klant wil juist
snel iets zien), met de kanttekening dat dit ook geldt voor
`manual_with_review`-tenants — een kickstart zet dan artikelen tot en met
"pending review" klaar, niet live. Alleen bij `manual_without_review` zou
dit artikelen direct publiceren; overweeg daarom **cascade te beperken tot
research + schrijven (CE-02/CE-03), nooit tot automatisch publiceren**,
tenzij de klant zelf al bewust `automatic` heeft gekozen. Dat sluit aan bij
de bestaande `auto_publish`-detectie in `PREPARE_CE03_TRIGGERS_JS`, die al
alleen bij `publish_mode = automatic` de publish-vlag meestuurt — dus dit
werkt al goed vanzelf, geen aparte guard nodig.

---

## 7. Kostenbeheersing

Eén kickstart-run over bijvoorbeeld 4 weken horizon triggert in één klap
4× CE-01 (al gedaan vóór cascade) + tot 4× CE-02 + tot 4× CE-03 — een
kortstondige piek in Claude-aanroepen t.o.v. de normale 1×/week cadans.

- Hergebruik de bestaande kostenraming (`web/api/tenant-cost-repository.php`,
  `/support/costs`) om dit zichtbaar te maken vóór je de kickstart-knop
  live zet voor klanten — geen nieuwe berekening nodig, wel een
  aandachtspunt.
- Overweeg een harde cap in `ce_upcoming_cycle_weeks()`, bijvoorbeeld nooit
  meer dan 4 weken in één kickstart cascaderen, ook als
  `planning_horizon_weeks` hoger staat — voorkomt dat een klant met horizon
  12 in één klap 12× de pipeline aanslingert.

---

## 8. Testplan (vóór dit bij een echte klant aan staat)

1. **Sandbox-tenant eerst**, nooit direct op een live klant — zie de
   mass-delete-les in `docs/BUILD-NOTES.md` § Open risico's.
2. Test met `cascade: false` (default) → gedrag moet identiek zijn aan nu:
   alleen thema's, geen research/artikelen.
3. Test met `cascade: true`, 2 weken horizon, `publish_mode =
   manual_with_review` → verwacht: thema's + research + artikelen tot
   "pending review", niets gepubliceerd.
4. Zelfde test met `publish_mode = automatic` + gevulde `client_images` →
   verwacht: artikelen automatisch gepubliceerd, zichtbaar op de test-site.
5. Dubbele trigger binnen dezelfde minuut → tweede aanroep moet de
   eenmalig-guard raken (§ 4), geen dubbele runs.
6. Kosten-check: vergelijk `scrape_runs`/Claude-aanroepen vóór en na een
   4-weken kickstart tegen de schatting uit `/support/costs`.

---

## 9. Samenvatting wijzigingsmatrix

| Bestand | Wijziging |
|---|---|
| `scripts/generate-ce01-workflow.py` | `cascade`-veld in `Parse Run Input`; `If Schedule`-conditie uitbreiden met `OR cascade == true` |
| `web/api/theme-repository.php` | `ce_trigger_ce01_theme_director()` — vierde parameter `bool $cascade = false` |
| `web/api/onboarding-kickstart.php` | Nieuw endpoint `POST /api/onboarding/kickstart` |
| `web/api/account-repository.php` (of nieuw `onboarding-repository.php`) | Kolom `clients.onboarding_kickstart_at` (auto-schema) |
| `web/api/home-repository.php` | `ce_build_home_todos()` — knop "Start planning" tonen zodra Sources+Strategy klaar zijn en agenda leeg is |
| `web/api/sources-repository.php` (of de save-handler) | Trigger kickstart na eerste actieve bron, als guard nog leeg is |
| `docs/BUILD-NOTES.md` | Toevoegen na implementatie; #16 van backlog naar "✅ live" |
| Deploy | `./scripts/push-ce01-n8n.sh` (workflow-JSON opnieuw genereren + pushen) |

Geen van deze wijzigingen raakt de bestaande scheduler- of self-service-paden
aan — alles is additief achter een expliciete vlag.
