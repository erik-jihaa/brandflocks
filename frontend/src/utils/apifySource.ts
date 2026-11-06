/** Apify actor URL / ID parsing — zelfde velden als n8n HTTP Request (POST + ?token=). */

export const DEFAULT_LINKEDIN_APIFY_ACTOR_URL =
  'https://api.apify.com/v2/acts/apimaestro~linkedin-batch-profile-posts-scraper/run-sync-get-dataset-items'

export const DEFAULT_LINKEDIN_APIFY_ACTOR_ID = 'apimaestro~linkedin-batch-profile-posts-scraper'

export const DEFAULT_LINKEDIN_COMPANY_APIFY_ACTOR_URL =
  'https://api.apify.com/v2/acts/apimaestro~linkedin-company-posts-batch-scraper-no-cookies/run-sync-get-dataset-items'

export const DEFAULT_LINKEDIN_COMPANY_APIFY_ACTOR_ID =
  'apimaestro~linkedin-company-posts-batch-scraper-no-cookies'

export function isLinkedInCompanyProfile(raw: string | null | undefined): boolean {
  return /linkedin\.com\/company\//i.test((raw ?? '').trim())
}

export function parseApifyActorFromUrl(raw: string): { actorId: string | null; apiUrl: string | null } {
  const trimmed = raw.trim()
  if (!trimmed) {
    return { actorId: null, apiUrl: null }
  }

  if (/^https?:\/\/api\.apify\.com\/v2\/acts\//i.test(trimmed)) {
    const match = trimmed.match(/\/acts\/([^/?]+)/i)
    const actorId = match ? decodeURIComponent(match[1]).replace(/\//g, '~') : null
    return { actorId, apiUrl: trimmed.split('?')[0] ?? trimmed }
  }

  if (trimmed.includes('~') || !trimmed.includes('/')) {
    return { actorId: trimmed.replace(/\//g, '~'), apiUrl: null }
  }

  return { actorId: null, apiUrl: null }
}

export function scraperActorDisplayUrl(
  url: string | null | undefined,
  actorId: string | null | undefined,
): string {
  const fromUrl = (url ?? '').trim()
  if (fromUrl && /api\.apify\.com/i.test(fromUrl)) {
    return fromUrl.split('?')[0] ?? fromUrl
  }

  const actor = (actorId ?? '').trim()
  if (actor) {
    const slug = actor.replace(/\//g, '~')
    return `https://api.apify.com/v2/acts/${slug}/run-sync-get-dataset-items`
  }

  return fromUrl
}

export function isLinkedInScraperSource(
  url: string | null | undefined,
  actorId: string | null | undefined,
): boolean {
  const haystack = `${url ?? ''} ${actorId ?? ''}`.toLowerCase()
  return haystack.includes('linkedin')
}
