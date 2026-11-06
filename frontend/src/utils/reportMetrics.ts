import type { ReportDto } from '../api/reports'

type DailyRow = {
  strategy_views?: number
  strategyViews?: number
  strategy_likes?: number
  strategyLikes?: number
  strategy_comments?: number
  strategyComments?: number
  general_views?: number
  generalViews?: number
  general_likes?: number
  generalLikes?: number
  general_comments?: number
  generalComments?: number
}

function pick(row: DailyRow, snake: keyof DailyRow, camel: keyof DailyRow): number {
  const value = row[snake] ?? row[camel]
  return typeof value === 'number' && Number.isFinite(value) ? value : 0
}

export function reportEngagementTotals(report: ReportDto): {
  views: number
  likes: number
  comments: number
  days: number
} {
  const rows = (report.dailyBreakdown ?? []) as DailyRow[]
  let views = 0
  let likes = 0
  let comments = 0

  for (const row of rows) {
    views +=
      pick(row, 'strategy_views', 'strategyViews') + pick(row, 'general_views', 'generalViews')
    likes +=
      pick(row, 'strategy_likes', 'strategyLikes') + pick(row, 'general_likes', 'generalLikes')
    comments +=
      pick(row, 'strategy_comments', 'strategyComments') +
      pick(row, 'general_comments', 'generalComments')
  }

  return { views, likes, comments, days: rows.length || 1 }
}

export function reportStrategyCount(report: ReportDto): number {
  if (report.articleCountStrategy != null) return report.articleCountStrategy
  if (report.pctOnStrategy != null && report.articleCountTotal != null) {
    return Math.round((report.pctOnStrategy / 100) * report.articleCountTotal)
  }
  return 0
}

export function reportGeneralCount(report: ReportDto): number {
  if (report.articleCountGeneral != null) return report.articleCountGeneral
  if (report.pctGeneral != null && report.articleCountTotal != null) {
    return Math.round((report.pctGeneral / 100) * report.articleCountTotal)
  }
  return 0
}

export function formatReportDiffPct(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return '—'
  const sign = value > 0 ? '+' : ''
  return `${sign}${value}% Difference`
}

export type ReportKpiHighlight = { label: string; value: string }

/**
 * Belangrijkste 2-3 KPI's op basis van het hoofddoel (brand_goal_category, product-vision.md §9).
 * Ordent/highlight bestaande metrics — verbergt niets, zie plan §3.
 */
export function reportKpiHighlights(
  report: ReportDto,
  engagementTotals: { views: number; likes: number; comments: number; days: number },
): ReportKpiHighlight[] {
  const category = report.brandGoalCategory ?? ''
  const diff = formatReportDiffPct(report.engagementDiffPct)
  const activeUsers = report.websiteAnalytics?.totals?.activeUsers
  const sessions = report.websiteAnalytics?.totals?.sessions
  const topPage = report.websiteAnalytics?.topPages?.[0]
  const linkedIn = report.linkedInEngagement?.totals
  const bestPost = (report.bestPerformingPost?.headline as string | undefined) ?? undefined

  switch (category) {
    case 'awareness':
      return [
        { label: 'Views (eigen posts)', value: String(engagementTotals.views) },
        { label: 'Active users (GA4)', value: activeUsers != null ? String(activeUsers) : '—' },
        { label: 'Engagement diff', value: diff },
      ]
    case 'leads':
      return [
        { label: 'Sessions (GA4)', value: sessions != null ? String(sessions) : '—' },
        { label: 'Top page', value: topPage ? `${topPage.pageTitle} (${topPage.views})` : '—' },
        { label: 'Engagement on-strategy', value: String(report.engagementOnStrategy ?? 0) },
      ]
    case 'sales':
      return [
        { label: 'Engagement diff', value: diff },
        { label: 'Best performing article', value: bestPost ?? '—' },
      ]
    case 'community':
      return [
        {
          label: 'LinkedIn engagement',
          value: linkedIn ? `${linkedIn.reactions + linkedIn.likes} reacties/likes` : '—',
        },
        { label: 'Comments (totaal)', value: String(engagementTotals.comments) },
      ]
    case 'recruitment':
    case 'service':
    case 'thought_leadership':
    case 'brand_preference':
      return [
        { label: 'Engagement diff', value: diff },
        { label: 'Articles on-strategy', value: String(reportStrategyCount(report)) },
      ]
    default:
      return [
        { label: 'Engagement diff', value: diff },
        { label: 'Views (eigen posts)', value: String(engagementTotals.views) },
      ]
  }
}
