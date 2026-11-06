# Instructie voor Eva — LinkedIn bedrijfspagina koppelen (iGiSNext)

**Doel:** Via jouw LinkedIn-profiel de koppeling regelen zodat Content Engine artikelen kan plaatsen op de **iGiSNext-bedrijfspagina** (niet alleen op een persoonlijk profiel).

**Wie:** Eva (of iemand die **beheerder** is van de iGiSNext-pagina op LinkedIn)  
**Waar:** LinkedIn + Content Engine Account (https://ce.igisnext.com/account)  
**Techniek (achtergrond):** LinkedIn vereist producttoegang *Community Management* / posten namens een organisatie. CE is daar al technisch op voorbereid; jij regelt de rechten bij LinkedIn.

---

## Deel A — Check: ben jij beheerder van iGiSNext op LinkedIn?

1. Log in op [linkedin.com](https://www.linkedin.com) met **jouw** account.
2. Zoek de pagina **iGiSNext** (Company Page).
3. Open **Beheerdersweergave** / **Admin tools** van die pagina (menu links of via `linkedin.com/company/…/admin`).
4. Ga naar **Paginabeheer** → **Beheerders** (of *Admins*).
5. Controleer of **jouw profiel** in de lijst staat met rol **Super admin** of **Content admin** (minimaal rechten om content te plaatsen).

**Als je geen beheerder bent:** vraag de huidige super admin van iGiSNext om jou toe te voegen als beheerder. Zonder dat lukt posten namens de pagina niet.

---

## Deel B — LinkedIn Developer-app (Erik / platform)

Deze stappen doet meestal **Erik** (of wie de LinkedIn-app beheert). Eva hoeft dit alleen te weten als er iets vastloopt.

1. In [LinkedIn Developers](https://www.linkedin.com/developers/apps) opent Erik de app die Content Engine gebruikt.
2. Tab **Products** → vraag toegang aan voor **Community Management API** (of het product dat *posten namens een organisatie* toestaat).
3. Vul het aanvraagformulier in: doel = automatisch goedgekeurde blogposts publiceren op de **eigen** iGiSNext-pagina via Content Engine.
4. Wacht op **goedkeuring door LinkedIn** (kan enkele dagen duren).

**Redirect-URL** (moet al kloppen):  
`https://ce.igisnext.com/api/account/linkedin/oauth/callback`

---

## Deel C — Bedrijfspagina-ID voor Content Engine

1. Blijf in de **beheerdersweergave** van iGiSNext op LinkedIn.
2. Kijk in de **adresbalk** van de browser. De URL ziet er ongeveer zo uit:  
   `https://www.linkedin.com/company/12345678/admin/`  
   of  
   `https://www.linkedin.com/company/igisnext/admin/`
3. Noteer het **numerieke ID** als dat in de URL staat (alleen cijfers, bijv. `12345678`).  
   - Als je alleen `igisnext` in de URL ziet: open **Over** / **Page info** in admin, of vraag Erik het ID op te zoeken via LinkedIn Company Page-instellingen.
4. Bewaar dit nummer — dat is de **Bedrijfspagina-ID** voor CE.

---

## Deel D — Koppelen in Content Engine (jouw profiel → pagina)

**Belangrijk:** Koppel met het LinkedIn-account dat **beheerder** is van iGiSNext (jouw profiel).

1. Ga naar **https://ce.igisnext.com** en log in.
2. Zorg dat je in Account het **juiste klant-/tenant-profiel** hebt (iGiSNext), niet een sandbox van een andere klant.
3. Open **Account** in het menu.
4. Scroll naar **Wissel van account** (onderaan, boven **Save**).
5. Je ziet accounts met hetzelfde contact-e-mailadres (bijv. iGiSNext + Sandbox 2/3). Klik de gewenste tenant — je gaat terug naar Home in dat account.
6. Scroll verder naar **Publiceren naar website** (LinkedIn, website, enz.).
   - Staat LinkedIn nog niet in de lijst? Kies **Extra bestemming toevoegen** → **LinkedIn** → **Save**.
7. Als er al een koppeling staat op een **persoonlijk** profiel en je wilt overschakelen naar de **pagina**:
   - Klik **Ontkoppel** (LinkedIn).
   - Klik **Save** onderaan Account.
8. Vul bij **Bedrijfspagina-ID** het numerieke ID uit deel C in (alleen cijfers).
9. Klik **Koppel LinkedIn-profiel**.
10. LinkedIn opent — log in als **Eva** (beheerder van iGiSNext).
11. Geef **alle gevraagde toestemmingen** (inclusief posten / organisatie, als LinkedIn dat toont).
12. Je komt terug in CE → controleer de groene melding **Gekoppeld als …**
13. Klik **Save** onderaan Account (velden + Bedrijfspagina-ID moeten opgeslagen zijn).

---

## Deel E — Test met één artikel

1. Open een artikel dat status **approved** heeft.
2. Klik **Publiceer** (zelfde knop als voor de website).
3. Lees de melding onder de knop:
   - **Succes:** regel met **LinkedIn: gepubliceerd** + link.
   - **Fout:** rode tekst (noteer exact de zin voor Erik).

4. Controleer op LinkedIn:
   - Open de **iGiSNext-bedrijfspagina** → tab **Posts**.
   - Het artikel zou daar moeten staan (niet alleen op jouw persoonlijke feed).

---

## Veelvoorkomende problemen

| Symptom | Wat te doen |
|--------|-------------|
| Geen optie “organisatie” / posten mislukt met rechten | Community Management API nog niet goedgekeurd (deel B) — even wachten of Erik checkt de app. |
| Post verschijnt op **jouw** profiel, niet op iGiSNext | Bedrijfspagina-ID leeg of fout; opnieuw invullen, **Ontkoppel** → opnieuw **Koppel**, **Save**. |
| “LinkedIn niet gekoppeld” | Opnieuw **Koppel LinkedIn-profiel** in Account; daarna **Save**. |
| Verkeerde klant (sandbox) | Account → **Wissel van account** (onderaan) of opnieuw inloggen en tenant kiezen. |

---

## Kort checklist voor Eva

- [ ] Ik ben **beheerder** van de iGiSNext-pagina op LinkedIn  
- [ ] **Bedrijfspagina-ID** genoteerd  
- [ ] In CE Account: ID ingevuld, LinkedIn **gekoppeld**, **Save** gedrukt  
- [ ] Erik bevestigt dat **Community Management API** is goedgekeurd (vóór definitieve pagina-test)  
- [ ] Eén testpost staat op **iGiSNext** (company posts), niet alleen persoonlijk  

---

*Laatste update: 23 juli 2026 — bij vragen: Erik + sectie “LinkedIn publish — multi-tenant” in `docs/BUILD-NOTES.md`.*
