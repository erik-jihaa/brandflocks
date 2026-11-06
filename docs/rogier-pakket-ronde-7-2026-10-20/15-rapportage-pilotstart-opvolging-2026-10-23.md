# Rapportage aan Rogier — opvolging pilotstart (23 okt)

**Reactie op:** `hba-reactie-pilotstart-2026-10-22.md`  
**Branch:** `hba-ronde-6-pilotklaar` (zie git HEAD na push)

Rogier,

Hier is wat we hebben gedaan naar aanleiding van je reactie. Scoring (v1.13) **ongewijzigd**; replay 95 scores → **19/19**.

## 1. A3 — Negeer → melding (klaar op staging)

- Bij **Negeer** op een positioneringshint loggen we `hba_hint_genegeerd` (event_log) met hoofdoordeel van de post.
- Hoofdoordeel **`tegenkracht`** of **`regelbreuk`**: dezelfde dag e-mail naar adressen in **`CE_PILOT_ALERT_EMAILS`** (comma-separated; fallback `CE_SUPPORT_EMAIL`).
- Inhoud mail: merk, posttitel, hoofdoordeel, citaat + uitleg uit score/`flag_citaten`.
- **Test (admin):** `GET /api/admin/hba-pilot-negeer-alert-test?slotId=b9f67061-c2e5-43db-8951-823decb25c78` (Brandpunt “Breekt de regel”) — reset-token, staging only.

**Actie Erik:** zet `CE_PILOT_ALERT_EMAILS=…` in `.env.staging` (jij + Rogier) als dat nog niet staat; test-endpoint opnieuw aanroepen of écht **Negeer** in UI op die post.

## 2. A2 — Negeer-telling in weekoverzicht

Weekrapport bevat nu sectie **“Negeer op positioneringshints”** per merk: aantal per hoofdoordeel + teller **kritiek** (tegenkracht/regelbreuk).

Voorbeeld week 43: `docs/pilotstart-a2-weekly-report-voorbeeld-2026-10-22.md` — ook in zip als **`14-a2-weekoverzicht-voorbeeld-week43.md`**.

## 3. Geheimenscan

**Scan gedraaid op 2026-10-23, 74 vondsten (gitleaks no-git), triage docs/voorbeelden — geen rotatie in deze run; git-history-scan open na Xcode-licentie op dev-machine.**

Details: `docs/pilotstart-secret-scan-2026-10-23.md`.

## 4. Domein

`brandfloks.nl` in eerdere tekst was een **tikfout** → overal **`brandflocks.com`** (Fable blijft wachten tot die server).

## 5. Nog open

- **A1:** screenshots drie neutrale posts (staging-login).
- **B1:** flagtelling vóór eerste klantrapport — graag **startdatum eerste pilotklant** voor terugrekenen.
- **C1–C3:** backlog.

Erik kan de mail aanvullen.
