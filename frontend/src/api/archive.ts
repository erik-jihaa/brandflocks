import { apiFetch } from './client'

export type PublishLinkDto = {
  kind: 'blog' | 'linkedin' | 'x'
  label: string
  url: string
}

export type ArchiveItemDto = {
  slotId: string
  articleId: string | null
  weekLabel: string
  title: string
  publishedAt: string | null
  socialPostId: string | null
  socialPostUrl: string | null
  publishLinks?: PublishLinkDto[]
  promoBudget: number | null
  archiveNote: string | null
  interactionViews: number | null
  interactionLikes: number | null
  interactionComments: number | null
  isArchived?: boolean
}

export type ArchiveReportDto = {
  reportId: string
  title: string
  narrativePreview: string
  generatedAt: string
  pdfUrl: string
}

export type ArchiveDetailDto = ArchiveItemDto & {
  id: string
  headline: string
  content: string
  hashtags: string
  rationale: string
  sourceUrl: string | null
  tone: string
  angle: string
  imageRatio: string
  platform: string
  imageUrlSquare?: string | null
  imageUrlLandscape?: string | null
  reports: ArchiveReportDto[]
  hasArticle?: boolean
  hasCta?: boolean | null
  ctaText?: string
}

export type ArchiveDraftPayload = {
  publishedAt?: string | null
  socialPostUrl?: string | null
  promoBudget?: number | null
  archiveNote?: string | null
}

export function fetchArchive(query?: string): Promise<{ items: ArchiveItemDto[]; reports: ArchiveReportDto[] }> {
  const q = query?.trim()
  const suffix = q ? `?q=${encodeURIComponent(q)}` : ''
  return apiFetch(`/archive${suffix}`)
}

export function fetchArchiveDetail(slotId: string): Promise<ArchiveDetailDto> {
  return apiFetch(`/archive/${slotId}`)
}

export function patchArchiveItem(
  slotId: string,
  payload: Partial<
    Pick<
      ArchiveItemDto,
      | 'publishedAt'
      | 'socialPostId'
      | 'socialPostUrl'
      | 'promoBudget'
      | 'archiveNote'
      | 'interactionViews'
      | 'interactionLikes'
      | 'interactionComments'
    >
  >,
): Promise<ArchiveItemDto> {
  return apiFetch(`/archive/${slotId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export function checkArchivePostUrl(url: string): Promise<{
  ok: boolean
  status: 'green' | 'blocked' | 'red' | 'invalid'
  httpCode?: number
  note?: string
}> {
  return apiFetch('/archive/check-url', {
    method: 'POST',
    body: JSON.stringify({ url }),
  })
}

export function submitArchiveFromAgenda(
  slotId: string,
  payload: ArchiveDraftPayload,
): Promise<{ ok: boolean; slotId: string }> {
  return apiFetch(`/agenda/${slotId}/archive`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}
