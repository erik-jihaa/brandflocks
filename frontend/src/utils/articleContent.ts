/** Twee witregels tussen body copy en hashtags (14/18 line-height) */
export const BODY_HASHTAG_SEPARATOR = '\n\n\n'

export function combineBodyAndHashtags(body: string, hashtags: string): string {
  const tags = hashtags.trim()
  if (!tags) return body
  if (!body) return tags
  return `${body}${BODY_HASHTAG_SEPARATOR}${hashtags}`
}

export function splitBodyAndHashtags(value: string): { body: string; hashtags: string } {
  const idx = value.lastIndexOf(BODY_HASHTAG_SEPARATOR)
  if (idx === -1) return { body: value, hashtags: '' }
  return {
    body: value.slice(0, idx),
    hashtags: value.slice(idx + BODY_HASHTAG_SEPARATOR.length),
  }
}
