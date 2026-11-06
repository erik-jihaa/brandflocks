import { GRID2_ROW, LIST_SECTION } from '../layout/grid.constants'
import { ARTICLE_TITLE_CLASS, THEME_BODY_CLASS, THEME_LABEL_CLASS } from './ArticleFormatBox'
import { ReviewPanel } from './ReviewPanel'
import { Pulldown } from './ui/Pulldown'
import { verdictPillClass } from './ArticleHbaHintSection'
import { themeVerdictKlantLabel } from '../utils/themeVerdict'
import { sourceArticleUrlLinkClass } from '../design/identity.constants'
import { normalizeThemeFeedback, type ThemeReviewFeedback } from './ThemeReviewSidebar'

type ThemeReviewRowProps = {
  themeId: string
  index: number
  title: string
  motivation: string
  rationale: string
  sourceArticleUrl: string
  editorSuggested?: string | null
  editorOptions?: readonly string[]
  feedback: ThemeReviewFeedback
  busy?: boolean
  verdictLabel?: string | null
  verdictToelichting?: string | null
  rhythmRoleLabel?: string | null
  onFeedbackChange: (feedback: 'liked' | 'deleted') => void
  onEditorChange?: (editor: string) => void
}

/** Theme — label, titel, copy, rationale als plain text (geen kaders) */
export function ThemeReviewRow({
  themeId,
  index,
  title,
  motivation,
  rationale,
  sourceArticleUrl,
  editorSuggested = null,
  editorOptions = [],
  feedback,
  busy = false,
  verdictLabel = null,
  verdictToelichting = null,
  rhythmRoleLabel = null,
  onFeedbackChange,
  onEditorChange,
}: ThemeReviewRowProps) {
  const label = String(index)
  const displayTitle = title.trim() || `Thema ${label}`
  const showAuthor = editorOptions.length > 0
  const authorOptions = editorOptions
  const selectedAuthor = editorSuggested && authorOptions.includes(editorSuggested)
    ? editorSuggested
    : authorOptions[0] ?? ''

  const klantLabel = themeVerdictKlantLabel(verdictLabel)

  return (
    <div className={`pt-[20px] ${LIST_SECTION.itemClass}`}>
      <div className={GRID2_ROW.rowClass}>
        <div className={GRID2_ROW.mainClass}>
          <div className="mb-[12px]">
            <p className={THEME_LABEL_CLASS}>
              {rhythmRoleLabel ? `${rhythmRoleLabel} — themaverhaal ${label}` : `Themaverhaal ${label}`}
            </p>
            {title.trim() !== '' && <p className={`${ARTICLE_TITLE_CLASS} mb-[6px]`}>{displayTitle}</p>}
            {sourceArticleUrl.trim() !== '' && (
              <a
                href={sourceArticleUrl}
                target="_blank"
                rel="noopener noreferrer"
                className={`${sourceArticleUrlLinkClass} mt-[6px] inline-block break-all`}
              >
                {sourceArticleUrl} →
              </a>
            )}
            {motivation.trim() !== '' && (
              <p className={`${THEME_BODY_CLASS} mt-[8px] whitespace-pre-wrap`}>{motivation}</p>
            )}
            {verdictToelichting ? (
              <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-black">{verdictToelichting}</p>
            ) : null}
          </div>

          {showAuthor && onEditorChange && (
            <div className="mb-[12px]">
              <p className={THEME_LABEL_CLASS}>Auteur</p>
              <Pulldown
                options={[...authorOptions]}
                value={selectedAuthor}
                onChange={onEditorChange}
              />
            </div>
          )}

          <div className="mb-[12px]">
            <p className={THEME_LABEL_CLASS}>Waarom dit thema {label}</p>
            {rationale.trim() !== '' && (
              <p className={`${THEME_BODY_CLASS} whitespace-pre-wrap`}>{rationale}</p>
            )}
          </div>
        </div>

        <div className={`${GRID2_ROW.sideClass} flex flex-col gap-[8px]`}>
          {klantLabel ? (
            <span
              className={`inline-flex w-fit rounded-full px-[10px] py-[4px] text-[12px] font-semibold ${verdictPillClass(klantLabel)}`}
            >
              {klantLabel}
            </span>
          ) : null}
          <ReviewPanel
            name={`theme-review-${themeId}`}
            primaryLabel="Dit thema kiezen"
            secondaryLabel="Dit thema verwijderen"
            primaryChecked={feedback === 'liked'}
            secondaryChecked={feedback === 'deleted'}
            busy={busy}
            onPrimary={() => onFeedbackChange('liked')}
            onSecondary={() => onFeedbackChange('deleted')}
          />
        </div>
      </div>
    </div>
  )
}

export { normalizeThemeFeedback }
export type { ThemeReviewFeedback }
