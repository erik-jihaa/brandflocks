import { useCallback, useEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH, LIST_SECTION } from '../layout/grid.constants'
import { PillButton, pillActionsRowClass, designStackClass } from '../components/PillButton'
import { SaveNotice } from '../components/ui/SaveNotice'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { FieldSingle } from '../components/ui/Field'
import { statusRowActionClass } from '../components/ui/ActionLink'
import { Pulldown } from '../components/ui/Pulldown'
import { RadioOption } from '../components/ui/RadioOption'
import { ReportDailyChart } from '../components/reports/ReportDailyChart'
import { ReportContentrolRadar, ReportToneRadar } from '../components/reports/ReportRadarChart'
import { ReportTrendChart } from '../components/reports/ReportTrendChart'
import { ReportFindabilitySection } from '../components/reports/ReportLlmVisibilitySection'
import { FindabilityScoreSummary } from '../components/FindabilityScoreSummary'
import { fetchLlmVisibility, type LlmVisibilityReport, type SearchQuestionKind } from '../api/llmQuestions'
import {
  fetchReport,
  fetchReportArticleOptions,
  fetchReports,
  generateReport,
  reportPdfUrl,
  type ReportLinkedInEngagement,
  type ReportScope,
  type ReportWebsiteAnalytics,
  type ReportDto,
  type ReportTrendPoint,
} from '../api/reports'
import { isApiConfigured } from '../api/client'
import {
  formatReportDiffPct,
  reportEngagementTotals,
  reportGeneralCount,
  reportKpiHighlights,
  reportStrategyCount,
} from '../utils/reportMetrics'
import { brandGoalLabel } from '../utils/brandGoal'
import { findabilityCombinedScore, findabilityKindScore } from '../utils/findabilityScore'
import { ArrowIcon } from '../components/ArrowIcon'
import { fetchHbaProgress, type HbaProgress } from '../api/hbaV2'
import { fetchHomeDashboardCached, type HomeDashboard } from '../api/home'
import { BrandWatcherDetails, BrandWatcherNotices } from '../components/BrandWatcherSection'
import { HomeStrategyTrend } from '../components/HomeStrategyTrend'
import { HomeFindabilityTrend } from '../components/HomeFindabilityTrend'
import { ReportDiagnosisSection } from '../components/reports/ReportDiagnosis'
import { ReportEngagementBreakdown } from '../components/reports/ReportEngagementBreakdown'
import { ReportStrategyVsGeneric } from '../components/reports/ReportStrategyVsGeneric'
import { ReportNotEnoughData } from '../components/reports/ReportNotEnoughData'
import { ReportSourcesExpand } from '../components/reports/ReportSourcesExpand'
import { homeOverviewHasPbsMeters } from '../utils/homeOverviewMeters'
import { HBA_SCORED_POSTS_FOR_OVERVIEW } from '../constants/hbaOverview'
import { reportTitleForKlant } from '../utils/reportTitleKlant'

function defaultPeriodStart(): string {
  const d = new Date()
  d.setMonth(d.getMonth() - 1)
  return d.toISOString().slice(0, 10)
}

function defaultPeriodEnd(): string {
  return new Date().toISOString().slice(0, 10)
}

function defaultReportTitle(scope: ReportScope, periodStart: string, periodEnd: string): string {
  const labels: Record<ReportScope, string> = {
    brand: 'Merk',
    article: 'Artikel',
    findability: 'Vindbaarheid',
  }
  return `${labels[scope]}rapport ${periodStart} – ${periodEnd}`
}

function MetricBlock({ label, value }: { label: string; value: string }) {
  return (
    <div className="min-w-0">
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">{label}</p>
      <p className="m-0 pt-[4px] text-[20px] font-semibold leading-[24px] text-ce-black">{value}</p>
    </div>
  )
}

function MetricPair({ label, total, average }: { label: string; total: number; average: number }) {
  return (
    <div className="grid gap-[12px] sm:grid-cols-2">
      <MetricBlock label={label} value={String(total)} />
      <MetricBlock label={`Gemiddeld ${label.toLowerCase()}`} value={String(average)} />
    </div>
  )
}

function formatCount(value: number | null | undefined, hasAnalytics?: boolean): string {
  if (hasAnalytics) {
    return (value ?? 0).toLocaleString('nl-NL')
  }
  if (value == null) return '—'
  return value.toLocaleString('nl-NL')
}

function ReportWebsiteAnalyticsSection({ data }: { data?: ReportWebsiteAnalytics }) {
  if (!data?.hasAnalytics) return null

  const totals = data.totals ?? {
    activeUsers: 0,
    newUsers: 0,
    sessions: 0,
    screenPageViews: 0,
  }

  return (
    <div className="space-y-[16px]">
      <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
        Website-analytics (GA4)
      </p>

      <div className="grid gap-[12px] sm:grid-cols-2 lg:grid-cols-4">
        <MetricBlock label="Actieve gebruikers" value={formatCount(totals.activeUsers, true)} />
        <MetricBlock label="Nieuwe gebruikers" value={formatCount(totals.newUsers, true)} />
        <MetricBlock label="Sessies" value={formatCount(totals.sessions, true)} />
        <MetricBlock label="Paginaweergaven" value={formatCount(totals.screenPageViews, true)} />
      </div>

      {data.topPages.length > 0 && (
        <div>
          <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
            Top pagina&apos;s
          </p>
          <div className="flex flex-col gap-[8px]">
            {data.topPages.map((row) => (
              <div key={row.pageTitle} className="flex items-baseline justify-between gap-[12px]">
                <p className="m-0 min-w-0 text-[14px] leading-[18px] text-ce-black">{row.pageTitle}</p>
                <p className="m-0 shrink-0 text-[14px] leading-[18px] text-ce-black">
                  {row.views.toLocaleString('nl-NL')} weergaven
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {data.sourceMedium.length > 0 && (
        <div>
          <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
            Actieve gebruikers per bron / medium
          </p>
          <div className="flex flex-col gap-[8px]">
            {data.sourceMedium.map((row) => (
              <div key={row.label} className="flex items-baseline justify-between gap-[12px]">
                <p className="m-0 min-w-0 text-[14px] leading-[18px] text-ce-black">{row.label}</p>
                <p className="m-0 shrink-0 text-[14px] leading-[18px] text-ce-black">
                  {row.activeUsers.toLocaleString('nl-NL')} gebruikers
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {data.weekly.length > 0 && (
        <div>
          <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
            Per week
          </p>
          <div className="flex flex-col gap-[8px]">
            {data.weekly.map((row) => (
              <div key={`${row.isoYear}-${row.weekNumber}`}>
                <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
                  {row.weekLabel}
                </p>
                <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-black">
                  {formatCount(row.websiteViews, true)} weergaven · {formatCount(row.sessions, true)} sessies
                </p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

function formatLinkedInTotals(
  totals: { reactions: number; likes: number; comments: number; reposts: number; posts: number },
): string {
  return `${totals.posts.toLocaleString('nl-NL')} posts — ${totals.reactions.toLocaleString('nl-NL')} reacties, ${totals.likes.toLocaleString('nl-NL')} likes, ${totals.comments.toLocaleString('nl-NL')} comments`
}

function ReportLinkedInEngagementSection({
  data,
}: {
  data?: ReportLinkedInEngagement
}) {
  if (!data?.hasSource) return null

  const totals = data.totals ?? {
    reactions: 0,
    likes: 0,
    comments: 0,
    reposts: 0,
    posts: 0,
  }

  return (
    <div className="space-y-[12px]">
      <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
        LinkedIn engagement (eigen posts)
      </p>
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        {formatLinkedInTotals(totals)}
      </p>
      {data.weekly.length > 0 && (
        <div className="flex flex-col gap-[8px]">
          {data.weekly.map((row) => (
            <div key={`${row.isoYear}-${row.weekNumber}`}>
              <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
                {row.weekLabel}
              </p>
              <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-black">
                {formatLinkedInTotals(row)}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function MoreDetail({ children }: { children: React.ReactNode }) {
  return (
    <details className="group border-t border-ce-black/10 pt-[16px]">
      <summary className="flex cursor-pointer list-none items-center gap-[6px] text-[14px] leading-[18px] font-semibold text-ce-black [&::-webkit-details-marker]:hidden">
        <span>Meer detail</span>
        <span className="inline-flex transition-transform group-open:rotate-180">
          <ArrowIcon direction="up" className="h-[9px] w-[9px]" />
        </span>
      </summary>
      <div className="space-y-[16px] pt-[16px]">{children}</div>
    </details>
  )
}

type ReportNavState = { periodStart?: string; periodEnd?: string }

/** Report-scherm — §5.8bis */
export function ReportPage() {
  const location = useLocation()
  const navState = (location.state as ReportNavState | null) ?? null
  const [periodStart, setPeriodStart] = useState(navState?.periodStart || defaultPeriodStart)
  const [periodEnd, setPeriodEnd] = useState(navState?.periodEnd || defaultPeriodEnd)
  const [scope, setScope] = useState<ReportScope>('brand')
  const [findabilityKind, setFindabilityKind] = useState<SearchQuestionKind>('geo')
  const [liveFindabilityGeo, setLiveFindabilityGeo] = useState<LlmVisibilityReport | null>(null)
  const [liveFindabilitySeo, setLiveFindabilitySeo] = useState<LlmVisibilityReport | null>(null)
  const [reportTitle, setReportTitle] = useState(() =>
    defaultReportTitle('brand', navState?.periodStart || defaultPeriodStart(), navState?.periodEnd || defaultPeriodEnd()),
  )
  const [scopeArticleId, setScopeArticleId] = useState('')
  const [articleOptions, setArticleOptions] = useState<Array<{ articleId: string; title: string }>>([])
  const [reports, setReports] = useState<ReportDto[]>([])
  const [trend, setTrend] = useState<ReportTrendPoint[]>([])
  const [activeReport, setActiveReport] = useState<ReportDto | null>(null)
  const [loading, setLoading] = useState(false)
  const [generating, setGenerating] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const {
    message: saveMessage,
    kind: saveNoticeKind,
    fading: saveNoticeFading,
    showSuccess: showSaveSuccess,
    clear: clearSaveNotice,
  } = useSaveNotice('Rapport opgeslagen bij Alle artikelen.')
  const [hbaProgress, setHbaProgress] = useState<HbaProgress | null>(null)
  const [homeDashboard, setHomeDashboard] = useState<HomeDashboard | null>(null)
  const [hbaLoading, setHbaLoading] = useState(() => isApiConfigured())
  const [homeLoading, setHomeLoading] = useState(() => isApiConfigured())

  useEffect(() => {
    if (!isApiConfigured()) {
      setHbaProgress(null)
      setHomeDashboard(null)
      setHbaLoading(false)
      setHomeLoading(false)
      return
    }
    setHbaLoading(true)
    void fetchHbaProgress()
      .then(setHbaProgress)
      .catch(() => setHbaProgress(null))
      .finally(() => setHbaLoading(false))
    setHomeLoading(true)
    void fetchHomeDashboardCached()
      .then(setHomeDashboard)
      .catch(() => setHomeDashboard(null))
      .finally(() => setHomeLoading(false))
  }, [])

  const load = useCallback(async () => {
    if (!isApiConfigured()) return
    setLoading(true)
    setError(null)
    try {
      const [list, options] = await Promise.all([fetchReports(), fetchReportArticleOptions()])
      setReports(list.reports)
      setTrend(list.trend ?? [])
      setArticleOptions(options.articles)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Laden mislukt')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (scope !== 'article' || scopeArticleId || articleOptions.length === 0) return
    setScopeArticleId(articleOptions[0].articleId)
  }, [scope, scopeArticleId, articleOptions])

  useEffect(() => {
    if (!isApiConfigured() || scope !== 'findability' || activeReport?.scope === 'findability') {
      return
    }
    void Promise.all([fetchLlmVisibility('geo'), fetchLlmVisibility('seo')])
      .then(([geo, seo]) => {
        setLiveFindabilityGeo(geo)
        setLiveFindabilitySeo(seo)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Vindbaarheidsdata laden mislukt')
      })
  }, [scope, activeReport?.scope])

  function handleScopeChange(nextScope: ReportScope) {
    setScope(nextScope)
    setReportTitle(defaultReportTitle(nextScope, periodStart, periodEnd))
  }

  async function handleGenerate() {
    const title = reportTitle.trim()
    if (!title) {
      setError('Vul een titel in voor het rapport.')
      return
    }
    setGenerating(true)
    setError(null)
    clearSaveNotice()
    try {
      const result = await generateReport({
        periodStart,
        periodEnd,
        scope,
        scopeArticleId: scope === 'article' ? scopeArticleId : undefined,
        title,
      })
      setActiveReport(result.report)
      setReports((prev) => [result.report, ...prev.filter((r) => r.id !== result.report.id)])
      void fetchReports().then((list) => setTrend(list.trend ?? []))
      showSaveSuccess(
        scope === 'findability'
          ? 'SEO/GEO-rapport opgeslagen bij Alle artikelen.'
          : undefined,
      )
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Genereren mislukt')
    } finally {
      setGenerating(false)
    }
  }

  async function openReport(id: string) {
    try {
      const result = await fetchReport(id)
      setActiveReport(result.report)
      clearSaveNotice()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Rapport laden mislukt')
    }
  }

  const articlePulldownValue = useMemo(() => {
    const match = articleOptions.find((opt) => opt.articleId === scopeArticleId)
    return match?.title ?? ''
  }, [articleOptions, scopeArticleId])

  const engagementTotals = useMemo(
    () => (activeReport ? reportEngagementTotals(activeReport) : null),
    [activeReport],
  )

  const kpiHighlights = useMemo(
    () => (activeReport && engagementTotals ? reportKpiHighlights(activeReport, engagementTotals) : []),
    [activeReport, engagementTotals],
  )

  const strategyCount = activeReport ? reportStrategyCount(activeReport) : 0
  const generalCount = activeReport ? reportGeneralCount(activeReport) : 0
  const articleTotal = activeReport?.articleCountTotal ?? strategyCount + generalCount
  const avgArticles =
    articleTotal && engagementTotals?.days
      ? Math.round((articleTotal / engagementTotals.days) * 10) / 10
      : 0

  const isEmpty =
    !loading && reports.length === 0 && !activeReport && articleOptions.length === 0

  const overviewMetricsLoading = hbaLoading || homeLoading
  // Dezelfde drempel als het Overzicht: zonder genoeg gescoorde posts geen diagnose (§2.5).
  const hasEnoughForDiagnosis = homeOverviewHasPbsMeters(hbaProgress)
  const showDiagnosisContent = !overviewMetricsLoading && hasEnoughForDiagnosis

  /** Bron voor de op-strategie-vergelijking: het geopende rapport, anders het laatste merkrapport. */
  const diagnosisReport = useMemo(
    () => activeReport ?? reports.find((report) => report.scope !== 'findability') ?? null,
    [activeReport, reports],
  )

  const isFindabilityReport = activeReport?.scope === 'findability'

  const findabilityScores = useMemo(() => {
    if (activeReport?.findabilitySnapshot) {
      const geo = activeReport.findabilitySnapshot.geo
      const seo = activeReport.findabilitySnapshot.seo
      return {
        score: findabilityCombinedScore(geo, seo),
        geoScore: findabilityKindScore(geo),
        seoScore: findabilityKindScore(seo),
      }
    }
    if (scope === 'findability') {
      return {
        score: findabilityCombinedScore(liveFindabilityGeo, liveFindabilitySeo),
        geoScore: findabilityKindScore(liveFindabilityGeo),
        seoScore: findabilityKindScore(liveFindabilitySeo),
      }
    }
    return null
  }, [activeReport, scope, liveFindabilityGeo, liveFindabilitySeo])

  return (
    <GridShell
      pageTitle="Rapport"
      activeNav="report"
      main={
        <div className={designStackClass}>
          {hbaProgress?.ok ? <BrandWatcherNotices progress={hbaProgress} /> : null}

          {overviewMetricsLoading ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">Laden…</p>
          ) : !hasEnoughForDiagnosis ? (
            <ReportNotEnoughData progress={hbaProgress} />
          ) : null}

          {showDiagnosisContent ? (
            <>
              <ReportDiagnosisSection
                hba={hbaProgress}
                contentrolMix={homeDashboard?.result.contentrolMix}
                latestReport={diagnosisReport}
              />

              {homeDashboard?.result ? (
                <ReportEngagementBreakdown
                  result={homeDashboard.result}
                  websiteAnalytics={activeReport?.websiteAnalytics}
                />
              ) : null}

              <ReportStrategyVsGeneric report={diagnosisReport} />

              {homeDashboard?.result &&
              (homeDashboard.result.strategyTrend.hasEnoughData ||
                homeDashboard.result.findability.hasMeasurement) ? (
                <section className={`${LIST_SECTION.dashboardSectionClass} space-y-[16px]`}>
                  <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Verloop over tijd</h2>
                  {homeDashboard.result.strategyTrend.hasEnoughData ? (
                    <HomeStrategyTrend data={homeDashboard.result.strategyTrend} />
                  ) : null}
                  {homeDashboard.result.findability.hasMeasurement ? (
                    <HomeFindabilityTrend data={homeDashboard.result.findability} />
                  ) : null}
                </section>
              ) : null}

              {homeDashboard?.result.sourcesReview ? (
                <ReportSourcesExpand review={homeDashboard.result.sourcesReview} />
              ) : null}

              {hbaProgress?.ok ? <BrandWatcherDetails progress={hbaProgress} /> : null}
            </>
          ) : null}

          {isEmpty ? (
            <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
              Nog geen rapporten
            </p>
          ) : (
            <>
          {/* Formulier blijft op veldbreedte; de diagnose erboven gebruikt de volle kolom. */}
          <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            Trendrapport over strategie-consistentie en engagement.
          </p>

          {hasEnoughForDiagnosis ? (
            <ReportTrendChart points={trend} />
          ) : (
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              Het verschilpercentage tussen posts op strategie en generieke posts tonen we vanaf{' '}
              {HBA_SCORED_POSTS_FOR_OVERVIEW} beoordeelde publicaties — dan is de vergelijking
              betrouwbaar genoeg.
            </p>
          )}

          <fieldset className="m-0 border-0 p-0">
            <legend className="mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
              Rapporttype
            </legend>
            <div className="flex flex-wrap gap-[16px]">
              <RadioOption
                name="report-scope"
                value="brand"
                label="Merk"
                checked={scope === 'brand'}
                onChange={() => handleScopeChange('brand')}
              />
              <RadioOption
                name="report-scope"
                value="article"
                label="Artikel"
                checked={scope === 'article'}
                onChange={() => handleScopeChange('article')}
              />
              <RadioOption
                name="report-scope"
                value="findability"
                label="Vindbaarheid"
                checked={scope === 'findability'}
                onChange={() => handleScopeChange('findability')}
              />
            </div>
          </fieldset>

          <div className="grid gap-[12px] sm:grid-cols-2">
            <FieldSingle
              id="report-start"
              label="Startdatum rapport"
              value={periodStart}
              onChange={setPeriodStart}
              type="date"
            />
            <FieldSingle
              id="report-end"
              label="Einddatum rapport"
              value={periodEnd}
              onChange={setPeriodEnd}
              type="date"
            />
          </div>

          <FieldSingle
            id="report-title"
            label="Rapporttitel"
            value={reportTitle}
            onChange={setReportTitle}
            placeholder="Bijv. Merkrapport aug 2026"
          />

          {scope === 'article' && (
            <div>
              <p className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black">
                Artikel kiezen
              </p>
              <Pulldown
                options={articleOptions.map((opt) => opt.title)}
                value={articlePulldownValue}
                placeholder="Kies een artikel"
                onChange={(title) => {
                  const match = articleOptions.find((opt) => opt.title === title)
                  if (match) setScopeArticleId(match.articleId)
                }}
              />
            </div>
          )}

          {scope === 'findability' && !isFindabilityReport && findabilityScores && (
            <FindabilityScoreSummary
              score={findabilityScores.score}
              geoScore={findabilityScores.geoScore}
              seoScore={findabilityScores.seoScore}
            />
          )}

          {scope === 'findability' && (
            <>
              <fieldset className="m-0 border-0 p-0">
                <legend className="mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
                  Vindbaarheidstype
                </legend>
                <div className="flex gap-[16px]">
                  <RadioOption
                    name="report-findability"
                    value="geo"
                    label="GEO (AI)"
                    checked={findabilityKind === 'geo'}
                    onChange={() => setFindabilityKind('geo')}
                  />
                  <RadioOption
                    name="report-findability"
                    value="seo"
                    label="SEO (Google)"
                    checked={findabilityKind === 'seo'}
                    onChange={() => setFindabilityKind('seo')}
                  />
                </div>
              </fieldset>
              <ReportFindabilitySection kind={findabilityKind} />
            </>
          )}

          <div className="mt-[18px]">
            <PillButton
              variant="main"
              disabled={generating}
              loading={generating}
              onClick={() => void handleGenerate()}
            >
              {scope === 'findability' ? 'Rapport opslaan' : 'Rapport genereren'}
            </PillButton>
          </div>
          </div>

          {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
          {loading && <p className="m-0 text-[14px] leading-[18px]">Laden…</p>}

          {activeReport && (
            <section className={`space-y-[16px] ${LIST_SECTION.dashboardSectionClass}`}>
              <h2 className="m-0 text-[18px] font-semibold leading-[24px] text-ce-black">
                {reportTitleForKlant(activeReport.title)}
              </h2>

              {isFindabilityReport ? (
                <>
                  <FindabilityScoreSummary
                    score={findabilityScores?.score ?? null}
                    geoScore={findabilityScores?.geoScore ?? null}
                    seoScore={findabilityScores?.seoScore ?? null}
                  />
                  <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                    Periode {activeReport.periodStart} – {activeReport.periodEnd}
                  </p>
                  {activeReport.narrative && (
                    <p className="m-0 text-[14px] leading-[21px] text-ce-black">{activeReport.narrative}</p>
                  )}
                  <ReportFindabilitySection
                    kind="geo"
                    snapshot={activeReport.findabilitySnapshot?.geo ?? null}
                  />
                  <ReportFindabilitySection
                    kind="seo"
                    snapshot={activeReport.findabilitySnapshot?.seo ?? null}
                  />
                </>
              ) : (
                <>
              <ReportDailyChart rows={activeReport.dailyBreakdown ?? []} />

              <div>
                <p className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
                  Belangrijkste KPI&apos;s
                  {activeReport.brandGoalCategory
                    ? ` voor ${brandGoalLabel(activeReport.brandGoalCategory).toLowerCase()}`
                    : ''}
                </p>
                {!activeReport.brandGoalCategory && (
                  <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-black">
                    Stel doelen in bij Targets — KPI&apos;s in het rapport sluiten daar straks op aan.
                  </p>
                )}
                <div className="grid gap-[12px] sm:grid-cols-3">
                  {kpiHighlights.map((kpi) => (
                    <MetricBlock key={kpi.label} label={kpi.label} value={kpi.value} />
                  ))}
                </div>
              </div>

              <div className="grid gap-[12px] sm:grid-cols-3">
                <MetricBlock
                  label="Posts op-strategie"
                  value={String(strategyCount)}
                />
                <MetricBlock
                  label="Posts onder drempel"
                  value={String(generalCount)}
                />
                {hasEnoughForDiagnosis ? (
                  <MetricBlock
                    label="Engagementverschil"
                    value={formatReportDiffPct(activeReport.engagementDiffPct)}
                  />
                ) : (
                  <MetricBlock
                    label="Engagementverschil"
                    value="—"
                  />
                )}
              </div>

              {activeReport.bestPerformingPost && (
                <div className={LIST_SECTION.itemClass}>
                  <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
                    Best presterende artikel
                  </p>
                  <p className="m-0 pt-[6px] text-[14px] leading-[18px] text-ce-black">
                    {String(activeReport.bestPerformingPost.headline ?? '—')}
                  </p>
                </div>
              )}

              {activeReport.narrative && (
                <div className={LIST_SECTION.itemClass}>
                  <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Inzicht</p>
                  <p className="m-0 whitespace-pre-wrap pt-[8px] text-[14px] leading-[21px] text-ce-black">
                    {activeReport.narrative}
                  </p>
                </div>
              )}

              <MoreDetail>
                <ReportWebsiteAnalyticsSection data={activeReport.websiteAnalytics} />

                <ReportLinkedInEngagementSection data={activeReport.linkedInEngagement} />

                <div className="grid gap-[12px] sm:grid-cols-2">
                  <ReportContentrolRadar values={activeReport.angleDistribution} />
                  <ReportToneRadar values={activeReport.toneDistribution} />
                </div>

                {engagementTotals && (
                  <div className="space-y-[12px]">
                    <MetricPair label="Gepubliceerde artikelen" total={articleTotal} average={avgArticles} />
                    <MetricPair
                      label="Weergaven"
                      total={engagementTotals.views}
                      average={Math.round((engagementTotals.views / engagementTotals.days) * 10) / 10}
                    />
                    <MetricPair
                      label="Likes"
                      total={engagementTotals.likes}
                      average={Math.round((engagementTotals.likes / engagementTotals.days) * 10) / 10}
                    />
                    <MetricPair
                      label="Opmerkingen"
                      total={engagementTotals.comments}
                      average={
                        Math.round((engagementTotals.comments / engagementTotals.days) * 10) / 10
                      }
                    />
                  </div>
                )}

                <div className="grid gap-[12px] sm:grid-cols-2">
                  <MetricBlock
                    label="Engagement op-strategie"
                    value={String(activeReport.engagementOnStrategy ?? 0)}
                  />
                  <MetricBlock
                    label="Engagement generiek"
                    value={String(activeReport.engagementGeneral ?? 0)}
                  />
                </div>

                {activeReport.medianOnStrategy != null && (
                  <div className="grid gap-[12px] sm:grid-cols-2">
                    <MetricBlock
                      label="Mediaan op-strategie"
                      value={String(activeReport.medianOnStrategy)}
                    />
                    <MetricBlock
                      label="Mediaan generiek"
                      value={String(activeReport.medianGeneral ?? 0)}
                    />
                  </div>
                )}
              </MoreDetail>
                </>
              )}

              <div className={pillActionsRowClass}>
                {!isFindabilityReport && (
                  <>
                    <PillButton
                      variant="main"
                      onClick={() => {
                        window.location.href = reportPdfUrl(activeReport.id)
                      }}
                    >
                      PDF downloaden
                    </PillButton>
                  </>
                )}
                <PillButton variant="second" onClick={() => setActiveReport(null)}>
                  Terug
                </PillButton>
              </div>
              <SaveNotice message={saveMessage} kind={saveNoticeKind} fading={saveNoticeFading} />
            </section>
          )}

          {reports.length > 0 && (
            <section className={`border-t border-ce-black/10 pt-[16px]`}>
              <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px]">Eerdere rapporten</p>
              <ul className="m-0 list-none space-y-[8px] p-0">
                {reports.map((report) => (
                  <li key={report.id}>
                    <button
                      type="button"
                      onClick={() => void openReport(report.id)}
                      className={`text-left ${statusRowActionClass}`}
                    >
                      {reportTitleForKlant(report.title)}
                    </button>
                  </li>
                ))}
              </ul>
            </section>
          )}
            </>
          )}
        </div>
      }
    />
  )
}
