import {
  createLlmQuestion,
  deleteLlmQuestion,
  patchLlmQuestion,
  type LlmQuestionDto,
  type SearchQuestionKind,
} from '../api/llmQuestions'

export type { SearchQuestionKind }
export const CE_SEARCH_QUESTIONS_MAX = 5
/** @deprecated use CE_SEARCH_QUESTIONS_MAX */
export const CE_LLM_QUESTIONS_MAX = CE_SEARCH_QUESTIONS_MAX

export type LlmQuestionRow = {
  id: string
  question: string
}

export function isTempLlmRowId(id: string): boolean {
  return id.startsWith('draft-') || id.startsWith('new-')
}

export function normalizeLlmQuestions(rows: LlmQuestionRow[]): string[] {
  return rows.map((r) => r.question.trim()).filter((q) => q !== '')
}

export function llmRowsEqual(a: LlmQuestionRow[], b: LlmQuestionRow[]): boolean {
  const na = normalizeLlmQuestions(a)
  const nb = normalizeLlmQuestions(b)
  if (na.length !== nb.length) return false
  return na.every((q, i) => q === nb[i])
}

export function displayLlmRowsFromSaved(saved: LlmQuestionRow[]): LlmQuestionRow[] {
  const rows = saved.map((r) => ({ ...r }))
  if (rows.length === 0) {
    return [{ id: 'draft-new', question: '' }]
  }
  const hasEmpty = rows.some((r) => r.question.trim() === '')
  if (rows.length < CE_SEARCH_QUESTIONS_MAX && !hasEmpty) {
    rows.push({ id: `draft-${Date.now()}`, question: '' })
  }
  return rows
}

export function llmRowsFromDto(questions: LlmQuestionDto[]): LlmQuestionRow[] {
  return questions.map((q) => ({ id: q.id, question: q.question }))
}

/** Synchroniseer draft met API; retourneert opgeslagen rijen (zonder lege placeholders). */
export async function persistLlmQuestionRows(
  saved: LlmQuestionRow[],
  draft: LlmQuestionRow[],
  kind: SearchQuestionKind = 'geo',
): Promise<LlmQuestionRow[]> {
  const savedPersisted = saved.filter((r) => !isTempLlmRowId(r.id))
  const draftPersistedIds = new Set(
    draft.filter((r) => !isTempLlmRowId(r.id)).map((r) => r.id),
  )

  for (const row of savedPersisted) {
    if (!draftPersistedIds.has(row.id)) {
      await deleteLlmQuestion(row.id)
    }
  }

  const result: LlmQuestionRow[] = []
  for (const row of draft) {
    const trimmed = row.question.trim()
    if (!trimmed) continue

    if (isTempLlmRowId(row.id)) {
      const { question } = await createLlmQuestion(trimmed, kind)
      result.push({ id: question.id, question: question.question })
    } else {
      const prev = savedPersisted.find((r) => r.id === row.id)
      if (!prev || prev.question.trim() !== trimmed) {
        await patchLlmQuestion(row.id, { question: trimmed })
      }
      result.push({ id: row.id, question: trimmed })
    }
  }

  return result
}
