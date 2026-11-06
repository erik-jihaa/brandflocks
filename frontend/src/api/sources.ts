import { apiFetch, ApiError } from './client'
import type {
  SourceDto,
  SourceFolderDto,
  SourceTab,
  CreateSourcePayload,
  UpdateSourcePayload,
  SourceInspirationSuggestion,
  MailboxNewsletterItem,
} from '../types/sources'

export function fetchSources(category: SourceTab): Promise<{ sources: SourceDto[] }> {
  return apiFetch(`/sources?category=${encodeURIComponent(category)}`)
}

export function fetchAllSources(): Promise<{ sources: SourceDto[]; folders?: SourceFolderDto[] }> {
  return apiFetch('/sources?category=all')
}

export function fetchSourceFolders(): Promise<{ folders: SourceFolderDto[] }> {
  return apiFetch('/source-folders')
}

export function createSourceFolder(
  name: string,
  objectiveId?: string | null,
): Promise<SourceFolderDto> {
  return apiFetch('/source-folders', {
    method: 'POST',
    body: JSON.stringify({
      name,
      ...(objectiveId ? { objectiveId } : {}),
    }),
  })
}

export function updateSourceFolder(
  folderId: string,
  payload: { name?: string; sortOrder?: number; objectiveId?: string | null },
): Promise<SourceFolderDto> {
  return apiFetch(`/source-folders/${folderId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export function deleteSourceFolder(folderId: string): Promise<{ ok: boolean }> {
  return apiFetch(`/source-folders/${folderId}`, { method: 'DELETE' })
}

export function fetchSource(sourceId: string): Promise<SourceDto> {
  return apiFetch(`/sources/${sourceId}`)
}

export function createSource(payload: CreateSourcePayload): Promise<SourceDto> {
  return apiFetch('/sources', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function deleteSource(sourceId: string): Promise<{ ok: boolean }> {
  try {
    return await apiFetch(`/sources/${sourceId}`, { method: 'DELETE' })
  } catch (err) {
    if (err instanceof ApiError && err.status === 405) {
      return apiFetch(`/sources/${sourceId}`, {
        method: 'POST',
        body: JSON.stringify({ action: 'delete' }),
      })
    }
    throw err
  }
}

export function checkSource(
  sourceId: string,
  payload?: { mailboxPasswordRef?: string },
): Promise<SourceDto> {
  const controller = new AbortController()
  const timeoutMs = 90_000
  const timer = window.setTimeout(() => controller.abort(), timeoutMs)

  return apiFetch<SourceDto>(`/sources/${sourceId}/check`, {
    method: 'POST',
    body: payload ? JSON.stringify(payload) : undefined,
    signal: controller.signal,
  })
    .catch((err) => {
      if (err instanceof DOMException && err.name === 'AbortError') {
        throw new ApiError(
          'Mailbox-check duurde te lang — probeer opnieuw of vraag support om de server-fix te deployen.',
          408,
        )
      }
      throw err
    })
    .finally(() => window.clearTimeout(timer))
}

export function fetchTrelloBoards(): Promise<{
  configured: boolean
  boards: Array<{ id: string; name: string; url: string }>
  note?: string
}> {
  return apiFetch('/sources/trello/boards')
}

export function updateSource(sourceId: string, payload: UpdateSourcePayload): Promise<SourceDto> {
  return apiFetch(`/sources/${sourceId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export function fetchAnalyticsOAuthStatus(): Promise<{
  configured: boolean
  note?: string
  redirectUri?: string
}> {
  return apiFetch('/sources/analytics/oauth/status')
}

export function startAnalyticsOAuth(
  sourceId: string,
  returnTo: 'sources' | 'integrations' = 'sources',
): Promise<{
  configured: boolean
  authUrl?: string
  note?: string
}> {
  return apiFetch(
    `/sources/analytics/oauth/start?source_id=${encodeURIComponent(sourceId)}&return_to=${encodeURIComponent(returnTo)}`,
  )
}

export function fetchCalendarOAuthStatus(): Promise<{
  configured: boolean
  note?: string
  redirectUri?: string
}> {
  return apiFetch('/sources/calendar/oauth/status')
}

export function startCalendarOAuth(
  sourceId: string,
  returnTo: 'sources' | 'integrations' = 'sources',
): Promise<{
  configured: boolean
  authUrl?: string
  note?: string
}> {
  return apiFetch(
    `/sources/calendar/oauth/start?source_id=${encodeURIComponent(sourceId)}&return_to=${encodeURIComponent(returnTo)}`,
  )
}

export function fetchCalendarList(sourceId: string): Promise<{
  configured: boolean
  calendars: Array<{ id: string; name: string; primary: boolean }>
  note?: string | null
}> {
  return apiFetch(`/sources/calendar/list?source_id=${encodeURIComponent(sourceId)}`)
}

export function updateSourceBudget(
  sourceId: string,
  budgetPerUnit: number,
  maxUnitsPerRun?: number | null,
): Promise<SourceDto> {
  return updateSource(sourceId, { budgetPerUnit, maxUnitsPerRun })
}

export function discoverAsanaProjects(
  asanaPatRef: string,
  sourceId?: string,
): Promise<{
  ok: boolean
  projects: Array<{
    gid: string
    name: string
    workspaceGid: string
    workspaceName: string
  }>
}> {
  return apiFetch('/sources/asana/discover', {
    method: 'POST',
    body: JSON.stringify({ asanaPatRef, sourceId }),
  })
}

export async function uploadSourceDocument(
  sourceId: string,
  file: File,
): Promise<{ ok: boolean; message?: string; itemCount?: number; source: SourceDto }> {
  const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'
  const form = new FormData()
  form.append('document', file)
  const response = await fetch(`${API_BASE}/sources/${encodeURIComponent(sourceId)}/document-upload`, {
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
    throw new ApiError(message, response.status)
  }
  return response.json() as Promise<{
    ok: boolean
    message?: string
    itemCount?: number
    source: SourceDto
  }>
}

export function fetchDriveOAuthStatus(): Promise<{
  configured: boolean
  note?: string
  redirectUri?: string
}> {
  return apiFetch('/sources/drive/oauth/status')
}

export function startDriveOAuth(
  sourceId: string,
  returnTo: 'sources' | 'integrations' = 'sources',
): Promise<{
  configured: boolean
  authUrl?: string
  note?: string
}> {
  return apiFetch(
    `/sources/drive/oauth/start?source_id=${encodeURIComponent(sourceId)}&return_to=${encodeURIComponent(returnTo)}`,
  )
}

export function fetchSourceInspiration(
  searchScope = 50,
  claimContext?: string,
): Promise<{
  ok: boolean
  suggestions: SourceInspirationSuggestion[]
  searchScope?: number
  droppedProbeFailed?: number
}> {
  return apiFetch('/sources/inspiration', {
    method: 'POST',
    body: JSON.stringify(
      claimContext?.trim()
        ? { claimContext: claimContext.trim() }
        : { searchScope },
    ),
  })
}

export function fetchMailboxNewsletters(sourceId: string): Promise<{ items: MailboxNewsletterItem[] }> {
  return apiFetch(`/sources/${sourceId}/newsletters`)
}

export function updateMailboxNewsletter(
  sourceId: string,
  itemId: string,
  payload: { subject?: string; bodyText?: string },
): Promise<{ item: MailboxNewsletterItem }> {
  return apiFetch(`/sources/${sourceId}/newsletters/${itemId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}

export function deleteMailboxNewsletter(
  sourceId: string,
  itemId: string,
): Promise<{ ok: boolean; id: string }> {
  return apiFetch(`/sources/${sourceId}/newsletters/${itemId}`, { method: 'DELETE' })
}
