import type { PublishMode } from '../api/account'
import type { BrandNarrativeCompleteness } from './onboarding'

export type HbaScoreDimensionsDto = {
  archetypeFit: number | null
  doelFit: number | null
  tegenkrachtFit: number | null
  spanning: number | null
  mechanismeProof: number | null
  distinctiviteit: number | null
}

export type HbaScoreDto = {
  id: string
  articleId: string
  brandNarrativeId: string
  status: string
  contentrol: string | null
  contentrolMotivation: string | null
  brandContribution: number | null
  brandContributionDimensions?: HbaScoreDimensionsDto
  brandConsistency: number | null
  brandConsistencyNotes?: string | null
  architectureRisk: number | null
  /** Presentatie: 10 − architectureRisk. Ruwe risicowaarde blijft architectureRisk. */
  architectureIntegrity?: number | null
  architectureRiskFlags: string[]
  longTermValue?: number | null
  shortTermValue?: number | null
  diagnose: string | null
  verbeteradvies: string | null
  kernformuleringHit?: number | null
  needsReview?: boolean
  scoredAt?: string
  modelUsed?: string
  promptVersion?: string
  hoofdoordeel?: string | null
  hoofdoordeelKlantlabel?: string | null
  hoofdoordeelKlantzin?: string | null
  hoofdoordeelGecorrigeerd?: string | null
}

export type FeedbackItemDto = {
  id: string
  type: 'text' | 'image'
  name: string
  comment: string
  actionLabel: string | null
  excerpt: string | null
  status: 'open' | 'okay' | 'deleted'
}

export type ReviewEditProgressDto = {
  statuses: Record<string, 'pending' | 'accepted' | 'rejected'>
  draftContent?: string
}

export type ReviewEditDto = {
  id: string
  articleId: string
  slotId: string
  originalText: string
  suggestedText: string
  status: 'pending' | 'accepted' | 'rejected'
  progress?: ReviewEditProgressDto | null
}

export type PublicationDto = {
  id: string
  articleId: string
  platform: string
  status: string
  scheduledAt?: string | null
  publishedAt?: string | null
  externalId?: string | null
  externalUrl?: string | null
  errorMessage?: string | null
  attempts?: number
}

export type ArticleDto = {
  id: string
  slotId: string
  weekLabel: string
  /** Campagneslot zonder artikeltekst — toon thema-startscherm i.p.v. lege editor. */
  themeOnly?: boolean
  themeId?: string
  campaignId?: string | null
  campaignTitle?: string | null
  campaignFaseLabel?: string | null
  publishDate?: string | null
  slotOrigin?: string | null
  copyModus?: string | null
  copyModusOverride?: boolean
  copyModusLabel?: string | null
  headline: string
  headlineAi?: string
  content: string
  contentAi: string
  hashtags: string
  rationale: string
  sourceUrl: string | null
  tone: string
  angle: string
  editor?: string | null
  imageRatio: string
  platform: string
  /** Kanaaltekstvorm (DB-sleutel: post, artikel, promotie, …) */
  contentTextvorm?: string
  toneSuggested?: string
  toneOptions?: string[]
  editorSuggested?: string | null
  editorOptions?: string[]
  angleSuggested?: string
  imageRatioSuggested?: string
  platformSuggested?: string
  hashtagsSuggested?: string
  /** Gekozen objective-id (final → suggested → slot/theme) */
  objective?: string | null
  objectiveSuggested?: string | null
  objectiveOptions?: Array<{ id: string; label: string; percentage: number }>
  imageUrlSquare?: string | null
  imageUrlLandscape?: string | null
  imageSource?: string | null
  /** Beeldvoorstel (handmatige publish-modes) — onder artikeltekst */
  imageAdvice?: string | null
  publishMode?: PublishMode
  status: string
  slotStatus: string | null
  feedback?: FeedbackItemDto[]
  reviewEdits?: ReviewEditDto[]
  reviewUrl?: string | null
  reviewerEmail?: string | null
  mailSent?: boolean
  mailWarning?: string | null
  aiReviewWarning?: string | null
  /** Harde kanaalgrenzen (tekens, hashtags) — addendum §3/§7. */
  channelHardIssues?: string[]
  /** Lichte kanaalcheck (reminders + zachte signalen) — addendum §3.2 */
  channelSoftHints?: string[]
  wordpressPostId?: string | null
  publications?: PublicationDto[]
  reviewMode?: ReviewMode | 'hba' | 'none'
  /** CE-03 loopt nog of wordt hervat — poll tot body binnen is */
  writingPending?: boolean
  /** PATCH/rewrite heeft CE-03 zojuist gestart */
  writing?: boolean
  hbaScore?: HbaScoreDto | null
  hbaHint?: import('../api/hbaV2').HbaArticleHint | null
  hbaContentrolOverride?: string | null
  /** A8: tenant-sessie — geen HBA-contentrol in UI/API. */
  hideHbaContentrol?: boolean
  brandNarrativeCompleteness?: BrandNarrativeCompleteness | null
  hbaScoreDisplayMode?: 'decimal' | 'band'
  positioningArchetype?: string | null
  positioningBrandGoal?: string | null
  positioningNarrativeVersion?: number
  selfReview?: {
    weakestClaim: string
    unsupportedAssumption: string
    missedCounterpoint: string
    regelbreuk?: { regel: string; citaat: string } | null
    themeConflict?: boolean
    themeConflictResolution?: string
    themeConflictWhy?: string
    themePremisse?: boolean
    themePremisseWat?: string
    themePremisseActie?: string
    merkBeweringen?: { citaat: string; bron: 'merkarchitectuur' | 'bewijsveld' | 'research' | 'geen_bron' }[]
    ce03SelfReviewError?: string
  } | null
  /** Zelf gekozen CTA — niet afgeleid uit de tekst. null = nog niet gekozen. */
  hasCta?: boolean | null
  ctaText?: string
}

export type ArticleSavePayload = {
  headline: string
  content?: string
  hashtags: string
  tone: string
  angle: string
  editor?: string | null
  imageRatio: string
  platform: string
  contentTextvorm?: string
  objective?: string | null
  hbaContentrolOverride?: string | null
  hasCta?: boolean | null
  ctaText?: string
}

export type ReviewMode = 'human' | 'ai'

export type ArticleSubmitPayload = ArticleSavePayload & {
  reviewMode: ReviewMode
}

export type CalendarPhase = 'theme' | 'research' | 'article' | 'publish' | 'published' | 'failed'

export type AgendaRowDto = {
  slotId: string
  articleId: string | null
  title: string
  articleStatus: string | null
  slotStatus: string | null
  status?: string | null
  phaseLabel?: string | null
  phase?: 'theme' | 'research' | 'article' | null
  editor?: string | null
  editorFirstName?: string | null
  slotOrigin?: string | null
  fullDeleteOnRemove?: boolean
  rhythmRole?: string | null
  rhythmRoleLabel?: string | null
  publishDate?: string | null
  publishTime?: string | null
  campaignId?: string | null
  campaignTitle?: string | null
  campaignFaseKey?: string | null
  campaignFaseLabel?: string | null
  campaignOpdracht?: string | null
  articleWritten?: boolean
  platform?: string | null
  calendarPhase?: CalendarPhase | null
  publications?: PublicationDto[]
  publicationStatus?: string | null
}

export type AgendaGroupDto = {
  week: string
  weekNumber?: number | null
  weekRange?: string | null
  cycleWeek?: string | null
  themeOnly?: boolean
  rows: AgendaRowDto[]
}

export type AgendaPortfolioSignalDto = {
  id: string
  message: string
  actionLabel: string
  actionHref: string
  priority?: number
}

export type AgendaDto = {
  todayLabel?: string
  currentWeek?: number
  postsPerWeek?: number
  planningHorizonWeeks?: number
  horizonMondays?: string[]
  portfolioSignals?: AgendaPortfolioSignalDto[]
  groups: AgendaGroupDto[]
}
