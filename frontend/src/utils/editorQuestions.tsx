import type { ReactNode } from 'react'

/** Artikel-body met optionele redacteur-vragen (rood) in de editor. */

export const ARTICLE_BODY_MIN_HEIGHT_CLASS = 'min-h-[320px]'

/** Marker — moet overeenkomen met CE-03 Validate Data. Vragen worden per artikel door AI gegenereerd. */
export const EDITOR_BLOCK_MARKER = '\n\n---\nREDACTEUR'
export const EDITOR_QUESTION_PREFIX = '→ '

/** Alleen fallback als CE-03 Editor Questions-stap faalt. */
export const FALLBACK_EDITOR_QUESTIONS = [
  'Welke zin in deze draft klinkt het meest als AI — en wat zou jij daar in eigen woorden neerzetten?',
  'Welk concreet moment uit je werk past bij dit onderwerp en ontbreekt nu volledig?',
  'Waar zou een collega het oneens mee zijn — en wat is jouw tegenargument?',
] as const

export function buildEditorQuestionsBlock(questions: readonly string[] = FALLBACK_EDITOR_QUESTIONS): string {
  const lines = questions.map((q) => `${EDITOR_QUESTION_PREFIX}${q}`)
  return `${EDITOR_BLOCK_MARKER} — vul aan:\n${lines.join('\n')}`
}

export function appendEditorQuestionsToContent(
  body: string,
  questions: readonly string[] = FALLBACK_EDITOR_QUESTIONS,
): string {
  if (hasEditorQuestionsBlock(body)) return body
  const trimmed = body.trimEnd()
  if (!trimmed) return buildEditorQuestionsBlock(questions).trimStart()
  return `${trimmed}${buildEditorQuestionsBlock(questions)}`
}

export function splitEditorQuestionsBlock(body: string): {
  article: string
  editorBlock: string | null
} {
  let idx = body.indexOf(EDITOR_BLOCK_MARKER)
  if (idx < 0) {
    idx = body.indexOf('REDACTEUR — vul aan')
  }
  if (idx === -1) {
    return { article: body, editorBlock: null }
  }
  return {
    article: body.slice(0, idx).trimEnd(),
    editorBlock: body.slice(idx).trimStart(),
  }
}

export function stripEditorQuestionsBlock(body: string): string {
  return splitEditorQuestionsBlock(body).article
}

/** Herstel body na bewerken van artikel- en redacteur-deel. */
export function joinArticleAndEditorBlock(article: string, editorBlock: string): string {
  const a = article.trimEnd()
  const b = editorBlock.trimStart()
  if (!b) return a
  if (!a) return b
  return `${a}\n\n${b}`
}

/** Verwijder per ongeluk gedupliceerde redacteur-blokken (HBA single-field editor bug). */
export function normalizeEditorQuestionsContent(body: string): string {
  const { article, editorBlock } = splitEditorQuestionsBlock(body)
  if (!editorBlock) return body

  let cleanBlock = editorBlock
  const duplicateMarkerIdx = cleanBlock.indexOf(EDITOR_BLOCK_MARKER, EDITOR_BLOCK_MARKER.length)
  if (duplicateMarkerIdx > 0) {
    cleanBlock = cleanBlock.slice(0, duplicateMarkerIdx).trimEnd()
  } else {
    const label = 'REDACTEUR — vul aan'
    const duplicateLabelIdx = cleanBlock.indexOf(label, label.length)
    if (duplicateLabelIdx > 0) {
      cleanBlock = cleanBlock.slice(0, duplicateLabelIdx).trimEnd()
    }
  }

  return joinArticleAndEditorBlock(article, cleanBlock)
}

function hasEditorQuestionsBlock(body: string): boolean {
  return body.includes(EDITOR_BLOCK_MARKER) || body.includes('REDACTEUR — vul aan')
}

/** Alleen regels met → — zonder --- / REDACTEUR-label. */
export function parseEditorQuestionLines(editorBlock: string): string[] {
  return editorBlock
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.startsWith('→'))
}

/** Render artikeltekst met redacteur-vragen in rood (MessageFatigue / briefing). */
export function renderArticleWithEditorQuestions(body: string): ReactNode[] {
  const { article, editorBlock } = splitEditorQuestionsBlock(body)
  const nodes: ReactNode[] = []

  if (article) {
    nodes.push(<span key="article">{article}</span>)
  }

  if (!editorBlock) {
    return nodes.length ? nodes : [body]
  }

  const questionLines = parseEditorQuestionLines(editorBlock)
  if (questionLines.length === 0) {
    return nodes.length ? nodes : [body]
  }

  if (article) {
    nodes.push(<span key="sep">{'\n\n'}</span>)
  }

  nodes.push(
    <span key="editor-block" className="text-ce-red">
      {questionLines.map((line, index) => (
        <span key={`line-${index}`}>
          {index > 0 ? '\n' : ''}
          {line}
        </span>
      ))}
    </span>,
  )

  return nodes
}
