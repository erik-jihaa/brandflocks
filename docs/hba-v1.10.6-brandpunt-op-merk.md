# HBA v1.10.6 — Brandpunt *Op merk* (post 1)

**Stap 3** in de bouwvolgorde na 5×19 v1.10.5.  
Prompt: **v1.10.6**. Geen deploy in deze stap.

## Probleem (v1.10.5, 5×19)

Post 1: *“Vandaag nieuwe oogst binnen uit Huila. … Wat na zes weken niet verkocht is, gaat de winkel uit.”*  
Verwacht: **`op_merk`**, geen `category_fallback`.

Uitkomst: **2/5 goed**. In 3 runs `category_fallback` op de openingszin; het model noemde de zes-wekenregel wel, maar eiste extra dat die “als keuze die iets kost” wordt uitgelegd.

Post 6 (markthygiëne, geen zes-wekenregel) bleef terecht 5× `categorietaal`.

## Aanpassing

In `hba-flags-definitie.txt` en `hba-hoofdoordeel-definitie.txt`:

- kern = de **hele** tekst, niet automatisch de openingszin;
- merkregel **noemen** is genoeg (geen kost-uitleg);
- openingszin oogst/rusttijd/branddatum + regel later → **geen** `category_fallback`, hoofdoordeel **`op_merk`**;
- dezelfde openingszin **zonder** merkregel → `category_fallback` / `categorietaal` (post 6).

## Klaar als

- [x] Staging-deploy v1.10.6 (smoke 6/6)
- [x] Gerichte 5× Brandpunt post 1 → 5/5 `op_merk`, geen `category_fallback`
- [x] Geen regressie Brandpunt post 6 (5× `categorietaal` + `category_fallback`)

Bewijs: `docs/hba-v1.10.6-kalibratie-brandpunt-1-6.md`
