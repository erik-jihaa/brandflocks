import { useEffect, useMemo, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { fetchArticle } from '../api/articles'
import type { HbaScoreDto } from '../types/api'
import { GridShell } from '../layout/GridShell'
import { statusRowActionClass } from '../components/ui/ActionLink'
import { ArticleFormatBox, ARTICLE_BODY_CLASS } from '../components/ArticleFormatBox'
import { ArticleMetaSidebar } from '../components/ArticleMetaSidebar'
import { IMAGE_SPECS, resolveImageRatio } from '../components/ImageUpload'
import { combineBodyAndHashtags } from '../utils/articleContent'
import { stripEditorQuestionsBlock } from '../utils/editorQuestions'
import { fetchArchiveDetail, type ArchiveDetailDto } from '../api/archive'
import { isApiConfigured } from '../api/client'
import { articleMock } from '../data/article.mock'
import { archiveMock } from '../data/archive.mock'
import { reportTitleForKlant } from '../utils/reportTitleKlant'
import { formatDayMonthYearLong } from '../utils/localeDates'

function formatPublishedDate(value: string | null | undefined): string {
  if (!value) return 'Gepubliceerd —'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Gepubliceerd —'
  return `Gepubliceerd ${formatDayMonthYearLong(date)}`
}

function formatPromoBudget(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return '—'
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value)
}

function mockDetail(slotId: string): ArchiveDetailDto {
  const item = archiveMock.find((row) => row.slotId === slotId) ?? archiveMock[0]
  return {
    ...item,
    id: item.articleId ?? 'mock',
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
    reports: [],
  }
}

/** briefing/archive single articleType+.png — read-only gearchiveerd artikel */
export function ArchiveArticlePage() {
  const { slotId } = useParams<{ slotId: string }>()
  const [searchParams] = useSearchParams()
  const hbaKlantPreview = searchParams.get('hbaKlant') === '1'
  const [detail, setDetail] = useState<ArchiveDetailDto | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)
  const [hbaScore, setHbaScore] = useState<HbaScoreDto | null>(null)
  const [hbaScoreDisplayMode, setHbaScoreDisplayMode] = useState<'decimal' | 'band'>('band')
  const [hideHbaContentrol, setHideHbaContentrol] = useState(false)

  useEffect(() => {
    if (!slotId) return

    if (!isApiConfigured()) {
      setDetail(mockDetail(slotId))
      setUsingMock(true)
      setLoading(false)
      return
    }

    void fetchArchiveDetail(slotId)
      .then((data) => {
        setDetail(data)
        setUsingMock(false)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Failed to load')
        setDetail(mockDetail(slotId))
        setUsingMock(true)
      })
      .finally(() => setLoading(false))
  }, [slotId])

  useEffect(() => {
    if (!slotId || !hbaKlantPreview || !isApiConfigured()) {
      setHbaScore(null)
      return
    }

    void fetchArticle(slotId)
      .then((article) => {
        setHbaScore(article.hbaScore ?? null)
        setHbaScoreDisplayMode(article.hbaScoreDisplayMode ?? 'band')
        setHideHbaContentrol(article.hideHbaContentrol ?? false)
      })
      .catch(() => {
        setHbaScore(null)
      })
  }, [slotId, hbaKlantPreview])

  const previewUrl = useMemo(() => {
    if (!detail) return null
    const variant = resolveImageRatio(detail.imageRatio)
    return variant === 'square' ? detail.imageUrlSquare : detail.imageUrlLandscape
  }, [detail])

  const spec = detail ? IMAGE_SPECS[resolveImageRatio(detail.imageRatio)] : IMAGE_SPECS.square

  if (loading) {
    return <GridShell pageTitle="Archive" activeNav="archive" mainLayout="grid2" main={<p>Laden…</p>} />
  }

  if (!detail) {
    return (
      <GridShell
        pageTitle="Archive"
        activeNav="archive"
        mainLayout="grid2"
        main={<p className="text-[14px] leading-[18px] text-ce-red">{error ?? 'Article not found.'}</p>}
      />
    )
  }

  const body = combineBodyAndHashtags(stripEditorQuestionsBlock(detail.content), detail.hashtags)

  return (
    <GridShell
      pageTitle="Archive"
      pageSubtitle={formatPublishedDate(detail.publishedAt)}
      activeNav="archive"
      mainLayout="grid2"
      main={
        <>
          {usingMock && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-beige">
              Mock archive article ({slotId}).
            </p>
          )}
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
            <h2 className="m-0 mb-[10px] text-[14px] leading-[18px] font-semibold text-ce-black">
              Interaction score
            </h2>
            <ul className="m-0 list-none space-y-[6px] p-0 text-[14px] leading-[18px] text-ce-black">
              <li>
                URL article{' '}
                {detail.publishLinks && detail.publishLinks.length > 0 ? (
                  <>
                    [
                    {detail.publishLinks.map((link, index) => (
                      <span key={link.kind}>
                        {index > 0 && ' '}
                        <a
                          href={link.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-ce-blue underline decoration-[1.5px]"
                        >
                          {link.label} →
                        </a>
                      </span>
                    ))}
                    ]
                  </>
                ) : (
                  '[—]'
                )}
              </li>
              <li>Promo budget [{formatPromoBudget(detail.promoBudget)}]</li>
              <li>View [{detail.interactionViews ?? '—'}]</li>
              <li>Liked [{detail.interactionLikes ?? '—'}]</li>
              <li>Comments [{detail.interactionComments ?? '—'}]</li>
            </ul>
            {detail.archiveNote && (
              <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-black">
                Notes — {detail.archiveNote}
              </p>
            )}
          </div>

          {detail.reports.length > 0 && (
            <div className="mt-[18px] border-t border-ce-beige pt-[18px]">
              <h2 className="m-0 mb-[12px] text-[14px] leading-[18px] font-semibold text-ce-black">Reports</h2>
              <div className="flex flex-col gap-[12px]">
                {detail.reports.map((report) => (
                  <div key={report.reportId} className="flex items-baseline justify-between gap-[12px]">
                    <span className="text-[14px] leading-[18px] font-semibold text-ce-black">
                      {reportTitleForKlant(report.title)}
                    </span>
                    <Link to="/report" className={`shrink-0 ${statusRowActionClass}`}>
                      View Report
                    </Link>
                  </div>
                ))}
              </div>
            </div>
          )}
        </>
      }
      meta={
        <ArticleMetaSidebar
          readonly
          hideSelfReview
          detailsDefaultOpen
          rationale={detail.rationale}
          tone={detail.tone}
          imageRatio={detail.imageRatio}
          platform={detail.platform}
          onToneChange={() => undefined}
          onImageRatioChange={() => undefined}
          onPlatformChange={() => undefined}
          hbaScore={hbaKlantPreview ? hbaScore : null}
          hbaScoreDisplayMode={hbaScoreDisplayMode}
          hideHbaContentrol={hideHbaContentrol}
          onContentrolOverrideChange={hbaKlantPreview ? () => {} : undefined}
          onHbaRescore={hbaKlantPreview ? async () => {} : undefined}
        />
      }
    />
  )
}
