import { ARTICLE_TEXT_MAX_WIDTH_CLASS } from '../layout/grid.constants'
import { PencilIcon } from './PencilIcon'
import {
  ARTICLE_TITLE_BLOCK_CLASS,
  ArticleHeadlineField,
} from './ArticleFormatBox'
import { ArticleBodyTextarea } from './ArticleEditorBodyField'
import { renderFeedbackInlineContent } from '../utils/contentDiff'

type ArticleCardProps = {
  headline: string
  onHeadlineChange: (value: string) => void
  content: string
  onContentChange?: (value: string) => void
  contentSlot?: React.ReactNode
  hashtags: string
  onHashtagsChange: (value: string) => void
  headlineClassName?: string
  /** Feedback-pagina: titel apart, body op volle kolombreedte */
  wideBody?: boolean
  /** Baseline voor rode inline correcties (contentAi) */
  contentBaseline?: string
  /** HBA: markeer te vervangen passage in de editor (exacte match) */
  highlightPassage?: string | null
}

/** Titel, body en hashtags — plain layout, volledige tekst zichtbaar. */
export function ArticleCard({
  headline,
  onHeadlineChange,
  content,
  onContentChange,
  contentSlot,
  hashtags,
  onHashtagsChange,
  headlineClassName,
  wideBody = false,
  contentBaseline = '',
  highlightPassage = null,
}: ArticleCardProps) {
  const inlineBody = renderFeedbackInlineContent(contentBaseline || content, content)

  const bodyField =
    contentSlot ??
    (wideBody && contentBaseline && contentBaseline !== content ? (
      <div className="whitespace-pre-wrap text-[14px] leading-[18px] text-ce-black outline-none">
        {inlineBody}
        {hashtags && (
          <>
            {'\n\n'}
            <span>{hashtags}</span>
          </>
        )}
      </div>
    ) : (
      <ArticleBodyTextarea
        content={content}
        hashtags={hashtags}
        highlightPassage={highlightPassage}
        contentBaseline={contentBaseline}
        onChange={(body, tags) => {
          onContentChange?.(body)
          onHashtagsChange(tags)
        }}
      />
    ))

  const titleBlock = (
    <div className={ARTICLE_TITLE_BLOCK_CLASS}>
      <span className="flex max-w-full items-start gap-[6px]">
        <ArticleHeadlineField
          value={headline}
          onChange={onHeadlineChange}
          className={headlineClassName}
        />
        <PencilIcon />
      </span>
    </div>
  )

  if (wideBody) {
    return (
      <div className={ARTICLE_TEXT_MAX_WIDTH_CLASS}>
        {titleBlock}
        <div className="mt-[12px]">
          {bodyField}
        </div>
      </div>
    )
  }

  return (
    <div className={ARTICLE_TEXT_MAX_WIDTH_CLASS}>
      {titleBlock}
      {bodyField}
    </div>
  )
}
