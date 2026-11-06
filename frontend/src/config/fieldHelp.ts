export interface FieldHelp {
  key: string
  /** Klantnaam van het veld — o.a. voor Help me verder (fase 3). */
  label: string
  what: string
  where?: string
  steps?: string[]
  looksLike?: string
  reassure?: string
}

export const FIELD_HELP = {
  wordpress_app_password: {
    key: 'wordpress_app_password',
    label: 'WordPress app-wachtwoord',
    what:
      'Dit is níét je gewone wachtwoord. WordPress kan een apart wachtwoord maken speciaal voor programma\'s die artikelen publiceren. Zo hoef je je eigen wachtwoord nergens in te vullen, en kun je deze toegang later intrekken zonder dat er verder iets stukgaat.',
    looksLike: 'vier groepjes van vier tekens, zoals abcd efgh ijkl mnop',
    reassure: 'Je website verandert hier niet van. Je geeft alleen toestemming.',
    steps: [
      'Log in op je eigen website, op het scherm waar je normaal een pagina aanpast.',
      'Klik links op Gebruikers en dan op je eigen naam.',
      'Scroll helemaal naar beneden tot je Application Passwords ziet — die zit onderaan verstopt.',
      'Typ bij de naam Publiceren en klik op de knop. Het wachtwoord verschijnt één keer: kopieer het meteen en plak het hier.',
    ],
  },
  wordpress_user: {
    key: 'wordpress_user',
    label: 'WordPress-gebruikersnaam',
    what:
      'De naam waarmee jij inlogt op je eigen website. Artikelen worden namens deze gebruiker geplaatst.',
    where: 'Staat op je profielpagina, achter Gebruikersnaam. Meestal is dit niet je e-mailadres.',
  },
  joomla_api_token: {
    key: 'joomla_api_token',
    label: 'Joomla API-token',
    what:
      'Een sleutel waarmee artikelen op je Joomla-site gezet mogen worden. Joomla maakt hem voor je aan; je hoeft niets te verzinnen.',
    reassure:
      'Werkt vanaf Joomla 4. Weet je niet welke versie je hebt? Klik op Help me verder — dan zoeken we het samen uit.',
    steps: [
      'Log in op het beheerscherm van je Joomla-site.',
      'Ga naar System → Plugins en zoek op token. Zet User – Joomla API Token en API Authentication – Token allebei aan.',
      'Ga naar Users, klik op je eigen gebruiker, en open het tabblad Joomla API Token.',
      'Klik op het oogje om de sleutel zichtbaar te maken en plak hem hier.',
    ],
  },
  site_ftp: {
    key: 'site_ftp',
    label: 'FTP-gegevens',
    what:
      'Bij een site zonder beheerscherm zetten we de bestanden er rechtstreeks op. Daar zijn vier dingen voor nodig, en die heeft de bouwer van je site.',
    where:
      'Vraag je websitebouwer of hostingpartij om host, gebruikersnaam, wachtwoord en het pad naar de website — vaak /httpdocs of /public_html.',
    reassure:
      'Weet je niet wie dat is? Klik op Help me verder, dan zoeken we het samen uit.',
  },
  analytics_property_id: {
    key: 'analytics_property_id',
    label: 'GA4 Property ID',
    what:
      'Het nummer van je website in Google Analytics. Daarmee weet Rapport welke site hij moet uitlezen.',
    looksLike:
      'een getal van negen cijfers, zoals 123456789 — niet G-XXXXXXX, dat is iets anders',
    steps: [
      'Ga naar analytics.google.com en kies bovenin de juiste website.',
      'Klik linksonder op het tandwiel (Beheer).',
      'In de middelste kolom staat Property-instellingen; de Property-ID staat rechtsboven.',
    ],
  },
  linkedin_organization_urn: {
    key: 'linkedin_organization_urn',
    label: 'LinkedIn bedrijfspagina-ID',
    what:
      'Vul je dit in, dan verschijnen posts op je bedrijfspagina in plaats van op je persoonlijke profiel. Laat je het leeg, dan post je als jezelf — dat mag ook.',
    looksLike: 'alleen cijfers, zoals 12345678',
    where:
      'Open je bedrijfspagina op LinkedIn in de beheerdersweergave. In de adresbalk van je browser staat een reeks cijfers na /company/. Dat nummer is het.',
    reassure:
      'Hiervoor moet LinkedIn je eerst toestemming geven om namens de pagina te posten. Lukt het niet meteen? Dat ligt niet aan jou — klik op Help me verder.',
  },
  mailbox_imap_host: {
    key: 'mailbox_imap_host',
    label: 'IMAP-host',
    what:
      'Het adres van de mailserver waar je nieuwsbrieven binnenkomen. Die mails tellen mee als input voor Thema\'s.',
    looksLike: 'mail.jouwbedrijf.nl of imap.gmail.com',
    reassure:
      'We vullen hem meestal automatisch goed in zodra je je e-mailadres invult. Klopt het niet, dan weet je provider het juiste adres — of klik op Help me verder.',
  },
  blog_overview_url: {
    key: 'blog_overview_url',
    label: 'Blogoverzicht-URL',
    what:
      'De pagina waarop alle blogs naast elkaar staan — niet één los artikel. De links naar onderliggende posts worden gevolgd en gebruikt als onderzoek.',
    looksLike: 'https://jouwsite.nl/blog/ of https://jouwsite.nl/blogs.html',
    reassure:
      'Hebben ze een RSS-feed, dan gebruiken we die automatisch. Zo niet, dan lezen we de overzichtspagina zelf.',
    steps: [
      'Open in je browser de pagina waar bezoekers alle blogs zien.',
      'Kopieer die URL uit de adresbalk.',
      'Plak hem hier en klik Check — je ziet hoeveel posts we vinden.',
    ],
  },
  youtube_channel_url: {
    key: 'youtube_channel_url',
    label: 'YouTube-kanaal-URL',
    what:
      'Het publieke YouTube-kanaal waarvan nieuwe video\'s als research-signalen mogen meetellen.',
    looksLike: 'https://www.youtube.com/@kanaal of https://www.youtube.com/channel/UC…',
    reassure:
      'We volgen het kanaal via de officiële RSS-feed — geen API-key nodig. Transcripties zitten er niet bij.',
  },
  asana_pat: {
    key: 'asana_pat',
    label: 'Asana Personal Access Token',
    what:
      'Je persoonlijke sleutel waarmee open taken uit het gekozen Asana-project gelezen mogen worden.',
    looksLike: 'lange tekst die begint met een cijfer/letter-combinatie',
    where:
      'Asana → My Settings → Apps → Personal access tokens → Create new token. Geef alleen leesrechten op het project dat je kiest.',
    reassure:
      'We slaan het token versleuteld op. Laat het veld leeg na opslaan om het huidige token te behouden.',
  },
  drive_file_url: {
    key: 'drive_file_url',
    label: 'Google Drive-bestands-URL',
    what:
      'Het document in Drive dat als bron mag dienen — alleen dit bestand, via de drive.file-scope.',
    looksLike: 'https://docs.google.com/document/d/…/edit of https://drive.google.com/file/d/…/view',
    reassure:
      'Koppel eerst met Google. Plak daarna de URL van het bestand dat je expliciet wilt delen.',
  },
  scraper_actor_url: {
    key: 'scraper_actor_url',
    label: 'Apify actor-URL',
    what:
      'Dit bepaalt welk hulpprogramma de gegevens ophaalt. Voor LinkedIn-profielen staat de juiste al ingevuld.',
    reassure:
      'Hier hoef je niets aan te veranderen. Wil je iets anders laten ophalen? Klik op Help me verder, dan zetten we het samen goed.',
  },
} as const satisfies Record<string, FieldHelp>

export type FieldHelpKey = keyof typeof FIELD_HELP

export function getFieldHelp(key: string): FieldHelp | undefined {
  if (key in FIELD_HELP) {
    return FIELD_HELP[key as FieldHelpKey]
  }
  return undefined
}

export function supportScreenLabel(fromPath: string): string {
  const path = fromPath.split('?')[0]?.trim() || fromPath
  if (path.startsWith('/integrations')) return 'Koppelingen'
  if (path.startsWith('/sources')) return 'Bronnen'
  if (path.startsWith('/account')) return 'Account'
  if (path.startsWith('/search')) return 'Vindbaarheid'
  if (path.startsWith('/planning')) return 'Planning'
  if (path.startsWith('/report')) return 'Rapport'
  if (path.startsWith('/theme')) return 'Thema\'s'
  if (path.startsWith('/brandarchitecture')) return 'Merkarchitectuur'
  if (path.startsWith('/styleguide') || path.startsWith('/strategy')) return 'Merkelementen'
  return 'de app'
}

/** Conceptvraag voor Support-chat — klant kan aanpassen vóór verzenden. */
export function buildSupportAskDraft(helpKey: string, fromPath?: string): string | null {
  const help = getFieldHelp(helpKey)
  if (!help) return null
  const screen = fromPath ? supportScreenLabel(fromPath) : 'de app'
  return `Ik sta op ${screen} bij "${help.label}" en kom er niet uit.`
}
