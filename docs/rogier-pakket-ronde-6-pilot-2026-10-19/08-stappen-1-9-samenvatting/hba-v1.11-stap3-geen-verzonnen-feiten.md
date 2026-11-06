# HBA ronde 6 — stap 3: geen verzonnen feiten in het artikel

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 3  
Branch: `hba-ronde-6-pilotklaar`  
Slot: Brandpunt week 41 `d733223a-7964-4324-9883-dcd4b1809006` (CF_Staging, geen nieuwe tenant)

## 1. Met welke CE-03 is het huidige artikel geschreven?

**Vóór** de premissecheck van ronde 5 stap 2 (`2a0c371`, 13 okt).

Bewijs in `self_review` van `fa79e799-c710-11f1-9456-9c6b00b7cd02`:

- wel `themeConflict` + `themeConflictWhy` (ronde 4);
- **geen** `themePremisse` / `themePremisseWat` / `themePremisseActie`.

De huidige CE-03 heeft die velden wél (sinds ronde 5). Het artikel “Waarom wij geen Black Friday doen” is dus een oude draft.

Bekende verzonnen zinnen (staan niet in merkarchitectuur, bewijsvelden of research):

- “Wij branden in kleine batches, op bestelling”
- “je verstuurt het snel”
- “soms is een batch uitverkocht”
- aanmeldlijst / `[aanmeldlink]`

Aanspreekvorm in de tekst: **u**. Brandpunt is **je** (veld gezet in stap 2a).

## 2–4. Wat erin zit (lokaal, herschrijven pas na `ja staging`)

| Punt | Actie |
| --- | --- |
| 2 Herschrijven | Zelfde slot + thema + research; trigger `ce03-phase2-test`. Runner: `scripts/run-hba-stap3-rewrite-staging.py`. |
| 3 Bron per bewering | Stap D levert `merk_beweringen[]` (`citaat` + `bron`: merkarchitectuur / bewijsveld / research / geen_bron). Zichtbaar onder Aandachtspunten. `geen_bron` blokkeert indienen/publiceren. Schrijver: geen proces verzinnen; ontbreekt een feit → werkwijze uit de architectuur of weglaten. |
| 4 Aanspreekvorm | Veld bestond al (stap 2a). CE-03 krijgt `aanspreekvorm_block`; Brandpunt = je. |

## Klaar-criteria

- [x] Versie van het bestaande artikel vastgelegd (vóór premissecheck)
- [x] CE-03: geen verzonnen merkenfeiten + bron per bewering + je/u
- [ ] Herschreven artikel op staging: geen bewering zonder bron, `self_review` toont de bronnen, tekst gebruikt **je**

## Staging (18 okt 2026)

- Deploy stap **3** (`8f7d79a`); smoke **6/6**; bundle `index-ByPUkz_w.js`.
- CE-03 n8n: `G00xCae5m8fTQ4KX` (webhook `ce03-write-article-staging`).
- Herschrijven week 41 (~48s): `docs/hba-v1.11-stap3-geen-verzonnen-feiten.json`.

| Check | Uitkomst |
| --- | --- |
| Verzonnen proces (bestelling/uitverkocht/aanmeldlijst) | **Weg** |
| Aanspreekvorm **je** | **Ja** |
| `themePremisse` in self_review | **Ja** (nieuwe CE-03) |
| Geen merkbewering zonder bron in tekst | **Nee** — 2 zinnen nog gemarkeerd als `geen_bron` |

Nieuw artikel: `9e73e3fd-caee-11f1-84cf-9c6b00b7cd02` — kop *Geen Black Friday-korting. Wel een eerlijke uitleg.*  
Klantpreview: https://staging.igisnext.com/article/9e73e3fd-caee-11f1-84cf-9c6b00b7cd02?hbaKlant=1

Open punten na 1× rewrite (opgelost in **stap 3a**, geen tweede rewrite):

1. *“Kortingen op koffie die ergens vandaan komt…”* — retoriek; hoort niet in `merk_beweringen`.
2. *“…leg je uit wat je proeft”* — voortzetting proefochtend → `bewijsveld`; tekstfix: *leggen wij uit*.

Hermeting stap D: `docs/hba-v1.11-stap3a-merkbewering-hermeting.json` (0× `geen_bron`).

**Staging 3a (18 okt):** deploy `2e1ebb7` + hotfixes (admin `$skipAuth`, `client_id` via slot, geen `updated_at`); CE-03 n8n `G00xCae5m8fTQ4KX`; smoke **6/6**. Artikel `9e73e3fd-…`: tekst *leggen wij uit*, self_review **0× `geen_bron`** (4 merkbeweringen na hermeting).
