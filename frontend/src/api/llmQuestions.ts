import { apiFetch } from './client'

export type SearchQuestionKind = 'geo' | 'seo'

export type LlmQuestionDto = {
  id: string
  clientId: string
  question: string
  kind: SearchQuestionKind
  active: boolean
  createdAt: string
}

export function fetchLlmQuestions(): Promise<{
  questions: LlmQuestionDto[]
  geoQuestions: LlmQuestionDto[]
  seoQuestions: LlmQuestionDto[]
}> {
  return apiFetch('/llm-questions')
}

export function createLlmQuestion(
  question: string,
  kind: SearchQuestionKind = 'geo',
): Promise<{ questions: LlmQuestionDto[]; question: LlmQuestionDto }> {
  return apiFetch('/llm-questions', { method: 'POST', body: JSON.stringify({ question, kind }) })
}

export function patchLlmQuestion(
  id: string,
  patch: Partial<Pick<LlmQuestionDto, 'question' | 'active'>>,
): Promise<{ question: LlmQuestionDto }> {
  return apiFetch(`/llm-questions/${id}`, { method: 'PATCH', body: JSON.stringify(patch) })
}

export function deleteLlmQuestion(id: string): Promise<{ ok: boolean }> {
  return apiFetch(`/llm-questions/${id}`, { method: 'DELETE' })
}

export type LlmVisibilityModelResult = {
  model: string
  mentioned: boolean
}

export type LlmVisibilityQuestionResult = {
  questionId: string
  question: string
  models: LlmVisibilityModelResult[]
}

export type LlmVisibilityReport = {
  kind?: SearchQuestionKind
  hasQuestions: boolean
  latest: {
    checkedAt: string
    items: LlmVisibilityQuestionResult[]
    mentionedCount: number
    totalCount: number
  } | null
  previous: {
    checkedAt: string
    mentionedCount: number
    totalCount: number
  } | null
  advice?: string[]
}

export function fetchLlmVisibility(kind: SearchQuestionKind = 'geo'): Promise<LlmVisibilityReport> {
  return apiFetch(`/llm-visibility?kind=${encodeURIComponent(kind)}`)
}
