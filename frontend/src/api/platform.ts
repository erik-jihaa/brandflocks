import { apiFetch } from './client'
import type { IntegrationStatusEntry } from './integrations'

export type PlatformUser = {
  id: string
  email: string
  name: string
  role: 'admin' | 'support'
  active: boolean
}

export type PlatformCmsPlatform = {
  type: string
  label: string
  configured: boolean
  siteUrl: string
}

export type PlatformCmsDiagnostics = {
  configured: boolean
  siteUrl: string
  primaryType: string | null
  primaryLabel: string | null
  platforms: PlatformCmsPlatform[]
}

export type PlatformTenantDiagnostics = {
  cms: PlatformCmsDiagnostics
  cmsConfigured: boolean
  cmsSiteUrl: string
  cmsPrimaryType: string | null
  cmsPrimaryLabel: string | null
  wordpressConfigured: boolean
  wordpressSiteUrl: string
  hasWordpressAppPassword: boolean
  linkedinConnected: boolean
  linkedinConfigured: boolean
  linkedinTokenValid: boolean
  linkedinMemberName: string | null
  activeSourcesCount: number
  galleryImageCount: number
  styleGuideReady: boolean
  objectivesCount: number
}

export type PlatformCmsDiagnose = {
  ok: boolean
  healthy: boolean
  cmsType: string
  cmsLabel: string
  clientId: string
  authError: string | null
  authenticatedAs: { id?: number; slug?: string; name?: string } | null
  resolvedPostAuthorId: number | null
  tenantWordPressConfigured: boolean
  usingPlatformFallback: boolean
  linkedinConnected: boolean
  linkedinMemberName: string | null
}

/** @deprecated use PlatformCmsDiagnose */
export type PlatformWpDiagnose = PlatformCmsDiagnose

export type PlatformServiceChatMessage = {
  id: number
  clientId: string
  question: string
  createdAt: string
}

export type PlatformServiceTicket = {
  id: string
  clientId: string
  subject: string
  status: 'open' | 'in_progress' | 'closed'
  note: string
  senderEmail?: string
  source?: string
  createdByPlatformUserId: string
  createdAt: string
  updatedAt: string
}

export type PlatformServiceSummary = {
  chatMessages: PlatformServiceChatMessage[]
  tickets: PlatformServiceTicket[]
}

export type PlatformTenantDetail = {
  ok: boolean
  tenant: PlatformTenantRow | null
  account: Record<string, unknown>
  diagnostics: PlatformTenantDiagnostics
  recentEvents: PlatformEvent[]
  service: PlatformServiceSummary
}

export const PLATFORM_ACTION_ITEMS_ANCHOR = 'actiepunten'

export type PlatformTenantLatestError = {
  event: string
  message: string
  suggestion: string
  createdAt?: string
}

export type PlatformIntegrationIssue = {
  key: string
  label: string
  context: string
  note: string | null
}

export type PlatformTenantRow = {
  clientId: string
  company: string
  contactEmail: string
  publishMode: string
  active: boolean
  status: 'green' | 'orange' | 'red'
  errors7d: number
  latestError?: PlatformTenantLatestError | null
  articlesThisMonth: number
  expectedArticlesMonth: number
  galleryImageCount: number
  cmsConfigured: boolean
  cmsSiteUrl: string
  cmsPrimaryType: string | null
  cmsPrimaryLabel: string | null
  wordpressConfigured: boolean
  wordpressSiteUrl: string
  costEurThisMonth: number | null
  n8nExecutionsThisMonth: number | null
  pipelineSignals: string[]
  openTicketCount: number
  supportEmailTicketCount: number
  lastLogin?: string | null
  lastPublish?: string | null
  daysSinceLastLogin?: number | null
  daysSinceLastPublish?: number | null
  integrationIssues?: PlatformIntegrationIssue[]
  mailboxSourcesOk?: boolean
}

export type PlatformEvent = {
  id?: number
  clientId?: string | null
  client_id?: string | null
  severity: string
  category: string
  event: string
  message: string
  environment?: string
  createdAt?: string
  created_at?: string
  context?: Record<string, unknown> | null
  suggestion?: string
}

export type PlatformCostBreakdownLine = {
  id: string
  label: string
  eur: number
}

export type PlatformCostTenantUsage = {
  articlesWritten: number
  articlesPublished: number
  scraperRuns: number
  apifyCostEur: number
  apifyUnits?: number
  n8nExecutions: number
}

export type PlatformCostTenantRow = {
  clientId: string
  company: string
  totalEur: number | null
  lines?: PlatformCostBreakdownLine[]
  usage?: PlatformCostTenantUsage | null
}

export type PlatformCostsTrend = {
  monthsSpan: number
  monthlyData: number[]
  monthLabels: string[]
  monthlyTotals?: number[]
  totalCost: number
  hasEnoughData: boolean
  lastMonthTotal: number
}

export type PlatformCostsResponse = {
  ok: boolean
  month: string
  grandTotalEur: number
  totalN8nExecutions?: number
  previousMonth?: string | null
  previousGrandTotalEur?: number
  breakdown?: PlatformCostBreakdownLine[]
  tenants: PlatformCostTenantRow[]
  trend?: PlatformCostsTrend
}

export type PlatformSupportEmailImportSkipped = {
  reason: string
  messageId?: string
  sender?: string
  subject?: string
  detail?: string
}

export type PlatformSupportEmailImportResult = {
  ok: boolean
  importedCount: number
  skippedCount: number
  imported: { ticketId: string; clientId: string; subject: string; senderEmail: string }[]
  skipped: PlatformSupportEmailImportSkipped[]
  skippedSummary?: Record<string, number>
}

export function platformSupportImportSkipLabel(reason: string): string {
  switch (reason) {
    case 'duplicate':
      return 'Al geïmporteerd'
    case 'self':
      return 'Eigen support-adres'
    case 'no_tenant':
      return 'Geen tenant-match'
    case 'no_sender':
      return 'Geen afzender'
    default:
      return reason
  }
}

export function fetchPlatformSession(): Promise<{
  ok: boolean
  authenticated: boolean
  user: PlatformUser | null
}> {
  return apiFetch('/platform/auth/session')
}

export function platformLogin(body: { email: string; password: string }): Promise<{
  ok: boolean
  user: PlatformUser
}> {
  return apiFetch('/platform/auth/login', { method: 'POST', body: JSON.stringify(body) })
}

export function platformLogout(): Promise<{ ok: boolean }> {
  return apiFetch('/platform/auth/logout', { method: 'POST' })
}

export function requestPlatformPasswordReset(email: string): Promise<{ ok: true; message: string }> {
  return apiFetch('/platform/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email: email.trim() }),
  })
}

export function validatePlatformPasswordResetToken(token: string): Promise<{
  ok: true
  name: string
  platformUserId: string
}> {
  return apiFetch(`/platform/auth/reset-password?token=${encodeURIComponent(token)}`)
}

export function completePlatformPasswordReset(body: {
  token: string
  password: string
  passwordRetype: string
}): Promise<{ ok: true; authenticated?: boolean; user?: PlatformUser }> {
  return apiFetch('/platform/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function fetchPlatformTenants(): Promise<{ ok: boolean; tenants: PlatformTenantRow[] }> {
  return apiFetch('/platform/tenants')
}

export type CreatePlatformTenantBody = {
  company: string
  contactEmail: string
  password?: string
  postsPerWeek?: number
  contactName?: string
  /** Review-mails en login voor meekijken (bijv. erik@igisnext.com) */
  reviewerEmail?: string
  contactRole?: 'contact' | 'reviewer' | 'both'
}

export function createPlatformTenant(body: CreatePlatformTenantBody): Promise<{
  ok: boolean
  client: { clientId: string; company: string; contactEmail: string }
}> {
  return apiFetch('/platform/tenants', { method: 'POST', body: JSON.stringify(body) })
}

export function deletePlatformTenant(
  clientId: string,
  body: { confirmCompanyName: string; confirmEnvironment?: string },
): Promise<{ ok: boolean; clientId: string; company: string }> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/delete`, {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function fetchPlatformTenant(clientId: string): Promise<PlatformTenantDetail> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}`)
}

export function fetchPlatformCmsDiagnose(clientId: string): Promise<PlatformCmsDiagnose> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/cms-diagnose`)
}

export function fetchPlatformIntegrationsStatus(clientId: string): Promise<{
  ok: boolean
  integrations: Record<string, IntegrationStatusEntry>
  summary: { active: number; actionRequired: number; notSet: number }
  publishMode: string
}> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/integrations/status`)
}

/** @deprecated use fetchPlatformCmsDiagnose */
export function fetchPlatformWpDiagnose(clientId: string): Promise<PlatformCmsDiagnose> {
  return fetchPlatformCmsDiagnose(clientId)
}

export function runPlatformThemeRefill(
  clientId: string,
  body: { weeks?: number[]; force?: boolean } = {},
): Promise<{ ok: boolean; weeks: number[]; ce01?: { ok?: boolean } }> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/theme-refill`, {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function runPlatformMailboxSync(clientId: string): Promise<{ ok: boolean; results: unknown[] }> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/mailbox-sync`, {
    method: 'POST',
  })
}

export function runPlatformScraperSync(clientId: string): Promise<{ ok: boolean; results: unknown[] }> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/scraper-sync`, {
    method: 'POST',
  })
}

export function fetchPlatformEvents(params?: {
  severity?: string
  days?: number
  clientId?: string
  limit?: number
}): Promise<{ ok: boolean; count: number; events: PlatformEvent[] }> {
  const q = new URLSearchParams()
  if (params?.severity) q.set('severity', params.severity)
  if (params?.days != null) q.set('days', String(params.days))
  if (params?.clientId) q.set('client_id', params.clientId)
  if (params?.limit != null) q.set('limit', String(params.limit))
  const suffix = q.toString() ? `?${q.toString()}` : ''
  return apiFetch(`/platform/events${suffix}`)
}

export function fetchPlatformStatus(): Promise<{
  ok: boolean
  environment: string
  errors24h: number
  tenantCount: number
  lastMigration: { filename: string; applied_at: string } | null
  health: { ok: boolean; db?: string }
}> {
  return apiFetch('/platform/status')
}

export function platformSignalLabel(signal: string): string {
  switch (signal) {
    case 'stuck_draft_slots':
      return 'Rolling Agenda — vastgelopen draft-slots'
    case 'pending_zonder_review_mail':
      return 'Pending zonder review-mail'
    default:
      return signal
  }
}

export function platformSignalAction(signal: string): string {
  switch (signal) {
    case 'stuck_draft_slots':
      return 'Open Planning/Agenda, controleer lege slots en genereer ontbrekende drafts opnieuw.'
    case 'pending_zonder_review_mail':
      return 'Stuur review-mail naar de tegenlezer of keur handmatig goed in Agenda.'
    default:
      return 'Controleer rolling agenda en tenant-configuratie.'
  }
}

export function ticketMessageText(note: string, subject?: string): string {
  const raw = note.trim()
  if (raw === '') {
    return subject?.trim() ?? ''
  }
  const withoutReplies = raw.split(/\n---\s/)[0]?.trim() ?? raw
  return withoutReplies
}

export function platformTicketSuggestion(ticket: {
  status: PlatformServiceTicket['status']
  source?: string
}): string | null {
  if (ticket.status === 'closed') {
    return null
  }
  if (ticket.source === 'email_import_escalation') {
    return 'Behandel chat-escalatie: antwoord de klant via Antwoorden per e-mail.'
  }
  if (ticket.source === 'email_import') {
    return 'Beantwoord de support-mail via Antwoorden per e-mail.'
  }
  if (ticket.status === 'open') {
    return 'Neem contact op en zet status op In behandeling.'
  }
  if (ticket.status === 'in_progress') {
    return 'Stuur een vervolgantwoord of markeer als afgerond.'
  }
  return null
}

export function fetchPlatformCosts(month?: string): Promise<PlatformCostsResponse> {
  const q = month ? `?month=${encodeURIComponent(month)}` : ''
  return apiFetch(`/platform/costs${q}`)
}

export function importPlatformSupportEmails(): Promise<PlatformSupportEmailImportResult> {
  return apiFetch('/platform/support/import-emails', { method: 'POST' })
}

export function platformTicketSourceLabel(source: string | undefined): string {
  switch (source) {
    case 'email_import':
      return 'E-mail'
    case 'email_import_escalation':
      return 'Chat-escalatie'
    case 'manual':
      return 'Handmatig'
    default:
      return source && source !== '' ? source : 'Handmatig'
  }
}

export function createPlatformServiceTicket(
  clientId: string,
  body: { subject: string; note?: string; status?: PlatformServiceTicket['status'] },
): Promise<{ ok: boolean; ticket: PlatformServiceTicket }> {
  return apiFetch(`/platform/tenants/${encodeURIComponent(clientId)}/service/tickets`, {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function updatePlatformServiceTicket(
  clientId: string,
  ticketId: string,
  body: Partial<Pick<PlatformServiceTicket, 'subject' | 'note' | 'status'>>,
): Promise<{ ok: boolean; ticket: PlatformServiceTicket }> {
  return apiFetch(
    `/platform/tenants/${encodeURIComponent(clientId)}/service/tickets/${encodeURIComponent(ticketId)}`,
    { method: 'PATCH', body: JSON.stringify(body) },
  )
}

export function replyPlatformServiceTicket(
  clientId: string,
  ticketId: string,
  body: { message: string; markClosed?: boolean },
): Promise<{ ok: boolean; ticket: PlatformServiceTicket; sentTo: string }> {
  return apiFetch(
    `/platform/tenants/${encodeURIComponent(clientId)}/service/tickets/${encodeURIComponent(ticketId)}/reply`,
    { method: 'POST', body: JSON.stringify(body) },
  )
}

export function redactPlatformServiceMessage(
  clientId: string,
  messageId: number,
): Promise<{ ok: boolean }> {
  return apiFetch(
    `/platform/tenants/${encodeURIComponent(clientId)}/service/messages/${messageId}`,
    { method: 'DELETE' },
  )
}

export function platformTicketStatusLabel(status: PlatformServiceTicket['status']): string {
  switch (status) {
    case 'open':
      return 'Open'
    case 'in_progress':
      return 'In behandeling'
    case 'closed':
      return 'Afgerond'
    default:
      return status
  }
}

export type PlatformHygieneFactor = {
  id: string
  phrase: string
  kind: 'observed' | 'category_condition' | string
  reviewStatus: 'pending' | 'approved' | 'rejected' | string
  competitorCount: number | null
  sources: Array<{ url?: string; competitor?: string }>
  addedBy: string
}

export type PlatformHygieneDetail = {
  contextprofiel: string
  active: { listId: string; version: number } | null
  draft: { listId: string; version: number } | null
  workingListId: string | null
  competitors: Array<{ id: string; name: string; baseUrl: string }>
  factors: PlatformHygieneFactor[]
  status: {
    activeVersion: number | null
    factorCount: number
  }
}

export function fetchPlatformHygiene(contextprofiel = 'b2b_complex_sale'): Promise<{ ok: boolean; detail: PlatformHygieneDetail }> {
  return apiFetch(`/platform/hygiene?contextprofiel=${encodeURIComponent(contextprofiel)}`)
}

export function createPlatformHygieneDraft(contextprofiel: string): Promise<{ ok: boolean; listId: string }> {
  return apiFetch('/platform/hygiene/lists', {
    method: 'POST',
    body: JSON.stringify({ contextprofiel }),
  })
}

export function savePlatformHygieneCompetitors(
  listId: string,
  competitors: Array<{ name: string; baseUrl: string }>,
): Promise<{ ok: boolean }> {
  return apiFetch(`/platform/hygiene/lists/${encodeURIComponent(listId)}/competitors`, {
    method: 'POST',
    body: JSON.stringify({ competitors }),
  })
}

export function scrapePlatformHygieneList(listId: string): Promise<{ ok: boolean }> {
  return apiFetch(`/platform/hygiene/lists/${encodeURIComponent(listId)}/scrape`, { method: 'POST' })
}

export function distillPlatformHygieneList(listId: string): Promise<{ ok: boolean; inserted?: number }> {
  return apiFetch(`/platform/hygiene/lists/${encodeURIComponent(listId)}/distill`, { method: 'POST' })
}

export function updatePlatformHygieneFactor(
  factorId: string,
  patch: { phrase?: string; reviewStatus?: string },
): Promise<{ ok: boolean }> {
  return apiFetch(`/platform/hygiene/factors/${encodeURIComponent(factorId)}`, {
    method: 'PATCH',
    body: JSON.stringify(patch),
  })
}

export function addPlatformHygieneCategoryFactor(listId: string, phrase: string): Promise<{ ok: boolean }> {
  return apiFetch(`/platform/hygiene/lists/${encodeURIComponent(listId)}/category-factors`, {
    method: 'POST',
    body: JSON.stringify({ phrase }),
  })
}

export function activatePlatformHygieneList(listId: string): Promise<{ ok: boolean }> {
  return apiFetch(`/platform/hygiene/lists/${encodeURIComponent(listId)}/activate`, { method: 'POST' })
}

export type PlatformDerivedBrancheRow = {
  typedLabel: string
  hitCount: number
  lastSeenAt: string
  brancheCode: string | null
}

export function fetchPlatformDerivedBranches(): Promise<{ ok: boolean; items: PlatformDerivedBrancheRow[] }> {
  return apiFetch('/platform/derived-branches')
}

export async function downloadPlatformDerivedBranchesCsv(): Promise<void> {
  const apiBase = import.meta.env.VITE_API_BASE ?? '/api'
  const response = await fetch(`${apiBase}/platform/derived-branches?format=csv`, {
    credentials: 'include',
    headers: { Accept: 'text/csv' },
  })
  if (!response.ok) {
    throw new Error('CSV-export mislukt')
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'branchelijst-afgeleid.csv'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
