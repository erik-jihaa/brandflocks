import { useEffect, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { publishWordPress, formatPublishDestinationsNotice, markPublicationPlaced, patchReviewEdit, rewriteArticle, waitForRewrittenArticle, reeditArticle, uploadArticleImage, attachGalleryImageToArticle, deleteArticleImage } from '../api/articles'
import { GridShell } from '../layout/GridShell'
import { ArticleCard } from '../components/ArticleCard'
import { ArticleMetaSidebar } from '../components/ArticleMetaSidebar'
import { ImageUpload, resolveImageRatio } from '../components/ImageUpload'
import { ArticleReviewPanel } from '../components/ArticleReviewPanel'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { ArticleWritingNotice } from '../components/ArticleWritingNotice'
import { LoadingDots } from '../components/LoadingDots'
import { ReviewEditsPanel } from '../components/ReviewEditsPanel'
import { SourceAccordion } from '../components/MetaSection'
import { useArticle } from '../hooks/useArticle'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { ActionNotice } from '../components/ui/ActionNotice'
import { showPublishResultNotice } from '../utils/publishNotice'
import type { ReviewEditDto, ReviewMode } from '../types/api'
import { renderContentDiff } from '../utils/contentDiff'
import { isManualPublishMode, isTestPost, pickArticleImageUrl, resolveEffectiveImageRatio } from '../utils/mediaUrl'
import { BODY_HASHTAG_SEPARATOR } from '../utils/articleContent'
import { toneOptionsFromStyles } from '../utils/toneStyles'
import { editorOptionsFromStyles } from '../utils/editorStyles'
import { normalizeEditorQuestionsContent, stripEditorQuestionsBlock } from '../utils/editorQuestions'
import { applyArticleHint, generateArticleHint, ignoreArticleHint, type HbaArticleHint } from '../api/hbaV2'
import { scoreHbaArticle } from '../api/hba'
import type { HbaScoreDto } from '../types/api'
import {
  applyHbaHintToArticle,
  hintRewritePreviews,
  OPEN_PLACEHOLDER_MESSAGE,
  textHasOpenPlaceholders,
} from '../utils/hbaHintApply'
import { ARTICLE_COLUMN_WIDTH } from '../layout/grid.constants'
import { ThemeStartPanel, isThemeStartArticle } from '../components/ThemeStartPanel'

export function ArticlePage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { slotId } = useParams<{ slotId?: string }>()
  const [searchParams] = useSearchParams()
  const expectWriting = searchParams.get('writing') === '1'
  const { article, loading, writing, writingSlow, saving, submitting, error, usingMock, save, submit, reload } =
    useArticle(slotId, { expectWriting })

  const [headline, setHeadline] = useState('')
  const [content, setContent] = useState('')
  const [contentBaseline, setContentBaseline] = useState('')
  const [hashtags, setHashtags] = useState('')
  const [reviewEdits, setReviewEdits] = useState<ReviewEditDto[]>([])
  const [localPreviewSquare, setLocalPreviewSquare] = useState<string | null>(null)
  const [localPreviewLandscape, setLocalPreviewLandscape] = useState<string | null>(null)
  const [imageUrlSquare, setImageUrlSquare] = useState<string | null>(null)
  const [imageUrlLandscape, setImageUrlLandscape] = useState<string | null>(null)
  const [uploadingImage, setUploadingImage] = useState<'square' | 'landscape' | null>(null)
  const [deletingImage, setDeletingImage] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const [resolvingEdit, setResolvingEdit] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [publishingWp, setPublishingWp] = useState(false)
  const [markingPlaced, setMarkingPlaced] = useState(false)
  const [submitNotice, setSubmitNotice] = useState<string | null>(null)
  const {
    message: publishNotice,
    kind: publishNoticeKind,
    fading: publishNoticeFading,
    showSuccess: showPublishSuccess,
    showError: showPublishError,
    clear: clearPublishNotice,
  } = useActionNotice()
  const {
    message: researchSubmitNotice,
    kind: researchSubmitNoticeKind,
    fading: researchSubmitNoticeFading,
    showSuccess: showResearchSubmitSuccess,
  } = useActionNotice()
  const {
    message: saveMessage,
    kind: saveNoticeKind,
    fading: saveNoticeFading,
    showSuccess: showSaveSuccess,
    clear: clearSaveNotice,
  } = useSaveNotice('Artikel opgeslagen')
  const [rewriteNotice, setRewriteNotice] = useState<string | null>(null)
  const [rewriting, setRewriting] = useState(false)

  const [tone, setTone] = useState('')
  const [angle, setAngle] = useState('general')
  const [editor, setEditor] = useState<string | null>(null)
  const [imageRatio, setImageRatio] = useState('square')
  const [platform, setPlatform] = useState('Linkedin')
  const [contentTextvorm, setContentTextvorm] = useState('post')
  const [objective, setObjective] = useState<string | null>(null)
  const [hbaHint, setHbaHint] = useState<HbaArticleHint | null>(null)
  const [hbaHintLoading, setHbaHintLoading] = useState(false)
  const [hbaHintApplying, setHbaHintApplying] = useState(false)
  const [hbaHintError, setHbaHintError] = useState<string | null>(null)
  const [hbaHintRetry, setHbaHintRetry] = useState(0)
  const [hbaScore, setHbaScore] = useState<HbaScoreDto | null>(null)
  const [hbaContentrolOverride, setHbaContentrolOverride] = useState<string | null>(null)
  const [hbaRescoring, setHbaRescoring] = useState(false)
  const [hbaScoreAutoWaiting, setHbaScoreAutoWaiting] = useState(false)
  const [hbaNarrativeScoreBlockers, setHbaNarrativeScoreBlockers] = useState<string[] | null>(null)
  const hbaHintRequestedFor = useRef<string | null>(null)
  const hbaHintDraftRef = useRef<{ headline: string; content: string } | null>(null)
  const hbaHintSuppressedForArticle = useRef<string | null>(null)
  const hbaHintNextForce = useRef(false)
  const hbaAutoScoreAttemptedFor = useRef<string | null>(null)
  const articleSyncFingerprint = useRef('')
  const articleSyncArticleId = useRef<string | null>(null)

  useEffect(() => {
    if (!article || article.themeOnly || writing) return
    const body = stripEditorQuestionsBlock(article.contentAi ?? article.content ?? '')
    if (!body.trim()) return
    if (searchParams.get('writing') !== '1') return
    navigate(location.pathname, { replace: true })
  }, [article, writing, searchParams, navigate, location.pathname])

  useEffect(() => {
    const state = location.state as {
      researchSubmitted?: boolean
      researchSubmitMessage?: string
    } | null
    if (!state?.researchSubmitted) return
    showResearchSubmitSuccess(
      state.researchSubmitMessage?.trim() || 'Onderzoek ingediend — artikel wordt geschreven.',
    )
    navigate(`${location.pathname}${location.search}`, { replace: true, state: null })
  }, [location.pathname, location.search, location.state, navigate, showResearchSubmitSuccess])

  useEffect(() => {
    if (!article || article.themeOnly) return
    const normalizedContent = normalizeEditorQuestionsContent(article.content)
    const fingerprint = [
      article.id,
      article.status,
      article.slotStatus ?? '',
      normalizedContent,
      article.headline,
      article.hashtags,
      article.hbaHint?.id ?? '',
      article.hbaScore?.id ?? '',
      article.hbaScore?.status ?? '',
    ].join('|')
    if (articleSyncFingerprint.current === fingerprint) return
    articleSyncFingerprint.current = fingerprint

    setHeadline(article.headline)
    setContent(normalizedContent)
    setContentBaseline(normalizedContent)
    setHashtags(article.hashtags)
    setReviewEdits(article.reviewEdits ?? [])
    setTone(article.tone)
    setAngle(article.angle)
    setEditor(
      article.editor ??
        article.editorSuggested ??
        article.editorOptions?.[0] ??
        null,
    )
    setImageRatio(
      resolveEffectiveImageRatio(
        article.imageRatio,
        article.imageUrlSquare,
        article.imageUrlLandscape,
      ),
    )
    setPlatform(article.platform)
    setContentTextvorm(article.contentTextvorm ?? 'post')
    setObjective(article.objective ?? null)
    setHbaHint((prev) => {
      if (hbaHintSuppressedForArticle.current === article.id) return null
      if (article.hbaHint?.aanwijzing) return article.hbaHint
      if (prev?.aanwijzing && !prev.toegepast) return prev
      return article.hbaHint ?? null
    })
    const incomingScore = article.hbaScore ?? null
    const narrativeReady = article.brandNarrativeCompleteness?.complete ?? false
    const articleChanged = articleSyncArticleId.current !== article.id
    articleSyncArticleId.current = article.id
    if (
      incomingScore &&
      narrativeReady &&
      incomingScore.status === 'narrative_incomplete'
    ) {
      setHbaScore(null)
    } else if (incomingScore) {
      setHbaScore(incomingScore)
    } else if (articleChanged) {
      setHbaScore(null)
    }
    setHbaContentrolOverride(article.hbaContentrolOverride ?? null)
    setImageUrlSquare((prev) => {
      const next = article.imageUrlSquare?.trim()
      return next ? next : prev
    })
    setImageUrlLandscape((prev) => {
      const next = article.imageUrlLandscape?.trim()
      return next ? next : prev
    })
  }, [article])

  useEffect(() => {
    hbaHintSuppressedForArticle.current = null
    hbaHintRequestedFor.current = null
    hbaAutoScoreAttemptedFor.current = null
    articleSyncArticleId.current = null
  }, [article?.id])

  const hbaKlantPreview = searchParams.get('hbaKlant') === '1'

  useEffect(() => {
    if (!article || article.themeOnly || !slotId || usingMock || hbaKlantPreview) return
    if (article.status === 'archived' || article.slotStatus === 'archived') {
      const archiveId = article.slotId || slotId
      navigate(`/archive/${archiveId}${location.search}`, { replace: true })
    }
  }, [article, slotId, navigate, usingMock, hbaKlantPreview, location.search])

  useEffect(() => {
    if (!article || article.themeOnly || !slotId || usingMock) return
    const hasPendingReview = (article.reviewEdits ?? []).some((edit) => edit.status === 'pending')
    if (article.status === 'feedback' || hasPendingReview) {
      navigate(`/article/${slotId}/feedback`, { replace: true })
    }
  }, [article, slotId, navigate, usingMock])

  const displayImageRatio = resolveEffectiveImageRatio(
    imageRatio,
    imageUrlSquare ?? article?.imageUrlSquare,
    imageUrlLandscape ?? article?.imageUrlLandscape,
  )
  const useTestImage =
    !isManualPublishMode(article?.publishMode) &&
    isTestPost({
      usingMock,
      slotId,
      headline: article?.headline,
    })
  const imagePickOptions = { testFallback: useTestImage } as const
  const activeImageVariant = resolveImageRatio(displayImageRatio)
  const apiImageUrl = pickArticleImageUrl(
    displayImageRatio,
    article?.imageUrlSquare,
    article?.imageUrlLandscape,
    imagePickOptions,
  )
  const activePreviewUrl =
    activeImageVariant === 'square'
      ? (localPreviewSquare ??
        pickArticleImageUrl(displayImageRatio, imageUrlSquare, imageUrlLandscape, imagePickOptions) ??
        apiImageUrl)
      : (localPreviewLandscape ??
        pickArticleImageUrl(displayImageRatio, imageUrlSquare, imageUrlLandscape, imagePickOptions) ??
        apiImageUrl)

  const isApprovedOrPublished =
    article?.status === 'approved' ||
    article?.status === 'published' ||
    article?.status === 'archived' ||
    article?.slotStatus === 'published' ||
    article?.slotStatus === 'archived'

  async function handleImageUpload(file: File) {
    const variant = activeImageVariant
    const previewUrl = URL.createObjectURL(file)
    if (variant === 'square') setLocalPreviewSquare(previewUrl)
    else setLocalPreviewLandscape(previewUrl)

    if (!slotId || usingMock) return

    setUploadingImage(variant)
    setUploadError(null)
    try {
      const result = await uploadArticleImage(slotId, variant, file)
      setImageUrlSquare(result.imageUrlSquare)
      setImageUrlLandscape(result.imageUrlLandscape)
      if (variant === 'square') setLocalPreviewSquare(null)
      else setLocalPreviewLandscape(null)
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Afbeelding upload mislukt')
    } finally {
      setUploadingImage(null)
    }
  }

  async function handleGalleryImageSelect(imageId: string) {
    const variant = activeImageVariant
    if (!slotId || usingMock) return

    setUploadingImage(variant)
    setUploadError(null)
    try {
      const result = await attachGalleryImageToArticle(slotId, variant, imageId)
      setImageUrlSquare(result.imageUrlSquare)
      setImageUrlLandscape(result.imageUrlLandscape)
      if (variant === 'square') setLocalPreviewSquare(null)
      else setLocalPreviewLandscape(null)
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Gallery-beeld koppelen mislukt')
    } finally {
      setUploadingImage(null)
    }
  }

  async function handleDeleteImage() {
    const variant = activeImageVariant
    if (variant === 'square') setLocalPreviewSquare(null)
    else setLocalPreviewLandscape(null)

    if (!slotId || usingMock) {
      if (variant === 'square') setImageUrlSquare(null)
      else setImageUrlLandscape(null)
      return
    }

    setDeletingImage(true)
    setUploadError(null)
    try {
      const result = await deleteArticleImage(slotId, variant)
      setImageUrlSquare(result.imageUrlSquare)
      setImageUrlLandscape(result.imageUrlLandscape)
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Afbeelding verwijderen mislukt')
    } finally {
      setDeletingImage(false)
    }
  }

  const pendingReviewEdits = reviewEdits.filter((edit) => edit.status === 'pending')
  const showReviewDiff = !isApprovedOrPublished && pendingReviewEdits.length > 0

  const diffNodes = useMemo(() => {
    if (!showReviewDiff) return null
    const edit = pendingReviewEdits[0]
    return renderContentDiff(edit.originalText, edit.suggestedText)
  }, [pendingReviewEdits, showReviewDiff])

  const toneOptions = useMemo(
    () => toneOptionsFromStyles(undefined, article?.toneOptions),
    [article?.toneOptions],
  )

  useEffect(() => {
    if (toneOptions.length === 0) return
    setTone((prev) => {
      if (prev && toneOptions.includes(prev)) return prev
      if (article?.tone && toneOptions.includes(article.tone)) return article.tone
      return toneOptions[0]
    })
  }, [article?.tone, toneOptions])

  const editorOptions = useMemo(
    () => editorOptionsFromStyles(undefined, editor, article?.editorOptions),
    [article?.editorOptions, editor],
  )

  useEffect(() => {
    if (!article || article.themeOnly || usingMock || isApprovedOrPublished || writing) {
      setHbaScoreAutoWaiting(false)
      return
    }

    const narrativeReady = article.brandNarrativeCompleteness?.complete ?? false
    if (!narrativeReady) {
      setHbaScoreAutoWaiting(false)
      return
    }

    const incomingScore = article.hbaScore ?? null
    const staleIncompleteScore =
      narrativeReady && incomingScore?.status === 'narrative_incomplete'
    const hasScore = Boolean(
      (hbaScore && !(narrativeReady && hbaScore.status === 'narrative_incomplete'))
        || (incomingScore && !staleIncompleteScore),
    )
    if (hasScore) {
      setHbaScoreAutoWaiting(false)
      return
    }

    if ((hbaNarrativeScoreBlockers ?? []).length > 0) {
      setHbaScoreAutoWaiting(false)
      return
    }

    const body = stripEditorQuestionsBlock(article.contentAi ?? article.content ?? '').trim()
    if (!body) {
      setHbaScoreAutoWaiting(false)
      return
    }

    setHbaScoreAutoWaiting(true)
    const maxWaitMs = 120_000
    const started = Date.now()
    const interval = window.setInterval(() => {
      if (Date.now() - started >= maxWaitMs) {
        setHbaScoreAutoWaiting(false)
        return
      }
      void reload(true)
    }, 4000)
    const timeout = window.setTimeout(() => setHbaScoreAutoWaiting(false), maxWaitMs)

    return () => {
      window.clearInterval(interval)
      window.clearTimeout(timeout)
    }
  }, [
    article,
    usingMock,
    isApprovedOrPublished,
    writing,
    hbaScore,
    hbaNarrativeScoreBlockers,
    reload,
  ])

  useEffect(() => {
    if (!article?.id || article.themeOnly || usingMock || isApprovedOrPublished || writing || hbaRescoring) {
      return
    }

    const narrativeReady = article.brandNarrativeCompleteness?.complete ?? false
    if (!narrativeReady) {
      return
    }

    const incomingScore = article.hbaScore ?? null
    const staleIncompleteScore =
      narrativeReady && incomingScore?.status === 'narrative_incomplete'
    const hasScore = Boolean(
      (hbaScore && !(narrativeReady && hbaScore.status === 'narrative_incomplete'))
        || (incomingScore && !staleIncompleteScore),
    )
    if (hasScore) {
      return
    }

    if ((hbaNarrativeScoreBlockers ?? []).length > 0) {
      return
    }

    const body = stripEditorQuestionsBlock(article.contentAi ?? article.content ?? '').trim()
    if (!body) {
      return
    }

    if (hbaAutoScoreAttemptedFor.current === article.id) {
      return
    }
    hbaAutoScoreAttemptedFor.current = article.id

    void (async () => {
      setHbaScoreAutoWaiting(true)
      try {
        const result = await scoreHbaArticle(article.id, { force: false })
        if (result.status === 'narrative_incomplete') {
          const blockers =
            result.missingOrInconsistent?.filter((line) => line.trim() !== '') ??
            (result.message ? [result.message] : [])
          setHbaNarrativeScoreBlockers(
            blockers.length > 0 ? blockers : ['Merkarchitectuur is voor HBA nog niet consistent genoeg.'],
          )
          return
        }
        setHbaNarrativeScoreBlockers(null)
        if (result.score) {
          setHbaScore(result.score)
        } else {
          await reload(true)
        }
      } catch {
        // Geen reset van hbaAutoScoreAttemptedFor — voorkomt score-poll loop bij herhaalde API-fouten.
      } finally {
        setHbaScoreAutoWaiting(false)
      }
    })()
  }, [
    article,
    usingMock,
    isApprovedOrPublished,
    writing,
    hbaScore,
    hbaRescoring,
    hbaNarrativeScoreBlockers,
    reload,
  ])

  const waitingForBody =
    (writing || article?.writingPending) &&
    !stripEditorQuestionsBlock(content || article?.contentAi || article?.content || '').trim()

  const hasArticleText = stripEditorQuestionsBlock(
    content || article?.contentAi || article?.content || '',
  ).trim() !== ''
  const showThemeStart =
    Boolean(slotId) && !expectWriting && isThemeStartArticle(article, { writing })

  function confirmDiscardEditsForRewrite(): boolean {
    const hasEdits =
      headline.trim() !== (article?.headline ?? '').trim() ||
      content.trim() !== (article?.content ?? '').trim()
    if (!hasEdits) return true
    return window.confirm(
      'Handmatige wijzigingen in headline of tekst gaan verloren. Toch herschrijven met de gekozen meta?',
    )
  }

  async function pollRewriteResult(previousArticleId: string | null) {
    if (!slotId) return
    setRewriteNotice('AI herschrijft de tekst — dit duurt ongeveer 1–2 minuten…')
    const fresh = await waitForRewrittenArticle(slotId, previousArticleId)
    if (fresh) {
      await reload(true)
      setRewriteNotice('Klaar — nieuwe versie geladen.')
    } else {
      setRewriteNotice(
        'Rewrite duurt langer dan verwacht — herlaad de pagina zodra CE-03 klaar is.',
      )
    }
  }

  function rewriteStarted(saved?: { writing?: boolean; writingPending?: boolean } | null): boolean {
    return Boolean(saved?.writing || saved?.writingPending)
  }

  async function handleRewrite() {
    if (!slotId || usingMock) return
    if (!confirmDiscardEditsForRewrite()) return

    setRewriteNotice(null)
    setRewriting(true)
    const previousArticleId = article?.id ?? null
    try {
      await rewriteArticle(slotId, formPayload())
      await pollRewriteResult(previousArticleId)
    } catch (err) {
      setRewriteNotice(err instanceof Error ? err.message : 'Rewrite mislukt')
    } finally {
      setRewriting(false)
    }
  }

  async function handleToneChange(next: string) {
    const previous = tone
    setTone(next)
    if (!slotId || usingMock || isApprovedOrPublished || rewriting || writing) return
    if (next === (article?.tone ?? previous)) return
    if (!confirmDiscardEditsForRewrite()) {
      setTone(previous)
      return
    }

    setRewriteNotice(null)
    setRewriting(true)
    const previousArticleId = article?.id ?? null
    try {
      const saved = await save({ ...formPayload(), tone: next })
      if (rewriteStarted(saved)) {
        await pollRewriteResult(previousArticleId)
      }
    } catch (err) {
      setTone(previous)
      setRewriteNotice(err instanceof Error ? err.message : 'Rewrite mislukt')
    } finally {
      setRewriting(false)
    }
  }

  async function handleReedit() {
    if (!slotId || usingMock) return
    setRewriteNotice(null)
    setRewriting(true)
    try {
      const result = await reeditArticle(slotId, formPayload())
      if (result.headline) setHeadline(result.headline)
      if (result.content) {
        const normalized = normalizeEditorQuestionsContent(result.content)
        setContent(normalized)
        setContentBaseline(normalized)
      }
      await reload(true)
      setRewriteNotice('Aantekeningen verwerkt.')
    } catch (err) {
      setRewriteNotice(err instanceof Error ? err.message : 'Opnieuw bewerken mislukt')
    } finally {
      setRewriting(false)
    }
  }

  async function handleSave() {
    clearSaveNotice()
    const previousArticleId = article?.id ?? null
    const toneChanged = tone !== (article?.tone ?? '')
    try {
      const saved = await save(formPayload())
      setContentBaseline(content)
      if (toneChanged && rewriteStarted(saved)) {
        setRewriting(true)
        try {
          await pollRewriteResult(previousArticleId)
        } finally {
          setRewriting(false)
        }
        return
      }
      showSaveSuccess()
    } catch {
      // error shown via hook
    }
  }

  /** Aandachtspunten-correctie: baseline = tekst vóór deze edit → alleen laatste wijziging blauw. */
  function handleArticleContentChange(
    next: string,
    options?: { incrementalCorrection?: boolean },
  ) {
    if (options?.incrementalCorrection) {
      setContentBaseline(content)
    }
    setContent(next)
  }

  async function handleReview(reviewMode: ReviewMode) {
    setSubmitNotice(null)
    clearSaveNotice()
    if (textHasOpenPlaceholders(`${headline}\n${content}`)) {
      setSubmitNotice(OPEN_PLACEHOLDER_MESSAGE)
      return
    }
    try {
      const result = await submit(formPayload(), reviewMode)
      if (reviewMode === 'ai' && slotId) {
        const openFeedback = (result?.feedback ?? []).filter((item) => item.status === 'open')
        const pendingEdits = (result?.reviewEdits ?? []).filter((edit) => edit.status === 'pending')
        navigate(`/article/${slotId}/feedback`, {
          replace: true,
          state:
            openFeedback.length === 0 && pendingEdits.length === 0
              ? {
                  aiReviewWarning:
                    result?.aiReviewWarning ??
                    'AI-review afgerond maar geen feedback zichtbaar. Gebruik Opnieuw bewerken in de sidebar om aantekeningen in de copy te verwerken.',
                }
              : undefined,
        })
        return
      }
      if (reviewMode === 'human') {
        const mode = result?.publishMode ?? article?.publishMode
        if (mode === 'manual_without_review') {
          await handlePublishWp()
          return
        }
        if (result?.reviewMode === 'hba') {
          await reload()
          setSubmitNotice('Goedgekeurd — je kunt nu publiceren.')
          return
        }
        navigate('/agenda')
        return
      }
    } catch {
      // error shown via hook
    }
  }

  async function handleResolveEdit(editId: string, status: 'accepted' | 'rejected') {
    if (!slotId || usingMock) {
      setReviewEdits((items) =>
        items.map((item) => (item.id === editId ? { ...item, status } : item)),
      )
      if (status === 'rejected' && pendingReviewEdits[0]) {
        setContent(pendingReviewEdits[0].originalText)
      }
      return
    }

    setResolvingEdit(true)
    try {
      const result = await patchReviewEdit(slotId, editId, status)
      setReviewEdits(result.reviewEdits)
      setContent(result.content)
      await reload()
    } catch {
      // keep UI state
    } finally {
      setResolvingEdit(false)
    }
  }

  async function handlePublish() {
    await handlePublishWp()
  }

  async function handlePublishWp() {
    if (!slotId || usingMock) return
    if (textHasOpenPlaceholders(`${headline}\n${content}`)) {
      showPublishError(OPEN_PLACEHOLDER_MESSAGE)
      return
    }

    setPublishing(true)
    setPublishingWp(true)
    clearPublishNotice()
    try {
      const result = await publishWordPress(slotId)
      showPublishResultNotice(
        formatPublishDestinationsNotice(result),
        showPublishSuccess,
        showPublishError,
      )
      await reload()
    } catch (err) {
      showPublishError(err instanceof Error ? err.message : 'Publiceren mislukt')
    } finally {
      setPublishingWp(false)
      setPublishing(false)
    }
  }

  async function handleMarkPlaced(platform: string) {
    if (!article?.id || usingMock) return
    setMarkingPlaced(true)
    try {
      await markPublicationPlaced(article.id, platform)
      showPublishSuccess('Gemarkeerd als geplaatst.')
      await reload()
    } catch (err) {
      showPublishError(err instanceof Error ? err.message : 'Markeren mislukt')
    } finally {
      setMarkingPlaced(false)
    }
  }

  useEffect(() => {
    if (!article?.id || usingMock || writing) return
    if (isApprovedOrPublished) {
      setHbaHintLoading(false)
      return
    }
    if (hbaHintSuppressedForArticle.current === article.id) return
    const force = hbaHintNextForce.current
    if (force) {
      hbaHintNextForce.current = false
    } else if (hbaHint?.aanwijzing || article.hbaHint?.aanwijzing) {
      return
    }
    const draft = hbaHintDraftRef.current
    const body = stripEditorQuestionsBlock(draft?.content || article.contentAi || article.content || '')
    if (!body.trim()) return
    const requestKey = `${article.id}:${hbaHintRetry}:${force ? 'force' : 'auto'}`
    if (hbaHintRequestedFor.current === requestKey) return
    hbaHintRequestedFor.current = requestKey

    let cancelled = false
    setHbaHintLoading(true)
    setHbaHintError(null)
    void generateArticleHint(article.id, {
      headline: draft?.headline || article.headline,
      content: body,
      force,
    })
      .then((res) => {
        if (cancelled) return
        if (article?.id && hbaHintSuppressedForArticle.current === article.id) return
        if (res.hint?.aanwijzing) {
          setHbaHint(res.hint)
          setHbaHintError(null)
          return
        }
        if (res.code === 'article_closed') {
          setHbaHintError(null)
          return
        }
        const message =
          res.code === 'architecture_incomplete'
            ? 'Vul eerst je merkarchitectuur in — daarna kan de laag meekijken.'
            : res.code === 'guardrail_inactive'
              ? (res.architectureFootnote ??
                'Positioneringsadvies op het artikel start na de architectuurcheck. Open Merkarchitectuur (stap 7).')
              : (res.error ?? 'Geen aanwijzing ontvangen. Probeer opnieuw.')
        setHbaHintError(message)
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setHbaHintError(err instanceof Error ? err.message : 'Aanwijzing mislukt')
        }
      })
      .finally(() => {
        if (!cancelled) setHbaHintLoading(false)
      })
    return () => {
      cancelled = true
      if (hbaHintRequestedFor.current === requestKey) {
        hbaHintRequestedFor.current = null
      }
    }
  }, [
    article?.id,
    article?.contentAi,
    article?.content,
    article?.headline,
    article?.hbaHint?.aanwijzing,
    usingMock,
    writing,
    isApprovedOrPublished,
    hbaHintRetry,
    hbaHint?.aanwijzing,
  ])

  async function handleHbaHintApply() {
    if (!hbaHint?.id) {
      setHbaHintError('Aanwijzing kon niet worden toegepast. Herlaad de pagina en probeer opnieuw.')
      return
    }
    setHbaHintApplying(true)
    setHbaHintError(null)
    try {
      const applied = applyHbaHintToArticle(headline, content, hbaHint)
      if (!applied.changed && hintRewritePreviews(hbaHint).length > 0) {
        setHbaHintError(
          'De tekst is gewijzigd sinds deze aanwijzing. Pas handmatig aan of klik Opnieuw voor een nieuwe aanwijzing.',
        )
        return
      }

      const nextHeadline = applied.headline
      const nextContent = applied.content

      const result = await applyArticleHint(hbaHint.id, {
        headline: nextHeadline,
        content: nextContent,
      })
      if (!result.ok) {
        if (applied.changed) {
          await save({
            ...formPayload(),
            headline: nextHeadline,
            content: nextContent,
          })
          setHeadline(nextHeadline)
          setContent(nextContent)
          setContentBaseline(nextContent)
          setHbaHint({
            ...hbaHint,
            toegepast: !textHasOpenPlaceholders(`${nextHeadline}\n${nextContent}`),
          })
          articleSyncFingerprint.current = ''
          return
        }
        setHbaHintError(result.error ?? 'Toepassen mislukt')
        return
      }

      setHeadline(result.hint?.headline ?? nextHeadline)
      setContent(result.hint?.content ?? nextContent)
      setContentBaseline(result.hint?.content ?? nextContent)
      const appliedContent = result.hint?.content ?? nextContent
      const appliedHeadline = result.hint?.headline ?? nextHeadline
      const stillOpen = Boolean(result.hint?.openPlaceholders)
        || textHasOpenPlaceholders(`${appliedHeadline}\n${appliedContent}`)
      setHbaHint({ ...hbaHint, toegepast: !stillOpen })
      articleSyncFingerprint.current = ''
    } catch (err) {
      setHbaHintError(err instanceof Error ? err.message : 'Toepassen mislukt')
    } finally {
      setHbaHintApplying(false)
    }
  }

  async function handleHbaHintIgnore() {
    const hintId = hbaHint?.id ?? article?.hbaHint?.id
    if (article?.id) {
      hbaHintSuppressedForArticle.current = article.id
    }
    hbaHintRequestedFor.current = null
    setHbaHint(null)
    setHbaHintError(null)
    setHbaHintLoading(false)
    if (!hintId || usingMock) return
    try {
      await ignoreArticleHint(hintId)
    } catch {
      // Lokaal al dichtgeklapt; negeer API-fout stilletjes.
    }
  }

  function handleHbaHintRetry() {
    if (!article?.id || usingMock || isApprovedOrPublished) return
    hbaHintSuppressedForArticle.current = null
    hbaHintDraftRef.current = {
      headline,
      content: stripEditorQuestionsBlock(content),
    }
    hbaHintRequestedFor.current = null
    hbaHintNextForce.current = true
    setHbaHint(null)
    setHbaHintError(null)
    setHbaHintRetry((n) => n + 1)
  }

  const hbaHighlightPassage =
    !hbaHint?.toegepast ? (hbaHint?.passageVoor || hbaHint?.ctaVoor || null) : null

  function formPayload() {
    return {
      headline,
      content: normalizeEditorQuestionsContent(content),
      hashtags,
      tone,
      angle,
      editor,
      imageRatio,
      platform,
      contentTextvorm,
      objective,
      hbaContentrolOverride,
    }
  }

  async function handleHbaRescore() {
    if (!article?.id || usingMock) return
    setHbaRescoring(true)
    try {
      const result = await scoreHbaArticle(article.id, { force: true })
      if (result.status === 'narrative_incomplete') {
        setHbaScore(null)
        const blockers =
          result.missingOrInconsistent?.filter((line) => line.trim() !== '') ??
          (result.message ? [result.message] : [])
        setHbaNarrativeScoreBlockers(blockers.length > 0 ? blockers : ['Merkarchitectuur is voor HBA nog niet consistent genoeg.'])
        return
      }
      setHbaNarrativeScoreBlockers(null)
      if (result.score?.status === 'scored') {
        setHbaScore(result.score)
      } else if (result.score) {
        setHbaScore(result.score)
      } else if (result.message) {
        showPublishError(result.message)
      }
    } catch (err) {
      showPublishError(err instanceof Error ? err.message : 'Beoordeling mislukt — probeer het opnieuw of meld het ons.')
    } finally {
      setHbaRescoring(false)
    }
  }

  if (loading && !writing) {
    return (
      <GridShell
        pageTitle="Artikel"
        activeNav="article"
        mainLayout="grid2"
        main={<LoadingDots label="Artikel laden" className="text-ce-black" />}
      />
    )
  }

  if (showThemeStart && article && slotId) {
    return (
      <GridShell
        pageTitle={article.headline.trim() || 'Thema'}
        activeNav="article"
        mainLayout="grid2"
        main={
          <ThemeStartPanel
            article={article}
            slotId={slotId}
            onCopyModusChange={() => {
              void reload()
            }}
          />
        }
      />
    )
  }

  if (!article) {
    return (
      <GridShell
        pageTitle="Artikel"
        activeNav="article"
        mainLayout="grid2"
        main={
          <div>
            {writing ? (
              <>
                <ArticleWritingNotice slow={writingSlow} />
                <div className="mt-[18px] space-y-[12px]">
                  <div className={pillActionsRowClass}>
                    <PillButton variant="second" disabled>
                      Opnieuw bewerken
                    </PillButton>
                  </div>
                  <ActionNotice
                    message={researchSubmitNotice}
                    kind={researchSubmitNoticeKind}
                    fading={researchSubmitNoticeFading}
                    className="mb-0"
                  />
                </div>
              </>
            ) : (
              <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                {error ??
                  (slotId
                    ? 'Artikel niet gevonden.'
                    : 'Nog geen artikel — open een slot via Agenda.')}
              </p>
            )}
          </div>
        }
      />
    )
  }

  return (
    <GridShell
      pageTitle={
        article.status === 'approved' || article.status === 'published'
          ? `${article.weekLabel} - Approved`
          : article.weekLabel
      }
      activeNav="article"
      mainLayout="grid2"
      mainContentMaxWidth={ARTICLE_COLUMN_WIDTH}
      metaAlign="flush"
      main={
        <>
          {error && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>
          )}
          {usingMock && slotId && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-beige">
              Mock data — configureer DB + API voor live artikel ({slotId}).
            </p>
          )}

          {uploadError && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{uploadError}</p>
          )}

          <ImageUpload
            ratio={displayImageRatio}
            previewUrl={activePreviewUrl}
            uploading={uploadingImage === activeImageVariant}
            deleting={deletingImage}
            readOnly={isApprovedOrPublished}
            galleryEnabled={!usingMock}
            onFileSelect={
              isApprovedOrPublished ? undefined : (file) => void handleImageUpload(file)
            }
            onGalleryImageSelect={
              isApprovedOrPublished || usingMock
                ? undefined
                : (imageId) => void handleGalleryImageSelect(imageId)
            }
            onDelete={
              isApprovedOrPublished || !activePreviewUrl
                ? undefined
                : () => void handleDeleteImage()
            }
          />

          {!isApprovedOrPublished && (
            <ReviewEditsPanel
              edits={reviewEdits}
              busy={resolvingEdit}
              onResolve={(id, status) => void handleResolveEdit(id, status)}
            />
          )}

          <ArticleCard
            headline={headline}
            onHeadlineChange={setHeadline}
            content={content}
            contentBaseline={contentBaseline}
            onContentChange={setContent}
            hashtags={hashtags}
            onHashtagsChange={setHashtags}
            highlightPassage={hbaHighlightPassage}
            contentSlot={
              waitingForBody ? (
                <ArticleWritingNotice slow={writingSlow} />
              ) : showReviewDiff ? (
                <div className="whitespace-pre-wrap text-[14px] leading-[18px] text-ce-black">
                  <span>{diffNodes}</span>
                  {BODY_HASHTAG_SEPARATOR}
                  <span className="block text-ce-black">{hashtags}</span>
                </div>
              ) : undefined
            }
          />

          <div className="mt-[18px]">
            {submitNotice && (
              <p className={`mb-[12px] text-[14px] leading-[18px] ${submitNotice === OPEN_PLACEHOLDER_MESSAGE ? 'text-ce-red' : 'text-ce-beige'}`}>
                {submitNotice}
              </p>
            )}
            {article.channelHardIssues && article.channelHardIssues.length > 0 ? (
              <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">
                {article.channelHardIssues.join(' ')}
              </p>
            ) : null}
            <ArticleReviewPanel
              status={article.status}
              headline={headline}
              content={content}
              hashtags={hashtags}
              platform={platform}
              publishMode={article.publishMode}
              reviewUrl={article.reviewUrl}
              reviewerEmail={article.reviewerEmail}
              mailSent={article.mailSent}
              mailWarning={article.mailWarning}
              saveMessage={saveMessage}
              saveNoticeKind={saveNoticeKind}
              saveNoticeFading={saveNoticeFading}
              publishNotice={publishNotice}
              publishNoticeKind={publishNoticeKind}
              publishNoticeFading={publishNoticeFading}
              actionNotice={researchSubmitNotice}
              actionNoticeKind={researchSubmitNoticeKind}
              actionNoticeFading={researchSubmitNoticeFading}
              onSubmit={(mode) => void handleReview(mode)}
              onReedit={isApprovedOrPublished ? undefined : () => void handleReedit()}
              reeditDisabled={writing}
              onSave={() => void handleSave()}
              onPublish={() => void handlePublish()}
              onPublishWp={() => void handlePublishWp()}
              submitting={submitting}
              reediting={rewriting}
              reeditNotice={rewriteNotice}
              saving={saving}
              publishing={publishing}
              publishingWp={publishingWp}
              wordpressPostId={article.wordpressPostId}
              publications={article.publications}
              markingPlaced={markingPlaced}
              onMarkPlaced={(platform) => void handleMarkPlaced(platform)}
              hasArticleText={hasArticleText}
            />
          </div>

          {article.sourceUrl && (
            <SourceAccordion label="Artikelbron" url={article.sourceUrl} />
          )}
        </>
      }
      meta={
        <ArticleMetaSidebar
          rationale={article.rationale}
          tone={tone}
          editor={editor}
          imageRatio={imageRatio}
          platform={platform}
          contentTextvorm={contentTextvorm}
          toneSuggested={article.toneSuggested}
          editorSuggested={article.editorSuggested}
          imageRatioSuggested={article.imageRatioSuggested}
          platformSuggested={article.platformSuggested}
          onToneChange={handleToneChange}
          onEditorChange={setEditor}
          onImageRatioChange={setImageRatio}
          onPlatformChange={setPlatform}
          onContentTextvormChange={setContentTextvorm}
          objective={objective}
          objectiveSuggested={article.objectiveSuggested}
          objectiveOptions={article.objectiveOptions}
          onObjectiveChange={setObjective}
          readonly={isApprovedOrPublished}
          hideSelfReview={isApprovedOrPublished}
          selfReview={article.selfReview}
          slotId={slotId}
          articleContent={content}
          onArticleContentChange={handleArticleContentChange}
          onRewrite={
            !isApprovedOrPublished && !writing ? () => void handleRewrite() : undefined
          }
          rewriting={rewriting}
          rewriteNotice={rewriteNotice}
          toneOptions={toneOptions}
          editorOptions={editorOptions}
          hbaHint={isApprovedOrPublished ? null : hbaHint}
          hbaHintLoading={!isApprovedOrPublished && hbaHintLoading}
          hbaHintError={isApprovedOrPublished ? null : hbaHintError}
          onHbaHintRetry={isApprovedOrPublished ? undefined : handleHbaHintRetry}
          onHbaHintApply={isApprovedOrPublished ? undefined : () => void handleHbaHintApply()}
          onHbaHintIgnore={isApprovedOrPublished ? undefined : handleHbaHintIgnore}
          hbaHintApplying={hbaHintApplying}
          hbaScore={hbaScore}
          hbaScoreDisplayMode={article.hbaScoreDisplayMode ?? 'band'}
          hbaContentrolOverride={hbaContentrolOverride}
          brandNarrativeCompleteness={article.brandNarrativeCompleteness}
          onContentrolOverrideChange={setHbaContentrolOverride}
          onHbaRescore={handleHbaRescore}
          hbaNarrativeScoreBlockers={hbaNarrativeScoreBlockers}
          hbaScoreAutoWaiting={hbaScoreAutoWaiting}
          hbaRescoring={hbaRescoring}
          positioningArchetype={article.positioningArchetype ?? null}
          positioningBrandGoal={article.positioningBrandGoal ?? null}
          hideHbaContentrol={article.hideHbaContentrol ?? false}
        />
      }
    />
  )
}
