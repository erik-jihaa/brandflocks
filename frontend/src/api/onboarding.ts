import { apiFetch } from './client'
import type { ResearchItemDto, StrategyDto, ThemeDto } from '../types/onboarding'

export type ThemeWeekMeta = {
  weekKey: string
  weekLabel: string
  cycleWeek: string
  pendingCount: number
}

export type ThemeListMeta = {
  pendingTotal: number
  weeks: ThemeWeekMeta[]
  activeWeekKey: string
  weekIndex: number
  weekCount: number
  postsPerWeek: number
  slotsFilledForWeek: number
  themesStillRequired: number
  weekFull?: boolean
  proposedThemeCount?: number
  needsThemeGeneration?: boolean
  activeCycleWeek?: string
  redirectWeekKey?: string
}

export function fetchStrategy(): Promise<{ strategy: StrategyDto | null; error?: string }> {
  return apiFetch('/strategy')
}

export function patchStrategy(body: Partial<StrategyDto>): Promise<{ strategy: StrategyDto }> {
  return apiFetch('/strategy', { method: 'PATCH', body: JSON.stringify(body) })
}

export function fetchThemes(
  week?: string,
): Promise<{ themes: ThemeDto[]; meta?: ThemeListMeta; note?: string }> {
  const q = week ? `?week=${encodeURIComponent(week)}` : ''
  return apiFetch(`/themes${q}`)
}

export function createTheme(
  body: Partial<Pick<ThemeDto, 'title' | 'motivation' | 'rationale' | 'sourceArticleUrl' | 'cycleWeek'>>,
): Promise<{ theme: ThemeDto }> {
  return apiFetch('/themes', { method: 'POST', body: JSON.stringify(body) })
}

export function patchTheme(
  themeId: string,
  body: Partial<
    Pick<
      ThemeDto,
      | 'title'
      | 'motivation'
      | 'rationale'
      | 'sourceArticleUrl'
      | 'feedback'
      | 'editorSuggested'
      | 'copyModus'
    > & { copyModusOverrideActivatie?: boolean }
  >,
): Promise<{ theme: ThemeDto }> {
  return apiFetch(`/themes/${themeId}`, { method: 'PATCH', body: JSON.stringify(body) })
}

export function submitTheme(themeId: string): Promise<{ theme: ThemeDto | null; webhookTriggered: boolean }> {
  return apiFetch(`/themes/${themeId}/submit`, { method: 'POST' })
}

export function generateThemes(week?: string): Promise<{
  ok: boolean
  async?: boolean
  week: string
  cycleWeek: string
  themes: ThemeDto[]
  meta?: ThemeListMeta
  ce01?: { ok?: boolean; error?: string; response?: unknown }
}> {
  const body = week ? JSON.stringify({ week }) : '{}'
  return apiFetch('/themes/generate', { method: 'POST', body })
}

export function patchResearchItem(
  itemId: string,
  feedback: 'liked' | 'forgotten' | 'none' | 'like' | 'forget',
): Promise<{ item: ResearchItemDto }> {
  return apiFetch(`/research-items/${itemId}`, {
    method: 'PATCH',
    body: JSON.stringify({ feedback }),
  })
}

export type ResearchThemeGroup = {
  slotId: string
  themeId: string
  themeTitle: string
  themeRationale: string
  expectsArticleWrite?: boolean
  items: ResearchItemDto[]
}

export type PendingResearchItem = ResearchItemDto & {
  slotId: string
  themeId: string
  themeTitle: string
  themeRationale: string
  weekLabel: string
  phaseKey?: string
}

export function fetchPendingResearch(): Promise<{ items: PendingResearchItem[]; total: number }> {
  return apiFetch('/research/pending')
}

export function fetchSlotResearch(slotId: string): Promise<{
  slotId: string
  weekLabel?: string
  weekNumber?: number | null
  phaseKey?: string
  slotTheme: string
  themeRationale?: string
  expectsArticleWrite?: boolean
  items: ResearchItemDto[]
  themeGroups?: ResearchThemeGroup[]
  note?: string
}> {
  return apiFetch(`/research/${slotId}`)
}

export function triggerSlotResearch(slotId: string): Promise<{
  ok: boolean
  webhookTriggered: boolean
  async?: boolean
  slotId: string
  slotTheme: string
  items: ResearchItemDto[]
}> {
  return apiFetch(`/research/${slotId}/trigger`, { method: 'POST' })
}

export function saveResearchFeedback(
  items: Array<{ id: string; feedback: 'liked' | 'forgotten' | 'none' }>,
): Promise<{ items: ResearchItemDto[]; failedCount: number }> {
  return Promise.allSettled(items.map((item) => patchResearchItem(item.id, item.feedback))).then(
    (results) => {
      const itemsOut: ResearchItemDto[] = []
      let failedCount = 0
      for (const result of results) {
        if (result.status === 'fulfilled') {
          itemsOut.push(result.value.item)
        } else {
          failedCount += 1
        }
      }
      return { items: itemsOut, failedCount }
    },
  )
}

export function submitSlotResearch(slotId: string): Promise<{
  ok: boolean
  slotId: string
  slotTheme: string
  items: ResearchItemDto[]
  status?: string
  articleWriteTriggered?: boolean
  expectsArticleWrite?: boolean
  webhookTriggered?: boolean
  reason?: 'article_exists' | 'insufficient_research' | 'missing_title' | 'not_eligible' | string
  message?: string
  usableResearchCount?: number
}> {
  return apiFetch(`/research/${slotId}/submit`, { method: 'POST' })
}
