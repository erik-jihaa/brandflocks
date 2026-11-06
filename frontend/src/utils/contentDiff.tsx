import type { ReactNode } from 'react'
import { diffArrays, diffLines, diffWordsWithSpace } from 'diff'

/** Woorden/regels die in het voorstel zijn toegevoegd t.o.v. baseline (feedbackkaart-label). */
export function addedTextFragment(baselineLine: string, suggestedLine: string): string {
  return diffWordsWithSpace(baselineLine, suggestedLine)
    .filter((part) => part.added)
    .map((part) => part.value)
    .join('')
    .trim()
}

const INVULVELD_RE = /\[[^\[\]]{3,200}\]/gu

/** Oranje markering voor open invulvelden `[...]` in de editor. */
export function renderInvulveldHighlights(text: string, keyPrefix = 'iv'): ReactNode[] {
  const nodes: ReactNode[] = []
  let last = 0
  let i = 0
  for (const match of text.matchAll(INVULVELD_RE)) {
    const start = match.index ?? 0
    const raw = match[0] ?? ''
    if (start > last) {
      nodes.push(<span key={`${keyPrefix}-t-${i}`}>{text.slice(last, start)}</span>)
    }
    nodes.push(
      <mark
        key={`${keyPrefix}-m-${i}`}
        className="rounded-[2px] bg-ce-orange/35 text-ce-black"
      >
        {raw}
      </mark>,
    )
    last = start + raw.length
    i += 1
  }
  if (last < text.length) {
    nodes.push(<span key={`${keyPrefix}-t-end`}>{text.slice(last)}</span>)
  }
  return nodes.length > 0 ? nodes : [<span key={`${keyPrefix}-plain`}>{text}</span>]
}

function renderPassageHighlight(text: string, passage: string, keyPrefix = 'hl'): ReactNode[] {
  const index = text.indexOf(passage)
  if (index < 0) return [<span key={`${keyPrefix}-plain`}>{text}</span>]

  const nodes: ReactNode[] = []
  if (index > 0) nodes.push(<span key={`${keyPrefix}-b`}>{text.slice(0, index)}</span>)
  nodes.push(
    <span key={`${keyPrefix}-m`} className="text-ce-red">
      {passage}
    </span>,
  )
  const after = text.slice(index + passage.length)
  if (after) nodes.push(...renderPassageHighlight(after, passage, `${keyPrefix}-a`))
  return nodes
}

/** Blauwe highlights: nieuwe invoer t.o.v. opgeslagen baseline (Re-edit-aantekeningen). */
export function renderUserInputDiff(
  baseline: string,
  current: string,
  options?: { highlightPassage?: string | null },
): ReactNode[] {
  const highlight = options?.highlightPassage?.trim()

  if (baseline === current) {
    if (highlight && current.includes(highlight)) {
      return renderPassageHighlight(current, highlight)
    }
    return renderInvulveldHighlights(current, 'user-same')
  }

  const nodes: ReactNode[] = []
  diffWordsWithSpace(baseline, current).forEach((part, index) => {
    if (part.removed) return
    if (part.added) {
      nodes.push(
        <span key={`user-add-${index}`} className="text-ce-blue">
          {renderInvulveldHighlights(part.value, `user-add-${index}`)}
        </span>,
      )
      return
    }
    if (highlight && part.value.includes(highlight)) {
      nodes.push(...renderPassageHighlight(part.value, highlight, `user-same-${index}`))
      return
    }
    nodes.push(<span key={`user-same-${index}`}>{part.value}</span>)
  })
  return nodes
}

/** Rode highlights: tekst die afwijkt van het AI-concept (toegevoegd/gewijzigd). */
export function renderContentDiff(baseline: string, current: string): ReactNode[] {
  if (baseline === current) {
    return [current]
  }

  return diffWordsWithSpace(baseline, current).flatMap((part, index) => {
    if (part.removed) return []
    if (part.added) {
      return [
        <span key={`add-${index}`} className="text-ce-red">
          {part.value}
        </span>,
      ]
    }
    return [<span key={`same-${index}`}>{part.value}</span>]
  })
}

/** Eén regel: gewijzigde woorden in rood t.o.v. baseline-regel. */
export function renderLineDiff(baselineLine: string, currentLine: string): ReactNode[] {
  if (baselineLine === currentLine) {
    return [currentLine]
  }

  return diffWordsWithSpace(baselineLine, currentLine).flatMap((part, index) => {
    if (part.removed) return []
    if (part.added) {
      return [
        <span key={`line-add-${index}`} className="text-ce-red">
          {part.value}
        </span>,
      ]
    }
    return [<span key={`line-same-${index}`}>{part.value}</span>]
  })
}

/** Feedback-pagina: regels met correctie in rood (briefing FeedbackType+.png). */
export function renderFeedbackInlineContent(baseline: string, current: string): ReactNode[] {
  if (baseline === current) {
    return [current]
  }

  const parts = diffLines(baseline, current)
  const nodes: ReactNode[] = []

  parts.forEach((part, index) => {
    if (part.removed) return
    if (part.added) {
      nodes.push(
        <span key={`line-add-${index}`} className="text-ce-red">
          {part.value}
        </span>,
      )
      return
    }
    nodes.push(<span key={`line-same-${index}`}>{part.value}</span>)
  })

  return nodes
}

export type FeedbackLineRow = {
  key: string
  lineIndex: number
  baselineLine: string
  currentLine: string
  /** Fragment voor feedbackkaart (alleen toegevoegd/gewijzigd deel). */
  correction: string | null
}

/** Regels uit review-diff — LCS-uitlijning (geen index-shift bij insert). */
export function buildFeedbackLineRows(baseline: string, current: string): FeedbackLineRow[] {
  if (baseline === current) {
    return current.split('\n').map((line, lineIndex) => ({
      key: `same-${lineIndex}`,
      lineIndex,
      baselineLine: line,
      currentLine: line,
      correction: null,
    }))
  }

  const baselineLines = baseline.split('\n')
  const currentLines = current.split('\n')
  const parts = diffArrays(baselineLines, currentLines)
  const rows: FeedbackLineRow[] = []
  let currentLineIndex = 0
  let pendingRemoved: string[] = []

  for (const part of parts) {
    if (part.removed) {
      pendingRemoved.push(...part.value)
      continue
    }

    if (part.added) {
      for (const line of part.value) {
        const baselineLine = pendingRemoved.shift() ?? ''
        const addedParts = diffWordsWithSpace(baselineLine, line)
          .filter((p) => p.added)
          .map((p) => p.value)
          .join('')
          .trim()
        const isNewLine = baselineLine === '' && line.trim() !== ''
        const isChanged = baselineLine !== line

        rows.push({
          key: isChanged ? `changed-${currentLineIndex}` : `same-${currentLineIndex}`,
          lineIndex: currentLineIndex,
          baselineLine,
          currentLine: line,
          correction:
            isNewLine || isChanged ? addedParts || line.trim() || null : null,
        })
        currentLineIndex += 1
      }
      pendingRemoved = []
      continue
    }

    pendingRemoved = []
    for (const line of part.value) {
      rows.push({
        key: `same-${currentLineIndex}`,
        lineIndex: currentLineIndex,
        baselineLine: line,
        currentLine: line,
        correction: null,
      })
      currentLineIndex += 1
    }
  }

  return rows
}

/** Zoek regelindex in content — robuust na splices en kleine tekstwijzigingen. */
export function findLineInContent(
  lines: string[],
  currentLine: string,
  hintIndex = -1,
  correction?: string,
): number {
  if (hintIndex >= 0 && hintIndex < lines.length && lines[hintIndex] === currentLine) {
    return hintIndex
  }

  const exact = lines.findIndex((line) => line === currentLine)
  if (exact >= 0) return exact

  const trimmed = currentLine.trim()
  if (trimmed !== '') {
    const byTrim = lines.findIndex((line) => line.trim() === trimmed)
    if (byTrim >= 0) return byTrim
  }

  if (correction && correction.trim() !== '') {
    const needle = correction.trim()
    const byCorrection = lines.findIndex(
      (line) => line.includes(needle) || needle.includes(line.trim()),
    )
    if (byCorrection >= 0) return byCorrection
  }

  return -1
}

/**
 * Herstel één specifieke regelcorrectie in content.
 * Gebruikt de opgeslagen baselineLine + currentLine uit FeedbackDisplayItem —
 * hiermee werkt de revert correct ongeacht eerdere splices.
 */
export function revertSpecificLine(
  content: string,
  lineIndex: number,
  currentLine: string,
  baselineLine: string,
  correction?: string,
): string {
  const lines = content.split('\n')
  let idx = findLineInContent(lines, currentLine, lineIndex, correction)
  if (idx < 0 && lineIndex >= 0 && lineIndex < lines.length) {
    idx = lineIndex
  }

  if (idx < 0) return content

  if (baselineLine === '' && lines[idx].trim() !== '') {
    lines.splice(idx, 1)
  } else {
    lines[idx] = baselineLine
  }

  return lines.join('\n')
}

/** Accepteer huidige regeltekst in baseline (okay — geen diff meer op deze regel). */
export function acceptLineInBaseline(
  baseline: string,
  content: string,
  lineIndex: number,
  currentLine: string,
  baselineLine: string,
  correction?: string,
): string {
  const baseLines = baseline.split('\n')
  const contentLines = content.split('\n')
  const idx = findLineInContent(contentLines, currentLine, lineIndex, correction)
  const resolvedIdx =
    idx >= 0 ? idx : lineIndex >= 0 && lineIndex < contentLines.length ? lineIndex : -1
  if (resolvedIdx < 0) return baseline

  const isInsert = baselineLine === '' && currentLine.trim() !== ''
  if (isInsert) {
    const insertAt = Math.min(resolvedIdx, baseLines.length)
    if (baseLines[insertAt] !== contentLines[resolvedIdx]) {
      baseLines.splice(insertAt, 0, contentLines[resolvedIdx])
    }
  } else if (resolvedIdx < baseLines.length) {
    baseLines[resolvedIdx] = contentLines[resolvedIdx]
  } else {
    while (baseLines.length <= resolvedIdx) baseLines.push('')
    baseLines[resolvedIdx] = contentLines[resolvedIdx]
  }

  return baseLines.join('\n')
}

export type DiffFeedbackItem = {
  id: string
  comment: string
}

/** Feedbackkaarten afgeleid van diff-segmenten. */
export function buildDiffFeedbackItems(baseline: string, current: string): DiffFeedbackItem[] {
  if (baseline === current) return []

  return buildFeedbackLineRows(baseline, current)
    .filter((row) => row.correction)
    .map((row, index) => ({
      id: `diff-line-${row.lineIndex}-${index}`,
      comment: row.correction!,
    }))
}
