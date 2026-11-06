import { apiFetch } from './client'
import type { LlmVisibilityReport } from './llmQuestions'

export type ReportWeeklyViews = {
  weekLabel: string
  weekNumber: number
  isoYear: number
  startDate: string
  endDate: string
  websiteViews: number | null
  sessions?: number | null
  activeUsers?: number | null
  websiteSource: 'ga4' | null
}

export type ReportWebsiteAnalyticsTotals = {
  activeUsers: number
  newUsers: number
  sessions: number
  screenPageViews: number
}

export type ReportWebsiteAnalytics = {
  hasAnalytics: boolean
  totals: ReportWebsiteAnalyticsTotals
  topPages: Array<{ pageTitle: string; views: number }>
  sourceMedium: Array<{ source: string; medium: string; activeUsers: number; label: string }>
  weekly: ReportWeeklyViews[]
}

export type ReportLinkedInEngagementTotals = {
  reactions: number
  likes: number
  comments: number
  reposts: number
  posts: number
}

export type ReportLinkedInWeeklyEngagement = ReportLinkedInEngagementTotals & {
  weekLabel: string
  weekNumber: number
  isoYear: number
  startDate: string
  endDate: string
}

export type ReportLinkedInEngagement = {
  hasSource: boolean
  totals: ReportLinkedInEngagementTotals
  weekly: ReportLinkedInWeeklyEngagement[]
}

export type ReportFindabilitySnapshot = {
  geo: LlmVisibilityReport
  seo: LlmVisibilityReport
}

export type ReportDto = {
  id: string
  title: string
  periodStart: string
  periodEnd: string
  scope: 'general' | 'article' | 'findability'
  scopeArticleId?: string | null
  pctOnStrategy?: number | null
  pctGeneral?: number | null
  toneDistribution?: Record<string, number> | null
  angleDistribution?: Record<string, number> | null
  executionRate?: number | null
  engagementOnStrategy?: number | null
  engagementGeneral?: number | null
  engagementDiffPct?: number | null
  articleCountStrategy?: number | null
  articleCountGeneral?: number | null
  articleCountTotal?: number | null
  medianOnStrategy?: number | null
  medianGeneral?: number | null
  outlierPosts?: Array<Record<string, unknown>>
  bestPerformingPost?: Record<string, unknown> | null
  dailyBreakdown?: Array<Record<string, unknown>>
  narrative?: string
  generatedAt?: string
  findabilitySnapshot?: ReportFindabilitySnapshot | null
  weeklyViews?: ReportWeeklyViews[]
  hasAnalytics?: boolean
  websiteAnalytics?: ReportWebsiteAnalytics
  linkedInEngagement?: ReportLinkedInEngagement
  /** Hoofddoel-categorie van de klant (product-vision.md §9) — bepaalt KPI-highlighting */
  brandGoalCategory?: string
}

export function reportPdfUrl(reportId: string): string {
  const base = import.meta.env.VITE_API_BASE ?? '/api'
  return `${base}/reports/${reportId}/pdf`
}

export type ReportTrendPoint = {
  reportId: string
  monthLabel: string
  periodStart: string
  engagementDiffPct: number | null
}

export function fetchReports(): Promise<{ reports: ReportDto[]; trend: ReportTrendPoint[] }> {
  return apiFetch('/reports')
}

export function fetchReport(reportId: string): Promise<{ report: ReportDto }> {
  return apiFetch(`/reports/${reportId}`)
}

export function fetchReportArticleOptions(): Promise<{
  articles: Array<{ articleId: string; title: string }>
}> {
  return apiFetch('/reports/articles')
}

export type ReportScope = 'brand' | 'article' | 'findability'

export function generateReport(body: {
  periodStart: string
  periodEnd: string
  scope: ReportScope
  scopeArticleId?: string
  title: string
}): Promise<{ report: ReportDto }> {
  return apiFetch('/reports', { method: 'POST', body: JSON.stringify(body) })
}
