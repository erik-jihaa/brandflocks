/**
 * FAQ letterlijk uit klikmodel v271 (p-help / faqData).
 * Bron: docs/briefings/klikmodel-brandflocks-export-2026-11-14.html
 * Extract 14 nov 2026 — tekst niet parafraseren; Rogier heeft FAQ nog niet goedgekeurd.
 */

export type HelpFaqItem = { v: string; a: string }
export type HelpFaqTopic = { topic: string; vragen: HelpFaqItem[] }

export const HELP_FAQ: HelpFaqTopic[] = [
  {
    topic: "Onboarding & eerste stappen",
    vragen: [
      {
        v: "Wat moet ik als eerste instellen?",
        a: "Je merkarchitectuur bij Merkarchitectuur: Wie je bent, Hoe je werkt en Taal. Zonder die velden kan de app geen content voor je maken."
      },
      {
        v: "Moet ik de hele HBA-theorie kennen om te starten?",
        a: "Nee. Kies bij \"Wie je bent\" voor \"Beantwoord de vragen\" — een gids van 8 simpele vragen die je architectuur voor je opbouwt."
      },
      {
        v: "Wat is het verschil tussen \"Vul structuur in\" en \"Beantwoord de vragen\"?",
        a: "\"Vul structuur in\" is voor wie de architectuur al scherp heeft; \"Beantwoord de vragen\" bouwt die voor je op via 8 vragen. Beide eindigen in dezelfde velden."
      },
      {
        v: "Waar vind ik mijn Trello- of WordPress-API-key?",
        a: "Vraag het de Onboarding-assistent hierboven, of mail Erik."
      },
      {
        v: "Hoeveel tijd kost onboarding?",
        a: "Meestal een kwartier voor de merkarchitectuur, plus het koppelen van je kanalen bij Koppelingen."
      },
      {
        v: "Kan ik later nog dingen aanpassen?",
        a: "Ja, alles bij Merkarchitectuur, Bronnen en Instellingen is op elk moment te bewerken."
      },
      {
        v: "Wat gebeurt er als ik iets oversla?",
        a: "Velden die leeg blijven, zie je terug als aandachtspunt in de architectuurcheck op Merkarchitectuur."
      },
      {
        v: "Moet ik eerst Bronnen vullen of eerst Merkarchitectuur?",
        a: "Eerst Merkarchitectuur — Bronnen en artikelen worden daar straks tegen gemeten."
      },
      {
        v: "Kan iemand anders namens mij onboarden?",
        a: "Ja, voeg diegene toe bij Account → Contactpersonen."
      },
      {
        v: "Wat als ik vastloop?",
        a: "Gebruik de Onboarding-assistent hierboven, of stel je vraag onderaan deze pagina."
      }
    ]
  },
  {
    topic: "Merkarchitectuur",
    vragen: [
      {
        v: "Wat is de Story Triangle?",
        a: "Rol, Doel en Tegenkracht samen: wie je bent, wat je wilt bereiken, en wat dat tegenhoudt."
      },
      {
        v: "Wat is het verschil tussen Tegenkracht en Hoofdregel?",
        a: "Tegenkracht is wat je bestrijdt; de Hoofdregel is een aanwijsbare weigering die daaruit volgt en die je iets kost."
      },
      {
        v: "Waarom heb ik Subregels naast een Hoofdregel?",
        a: "Een merk heeft vaak meerdere regels van wisselend gewicht; de Hoofdregel is de zwaarste, Subregels vullen aan."
      },
      {
        v: "Wat is een Markt-archetype?",
        a: "Het archetype dat je hele branche al speelt — de hygiënetaal, geen onderscheid op zich. De app meet je content hier later tegen af."
      },
      {
        v: "Wat is het verschil tussen de Story Triangle en de volledige architectuur?",
        a: "De Story Triangle is alleen Rol, Doel en Tegenkracht; met Mechanisme (Hoe je werkt) en Regel(s) erbij heet het de volledige merkarchitectuur."
      },
      {
        v: "Wat is \"Hoe je werkt\"?",
        a: "Het mechanisme: hoe je regel in de praktijk werkt, met bewijs."
      },
      {
        v: "Wat betekent de melding \"Nog aan te scherpen\" bij mijn architectuur?",
        a: "De architectuurcheck signaleert welk veld zwak of leeg is — dit blokkeert niets, het is een aanwijzing."
      },
      {
        v: "Kan ik een HBA-strateeg laten meekijken?",
        a: "Ja, die optie staat bij de architectuurcheck en op het bevestigingsscherm van de 8-vragenwizard."
      },
      {
        v: "Wat gebeurt er als mijn Rol samenvalt met het Markt-archetype?",
        a: "De architectuurcheck waarschuwt je: dat is dan de toegangsprijs van de branche, geen eigen onderscheid."
      },
      {
        v: "Hoe vaak moet ik mijn merkarchitectuur bijwerken?",
        a: "Alleen als er iets wezenlijks verandert aan wie je bent of wat je weigert — niet standaard periodiek."
      }
    ]
  },
  {
    topic: "Bronnen & Kennis",
    vragen: [
      {
        v: "Wat staat er bij Kennis → Bronnen?",
        a: "Je vaste bronnenbibliotheek, plus automatisch aangemaakte mappen per campagne en per artikel."
      },
      {
        v: "Waarom krijgt elke campagne een eigen map?",
        a: "Zodat alle bronnen, teksten en gebruikte merkonderdelen van die campagne overzichtelijk bij elkaar staan, en de app zelfstandig content kan maken op basis daarvan."
      },
      {
        v: "Kan ik een bron weer verwijderen?",
        a: "Ja, elk item is los te verwijderen, ook nadat een campagne of artikel al loopt."
      },
      {
        v: "Welke bestandstypen kan ik uploaden?",
        a: "Documenten en links; zie de upload-knop bij elk bronnenblok voor de actuele ondersteunde typen."
      },
      {
        v: "Gebruikt de app ook bronnen buiten mijn bibliotheek?",
        a: "Nee, alleen wat jij toevoegt bij Bronnen, of specifiek bij een artikel of campagne."
      },
      {
        v: "Wat is het verschil tussen SEO/GEO-vragen en gewone bronnen?",
        a: "SEO/GEO-vragen zijn de zoekvragen die je content moet beantwoorden; bronnen zijn het feitenmateriaal daarachter."
      },
      {
        v: "Kan ik bronnen delen tussen campagnes?",
        a: "Nog niet automatisch; voeg de bron toe aan de map van elke campagne waar hij relevant voor is."
      },
      {
        v: "Wie kan bronnen toevoegen of verwijderen?",
        a: "Iedereen met toegang tot het account; er is geen apart rechtenniveau voor Bronnen."
      },
      {
        v: "Waarom zie ik bij een artikel ook een eigen bronnenmap?",
        a: "Net als bij campagnes: zodat losse artikelen ook hun eigen, herleidbare bronnen hebben."
      },
      {
        v: "Hoe weet de app welke bron bij welk artikel hoort?",
        a: "Via de map waarin je de bron hebt toegevoegd, gekoppeld aan dat specifieke artikel of die campagne."
      }
    ]
  },
  {
    topic: "Artikelen schrijven",
    vragen: [
      {
        v: "Wat zijn de drie manieren om een artikel te starten?",
        a: "\"Eén artikel\", \"Meerdere artikelen\", en \"Ik heb het artikel al\" (voor een kant-en-klare tekst)."
      },
      {
        v: "Wat gebeurt er bij \"Meerdere artikelen\"?",
        a: "De app stelt een paar invalshoeken voor om hetzelfde thema uit te splitsen."
      },
      {
        v: "Wat als ik zelf al een tekst heb geschreven?",
        a: "Kies \"Ik heb het artikel al\", upload of plak je tekst — je slaat de Onderzoek-stap over en gaat direct naar Artikel-detail."
      },
      {
        v: "Gaat mijn eigen tekst ook langs de merkcontrole?",
        a: "Ja, precies dezelfde check en aandachtspunten als een door de app geschreven artikel."
      },
      {
        v: "Wat doet de stap Onderzoek?",
        a: "Daar beoordeel je de bronfragmenten die de app heeft gevonden, voordat het artikel geschreven wordt."
      },
      {
        v: "Kan ik een artikel nog aanpassen na het schrijven?",
        a: "Ja, bij Artikel-detail kun je de tekst altijd bewerken voordat je hem indient."
      },
      {
        v: "Wat is een Call-to-action bij een artikel?",
        a: "Jij geeft zelf aan of de post een CTA heeft; de app stelt daar desgewenst drie voorbeeldteksten voor voor."
      },
      {
        v: "Waarom is LinkedIn soms een artikel en soms een post?",
        a: "Bij Campagne stap 4 kies je per kanaal of LinkedIn-content een post of een artikel wordt; bij een artikel maakt de app er automatisch een bijbehorende post bij."
      },
      {
        v: "Kan ik een artikel als concept bewaren?",
        a: "Ja, met \"Opslaan als concept\" bij Artikel-detail."
      }
    ]
  },
  {
    topic: "Campagnes plannen",
    vragen: [
      {
        v: "Wat zijn de 6 stappen van een campagne?",
        a: "Touch/Tell/Sell, Titel, Merkonderdelen & vragen, Aantal artikelen en kanalen, Periode, en Klopt het plan? (met daarin de ritme-check)."
      },
      {
        v: "Wat is het verschil tussen Touch, Tell en Sell?",
        a: "Touch en Tell rekenen als merkbouw; Sell rekent als activatie en gebruikt de opbouw van een lancering."
      },
      {
        v: "Hoe kies ik de campagneperiode?",
        a: "Via de kalender bij stap 5: wijs per artikel een dag toe."
      },
      {
        v: "Wat gebeurt er als ik te veel posts inplan voor de gekozen periode?",
        a: "De app waarschuwt je met een melding die het aantal posts koppelt aan de lengte van de periode."
      },
      {
        v: "Wat toetst de ritme-check?",
        a: "Of je campagne past binnen het basisritme dat je bij Instellingen → Planning hebt ingesteld; te vinden bij stap 6 \"Klopt het plan?\", onder \"Systeemcheck\"."
      },
      {
        v: "Kan ik de voorgestelde posts nog aanpassen voor ik de campagne bevestig?",
        a: "Ja, bij stap 6 \"Klopt het plan?\" zie je het volledige postvoorstel en kun je per post naar Onderzoek om die te schrijven."
      },
      {
        v: "Moet ik eerst alles schrijven voor ik de campagne kan bevestigen?",
        a: "Ja. \"Campagne bevestigen\" is pas te klikken zodra elke check bij het Postvoorstel is ingediend bij de tegenlezer. Per artikel zijn dat meerdere checks: één per kanaal, en bij een LinkedIn-artikel ook de aankondigingspost."
      },
      {
        v: "Schrijf ik bij een campagne apart voor LinkedIn en Instagram?",
        a: "Nee, net als bij een los artikel: je schrijft één keer per artikel, en past met de kanaal-pills de vorm per kanaal aan. De tegenlezer krijgt elke kanaalversie wel als eigen check te zien, zodat bijvoorbeeld alleen de Instagram-versie kan worden teruggestuurd."
      },
      {
        v: "Krijgt elke campagne een eigen kleur?",
        a: "Ja, automatisch bij het aanmaken, zodat je in de Kalender in één oogopslag ziet welke posts bij welke campagne horen."
      },
      {
        v: "Wat als een losse actie tussen mijn basisritme door moet?",
        a: "De app stelt voor bestaande, niet-tijdgebonden posts naar een andere week te schuiven in plaats van op te stapelen."
      },
      {
        v: "Kan ik bronnen specifiek voor deze campagne toevoegen?",
        a: "Ja, bij stap 3 \"Welke vragen en feiten gaan mee?\", onder \"Bronnen voor deze campagne\"."
      },
      {
        v: "Waar vind ik een lopende campagne terug?",
        a: "Bij Kennis → Bronnen staat de campagnemap, en in de Kalender zie je de posts in de campagnekleur."
      }
    ]
  },
  {
    topic: "Kalender & planning",
    vragen: [
      {
        v: "Wat is het verschil tussen de Kalender- en Lijstweergave?",
        a: "Dezelfde posts, alleen anders gerangschikt — de Kalender toont per dag, de Lijst toont gegroepeerd en doorzoekbaar."
      },
      {
        v: "Hoe herken ik welke post bij welke campagne hoort?",
        a: "Aan de campagnekleur; de legenda bovenaan de Kalenderweergave laat zien welke kleur bij welke campagne hoort."
      },
      {
        v: "Welke kleur hebben losse artikelen zonder campagne?",
        a: "Blauw — de standaardkleur voor niet-campagne content."
      },
      {
        v: "Kan ik een post direct vanuit de Kalender bewerken?",
        a: "Ja, via de \"Bewerk\"-link in de Lijstweergave, of door in de Kalender op een post te klikken."
      },
      {
        v: "Wat is mijn planhorizon?",
        a: "Hoe ver vooruit de app standaard plant; stel dit in bij Instellingen → Planning."
      },
      {
        v: "Hoe vaak checkt de app mijn bronnen?",
        a: "Volgens het aantal broncontroles per week dat je bij Planning hebt ingesteld."
      },
      {
        v: "Wat gebeurt er met posts als ik mijn basisritme wijzig?",
        a: "Al geplande posts blijven staan; het nieuwe ritme geldt voor wat de app daarna plant."
      },
      {
        v: "Kan ik meerdere kanalen tegelijk in de Kalender zien?",
        a: "Ja, kanaal staat bij elke post vermeld, in beide weergaven."
      },
      {
        v: "Wie bepaalt de volgorde van rollen per week?",
        a: "Het voorgestelde ritme (Positionering, Kennis delen, Bewijs) bij Instellingen → Planning, zelf aan te passen."
      },
      {
        v: "Wat als twee campagnes dezelfde week overlappen?",
        a: "Beide kleuren zijn gewoon zichtbaar in de Kalender; er is geen automatische conflictwaarschuwing."
      }
    ]
  },
  {
    topic: "Rapport & meting",
    vragen: [
      {
        v: "Wat laat de spreidingsgrafiek op Rapport zien?",
        a: "Elke post als stip: verticaal de consistentie met je merk, horizontaal waar de post tussen activatie en merkbouw in staat — dat plaats je zelf met een schuifje bij de post, los van of er een CTA in staat."
      },
      {
        v: "Hoe bepaalt de app of een post activatie of merkbouw is?",
        a: "Dat plaats jij zelf bij Artikel-detail met een schuifje onder \"Merkbouw of activatie?\" — geen automatische tekstanalyse. Je kunt het schuifje helemaal naar één kant zetten, of ergens in het midden laten staan als de post een beetje van beide is. De app kan niet betrouwbaar uit de tekst afleiden wat je bedoeling met de post was; de consistentie met je merk (verticaal in de grafiek) wordt wel automatisch gescoord."
      },
      {
        v: "Waarom worden oudere posts lichter weergegeven?",
        a: "Zodat je in één oogopslag ziet welke stippen recent zijn en welke verder terugliggen."
      },
      {
        v: "Kan ik een post vanuit de grafiek openen?",
        a: "Ja, een klik op een stip opent het Artikel-detailscherm van die post."
      },
      {
        v: "Hoeveel posts toont de grafiek maximaal?",
        a: "Twintig, de meest recente."
      },
      {
        v: "Wat betekent een hoge score voor \"Consistentie met merk\"?",
        a: "Hoe hoger, hoe dichter de post bij je merkarchitectuur zit."
      },
      {
        v: "Kan ik de weekgemiddeldes ook los van de spreiding bekijken?",
        a: "Ja, die staan los op Rapport, boven de spreidingsgrafiek."
      },
      {
        v: "Wordt Rapport automatisch bijgewerkt?",
        a: "Ja, elke nieuwe ingediende of geplande post komt erin mee."
      },
      {
        v: "Kan ik Rapport delen met iemand buiten de app?",
        a: "Nog niet apart uitgewerkt in dit klikmodel; vraag het bij Help als je dit nodig hebt."
      },
      {
        v: "Wat als ik het schuifje bij een post niet zelf aanpas?",
        a: "Dan staat die standaard helemaal op merkbouw, tot je het schuifje zelf verplaatst."
      }
    ]
  },
  {
    topic: "Merkcontrole & aandachtspunten",
    vragen: [
      {
        v: "Wat toetst de merkcontrole op een artikel?",
        a: "Of de tekst bij je Rol, Doel, Tegenkracht en Regel past, met concrete aandachtspunten per zwakke claim."
      },
      {
        v: "Wat is een \"zwakste claim\"?",
        a: "De bewering in je tekst die het minst onderbouwd of het minst bij je architectuur past."
      },
      {
        v: "Kan ik een AI-advies negeren?",
        a: "Ja, met de knop \"Negeer\" — de regie blijft bij jou."
      },
      {
        v: "Wat gebeurt er als mijn merkarchitectuur niet compleet is?",
        a: "De merkcontrole kan dan geen betrouwbaar oordeel geven; vul eerst Merkarchitectuur aan."
      },
      {
        v: "Hoe vaak draait de merkcontrole?",
        a: "Bij elk artikel dat je schrijft of indient, en bij elke wijziging aan je merkarchitectuur."
      },
      {
        v: "Is de merkcontrole hetzelfde als de architectuurcheck?",
        a: "Nee: de architectuurcheck beoordeelt je merkarchitectuur zelf, de merkcontrole beoordeelt losse content ertegen."
      },
      {
        v: "Wat als ik het oneens ben met een aandachtspunt?",
        a: "Je kunt het negeren of de tekst zelf aanpassen; niets wordt afgedwongen."
      },
      {
        v: "Wordt elke zin apart beoordeeld?",
        a: "Nee, de check kijkt naar het geheel en licht de sterkste en zwakste onderdelen eruit."
      },
      {
        v: "Waar zie ik mijn merkarchitectuur-score per artikel?",
        a: "Rechts naast de tekst bij Artikel-detail, onder \"Score\"."
      }
    ]
  },
  {
    topic: "Koppelingen & publiceren",
    vragen: [
      {
        v: "Welke publicatiemodi zijn er?",
        a: "Handmatig met tegenlezer, en automatisch publiceren."
      },
      {
        v: "Waar stel ik WordPress-publicatie in?",
        a: "Bij Koppelingen → Website."
      },
      {
        v: "Welke kanalen kan ik koppelen?",
        a: "In elk geval LinkedIn en Instagram; zie Koppelingen voor de actuele lijst."
      },
      {
        v: "Wat doet de tegenlezer in \"Handmatig met tegenlezer\"?",
        a: "Een laatste menselijke blik voordat een post live gaat."
      },
      {
        v: "Kan ik per artikel een andere publicatiemodus kiezen?",
        a: "Nee, de modus staat accountbreed bij Account → Publicatiemodus."
      },
      {
        v: "Wat gebeurt er als een koppeling verbroken raakt?",
        a: "De app meldt dit en plant niets automatisch door tot de koppeling hersteld is."
      },
      {
        v: "Kan ik Analytics koppelen voor resultaten?",
        a: "Ja, via Koppelingen; vraag de Onboarding-assistent naar de precieze stappen."
      },
      {
        v: "Wie krijgt de review-link na indienen?",
        a: "De tegenlezer, ook zonder dat er een mail bij hoort."
      },
      {
        v: "Welke statussen doorloopt een artikel of post?",
        a: "Concept, Ingediend, Goedgekeurd, en Gepubliceerd. Gebruikt een artikel meerdere kanalen, dan krijgt elke kanaalversie zijn eigen status; bij een LinkedIn-artikel komt de aankondigingspost er als losse check bij. Te zien bij Alle artikelen en, bij een campagne, in het Postvoorstel."
      },
      {
        v: "Wat betekent \"Goedkeuringen afhandelen\" bij Te doen?",
        a: "Dit zijn artikelen die de tegenlezer al heeft goedgekeurd; jij geeft ze met \"Publiceren\" vrij voor publicatie."
      },
      {
        v: "Kan ik meerdere WordPress-sites koppelen?",
        a: "Nog niet apart uitgewerkt in dit klikmodel."
      },
      {
        v: "Wat als ik tijdelijk niet wil publiceren?",
        a: "Zet de publicatiemodus op handmatig, dan gaat niets automatisch live."
      }
    ]
  },
  {
    topic: "Account, kosten & instellingen",
    vragen: [
      {
        v: "Waar wijzig ik mijn bedrijfsnaam of logo?",
        a: "Bij Account → Bedrijfsprofiel."
      },
      {
        v: "Hoe voeg ik een contactpersoon toe?",
        a: "Bij Account → Contactpersonen."
      },
      {
        v: "Waar zie ik mijn verbruik?",
        a: "Bij Help → Kosten & verbruik, of via de rekentool daar."
      },
      {
        v: "Wat valt onder \"overige diensten\" in de kosten?",
        a: "Alles buiten AI-gebruik en dataverkeer; zie de rekentool voor de uitsplitsing."
      },
      {
        v: "Hoe wijzig ik mijn wachtwoord?",
        a: "Bij Account → Wachtwoord."
      },
      {
        v: "Kan ik mijn planhorizon per kanaal instellen?",
        a: "Nee, die staat accountbreed bij Instellingen → Planning."
      },
      {
        v: "Wat gebeurt er met mijn chatbudget bij de Onboarding-assistent?",
        a: "Je ziet het verbruikte bedrag van het beschikbare budget bovenaan dat blok; bij overschrijding stuur je je vraag door naar Erik."
      },
      {
        v: "Kan ik mijn account tijdelijk pauzeren?",
        a: "Nog niet apart uitgewerkt in dit klikmodel; neem contact op via Help."
      },
      {
        v: "Wie heeft toegang tot mijn facturatiegegevens?",
        a: "De contactpersonen die je bij Account hebt toegevoegd."
      },
      {
        v: "Waar vind ik de technische documentatie?",
        a: "Onderaan deze Help-pagina, bij \"Documentatie: igisnext.com\"."
      }
    ]
  }
]
