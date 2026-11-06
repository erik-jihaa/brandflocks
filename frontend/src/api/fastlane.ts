import { apiFetch } from './client'

export type FastlaneCreateResponse = {
  ok: boolean
  themeId: string
  slotId: string
  articleId?: string | null
  title: string
  phase?: 'research' | 'write'
  researchUrlCount: number
  hasExtraThoughts: boolean
  webhookTriggered: boolean
}

export type FastlaneCreatePayload = {
  title: string
  researchUrls: string[]
  extraThoughts?: string
  tone?: string
  angle?: string
  editor?: string
  hook?: string
  imageRatio?: string
  platform?: string
  rationale?: string
  objective?: string
  format?: string
  tags?: string[]
  scheduledWeek?: number
  scheduledDate?: string
  origin?: string
  notes?: string
  /** Direct schrijven: sla research over en start CE-03 meteen. */
  writeImmediately?: boolean
}

export function createFastlaneArticle(body: FastlaneCreatePayload): Promise<FastlaneCreateResponse> {
  return apiFetch('/fastlane', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}
