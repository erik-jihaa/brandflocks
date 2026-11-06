# WordPress-koppeling — onboarding per tenant

Praktische gids voor het instellen en controleren van WordPress-publicatie in Content Engine. Gebaseerd op productie-incident Sandbox 3 (aug 2026).

---

## 1. Waar staan credentials?

| Laag | Waar | Wanneer gebruikt |
|------|------|------------------|
| **Tenant (primair)** | Account → Publiceren (`clients.wordpress_*` in DB) | Altijd eerst — elke klant/tenant heeft eigen velden |
| **Platform-fallback** | `.env` → `web/includes/ce.local.php` op server | Alleen als tenant-velden leeg zijn |

**Regel voor onboarding:** vul altijd **Account → Publiceren** in voor de tenant. `.env` is platform-default / backup, geen vervanging voor tenant-config.

Diagnose per tenant:

```bash
curl -s "https://ce.igisnext.com/api/admin/wp-diagnose?client_id=TENANT-UUID" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN"
```

Verwacht: `"authenticatedAs": { "slug": "…", "roles": ["administrator" | "editor"] }` — geen `authError`.

---

## 2. Wat is een Application Password?

WordPress **Application Password** ≠ normaal inlogwachtwoord.

| | Normaal wachtwoord | Application Password |
|---|-------------------|----------------------|
| Waar aanmaken | Login | WP Admin → Gebruikers → **Profiel** → Applicatie-wachtwoorden |
| Wanneer zichtbaar | Altijd (via reset) | **Eén keer** in grijs vak na aanmaken |
| Formaat | willekeurig | meestal **24 tekens**, vaak getoond als `xxxx xxxx xxxx xxxx xxxx xxxx` (29 tekens mét spaties) |
| REST API | ❌ | ✅ |

**Gebruikersnaam:** WordPress **login-naam** (slug), bijv. `erik` — **niet** het e-mailadres (`erik@igisnext.com`), tenzij login en e-mail toevallig gelijk zijn.

**Revoke:** maakt dat Application Password **definitief ongeldig**. Gebruik alleen als je bewust wilt roteren. Na Revoke overal opnieuw invullen (alle tenants die dat wachtwoord gebruiken).

---

## 3. Onboarding-checklist per tenant

### A. Basis (alle modi)

1. **Account → Publiceren**
   - Site-URL: `https://klantdomein.nl` (geen `/wp-admin`)
   - WordPress-gebruiker: login-naam
   - Application Password: volledig gekopieerd uit grijs vak
   - Optioneel: auteur-ID (laat leeg tenzij specifieke auteur nodig)
2. **Opslaan** in Account
3. **Verifiëren** via `wp-diagnose` (zie §1)
4. Minimaal **één beeld** in Gallery (`/gallery`) als automatic-modus of auto-beeld gewenst is

### B. Publish mode kiezen (Account)

| Modus | Gedrag na CE-03 |
|-------|-----------------|
| `manual_with_review` | Draft → redacteur levert in → reviewer → handmatig Publiceer |
| `manual_without_review` | Draft → redacteur publiceert zelf |
| `automatic` | CE-03 schrijft artikel → systeem publiceert automatisch (WP + gallery-beeld) |

Zie `briefing/cursor-publish-modes.md` voor pipeline-details.

### C. Automatic-modus — extra vereisten

1. `publish_mode = automatic` in Account
2. Gallery: minimaal 1 geliked/geüpload beeld (`client_images`)
3. WordPress-koppeling **werkend** (wp-diagnose groen)
4. CE-01 → CE-03 stuurt `auto_publish`-vlaggen mee (platform; sinds aug 2026 gefixt)
5. Optioneel: test met handmatige auto-publish:
   ```bash
   curl -X POST "https://ce.igisnext.com/api/articles/SLOT-UUID/auto-publish" \
     -H "X-CE-Reset-Token: $CE_RESET_TOKEN"
   ```

---

## 4. Verificatie — altijd vóór “het werkt”

### Stap 1: curl (lokaal, vóór CE)

```bash
curl -s -u 'GEBRUIKER:APPLICATION-PASSWORD' \
  'https://klantdomein.nl/wp-json/wp/v2/users/me'
```

**Succes:** JSON met `"slug":"…"` en `"id":…`  
**Fout:** `{"code":"rest_not_logged_in",…}` → wachtwoord of gebruiker klopt niet; **niet** verder in CE invullen.

Gebruik **enkele quotes** rond `gebruiker:wachtwoord` (beschermt `&`, `)`, `!`, spaties).

### Stap 2: wp-diagnose (tenant in CE)

```bash
curl -s "https://ce.igisnext.com/api/admin/wp-diagnose?client_id=TENANT-UUID" \
  -H "X-CE-Reset-Token: $CE_RESET_TOKEN"
```

Controleer:
- `config.user` = juiste login-naam
- `config.app_password_length` ≈ 24–29 (niet 16 of korter)
- `authenticatedAs` ≠ null
- `resolvedPostAuthorId` ≠ null

### Stap 3: proefpublicatie

Handmatig op Article → **Publiceer**, of auto-publish-endpoint (automatic). Controleer post op live site.

---

## 5. Scenario’s

### Zelfde WordPress-site, meerdere tenants (sandbox + productie)

- **Mag:** één Application Password delen (zelfde `erik` +zelfde app-wachtwoord)
- **Praktisch:** kopieer werkende credentials van hoofdtenant:
  ```bash
  curl -X POST "https://ce.igisnext.com/api/admin/update-client" \
    -H "Content-Type: application/json" \
    -H "X-CE-Reset-Token: $CE_RESET_TOKEN" \
    -d '{"clientId":"DOEL-TENANT","copyWordpressFromClientId":"BRON-TENANT"}'
  ```
- **Revoke** op dat wachtwoord in WP → **alle** tenants op die site breken tegelijk

### Eigen WordPress-site per klant

- Per tenant **eigen** Application Password aanmaken op **hun** WP
- Account → Publiceren per tenant invullen
- `.env` niet gebruiken als bron — alleen tenant-velden

### `.env` (platform)

Alleen voor fallback / scripts / deploy-default:

```env
WORDPRESS_SITE_URL=https://igisnext.com
WORDPRESS_USER=erik
WORDPRESS_APP_PASSWORD="abcd EFGH ijkl MNOP qrst UVWX"
WORDPRESS_AUTHOR_ID=1
```

**Speciale tekens** (`&`, `)`, `!`, `#`): wachtwoord **tussen dubbele quotes**. Zie `.env.example` (zelfde patroon als `DB_PASS`).

Na `.env`-wijziging: `./scripts/deploy-ftp.sh` (schrijft `ce.local.php` op server).

---

## 6. Veelvoorkomende fouten

| Symptoom | Oorzaak | Oplossing |
|----------|---------|-----------|
| `rest_not_logged_in` | Geen geldig Application Password | Nieuw app-wachtwoord in WP; curl-test eerst |
| `app_password_length: 16` | Afgekapt / verkeerd gekopieerd / normaal wachtwoord | Opnieuw aanmaken; volledige 24 tekens |
| Gebruiker = e-mail | Verkeerd veld | Login-naam uit WP (Users) |
| curl werkt, CE niet | Alleen `.env` gevuld, niet Account | Account → Publiceren voor **die tenant** |
| `.env` werkt niet na edit | Niet gedeployed | `./scripts/deploy-ftp.sh` |
| `article_not_found` bij auto-publish | Systeem-call zonder tenant-context | Opgelost aug 2026 in API; deploy vereist |
| Automatic publiceert niet | Geen gallery-beeld / WP faalt / geen auto_publish | wp-diagnose + gallery + publish_mode |
| Rode syntax in `.env` | Speciale tekens | Quotes om wachtwoord |

---

## 7. Onboarding-gesprek — korte flow

1. **Kickoff:** publish mode kiezen (light demo → handmatig; productie → automatic pas als gallery + WP klaar)
2. **WordPress:** klant maakt Application Password aan (scherm delen of instructie-mail)
3. **Jij:** curl-test → Account invullen → wp-diagnose groen
4. **Gallery:** min. 1 beeld uploaden + taggen (`use in all posts` werkt voor automatic)
5. **Sources + Strategy** (volgens tier — zie `briefing/cursor-onboarding-tiers.md`)
6. **Theme/rolling agenda** starten
7. **Automatic:** bevestig proefpost op live site

---

## 8. Referenties

| Onderwerp | Document / tool |
|-----------|-----------------|
| **Onboarding per tier (Light/Medium/Full)** | `briefing/cursor-onboarding-tiers.md` |
| Publish modes | `briefing/cursor-publish-modes.md` |
| Onboarding tiers | `briefing/cursor-onboarding-tiers.md` |
| Eerste klant draaiboek | `briefing/onboarding-eerste-klant.md` |
| WP setup-script (platform) | `scripts/setup-igisnext-wordpress-ce.sh` |
| Diagnose | `GET /api/admin/wp-diagnose` |
| Slots/articles debug | `GET /api/admin/list-slots?client_id=…` |

---

## 9. Incident Sandbox 3 — lessons learned (aug 2026)

1. **CE-01** stuurde geen `auto_publish` naar CE-03 → automatic-modus schreef wel, publiceerde niet → gefixt in `generate-ce01-workflow.py`
2. **Auto-publish API** faalde zonder sessie (tenant-scope) → gefixt: client via slot-id
3. **WP-credentials** leken ingevuld maar Application Password was ongeldig; curl-test ving dit direct
4. **Werkende oplossing:** credentials van hoofdtenant gekopieerd (`copyWordpressFromClientId`) → auto-publish succesvol naar igisnext.com
