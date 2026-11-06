# Cursor-briefing — titelposter-generator: de artikeltitel als LinkedIn-beeld

**Status:** besluiten vastgelegd — klaar om te bouwen (eerst staging)
**Datum:** aug 2026 (besluiten Erik 18 aug 2026)
**Raakt:** nieuw `poster-repository.php`, `clients` (brandingvelden), beeld-sectie op Article,
CE-03 (`poster_headline`), automatic pipeline
**Referentie-beelden:** `image/` (24 screenshots, aug 2026) — stijlmoodboard; v1 rendert alleen
vlakke typografie (geen 3D, scribbles, herhalingspatronen)
**Huisstijl tenants:** `briefing/huisstijl-analyse-iqcoaches-bieze.md` — iQ Coaches + Bieze
**Eerste rollout:** alleen **staging**, tenant **iGiSNext (Erik)** — geen productie tot acceptatie

---

## 1. Wat we bouwen, in één zin

Een generator die van de artikeltitel een krachtig typografisch beeld maakt — in de kleuren en het
lettertype van de tenant — dat automatisch als `image_url_square` en `image_url_landscape` aan het
artikel hangt en dus zonder extra stappen meegaat in handmatige én automatische publicatie.

Voorbeeld van het doel:

> **De boterham heeft geen upgrade nodig.**
> **Jij misschien wel.**

Groot gezet, strak afgebroken, één woord in de accentkleur. Geen stockfoto, geen AI-plaatje — de
titel ís het beeld.

---

## 2. Techniekkeuze — waarom PHP GD en niet een headless browser

De omgeving is shared hosting met FTP-deploys; een headless Chrome (HTML → screenshot) is daar
niet betrouwbaar beschikbaar, en een externe render-API introduceert kosten en een afhankelijkheid
in het publish-pad — precies waar automatic mode niet stil op mag vallen.

**PHP GD met TTF-fonts kan dit prima**, mits we het ontwerp deterministisch houden:
`imagettftext()` zet tekst met elk aangeleverd lettertype, en typografische posters van dit genre
zijn juist gebaat bij een klein aantal strakke sjablonen in plaats van vrije vormgeving. Wat GD
niet kan (verlopen, blur, organische vormen) hebben we niet nodig — zie §4.

Check vóór de bouw (nog niet gedaan — **stap 0**): `php -m | grep gd` op staging/productie, en of
`imagettftext` niet in `disable_functions` staat. Fallback als GD ontbreekt: Imagick, zelfde aanpak.

### 2.1 Waarom niet Nano Banana of "Claude design" als renderer

Afgewogen (aug 2026) en afgewezen voor het publish-pad, met redenen:

**Claude** genereert geen afbeeldingen — het kan SVG/HTML schrijven, maar dan heb je alsnog een
renderer nodig om daar een PNG van te maken, en die renderer hébben we dan al (GD). Claude zit al
op de juiste plek in deze pipeline: de tekst (§5).

**Nano Banana Pro (Gemini 3 Pro Image)** is sinds juni 2026 GA en rendert tekst in beeld
aantoonbaar goed, ook meertalig, met stuurbare fontstijl. Toch niet als primaire renderer, om vier
redenen die stuk voor stuk zwaarder wegen in automatic mode:

1. **Niet deterministisch.** Twee posts van dezelfde tenant moeten hetzelfde systeem volgen;
   herkenbaarheid is het hele doel. Een generatief model geeft elke keer een andere compositie.
2. **Het exacte merkfont is niet gegarandeerd.** "Bold sans-serif" is stuurbaar; *Bricolage
   Grotesque 700 op 12% tracking* niet. Voor een huisstijl-tool is bijna-goed fout.
3. **Een externe API in het automatische publish-pad** betekent dat een storing of ratelimit bij
   Google een publicatie blokkeert. GD faalt nooit en kost niets per beeld.
4. **Eén spelfout in Nederlandse tekst is een teruggetrokken post.** De foutkans is klein geworden,
   maar bij automatisch publiceren zonder mensenoog is klein niet klein genoeg.

**Waar Nano Banana wél past — backlog "Poster v2":** een optionele *achtergrondlaag*. Nano Banana
genereert een abstract/fotografisch vlak in de merkkleuren (zonder tekst!), GD zet de typografie er
deterministisch overheen. Beste van twee werelden: levendigheid uit het model, letterzekerheid uit
de zetmachine. Alleen in handmatige modus met preview, nooit ongezien in automatic. De
`GEMINI_API_KEY` bestaat al in het platform (LLM-visibility), dus de plumbing is er.

**Wat we bewust niet doen in v1:** AI-beeldgeneratie in het publish-pad. Dit is een zetmachine,
geen kunstenaar — en dat is bij automatisch posten precies de kwaliteit die je wilt.

---

## 3. Branding per tenant — er is nu alleen een logo

`clients` heeft alleen `logo_url`. Voor de generator komen erbij:

```sql
ALTER TABLE clients ADD COLUMN brand_color_bg       VARCHAR(7)   NULL;
ALTER TABLE clients ADD COLUMN brand_color_text     VARCHAR(7)   NULL;
ALTER TABLE clients ADD COLUMN brand_color_accent    VARCHAR(7)   NULL;
ALTER TABLE clients ADD COLUMN brand_font_family     VARCHAR(100) NULL;  -- Google Fonts family name
ALTER TABLE clients ADD COLUMN poster_style          VARCHAR(20)  NULL;  -- blok | contrast | onderstreept

ALTER TABLE articles ADD COLUMN poster_headline      TEXT         NULL;
ALTER TABLE articles ADD COLUMN poster_style_used    VARCHAR(20)  NULL;  -- daadwerkelijk gerenderd sjabloon
ALTER TABLE articles ADD COLUMN image_source         VARCHAR(20)  NULL;  -- poster | upload | gallery | null
```

UI: sectie **Huisstijl** op **BrandElements** — de **klant vult dit zelf in** (geen coach-only).
Drie kleurvelden (kleurkiezer + hex), contrastcheck (tekst op achtergrond minimaal AA, anders
waarschuwing), Google Fonts-keuze, sjabloonkeuze uit §4 met live preview.

**Fontkeuze via Google Fonts (besluit Erik, aug 2026).** Geen eigen font-upload in v1 — geen
licentievinkje. De tenant kiest uit Google Fonts.

Implementatie:

- `brand_font_family` (VARCHAR) in plaats van `brand_font_path`; UI is een zoekveld/pulldown met
  een gecureerde poster-shortlist bovenaan (± 12 fonts die groot gezet goed staan: Archivo Black,
  DM Sans, Inter, Space Grotesk, Fraunces, Libre Franklin, Anton, Bricolage Grotesque, …) en
  daaronder de volledige lijst. Live preview met de eigen titel.
- Server-side: TTF één keer downloaden (Google Fonts API of vaste `fonts.gstatic.com`-URL per
  shortlist-family) en cachen in `uploads/fonts/{family}/`. **Renderen altijd vanaf de lokale kopie**
  — het publish-pad mag nooit van een live Google-request afhangen. Schrijfrechten op shared
  hosting zijn OK; meenemen in backup.
- Cache per gewicht (regular + bold volstaat voor de drie sjablonen).
- Default font als niets gekozen: **Archivo Black**.

**Fallback-kleuren (iGiS-palet)** als tenant geen huisstijl invulde — generator faalt nooit:

| Rol | Hex | Token |
| --- | --- | --- |
| Achtergrond | `#eae6dc` | ce-offwhite |
| Tekst | `#2e2014` | ce-black |
| Accent | `#033dff` | ce-blue |

Default sjabloon: **Blok**.

**Logo:** klein in een hoek (max 8% breedte). Geen logo → weglaten. **Contrast-fix:** als het logo
op de gekozen achtergrond slecht leesbaar is, converteer naar wit (luminance-check op `logo_url` —
donkere pixels → wit). Geen aparte logo-variant upload in v1.

Eigen font-upload kan later als backlog (#poster-font-upload).

### 3.1 Vooringevulde tenants (acceptatietest)

Bron: `briefing/huisstijl-analyse-iqcoaches-bieze.md` (live CSS homepages, 18 aug 2026).

Geen van beide sites gebruikt Google Fonts — voor v1 kiezen we **dichtstbijzijnde Google Fonts**
voor poster-rendering. Script/sierfonts (Bieze *All of me*) zijn **niet** geschikt op posterschaal;
daar gebruiken we een strak sans-serif displayfont.

| Tenant | `brand_color_bg` | `brand_color_text` | `brand_color_accent` | `brand_font_family` | `poster_style` | Opmerking |
| --- | --- | --- | --- | --- | --- | --- |
| **iQ Coaches** | `#F7F9FB` | `#0C1125` | `#980028` | **Inter** (700) | Contrast | Website: Helvetica-stack → Inter |
| **Bieze** | `#FFFFFF` | `#16281B` | `#C9D200` | **DM Sans** (700) | Blok | Website: Apertura → DM Sans; script *All of me* niet op poster |
| **iGiSNext** | `#eae6dc` | `#2e2014` | `#033dff` | **Archivo Black** | Blok | Zie §3 fallback / `docs/igisnext-identity.md` |

**Seed bij eerste deploy staging:** vul deze waarden in `clients` voor de drie test-tenants (of
toon als voorbeeld in BrandElements-Huisstijl). Klant kan daarna zelf wijzigen.

**Logo:** iQ Coaches / Bieze — bestaande `logo_url`; logo-conversie §3 indien contrast te laag.

**Acceptatie-titel** (zelfde tekst voor alle drie tenants, pixelverschil per huisstijl):

> De boterham heeft geen upgrade nodig. Jij misschien wel.

Alternatieve titels voor few-shot tuning: Agenda of [ce.igisnext.com/blog](https://ce.igisnext.com/blog).

---

## 4. De sjablonen — drie is genoeg

**Referentie:** `image/` — moodboard (FlowState-achtige grid, gestapelde blokletters, twee-regel
contrast). Screenshots met 3D-schaduwen, scribbles of woordherhaling (**ENERGY**, **YES!**) zijn
**niet** v1 — GD rendert alleen vlakke vlakken + TTF.

Het genre "typographic poster, levendiger dan een PPT-slide" draait om drie dingen: **schaal**
(tekst vult het vlak, marges durven klein), **contrast in gewicht** (één zin zwaar, één zin licht),
en **één accent** (woord in kleur, of een onderstreep). Niet meer dan dat.

| Sjabloon | Beschrijving | Werkt het best bij |
| --- | --- | --- |
| **Blok** | Titel in 2–4 regels, linkslijnend, vlak gevuld tot de marges; laatste zin of kernwoord in accentkleur; logo klein in de hoek | vrijwel alles — dit is de default |
| **Contrast** | Eerste zin enorm en zwaar, tweede zin klein en licht eronder (werkt precies voor "De boterham heeft geen upgrade nodig. / Jij misschien wel.") | titels met een wending in twee zinnen |
| **Onderstreept** | Eén centrale regel met een dikke accentstreep onder het kernwoord, veel witruimte | korte, stellige titels tot ~8 woorden |

Vaste regels voor alle drie:

- **Formaten:** 1080×1080 (square) en 1200×627 (landscape) — beide kolommen die al bestaan.
- **Landscape ≠ square herschaald:** aparte layout per formaat (square: gestapelde blokken;
  landscape: tekstblok links of gecentreerd met andere margeverhouding — zelfde typografie,
  andere compositie).
- **Automatische regelafbreking op zinsdelen**, niet op breedte alleen: breek bij voorkeur op
  leestekens en voegwoorden. **Nederlandse spelling:** geen afbreking midden in woord; respecteer
  samenstellingen en trema's (bijv. *gezondheids* + *claims* alleen als beide delen leesbaar zijn).
  Een poster met "de boterham heeft geen up-" is mislukt.
- **Font-size-solver:** begin groot, verklein tot alles past met minimaal 5% marge. Minimum ~48px
  op 1080 breed; past het dan nog niet → **opnieuw via LLM** een kortere `poster_headline` (§5),
  daarna opnieuw renderen.
- Logo uit `logo_url` klein in een hoek, nooit groter dan 8% van de breedte (met contrast-fix §3).
- Geen effecten. Vlakke achtergrond, of een subtiel donkerder vlak achter de tekst als het moet.

### 4.1 Sjabloonkeuze is tenant-niveau — niet per artikel (besluit v1)

`poster_style` staat op `clients` en nergens anders. Elke post van hetzelfde merk volgt hetzelfde
systeem; herkenbaarheid ís het doel, en per artikel kunnen wisselen ondermijnt precies dat.
Bovendien kan automatic mode toch niet kiezen, en heeft de redacteur in het handmatige pad al een
sterkere ontsnapping: een eigen beeld of een gallery-beeld.

**Wel nodig: terugval als het sjabloon niet past.** De sjablonen stellen vormeisen, en een
posterregel voldoet daar niet altijd aan. Dat is geen smaakkwestie maar een rendervraag, dus die
lost de generator zelf op:

| Gekozen sjabloon | Past niet als… | Val terug op |
| --- | --- | --- |
| **Contrast** | de posterregel bestaat uit één zin | **Blok** |
| **Onderstreept** | meer dan 8 woorden, of geen duidelijk kernwoord | **Blok** |
| **Blok** | past altijd | — (dit is het vangnet) |

Blok moet dus altijd renderbaar zijn — dat is de reden dat hij de default is.

Log de daadwerkelijk gebruikte variant in `articles.poster_style_used`. Twee redenen: je kunt zien
hoe vaak een tenant zijn eigen sjabloon niet krijgt (signaal dat de posterregels structureel niet
bij de keuze passen), en mocht je per-artikel overrulen later alsnog willen, dan legt het datamodel
nu al vast wat er gerenderd is.

**De redacteur stuurt op de tekst, niet op het sjabloon.** Bevalt de poster niet, dan past hij de
posterregel aan en klikt **Vernieuw**. Dat verandert de inhoud binnen het systeem in plaats van het
systeem zelf — precies de goede knop om wél te geven.

Per-artikel override: backlog, niet in v1.

---

## 5. De titel zelf — een postervariant, geen andere kop

"Maak een artikeltitel krachtiger zodat hij werkt als image" — terecht, maar de SEO-kop en de
posterregel zijn verschillende dingen. Een kop van veertien woorden is prima voor WordPress en
onbruikbaar op een poster.

Voeg aan CE-03 stap C één outputveld toe (zelfde call, geen extra kosten). De volledige
promptinstructie:

```
poster_headline: herschrijf de titel als posterregel voor een beeld bij de LinkedIn-post.

REGELS:
- Maximaal 10 woorden, verdeeld over maximaal 2 zinnen.
- De kracht zit in de wending: zin 1 zet iets neer, zin 2 draait het om of maakt het
  persoonlijk. Als de titel geen wending heeft, maak er dan één krachtige zin van.
- Spreektaal. Geen dubbele punt, geen vraagteken als goedkope truc, geen jargon.
- Behoud de toon van het merk (tone_of_voice) en de kern van het artikel.
- Is de bestaande titel al 10 woorden of korter en krachtig: gebruik hem letterlijk.
- Geen aanhalingstekens om de output.
```

Met voorbeelden erbij in de prompt (few-shot, dit stuurt harder dan de regels):

```
VOORBEELDEN van kop → posterregel:
"Waarom een goede lunch belangrijker is dan je denkt tijdens BBQ-season"
  → "De grill kan wachten. Je lunch niet."
"Vijf manieren om je merkpositionering te bewaken in dagelijkse content"
  → "Elke post bouwt je merk. Of sloopt het."
"De boterham heeft geen upgrade nodig. Jij misschien wel."
  → ongewijzigd (al kort en krachtig)
```

Opslag: `articles.poster_headline`. De generator gebruikt die, met de gewone `headline` als
fallback. De redacteur past hem aan **onderaan de beeld-sectie** op Article (niet in het
publishpanel). Bij **automatic mode** gaat de gegenereerde variant ongezien uit — de regels
hierboven zijn de enige kwaliteitspoort.

**Few-shot tuning:** haal voorbeeldkoppen uit de Agenda of van [ce.igisnext.com/blog](https://ce.igisnext.com/blog)
vóór livegang (minimaal tien).

**Te lange posterregel na CE-03:** als de renderer na size-solver nog niet past → lichte LLM-call
(zelfde regels, "kort tot max 10 woorden") — hergebruik bestaande LLM-plumbing (`GEMINI_API_KEY` /
Claude-pad zoals LLM-visibility). Geen hard afkappen.

Dit veld gaat **niet** door de HBA-scoring als apart item — het is dezelfde titel in postervorm.

---

## 6. Waar het in de flow hangt

**Endpoint:** `POST /api/articles/{slot}/poster` → rendert beide formaten, schrijft naar de
bestaande upload-locatie (zelfde patroon als `ce_upload_article_image`), vult `image_url_square`
en `image_url_landscape`, zet `image_source = 'poster'`.

**Geen poster bij CE-03.** De poster wordt **niet** automatisch gegenereerd na het schrijven —
alleen als de redacteur op **Gebruik als beeld** of **Vernieuw** klikt (handmatig pad).

**Handmatig:** onderaan de **beeld-sectie** op Article (bij upload/gallery/ratio) een blok
**Titelbeeld** met preview, bewerkbaar veld `poster_headline`, en twee knoppen: **Gebruik als
beeld** en **Vernieuw**. Geen sjabloonkeuze per artikel — zie §4.1. Kiest de redacteur een eigen
foto of gallery-beeld, dan wint dat; de poster is een optie naast upload en gallery. **Pexels:**
buiten scope v1 (backlog).

**Overschrijven:** bestaand beeld wordt nooit stilzwijgend overschreven. **Vernieuw** mag alleen
een vorig poster-beeld vervangen (`image_source = 'poster'`); upload of gallery blijft staan tot de
redacteur het beeld expliciet wijzigt.

Viel de generator terug op een ander sjabloon (§4.1), toon onder de preview:
*"Deze titel past niet in jullie vaste sjabloon — dit is de standaardvariant."*

**Automatic mode:** vlak vóór publicatie, als er nog géén beeld hangt → genereer titelposter.
Automatic **faalt nooit meer** op een lege gallery zolang branding-fallback werkt.

**Volgorde beeldkeuze automatic mode (besluit Erik):** handmatig gekozen beeld → **titelposter**
→ gallery-match op tags. Titelposter is de **voorkeur**; gallery is fallback. **Onboarding-tekst**
("minimaal 1 gallery-beeld") **voorlopig laten staan** — gallery blijft aanbevolen, poster is het
vangnet.

**LinkedIn:** geen wijziging — `ce_article_primary_image_url` pakt square; Images API bestaat.

---

## 7. Bouwvolgorde

0. **GD-check** op staging (`php -m | grep gd`, `disable_functions`).
1. **Brandingvelden + UI op BrandElements** (§3), iGiS-fallback, Google Fonts shortlist + cache.
2. **Renderer** (`poster-repository.php`): GD, drie sjablonen, NL regelafbreking, size-solver,
   aparte landscape-layout, logo-conversie. Testbaar via endpoint.
3. **`poster_headline`** in CE-03 stap C + kolommen (`poster_headline`, `poster_style_used`,
   `image_source`).
4. **Beeld-sectie** op Article — preview + posterregel + knoppen (§6).
5. **Automatic mode** (§6) — als laatste, pas wanneer 1–4 op **staging iGiSNext** bewezen zijn.

Stap 2 is het echte werk. Reken op het meeste geploeter in regelafbreking en size-solver.

**Niet in v1:** Nano Banana achtergrondlaag (Poster v2), Pexels, eigen font-upload, productie-deploy.

---

## 8. Acceptatiecriteria

- Genereer voor **Bieze**, **iQ Coaches** en **iGiSNext** (andere kleuren/fonts) dezelfde titel:
  drie herkenbaar verschillende, merkvaste posters. Zelfde tenant twee keer = pixel-identiek.
- "De boterham heeft geen upgrade nodig. Jij misschien wel." levert in sjabloon Contrast een poster
  waarin de tweede zin visueel ondergeschikt is aan de eerste; één zin → terugval Blok (§4.1).
- Geen afgebroken woorden; NL-spellingregels; geen tekst buiten marges; AA-contrast.
- Tenant zonder huisstijl → nette poster op iGiS-fallback (§3).
- Automatic publish zonder gekozen beeld → post mét titelposter op LinkedIn én WordPress.
- Handmatig gekozen upload/gallery-beeld wordt nooit overschreven door poster of automatic.
- **Vernieuw** vervangt alleen vorig poster-beeld.
- Google Font lokaal gecachet; render werkt zonder live Google-request.
- `poster_style_used` en `image_source` altijd gezet na render.
- Geen sjabloonkeuze per artikel in UI.
- Eerste live-test alleen op **staging iGiSNext (Erik)**.

---

## 9. Besluiten Erik (18 aug 2026)

| # | Onderwerp | Besluit |
| --- | --- | --- |
| 1 | Referenties | `image/` map met screenshots |
| 2 | Rollout | Alleen staging iGiSNext (Erik) |
| 3 | Pexels | Buiten scope v1 |
| 4 | Fonts | Alleen Google Fonts, geen upload/licentie |
| 5 | Fallback kleuren | iGiS-palet (§3) |
| 6 | Default sjabloon | Blok |
| 7 | Logo | Converteer naar wit indien nodig |
| 8 | Huisstijl invullen | Klant zelf op BrandElements |
| 9 | Landscape | Aparte layout, niet herschaald |
| 10 | Contrast + 1 zin | Terugval Blok |
| 11 | Sjabloon per artikel | Nee — tenant-niveau (§4.1) |
| 12 | Regelafbreking | NL-spellingregels |
| 13 | UI posterregel | Onderaan beeld-sectie |
| 14 | Wanneer renderen | Alleen op Vernieuw / Gebruik als beeld |
| 15 | Te lang | Opnieuw via LLM |
| 16 | Few-shot | Agenda + ce.igisnext.com/blog |
| 17 | Automatic zonder gallery | Ja, faalt niet |
| 18 | Automatic volgorde | Titelposter vóór gallery |
| 19 | Vernieuw overschrijven | Alleen vorig poster-beeld |
| 20 | Onboarding gallery-tekst | Laten staan |
| 21 | GD-check | Nog doen — stap 0 |
| 22 | LLM | Bestaande `GEMINI_API_KEY` / LLM-plumbing |
| 23 | Font-cache | OK op shared hosting |
| 24 | Test-tenants | Bieze, iQ Coaches, iGiSNext |
| 25 | Coach-instructie | §10 |
| 26 | Nano Banana achtergrond | Later (Poster v2) |

---

## 10. iQ-coach — korte instructie (intern)

1. **Huisstijl:** wijs klanten naar BrandElements → sectie Huisstijl (kleuren, Google Font,
   sjabloon). Zonder invulling werkt de poster op iGiS-defaults — merkvast wordt pas echt bij
   ingevulde kleuren/font.
2. **Automatic mode:** posts krijgen standaard een titelposter als er geen eigen beeld is; gallery
   is optioneel maar nog steeds aanbevolen voor variatie (onboarding-tekst blijft).
3. **Handmatig:** onderaan beeld-sectie → posterregel checken → **Gebruik als beeld**. Eigen foto
   wint altijd; **Vernieuw** werkt alleen op een eerder gekozen poster.
4. **Staging:** feature eerst testen op iGiSNext-staging vóór productie of andere tenants.
5. **Poster v2** (AI-achtergrond): niet beloven — staat op backlog.
