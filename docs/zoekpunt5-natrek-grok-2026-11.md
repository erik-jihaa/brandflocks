# Zoekpunt 5 — review 13 nov nagetrokken (Grok)

**Datum:** 14 november 2026 · **Opdracht:** `docs/briefings/opdracht-4-zoekpunt5-grok-2026-11-14.md`
**Toetsstuk:** `docs/briefings/review-bouwaddendum-en-hba-2026-11-13.md` §2 (acht beweringen)
**Code:** `erik-jihaa/brandflocks` `main` @ `59acf00` (12 nov `81def5c` of nieuwer, zoals de opdracht toestaat)
**Werkwijze:** elke bewering tegen de code, niet tegen de review en niet tegen de leesronde. Niets gewijzigd; dit bestand is de enige uitvoer.

De review zelf is gedateerd tegen `81def5c`. Waar `main` sindsdien is opgeschoven, staat dat bij het oordeel. Oordeel **bevestigd / weerlegd / deels** geldt voor de code nú (`59acf00`).

---

## 1. `web/api/hba-open-koppelpunten.php` bestaat niet

**Oordeel: weerlegd** (op huidige `main`). Op de review-snapshot `81def5c`: bevestigd.

Het bestand staat in de boom: `web/api/hba-open-koppelpunten.php`. Toegevoegd in `cc9d57d` (13 nov, ná `81def5c`). Inhoud: catalogus met sleutels (`pickup_power_*`, architectuur-signalen, thema-bronnen, CTA-voorrang), alle waarden `null` (regels 13–33). Getter `ce_hba_open_koppelpunt()` regels 36–41.

Geen `require`/`include` van dit bestand elders in `web/api/`. De functies worden nergens aangeroepen. `ce_hba_open_placeholder_message()` in `hba-v2-repository.php:2033` is een ander, wel gebruikt pad (`articles-submit.php`, `publish-repository.php`).

---

## 2. De stap-1-hint is een zoekpatroon op prijswoorden, geen AI-call

**Oordeel: bevestigd.**

`ce_campagne_sell_fit_hint()` in `web/api/campagne-preview-templates.php:472–487`:

- Leest `ce_hba_v2_architecture_context()` en plakt drie velden aan elkaar: `regels`, `verboden_logica`, `wat_bewust_niet`.
- Matcht `preg_match('/afprijs|korting|prijsactie|nooit[^.]{0,40}prijs|geen korting/u', $blob)`.
- Bij treffer: vaste zin *«Let op: dit merk profileert zich normaal niet met prijsacties. Sell kan hier wringen. Je kunt toch doorgaan.»*
- Geen LLM-aanroep in deze functie.

Aangesloten op `GET /campagne-preview/templates` in `web/api/index.php:481–484`.

---

## 3. Copy-modus is gebouwd; een los artikel zonder campagne valt terug op `merkbouw_bewijs`

**Oordeel: bevestigd**, met één interne tegenstrijdigheid (geen weerlegging van de bewering).

Bestand `web/api/copy-modus.php` bestaat. `ce_resolve_copy_modus_for_slot()` (`:72–123`):

1. Geen slot / slot ontbreekt → `['modus' => CE_COPY_MODUS_MERK, … 'source' => 'default']` (`:74`, `:77`, `:86`, `:122`). `CE_COPY_MODUS_MERK` = `'merkbouw_bewijs'` (`:8`).
2. Anders theme `copy_modus` als die geldig is.
3. Anders campagne `template_key` via `ce_copy_modus_from_template_key()`.
4. Anders opnieuw merkbouw-fallback.

Een slot zonder theme-modus en zonder campagne komt dus op merkbouw uit. Dat dekt “los artikel zonder campagne”.

Tegenstrijdigheid (niet de bewering, wél de code): `ce_copy_modus_from_template_key()` (`:21–31`) mapt `''` en `'default'` naar **`sell_activatie`**, niet naar merkbouw. Die tak geldt alleen als er wél een campagne is met lege/default-template. De slot-resolver zonder campagne blijft merkbouw.

---

## 4. Schrijfstijl per doelgroep gaat al mee naar de copy-call, met terugval op een standaardtoon

**Oordeel: deels.**

`ce_build_article_write_directive()` in `web/api/tone-styles.php:264–359`:

- Bij gekozen toon: regel `Tone (doelgroep/kanaal): …` plus de client-stijltekst (`:287–292`).
- Zonder toon: `Schrijfstijl: standaardtoon van het merk (geen aparte doelgroep gekozen).` (`:293–295`).
- Extra: `ce_tone_mandatory_rules()` per toon-sleutel (`:319–322`, `:362–379`); objective-`writingStyle` als die er is (`:308–317`).

Wat de bewering te ruim maakt: er is **geen** eigen veld “voor wie is deze post bedoeld”. De toon is `tone_final` / `tone_suggested` op het artikel (`ce_persist_article_write_directive()` `:397`). Doelgroep ≠ kanaal; het label in de prompt noemt beide in één adem.

---

## 5. Kanaaltabel ingekort; `content_textvorm` nergens geschreven; check op het postvoorstel ontbreekt

Drie deelbeweringen. **Oordeel: deels** (op `59acf00`). Op `81def5c` waren alle drie waar.

### 5a — tabel ingekort t.o.v. addendum §3

**Weerlegd** op huidige `main`. `ce_channel_copy_constraint_catalog()` in `web/api/channel-copy-constraints.php:10–26` is woord-voor-woord de tabel in `bouwaddendum-intelligentielaag-brandflocks-2026-11-10.md` §3 (13 rijen, o.a. LinkedIn-post, X 280 tekens, Instagram 5 hashtags).

Op `81def5c` stonden wél ingekorte zinnen (zelfde keys, kortere tekst). Die stand is de review.

### 5b — `articles.content_textvorm` wordt nergens geschreven

**Weerlegd** op huidige `main`. Schrijvers:

- Artikel-PATCH: `web/api/article-repository.php:559` (`contentTextvorm` → kolom `content_textvorm`).
- Fastlane-insert: `web/api/fastlane-repository.php:290–298`.
- UI: `frontend/src/components/ArticleMetaSidebar.tsx:331–351` (radios `name="content_textvorm"`).
- Ensure-kolom: `channel-copy-constraints.php:449–451`.

Lezen gebeurt o.a. in `mappers.php:1071` en `ce_channel_hard_validation_for_article_row()` `:267`.

Op `81def5c` was schrijven inderdaad afwezig (commit `8d55cd1` erna).

### 5c — check op het postvoorstel ontbreekt

**Deels.** Addendum §3 punt 2 vraagt dezelfde eisen “terugleggen als lichte check op het postvoorstel”.

Aanwezig:

- Zachte, niet-blokkerende hints: `ce_channel_soft_hints_for_proposal()` (`channel-copy-constraints.php:395–428`), gehangen aan artikel-API (`articles.php:76–84`) en campagne-preview (`campagne-preview-repository.php:421`).
- Harde teken-/hashtag-telling: `ce_channel_hard_validation_messages()` (`:186+`). Blokkeert bij submit (`articles-submit.php:68–75`) en publish (`publish-repository.php:1038–1040`). Artikel-UI toont `channelHardIssues` (`ArticlePage.tsx`, `ArticleFeedbackPage.tsx`).

Wat ontbreekt t.o.v. een volledige “zelfde eisen”-check op het **voorstel**: de harde telling loopt op het artikel, niet als poort op het kale postvoorstel; structuur/toon uit de catalogus blijven prompttekst, geen check.

---

## 6. Pickup power is nergens gebouwd; de PCB-component bestaat niet

**Oordeel: deels.**

In heel `web/api/` (en `frontend/src/`): geen functie, klasse of bestand `PCB`, `Proof & Constraint Balance`, of een oppakbaarheidsscore. Geen AI-call of drempelvergelijking voor pickup power.

Wél: drie null-sleutels in `hba-open-koppelpunten.php:16–19` (`pickup_power_low|medium|high`, plus `pickup_maar_drift_driftsignaal`). Die catalogus is niet aangesloten (zie §1).

Dus: geen gebouwde component, geen scoring; wel een lege haak. De review-zin “nergens gebouwd” klopt voor gedrag; “nergens” klopt niet meer letterlijk voor de bestandsboom.

---

## 7. Elke campagnepost is een eigen slot met eigen kanaal en datum; één artikel op meerdere kanalen is niet in het datamodel gevonden

**Oordeel: bevestigd.**

`content_slots` (`web/migrations/brandflocks_baseline.sql:388–417`): één rij, één `publish_date`. Campagne vult per post `campaign_platform` en `publish_date` (`campagne-preview-repository.php:1225–1233` bij insert).

`articles.slot_id` is FK naar `content_slots.id` (`baseline.sql:141–142`), **geen UNIQUE**. Platform van het artikel staat op `articles.platform_final` / `platform_suggested`, niet als lijst. Geen tabel of JSON-kolom “zelfde artikel, N kanalen, N datums/offsets”.

Geen veld `channel_offset` / verschuiving in `web/api/` of migraties. Kanaaldatum = de `publish_date` van díe slot-rij.

Meerdere kanalen = meerdere slots (vaak elk met eigen `article_id`). Dat is het omgekeerde van “één artikel, meerdere kanalen”.

---

## 8. De navigatie is nog de oude CE-navigatie

**Oordeel: weerlegd** (op huidige `main`). Op `81def5c`: bevestigd.

`frontend/src/components/SidebarNav.tsx` nu (`:47–99`): Overzicht (Rapport, Te doen) · Identiteit (Merkarchitectuur, Merktaal) · Kennis (Bronnen, SEO/GEO, Beeldbank) · Publicatie (Artikel, Campagne, Kalender, Alle artikelen) · Instellingen (Planning eerst, Mijn account, Koppelingen, Help, Uitloggen).

Niet meer in de zijbalk: Doelen, Thema's, Onderzoek, Snel artikel, Planning-onder-Kennis, Archief-label, Rapport-onder-Publicatie. Routes van die pagina's bestaan nog.

Op `81def5c` stond wél de oude CE-set (Merkelementen, Doelen, Thema's, Onderzoek, Planning onder Kennis, Snel artikel, Campagnes, Agenda, Archief, Rapport onder Publicatie). Review klopte toen. Huidige `main` is merge van opdracht 2 (`59acf00`).

---

## Wat de review mist

Zaken in de code die bij deze acht punten horen en in §2 niet staan:

1. **Koppelpunten-bestand zonder aanroep.** Sinds `cc9d57d` bestaat het bestand, maar niets laadt het. Open waarden zitten nergens in runtime. Een tweede pad, `ce_hba_open_placeholder_message()`, ís wél gekoppeld aan submit/publish.
2. **Twee copy-modus-defaults.** Slot zonder campagne → merkbouw; campagne-template `''`/`default` → sell. Beide in hetzelfde bestand; de review noemt de slot-fallback, niet deze split.
3. **Toon ≠ doelgroepveld.** De schrijfopdracht krijgt een tone-regel; er is geen apart “voor wie”-gegeven. Wie §2 leest als “doelgroep zit in het datamodel”, leest te veel.
4. **Platform op twee lagen.** Slot: `campaign_platform` + `publish_date`. Artikel: `platform_final`. Geen gedeelde artikel-identiteit over kanalen. Relevant voor addendum §5, niet alleen “elk slot een datum”.
5. **`articles.slot_id` is niet UNIQUE.** Meerdere artikelrijen op één slot zijn schema-technisch mogelijk; het datamodel dwingt “één artikel per slot” niet af. Dat versterkt punt 7 in plaats van een multi-kanaal-artikel te geven.
6. **Stap-1-hint zoekt maar drie architectuurvelden.** Prijswoorden in andere HBA-velden geven geen hint. De review noemt de regex, niet de veldsubset.
7. **Harde kanaalcheck vs lichte voorstel-check.** Op huidige `main` blokkeert teken/hashtag bij submit/publish. Addendum §3.2 vroeg een lichte check à la stap-1-hint. De review zei “ontbreekt”; de latere bouw is strenger op het artikel en zachter op het voorstel — dat onderscheid stond niet in §2.

Niets gevonden dat pickup power of een PCB-component stiekem al doet.
