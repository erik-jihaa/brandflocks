import { ArticleAutoResizeTextarea } from './ArticleFormatBox'
import { BODY_HASHTAG_SEPARATOR, combineBodyAndHashtags, splitBodyAndHashtags } from '../utils/articleContent'
import { renderUserInputDiff } from '../utils/contentDiff'
import { textHasOpenPlaceholders } from '../utils/hbaHintApply'

const bodyTypography = 'whitespace-pre-wrap text-[14px] leading-[18px] font-normal'
const overlayTextareaClass = `${bodyTypography} relative bg-transparent text-transparent caret-ce-black selection:bg-ce-blue/20`

type ArticleBodyTextareaProps = {
  content: string
  hashtags: string
  onChange: (body: string, hashtags: string) => void
  id?: string
  highlightPassage?: string | null
  contentBaseline?: string
}

function ArticleBodyTextarea({
  content,
  hashtags,
  onChange,
  id = 'content',
  highlightPassage = null,
  contentBaseline,
}: ArticleBodyTextareaProps) {
  const combined = combineBodyAndHashtags(content, hashtags)
  const baselineBody = contentBaseline ?? content
  const showOverlay =
    content !== baselineBody ||
    Boolean(highlightPassage && content.includes(highlightPassage)) ||
    textHasOpenPlaceholders(content)

  if (!showOverlay) {
    return (
      <ArticleAutoResizeTextarea
        id={id}
        value={combined}
        onChange={(value) => {
          const { body, hashtags: tags } = splitBodyAndHashtags(value)
          onChange(body, tags)
        }}
        className={`${bodyTypography} text-ce-black`}
      />
    )
  }

  const overlayNodes = renderUserInputDiff(baselineBody, content, { highlightPassage })
  const hashtagSuffix = hashtags ? `${BODY_HASHTAG_SEPARATOR}${hashtags}` : ''

  return (
    <div className="relative">
      <div
        aria-hidden
        className={`pointer-events-none absolute inset-0 ${bodyTypography} text-ce-black`}
      >
        {overlayNodes}
        {hashtagSuffix}
      </div>
      <ArticleAutoResizeTextarea
        id={id}
        value={combined}
        onChange={(value) => {
          const { body, hashtags: tags } = splitBodyAndHashtags(value)
          onChange(body, tags)
        }}
        className={overlayTextareaClass}
      />
    </div>
  )
}

export { ArticleBodyTextarea }
