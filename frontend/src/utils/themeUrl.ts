import type { ThemeDto } from '../types/onboarding'

export function isFeedOrSearchUrl(url: string | null | undefined): boolean {
  const u = (url ?? '').trim().toLowerCase()
  if (!u) return true
  if (u.includes('news.google.com/rss/search')) return true
  if (u.includes('news.google.com/rss/feeds')) return true
  if (/\/rss(\?|$)/.test(u) && !u.includes('/rss/articles/')) return true
  if (/\.(xml|rss)(\?|$)/.test(u)) return true
  return false
}

export function resolveResearchArticleUrl(url: string | null | undefined): string | undefined {
  const trimmed = (url ?? '').trim()
  if (!trimmed || isFeedOrSearchUrl(trimmed)) return undefined
  return trimmed
}

function extractArticleUrlFromText(text: string | null | undefined): string | undefined {
  const raw = (text ?? '').trim()
  if (!raw) return undefined
  const match = raw.match(/https?:\/\/[^\s<>"')\]]+/i)
  if (!match) return undefined
  const url = match[0].replace(/[.,;:!?)]+$/, '')
  return isFeedOrSearchUrl(url) ? undefined : url
}

/** Prefer resolved API URL; fallback to first research item with a real article link. */
export function resolveThemeArticleUrl(theme: ThemeDto): string | undefined {
  const stored = theme.sourceArticleUrl?.trim()
  if (stored && !isFeedOrSearchUrl(stored)) return stored

  const fromResearch = theme.researchItems?.find(
    (item) => item.url?.trim() && !isFeedOrSearchUrl(item.url),
  )
  if (fromResearch?.url?.trim()) {
    return fromResearch.url.trim()
  }

  return (
    extractArticleUrlFromText(theme.rationale) ||
    extractArticleUrlFromText(theme.motivation) ||
    undefined
  )
}
