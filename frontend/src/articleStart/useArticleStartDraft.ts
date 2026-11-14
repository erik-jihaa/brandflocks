import { useCallback, useMemo, useState } from 'react'
import type { ArticleStartMode } from './articleStartCopy'
import {
  ARTICLE_START_MERK_KEYS,
  defaultArticleStartChannels,
  type ArticleStartChannelKey,
} from './articleStartMerk'

const STORAGE_KEY = 'bf-article-start-draft-v2'

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
})

function readDraft(): ArticleStartDraft {
  if (typeof sessionStorage === 'undefined') return emptyDraft()
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return emptyDraft()
    const parsed = JSON.parse(raw) as Partial<ArticleStartDraft>
    return { ...emptyDraft(), ...parsed }
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