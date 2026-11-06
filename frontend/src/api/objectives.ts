import { apiFetch } from './client'
import type { ObjectiveDraft, ObjectivesPayload } from '../types/objectives'

export function fetchObjectives(): Promise<ObjectivesPayload> {
  return apiFetch('/objectives')
}

export function saveObjectives(objectives: ObjectiveDraft[]): Promise<ObjectivesPayload> {
  return apiFetch('/objectives', {
    method: 'PUT',
    body: JSON.stringify({ objectives }),
  })
}

export function deleteObjective(objectiveId: string): Promise<{ ok: boolean }> {
  return apiFetch(`/objectives/${objectiveId}`, { method: 'DELETE' })
}
