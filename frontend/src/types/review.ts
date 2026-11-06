export type ReviewDto = {
  articleId: string
  slotId: string
  weekLabel: string
  headline: string
  content: string
  contentBaseline: string
  hashtags?: string
  rationale: string
  tone: string
  angle: string
  platform: string
  imageRatio?: string
  imageUrlSquare?: string | null
  imageUrlLandscape?: string | null
  imageUrl?: string | null
  status: string
  canDecide: boolean
  decision?: 'approved' | 'feedback'
  editCount?: number
}

export type ReviewDecisionPayload = {
  decision: 'approved' | 'feedback'
  headline: string
  content: string
}
