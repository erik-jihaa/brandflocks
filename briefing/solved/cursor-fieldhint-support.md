# Cursor-briefing — uitlegballetjes in mensentaal + "Help me verder"

**Status:** voorstel — nog niet gebouwd
**Datum:** aug 2026
**Raakt:** `FieldHint.tsx`, nieuw `config/fieldHelp.ts`, `SupportChatBox.tsx`, `SupportPage.tsx`,
`support-chat-repository.php`
**Zie ook:** `briefing/support-plan.md` (waarom), `briefing/cursor-integrations-ordening.md`

---

## 1. Wat we bouwen

Drie dingen, in deze volgorde:

1. **Eén centrale copy-registry** voor alle uitlegteksten (`fieldHelp.ts`), zodat teksten niet
   verspreid in JSX staan en zonder codewijziging bij te werken zijn.
2. **`FieldHint` uitgebreid** met een optionele stap-voor-stap en een knop `Help me verder`.
3. **De support-chat opent met context**: veldsleutel in de URL, vraag al ingevuld.

De bestaande `FieldHint`-aanroepen blijven werken — alle nieuwe props zijn optioneel.

---

## 2. Copy-registry

Nieuw bestand `frontend/src/config/fieldHelp.ts`:

```ts
export interface FieldHelp {
  key: string          // 'wordpress_app_password' — ook de sleutel richting de chat
  what: string         // wat is dit + waarom, 1–2 zinnen, geen jargon
  where?: string       // waar vind je het, in één zin
  steps?: string[]     // alleen bij de zware velden; per stap één handeling
  looksLike?: string   // vorm van de waarde: 'abcd efgh ijkl mnop'
  reassure?: string    // wegnemen van angst, alleen waar die er is
}

export const FIELD_HELP: Record<string, FieldHelp> = { /* zie §5 */ }
```

Waarom een registry en geen tekst in JSX: de teksten zijn een supportproduct dat vaker verandert
dan de schermen. Ze horen bij elkaar zodat je toon en woordkeus kunt vergelijken. Zelfde
redenering als `integrations.ts`.

---

## 3. `FieldHint` uitbreiden

```tsx
type FieldHintProps = {
  children?: ReactNode          // blijft werken voor losse teksten
  helpKey?: string              // pakt tekst + stappen uit FIELD_HELP
  position?: 'above' | 'below'
  tone?: FieldHintTone
}
```

Gedrag bij `helpKey`:

- **Dicht:** ongewijzigd — alleen het beige i-bolletje van 11px.
- **Open:** `what` als lopende tekst. Dan `looksLike` als die er is, in `text-ce-beige`, met het
  voorbeeld in monospace. Dan `where`. Dan bij `steps` een genummerde lijst die **één stap tegelijk**
  toont met `Volgende` / `Vorige` en een teller `Stap 2 van 4`.
- **Onderaan altijd:** `Kom je er niet uit? → Help me verder`

Stijl volgt het bestaande scherm: `text-[13px] leading-[18px]`, `px-[30px]`, `mt-[9px]`. De
`Help me verder`-link krijgt `text-ce-green-text`, geen PillButton — het is hulp, geen actie.

Toegankelijkheid: de stappenteller in een `aria-live="polite"`, zodat een schermlezer de wissel
meekrijgt.

**Eén stap tegelijk is opzettelijk.** Vier stappen onder elkaar leest als een handleiding en
overweldigt precies de klant voor wie dit bedoeld is.

---

## 4. "Help me verder" → chat met context

**Frontend:**

- De link navigeert naar `/support?ask={helpKey}&from={pathname}`.
- `SupportPage` leest de params (`useSearchParams`, al in gebruik) en geeft ze door aan
  `SupportChatBox`.
- `SupportChatBox` krijgt props `askKey?: string` en `fromPath?: string`. Bij een `askKey` zet hij
  het invoerveld vóór met een leesbare zin uit `FIELD_HELP[askKey].what` — **niet verzenden**, de
  klant houdt de regie en kan de vraag aanvullen:

  > *Ik sta op Integrations bij "WordPress app-wachtwoord" en kom er niet uit.*

- Scroll naar de chat en focus het invoerveld.
- Onbekende `askKey` → gewoon een lege chat, geen fout.

**Backend:** `POST /api/support-chat` accepteert een optioneel veld `context`:

```json
{ "fieldKey": "wordpress_app_password", "screen": "/integrations" }
```

Dit gaat **niet** in de system prompt (injection-regel uit BUILD-NOTES blijft staan), maar als
korte, server-samengestelde regel bovenaan het **eerste user-message**. De server bouwt die zin
zelf uit een whitelist van bekende `fieldKey`-waarden — nooit uit vrije invoer van de client:

```php
$known = array_keys(ce_support_chat_field_labels());
if (!in_array($fieldKey, $known, true)) { $fieldKey = null; }
```

**Loggen:** alleen `fieldKey` + timestamp + `client_id` in `support_chat_usage` (nieuwe kolom
`last_field_key`) of een aparte tabel `support_field_hits`. Geen berichtinhoud. Dit is signaal 2
uit het supportplan: welke velden kosten de meeste hulp.

---

## 5. De teksten

Letterlijk over te nemen. Nederlands, `je`-vorm, geen "gewoon" of "eenvoudig".

### `wordpress_app_password` — WordPress app-wachtwoord

> **what:** Dit is níét je gewone wachtwoord. WordPress kan een apart wachtwoord maken speciaal voor
> programma's zoals Content Engine. Zo hoef je je eigen wachtwoord nergens in te vullen, en kun je
> deze toegang later intrekken zonder dat er verder iets stukgaat.
>
> **looksLike:** vier groepjes van vier tekens, zoals `abcd efgh ijkl mnop`
>
> **reassure:** Je website verandert hier niet van. Je geeft alleen toestemming.
>
> **steps:**
> 1. Log in op je eigen website, op het scherm waar je normaal een pagina aanpast.
> 2. Klik links op **Gebruikers** en dan op je eigen naam.
> 3. Scroll helemaal naar beneden tot je **Application Passwords** ziet — die zit onderaan
>    verstopt.
> 4. Typ bij de naam **Content Engine** en klik op de knop. Het wachtwoord verschijnt één keer:
>    kopieer het meteen en plak het hier.

### `wordpress_user` — WordPress-gebruikersnaam

> **what:** De naam waarmee jij inlogt op je eigen website. Content Engine plaatst artikelen namens
> deze gebruiker.
>
> **where:** Staat op je profielpagina, achter **Gebruikersnaam**. Meestal is dit niet je
> e-mailadres.

### `joomla_api_token` — Joomla API-token

> **what:** Een sleutel waarmee Content Engine artikelen op je Joomla-site mag zetten. Joomla maakt
> hem voor je aan; je hoeft niets te verzinnen.
>
> **reassure:** Werkt vanaf Joomla 4. Weet je niet welke versie je hebt? Klik op **Help me verder** —
> dan zoeken we het samen uit.
>
> **steps:**
> 1. Log in op het beheerscherm van je Joomla-site.
> 2. Ga naar **System** → **Plugins** en zoek op *token*. Zet **User – Joomla API Token** en
>    **API Authentication – Token** allebei aan.
> 3. Ga naar **Users**, klik op je eigen gebruiker, en open het tabblad **Joomla API Token**.
> 4. Klik op het oogje om de sleutel zichtbaar te maken en plak hem hier.

### `site_ftp` — FTP-gegevens (statische site)

> **what:** Bij een site zonder beheerscherm zetten we de bestanden er rechtstreeks op. Daar zijn
> vier dingen voor nodig, en die heeft de bouwer van je site.
>
> **where:** Vraag je websitebouwer of hostingpartij om host, gebruikersnaam, wachtwoord en het pad
> naar de website — vaak `/httpdocs` of `/public_html`.
>
> **reassure:** Weet je niet wie dat is? Klik op **Help me verder**, dan zoeken we het samen uit.

### `analytics_property_id` — GA4 Property ID

> **what:** Het nummer van je website in Google Analytics. Daarmee weet Content Engine welke site
> hij moet uitlezen voor je Report.
>
> **looksLike:** een getal van negen cijfers, zoals `123456789` — niet `G-XXXXXXX`, dat is iets
> anders
>
> **steps:**
> 1. Ga naar analytics.google.com en kies bovenin de juiste website.
> 2. Klik linksonder op het tandwiel (**Beheer**).
> 3. In de middelste kolom staat **Property-instellingen**; de **Property-ID** staat rechtsboven.

### `linkedin_organization_urn` — LinkedIn bedrijfspagina-ID

> **what:** Vul je dit in, dan verschijnen posts op je bedrijfspagina in plaats van op je
> persoonlijke profiel. Laat je het leeg, dan post je als jezelf — dat mag ook.
>
> **looksLike:** alleen cijfers, zoals `12345678`
>
> **where:** Open je bedrijfspagina op LinkedIn in de beheerdersweergave. In de adresbalk van je
> browser staat een reeks cijfers na `/company/`. Dat nummer is het.
>
> **reassure:** Hiervoor moet LinkedIn je eerst toestemming geven om namens de pagina te posten.
> Lukt het niet meteen? Dat ligt niet aan jou — klik op **Help me verder**.

### `mailbox_imap_host` — IMAP-host

> **what:** Het adres van de mailserver waar je nieuwsbrieven binnenkomen. Content Engine leest die
> mails mee als inspiratiebron.
>
> **looksLike:** `mail.jouwbedrijf.nl` of `imap.gmail.com`
>
> **reassure:** We vullen hem meestal automatisch goed in zodra je je e-mailadres invult. Klopt het
> niet, dan weet je provider het juiste adres — of klik op **Help me verder**.

### `scraper_actor_url` — Apify actor-URL

> **what:** Dit bepaalt welk hulpprogramma de gegevens ophaalt. Voor LinkedIn-profielen staat de
> juiste al ingevuld.
>
> **reassure:** Hier hoef je niets aan te veranderen. Wil je iets anders laten ophalen? Klik op
> **Help me verder**, dan zetten we het samen goed.

### Statusteksten (niet in `FieldHint`, wel dezelfde toon)

| Situatie | Nu | Straks |
| --- | --- | --- |
| Gekoppeld | `Gekoppeld` | `Gekoppeld als Eva de Jong — dit is gelukt.` |
| Verlopen | `Token expired` | `De koppeling is verlopen. Dat gebeurt vanzelf na een tijd; het ligt niet aan jou. Eén klik op Opnieuw koppelen en je bent weer bij.` |
| Aan maar niet gekoppeld | `Nog niet gekoppeld` | `LinkedIn staat aan, maar de koppeling ontbreekt nog — er wordt dus nog niets geplaatst. Koppelen doe je hier.` |
| Test mislukt | `Fout bij verbinden` | `De verbinding met je website lukte niet. Meestal klopt het app-wachtwoord niet meer. Maak een nieuw wachtwoord aan en plak het opnieuw — of klik op Help me verder.` |
| Geen bronnen | `0 bronnen` | `Nog geen bronnen. Content Engine heeft leesvoer nodig voordat hij thema's kan voorstellen — één RSS-feed is al genoeg om te beginnen.` |

---

## 6. System prompt bijwerken

In `ce_support_chat_system_prompt()`, twee wijzigingen.

**A. Feitelijke fout herstellen.** De prompt zegt nu *"WordPress: … invullen bij Account →
Publiceren"*. Dat klopt niet meer sinds het Integrations-scherm. Vervang door:

```
- WordPress/Joomla/statische site: instellen bij Integrations (Settings) → Website.
  WordPress Application Password maak je aan op je eigen site onder Gebruikers → Profiel →
  Application Passwords. Dit is een apart wachtwoord, niet het gewone inlogwachtwoord.
- Google Analytics (GA4): koppelen via Integrations, of via de Analytics-bron op Sources.
```

**B. Toonblok toevoegen**, ná de scope en vóór de veiligheidsregels:

```
HOE JE ANTWOORDT — je praat met iemand die haar vak goed verstaat maar niet thuis is in
technische termen:
- Warm en rustig. Nooit neerbuigend en nooit joviaal.
- Gebruik de woorden "gewoon", "simpelweg", "eenvoudig" of "even" niet. Als het eenvoudig was,
  had ze niet gevraagd.
- Leg eerst uit waaróm iets nodig is, dan pas hoe.
- Noem de plek in gewone taal ("log in op je eigen website"), niet in jargon ("ga naar je
  wp-admin"). Gebruik wél de exacte woorden die op het scherm van de andere partij staan.
- Eén stap per keer. Vraag na een stap of het gelukt is voordat je verdergaat.
- Zeg eerlijk als een veld lastig is. Dat haalt de spanning eruit.
- Vraag nooit om een wachtwoord of sleutel, ook niet gedeeltelijk. Je hoeft de waarde niet te
  zien om te helpen.
- Als iets misgaat: beschrijf wat er waarschijnlijk gebeurde en wat ze nu kan doen. Geef haar
  nooit de schuld.

JIJ LOST HET OP — je bent niet het loket dat doorverbindt. Je beantwoordt de vraag zelf en je
loopt bij een probleem de bekende oorzaken langs (klopt het veld, is het het juiste soort
wachtwoord, is de koppeling verlopen). Doorzetten naar een mens is de uitzondering, niet je
uitweg bij een lastige vraag.

Zet pas door naar support@igisnext.com als een van deze drie geldt:
- de klant vraagt er zelf om — doe dat dan meteen en zonder drempel;
- er is aantoonbaar iets stuk aan onze kant dat de klant zelf niet kan verhelpen (een
  koppeling die als "niet beschikbaar" wordt gemeld, of een serverfout);
- na vier beurten waarin de klant blijft aangeven dat het niet lukt.

Bied het dan opgewekt aan, als volgende stap en niet als falen: "Dit krijgen we hier niet
helder genoeg. Zullen we het doorzetten naar Erik? Dan kijkt hij mee."

Vragen over inhoud en redactie (is dit een goed thema, klopt deze tone of voice) horen bij
Eva; zeg dat er dan bij.
```

De veiligheidsregels blijven ongewijzigd, inclusief de injection-bescherming en het verbod op
code-uitvoering. `max_tokens` van 700 blijft ruim genoeg; wel `~150 woorden` verhogen naar
`~200 woorden`, want stap-voor-stap in gewone taal kost meer ruimte dan een opsomming.

---

## 7. Doorzetten naar een mens

Onder de chat een knop `Stuur mijn vraag door naar Erik`, altijd zichtbaar (niet alleen na twee
beurten — de klant mag altijd kiezen).

`POST /api/support-chat/escalate` mailt naar support@igisnext.com met: tenantnaam en contactpersoon,
het chatverloop, het scherm en `fieldKey` waar het begon, en de koppelstatus uit
`/api/integrations/status`. Bevestiging in beeld:

> Je vraag is onderweg. Erik reageert binnen één werkdag — je hoeft niets meer te doen.

Diezelfde tekst ook per mail naar de klant, zodat ze zwart-op-wit heeft dat het verstuurd is.

---

## 8. Bouwvolgorde

1. `fieldHelp.ts` met de teksten uit §5 + `FieldHint` uitgebreid (`helpKey`, stappen, `Help me verder`).
2. De acht velden uit §5 omzetten naar `helpKey`; statusteksten uit de tabel doorvoeren.
3. System prompt (§6) — kan onafhankelijk, doe dit meteen: de WordPress-verwijzing is nu fout.
4. `/support?ask=` + `context` in de chat-API + `fieldKey`-logging.
5. Escalatieknop + mail.

Stap 1–3 leveren het grootste deel van de winst en raken geen backend-logica.

---

## 9. Acceptatiecriteria

- Elk van de acht velden uit §5 heeft een uitlegballetje met `Help me verder`.
- Klikken op `Help me verder` opent de chat met een ingevulde, nog niet verzonden vraag die het veld
  bij naam noemt.
- De woorden "gewoon", "simpelweg", "eenvoudig" en "even" komen niet meer voor in klantgerichte
  tekst — controleer met een grep over `frontend/src`.
- Bestaande `FieldHint`-aanroepen zonder `helpKey` renderen ongewijzigd.
- Een onbekende of geknoeide `ask`-parameter geeft een lege chat, geen fout en geen invloed op de
  system prompt.
- De chat biedt na twee beurten zonder oplossing zelf een mens aan.
