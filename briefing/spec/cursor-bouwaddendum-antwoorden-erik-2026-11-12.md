# Antwoorden Erik — bouwaddendum intelligentielaag (12 nov 2026)

Vervolg op [`bouwaddendum-intelligentielaag-brandflocks-2026-11-10.md`](bouwaddendum-intelligentielaag-brandflocks-2026-11-10.md). Dit document is de **Erik-kant** van de vragen uit de Cursor-review; technische feiten zijn waar mogelijk uit de **brandflocks-fork** gehaald.

---

## 1. Schrijfstijl per doelgroep → copy-call

**Antwoord:** Ja, **gedeeltelijk al**. In `tone-styles.php` bouwt `ce_build_tone_directive()` een regel uit `clients.tone_styles` op basis van de gekozen toon/doelgroep (`ce_lookup_tone_style_line`) en die directive gaat mee naar het schrijfpad. Wat nog ontbreekt t.o.v. het addendum: expliciet koppelen aan **voor wie de post bedoeld is** (niet alleen het tone-veld op artikelniveau) en de **fallback standaardtoon** zonder doelgroep (Rogier §2 besluit) nog eenduidig in prompt + tests vastleggen.

---

## 2. Voortgang sinds 5 november (fork / opschonen)

**Antwoord:** Grote stap gedaan: repo **`erik-jihaa/brandflocks`**, tag **`bf-forkpunt`**, rondes **A–F** en herontwerp **1a/1b** (merkdoel → schrijven, campagne-slot = thema) staan in `docs/BUILD-NOTES.md` (6 nov). **Addendum bouwen:** parallel aan herontwerp, maar **slice-volgorde** weekplan eerst (testset + campagne-basis), daarna addendum §1+3+5 zoals §6. Geen wachten op volledige klikmodel-sync vóór fork-deploy.

---

## 3. Bron klikmodel

**Antwoord:** Canoniek = **live Claude-artifact** (niet export 2 nov). URL/versie in [`klikmodel-bronnen.md`](klikmodel-bronnen.md) bijhouden; periodieke export naar `briefing/spec/klikmodel/` alleen als snapshot, niet als leidend.

---

## 4. Pickup power — kalibratie

**Antwoord:** Eerst op **eigen Brandflocks-host** (Antagonist) met testtenant; grenzen `pickup_maar_drift` en laag/midden/hoog in meetronde met Rogier, analoog v1.13. Docx `Bouwaddendum_HBA-laag_pickup_power.docx` nog in repo zetten vóór bouw §4.

---

## 5. Antagonist Plus / eigen host

**Besluit (9 nov):** **Plus** eerste jaar, **eigen** MariaDB + FTP + URL op **brandflocks.nl** — géén gedeelde staging-DB. Deploy-profiel **`brandflocks`** + `.env.brandflocks`. n8n: zelfde cloud, **`N8N_WEBHOOK_SUFFIX=-brandflocks`**. Rooktest op staging-submap (`brandflocks-target`) blijft optioneel voor snelle checks.

**Inrichting (12 nov):** `./scripts/bootstrap-env-brandflocks.sh` → `.env.brandflocks`; geheimen in `.env.brandflocks.antagonist-secrets` (DirectAdmin). Daarna `check-env-isolation.sh brandflocks` → `deploy-ftp-probe.sh brandflocks` → eerste deploy.

---

## 16. Volgorde addendum vs weekplan

**Akkoord:** Addendum **1 (copy-modus)** en **3 (per-kanaal)** pas na **weekplan stap 0 + campagne-basis** op fork; **pickup power (§4)** pas na v1.13-replay groen. **`copy_modus`** bestaat in campagne-antwoord 30 okt; in fork-code nog niet als veld — eerste addendum-slice.

---

## Nog naar Rogier (ongewijzigd)

- Pickup-power docx in `hba-input/`
- OPEN KOPPELPUNTEN (drempels) — niet zelf invullen
- Bevestiging artifact-versie Agenda Datum/Campagne + Planning onder Instellingen (§8)
