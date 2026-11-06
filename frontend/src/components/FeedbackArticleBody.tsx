import { forwardRef, useImperativeHandle, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { GRID2_ROW, ARTICLE_TEXT_MAX_WIDTH_CLASS } from '../layout/grid.constants'
import { ARTICLE_TITLE_BLOCK_CLASS, ArticleHeadlineField } from './ArticleFormatBox'
import { PencilIcon } from './PencilIcon'
import { FeedbackCard } from './FeedbackCard'
import { addedTextFragment } from '../utils/contentDiff'
import type { FeedbackDisplayItem } from '../utils/feedbackItems'
import { assignFeedbackToRows } from '../utils/feedbackItems'
import { measureCorrectionOffset } from '../utils/feedbackCardAlign'
import {
  listChanges,
  trackedChangeComment,
  trackedRows,
  type TrackedChange,
  type TrackedDoc,
} from '../utils/trackedChanges'

export type FeedbackArticleBodyHandle = {
  /** Lees alle contentEditable-regels uit de DOM (vóór okay/delete — blur wordt geblokkeerd). */
  flushEdits: () => { body: string; hashtags: string } | null
}

type FeedbackArticleBodyProps = {
  headline: string
  onHeadlineChange: (value: string) => void
  headlineClassName?: string
  /** Tracked-changes-document (Word-model); null zonder pending review-edit. */
  doc: TrackedDoc | null
  content: string
  hashtags: string
  onBodyEdited: (body: string, hashtags: string) => void
  onChangeAction: (changeId: string, action: 'okay' | 'deleted') => void
  headlineFeedback?: FeedbackDisplayItem
  /** AI-feedback (CE-04) — alleen getoond zonder pending review-edit. */
  apiFeedbackItems?: FeedbackDisplayItem[]
  onFeedbackAction: (item: FeedbackDisplayItem, action: 'okay' | 'deleted') => void
}

/** Tekst uit een contentEditable-regel halen (nbsp weg, geen trailing newlines). */
export function editableText(el: HTMLElement): string {
  return el.innerText.replace(/\u00a0/g, ' ').replace(/\n+$/, '')
}

type EditableLineProps = {
  text: string
  className: string
  ariaLabel: string
  onCommit: (next: string) => void
  dataAttr?: 'body' | 'hashtag'
}

/** Inline bewerkbare tekstregel — commit bij blur; altijd platte tekst (geen diff-spans in contentEditable). */
const EditableLine = forwardRef<HTMLParagraphElement, EditableLineProps>(function EditableLine(
  { text, className, ariaLabel, onCommit, dataAttr },
  ref,
) {
  return (
    <p
      ref={ref}
      contentEditable
      suppressContentEditableWarning
      role="textbox"
      aria-label={ariaLabel}
      data-ce-editable={dataAttr ?? 'body'}
      className={`m-0 min-h-[18px] cursor-text whitespace-pre-wrap outline-none ${className}`}
      onBlur={(e) => {
        const next = editableText(e.currentTarget)
        if (next !== text) onCommit(next)
      }}
    >
      {text === '' ? '\u00a0' : text}
    </p>
  )
})

type FeedbackSideCardProps = {
  lineRef: React.RefObject<HTMLParagraphElement | null>
  alignTo?: string
  lineSnapshot?: string
  children: React.ReactNode
}

/** Lijn feedbackkaart uit met correctietekst in de regel (wrap-safe). */
function FeedbackSideCard({ lineRef, alignTo, lineSnapshot, children }: FeedbackSideCardProps) {
  const [marginTop, setMarginTop] = useState(0)

  useLayoutEffect(() => {
    const el = lineRef.current
    if (!el || !alignTo?.trim()) {
      setMarginTop(0)
      return
    }
    const run = () => setMarginTop(measureCorrectionOffset(el, alignTo))
    run()
    window.addEventListener('resize', run)
    return () => window.removeEventListener('resize', run)
  }, [lineRef, alignTo, lineSnapshot])

  return <div style={marginTop > 0 ? { marginTop } : undefined}>{children}</div>
}

type BodyTextRowProps = {
  lineRef: React.RefObject<HTMLParagraphElement | null>
  main: React.ReactNode
  card?: React.ReactNode
  alignTo?: string
  lineSnapshot?: string
}

function BodyTextRow({ lineRef, main, card, alignTo, lineSnapshot }: BodyTextRowProps) {
  return (
    <div className={`${GRID2_ROW.rowClass} items-start`}>
      <div className={`${GRID2_ROW.mainClass} ${ARTICLE_TEXT_MAX_WIDTH_CLASS} min-h-[18px]`}>{main}</div>
      <div className={`${GRID2_ROW.sideClass} min-h-[18px]`}>
        {card ? (
          <FeedbackSideCard lineRef={lineRef} alignTo={alignTo} lineSnapshot={lineSnapshot}>
            {card}
          </FeedbackSideCard>
        ) : null}
      </div>
    </div>
  )
}

function cardCommentForTrackedRow(
  row: { kind: string; text: string; baselineLine?: string; isChangeStart?: boolean },
  change: TrackedChange | undefined,
): string | null {
  if (!change || change.status !== 'pending') return null
  if (row.kind === 'added') {
    const fragment = addedTextFragment(row.baselineLine ?? '', row.text)
    return fragment || null
  }
  if (row.kind === 'removed' && row.isChangeStart) {
    return trackedChangeComment(change)
  }
  return null
}

type PlainLineRowProps = {
  index: number
  line: string
  card?: FeedbackDisplayItem
  onCommit: (next: string) => void
  onFeedbackAction: FeedbackArticleBodyProps['onFeedbackAction']
}

function PlainLineRow({ index, line, card, onCommit, onFeedbackAction }: PlainLineRowProps) {
  const lineRef = useRef<HTMLParagraphElement>(null)

  return (
    <BodyTextRow
      lineRef={lineRef}
      alignTo={card?.comment}
      lineSnapshot={line}
      main={
        <EditableLine
          ref={lineRef}
          key={`plain-${index}`}
          text={line}
          ariaLabel="Artikeltekst bewerken"
          className="text-[14px] leading-[18px] text-ce-black"
          onCommit={onCommit}
        />
      }
      card={
        card ? (
          <FeedbackCard
            name={card.name}
            comment={card.comment}
            actionLabel={card.actionLabel}
            commentTone="text"
            onOkay={() => onFeedbackAction(card, 'okay')}
            onDelete={() => onFeedbackAction(card, 'deleted')}
          />
        ) : undefined
      }
    />
  )
}

type TrackedLineRowProps = {
  row: ReturnType<typeof trackedRows>[number]
  lineIndex: number
  change?: TrackedChange
  cardComment: string | null
  onCommitLine: (visibleIndex: number, next: string) => void
  onChangeAction: FeedbackArticleBodyProps['onChangeAction']
}

function TrackedLineRow({
  row,
  lineIndex,
  change,
  cardComment,
  onCommitLine,
  onChangeAction,
}: TrackedLineRowProps) {
  const lineRef = useRef<HTMLParagraphElement>(null)

  const main =
    row.kind === 'removed' ? (
      <p className="m-0 min-h-[18px] whitespace-pre-wrap text-[14px] leading-[18px] text-ce-beige line-through">
        {row.text === '' ? '\u00a0' : row.text}
      </p>
    ) : (
      <EditableLine
        ref={lineRef}
        key={row.key}
        text={row.text}
        ariaLabel="Artikeltekst bewerken"
        className={
          row.kind === 'added'
            ? 'text-[14px] leading-[18px] text-ce-red'
            : 'text-[14px] leading-[18px] text-ce-black'
        }
        onCommit={(next) => onCommitLine(lineIndex, next)}
      />
    )

  return (
    <BodyTextRow
      lineRef={lineRef}
      alignTo={cardComment ?? undefined}
      lineSnapshot={row.text}
      main={main}
      card={
        cardComment && change ? (
          <FeedbackCard
            name="Beoordelen"
            comment={cardComment}
            commentTone="text"
            onOkay={() => onChangeAction(change.id, 'okay')}
            onDelete={() => onChangeAction(change.id, 'deleted')}
          />
        ) : undefined
      }
    />
  )
}

/** Feedback-pagina: Word-stijl wijzigingen bijhouden (briefing FeedbackType+.png). */
export const FeedbackArticleBody = forwardRef<FeedbackArticleBodyHandle, FeedbackArticleBodyProps>(
  function FeedbackArticleBody(
    {
      headline,
      onHeadlineChange,
      headlineClassName,
      doc,
      content,
      hashtags,
      onBodyEdited,
      onChangeAction,
      headlineFeedback,
      apiFeedbackItems = [],
      onFeedbackAction,
    },
    ref,
  ) {
    const rootRef = useRef<HTMLDivElement>(null)

    useImperativeHandle(ref, () => ({
      flushEdits() {
        const root = rootRef.current
        if (!root) return null
        const lineEls = root.querySelectorAll<HTMLElement>('[data-ce-editable="body"]')
        if (lineEls.length === 0) return null
        const bodyLines = [...lineEls].map((el) => editableText(el))
        const tagEl = root.querySelector<HTMLElement>('[data-ce-editable="hashtag"]')
        return {
          body: bodyLines.join('\n'),
          hashtags: tagEl ? editableText(tagEl) : hashtags,
        }
      },
    }))

    const rows = useMemo(() => (doc ? trackedRows(doc) : null), [doc])
    const changeById = useMemo(() => {
      const map = new Map<string, ReturnType<typeof listChanges>[number]>()
      if (doc) for (const change of listChanges(doc)) map.set(change.id, change)
      return map
    }, [doc])

    /** Regel visibleIndex vervangen door (mogelijk meerregelige) nieuwe tekst. */
    function commitLine(lines: string[], visibleIndex: number, next: string) {
      const nextLines = [...lines]
      nextLines[visibleIndex] = next
      onBodyEdited(nextLines.join('\n'), hashtags)
    }

    const plainLines = content.split('\n')
    const visibleRowLines = rows
      ? rows.filter((row) => row.kind !== 'removed').map((row) => row.text)
      : null

    const apiCardsByLine = useMemo(() => {
      const lineRows = plainLines.map((line, lineIndex) => ({
        key: `plain-${lineIndex}`,
        lineIndex,
        baselineLine: line,
        currentLine: line,
        correction: null,
      }))
      return assignFeedbackToRows(lineRows, apiFeedbackItems)
    }, [plainLines, apiFeedbackItems])

    return (
      <div ref={rootRef}>
        <div className={GRID2_ROW.rowClass}>
          <div className={`${GRID2_ROW.mainClass} ${ARTICLE_TEXT_MAX_WIDTH_CLASS}`}>
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
          </div>
          <div className={GRID2_ROW.sideClass}>
            {headlineFeedback && (
              <FeedbackCard
                name={headlineFeedback.name}
                comment={headlineFeedback.comment}
                actionLabel={headlineFeedback.actionLabel}
                commentTone="text"
                onOkay={() => onFeedbackAction(headlineFeedback, 'okay')}
                onDelete={() => onFeedbackAction(headlineFeedback, 'deleted')}
              />
            )}
          </div>
        </div>

        {!rows ? (
          <div className="mt-[12px]">
            {plainLines.map((line, index) => (
              <PlainLineRow
                key={`plain-${index}`}
                index={index}
                line={line}
                card={apiCardsByLine.get(index)}
                onCommit={(next) => commitLine(plainLines, index, next)}
                onFeedbackAction={onFeedbackAction}
              />
            ))}

            {hashtags && (
              <div className={`${GRID2_ROW.rowClass} mt-[12px] items-start`}>
                <div className={`${GRID2_ROW.mainClass} ${ARTICLE_TEXT_MAX_WIDTH_CLASS}`}>
                  <EditableLine
                    key={`tags-${hashtags}`}
                    text={hashtags}
                    ariaLabel="Hashtags bewerken"
                    dataAttr="hashtag"
                    className="text-[14px] leading-[18px] text-ce-black"
                    onCommit={(next) => onBodyEdited(content, next)}
                  />
                </div>
                <div className={GRID2_ROW.sideClass} />
              </div>
            )}
          </div>
        ) : (
          <div className="mt-[12px]">
            {(() => {
              let visibleIndex = -1
              return rows.map((row) => {
                const change = row.changeId ? changeById.get(row.changeId) : undefined
                const cardComment = cardCommentForTrackedRow(row, change)
                if (row.kind !== 'removed') visibleIndex += 1
                const lineIndex = visibleIndex

                return (
                  <TrackedLineRow
                    key={row.key}
                    row={row}
                    lineIndex={lineIndex}
                    change={change}
                    cardComment={cardComment}
                    onCommitLine={(idx, next) => commitLine(visibleRowLines!, idx, next)}
                    onChangeAction={onChangeAction}
                  />
                )
              })
            })()}

            {hashtags && (
              <div className={`${GRID2_ROW.rowClass} mt-[12px] items-start`}>
                <div className={`${GRID2_ROW.mainClass} ${ARTICLE_TEXT_MAX_WIDTH_CLASS}`}>
                  <EditableLine
                    key={`tags2-${hashtags}`}
                    text={hashtags}
                    ariaLabel="Hashtags bewerken"
                    dataAttr="hashtag"
                    className="text-[14px] leading-[18px] text-ce-black"
                    onCommit={(next) => onBodyEdited(content, next)}
                  />
                </div>
                <div className={GRID2_ROW.sideClass} />
              </div>
            )}
          </div>
        )}
      </div>
    )
  },
)
