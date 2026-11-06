import { apiFetch } from './client'

export type IntegrationStatus = 'connected' | 'action_required' | 'not_set' | 'unavailable'

export type IntegrationStatusEntry = {
  status: IntegrationStatus
  label: string
  context: string
  note: string | null
  since: string | null
  meta: Record<string, unknown>
}

export type IntegrationsStatusResponse = {
  ok: boolean
  integrations: Record<string, IntegrationStatusEntry>
  summary: {
    active: number
    actionRequired: number
    notSet: number
  }
  publishMode: string
}

export function fetchIntegrationsStatus(): Promise<IntegrationsStatusResponse> {
  return apiFetch('/integrations/status')
}

export function disconnectGoogleOAuth(): Promise<{ ok: boolean; clearedSources: number }> {
  return apiFetch('/integrations/google/disconnect', { method: 'POST', body: '{}' })
}
