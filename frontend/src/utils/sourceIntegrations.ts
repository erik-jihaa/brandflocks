import type { IntegrationKey } from '../config/integrations'
import type { IntegrationStatusEntry } from '../api/integrations'
import type { SourceTab } from '../types/sources'

export function sourceTabIntegrationKey(tab: SourceTab): IntegrationKey | null {
  switch (tab) {
    case 'Calendar':
      return 'calendar'
    case 'Mailbox':
      return 'mailbox'
    case 'Scraper':
    case 'Linkedin':
      return 'apify_scraper'
    case 'Analythics':
      return 'ga4'
    default:
      return null
  }
}

export function isSourceIntegrationConnected(
  category: SourceTab,
  integrations: Record<string, IntegrationStatusEntry> | undefined,
): boolean {
  const key = sourceTabIntegrationKey(category)
  if (!key) return true
  if (!integrations) return true
  return integrations[key]?.status === 'connected'
}
