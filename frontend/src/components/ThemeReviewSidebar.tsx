import { ReviewPanel } from './ReviewPanel'

export type ThemeReviewFeedback = 'liked' | 'deleted' | 'none'

type ThemeReviewSidebarProps = {
  feedback: ThemeReviewFeedback
  busy?: boolean
  onFeedbackChange: (feedback: 'liked' | 'deleted') => void
}

export function normalizeThemeFeedback(feedback: string): ThemeReviewFeedback {
  if (feedback === 'liked' || feedback === 'like') return 'liked'
  if (feedback === 'deleted' || feedback === 'forgotten' || feedback === 'delete') return 'deleted'
  return 'none'
}

/** briefing/theme.png — Review in meta-kolom */
export function ThemeReviewSidebar({ feedback, busy = false, onFeedbackChange }: ThemeReviewSidebarProps) {
  return (
    <ReviewPanel
      name="theme-review"
      primaryLabel="Like this theme"
      secondaryLabel="Delete this theme"
      primaryChecked={feedback === 'liked'}
      secondaryChecked={feedback === 'deleted'}
      busy={busy}
      onPrimary={() => onFeedbackChange('liked')}
      onSecondary={() => onFeedbackChange('deleted')}
    />
  )
}
