import type { LlmVisibilityReport } from '../api/llmQuestions'

export function findabilityKindScore(report: LlmVisibilityReport | null | undefined): number | null {
  const latest = report?.latest
  if (!latest || latest.totalCount <= 0) return null
  return Math.round((latest.mentionedCount / latest.totalCount) * 100)
}

export function findabilityCombinedScore(
  geo: LlmVisibilityReport | null | undefined,
  seo: LlmVisibilityReport | null | undefined,
): number | null {
  const scores = [findabilityKindScore(geo), findabilityKindScore(seo)].filter(
    (score): score is number => score !== null,
  )
  if (scores.length === 0) return null
  return Math.round(scores.reduce((sum, score) => sum + score, 0) / scores.length)
}

export function findabilityScoreDetail(geoScore: number | null, seoScore: number | null): string {
  const parts: string[] = []
  if (geoScore !== null) parts.push(`GEO ${geoScore}%`)
  if (seoScore !== null) parts.push(`SEO ${seoScore}%`)
  return parts.length > 0 ? parts.join(' · ') : 'Nog geen meting'
}
