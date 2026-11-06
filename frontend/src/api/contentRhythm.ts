import { apiFetch } from './client'

export type ContentRhythmRole = {
  role_name: string
  cadence: string
  prompt_template_id: string
}

export type ContentRhythm = {
  id: string
  postsPerWeek: number
  roles: ContentRhythmRole[]
  active: boolean
  proposed: boolean
  createdAt: string
}

export function fetchContentRhythm(): Promise<{ ok: boolean; rhythm: ContentRhythm }> {
  return apiFetch('/content-rhythm')
}

export function saveContentRhythm(body: {
  postsPerWeek?: number
  roles?: ContentRhythmRole[]
}): Promise<{ ok: boolean; rhythm: ContentRhythm }> {
  return apiFetch('/content-rhythm', { method: 'PATCH', body: JSON.stringify(body) })
}
