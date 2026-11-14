import { useCallback, useMemo, useState } from 'react'
import type { ArticleStartMode } from './articleStartCopy'
import {
  ARTICLE_START_MERK_KEYS,
  defaultArticleStartChannels,
  type ArticleStartChannelKey,
} from './articleStartMerk'
import type { ArticleStartPlanItem } from './articleStartPlan'

const STORAGE_KEY = 'bf-article-start-draft-v3'

export type ArticleStartLinkedInType = 'post' | 'artikel'

export type ArticleStartDraft = {
  mode: ArticleStartMode
  theme: string
  extraThoughts: string
  ownText: string
  articleCount: number
  merkOnderdelen: string[]
  feitIds: string[]
  hashtags: string[]
  channels: Record<ArticleStartChannelKey, boolean>
  linkedinType: ArticleStartLinkedInType
  planItems: ArticleStartPlanItem[]
}

const emptyDraft = (): ArticleStartDraft => ({
  mode: '',
  theme: '',
  extraThoughts: '',
  ownText: '',
  articleCount: 2,
  merkOnderdelen: [...ARTICLE_START_MERK_KEYS],
  feitIds: [],
  hashtags: [],
  channels: defaultArticleStartChannels(),
  linkedinType: 'post',
  planItems: [],
})

function readDraft(): ArticleStartDraft {
  if (typeof sessionStorage === 'undefined') return emptyDraft()
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) {
      // Migreer v2-draft indien aanwezig
      const legacy = sessionStorage.getItem('bf-article-start-draft-v2')
      if (!legacy) return emptyDraft()
      const parsed = JSON.parse(legacy) as Partial<ArticleStartDraft>
      return { ...emptyDraft(), ...parsed, planItems: [] }
    }
    const parsed = JSON.parse(raw) as Partial<ArticleStartDraft>
    return {
      ...emptyDraft(),
      ...parsed,
      planItems: Array.isArray(parsed.planItems) ? parsed.planItems : [],
    }
  } catch {
    return emptyDraft()
  }
}

function writeDraft(draft: ArticleStartDraft) {
  if (typeof sessionStorage === 'undefined') return
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(draft))
}

export function useArticleStartDraft() {
  const [draft, setDraftState] = useState<ArticleStartDraft>(() => readDraft())

  const setDraft = useCallback((patch: Partial<ArticleStartDraft>) => {
    setDraftState((prev) => {
      const next = { ...prev, ...patch }
      writeDraft(next)
      return next
    })
  }, [])

  const resetDraft = useCallback(() => {
    const next = emptyDraft()
    writeDraft(next)
    setDraftState(next)
  }, [])

  return useMemo(() => ({ draft, setDraft, resetDraft }), [draft, setDraft, resetDraft])
}
