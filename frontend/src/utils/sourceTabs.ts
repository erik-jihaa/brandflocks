import type { SourceTab } from '../types/sources'

export const SOURCE_TABS: SourceTab[] = [
  'URL/RSS',
  'Blogs',
  'Document',
  'YouTube',
  'Asana',
  'Drive',
  'Linkedin',
  'Scraper',
  'Mailbox',
  'Analythics',
  'Trello',
  'Calendar',
]

const SLUG_TO_TAB: Record<string, SourceTab> = {
  'url-rss': 'URL/RSS',
  blogs: 'Blogs',
  document: 'Document',
  youtube: 'YouTube',
  asana: 'Asana',
  drive: 'Drive',
  linkedin: 'Linkedin',
  scraper: 'Scraper',
  mailbox: 'Mailbox',
  analytics: 'Analythics',
  trello: 'Trello',
  calendar: 'Calendar',
}

export function sourceTabToSlug(tab: SourceTab): string {
  const entry = Object.entries(SLUG_TO_TAB).find(([, value]) => value === tab)
  return entry?.[0] ?? tab.toLowerCase()
}

export function slugToSourceTab(slug: string): SourceTab | null {
  return SLUG_TO_TAB[slug] ?? null
}
