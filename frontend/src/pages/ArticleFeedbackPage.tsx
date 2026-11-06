import { useEffect, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import {
  patchFeedback,
  patchReviewEdit,
  patchReviewEditProgress,
  publishWordPress,
  formatPublishDestinationsNotice,
  rewriteArticle,
  waitForRewrittenArticle,
  reeditArticle,
  uploadArticleImage,
  attachGalleryImageToArticle,
  deleteArticleImage,
} from '../api/articles'
import { GridShell } from '../layout/GridShell'
import { GRID2_ROW } from '../layout/grid.constants'
import { ArticleMetaSidebar } from '../components/ArticleMetaSidebar'
import { FeedbackArticleBody, type FeedbackArticleBodyHandle } from '../components/FeedbackArticleBody'
import { FeedbackCard } from '../components/FeedbackCard'
import { ImageUpload, resolveImageRatio } from '../components/ImageUpload'
import { ArticleReviewPanel } from '../components/ArticleReviewPanel'
import { SourceAccordion } from '../components/MetaSection'
import { feedbackMock } from '../data/feedback.mock'
import { useArticle } from '../hooks/useArticle'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { showPublishResultNotice } from '../utils/publishNotice'
import { OPEN_PLACEHOLDER_MESSAGE, textHasOpenPlaceholders } from '../utils/hbaHintApply'
import { ActionNotice } from '../components/ui/ActionNotice'
import type { FeedbackItemDto, ReviewMode } from '../types/api'
import { buildFeedbackDisplayItems, type FeedbackDisplayItem } from '../utils/feedbackItems'
import {
  applyStoredStatuses,
  buildTrackedDoc,
  clearStoredStatuses,
  deriveContent,
  extractStatuses,
  listChanges,
  loadStoredStatuses,
  pendingCount,
  resolveChange,
  storeStatuses,
  updateDocFromEditedContent,
  type TrackedDoc,
} from '../utils/trackedChanges'
import { isManualPublishMode, isTestPost, pickArticleImageUrl, resolveEffectiveImageRatio } from '../utils/mediaUrl'
import { toneOptionsFromStyles } from '../utils/toneStyles'
import { editorOptionsFromStyles } from '../utils/editorStyles'

export function ArticleFeedbackPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { slotId } = useParams<{ slotId?: string }>()
  const { article, loading, saving, submitting, error, usingMock, save, submit, reload } =
    useArticle(slotId)

  const [headline, setHeadline] = useState('')
  const [content, setContent] = useState('')
  const [hashtags, setHashtags] = useState('')
  const [feedback, setFeedback] = useState<FeedbackItemDto[]>([])
  const [resolvedIds, setResolvedIds] = useState<Set<string>>(new Set())
  const [publishing, setPublishing] = useState(false)
  const [publishingWp, setPublishingWp] = useState(false)
  const [imageUrlSquare, setImageUrlSquare] = useState<string | null>(null)
  const [imageUrlLandscape, setImageUrlLandscape] = useState<string | null>(null)
  const [localPreview, setLocalPreview] = useState<string | null>(null)
  const [uploadingImage, setUploadingImage] = useState(false)
  const [deletingImage, setDeletingImage] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const {
    message: submitNotice,
    kind: submitNoticeKind,
    fading: submitNoticeFading,
    showError: showSubmitNotice,
    clear: clearSubmitNotice,
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
  const {
    message: publishNotice,
    kind: publishNoticeKind,
    fading: publishNoticeFading,
    showSuccess: showPublishSuccess,
    showError: showPublishError,
    clear: clearPublishNotice,
  } = useActionNotice()
  const routeAiWarning =
    typeof location.state === 'object' &&
    location.state !== null &&
    'aiReviewWarning' in location.state &&
    typeof (location.state as { aiReviewWarning?: unknown }).aiReviewWarning === 'string'
      ? (location.state as { aiReviewWarning: string }).aiReviewWarning
      : null

  const [tone, setTone] = useState('')
  const [angle, setAngle] = useState('general')
  const [editor, setEditor] = useState<string | null>(null)
  const [imageRatio, setImageRatio] = useState('square')
  const [platform, setPlatform] = useState('Linkedin')
  const [objective, setObjective] = useState<string | null>(null)

  /** Word-model: wijzigingen 1x berekend uit de review-edit, daarna alleen statusflips. */
  const [trackedDoc, setTrackedDoc] = useState<TrackedDoc | null>(null)
  const trackedDocRef = useRef<TrackedDoc | null>(null)
  const trackedEditIdRef = useRef<string | null>(null)
  const finalizingRef = useRef(false)
  const bodyRef = useRef<FeedbackArticleBodyHandle>(null)

  useEffect(() => {
    trackedDocRef.current = trackedDoc
  }, [trackedDoc])

  const pendingEdit = useMemo(() => {
    const pending = (article?.reviewEdits ?? []).filter((edit) => edit.status === 'pending')
    return pending.length > 0 ? pending[pending.length - 1] : null
  }, [article?.reviewEdits])

  useEffect(() => {
    if (!article) return
    setHeadline(article.headline)
    setHashtags(article.hashtags)
    setFeedback(article.feedback ?? [])
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
    setObjective(article.objective ?? null)
    setImageUrlSquare((prev) => {
      const next = article.imageUrlSquare?.trim()
      return next ? next : prev
    })
    setImageUrlLandscape((prev) => {
      const next = article.imageUrlLandscape?.trim()
      return next ? next : prev
    })
  }, [article])

  // Tracked doc opbouwen zodra er een (nieuwe) pending review-edit is.
  useEffect(() => {
    if (!article || finalizingRef.current) return

    if (!pendingEdit) {
      trackedEditIdRef.current = null
      trackedDocRef.current = null
      setTrackedDoc(null)
      setContent(article.content)
      return
    }

    if (trackedEditIdRef.current === pendingEdit.id && trackedDocRef.current) {
      return
    }

    trackedEditIdRef.current = pendingEdit.id

    let doc = buildTrackedDoc(pendingEdit.id, pendingEdit.originalText, pendingEdit.suggestedText)
    const serverStatuses = pendingEdit.progress?.statuses ?? {}
    const localStatuses = slotId ? loadStoredStatuses(slotId, pendingEdit.id) : {}
    doc = applyStoredStatuses(doc, { ...localStatuses, ...serverStatuses })
    if (pendingEdit.progress?.draftContent?.trim()) {
      doc = updateDocFromEditedContent(doc, pendingEdit.progress.draftContent)
    }
    trackedDocRef.current = doc
    setTrackedDoc(doc)
    setContent(deriveContent(doc))
    setResolvedIds(new Set())
  }, [article, pendingEdit, slotId])

  const headlineAi = article?.headlineAi ?? article?.headline ?? ''
  const headlineChanged = headlineAi.trim() !== '' && headline.trim() !== headlineAi.trim()

  const allDisplayItems = useMemo(() => {
    if (!article) return []
    return buildFeedbackDisplayItems({
      feedback,
      reviewEdits: article.reviewEdits ?? [],
      headline,
      headlineAi,
      content,
      feedbackBaseline: '',
      reviewerName: 'Review',
    })
  }, [article, feedback, headline, headlineAi, content])

  const displayItems = useMemo(
    () => allDisplayItems.filter((item) => !resolvedIds.has(item.id)),
    [allDisplayItems, resolvedIds],
  )

  const imageFeedback = displayItems.filter((item) => item.type === 'image')
  const headlineFeedback = displayItems.find((item) => item.lineIndex === -1)
  const apiTextFeedback = displayItems.filter(
    (item) => item.source === 'api' && item.type === 'text',
  )
  const showMockFeedback = usingMock && displayItems.length === 0

  const displayImageRatio = resolveEffectiveImageRatio(
    imageRatio,
    imageUrlSquare ?? article?.imageUrlSquare,
    imageUrlLandscape ?? article?.imageUrlLandscape,
  )

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
  const useTestImage =
    !isManualPublishMode(article?.publishMode) &&
    isTestPost({
      usingMock,
      slotId,
      headline: article?.headline,
    })
  const imagePickOptions = { testFallback: useTestImage } as const
  const activeImageVariant = resolveImageRatio(displayImageRatio)
  const activePreviewUrl =
    localPreview ??
    pickArticleImageUrl(displayImageRatio, imageUrlSquare, imageUrlLandscape, imagePickOptions) ??
    pickArticleImageUrl(
      displayImageRatio,
      article?.imageUrlSquare,
      article?.imageUrlLandscape,
      imagePickOptions,
    )

  /** Sync DOM → tracked doc (okay/delete blokkeert blur via preventDefault). */
  function syncBodyFromDom(): TrackedDoc | null {
    const current = trackedDocRef.current
    if (!current) return null

    const flushed = bodyRef.current?.flushEdits()
    if (!flushed) return current

    if (flushed.hashtags !== hashtags) setHashtags(flushed.hashtags)
    const nextDoc = updateDocFromEditedContent(current, flushed.body)
    trackedDocRef.current = nextDoc
    setTrackedDoc(nextDoc)
    return nextDoc
  }

  /** Laatste change afgehandeld → edit serverzijde afronden (Word: document is schoon). */
  async function finalizeIfDone(doc: TrackedDoc, finalContent: string) {
    if (!slotId || pendingCount(doc) > 0 || finalizingRef.current) return
    finalizingRef.current = true
    clearSubmitNotice()
    try {
      const anyAccepted = listChanges(doc).some((change) => change.status === 'accepted')
      const decision = anyAccepted ? 'accepted' : 'rejected'
      await patchReviewEdit(slotId, doc.editId, decision, finalContent)
      const { content: _content, ...metaOnly } = formPayload()
      await save(metaOnly)
      const refreshed = await reload(true)
      const stillPending = (refreshed?.reviewEdits ?? []).some(
        (edit) => edit.id === doc.editId && edit.status === 'pending',
      )
      if (stillPending) {
        storeStatuses(slotId, doc)
        throw new Error('Terugkoppeling opslaan mislukt — probeer opnieuw')
      }
      clearStoredStatuses(slotId, doc.editId)
    } catch (err) {
      storeStatuses(slotId, doc)
      showSubmitNotice(err instanceof Error ? err.message : 'Terugkoppeling opslaan mislukt')
      throw err
    } finally {
      finalizingRef.current = false
    }
  }

  /** okay = accepteren, delete = verwerpen — alléén statusflip, geen tekst-gepuzzel. */
  async function handleChangeAction(changeId: string, action: 'okay' | 'deleted') {
    if (finalizingRef.current) return
    const synced = syncBodyFromDom()
    const current = synced ?? trackedDocRef.current
    if (!current) return

    const nextDoc = resolveChange(current, changeId, action === 'okay' ? 'accepted' : 'rejected')
    const nextContent = deriveContent(nextDoc)
    trackedDocRef.current = nextDoc
    setTrackedDoc(nextDoc)
    setContent(nextContent)

    if (!slotId || usingMock) return
    storeStatuses(slotId, nextDoc)

    try {
      await patchReviewEditProgress(slotId, nextDoc.editId, {
        statuses: extractStatuses(nextDoc),
        draftContent: nextContent,
      })
    } catch {
      // localStorage + server progress zijn fallback — partial save mag niet blokkeren
    }

    if (pendingCount(nextDoc) === 0) {
      try {
        await finalizeIfDone(nextDoc, nextContent)
      } catch {
        // foutmelding staat in submitNotice
      }
    }
  }

  /** Handmatige bewerking: eigen edits worden niet getrackt (direct geaccepteerd). */
  function handleBodyEdited(nextBody: string, nextTags: string) {
    setHashtags(nextTags)
    const current = trackedDocRef.current
    if (current) {
      const nextDoc = updateDocFromEditedContent(current, nextBody)
      trackedDocRef.current = nextDoc
      setTrackedDoc(nextDoc)
      setContent(deriveContent(nextDoc))
      if (slotId && !usingMock) storeStatuses(slotId, nextDoc)
    } else {
      setContent(nextBody)
    }
  }

  async function handleFeedbackAction(item: FeedbackDisplayItem, action: 'okay' | 'deleted') {
    if (usingMock || !slotId) {
      setResolvedIds((prev) => new Set(prev).add(item.id))
      return
    }

    if (item.source === 'api') {
      try {
        await patchFeedback(slotId, item.id, action === 'okay' ? 'okay' : 'deleted')
        if (action === 'deleted' && item.comment.trim()) {
          const needle = item.comment.trim()
          const lines = content.split('\n')
          const idx = lines.findIndex(
            (line) => line.trim() === needle || line.includes(needle),
          )
          if (idx >= 0) {
            const nextLines = lines.filter((_, i) => i !== idx)
            const nextContent = nextLines.join('\n')
            setContent(nextContent)
            await save({ ...formPayload(), content: nextContent })
          } else if (needle.startsWith('#') && hashtags.includes(needle)) {
            setHashtags('')
            await save({ ...formPayload(), hashtags: '' })
          }
        }
        await reload()
      } catch (err) {
        showSubmitNotice(err instanceof Error ? err.message : 'Terugkoppeling bijwerken mislukt')
      }
      return
    }

    if (item.source === 'headline' && action === 'deleted') {
      setHeadline(headlineAi)
      setResolvedIds((prev) => new Set(prev).add(item.id))
      return
    }

    if (item.source === 'headline' && action === 'okay') {
      setResolvedIds((prev) => new Set(prev).add(item.id))
    }
  }

  async function handleImageUpload(file: File) {
    const previewUrl = URL.createObjectURL(file)
    setLocalPreview(previewUrl)

    if (!slotId || usingMock) return

    setUploadingImage(true)
    setUploadError(null)
    try {
      const result = await uploadArticleImage(slotId, activeImageVariant, file)
      setImageUrlSquare(result.imageUrlSquare)
      setImageUrlLandscape(result.imageUrlLandscape)
      setImageRatio(activeImageVariant)
      setLocalPreview(null)
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Afbeelding upload mislukt')
    } finally {
      setUploadingImage(false)
    }
  }

  async function handleGalleryImageSelect(imageId: string) {
    if (!slotId || usingMock) return

    setUploadingImage(true)
    setUploadError(null)
    try {
      const result = await attachGalleryImageToArticle(slotId, activeImageVariant, imageId)
      setImageUrlSquare(result.imageUrlSquare)
      setImageUrlLandscape(result.imageUrlLandscape)
      setImageRatio(activeImageVariant)
      setLocalPreview(null)
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Gallery-beeld koppelen mislukt')
    } finally {
      setUploadingImage(false)
    }
  }

  async function handleDeleteImage() {
    setLocalPreview(null)

    if (!slotId || usingMock) {
      if (activeImageVariant === 'square') setImageUrlSquare(null)
      else setImageUrlLandscape(null)
      return
    }

    setDeletingImage(true)
    setUploadError(null)
    try {
      const result = await deleteArticleImage(slotId, activeImageVariant)
      setImageUrlSquare(result.imageUrlSquare)
      setImageUrlLandscape(result.imageUrlLandscape)
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'Afbeelding verwijderen mislukt')
    } finally {
      setDeletingImage(false)
    }
  }

  function formPayload() {
    return {
      headline,
      content,
      hashtags,
      tone,
      angle,
      editor,
      imageRatio,
      platform,
      objective,
    }
  }

  async function handleRewrite() {
    if (!slotId || usingMock) return
    if (trackedDoc && pendingCount(trackedDoc) > 0) {
      setRewriteNotice('Verwerk eerst open feedback-wijzigingen voordat je herschrijft.')
      return
    }
    if (
      !window.confirm(
        'Herschrijven slaat meta op en genereert rationale + tekst opnieuw. Open feedback blijft staan; de tekst wordt vervangen. Doorgaan?',
      )
    ) {
      return
    }

    setRewriteNotice(null)
    setRewriting(true)
    const previousArticleId = article?.id ?? null
    try {
      const { content: _content, ...metaOnly } = formPayload()
      await rewriteArticle(slotId, metaOnly)
      setRewriteNotice('AI herschrijft de tekst — dit duurt ongeveer 1–2 minuten…')
      const fresh = await waitForRewrittenArticle(slotId, previousArticleId)
      if (fresh) {
        navigate(`/article/${slotId}`, { replace: true })
      } else {
        setRewriteNotice(
          'Herschrijven duurt langer dan verwacht — herlaad de pagina zodra de schrijver klaar is.',
        )
      }
    } catch (err) {
      setRewriteNotice(err instanceof Error ? err.message : 'Herschrijven mislukt')
    } finally {
      setRewriting(false)
    }
  }

  async function handleReedit() {
    if (!slotId || usingMock) return
    if (trackedDoc && pendingCount(trackedDoc) > 0) {
      setRewriteNotice('Verwerk eerst open feedback-wijzigingen voordat je opnieuw bewerkt.')
      return
    }

    setRewriteNotice(null)
    setRewriting(true)
    try {
      const result = await reeditArticle(slotId, formPayload())
      if (result.headline) setHeadline(result.headline)
      if (result.content) setContent(result.content)
      await reload(true)
      setRewriteNotice('Aantekeningen verwerkt.')
      navigate(`/article/${slotId}`, { replace: true })
    } catch (err) {
      setRewriteNotice(err instanceof Error ? err.message : 'Opnieuw bewerken mislukt')
    } finally {
      setRewriting(false)
    }
  }

  async function handleSave() {
    clearSaveNotice()
    try {
      const payload = formPayload()
      if (trackedDoc && pendingCount(trackedDoc) > 0) {
        const { content: _content, ...metaOnly } = payload
        await save(metaOnly)
      } else {
        await save(payload)
      }
      showSaveSuccess()
    } catch {
      // error via hook
    }
  }

  async function handleReview(reviewMode: ReviewMode) {
    clearSubmitNotice()
    if (textHasOpenPlaceholders(`${headline}\n${content}`)) {
      showSubmitNotice(OPEN_PLACEHOLDER_MESSAGE)
      return
    }
    try {
      const flushed = bodyRef.current?.flushEdits()
      if (flushed) {
        setHashtags(flushed.hashtags)
        setContent(flushed.body)
        if (trackedDocRef.current) {
          const nextDoc = updateDocFromEditedContent(trackedDocRef.current, flushed.body)
          trackedDocRef.current = nextDoc
          setTrackedDoc(nextDoc)
        }
      }

      const payload = {
        ...formPayload(),
        content: flushed?.body ?? content,
        hashtags: flushed?.hashtags ?? hashtags,
      }

      // Openstaande wijzigingen tellen bij submit als geaccepteerd (huidige weergave).
      if (trackedDoc && slotId && !usingMock && pendingCount(trackedDoc) > 0) {
        const finalContent = flushed?.body ?? content
        await patchReviewEdit(slotId, trackedDoc.editId, 'accepted', finalContent)
        clearStoredStatuses(slotId, trackedDoc.editId)
      }
      const result = await submit(payload, reviewMode)
      if (reviewMode === 'human') {
        const mode = result?.publishMode ?? article?.publishMode
        if (mode === 'manual_without_review') {
          await handlePublishWp()
          return
        }
        if (result?.mailSent === false) {
          showSubmitNotice(result.mailWarning ?? 'Beoordelingsmail niet verstuurd')
          return
        }
        navigate('/agenda', {
          state: {
            submitNotice:
              result?.mailSent === true
                ? `Review-mail verstuurd${result.reviewerEmail ? ` naar ${result.reviewerEmail}` : ''}.`
                : undefined,
          },
        })
      } else {
        const openFeedback = (result?.feedback ?? []).filter((item) => item.status === 'open')
        const pendingEdits = (result?.reviewEdits ?? []).filter((edit) => edit.status === 'pending')
        if (openFeedback.length === 0 && pendingEdits.length === 0) {
          showSubmitNotice(
            result?.aiReviewWarning ??
              'AI-beoordeling afgerond maar geen terugkoppeling zichtbaar — sla eerst op en probeer opnieuw, of gebruik Opnieuw bewerken voor een nieuwe tekst.',
          )
        } else {
          clearSubmitNotice()
        }
        await reload()
      }
    } catch {
      // error shown via hook
    }
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

  async function handlePublish() {
    await handlePublishWp()
  }

  if (loading) {
    return (
      <GridShell
        pageTitle="Terugkoppeling"
        activeNav="article"
        mainLayout="grid2"
        main={<p>Laden…</p>}
      />
    )
  }

  if (!article) {
    return (
      <GridShell
        pageTitle="Terugkoppeling"
        activeNav="article"
        mainLayout="grid2"
        main={
          <div className="space-y-[12px] text-[14px] leading-[18px]">
            <p className="m-0 text-ce-red">
              {error ?? 'Artikel niet gevonden — log opnieuw in en open Terugkoppeling via de agenda.'}
            </p>
            {!slotId && (
              <p className="m-0 text-ce-beige">
                Geen slot gekozen. Ga naar Agenda en klik op Terugkoppeling bij het artikel.
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
        <>
          <span className="text-ce-blue">{article.weekLabel}</span>
          <span className="text-ce-orange"> - Terugkoppeling</span>
        </>
      }
      activeNav="article"
      mainLayout="grid2"
      main={
        <>
          {error && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>
          )}
          {usingMock && slotId && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-beige">
              Mock feedback — configureer DB + API voor live review ({slotId}).
            </p>
          )}

          {uploadError && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{uploadError}</p>
          )}
          <ActionNotice message={submitNotice} kind={submitNoticeKind} fading={submitNoticeFading} />
          {routeAiWarning && !submitNotice && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{routeAiWarning}</p>
          )}
          {!submitNotice &&
            !routeAiWarning &&
            displayItems.length === 0 &&
            article.status === 'feedback' &&
            !pendingEdit && (
              <p className="mb-[12px] text-[14px] leading-[18px] text-ce-beige">
                Geen open terugkoppeling — gebruik Opnieuw bewerken om aantekeningen in de copy te verwerken.
              </p>
            )}

          <div className={GRID2_ROW.rowClass}>
            <div className={GRID2_ROW.mainClass}>
              <ImageUpload
                ratio={displayImageRatio}
                previewUrl={activePreviewUrl}
                uploading={uploadingImage}
                deleting={deletingImage}
                galleryEnabled={!usingMock}
                onFileSelect={(file) => void handleImageUpload(file)}
                onGalleryImageSelect={
                  usingMock ? undefined : (imageId) => void handleGalleryImageSelect(imageId)
                }
                onDelete={
                  activePreviewUrl ? () => void handleDeleteImage() : undefined
                }
              />
            </div>

            <div className={GRID2_ROW.sideClass}>
              {imageFeedback.map((item) => (
                <FeedbackCard
                  key={item.id}
                  name={item.name}
                  comment={item.comment}
                  actionLabel={item.actionLabel}
                  commentTone="image"
                  onOkay={() => void handleFeedbackAction(item, 'okay')}
                  onDelete={() => void handleFeedbackAction(item, 'deleted')}
                />
              ))}
              {showMockFeedback && (
                <FeedbackCard
                  name={feedbackMock.imageFeedback.name}
                  comment={feedbackMock.imageFeedback.comment}
                  actionLabel={feedbackMock.imageFeedback.actionLabel}
                  commentTone="image"
                />
              )}
            </div>
          </div>

          <FeedbackArticleBody
            ref={bodyRef}
            headline={headline}
            onHeadlineChange={setHeadline}
            headlineClassName={headlineChanged ? 'text-ce-red' : undefined}
            doc={trackedDoc}
            content={content}
            hashtags={hashtags}
            onBodyEdited={handleBodyEdited}
            onChangeAction={(changeId, action) => void handleChangeAction(changeId, action)}
            headlineFeedback={headlineFeedback}
            apiFeedbackItems={apiTextFeedback}
            onFeedbackAction={(item, action) => void handleFeedbackAction(item, action)}
          />

          {showMockFeedback && (
            <div className={`${GRID2_ROW.rowClass} mt-[16px]`}>
              <div className={GRID2_ROW.mainClass} />
              <div className={GRID2_ROW.sideClass}>
                <FeedbackCard
                  name={feedbackMock.textFeedback.name}
                  comment={feedbackMock.textFeedback.comment}
                  actionLabel={feedbackMock.textFeedback.actionLabel}
                  commentTone="text"
                />
              </div>
            </div>
          )}

          <div className="mt-[18px]">
            <ArticleReviewPanel
              status={article.status}
              headline={headline}
              content={content}
              hashtags={hashtags}
              platform={platform}
              publishMode={article.publishMode}
              saveMessage={saveMessage}
              saveNoticeKind={saveNoticeKind}
              saveNoticeFading={saveNoticeFading}
              publishNotice={publishNotice}
              publishNoticeKind={publishNoticeKind}
              publishNoticeFading={publishNoticeFading}
              onSubmit={(mode) => void handleReview(mode)}
              onReedit={() => void handleReedit()}
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
            />
          </div>

          {article.sourceUrl && (
            <SourceAccordion label="Artikelbron" url={article.sourceUrl} />
          )}
        </>
      }
      meta={
        <ArticleMetaSidebar
          detailsDefaultOpen
          rationale={article.rationale}
          tone={tone}
          editor={editor}
          imageRatio={imageRatio}
          platform={platform}
          toneSuggested={article.toneSuggested}
          editorSuggested={article.editorSuggested}
          imageRatioSuggested={article.imageRatioSuggested}
          platformSuggested={article.platformSuggested}
          onToneChange={setTone}
          onEditorChange={setEditor}
          onImageRatioChange={setImageRatio}
          onPlatformChange={setPlatform}
          objective={objective}
          objectiveSuggested={article.objectiveSuggested}
          objectiveOptions={article.objectiveOptions}
          onObjectiveChange={setObjective}
          onRewrite={() => void handleRewrite()}
          rewriting={rewriting}
          rewriteNotice={rewriteNotice}
          toneOptions={toneOptions}
          editorOptions={editorOptions}
        />
      }
    />
  )
}
