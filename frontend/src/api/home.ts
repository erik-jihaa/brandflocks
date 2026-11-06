import { apiFetch } from './client'

export type HomeTodo = {
  id: string
  label: string
  reason: string
  path: string
  /** true = blauwe stip (blokkeert de pijplijn); false = beige stip, kan later */
  blocking: boolean
}

export function emptyHomeDashboard(weekLabel = ''): HomeDashboard {
  return {
    companyName: '',
    weekLabel,
    stats: {
      sources: 0,
      themesProposed: 0,
      agendaSlots: 0,
      pendingReview: 0,
      feedbackOpen: 0,
      draftArticles: 0,
      approvedArticles: 0,
    },
    nextAction: null,
    todos: [],
    returnSummary: null,
    overviewSignal: null,
    pendingConnections: [],
    result: {
      strategyTrend: {
        currentPct: null,
        trendDirection: 'stable',
        weeklyData: [],
        hasEnoughData: false,
        weeksWithData: 0,
        publishedPostsCount: 0,
        minWeeksRequired: 3,
      },
      engagementTrend: {
        totalEngagement: 0,
        weeklyData: [],
        hasEnoughData: false,
        weeksSpan: 0,
        breakdown: {
          articles: { views: 0 },
          linkedIn: { reactions: 0, likes: 0, comments: 0 },
        },
        lastWeekTotal: 0,
      },
      findability: {
        score: null,
        geoScore: null,
        seoScore: null,
        hasQuestions: false,
        hasMeasurement: false,
        checkedAt: null,
        trendData: [],
        trendLabels: [],
        hasEnoughData: false,
        previousScore: null,
        trendDirection: 'stable',
      },
      postsPerWeek: 0,
      publishedThisWeek: 0,
      publishedThisMonth: 0,
      angleMix: null,
      contentrolMix: null,
      weekLabel,
      websiteViewsThisWeek: null,
      websiteSource: null,
      hasAnalytics: false,
      linkedInEngagement: {
        hasSource: false,
        thisWeek: { reactions: 0, likes: 0, comments: 0, reposts: 0, posts: 0 },
        last90Days: { reactions: 0, likes: 0, comments: 0, reposts: 0, posts: 0 },
      },
      sourcesReview: { summary: '', likedResearch: 0, total: 0, expandTips: [] },
      engagement: { views: 0, likes: 0, comments: 0, postsTracked: 0, postsMissing: 0 },
      latestReport: null,
    },
    nextArticle: null,
  }
}

export type HomeAngleMix = {
  archetype: number
  adversary: number
  general: number
}

export type HomeContentrolMix = Record<string, number>

export type HomeLinkedInEngagementTotals = {
  reactions: number
  likes: number
  comments: number
  reposts: number
  posts: number
}

export type HomeLinkedInEngagement = {
  hasSource: boolean
  thisWeek: HomeLinkedInEngagementTotals
  last90Days: HomeLinkedInEngagementTotals
}

export type HomeSourcesExpandTip = {
  id: string
  label: string
  reason: string
}

export type HomeSourcesReview = {
  summary: string
  likedResearch: number
  total: number
  expandTips: HomeSourcesExpandTip[]
}

export type HomeStrategyTrend = {
  currentPct: number | null
  trendDirection: 'up' | 'down' | 'stable'
  weeklyData: number[]
  hasEnoughData: boolean
  weeksWithData: number
  publishedPostsCount: number
  minWeeksRequired: number
  metricKind?: 'angle' | 'brand_contribution'
}

export type HomeEngagementBreakdown = {
  /** Echte GA4-paginaweergaven op gepubliceerde artikelen (aug 2026 — geen LinkedIn-mix meer) */
  articles: { views: number }
  linkedIn: { reactions: number; likes: number; comments: number }
}

export type HomeEngagementTrend = {
  totalEngagement: number
  weeklyData: number[]
  hasEnoughData: boolean
  weeksSpan: number
  breakdown: HomeEngagementBreakdown
  lastWeekTotal: number
}

export type HomeFindabilityTrend = {
  score: number | null
  geoScore: number | null
  seoScore: number | null
  hasQuestions: boolean
  hasMeasurement: boolean
  checkedAt: string | null
  trendData: number[]
  trendLabels: string[]
  hasEnoughData: boolean
  previousScore: number | null
  trendDirection: 'up' | 'down' | 'stable'
}

export type HomeResult = {
  strategyTrend: HomeStrategyTrend
  engagementTrend: HomeEngagementTrend
  findability: HomeFindabilityTrend
  postsPerWeek: number
  publishedThisWeek: number
  publishedThisMonth: number
  angleMix: HomeAngleMix | null
  contentrolMix: HomeContentrolMix | null
  weekLabel: string
  websiteViewsThisWeek: number | null
  websiteSource: 'ga4' | null
  hasAnalytics: boolean
  linkedInEngagement: HomeLinkedInEngagement
  sourcesReview: HomeSourcesReview
  engagement: {
    views: number
    likes: number
    comments: number
    postsTracked: number
    postsMissing: number
  }
  latestReport: {
    periodStart: string
    periodEnd: string
    pctOnStrategy: number | null
    executionRate: number | null
    engagementDiffPct: number | null
    narrativePreview: string
  } | null
}

export type HomeReturnSummary = {
  message: string
  daysAway: number
  sinceDate: string
  reportPath: string
}

/** §1.2 — één signaal; portfoliosignaal wint op PBS-erosion (backend). */
export type HomeOverviewSignal = {
  source: 'portfolio' | 'erosion'
  message: string
  actionLabel: string
  actionPath: string
}

export type HomePendingConnection = {
  id: string
  label: string
  path: string
}

export type HomeDashboard = {
  companyName: string
  weekLabel: string
  stats: {
    sources: number
    themesProposed: number
    agendaSlots: number
    pendingReview: number
    feedbackOpen: number
    draftArticles: number
    approvedArticles: number
  }
  nextAction: { label: string; path: string; reason: string } | null
  todos: HomeTodo[]
  /** Alleen aanwezig na >7 dagen zonder login (notitie 15 §1.1). */
  returnSummary?: HomeReturnSummary | null
  overviewSignal?: HomeOverviewSignal | null
  /** §1.6 — leeg zodra alles gekoppeld is. */
  pendingConnections?: HomePendingConnection[]
  result: HomeResult
  nextArticle: { slotId: string; title: string; status: string } | null
}

export function fetchHomeDashboard(): Promise<HomeDashboard> {
  return apiFetch('/home')
}

/** In-memory cache — Overview laadt 1× per browsersessie (tot logout of refresh). */
let homeDashboardCache: HomeDashboard | null = null
let homeDashboardInflight: Promise<HomeDashboard> | null = null

export function getHomeDashboardCache(): HomeDashboard | null {
  return homeDashboardCache
}

export function clearHomeDashboardCache(): void {
  homeDashboardCache = null
  homeDashboardInflight = null
}

/** Gedeelde fetch: hergebruikt cache en dedupe parallelle requests. `force` = altijd opnieuw laden. */
export function fetchHomeDashboardCached(options?: { force?: boolean }): Promise<HomeDashboard> {
  if (!options?.force && homeDashboardCache) {
    return Promise.resolve(homeDashboardCache)
  }
  if (homeDashboardInflight) {
    return homeDashboardInflight
  }

  homeDashboardInflight = fetchHomeDashboard()
    .then((dashboard) => {
      homeDashboardCache = dashboard
      return dashboard
    })
    .finally(() => {
      homeDashboardInflight = null
    })

  return homeDashboardInflight
}

export type SupportInfo = {
  email: string
  docsUrl: string
  humanReviewConfigured: boolean
}

export function fetchSupportInfo(): Promise<SupportInfo> {
  return apiFetch('/support')
}
