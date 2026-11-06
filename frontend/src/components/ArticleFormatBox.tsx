import { useEffect, useRef } from 'react'
import { PINK_BOX_CLASS } from '../layout/grid.constants'

/** Zelfde typografie als ArticleCard — Artboard 20 */
export const ARTICLE_TITLE_CLASS = 'm-0 text-[24px] leading-[28px] font-semibold text-ce-blue whitespace-normal break-words'
export const ARTICLE_TITLE_INPUT_CLASS =
  'w-full min-h-[28px] resize-none overflow-hidden whitespace-normal break-words text-[24px] leading-[28px] font-semibold text-ce-blue'
export const ARTICLE_BODY_CLASS = 'm-0 text-[14px] leading-[18px] text-ce-black'
/** Theme — sectielabels, rationale, story, URL (18px) */
export const THEME_LABEL_CLASS = 'm-0 mb-[6px] text-[18px] leading-[21px] font-semibold text-ce-black'
export const THEME_BODY_CLASS = 'm-0 text-[18px] leading-[21px] text-ce-black'
export const THEME_BODY_INPUT_CLASS = 'text-[18px] leading-[21px] text-ce-black'
export const ARTICLE_TITLE_BLOCK_CLASS = 'mb-[12px]'

const headlineFieldReset =
  'w-full border-0 bg-transparent p-0 outline-none focus:ring-0 placeholder:text-ce-beige'

const autoResizeFieldReset =
  'w-full resize-none overflow-hidden border-0 bg-transparent p-0 outline-none focus:ring-0 placeholder:text-ce-beige'

type ArticleAutoResizeTextareaProps = {
  value: string
  onChange: (value: string) => void
  id?: string
  className?: string
  disabled?: boolean
  placeholder?: string
  'aria-label'?: string
  rows?: number
}

/** Body/hashtags — groeit mee met inhoud, geen scroll-kader of resize-handle. */
export function ArticleAutoResizeTextarea({
  value,
  onChange,
  id,
  className = '',
  disabled = false,
  placeholder,
  'aria-label': ariaLabel,
  rows = 1,
}: ArticleAutoResizeTextareaProps) {
  const ref = useRef<HTMLTextAreaElement>(null)

  function syncHeight() {
    const el = ref.current
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${el.scrollHeight}px`
  }

  useEffect(() => {
    syncHeight()
  }, [value])

  return (
    <textarea
      ref={ref}
      id={id}
      value={value}
      disabled={disabled}
      placeholder={placeholder}
      aria-label={ariaLabel}
      rows={rows}
      onChange={(e) => {
        onChange(e.target.value)
        requestAnimationFrame(syncHeight)
      }}
      className={`${autoResizeFieldReset}${className ? ` ${className}` : ''}${disabled ? ' opacity-70' : ''}`}
    />
  )
}

type ArticleHeadlineFieldProps = {
  value: string
  onChange: (value: string) => void
  id?: string
  className?: string
  disabled?: boolean
  placeholder?: string
}

/** Post-titel — wrapt over meerdere regels (geen single-line input). */
export function ArticleHeadlineField({
  value,
  onChange,
  id = 'headline',
  className = '',
  disabled = false,
  placeholder,
}: ArticleHeadlineFieldProps) {
  const ref = useRef<HTMLTextAreaElement>(null)

  function syncHeight() {
    const el = ref.current
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${el.scrollHeight}px`
  }

  useEffect(() => {
    syncHeight()
  }, [value])

  return (
    <textarea
      ref={ref}
      id={id}
      value={value}
      disabled={disabled}
      placeholder={placeholder}
      onChange={(e) => {
        onChange(e.target.value)
        requestAnimationFrame(syncHeight)
      }}
      rows={1}
      aria-label="Posttitel"
      className={`${headlineFieldReset} ${ARTICLE_TITLE_INPUT_CLASS}${className ? ` ${className}` : ''}${disabled ? ' opacity-70' : ''}`}
    />
  )
}

type ArticleFormatBoxProps = {
  title: string
  children?: React.ReactNode
}

/** Read-only pink kader — zelfde padding/typografie als ArticleCard, zonder pencil */
export function ArticleFormatBox({ title, children }: ArticleFormatBoxProps) {
  return (
    <div className={PINK_BOX_CLASS}>
      <div className={ARTICLE_TITLE_BLOCK_CLASS}>
        <p className={ARTICLE_TITLE_CLASS}>{title}</p>
      </div>
      {children}
    </div>
  )
}
