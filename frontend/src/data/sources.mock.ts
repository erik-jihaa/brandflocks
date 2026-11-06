import type { CheckStatus } from '../components/StatusRow'
import type { SourceTab } from '../types/sources'

export type SourceMockRow = {
  title: string
  checkStatus: CheckStatus
  showBudget: boolean
  category: SourceTab
}

/** sources 1Type+.png */
export const sourcesMock: SourceMockRow[] = [
  { title: 'Source title', checkStatus: 'none', showBudget: true, category: 'URL/RSS' },
  { title: 'Source title', checkStatus: 'red', showBudget: true, category: 'Scraper' },
  { title: 'Source title', checkStatus: 'green', showBudget: true, category: 'URL/RSS' },
  { title: 'Mailbox title', checkStatus: 'none', showBudget: false, category: 'Mailbox' },
  { title: 'Analythics title', checkStatus: 'green', showBudget: false, category: 'Analythics' },
]
