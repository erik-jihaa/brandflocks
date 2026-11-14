import { GRID, GRID_META, LIST_SECTION } from '../layout/grid.constants'
import { ReviewPanel } from './ReviewPanel'
import { sourceArticleUrlLinkClass } from '../design/identity.constants'
import { verdictPillClass } from './ArticleHbaHintSection'
import { themeVerdictKlantLabel } from '../utils/themeVerdict'
import { researchInvalshoekLabel } from '../utils/invalshoekLabels'
import { researchSourceLinkLabel } from '../utils/researchSourceLabel'

export type ResearchFeedback = 'liked' | 'forgotten' | 'none'

type ResearchReviewRowProps = {
  itemId: string
  title: string
  themeTitle?: string
  previousTitle?: string | null
  snippet: string
  articleUrl?: string
  invalshoek?: string | null
  waarom?: string
  getoetstAan?: string
  rationale?: string
  rationaleIndex?: number
  feedback: ResearchFeedback
  busy?: boolean
  verdictLabel?: string | null
  onFeedbackChange: (feedback: 'liked' | 'forgotten') => void
}

function FieldRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex gap-[12px] border-b border-ce-beige py-[10px] last:border-b-0">
      <div className="w-[140px] shrink-0 text-[14px] font-semibold leading-[18px] text-ce-black">{label}</div>
      <div className="min-w-0 flex-1 text-[14px] leading-[18px] text-ce-black">{children}</div>
    </div>
  )
}

/** Onderzoek-kaart — klikmodel p-onderzoek: Vraag / Bron / Fragment / Waarom + kiezen/vergeten. */
export function ResearchReviewRow({
  itemId,
  title,
  snippet,
  articleUrl,
  invalshoek = null,
  waarom,
  getoetstAan,
  rationale,
  feedback,
  busy = false,
  verdictLabel = null,
  onFeedbackChange,
}: ResearchReviewRowProps) {
  const invalshoekText = researchInvalshoekLabel(invalshoek)
  const showOwnAngle = !articleUrl?.trim()
  const primaryLabel = showOwnAngle ? 'Deze richting kiezen' : 'Deze bron kiezen'
  const secondaryLabel = showOwnAngle ? 'Deze richting vergeten' : 'Deze bron vergeten'
  const vraag = title.trim() || invalshoekText || '—'
  const fragment = snippet.trim()
  const waaromTekst = (waarom ?? rationale ?? '').trim()
  const getoetst = (getoetstAan ?? '').trim()

  return (
    <div className={`relative pt-[20px] ${LIST_SECTION.itemClass}`}>
      <article className="rounded-[12px] border border-ce-beige px-[20px] py-[4px]">
        <h3 className="m-0 pt-[10px] pb-[4px] text-[14px] font-semibold leading-[18px] text-ce-black">
          Bronfragment
        </h3>
        <FieldRow label="Vraag">{vraag}</FieldRow>
        <FieldRow label="Bron">
          {articleUrl ? (
            <a
              href={articleUrl}
              target="_blank"
              rel="noopener noreferrer"
              className={sourceArticleUrlLinkClass}
            >
              {researchSourceLinkLabel(title, articleUrl)} →
            </a>
          ) : showOwnAngle ? (
            <span className="text-ce-beige">Eigen aanzet — geen bron, wel een richting.</span>
          ) : (
            '—'
          )}
        </FieldRow>
        {fragment ? (
          <FieldRow label="Fragment">
            <span className="italic">&ldquo;{fragment}&rdquo;</span>
          </FieldRow>
        ) : null}
        {getoetst ? <FieldRow label="Getoetst aan">{getoetst}</FieldRow> : null}
        {waaromTekst ? <FieldRow label="Waarom dit meespeelt">{waaromTekst}</FieldRow> : null}
      </article>

      <div
        className={`absolute top-[20px] ${GRID_META.widthClass} flex shrink-0 flex-col gap-[8px]`}
        style={{ left: `calc(100% + ${GRID.colGapMeta}px)` }}
      >
        {themeVerdictKlantLabel(verdictLabel) ? (
          <span
            className={`inline-flex w-fit rounded-full px-[10px] py-[4px] text-[12px] font-semibold ${verdictPillClass(themeVerdictKlantLabel(verdictLabel) ?? '')}`}
          >
            {themeVerdictKlantLabel(verdictLabel)}
          </span>
        ) : null}
        <ReviewPanel
          name={`research-review-${itemId}`}
          primaryLabel={primaryLabel}
          secondaryLabel={secondaryLabel}
          primaryChecked={feedback === 'liked'}
          secondaryChecked={feedback === 'forgotten'}
          busy={busy}
          onPrimary={() => onFeedbackChange('liked')}
          onSecondary={() => onFeedbackChange('forgotten')}
        />
      </div>
    </div>
  )
}

export function normalizeResearchFeedback(feedback: string): ResearchFeedback {
  if (feedback === 'liked' || feedback === 'like') return 'liked'
  if (feedback === 'forgotten' || feedback === 'forget' || feedback === 'deleted') return 'forgotten'
  return 'none'
}
