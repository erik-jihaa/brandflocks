import { GRID, GRID_META, LIST_SECTION } from '../layout/grid.constants'
import { PinkContentBox, ReviewPanel, themeSectionTitleClass } from './ReviewPanel'
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
  rationale?: string
  rationaleIndex?: number
  feedback: ResearchFeedback
  busy?: boolean
  verdictLabel?: string | null
  onFeedbackChange: (feedback: 'liked' | 'forgotten') => void
}

function sameTitle(a?: string | null, b?: string | null): boolean {
  const left = (a ?? '').replace(/\s+/g, ' ').trim().toLowerCase()
  const right = (b ?? '').replace(/\s+/g, ' ').trim().toLowerCase()
  return left !== '' && left === right
}

/** Research item — briefing/research.png: pink kader (titel + snippet), rationale eronder, Review rechts */
export function ResearchReviewRow({
  itemId,
  title,
  themeTitle,
  previousTitle,
  snippet,
  articleUrl,
  invalshoek = null,
  rationale,
  rationaleIndex,
  feedback,
  busy = false,
  verdictLabel = null,
  onFeedbackChange,
}: ResearchReviewRowProps) {
  const rationaleLabel =
    rationaleIndex != null ? `Onderzoeksreden ${rationaleIndex}` : 'Onderzoeksreden'
  const showTitle = title.trim() !== '' && !sameTitle(title, themeTitle) && !sameTitle(title, previousTitle)
  const invalshoekText = researchInvalshoekLabel(invalshoek)
  const showOwnAngle = !articleUrl?.trim()
  const primaryLabel = showOwnAngle ? 'Deze richting kiezen' : 'Deze bron kiezen'
  const secondaryLabel = showOwnAngle ? 'Deze richting vergeten' : 'Deze bron vergeten'

  return (
    <div className={`relative pt-[20px] ${LIST_SECTION.itemClass}`}>
      <div>
        <PinkContentBox>
          {invalshoekText ? (
            <p className="m-0 text-[12px] leading-[16px] text-ce-beige">{invalshoekText}</p>
          ) : null}
          {showTitle ? (
            <p
              className={`m-0 whitespace-normal break-words text-[21px] leading-[28px] font-semibold text-ce-blue${invalshoekText ? ' mt-[8px]' : ''}`}
            >
              {title}
            </p>
          ) : null}
          {snippet ? (
            <p className={`${showTitle ? 'mt-[12px] ' : ''}mb-0 text-[14px] leading-[18px] text-ce-black`}>
              {snippet}
            </p>
          ) : null}
          {articleUrl ? (
            <a
              href={articleUrl}
              target="_blank"
              rel="noopener noreferrer"
              className={`mt-[12px] inline-block ${sourceArticleUrlLinkClass}`}
            >
              {researchSourceLinkLabel(title, articleUrl)} →
            </a>
          ) : showOwnAngle ? (
            <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-beige">
              Eigen aanzet — geen bron, wel een richting.
            </p>
          ) : null}
        </PinkContentBox>
        {rationale ? (
          <div className="px-[30px]">
            <p className={`${themeSectionTitleClass} mt-[16px] mb-[6px]`}>{rationaleLabel}</p>
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">{rationale}</p>
          </div>
        ) : null}
      </div>

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
