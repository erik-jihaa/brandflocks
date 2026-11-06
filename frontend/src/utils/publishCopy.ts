import { stripEditorQuestionsBlock } from './editorQuestions'

/** Tekst + hashtags voor handmatig plaatsen op social. */
export function buildPublishCopy(headline: string, content: string, hashtags: string): string {
  const tags = hashtags.trim()
  const parts = [headline.trim(), '', stripEditorQuestionsBlock(content).trim()]
  if (tags) {
    parts.push('', tags)
  }
  return parts.join('\n').trim()
}
