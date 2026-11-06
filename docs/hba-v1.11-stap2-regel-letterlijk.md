# HBA ronde 6 — stap 2: hint geeft de regel letterlijk weer

Datum: 2026-10-18  
Bron: `briefing/hba-input/hba-ronde-6-pilotklaar.md` stap 2  
Branch: `hba-ronde-6-pilotklaar`  
Prompt scoring blijft **`v1.11`** · model **`claude-sonnet-4-6`**

## Probleem

Screenshot 6 (Brandpunt, week 41): de hint schreef “koffie die de grens van zes weken *nadert* naar de voedselbank gaat”. De regel is “ouder dan zes weken”. Dezelfde parafrase zat in ronde 5 in `constraint_breach_uitleg` en is daar opgelost; in de hint (en thema/onderzoek/zijpaneel) nog niet.

## Wat erin zit

Eén gedeelde eis: `web/api/prompts/hba-regel-letterlijk.txt`, via `ce_hba_regel_letterlijk_eis()`.

| Plek | Veld |
| --- | --- |
| Hint (`ce_hba_v2_article_system_prompt`) | `aanwijzing`, `regel_overtreden`, `passage_na`, `headline_na`, `cta_na` |
| Thema-advies | `toelichting`, `wat_ontbreekt` |
| Onderzoeksadvies | `toelichting`, `wat_ontbreekt` |
| Zijpaneel Merkarchitectuur (score) | `diagnose`, `verbeteradvies` |
| Architectuurcheck | `toelichting`, `verbetering` die een regel noemt |

Geen nieuwe promptversie: dit is coaching/weergave op v1.11, geen andere hoofdoordeel-logica.

## Bestanden

- `web/api/prompts/hba-regel-letterlijk.txt`
- `web/api/prompts/hba-verbeteradvies-definitie.txt`
- `web/api/hba-prompt.php` — loader + stap 8 (diagnose = zijpaneel)
- `web/api/hba-v2-prompts.php` — hint, thema, research, architectuurcheck
- `scripts/test-hba-regel-letterlijk-v1.11.php`

## Klaar-criteria stap 2

- [x] Code: dezelfde eis op hint, thema, onderzoek, zijpaneel
- [x] Lokaal: `php scripts/test-hba-regel-letterlijk-v1.11.php`
- [x] Git: `f722b92` + poging 2 (geen “nadert” in output; retry als het model het toch zet)
- [x] Staging: smoke 6/6

**5 van 5 (poging 1, 18 okt):** V0 Brandpunt / Stelwerk / Duinroos **5/5**. Brandpunt week 41 **0/5** — de hint citeerde “nadert” terwijl dat niet in het artikel staat (echo van het promptvoorbeeld). Ruwe data: `docs/hba-v1.11-stap2-hints-5runs.json`.

Poging 2 (`b8ac5c9`): eis aangescherpt + één retry. Brandpunt week 41 opnieuw **0/5**. De hint verzint nog steeds de zin “koffie die de zes weken na het branden nadert” (staat niet in het artikel) en “corrigeert” die daarna wél letterlijk. `passage_na` is letterlijk; `aanwijzing` toont “nadert” aan de klant.

**Stop na twee pogingen** (briefing). Ruwe output: `docs/hba-v1.11-stap2-hints-5runs.json` en `docs/hba-v1.11-stap2-hints-brandpunt-poging2.json`.

V0-hints blijven **5/5**. Geen derde promptschuif.
