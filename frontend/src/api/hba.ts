import { apiFetch } from './client'
import type { HbaScoreDto } from '../types/api'

export type HbaScoreResponse = {
  ok: boolean
  status: string
  cached?: boolean
  message?: string
  missingOrInconsistent?: string[]
  score: HbaScoreDto | null
}

export function scoreHbaArticle(articleId: string, options?: { force?: boolean }): Promise<HbaScoreResponse> {
  return apiFetch<HbaScoreResponse>('/hba/score', {
    method: 'POST',
    body: JSON.stringify({
      article_id: articleId,
      force: options?.force ?? false,
    }),
  })
}
