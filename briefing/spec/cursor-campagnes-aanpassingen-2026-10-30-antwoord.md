# Antwoord op campagnes-aanpassingen-2026-10-30 — richting voor Cursor

**Datum:** 2026-10-30
**Bron:** `briefing/hba-input/campagnes-aanpassingen-2026-10-30.md` (Rogier, na doorloop van 30 okt). Dit document beantwoordt de open vragen die nodig zijn om te kunnen starten, en legt vast wat al besloten is. De overige open vragen (die Rogier zelf moet invullen of die pas na §1 scherp te maken zijn) staan onderaan, zodat ze niet verloren gaan.

## Antwoord aan Rogier — §2: intelligentie achter Touch/Tell/Sell

**Twee momenten, ander type check.**

- **Stap 1:** lichte, statische merk-fit-hint, geen blokkade. Op dat moment is alleen het type gekozen, er is nog geen plan. Toets tegen de verboden logica van het merk — bijv. merk met "nooit afprijzen" + keuze Sell → zachte waarschuwing dat Sell kan wringen, met ruimte om toch door te gaan.
- **Stap 8:** de echte plan-check (`ce_hba_plan_check`) op het concrete plan — Branchehygiëne, Regel, Dekking, enz. Kan niet naar voren, want die checks hebben inhoud nodig die er op stap 1 nog niet is.

**Copy-modus.** Sell/Tell/Touch bepaalt niet alleen het fase-sjabloon, maar moet ook een copy-modus per thema meegeven aan de artikelmodus: Sell = activatie/CTA toegestaan; Tell/Touch = geen sales-taal, nadruk op mechanisme/bewijs. Zonder die koppeling kan iemand een Tell-thema met Sell-toon schrijven en wordt dat pas op stap 8 gesignaleerd, in plaats van voorkomen.

**Tell vs Touch — niet hetzelfde sjabloon.** Beide zijn merkbouw, maar met een ander ritmedoel (Touch = licht/frequent, Tell = diep/opbouwend). Eén onderliggende serie-opbouw-engine, met per type een ander fase-aantal/diepteniveau:
- **Tell:** de huidige volledige serie-opbouw.
- **Touch:** lichter — zie item 6 hieronder voor het voorstel.

## Antwoord aan Cursor — §7: wat doet de app met de posts

Advies- en signaal-interacties moeten een plek in het datamodel krijgen die doorwerkt tot aan het schrijven van het artikel, niet alleen een UI-vinkje op dat moment.

**Advies (volgen / niet volgen / zelf aanpassen):**
- *Volgen* — app past de wijziging direct toe op het plan (los post of hele opbouw, afhankelijk van het advies); het postvoorstel update meteen zichtbaar.
- *Niet volgen* — plan blijft ongewijzigd, maar de keuze wordt vastgelegd bij de campagne (voor het campagneverslag — anders is later niet te zien of een advies bewust genegeerd is).
- *Zelf aanpassen* — de ingevulde tekst wordt een veld op het betrokken thema ("aanvullende instructie"), dat meegaat naar de artikelmodus zodra dat thema geschreven wordt.

**Signaal (akkoord / aanpassen):**
- *Akkoord* — bevestiging vastleggen, geen wijziging.
- *Aanpassen* — tekst landt afhankelijk van het signaaltype: info-gaten (bv. "wat maakt dit aanbod anders?") gaan naar alle betrokken thema's (bv. de Bewijs-fase); post-specifieke signalen (bv. Regel op één post) gaan alleen naar dat thema; opbouw-brede signalen (Balans, Dekking) moeten het plan laten herberekenen, niet alleen een los tekstveld opslaan.

De precieze regel voor "welke thema's" bij elk signaaltype (vraag 14) is onderdeel van de stap-7-redesign zelf en hoeft nu niet voorafgaand vastgelegd te worden.

## Besloten — vragen 6, 11, 17, 18

**6 — Touch-fases.** Vaste set voor alle merken, geen per-merk-variant in fase 1. Voorstel: 3 fases — Herkenning (40%), Anker/Inzicht (35%), Call-to-connect (25%). Als blijkt dat bepaalde merken een andere verdeling nodig hebben, is dat een latere iteratie, geen uitgangspunt nu.

**11 — Kanalen-limiet.** 100 slots blijft de harde cap (arbitraire systeemgrens, los van fase-logica). De validatie "te weinig posts voor fases" verhuist naar stap 5 — fouten zo vroeg mogelijk tonen, net als bij het 8→84-veldbugje.

**17 — Volgorde.** Akkoord met Rogier: §1 (thema's i.p.v. artikelen) eerst — fundamenteel datamodelwerk waar de rest op rust. Daarna de Touch/Tell/Sell-flow (stap 1-6). Stap-7-redesign laatste: heeft de meeste open vragen en kan het beste scherp gemaakt worden met een werkend thema-model om tegen te testen.

**18 — Preview-scope.** Akkoord: blijf op staging, geen sjablonen C-F tot er een bureau-preview is geweest.

## Nog open — voor Rogier of pas na §1 te beantwoorden

Deze blokkeren de start van §1 niet, maar moeten voor hun eigen onderdeel beantwoord zijn:

- **§1 (1-5):** datamodel thema-vs-slot, artikelmodus-koppeling, kalenderweergave van "nog te schrijven" vs "klaar", publicatietijd overal, campagne-overzicht als bewerkbare lijst vs. alleen agenda.
- **§2 (7-8):** omvang van de stap-1-hint (alleen regels, of ook archetype/wat_bewust_niet/doel), en of de copy-modus per thema overschrijfbaar is.
- **§4-5 (9-10):** gecombineerde SEO+GEO-lijst of twee secties, max per type; schakelaar-default (alle vijf merkonderdelen aan).
- **§7 (12-16) besloten:** 12 echte stappen uit de API (Plan maken, Check uitvoeren, Posts beoordelen). 13 Volgen bij Balans/Dekking herberekent het hele plan. 14 Welke thema's een signaal raakt, kiest de gebruiker. 15 Negeren is toegestaan; bevestigen mag met open signalen. 16 Wijzigen vóór bevestigen is een directe PATCH op de slots, daarna loopt de plan-check opnieuw.

**Volgende stap:** Cursor kan starten met §1 op basis van dit document; vragen 1-5 daarbinnen moeten bij de start van die slice alsnog beantwoord worden (het zijn implementatiekeuzes, geen Rogier-vragen).

---
🤖 Generated with [Claude Code](https://claude.com/claude-code)
