import { apiFetch } from './client'
import type { AgendaDto } from '../types/api'

export function fetchAgenda(): Promise<AgendaDto> {
  return apiFetch<AgendaDto>('/agenda')
}

export function deleteAgendaSlot(slotId: string): Promise<{ ok: boolean }> {
  return apiFetch(`/agenda/${slotId}/delete`, { method: 'POST' })
}

export function archiveAgendaSlot(slotId: string): Promise<{ ok: boolean }> {
  return apiFetch(`/agenda/${slotId}/archive`, { method: 'POST' })
}

export function scheduleAgendaSlot(
  slotId: string,
  payload: { publishDate: string; publishTime?: string | null; platform?: string | null },
): Promise<{ ok: boolean; slotId: string; publishDate: string; publishTime: string | null; platform?: string | null }> {
  return apiFetch(`/agenda/${slotId}/schedule`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}
