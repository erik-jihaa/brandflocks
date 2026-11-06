import type { ResearchThemeGroup } from '../api/onboarding'

const sampleItem = (
  id: string,
  title: string,
  rationaleSuffix: string,
): ResearchThemeGroup['items'][number] => ({
  id,
  title,
  snippet:
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
  url: 'https://example.com/bron-artikel',
  itemRationale: `How the Strategy input connects with the article — ${rationaleSuffix}`,
  feedback: 'none',
  sourceType: 'rss',
})

export const researchMock: {
  slotId: string
  weekLabel: string
  slotTheme: string
  themeGroups: ResearchThemeGroup[]
  items: ResearchThemeGroup['items']
} = {
  slotId: 'mock-research-slot',
  weekLabel: 'Week 14',
  slotTheme: 'Theme title 1',
  themeGroups: [
    {
      slotId: 'mock-research-slot-a',
      themeId: 'mock-theme-a',
      themeTitle: 'Theme title 1',
      themeRationale: '',
      items: [sampleItem('mock-research-1a', 'Research name 1', 'tation ullamco laboris.')],
    },
    {
      slotId: 'mock-research-slot-b',
      themeId: 'mock-theme-b',
      themeTitle: 'Theme title 2',
      themeRationale: '',
      items: [sampleItem('mock-research-2a', 'Research name 2', 'sunt in culpa qui officia.')],
    },
  ],
  items: [sampleItem('mock-research-1a', 'Research name 1', 'tation ullamco laboris.')],
}
