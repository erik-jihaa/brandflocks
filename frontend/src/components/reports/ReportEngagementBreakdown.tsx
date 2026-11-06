import type { HomeResult } from '../../api/home'
import type { ReportWebsiteAnalytics } from '../../api/reports'
import { LIST_SECTION } from '../../layout/grid.constants'
import { HomeEngagementTrend } from '../HomeEngagementTrend'
import { directionLabel, seriesDirection } from '../../utils/trendDirection'

function nl(value: number): string {
  return value.toLocaleString('nl-NL')
}

function MetricLine({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-baseline justify-between gap-[12px]">
      <p className="m-0 min-w-0 text-[14px] leading-[18px] text-ce-black">{label}</p>
      <p className="m-0 shrink-0 text-[14px] font-semibold leading-[18px] text-ce-black">{value}</p>
    </div>
  )
}

type ReportEngagementBreakdownProps = {
  result: HomeResult
  websiteAnalytics?: ReportWebsiteAnalytics
}

/** §2.3 — engagement per kanaal: LinkedIn over 90 dagen, GA4 met richting, en het verloop. */
export function ReportEngagementBreakdown({
  result,
  websiteAnalytics,
}: ReportEngagementBreakdownProps) {
  const linkedIn = result.linkedInEngagement
  const last90 = linkedIn.last90Days
  const ga4Views = result.engagementTrend.breakdown.articles.views

  const ga4Weekly = (websiteAnalytics?.weekly ?? [])
    .map((week) => week.websiteViews ?? 0)
  const ga4Direction = directionLabel(seriesDirection(ga4Weekly))

  const hasLinkedIn = linkedIn.hasSource
  const hasGa4 = result.hasAnalytics || ga4Views > 0
  if (!hasLinkedIn && !hasGa4 && result.engagementTrend.totalEngagement === 0) return null

  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Engagement per kanaal</h2>

      <div className="grid gap-[20px] pt-[12px] sm:grid-cols-2">
        {hasLinkedIn ? (
          <div>
            <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
              LinkedIn — laatste 90 dagen
            </p>
            <div className="space-y-[4px]">
              <MetricLine label="Posts" value={nl(last90.posts)} />
              <MetricLine label="Reacties" value={nl(last90.reactions)} />
              <MetricLine label="Likes" value={nl(last90.likes)} />
              <MetricLine label="Opmerkingen" value={nl(last90.comments)} />
            </div>
          </div>
        ) : null}

        {hasGa4 ? (
          <div>
            <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
              Website-weergaven (GA4)
            </p>
            <p className="m-0 text-[20px] font-semibold leading-[24px] text-ce-black">
              {nl(ga4Views)}
              {ga4Direction ? (
                <span className="ml-[6px] text-[14px] font-normal leading-[18px] text-ce-black">
                  {ga4Direction}
                </span>
              ) : null}
            </p>
            <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">
              Over {result.engagementTrend.weeksSpan} weken op gepubliceerde artikelen
              {result.websiteViewsThisWeek != null
                ? ` · deze week ${nl(result.websiteViewsThisWeek)}`
                : ''}
            </p>
            {ga4Direction === undefined ? (
              <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">
                Richting volgt zodra een rapport met vier weken GA4-data is gegenereerd.
              </p>
            ) : null}
          </div>
        ) : null}
      </div>

      {result.engagementTrend.hasEnoughData ? (
        <div className="pt-[16px]">
          <HomeEngagementTrend data={result.engagementTrend} />
        </div>
      ) : null}
    </section>
  )
}
