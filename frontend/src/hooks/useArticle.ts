import { useCallback, useEffect, useState } from 'react'
import { fetchArticle, saveArticle, submitArticle } from '../api/articles'
import { ApiError, isApiConfigured } from '../api/client'
import { articleMock } from '../data/article.mock'
import type { ArticleDto, ArticleSavePayload, ReviewMode } from '../types/api'
import { stripEditorQuestionsBlock } from '../utils/editorQuestions'

function mockArticle(slotId?: string): ArticleDto {
  return {
    id: 'mock',
    slotId: slotId ?? 'demo',
    weekLabel: articleMock.weekLabel,
    headline: articleMock.headline,
    content: articleMock.content,
    contentAi: articleMock.content.replace(
      'consectetuer adipiscing elit',
      'consectetuer adipiscing',
    ),
    hashtags: articleMock.hashtags,
    rationale: articleMock.rationale,
    sourceUrl: articleMock.sourceUrl,
    tone: articleMock.tone.selected,
    angle: articleMock.angle.selected,
    imageRatio: articleMock.imageRatio.selected,
    platform: articleMock.platform.selected,
    toneSuggested: articleMock.tone.suggested,
    angleSuggested: articleMock.angle.suggested,
    imageRatioSuggested: articleMock.imageRatio.suggested,
    platformSuggested: articleMock.platform.suggested,
    hashtagsSuggested: articleMock.hashtags,
    publishMode: 'manual_with_review',
    imageUrlSquare: null,
    imageUrlLandscape: null,
    status: 'draft',
    slotStatus: 'written',
    feedback: [],
  }
}

function articleContentFingerprint(data: ArticleDto): string {
  const body = stripEditorQuestionsBlock(data.contentAi ?? data.content ?? '')
  return [
    data.id,
    data.status,
    data.slotStatus ?? '',
    body,
    data.headline,
    data.hbaHint?.id ?? '',
    data.hbaScore?.id ?? '',
    data.hbaScore?.status ?? '',
    data.writingPending ? '1' : '0',
  ].join('|')
}

function articleLooksEmpty(data: ArticleDto): boolean {
  const body = stripEditorQuestionsBlock(data.contentAi ?? data.content ?? '')
  return !body.trim()
}

type UseArticleOptions = {
  /** CE-03 draait — poll tot artikel + rationale beschikbaar zijn. */
  expectWriting?: boolean
}

export function useArticle(slotId?: string, options?: UseArticleOptions) {
  const expectWriting = options?.expectWriting ?? false
  const [article, setArticle] = useState<ArticleDto | null>(null)
  const [loading, setLoading] = useState(Boolean(slotId))
  const [writing, setWriting] = useState(false)
  const [writingSlow, setWritingSlow] = useState(false)
  const [saving, setSaving] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)

  const load = useCallback(
    async (silent = false): Promise<ArticleDto | null> => {
      if (!slotId) {
        setArticle(null)
        setUsingMock(false)
        setLoading(false)
        setWriting(false)
        setWritingSlow(false)
        setError(null)
        return null
      }

      if (!silent) {
        setLoading(true)
        setError(null)
      }

      if (!isApiConfigured()) {
        const mock = mockArticle(slotId)
        setArticle(mock)
        setUsingMock(true)
        setLoading(false)
        setWriting(false)
        setWritingSlow(false)
        return mock
      }

      try {
        const data = await fetchArticle(slotId)
        setArticle((prev) => {
          if (prev && articleContentFingerprint(prev) === articleContentFingerprint(data)) {
            return prev
          }
          return data
        })
        setUsingMock(false)
        const stillEmpty = articleLooksEmpty(data)
        setWriting((expectWriting && stillEmpty) || (Boolean(data.writingPending) && stillEmpty))
        if (!stillEmpty) {
          setWritingSlow(false)
          setError(null)
        }
        return data
      } catch (err) {
        if (expectWriting && err instanceof ApiError && err.status === 404) {
          setArticle(null)
          setUsingMock(false)
          setWriting(true)
          setWritingSlow(false)
          setError(null)
        } else if (expectWriting && err instanceof ApiError && err.status === 409) {
          setArticle(null)
          setUsingMock(false)
          setWriting(false)
          setWritingSlow(false)
          setError(err.message)
        } else if (err instanceof ApiError && err.status === 503) {
          setError('Database niet geconfigureerd — mock data actief.')
          setArticle(mockArticle(slotId))
          setUsingMock(true)
          setWriting(false)
          setWritingSlow(false)
        } else {
          setError(err instanceof Error ? err.message : 'Laden mislukt')
          setArticle(null)
          setUsingMock(false)
          setWriting(false)
          setWritingSlow(false)
        }
        return null
      } finally {
        if (!silent) {
          setLoading(false)
        }
      }
    },
    [slotId, expectWriting],
  )

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (!writing || !slotId || usingMock) return

    const interval = window.setInterval(() => {
      void load(true)
    }, 4000)
    const slowTimeout = window.setTimeout(() => {
      setWritingSlow(true)
    }, 120000)

    return () => {
      window.clearInterval(interval)
      window.clearTimeout(slowTimeout)
    }
  }, [writing, slotId, usingMock, load])

  const buildPayload = (payload: ArticleSavePayload): ArticleSavePayload => payload

  const save = useCallback(
    async (payload: ArticleSavePayload) => {
      if (!slotId || usingMock) {
        setArticle((current) => (current ? { ...current, ...payload } : current))
        return
      }

      setSaving(true)
      setError(null)
      try {
        const data = await saveArticle(slotId, buildPayload(payload))
        // PATCH-response bevat geen reviewEdits/feedback — merge met huidig artikel
        setArticle((current) => (current ? { ...current, ...data } : data))
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Opslaan mislukt')
        throw err
      } finally {
        setSaving(false)
      }
    },
    [slotId, usingMock],
  )

  const submit = useCallback(
    async (payload: ArticleSavePayload, reviewMode: ReviewMode = 'human') => {
      if (!slotId || usingMock) {
        setArticle((current) =>
          current
            ? {
                ...current,
                ...payload,
                status: reviewMode === 'ai' ? 'feedback' : 'pending',
              }
            : current,
        )
        return undefined
      }

      setSubmitting(true)
      setError(null)
      try {
        const data = await submitArticle(slotId, {
          ...buildPayload(payload),
          reviewMode,
        })
        setArticle(data)
        return data
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : reviewMode === 'ai'
              ? 'AI review mislukt'
              : 'Indienen mislukt',
        )
        throw err
      } finally {
        setSubmitting(false)
      }
    },
    [slotId, usingMock],
  )

  return {
    article,
    loading,
    writing,
    writingSlow,
    saving,
    submitting,
    error,
    usingMock,
    save,
    submit,
    reload: (silent = false) => load(silent),
  }
}
