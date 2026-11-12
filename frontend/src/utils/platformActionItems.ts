import {
  PLATFORM_ACTION_ITEMS_ANCHOR,
  platformSignalAction,
  platformSignalLabel,
  type PlatformEvent,
  type PlatformIntegrationIssue,
  type PlatformTenantLatestError,
  type PlatformTenantRow,
} from '../api/platform'
import type { IntegrationStatusEntry } from '../api/integrations'
import { integrationDef } from '../config/integrations'

export type PlatformActionItem = {
  clientId: string
  company: string
  label: string
  /** Korte foutmelding of context (optioneel). */
  detail?: string
  action: string
  tone: 'error' | 'warn'
}

export function platformTenantActionHref(clientId: string): string {
  return `/platform/tenant/${clientId}#${PLATFORM_ACTION_ITEMS_ANCHOR}`
}

function truncateText(value: string, max = 160): string {
  const text = value.trim()
  if (text.length <= max) return text
  return `${text.slice(0, max - 1)}…`
}

function eventIntegrationKey(event: string): string | null {
  const hay = event.toLowerCase()
  if (hay.includes('mailbox')) return 'mailbox'
  if (hay.includes('scraper') || hay.includes('apify')) return 'apify_scraper'
  if (
    hay.includes('wordpress') ||
    hay.includes('joomla') ||
    hay.includes('cms') ||
    hay.includes('publish') ||
    hay.includes('ftp')
  ) {
    return 'website_cms'
  }
  if (hay.includes('analytics') || hay.includes('ga4')) return 'ga4'
  if (hay.includes('calendar')) return 'calendar'
  if (hay.includes('linkedin')) return 'linkedin_publish'
  return null
}

function resolveLatestError(
  tenant: PlatformTenantRow,
  events: PlatformEvent[],
): PlatformTenantLatestError | null {
  if (tenant.latestError?.event || tenant.latestError?.message) {
    return tenant.latestError
  }

  for (const event of events) {
    const clientId = (event.clientId ?? event.client_id ?? '').trim()
    if (clientId !== tenant.clientId || event.severity !== 'error') continue
    return {
      event: event.event ?? '',
      message: event.message ?? '',
      suggestion: event.suggestion ?? '',
      createdAt: event.createdAt ?? event.created_at,
    }
  }

  return null
}

function integrationIsConnected(
  tenant: PlatformTenantRow,
  key: string,
  integrations?: Record<string, IntegrationStatusEntry> | null,
): boolean {
  const live = integrations?.[key]?.status
  if (live === 'connected') return true
  if (key === 'mailbox' && tenant.mailboxSourcesOk) return true
  return false
}

function shouldSuppressError(
  tenant: PlatformTenantRow,
  latest: PlatformTenantLatestError,
  integrations?: Record<string, IntegrationStatusEntry> | null,
): boolean {
  const key = eventIntegrationKey(latest.event)
  if (!key) return false
  return integrationIsConnected(tenant, key, integrations)
}

function integrationActionFromEntry(
  tenant: PlatformTenantRow,
  key: string,
  entry: Pick<IntegrationStatusEntry, 'context' | 'note'>,
  name?: string,
): PlatformActionItem {
  const label = name ?? integrationDef(key)?.name ?? key
  const note = entry.note?.trim() ?? ''
  return {
    clientId: tenant.clientId,
    company: tenant.company,
    label: `${label} — Actie nodig`,
    detail: note ? truncateText(note) : entry.context.trim() || undefined,
    action:
      note ||
      (entry.context.trim()
        ? `Controleer ${entry.context.trim()} in Integrations/Sources.`
        : 'Controleer Integrations en los de koppeling op.'),
    tone: 'error',
  }
}

function integrationActionFromIssue(
  tenant: PlatformTenantRow,
  issue: PlatformIntegrationIssue,
): PlatformActionItem {
  return integrationActionFromEntry(
    tenant,
    issue.key,
    { context: issue.context, note: issue.note },
    issue.label,
  )
}

function liveIntegrationActionItems(
  tenant: PlatformTenantRow,
  integrations?: Record<string, IntegrationStatusEntry> | null,
): PlatformActionItem[] {
  if (!integrations) return []

  const items: PlatformActionItem[] = []
  for (const [key, entry] of Object.entries(integrations)) {
    if (entry.status !== 'action_required') continue
    items.push(integrationActionFromEntry(tenant, key, entry))
  }
  return items
}

function overviewIntegrationActionItems(tenant: PlatformTenantRow): PlatformActionItem[] {
  return (tenant.integrationIssues ?? []).map((issue) => integrationActionFromIssue(tenant, issue))
}

function integrationActionItems(
  tenant: PlatformTenantRow,
  integrations?: Record<string, IntegrationStatusEntry> | null,
): PlatformActionItem[] {
  const live = liveIntegrationActionItems(tenant, integrations)
  if (live.length > 0) return live
  return overviewIntegrationActionItems(tenant)
}

function integrationKeysWithAction(
  tenant: PlatformTenantRow,
  integrations?: Record<string, IntegrationStatusEntry> | null,
): Set<string> {
  const keys = new Set<string>()
  if (integrations) {
    for (const [key, entry] of Object.entries(integrations)) {
      if (entry.status === 'action_required') keys.add(key)
    }
  }
  for (const issue of tenant.integrationIssues ?? []) {
    keys.add(issue.key)
  }
  return keys
}

function errorActionItem(
  tenant: PlatformTenantRow,
  events: PlatformEvent[],
  integrations?: Record<string, IntegrationStatusEntry> | null,
): PlatformActionItem | null {
  if (tenant.errors7d <= 0) return null

  const latest = resolveLatestError(tenant, events)
  if (!latest?.event && !latest?.message) return null
  if (latest && shouldSuppressError(tenant, latest, integrations)) return null

  const integrationKey = latest?.event ? eventIntegrationKey(latest.event) : null
  if (integrationKey && integrationKeysWithAction(tenant, integrations).has(integrationKey)) {
    return null
  }

  const count = tenant.errors7d
  const eventName = latest?.event?.trim()

  const label =
    count === 1
      ? eventName
        ? `${eventName} — technische fout (7 dagen)`
        : '1 technische fout (7 dagen)'
      : eventName
        ? `${eventName} — ${count} technische fouten (7 dagen)`
        : `${count} technische fouten (7 dagen)`

  const detail = latest?.message?.trim() ? truncateText(latest.message) : undefined
  const suggestion = latest?.suggestion?.trim()

  let action: string
  if (suggestion) {
    action = suggestion
  } else if (detail) {
    action = `Controleer: ${detail}`
  } else {
    action = 'Open Actiepunten op de tenant-pagina en bekijk het logboek voor details.'
  }

  return {
    clientId: tenant.clientId,
    company: tenant.company,
    label,
    detail,
    action,
    tone: 'error',
  }
}

export function collectPlatformActionItems(
  tenants: PlatformTenantRow[],
  events: PlatformEvent[] = [],
  integrationsByClientId?: Record<string, Record<string, IntegrationStatusEntry>>,
): PlatformActionItem[] {
  const items: PlatformActionItem[] = []

  for (const tenant of tenants) {
    const integrations = integrationsByClientId?.[tenant.clientId] ?? null

    if ((tenant.supportEmailTicketCount ?? 0) > 0) {
      items.push({
        clientId: tenant.clientId,
        company: tenant.company,
        label:
          tenant.supportEmailTicketCount === 1
            ? '1 support-mail wacht op behandeling'
            : `${tenant.supportEmailTicketCount} support-mails wachten op behandeling`,
        action:
          'Importeer indien nodig via Settings → importeer support-mails. Antwoord via Service → Antwoorden per e-mail.',
        tone: 'error',
      })
    } else if ((tenant.openTicketCount ?? 0) > 0) {
      items.push({
        clientId: tenant.clientId,
        company: tenant.company,
        label:
          tenant.openTicketCount === 1
            ? '1 open service-ticket'
            : `${tenant.openTicketCount} open service-tickets`,
        action: 'Behandel via Service → Antwoorden per e-mail.',
        tone: 'warn',
      })
    }

    for (const item of integrationActionItems(tenant, integrations)) {
      items.push(item)
    }

    for (const signal of tenant.pipelineSignals) {
      items.push({
        clientId: tenant.clientId,
        company: tenant.company,
        label: platformSignalLabel(signal),
        action: platformSignalAction(signal),
        tone: signal === 'stuck_draft_slots' ? 'error' : 'warn',
      })
    }

    const errorItem = errorActionItem(tenant, events, integrations)
    if (errorItem) {
      items.push(errorItem)
    }
  }

  return items
}

export function collectTenantActionItems(
  tenant: PlatformTenantRow,
  events: PlatformEvent[] = [],
  integrations?: Record<string, IntegrationStatusEntry> | null,
): PlatformActionItem[] {
  const map = integrations ? { [tenant.clientId]: integrations } : undefined
  return collectPlatformActionItems([tenant], events, map)
}
