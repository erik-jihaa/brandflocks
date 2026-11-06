import { apiFetch } from './client'
import { clearHomeDashboardCache } from './home'

export type ContactRole = 'contact' | 'reviewer' | 'both'

export type PublishMode = 'manual_with_review' | 'manual_without_review'

export type WebsiteCms = 'wordpress' | 'joomla' | 'static' | ''

export const WEBSITE_CMS_OPTIONS: { value: Exclude<WebsiteCms, ''>; label: string }[] = [
  { value: 'wordpress', label: 'WordPress' },
  { value: 'joomla', label: 'Joomla' },
  { value: 'static', label: 'Statische HTML (FTP)' },
]

export function websiteCmsLabel(cms: WebsiteCms): string {
  return WEBSITE_CMS_OPTIONS.find((opt) => opt.value === cms)?.label ?? ''
}

export function websiteCmsFromLabel(label: string): WebsiteCms {
  return WEBSITE_CMS_OPTIONS.find((opt) => opt.label === label)?.value ?? ''
}

export type PublishExtraDestination = {
  id: string
  kind: 'linkedin' | 'magazine'
  label: string
  enabled: boolean
  magazineUrl?: string
  organizationUrn?: string
}

export type AccountDto = {
  clientId: string
  company: string
  contactName: string
  contactRole: ContactRole
  contactEmail: string
  reviewerEmail: string
  logoUrl: string
  publishMode: PublishMode
  imageDatabaseUrl: string
  hasImageDatabasePassword: boolean
  active: boolean
  planningHorizonWeeks: number | null
  postsPerWeek: number | null
  sourceChecksPerWeek: number | null
  writingStyle?: string
  googlePlaceId?: string
  hasPassword: boolean
  websiteCms: WebsiteCms
  websiteBlogUrl: string
  wordpressSiteUrl: string
  wordpressUser: string
  hasWordpressAppPassword: boolean
  wordpressAuthorId: number | null
  siteFtpHost: string
  siteFtpPort: number
  siteFtpUser: string
  hasSiteFtpPassword: boolean
  siteFtpRemotePath: string
  joomlaSiteUrl: string
  joomlaCategoryId: number | null
  hasJoomlaApiToken: boolean
  publishExtraDestinations: PublishExtraDestination[]
}

export type ClientSearchHit = {
  clientId: string
  company: string
  contactEmail: string
  publishMode?: string
}

export function clientPickLabel(client: ClientSearchHit): string {
  const mode = client.publishMode?.trim()
  if (mode === 'manual_with_review') return `${client.company} — met review`
  if (mode === 'manual_without_review') return `${client.company} — zonder review`
  return client.company
}

export function fetchAccount(clientId?: string): Promise<{ account: AccountDto | null; error?: string }> {
  const q = clientId ? `?client_id=${encodeURIComponent(clientId)}` : ''
  return apiFetch(`/account${q}`)
}

export function patchAccount(body: Record<string, unknown>): Promise<{ account: AccountDto | null; error?: string }> {
  return apiFetch('/account', { method: 'PATCH', body: JSON.stringify(body) })
}

export function switchAccountClient(
  fromClientId: string,
  switchClientId: string,
): Promise<{ account: AccountDto | null; error?: string }> {
  return patchAccount({ clientId: fromClientId, switchClientId })
}

export function searchClients(query?: string): Promise<{ clients: ClientSearchHit[] }> {
  const q = query ? `?q=${encodeURIComponent(query)}` : ''
  return apiFetch(`/clients${q}`)
}

export function fetchAuthSession(): Promise<{
  authenticated: boolean
  clientId: string | null
  authRequired?: boolean
}> {
  return apiFetch('/auth/session')
}

export function login(body: { email?: string; password: string; clientId?: string }): Promise<
  | { ok: true; account: AccountDto }
  | { ok: false; requiresClientPick: true; clients: ClientSearchHit[] }
> {
  return apiFetch('/auth/login', { method: 'POST', body: JSON.stringify(body) })
}

export function logoutAuth(): Promise<{ ok: boolean }> {
  clearHomeDashboardCache()
  return apiFetch('/auth/logout', { method: 'POST' })
}

export function requestPasswordReset(email: string): Promise<{ ok: true; message: string }> {
  return apiFetch('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email: email.trim() }),
  })
}

export function validatePasswordResetToken(token: string): Promise<{
  ok: true
  company: string
  clientId: string
}> {
  return apiFetch(`/auth/reset-password?token=${encodeURIComponent(token)}`)
}

export function completePasswordReset(body: {
  token: string
  password: string
  passwordRetype: string
}): Promise<{ ok: true; authenticated?: boolean }> {
  return apiFetch('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function fetchLinkedInOAuthStatus(clientId?: string): Promise<{
  configured: boolean
  connected: boolean
  memberName?: string | null
  organizationSocialGranted?: boolean
  oauthScopes?: string | null
  note?: string
}> {
  const q = clientId ? `?client_id=${encodeURIComponent(clientId)}` : ''
  return apiFetch(`/account/linkedin/oauth/status${q}`)
}

export function startLinkedInOAuth(clientId?: string): Promise<{
  configured: boolean
  authUrl?: string
  note?: string
}> {
  const q = clientId ? `?client_id=${encodeURIComponent(clientId)}` : ''
  return apiFetch(`/account/linkedin/oauth/start${q}`)
}

export function disconnectLinkedInOAuth(clientId?: string): Promise<{ ok: boolean }> {
  return apiFetch('/account/linkedin/oauth/disconnect', {
    method: 'POST',
    body: JSON.stringify(clientId ? { clientId } : {}),
  })
}

export type WordPressTestResult = {
  ok: boolean
  connected: boolean
  authError?: string | null
  wpErrorCode?: string | null
  authenticatedAs?: {
    id?: number
    slug?: string
    name?: string
    roles?: string[]
  } | null
  siteUrl?: string
  user?: string
  hasAppPassword?: boolean
  tenantWordPressConfigured?: boolean
}

export function testWordPressConnection(body: {
  wordpressSiteUrl?: string
  wordpressUser?: string
  wordpressAppPassword?: string
}): Promise<WordPressTestResult> {
  return apiFetch('/wordpress/test', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export type JoomlaTestResult = {
  ok: boolean
  connected: boolean
  authError?: string | null
  categoryOk?: boolean
  categoryTitle?: string | null
  siteUrl?: string
  categoryId?: number | null
  hasApiToken?: boolean
  tenantJoomlaConfigured?: boolean
}

export function testJoomlaConnection(body: {
  joomlaSiteUrl?: string
  joomlaCategoryId?: number | null
  joomlaApiToken?: string
}): Promise<JoomlaTestResult> {
  return apiFetch('/joomla/test', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export async function uploadAccountLogo(
  file: File,
  clientId: string,
): Promise<{ ok: boolean; logoUrl: string }> {
  const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'
  const form = new FormData()
  form.append('logo', file)
  form.append('clientId', clientId)
  const response = await fetch(`${API_BASE}/account/logo`, {
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
  return response.json() as Promise<{ ok: boolean; logoUrl: string }>
}
