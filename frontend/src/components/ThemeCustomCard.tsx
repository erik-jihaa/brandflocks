import { PencilIcon } from './PencilIcon'
import { ARTICLE_TITLE_INPUT_CLASS, THEME_LABEL_CLASS } from './ArticleFormatBox'

const fieldReset =
  'w-full border-0 bg-transparent p-0 outline-none focus:ring-0 resize-none'

const themeHintFieldClass =
  'text-[14px] leading-[18px] text-ce-black placeholder:text-ce-beige'

type ThemeCustomCardProps = {
  title: string
  motivation: string
  sourceArticleUrl: string
  onTitleChange: (value: string) => void
  onMotivationChange: (value: string) => void
  onSourceUrlChange: (value: string) => void
  disabled?: boolean
}

/** Eigen theme — zelfde volgorde als voorstellen; vaste velden zonder auto-resize */
export function ThemeCustomCard({
  title,
  motivation,
  sourceArticleUrl,
  onTitleChange,
  onMotivationChange,
  onSourceUrlChange,
  disabled = false,
}: ThemeCustomCardProps) {
  return (
    <div className="border-b border-ce-beige pb-[18px]">
      <p className={THEME_LABEL_CLASS}>Eigen thema</p>

      <div className="mb-[12px]">
        <span className="mb-[6px] flex max-w-full items-start gap-[2px]">
          <textarea
            id="custom-theme-title"
            value={title}
            disabled={disabled}
            onChange={(e) => onTitleChange(e.target.value)}
            placeholder="Titel"
            rows={2}
            aria-label="Titel"
            className={`${fieldReset} ${ARTICLE_TITLE_INPUT_CLASS} placeholder:text-ce-blue whitespace-normal break-words`}
          />
          <PencilIcon className="mt-[8px]" />
        </span>
        <textarea
          id="custom-theme-motivation"
          value={motivation}
          disabled={disabled}
          onChange={(e) => onMotivationChange(e.target.value)}
          placeholder="Samenvatting van het bronartikel"
          rows={8}
          aria-label="Samenvatting van het bronartikel"
          className={`${fieldReset} ${themeHintFieldClass} whitespace-pre-wrap`}
        />
      </div>

      <input
        id="custom-theme-url"
        type="url"
        value={sourceArticleUrl}
        disabled={disabled}
        onChange={(e) => onSourceUrlChange(e.target.value)}
        placeholder="Link naar een bron (optioneel) →"
        aria-label="Article URL"
        className={`${fieldReset} ${themeHintFieldClass}`}
      />
    </div>
  )
}
