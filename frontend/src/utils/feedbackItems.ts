import type { FeedbackItemDto, ReviewEditDto } from '../types/api'
import { buildFeedbackLineRows, type FeedbackLineRow } from './contentDiff'

/** Baseline voor diff op feedback-pagina — niet terugvallen op contentAi na HR-review. */
export function feedbackBaselineFromReviewEdits(
  reviewEdits: ReviewEditDto[] | undefined,
  contentAi: string,
): string {
  const pending = reviewEdits?.find((edit) => edit.status === 'pending')
  if (pending?.originalText != null) {
    return pending.originalText
  }

  for (let i = (reviewEdits?.length ?? 0) - 1; i >= 0; i--) {
    const original = reviewEdits?.[i]?.originalText?.trim()
    if (original) {
      return reviewEdits![i].originalText
    }
  }

  return contentAi
}


function lineIndexForExcerpt(excerpt: string | null | undefined, content: string): number | undefined {
  const needle = excerpt?.trim()
  if (!needle) return undefined

  const lines = content.split('\n')
  const exact = lines.findIndex((line) => line.trim() === needle)
  if (exact >= 0) return exact

  const lowerNeedle = needle.toLowerCase()
  const byWord = lines.findIndex((line) => {
    const hay = line.toLowerCase()
    if (hay.includes(lowerNeedle)) return true
    if (needle.length >= 3 && line.includes(needle)) return true
    return false
  })
  if (byWord >= 0) return byWord

  const partial = lines.findIndex(
    (line) => line.includes(needle) || needle.includes(line.trim()),
  )
  return partial >= 0 ? partial : undefined
}

export type FeedbackDisplayItem = {
  id: string
  source: 'api' | 'review-edit' | 'headline'
  name: string
  comment: string
  actionLabel: string
  type: 'text' | 'image'
  status: 'open' | 'okay' | 'deleted'
  editId?: string
  /** Regelindex in body — uitlijning met feedbackkolom */
  lineIndex?: number
  /**
   * Exacte regelwaarde in suggestedText (voor positie-matching na splices).
   * Leeg string = de regel was ingevoegd (baseline had geen corresponderende regel).
   */
  baselineLine?: string
  /** Exacte regelwaarde in suggestedText — voor zoeken na line-count-wijzigingen. */
  currentLine?: string
}

/** §6 — Engelse knoppen (o.a. CE-04 "Remove") → Nederlands. */
export function normalizeFeedbackActionLabel(
  label: string | null | undefined,
  type: 'text' | 'image' = 'text',
): string {
  const raw = (label ?? '').trim()
  if (raw === '' || raw === 'Remove') {
    return type === 'image' ? 'Nieuwe afbeelding' : 'Verwijderen'
  }
  if (raw === 'New image') return 'Nieuwe afbeelding'
  return raw
}

function mapApiItem(item: FeedbackItemDto, content: string): FeedbackDisplayItem {
  const type = item.type === 'image' ? 'image' : 'text'
  const lines = content.split('\n')
  const lineIndex = lineIndexForExcerpt(item.excerpt ?? item.comment, content)
  const line =
    lineIndex !== undefined && lineIndex >= 0 && lineIndex < lines.length
      ? lines[lineIndex]
      : undefined
  return {
    id: item.id,
    source: 'api',
    name: item.name,
    comment: item.comment,
    actionLabel: normalizeFeedbackActionLabel(item.actionLabel, type),
    type,
    status: item.status === 'okay' ? 'okay' : item.status === 'deleted' ? 'deleted' : 'open',
    lineIndex,
    baselineLine: line,
    currentLine: line,
  }
}

function stableLineId(editId: string, baselineLine: string, currentLine: string): string {
  const key = `${baselineLine}\x00${currentLine}`
  let hash = 0
  for (let i = 0; i < key.length; i++) {
    hash = (hash * 31 + key.charCodeAt(i)) | 0
  }
  return `edit-${editId}-${Math.abs(hash)}`
}

function mapReviewEdit(edit: ReviewEditDto, reviewerName = 'Review'): FeedbackDisplayItem[] {
  const original = edit.originalText ?? ''
  const suggested = edit.suggestedText ?? ''
  if (suggested.trim() === '' || suggested.trim() === original.trim()) return []

  const rows = buildFeedbackLineRows(original, suggested).filter((row) => row.correction)

  if (rows.length === 0) {
    return [
      {
        id: `edit-${edit.id}`,
        source: 'review-edit',
        editId: edit.id,
        name: reviewerName,
        comment: suggested.slice(0, 120).trim() || 'Wijziging',
        actionLabel: 'Verwijderen',
        type: 'text',
        status: edit.status === 'pending' ? 'open' : 'okay',
      },
    ]
  }

  return rows.map((row) => ({
    id: stableLineId(edit.id, row.baselineLine, row.currentLine),
    source: 'review-edit' as const,
    editId: edit.id,
    name: reviewerName,
    comment: row.correction!,
    actionLabel: 'Verwijderen',
    type: 'text' as const,
    status: edit.status === 'pending' ? 'open' : 'okay',
    lineIndex: row.lineIndex,
    baselineLine: row.baselineLine,
    currentLine: row.currentLine,
  }))
}

export function buildFeedbackDisplayItems(input: {
  feedback: FeedbackItemDto[]
  reviewEdits: ReviewEditDto[]
  headline: string
  headlineAi: string
  content: string
  feedbackBaseline: string
  reviewerName?: string
}): FeedbackDisplayItem[] {
  const pendingEdits = input.reviewEdits.filter((edit) => edit.status === 'pending')
  const editItems = pendingEdits.flatMap((edit) =>
    mapReviewEdit(edit, input.reviewerName ?? 'Review'),
  )

  const openApi = input.feedback
    .filter((item) => item.status === 'open')
    .map((item) => mapApiItem(item, input.content))
  const imageItems = openApi.filter((item) => item.type === 'image')
  const textApi = openApi.filter((item) => item.type === 'text')

  const headlineItems: FeedbackDisplayItem[] = []
  const headlineAi = input.headlineAi.trim()
  const headline = input.headline.trim()
  if (headlineAi !== '' && headline !== '' && headlineAi !== headline) {
    headlineItems.push({
      id: 'headline-diff',
      source: 'headline',
      name: input.reviewerName ?? 'Review',
      comment: headline,
      actionLabel: 'Verwijderen',
      type: 'text',
      status: 'open',
      lineIndex: -1,
    })
  }

  return [...imageItems, ...textApi, ...editItems, ...headlineItems]
}

/** Koppel feedbackkaarten aan live content-regels — één kaart per item, match op regeltekst. */
function lineMatchesItem(row: FeedbackLineRow, item: FeedbackDisplayItem): boolean {
  if (item.currentLine === undefined) return false
  if (item.currentLine === row.currentLine) return true
  if (item.currentLine.trim() !== '' && item.currentLine.trim() === row.currentLine.trim()) return true
  if (row.correction && item.comment.trim() === row.correction.trim()) return true
  if (item.comment.trim() !== '' && row.currentLine.includes(item.comment.trim())) return true
  if (item.currentLine.trim() !== '' && row.currentLine.includes(item.currentLine.trim())) return true
  return false
}

export function assignFeedbackToRows(
  rows: FeedbackLineRow[],
  items: FeedbackDisplayItem[],
): Map<number, FeedbackDisplayItem> {
  const used = new Set<string>()
  const map = new Map<number, FeedbackDisplayItem>()

  for (const row of rows) {
    const item = items.find((candidate) => {
      if (used.has(candidate.id)) return false
      if (candidate.type !== 'text' || candidate.status !== 'open') return false
      return lineMatchesItem(row, candidate)
    })
    if (item) {
      used.add(item.id)
      map.set(row.lineIndex, item)
    }
  }

  return map
}
