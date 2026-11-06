# HBA v1.12 — stap 4: A6-restpunten (Rogier, ronde 6)

**Datum:** 19 oktober 2026  
**Staging:** https://staging.igisnext.com/ (deploy + CE-03 n8n staging)  
**Screenshots:** `docs/a6-screenshots-staging-2026-10-19/`

Bron besluiten: `briefing/hba-input/terugkoppeling-rogier-ronde-6-ingevuld.md` (zes restpunten).

## Zes punten — status

| # | Restpunt | Status | Bewijs |
| --- | --- | --- | --- |
| 1 | Doelgroeplabels: Consumenten / Persoonlijk (ik-vorm) / Zakelijk i.p.v. b to c / personal / b to b | **Klaar** | `03-merkelementen.png`, `07-feedback-terugkoppeling.png` (Tone-radios) |
| 2 | Terugkoppeling: **Akkoord \| Verwijderen**; kolom niet afkappen | **Klaar** | `07-feedback-terugkoppeling.png` (open CE-04-regel, slot `c8d60edd-…`) |
| 3 | Agendadatums nl-NL | **Deels** | Weekregels nl (`5 October`, `kies een thema`); **kop blijft Engels** (`Agenda _ 19 October 2026`) — zie `11-agenda-signalen.png` |
| 4 | Ritmetekst Overzicht niet afgekapt | **Klaar** | `01-overzicht.png` — volledige zin “Achter op ritme deze week” |
| 5 | Rapport: geen `(HBA v1.7)` in nieuw titelveld / lijst | **Klaar** | `10-rapport.png` — veld `Merkrapport 2026-09-19 – 2026-10-19`; eerdere rapporten zonder HBA-suffix |
| 6 | Hashtags meenemen in controle (scoring + CE-03) | **Klaar (code)** | Scoring: `ce_hba_article_post_text()` voegt `hashtags_final`/`hashtags_suggested` toe (`web/api/hba-repository.php`). CE-03: hashtag-regel in `scripts/generate-ce03-workflow.py` (gepusht naar n8n staging). Niet zichtbaar op deze vijf shots. |

## Overige A6 (stap 5/6, niet opnieuw gefotografeerd)

Thema’s, Onderzoek, Planning, Vindbaarheid, Artikel-meta: ongewijzigd t.o.v. `docs/a6-screenshots-staging-2026-10-18/` tenzij Rogier opnieuw wil.

## Deploy

- `./scripts/deploy-ftp.sh staging` — smoke 5 pass (3 event_log-fouten 24u, niet blokkerend).
- `./scripts/push-ce03-n8n.sh staging` — webhook `ce03-write-article-staging`.

## Niet in scope stap 4

- Geen derde 5×19, geen scorer-/volgorde-wijzigingen (afronden-briefing).
