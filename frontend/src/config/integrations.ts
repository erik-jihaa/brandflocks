export type IntegrationGroup = 'input' | 'publish' | 'measure'

export type IntegrationKey =
  | 'calendar'
  | 'mailbox'
  | 'apify_scraper'
  | 'website_cms'
  | 'ga4'

export type IntegrationDef = {
  key: IntegrationKey
  name: string
  group: IntegrationGroup
  purpose: string
  settingsHref?: string
  googleAccount?: boolean
}

export const INTEGRATION_GROUPS: { key: IntegrationGroup; label: string }[] = [
  { key: 'input', label: 'Input' },
  { key: 'publish', label: 'Publish' },
  { key: 'measure', label: 'Measure' },
]

export const INTEGRATIONS: IntegrationDef[] = [
  {
    key: 'calendar',
    name: 'Google Calendar',
    group: 'input',
    purpose: 'Agenda-events als input voor contentplanning.',
    settingsHref: '/sources/new/calendar',
    googleAccount: true,
  },
  {
    key: 'mailbox',
    name: 'Mailbox',
    group: 'input',
    purpose: 'IMAP-mailbox als source voor Theme.',
    settingsHref: '/sources/new/mailbox',
  },
  {
    key: 'apify_scraper',
    name: 'Apify-scraper',
    group: 'input',
    purpose: 'Haalt LinkedIn-profielen en websites op voor Theme.',
    settingsHref: '/sources/new/scraper',
  },
  {
    key: 'website_cms',
    name: 'Website',
    group: 'publish',
    purpose: 'Blogposts via WordPress, Joomla of FTP.',
    settingsHref: '/account?focus=publish-mode',
  },
  {
    key: 'ga4',
    name: 'Google Analytics (GA4)',
    group: 'measure',
    purpose: 'Paginaweergaven per artikel — voedt Overview en Report.',
    settingsHref: '/sources/new/analytics',
    googleAccount: true,
  },
]

export function integrationsForGroup(group: IntegrationGroup): IntegrationDef[] {
  return INTEGRATIONS.filter((item) => item.group === group)
}

export function integrationDef(key: string): IntegrationDef | undefined {
  return INTEGRATIONS.find((item) => item.key === key)
}

export const GOOGLE_INTEGRATION_KEYS: IntegrationKey[] = ['calendar', 'ga4']
