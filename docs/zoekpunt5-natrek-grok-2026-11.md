# Zoekpunt 5 — review 13 nov natrekken

**Datum:** 14 november 2026 · **Uitgevoerd door:** Cursor Composer (onafhankelijk van Claude leesronde)
**Basis:** `docs/briefings/review-bouwaddendum-en-hba-2026-11-13.md` §2 tabel
**Repo-stand:** `main` @ `070903f` (na §9-deploy; leesronde citeerde `81def5c`)

| # | Bewering review | Oordeel | Bewijs |
|---|-----------------|---------|--------|
| 1 | `hba-open-koppelpunten.php` ontbreekt | **Weerlegd** | Bestand `web/api/hba-open-koppelpunten.php` met `ce_hba_open_koppelpunten_catalog()` en `null`-placeholders (niet leeg comment-only). |
| 2 | Stap-1-hint = prijs-regex, geen AI | **Bevestigd** | `ce_campagne_sell_fit_hint()` in `campagne-preview-templates.php` (~472+): `preg_match` op prijswoorden. |
| 3 | Copy-modus gebouwd; los artikel → `merkbouw_bewijs` | **Deels** | Slot zonder thema/campagne: `ce_resolve_copy_modus_for_slot()` fallback `CE_COPY_MODUS_MERK` (`copy-modus.php` ~74). Template-key leeg in `ce_copy_modus_from_template_key('')` → **sell** (`copy-modus.php` ~24–25), niet merk — wijkt af van reviewtekst “los artikel”. |
| 4 | Toon per doelgroep in copy-call | **Bevestigd** | `ce_build_article_write_directive()` in `tone-styles.php` (~264+): tone via client/slot, terugval merktoon. |
| 5 | Kanaaltabel ingekort; `content_textvorm` niet geschreven; postvoorstel-check ontbreekt | **Deels** | Catalogus in `channel-copy-constraints.php` sluit inhoudelijk aan op addendum §3-tabel (13 rijen). PATCH `contentTextvorm` → kolom `content_textvorm` (`article-repository.php` ~559). Harde + zachte validatie en hints op artikel (`mappers.php`, `articles.php`). Review datum: nu grotendeels gebouwd; rest = API voor limits + grenstests (opdracht 1). |
| 6 | Pickup power / PCB ontbreekt | **Bevestigd** | Geen `pickup_power` scoring-call; alleen placeholder-keys in `hba-open-koppelpunten.php`. |
| 7 | Campagne = eigen slot/kanaal/datum; geen multi-kanaal één artikel | **Bevestigd** | Planning via `content_slots` per post (`campagne-preview-repository.php`); geen gedeeld artikel over kanalen in schema. |
| 8 | Oude CE-navigatie | **Deels** | `SidebarNav.tsx`: al Overzicht (Rapport/Te doen), Planning onder Instellingen, “Alle artikelen”. Nog: Merkelementen i.p.v. Merktaal, Agenda i.p.v. Kalender, Doelen + Thema’s/Onderzoek/Snel artikel in nav (opdracht 2). |

## Wat de review mist (eigen bevindingen)

- **Agenda/kalender §9** op `main`: Op campagne/Op datum, kanaalfilters, periode-overzicht campagne/artikel (`070903f`) — niet in §2-tabel.
- **Verbodslijst alleen architectuurcheck** (leesronde B1): vier andere AI-uitvoeren zonder `ce_hba_architecture_forbidden_hits_in_text`.
- **Nav gedeeltelijk herontwerp** zonder dat de review dat bijwerkt na nov-bouwslices.

## Niet gelezen / niet gedraaid

- Volledige `scripts/inventaris-dode-code.sh` (timeouts in leesronde).
- HBA v1.13 replay in deze sessie apart — wel groen in `test-all.sh` vóór opdracht 1.