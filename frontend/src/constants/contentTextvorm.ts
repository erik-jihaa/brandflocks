/** Tekstvorm (DB-sleutel) per kanaal — addendum §3. */

const PLATFORM_DB: Record<string, string> = {
  Linkedin: 'linkedin',
  Instagram: 'instagram',
  X: 'x',
  Facebook: 'facebook',
  Blog: 'blog',
}

export const TEXTVORM_OPTIONS_BY_PLATFORM: Record<string, readonly string[]> = {
  blog: ['artikel'],
  linkedin: ['post', 'artikel', 'promotie'],
  instagram: ['bijschrift', 'carrousel'],
  x: ['post'],
  facebook: ['post'],
}

export const TEXTVORM_LABELS: Record<string, string> = {
  artikel: 'Artikel',
  post: 'Zelfstandige post',
  promotie: 'Promotiepost bij artikel',
  bijschrift: 'Bijschrift',
  carrousel: 'Carrouseltekst',
  videoscript: 'Videoscript en bijschrift',
  editie: 'Editie',
}

export function platformUiToDb(platform: string): string {
  return PLATFORM_DB[platform] ?? platform.trim().toLowerCase()
}

export function defaultTextvormForPlatform(platform: string): string {
  const db = platformUiToDb(platform)
  const options = TEXTVORM_OPTIONS_BY_PLATFORM[db]
  if (options && options.length > 0) return options[0]
  return 'post'
}

export function textvormOptionsForPlatform(
  platform: string,
  fromApi?: Record<string, Array<{ value: string; label: string }>> | null,
): Array<{ value: string; label: string }> {
  const db = platformUiToDb(platform)
  const apiRow = fromApi?.[db]
  if (apiRow && apiRow.length > 0) {
    return apiRow
  }
  const keys = TEXTVORM_OPTIONS_BY_PLATFORM[db] ?? ['post']

  return keys.map((value) => ({
    value,
    label: TEXTVORM_LABELS[value] ?? value,
  }))
}

export function textvormDisplayLabel(value: string): string {
  return TEXTVORM_LABELS[value] ?? value
}

export function coerceTextvormForPlatform(platform: string, textvorm: string): string {
  const options = textvormOptionsForPlatform(platform).map((o) => o.value)
  const v = textvorm.trim().toLowerCase()
  if (options.includes(v)) return v
  return defaultTextvormForPlatform(platform)
}
