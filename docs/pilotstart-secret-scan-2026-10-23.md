# Geheimenscan repo (Rogier pilotstart §2)

**Scan gedraaid op 23 oktober 2026:** gitleaks **8.24.2**, modus **`--no-git`** (working tree).

**Volledige git-geschiedenis:** niet gedraaid op deze machine — `git`/`gitleaks` blokkeert door **niet-geaccepteerde Xcode-licentie**. Na `sudo xcodebuild -license accept` opnieuw:  
`gitleaks detect --source .` (zonder `--no-git`).

## Resultaat no-git

| | |
|---|---|
| **Vondsten** | **74** (gitleaks-rapport, niet in git gecommit) |
| **Regels (top)** | generic-api-key (53), linkedin-client-id (6), curl-auth-user (5), jwt (5), … |
| **Verdeling** | ~52 in docs/briefing/.env-voorbeelden; ~22 in code/config-voorbeelden |
| **Actie** | Geen live rotatie in deze run — handmatige triage nodig; **geen** `.env` / credentials in git committen. Productie/staging-secrets staan in `.env*` (gitignore). |

**Regel voor volgende rapportage:**  
*Scan gedraaid op 2026-10-23, 74 vondsten (no-git, deels false positives in docs); git-history-scan open na Xcode-licentie.*
