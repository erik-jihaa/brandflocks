import { apiFetch } from './client'
import type { ReviewDecisionPayload, ReviewDto } from '../types/review'

export function fetchReview(token: string): Promise<ReviewDto> {
  return apiFetch<ReviewDto>(`/review/${token}`)
}

export function submitReviewDecision(
  token: string,
  payload: ReviewDecisionPayload,
): Promise<ReviewDto> {
  return apiFetch<ReviewDto>(`/review/${token}`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}
