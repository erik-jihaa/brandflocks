import type { ArticleStartDraft } from './useArticleStartDraft'
import { ARTICLE_START_CHANNELS, type ArticleStartChannelKey } from './articleStartMerk'

export type ArticleStartPlanItem = {
  id: string
  angle: string
  publishDate: string
}

const CHANNEL_TO_PLATFORM: Record<ArticleStartChannelKey, string> = {
  linkedin: 'Linkedin',
  instagram: 'Instagram',
  website: 'Blog',
  x: 'X',
  facebook: 'Facebook',
}

function newPlanId(index: number): string {
  return `plan-${index + 1}-${Date.now().toString(36)}`
}

function defaultAngle(theme: string, index: number, total: number): string {
  const base = theme.trim() || 'Artikel'
  if (total <= 1) return base
  return `${base} — invalshoek ${index + 1}`
}

function addDaysIso(iso: string, days: number): string {
  const d = new Date(`${iso}T12:00:00`)
  if (Number.isNaN(d.getTime())) return iso
  d.setDate(d.getDate() + days)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

export function todayIso(): string {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

/** Zorg dat planItems overeenkomt met articleCount (of 1 bij mode `een`). */
export function syncPlanItems(draft: ArticleStartDraft): ArticleStartPlanItem[] {
  const count = draft.mode === 'een' ? 1 : Math.max(1, Math.min(99, draft.articleCount || 1))
  const prev = draft.planItems ?? []
  const start = todayIso()
  const next: ArticleStartPlanItem[] = []
  for (let i = 0; i < count; i++) {
    const existing = prev[i]
    next.push({
      id: existing?.id ?? newPlanId(i),
      angle: existing?.angle?.trim() ? existing.angle : defaultAngle(draft.theme, i, count),
      publishDate: existing?.publishDate?.trim()
        ? existing.publishDate
        : addDaysIso(start, i * 2),
    })
  }
  return next
}

export function selectedChannelKeys(draft: ArticleStartDraft): ArticleStartChannelKey[] {
  return ARTICLE_START_CHANNELS.filter((c) => draft.channels[c.key]).map((c) => c.key)
}

export function selectedChannelLabels(draft: ArticleStartDraft): string[] {
  return ARTICLE_START_CHANNELS.filter((c) => draft.channels[c.key]).map((c) => c.label)
}

export function primaryPlatform(draft: ArticleStartDraft): string {
  const keys = selectedChannelKeys(draft)
  if (keys.length === 0) return 'Linkedin'
  return CHANNEL_TO_PLATFORM[keys[0]] ?? 'Linkedin'
}

export function planDateRange(items: ArticleStartPlanItem[]): { start: string; end: string } {
  const dates = items.map((i) => i.publishDate).filter((d) => /^\d{4}-\d{2}-\d{2}$/.test(d)).sort()
  if (dates.length === 0) {
    const t = todayIso()
    return { start: t, end: t }
  }
  return { start: dates[0], end: dates[dates.length - 1] }
}

export function merkLabelsForDraft(draft: ArticleStartDraft): string {
  const labels: Record<string, string> = {
    wat_je_doet: 'Rol',
    waarom: 'Doel',
    waar_je_tegen_bent: 'Waar je tegen bent',
    hoe_je_werkt: 'Hoe je werkt',
    waar_je_nee_tegen_zegt: 'Regel of subregel',
  }
  return draft.merkOnderdelen.map((k) => labels[k] ?? k).join(' · ') || '—'
}