import type { HbaArticleHint } from '../api/hbaV2'
import { joinArticleAndEditorBlock, normalizeEditorQuestionsContent, splitEditorQuestionsBlock } from './editorQuestions'

function normalizeHintText(value?: string | null): string {
  return (value ?? '').replace(/\s+/g, ' ').trim()
}

const INVULVELD_RE = /\[[^\[\]]{3,200}\]/gu

export function extractInvulvelden(text: string): string[] {
  const seen = new Set<string>()
  const out: string[] = []
  for (const match of text.matchAll(INVULVELD_RE)) {
    const inner = (match[0] ?? '').replace(/^\[/, '').replace(/\]$/, '').trim()
    if (inner === '' || seen.has(inner)) continue
    seen.add(inner)
    out.push(inner)
  }
  return out
}

export function textHasOpenPlaceholders(text: string): boolean {
  return extractInvulvelden(text).length > 0
}

export const OPEN_PLACEHOLDER_MESSAGE =
  'Er staat nog een stukje tussen haken dat je zelf moet invullen.'

/** Eén citaat per herschrijving — LLM zet dezelfde CTA vaak in passage én cta. */
export function hintRewritePreviews(hint: {
  passageNa?: string | null
  headlineNa?: string | null
  ctaNa?: string | null
}): { label: string | null; text: string }[] {
  const items: { label: string | null; text: string }[] = []
  const seen = new Set<string>()

  const add = (label: string | null, value?: string | null) => {
    const text = (value ?? '').trim()
    const key = normalizeHintText(value).toLowerCase()
    if (key === '' || seen.has(key)) return
    seen.add(key)
    items.push({ label, text })
  }

  add('Kop', hint.headlineNa)
  const passage = normalizeHintText(hint.passageNa)
  const cta = normalizeHintText(hint.ctaNa)
  if (passage !== '' && cta !== '' && passage.toLowerCase() === cta.toLowerCase()) {
    add('Call to action', hint.ctaNa)
  } else {
    add(null, hint.passageNa)
    add('Call to action', hint.ctaNa)
  }

  return items
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function replaceFirst(text: string, before: string, after: string): { text: string; changed: boolean } {
  const needle = before.trim()
  const replacement = after.trim()
  if (needle === '' || replacement === '') {
    return { text, changed: false }
  }
  if (text.includes(needle)) {
    return { text: text.replace(needle, replacement), changed: true }
  }
  const pattern = escapeRegExp(needle).replace(/\s+/g, '\\s+')
  const re = new RegExp(pattern, 'u')
  if (!re.test(text)) {
    return { text, changed: false }
  }
  return { text: text.replace(re, replacement), changed: true }
}

export function applyHbaHintToArticle(
  headline: string,
  content: string,
  hint: Pick<HbaArticleHint, 'passageVoor' | 'passageNa' | 'headlineNa' | 'ctaVoor' | 'ctaNa'>,
): { headline: string; content: string; changed: boolean } {
  const { article, editorBlock } = splitEditorQuestionsBlock(content)
  let nextArticle = article
  let nextHeadline = headline
  let changed = false

  for (const [before, after] of [
    [hint.passageVoor, hint.passageNa],
    [hint.ctaVoor, hint.ctaNa],
  ] as const) {
    const result = replaceFirst(nextArticle, before ?? '', after ?? '')
    nextArticle = result.text
    changed = changed || result.changed
  }

  const headlineNa = hint.headlineNa?.trim() ?? ''
  if (headlineNa !== '' && headlineNa !== headline.trim()) {
    nextHeadline = headlineNa
    changed = true
  }

  const nextContent = editorBlock
    ? joinArticleAndEditorBlock(nextArticle, editorBlock)
    : nextArticle

  return {
    headline: nextHeadline,
    content: normalizeEditorQuestionsContent(nextContent),
    changed,
  }
}
