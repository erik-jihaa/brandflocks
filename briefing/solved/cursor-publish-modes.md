# Content Engine — drie publicatiemodi + klant-image-bibliotheek

## Context

De Content Engine krijgt drie publicatiemodi, instelbaar per klant via het
Account-scherm. De modus bepaalt hoe de pipeline zich gedraagt na CE-03
(artikel geschreven). Raadpleeg bij grote wijzigingen ook `briefing/product-vision.md`.

---

## Stap 1 — Datamodel

### `clients`-tabel: nieuw veld

```sql
ALTER TABLE clients
  ADD COLUMN publish_mode
    ENUM('manual_with_review', 'manual_without_review', 'automatic')
    NOT NULL DEFAULT 'manual_with_review';
```

### Nieuwe tabel: `client_images`

```sql
CREATE TABLE client_images (
  id           CHAR(36)      NOT NULL PRIMARY KEY,
  client_id    CHAR(36)      NOT NULL,
  filename     VARCHAR(500)  NOT NULL,
  url          VARCHAR(500)  NOT NULL,
  alt_text     VARCHAR(500),
  tags         VARCHAR(500),   -- kommagescheiden, voor CE-03 matching
  uploaded_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_client_images_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

SQL-bestanden: `scripts/migrations/add_publish_mode.sql` en
`scripts/migrations/add_client_images_table.sql`.

---

## Stap 2 — Account-scherm: modus-selector

Voeg een nieuwe sectie toe aan `AccountPage.tsx`, onder de bestaande
reviewer-velden en vóór de wachtwoord-sectie.

### UI

Koptekst: **"Publicatiemodus"**

Drie radiobuttons (zelfde stijl als Tone/Angle op het Article-scherm —
gevuld bolletje = geselecteerd, outline = niet geselecteerd):

```
● Handmatig met tegenlezer         (manual_with_review)
  Artikel → redacteur dient in → reviewer keurt goed → redacteur plaatst.
  Huidig standaard.

○ Handmatig zonder tegenlezer      (manual_without_review)
  Artikel → redacteur plaatst zelf. CE-03 genereert ook een beeldadvies.
  Geen approval-mail.

○ Automatisch inclusief plaatsen   (automatic)
  Artikel → scheduler plaatst automatisch via de beelddatabase in Sources.
  Vereist: minimaal 1 beeld in de Images-bibliotheek.
```

Bij selectie van **automatic**: toon een inline waarschuwing (beige tekst,
14px) — "Artikelen worden automatisch gepubliceerd zonder menselijke
goedkeuring. Zorg dat de beelddatabase gevuld is."

### API

`PATCH /api/account` — bestaande endpoint, voeg `publish_mode` toe aan de
velden die worden opgeslagen.

`GET /api/account` — voeg `publish_mode` toe aan de response.

---

## Stap 3 — Pipeline-gedrag per modus

### `manual_with_review` (ongewijzigd)

Geen wijzigingen. Huidige flow blijft exact zoals die is.

### `manual_without_review`

**Wijziging in CE-03 (`web/api/` of n8n):**
- Sla de approval-mail stap over — geen webhook naar CE-04.1bis.
- Zet `articles.status` direct op `draft` (niet `pending_review`).
- Voeg een **beeldadvies** toe als extra CE-03 output.

**Beeldadvies — nieuwe Stap E in CE-03:**
Na Stap D (zelfcontrole), één extra Claude-call:

```
OPDRACHT: Beschrijf in maximaal 2 zinnen welk beeld goed bij dit artikel past.
Geen gegenereerde afbeelding — een concrete beschrijving waarmee een redacteur
zelf een beeld kan zoeken of uploaden. Beschrijf het gewenste beeld concreet:
sfeer, onderwerp, compositie. Vermijd generieke omschrijvingen als "een blij team".

Output als JSON: { "image_advice": "string" }
```

Sla op in `articles.image_advice` (nieuw nullable VARCHAR-veld).

Toon het beeldadvies op het Article-scherm als een compacte hint onder de
upload-zone (beige tekst, 14px) — alleen zichtbaar als het veld gevuld is
en nog geen afbeelding is geüpload.

**Wijziging in CE-04.1bis:**
Stuur de approval-mail alleen als `clients.publish_mode = 'manual_with_review'`.
Check dit in de n8n-flow of in de PHP-submit-handler vóór de webhook-aanroep.

### `automatic`

**Wijziging in de maandag-scheduler (CE-01 / n8n):**
Na CE-03 (artikel klaar, `status = draft`):
1. Haal een beeld op uit `client_images` — kies het meest recente beeld,
   of match op `tags` als het artikel-thema overeenkomt met een tag.
2. Zet `articles.image_url_square` of `image_url_landscape` op de gekozen
   image-URL.
3. Roep de publish-WP-endpoint aan (`POST /api/articles/{id}/publish-wp`)
   of de social-publish-route, afhankelijk van `platform_publish_mode`.
4. Zet `articles.status` op `published`.

**Vereiste guard:** als `client_images` leeg is voor deze client, sla de
automatische publicatie over en stuur een notificatie-mail naar `contact_email`
("Automatische publicatie mislukt: geen beelden beschikbaar in de bibliotheek").

---

## Stap 4 — Sources: Images-tab

Voeg een vijfde tab toe aan het Sources-scherm, naast URL/RSS, Scraper,
Mailbox, Analythics, Trello.

Tab-label: **Images**

### Lijst-weergave

Per rij:
```
[thumbnail 48×48]  Bestandsnaam          Tags: merkidentiteit, team   Delete
```

- Thumbnail: kleine preview van het geüploade beeld.
- Delete: directe verwijdering (geen tweetraps-bevestiging, anders dan Scraper).
- Lege staat: "Nog geen beelden geüpload. Voeg beelden toe voor automatische
  publicatie." + **Upload images**-knop.

### Upload

- Knop **Upload images** (btn-second stijl) opent een file-picker.
- Accepteer: jpg, png, webp. Max bestandsgrootte: 5 MB per beeld.
- Velden per beeld na upload:
  - **Alt-tekst** (verplicht bij automatische modus, optioneel anders)
  - **Tags** (optioneel, kommagescheiden — gebruikt voor matching in `automatic`-modus)
- Opslag: upload naar de bestaande image-upload-handler
  (`POST /api/articles/{id}/image` of een nieuwe `POST /api/images`),
  sla URL op in `client_images`.

### API

```
GET  /api/images              → lijst van client_images voor huidige client
POST /api/images              → upload + insert in client_images
DELETE /api/images/:id        → verwijder beeld (DB + bestand)
```

---

## Stap 5 — Datamodel-aanpassingen articles-tabel

```sql
ALTER TABLE articles
  ADD COLUMN image_advice VARCHAR(1000) NULL,
  ADD COLUMN auto_published BOOLEAN NOT NULL DEFAULT FALSE;
```

`auto_published` maakt het mogelijk om in het Report-scherm later onderscheid
te maken tussen handmatig en automatisch gepubliceerde posts.

---

## Wijzigingsmatrix: wat verandert waar

| Onderdeel | Wijziging |
|-----------|-----------|
| `clients` tabel | `publish_mode` ENUM |
| `client_images` tabel | nieuw |
| `articles` tabel | `image_advice`, `auto_published` |
| `AccountPage.tsx` | Modus-selector radiobuttons |
| `PATCH/GET /api/account` | `publish_mode` veld |
| `web/api/articles-submit.php` | Skip CE-04.1bis webhook bij `manual_without_review` |
| CE-03 n8n / `push-ce03-n8n.sh` | Stap E (beeldadvies) bij `manual_without_review` |
| CE-01 scheduler | Automatisch publiceren bij `automatic` na CE-03 |
| Sources-scherm | Images-tab + upload-UI |
| `POST/GET/DELETE /api/images` | Nieuw |
| Article-scherm (Grid #2) | `image_advice` hint onder upload-zone |
| `BUILD-NOTES.md` | Toevoegen na implementatie |

---

## Wat bewust buiten scope is

- Automatisch gegenereerde beelden (DALL-E, Stable Diffusion) — bewust niet.
- Social-publicatie in `automatic`-modus is alleen WordPress voor nu;
  LinkedIn/Instagram vereist apart OAuth per klant en is een latere uitbreiding.
- Beeld-matching op basis van AI (semantisch zoeken in `client_images`) —
  voor nu: recentste beeld of tag-match op thema-titel. Later uitbreiden.
