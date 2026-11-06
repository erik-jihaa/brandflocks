import { apiFetch, ApiError } from './client'
import type {
  ArticleDto,
  ArticleSavePayload,
  ArticleSubmitPayload,
  ReviewEditDto,
  ReviewEditProgressDto,
} from '../types/api'

export function fetchArticle(slotId: string): Promise<ArticleDto> {
  return apiFetch<ArticleDto>(`/articles/${slotId}`)
}

export function saveArticle(slotId: string, payload: ArticleSavePayload): Promise<ArticleDto> {
  return apiFetch<ArticleDto>(`/articles/${slotId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export function rewriteArticle(
  slotId: string,
  payload: ArticleSavePayload,
): Promise<{ ok: boolean; writing: boolean; slotId: string }> {
  return apiFetch(`/articles/${slotId}/rewrite`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function reeditArticle(
  slotId: string,
  payload: ArticleSavePayload,
): Promise<{ ok: boolean; headline: string; content: string }> {
  return apiFetch(`/articles/${slotId}/reedit`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function softenClaim(
  slotId: string,
  payload: { claim: string; content: string },
): Promise<{ ok: boolean; content: string }> {
  return apiFetch(`/articles/${slotId}/soften-claim`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function substantiateClaim(
  slotId: string,
  payload: {
    claim: string
    content: string
    sourceTitle?: string
    sourceUrl?: string
    sourceSummary?: string
  },
): Promise<{ ok: boolean; content: string; replacement?: string; original?: string }> {
  return apiFetch(`/articles/${slotId}/substantiate-claim`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export type ClaimEvidenceResult = {
  title: string
  source: string
  url: string
  published: string
  quote: string
  reliability: string
  stance: string
}

export function searchClaimEvidence(
  slotId: string,
  claim: string,
): Promise<{ ok: boolean; results: ClaimEvidenceResult[] }> {
  return apiFetch(`/articles/${slotId}/claim-evidence`, {
    method: 'POST',
    body: JSON.stringify({ claim }),
  })
}

export function acceptClaimEvidence(
  slotId: string,
  result: ClaimEvidenceResult,
): Promise<{ ok: boolean; item: unknown }> {
  return apiFetch(`/articles/${slotId}/claim-evidence`, {
    method: 'POST',
    body: JSON.stringify({ action: 'accept', ...result }),
  })
}

export function confirmGeenBronMerkBewering(
  slotId: string,
  payload: { citaat: string; bronType: string; bronWaarde: string },
): Promise<{ ok: boolean; selfReview: unknown }> {
  return apiFetch(`/articles/${slotId}/self-review/confirm-geen-bron`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

/**
 * Rewrite vervangt de artikel-rij (DELETE + INSERT in CE-03) — content blijft dus
 * niet-leeg tijdens het herschrijven. Poll daarom op een gewijzigd article.id.
 */
export async function waitForRewrittenArticle(
  slotId: string,
  previousArticleId: string | null,
  options: { intervalMs?: number; timeoutMs?: number } = {},
): Promise<ArticleDto | null> {
  const intervalMs = options.intervalMs ?? 3000
  const timeoutMs = options.timeoutMs ?? 180000
  const deadline = Date.now() + timeoutMs

  while (Date.now() < deadline) {
    await new Promise((resolve) => setTimeout(resolve, intervalMs))
    try {
      const fresh = await fetchArticle(slotId)
      if (fresh.id !== previousArticleId) {
        return fresh
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) {
        // Oude rij net verwijderd, nieuwe nog niet ingevoegd — blijf pollen.
        continue
      }
      throw err
    }
  }

  return null
}

export function submitArticle(slotId: string, payload: ArticleSubmitPayload): Promise<ArticleDto> {
  return apiFetch<ArticleDto>(`/articles/${slotId}/submit`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function patchFeedback(
  slotId: string,
  feedbackId: string,
  status: 'okay' | 'deleted' | 'open',
): Promise<NonNullable<ArticleDto['feedback']>[number]> {
  return apiFetch(`/articles/${slotId}/feedback/${feedbackId}`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
}

export function patchReviewEdit(
  slotId: string,
  editId: string,
  status: 'accepted' | 'rejected',
  content?: string,
): Promise<{ edit: ReviewEditDto; content: string; reviewEdits: ReviewEditDto[] }> {
  return apiFetch(`/articles/${slotId}/review-edits/${editId}`, {
    method: 'PATCH',
    body: JSON.stringify({ status, ...(content !== undefined ? { content } : {}) }),
  })
}

export function patchReviewEditProgress(
  slotId: string,
  editId: string,
  progress: ReviewEditProgressDto,
): Promise<{ edit: ReviewEditDto; reviewEdits: ReviewEditDto[] }> {
  return apiFetch(`/articles/${slotId}/review-edits/${editId}`, {
    method: 'PATCH',
    body: JSON.stringify({ progress }),
  })
}

export function publishWordPress(slotId: string): Promise<{
  ok: boolean
  postId?: string
  postUrl?: string
  status: string
  destinations?: Array<{
    kind: string
    label?: string
    ok?: boolean
    pending?: boolean
    message?: string | null
    postUrl?: string | null
  }>
}> {
  return apiFetch(`/articles/${slotId}/publish-wp`, { method: 'POST' })
}

export function formatPublishDestinationsNotice(result: {
  postId?: string
  postUrl?: string
  destinations?: Array<{ kind: string; ok?: boolean; postUrl?: string | null; message?: string | null; label?: string }>
}): string {
  const parts: string[] = []
  if (result.postUrl) {
    parts.push(`Live op de blog: ${result.postUrl}`)
  } else if (result.postId) {
    const websiteLabel =
      result.destinations?.find((dest) => dest.kind === 'website')?.label ?? 'Website'
    parts.push(
      String(result.postId).startsWith('static:')
        ? `Statische pagina aangemaakt (${result.postId}).`
        : websiteLabel.includes('Joomla')
          ? `Joomla artikel #${result.postId} aangemaakt.`
          : `WordPress post #${result.postId} aangemaakt.`,
    )
  }
  for (const dest of result.destinations ?? []) {
    if (dest.kind === 'website') {
      if (dest.message) {
        parts.push(dest.message)
      }
      continue
    }
    if (dest.ok) {
      parts.push(
        dest.postUrl
          ? `${dest.label ?? dest.kind}: gepubliceerd — ${dest.postUrl}`
          : `${dest.label ?? dest.kind}: gepubliceerd.`,
      )
    } else if (dest.message) {
      parts.push(dest.label ? `${dest.label}: ${dest.message}` : dest.message)
    }
  }
  if (parts.length === 0) {
    return 'Geen bevestiging van publicatie — controleer Integrations (WordPress, Joomla of FTP) of probeer opnieuw.'
  }
  return parts.join(' · ')
}

export async function deleteArticleImage(
  slotId: string,
  variant: 'square' | 'landscape',
): Promise<{
  ok: boolean
  variant: string
  imageUrlSquare: string | null
  imageUrlLandscape: string | null
}> {
  return apiFetch(`/articles/${slotId}/image?variant=${encodeURIComponent(variant)}`, {
    method: 'DELETE',
  })
}

export async function attachGalleryImageToArticle(
  slotId: string,
  variant: 'square' | 'landscape',
  galleryImageId: string,
): Promise<{
  ok: boolean
  variant: string
  imageUrlSquare: string | null
  imageUrlLandscape: string | null
}> {
  const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'
  const form = new FormData()
  form.append('variant', variant)
  form.append('galleryImageId', galleryImageId)
  const response = await fetch(`${API_BASE}/articles/${slotId}/image`, {
    method: 'POST',
    credentials: 'include',
    body: form,
  })
  if (!response.ok) {
    let message = `Gallery-koppeling mislukt (${response.status})`
    try {
      const body = (await response.json()) as { error?: string }
      if (body.error) message = body.error
    } catch {
      // ignore
    }
    throw new Error(message)
  }
  return response.json() as Promise<{
    ok: boolean
    variant: string
    imageUrlSquare: string | null
    imageUrlLandscape: string | null
  }>
}

export async function uploadArticleImage(
  slotId: string,
  variant: 'square' | 'landscape',
  file: File,
): Promise<{
  ok: boolean
  variant: string
  imageUrlSquare: string | null
  imageUrlLandscape: string | null
}> {
  const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'
  const form = new FormData()
  form.append('image', file)
  form.append('variant', variant)
  const response = await fetch(`${API_BASE}/articles/${slotId}/image`, {
    method: 'POST',
    credentials: 'include',
    body: form,
  })
  if (!response.ok) {
    let message = `Upload mislukt (${response.status})`
    try {
      const body = (await response.json()) as { error?: string }
      if (body.error) message = body.error
    } catch {
      // ignore
    }
    throw new Error(message)
  }
  return response.json() as Promise<{
    ok: boolean
    variant: string
    imageUrlSquare: string | null
    imageUrlLandscape: string | null
  }>
}

export function patchArticleStatus(
  slotId: string,
  status: 'published' | 'approved' | 'draft' | 'pending' | 'feedback',
): Promise<{ ok: boolean; status: string }> {
  return apiFetch(`/articles/${slotId}/status`, {
    method: 'POST',
    body: JSON.stringify({ status }),
  })
}

export function markPublicationPlaced(
  articleId: string,
  platform = 'manual',
  extra?: { externalUrl?: string },
): Promise<{ ok: boolean; publication: import('../types/api').PublicationDto }> {
  return apiFetch(`/articles/${articleId}/publications`, {
    method: 'POST',
    body: JSON.stringify({
      action: 'geplaatst',
      platform,
      externalUrl: extra?.externalUrl ?? null,
    }),
  })
}
