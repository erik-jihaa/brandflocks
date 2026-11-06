/** Placeholder voor sandbox/test-posts — zelfde asset als hero.png */
export const TEST_IMAGE_URLS = {
  square: '/images/test-image-square.png',
  landscape: '/images/test-image-landscape.png',
} as const

function resolveImageRatio(ratio: string): 'square' | 'landscape' {
  return ratio === 'landscape' ? 'landscape' : 'square'
}

export function testImageUrl(imageRatio: string): string {
  return TEST_IMAGE_URLS[resolveImageRatio(imageRatio)]
}

export function isTestImageUrl(url: string | null | undefined): boolean {
  const resolved = resolveMediaUrl(url)
  if (!resolved) return false
  return resolved.includes('/images/test-image-')
}

export function isManualPublishMode(mode: string | null | undefined): boolean {
  return mode === 'manual_with_review' || mode === 'manual_without_review'
}

export function isTestPost(options: {
  usingMock?: boolean
  slotId?: string
  headline?: string
  company?: string
}): boolean {
  if (options.usingMock) return true
  if (options.slotId === 'demo') return true
  if (/\bsandbox\b/i.test(options.company ?? '')) return true
  if (/\btest\b/i.test(options.headline ?? '')) return true
  return false
}

/** Zet API-paden (/uploads/…) om naar een bruikbare src voor <img>. */
export function resolveMediaUrl(url: string | null | undefined): string | null {
  if (!url) return null
  const trimmed = url.trim()
  if (trimmed === '') return null
  if (
    trimmed.startsWith('http://') ||
    trimmed.startsWith('https://') ||
    trimmed.startsWith('blob:') ||
    trimmed.startsWith('data:')
  ) {
    return trimmed
  }
  return trimmed.startsWith('/') ? trimmed : `/${trimmed}`
}

export type PickArticleImageOptions = {
  /** Toon test-beeld wanneer square/landscape leeg zijn */
  testFallback?: boolean
}

/** Eerste beschikbare variant (square → landscape), optioneel test-placeholder. */
export function pickArticleImageUrl(
  imageRatio: string,
  square: string | null | undefined,
  landscape: string | null | undefined,
  options?: PickArticleImageOptions,
): string | null {
  const allowTest = options?.testFallback === true
  const sqRaw = resolveMediaUrl(square)
  const lsRaw = resolveMediaUrl(landscape)
  const sq = sqRaw && isTestImageUrl(sqRaw) && !allowTest ? null : sqRaw
  const ls = lsRaw && isTestImageUrl(lsRaw) && !allowTest ? null : lsRaw
  const picked =
    resolveImageRatio(imageRatio) === 'landscape' ? (ls ?? sq) : (sq ?? ls)
  if (picked) return picked
  if (allowTest) {
    return testImageUrl(imageRatio)
  }
  return null
}

/** Ratio afleiden uit meta + welke afbeelding daadwerkelijk aanwezig is. */
export function resolveEffectiveImageRatio(
  ratio: string,
  square: string | null | undefined,
  landscape: string | null | undefined,
): 'square' | 'landscape' {
  const hasSquare = Boolean(square?.trim())
  const hasLandscape = Boolean(landscape?.trim())
  if (hasSquare && !hasLandscape) return 'square'
  if (hasLandscape && !hasSquare) return 'landscape'
  return ratio === 'landscape' ? 'landscape' : 'square'
}
