# Brandpunt stroomtest — staging artefacten

Map voor stap 8 (ronde 6):

- `full-pipeline.json` — onboarding + import + vijf checkpoints (via run-script).
- `onboarding-poging-*.json` — ruwe architectuurcheck (API).
- `checkpoint-<n>.json` — home/progress/PBS/portfolio + post-analyse.
- **Screenshots (2026-10-18):** `checkpoint-{10,15,20,21,25}-overzicht.png` + `-rapport.png`; wizard: `onboarding-wizard-merkarchitectuur-voltooid.png`.
- **Login staging:** `brandpunt-koffie-stroomtest@igisnext.com` / wachtwoord lokaal gezet (Erik: zelfde als andere staging-testtenants of opnieuw via `scripts/reset-login-password.sh staging '…' 9d414129-29ab-ee9c-95c7-dc5452f6a65f`).
- Poging 1 (geblokkeerd): geen aparte UI-run — zie `onboarding-poging-1.json` (APIzelfde antwoorden als wizard).

Run data: `./scripts/run-hba-brandpunt-stroomtest-staging.sh` (na deploy).
