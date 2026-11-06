# Campaign Planner — meta-sidebar toevoegen (Tone / Angle / Image ratio / Platform)

## Wat er moet gebeuren

Voeg de meta-kolom toe aan de Campaign Planner (`/campaign`), identiek aan hoe die op het Article-scherm (Grid #2) werkt. De redacteur kan per campagne-post Tone, Angle, Image ratio en Platform instellen vóórdat hij op **Add** of **Save and add** klikt.

---

## Waar het moet komen

Het meta-blok hoort in `CampaignPostEdit` (de inline-edit staat die verschijnt als de redacteur een post bewerkt vóór toevoegen), niet in `CampaignPostCard` (de read-only preview). Reden: in de card-weergave is er geen ruimte en geen actie-context; in de edit-staat past het logisch naast de velden die de redacteur al invult.

Optioneel: voeg een compacte read-only samenvatting toe aan `CampaignPostCard` (bv. `b to b · archetype · Linkedin`) als de waarden al zijn ingesteld — zodat de redacteur in de overzichtslijst kan zien wat er al staat zonder te hoeven openen.

---

## Technische implementatie

### 1. State uitbreiden in `CampaignPostEdit`

Voeg lokale state toe voor de vier meta-velden:

```tsx
const [tone, setTone] = useState<string>(post.tone ?? 'b2b');
const [angle, setAngle] = useState<string>(post.angle ?? 'archetype');
const [imageRatio, setImageRatio] = useState<string>(post.imageRatio ?? 'landscape');
const [platform, setPlatform] = useState<string>(post.platform ?? 'linkedin');
```

Gebruik de constanten uit `frontend/src/constants/metaOptions.ts` (die bestaan al voor het Article-scherm):

```ts
// metaOptions.ts — bestaande constanten, niet opnieuw definiëren
TONE_OPTIONS    // ['b to b', 'b to c', 'internal', 'partners', 'personal', 'archetype', 'adversary']
ANGLE_OPTIONS   // ['archetype', 'adversary', 'general']
RATIO_OPTIONS   // ['square', 'landscape']
PLATFORM_OPTIONS // ['Linkedin', 'Instagram', 'X', 'Facebook', 'Blog']
```

### 2. UI: accordeon-stijl, identiek aan Article Grid #2 meta

Gebruik dezelfde component die al bestaat voor het Article-scherm — `ArticleMetaSidebar` of de losse accordeon-componenten die daarin zitten. Kopieer géén logica opnieuw; import en hergebruik.

Layout in `CampaignPostEdit`: twee kolommen.
- Links: bestaande velden (theme/hook/format/tags)
- Rechts: de vier accordeons (Tone, Angle, Image ratio, Platform)

Of, als de beschikbare breedte te smal is voor twee kolommen: de meta-accordeons onderaan de edit-kaart, onder een horizontale lijn.

Gebruik exact dezelfde visuele stijl als op het Article-scherm:
- Accordeon header: `font-semibold`, pijltje dat 180° draait bij open/dicht
- Radio-optie: gevuld bolletje = geselecteerd, outline = niet geselecteerd
- Lettertypes en kleuren uit `tailwind.config.js` (`igis-*`)

### 3. Doorgeven via `createFastlaneArticle()`

Bij **Save and add** / **Add**: geef de vier meta-waarden mee aan `createFastlaneArticle()`. Die functie stuurt al een payload naar `POST /api/fastlane` — voeg toe:

```ts
createFastlaneArticle({
  // bestaande velden...
  tone,
  angle,
  image_ratio: imageRatio,
  platform,
});
```

Check `web/api/fastlane.php`: als die de meta-velden nog niet doorgeeft aan CE-03, voeg ze toe aan de INSERT/webhook-payload. Ze landen uiteindelijk in `articles.tone_suggested` / `angle_suggested` / `image_ratio_suggested` / `platform_suggested` — dezelfde kolommen die CE-03 Stap C al vult.

### 4. n8n CE-CampaignPlanner — angle al aanwezig

De n8n-flow genereert per post al een `angle`-waarde. Die kan als default worden gebruikt bij het openen van `CampaignPostEdit`:

```ts
// In CampaignPostCard → CampaignPostEdit doorgeven
const [angle, setAngle] = useState(post.angle ?? 'archetype');
```

Voor Tone, Image ratio en Platform heeft de n8n-flow nog geen default — gebruik dan de klantbrede defaults uit `clients.default_tone` / `default_image_ratio` / `default_platform` als die beschikbaar zijn via de API, anders de hardcoded fallbacks hierboven.

---

## Wat je NIET hoeft te doen

- De n8n `CE-CampaignPlanner`-flow hoeft **niet** te worden aangepast — die genereert al `angle` per post; de overige drie meta-velden worden door de redacteur ingesteld, niet door AI.
- Geen nieuwe API-route — `createFastlaneArticle()` / `POST /api/fastlane` breidt uit, geen nieuw endpoint.
- Geen nieuwe componenten bouwen — hergebruik de bestaande accordeon/radio-stijl van het Article-scherm.

---

## Checklist

- [ ] `CampaignPostEdit` heeft lokale state voor tone, angle, imageRatio, platform
- [ ] Vier accordeons zichtbaar in edit-staat, identiek in stijl aan Article Grid #2 meta
- [ ] Geselecteerde waarde toont gevuld bolletje, rest outline (1.5px)
- [ ] `createFastlaneArticle()` geeft de vier waarden mee
- [ ] `web/api/fastlane.php` slaat ze op / geeft ze door aan CE-03
- [ ] `post.angle` uit de n8n-response wordt als default voor Angle gebruikt
- [ ] `CampaignPostCard` toont een compacte samenvatting van de ingestelde meta (optioneel maar aan te raden)
