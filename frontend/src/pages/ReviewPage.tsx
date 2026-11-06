import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { fetchReview, submitReviewDecision } from '../api/review'
import { ApiError } from '../api/client'
import { ArticleAutoResizeTextarea, ArticleHeadlineField } from '../components/ArticleFormatBox'
import { Logo } from '../components/Logo'
import { LoadingDots } from '../components/LoadingDots'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { ARTICLE_TEXT_MAX_WIDTH_CLASS } from '../layout/grid.constants'
import { resolveMediaUrl } from '../utils/mediaUrl'
import type { ReviewDto } from '../types/review'

/** Publieke reviewpagina — CE-04.1bis (token, geen login). */
export function ReviewPage() {
  const { token } = useParams<{ token?: string }>()
  const [review, setReview] = useState<ReviewDto | null>(null)
  const [headline, setHeadline] = useState('')
  const [content, setContent] = useState('')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState<'approved' | 'feedback' | null>(null)

  useEffect(() => {
    if (!token) {
      setError('Geen review-link.')
      setLoading(false)
      return
    }

    setLoading(true)
    fetchReview(token)
      .then((data) => {
        setReview(data)
        setHeadline(data.headline)
        setContent(data.content)
        if (!data.canDecide && data.decision) {
          setDone(data.decision)
        }
      })
      .catch((err: unknown) => {
        setError(err instanceof ApiError ? err.message : 'Review laden mislukt.')
      })
      .finally(() => setLoading(false))
  }, [token])

  async function handleDecision(decision: 'approved' | 'feedback') {
    if (!token || !review?.canDecide) return

    setSubmitting(true)
    setError(null)
    try {
      const result = await submitReviewDecision(token, { decision, headline, content })
      setDone(decision)
      setReview(result)
    } catch (err: unknown) {
      setError(err instanceof ApiError ? err.message : 'Versturen mislukt.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="min-h-screen bg-ce-offwhite px-[24px] pb-[60px] pt-[60px]">
      <div className="mx-auto max-w-[740px]">
        <div className="mb-[40px] flex justify-start">
          <Logo variant="platform" />
        </div>

        <h1 className="m-0 mb-[8px] text-[32px] leading-[40px] font-semibold text-ce-black">
          Review
        </h1>
        {review?.weekLabel && (
          <p className="m-0 mb-[24px] text-[14px] leading-[18px] text-ce-beige">{review.weekLabel}</p>
        )}

        {loading && <LoadingDots label="Review laden" className="text-ce-black" />}

        {error && (
          <p className="text-[14px] leading-[18px] text-ce-red" role="alert">
            {error}
          </p>
        )}

        {!loading && review && (
          <>
            {review.rationale && (
              <section className="mb-[20px]">
                <h2 className="m-0 mb-[6px] text-[14px] leading-[18px] font-semibold">Rationale</h2>
                <p className="m-0 whitespace-pre-wrap text-[14px] leading-[18px]">{review.rationale}</p>
              </section>
            )}

            <p className="m-0 mb-[16px] text-[14px] leading-[18px] text-ce-beige">
              {review.tone} · {review.angle} · {review.platform}
            </p>

            {resolveMediaUrl(review.imageUrl) && (
              <div className="mb-[16px]">
                <img
                  src={resolveMediaUrl(review.imageUrl) ?? undefined}
                  alt=""
                  className="max-h-[270px] max-w-full border-[1.5px] border-ce-beige object-contain"
                />
              </div>
            )}

            <div className={ARTICLE_TEXT_MAX_WIDTH_CLASS}>
              <div className="mb-[12px]">
                <ArticleHeadlineField
                  id="review-headline"
                  value={headline}
                  onChange={setHeadline}
                  disabled={!review.canDecide || submitting || done !== null}
                />
              </div>
              <ArticleAutoResizeTextarea
                id="review-content"
                value={content}
                onChange={setContent}
                disabled={!review.canDecide || submitting || done !== null}
                aria-label="Artikeltekst"
                className="text-[14px] leading-[18px] text-ce-black"
              />
            </div>

            {done && (
              <p className="mt-[20px] text-[14px] leading-[18px] text-ce-black">
                {done === 'approved'
                  ? 'Bedankt — artikel goedgekeurd. De redacteur kan het nu plaatsen.'
                  : 'Bedankt — feedback verstuurd naar de redacteur.'}
              </p>
            )}

            {review.canDecide && !done && (
              <div className={pillActionsRowClass}>
                <PillButton
                  variant="main"
                  disabled={submitting}
                  loading={submitting}
                  onClick={() => void handleDecision('approved')}
                >
                  Approved
                </PillButton>
                <PillButton
                  variant="second"
                  disabled={submitting}
                  loading={submitting}
                  onClick={() => void handleDecision('feedback')}
                >
                  Feedback
                </PillButton>
              </div>
            )}

            {!review.canDecide && !done && (
              <p className="mt-[20px] text-[14px] leading-[18px] text-ce-black">
                Deze review is al afgehandeld.
              </p>
            )}
          </>
        )}
      </div>
    </div>
  )
}
