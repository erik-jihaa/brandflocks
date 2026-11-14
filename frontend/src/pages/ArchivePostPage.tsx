import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { ArticleFormatBox, ARTICLE_BODY_CLASS } from '../components/ArticleFormatBox'
import { ArticleMetaSidebar } from '../components/ArticleMetaSidebar'
import { PillButton } from '../components/PillButton'
import { FieldSingle } from '../components/ui/Field'
import { statusRowActionClass } from '../components/ui/ActionLink'
import { PINK_BOX_CLASS } from '../layout/grid.constants'
import { IMAGE_SPECS, resolveImageRatio } from '../components/ImageUpload'
import { combineBodyAndHashtags } from '../utils/articleContent'
import { stripEditorQuestionsBlock } from '../utils/editorQuestions'
import { checkArchivePostUrl, fetchArchiveDetail, type ArchiveDetailDto, type ArchiveDraftPayload } from '../api/archive'
import { isApiConfigured } from '../api/client'
import { articleMock } from '../data/article.mock'
import { archiveMock } from '../data/archive.mock'
import { formatDayMonthYearLong } from '../utils/localeDates'

type UrlCheckStatus = 'none' | 'checking' | 'green' | 'blocked' | 'red' | 'invalid'

function urlCheckFromApi(status: string): UrlCheckStatus {
  if (status === 'green') return 'green'
  if (status === 'blocked') return 'blocked'
  if (status === 'invalid') return 'invalid'
  return 'red'
}

function initialPostUrl(data: ArchiveDetailDto): string {
  const blog = data.publishLinks?.find((link) => link.kind === 'blog')?.url
  const first = data.publishLinks?.[0]?.url
  return blog ?? first ?? data.socialPostUrl ?? ''
}

function urlCheckLabel(status: UrlCheckStatus): string | null {
  if (status === 'blocked') return 'automated check blocked'
  if (status === 'checking') return 'checking…'
  return null
}

function formatPublishedDate(value: string | null | undefined): string {
  if (!value) return 'Gepubliceerd —'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Gepubliceerd —'
  return `Gepubliceerd ${formatDayMonthYearLong(date)}`
}

function toDateInput(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function dateInputToIso(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed) return null
  const date = new Date(`${trimmed}T12:00:00`)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString()
}

function mockDetail(slotId: string): ArchiveDetailDto {
  const item = archiveMock[0]
  return {
    ...item,
    slotId,
    id: 'mock',
    headline: item.title,
    content: articleMock.content,
    hashtags: articleMock.hashtags,
    rationale: articleMock.rationale,
    sourceUrl: articleMock.sourceUrl,
    tone: articleMock.tone.selected,
    angle: articleMock.angle.selected,
    imageRatio: articleMock.imageRatio.selected,
    platform: articleMock.platform.selected,
    imageUrlSquare: articleMock.imageUrlSquare,
    imageUrlLandscape: articleMock.imageUrlLandscape,
    isArchived: false,
    reports: [],
  }
}

/** briefing/archive single postType+.png — archiveren vanuit Agenda */
export function ArchivePostPage() {
  const navigate = useNavigate()
  const { slotId } = useParams<{ slotId: string }>()
  const [detail, setDetail] = useState<ArchiveDetailDto | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)

  const [publishedAt, setPublishedAt] = useState('')
  const [promoBudget, setPromoBudget] = useState('')
  const [postUrl, setPostUrl] = useState('')
  const [notes, setNotes] = useState('')
  const [urlCheck, setUrlCheck] = useState<UrlCheckStatus>('none')
  const pendingAutoCheck = useRef<string | null>(null)

  useEffect(() => {
    if (!slotId) return

    if (!isApiConfigured()) {
      const mock = mockDetail(slotId)
      setDetail(mock)
      setPublishedAt(toDateInput(mock.publishedAt))
      setUsingMock(true)
      setLoading(false)
      return
    }

    void fetchArchiveDetail(slotId)
      .then((data) => {
        if (data.isArchived) {
          navigate(`/archive/${slotId}`, { replace: true })
          return
        }
        if (data.hasArticle === false) {
          navigate(`/archive/${slotId}/confirm`, {
            replace: true,
            state: {
              slotId,
              title: data.headline,
              publishedAt: null,
              promoBudget: null,
              socialPostUrl: null,
              archiveNote: null,
            },
          })
          return
        }
        setDetail(data)
        setPublishedAt(toDateInput(data.publishedAt))
        setPromoBudget(data.promoBudget != null ? String(data.promoBudget) : '')
        const url = initialPostUrl(data)
        setPostUrl(url)
        setNotes(data.archiveNote ?? '')
        if (url.trim()) {
          pendingAutoCheck.current = url.trim()
        }
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Failed to load')
        const mock = mockDetail(slotId)
        setDetail(mock)
        setPublishedAt(toDateInput(mock.publishedAt))
        setUsingMock(true)
      })
      .finally(() => setLoading(false))
  }, [slotId, navigate])

  useEffect(() => {
    if (loading || usingMock || !pendingAutoCheck.current) return

    const url = pendingAutoCheck.current
    pendingAutoCheck.current = null
    setUrlCheck('checking')

    void checkArchivePostUrl(url)
      .then((result) => setUrlCheck(urlCheckFromApi(result.status)))
      .catch(() => setUrlCheck('red'))
  }, [loading, usingMock])

  const previewUrl = useMemo(() => {
    if (!detail) return null
    const variant = resolveImageRatio(detail.imageRatio)
    return variant === 'square' ? detail.imageUrlSquare : detail.imageUrlLandscape
  }, [detail])

  const spec = detail ? IMAGE_SPECS[resolveImageRatio(detail.imageRatio)] : IMAGE_SPECS.square
  const urlCheckMessage = urlCheckLabel(urlCheck)

  async function handleCheckUrl() {
    if (!postUrl.trim()) {
      setUrlCheck('invalid')
      return
    }
    if (usingMock) {
      setUrlCheck(postUrl.startsWith('http') ? 'green' : 'invalid')
      return
    }
    setUrlCheck('checking')
    try {
      const result = await checkArchivePostUrl(postUrl.trim())
      setUrlCheck(urlCheckFromApi(result.status))
    } catch {
      setUrlCheck('red')
    }
  }

  function handleArchiveClick() {
    if (!slotId || !detail) return
    const payload: ArchiveDraftPayload = {
      publishedAt: dateInputToIso(publishedAt),
      promoBudget: promoBudget.trim() ? Number.parseFloat(promoBudget) : null,
      socialPostUrl: postUrl.trim() || null,
      archiveNote: notes.trim() || null,
    }
    navigate(`/archive/${slotId}/confirm`, {
      state: { ...payload, slotId, title: detail.headline },
    })
  }

  if (loading) {
    return (
      <GridShell
        pageTitle="Deze post archiveren"
        activeNav="agenda"
        mainLayout="grid2"
        main={<p>Laden…</p>}
      />
    )
  }

  if (!detail) {
    return (
      <GridShell
        pageTitle="Deze post archiveren"
        activeNav="agenda"
        mainLayout="grid2"
        main={<p className="text-[14px] leading-[18px] text-ce-red">{error ?? 'Artikel niet gevonden.'}</p>}
      />
    )
  }

  const body = combineBodyAndHashtags(stripEditorQuestionsBlock(detail.content), detail.hashtags)

  return (
    <GridShell
      pageTitle="Deze post archiveren"
      pageSubtitle={formatPublishedDate(detail.publishedAt)}
      activeNav="agenda"
      mainLayout="grid2"
      main={
        <>
          {error && <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>}

          {previewUrl && (
            <div
              className="mb-[16px] overflow-hidden border-[1.5px] border-ce-beige bg-ce-offwhite"
              style={{ width: spec.width, height: spec.height }}
            >
              <img src={previewUrl} alt="" className="h-full w-full object-cover" />
            </div>
          )}

          <ArticleFormatBox title={detail.headline}>
            <p className={`${ARTICLE_BODY_CLASS} whitespace-pre-wrap`}>{body}</p>
          </ArticleFormatBox>

          <div className="mt-[18px] border-t border-ce-beige pt-[18px]">
            <div className="grid grid-cols-2 gap-[12px]">
              <FieldSingle
                id="archive-published-at"
                type="date"
                value={publishedAt}
                onChange={setPublishedAt}
                placeholder="Publicatiedatum"
                placeholderTone="black"
                aria-label="Publicatiedatum"
              />
              <FieldSingle
                id="archive-promo-budget"
                value={promoBudget}
                onChange={setPromoBudget}
                placeholder="Promobudget €"
                placeholderTone="black"
                aria-label="Promobudget"
              />
            </div>

            <div className="mt-[12px]">
              <FieldSingle
                id="archive-post-url"
                value={postUrl}
                onChange={(value) => {
                  setPostUrl(value)
                  setUrlCheck('none')
                }}
                placeholder="URL van het artikel"
                placeholderTone="black"
                aria-label="URL van het artikel"
              />
              <div className="mt-[8px] flex items-center gap-[8px] text-[14px] leading-[18px] text-ce-black">
                <button
                  type="button"
                  onClick={() => void handleCheckUrl()}
                  className={statusRowActionClass}
                >
                  Controleren
                </button>
                <span
                  className={`inline-block h-[8px] w-[8px] rounded-full ${
                    urlCheck === 'green' || urlCheck === 'blocked'
                      ? 'bg-ce-green-fill'
                      : urlCheck === 'red' || urlCheck === 'invalid'
                        ? 'bg-ce-red'
                        : urlCheck === 'checking'
                          ? 'animate-pulse bg-ce-beige'
                          : 'bg-ce-beige'
                  }`}
                  aria-hidden
                />
                {urlCheckMessage && <span className="text-ce-beige">{urlCheckMessage}</span>}
              </div>
            </div>

            <div className={`${PINK_BOX_CLASS} mt-[16px]`}>
              <textarea
                id="archive-notes"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Notities — neveneffecten van dit artikel"
                rows={3}
                aria-label="Notities"
                className="w-full resize-y border-0 bg-transparent p-0 text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-black"
              />
            </div>

            <div className="mt-[18px]">
              <PillButton variant="main" onClick={handleArchiveClick}>
                Archiveren
              </PillButton>
            </div>
          </div>
        </>
      }
      meta={
        <div className="pointer-events-none opacity-90">
          <ArticleMetaSidebar
            detailsDefaultOpen
            rationale={detail.rationale}
            tone={detail.tone}
            imageRatio={detail.imageRatio}
            platform={detail.platform}
            onToneChange={() => undefined}
            onImageRatioChange={() => undefined}
            onPlatformChange={() => undefined}
          />
        </div>
      }
    />
  )
}
