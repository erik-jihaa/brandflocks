import type { ReportDto } from '../../api/reports'
import { LIST_SECTION } from '../../layout/grid.constants'
import { strategyVsGenericFinding } from '../../utils/reportFindings'

function nl(value: number): string {
  return value.toLocaleString('nl-NL', { maximumFractionDigits: 1 })
}

/** §2.4 — jouw content op-strategie tegen jouw generieke content. */
export function ReportStrategyVsGeneric({ report }: { report: ReportDto | null }) {
  const finding = strategyVsGenericFinding(report)
  if (!finding) return null

  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>
        Jouw content op-strategie versus generiek
      </h2>
      <p className="m-0 pt-[8px] text-[14px] leading-[21px] text-ce-black">{finding.message}</p>
      <p className="m-0 pt-[8px] text-[14px] leading-[18px] text-ce-black">
        Op-strategie: {nl(finding.onStrategy)} gemiddeld over {finding.strategyCount} posts · generiek:{' '}
        {nl(finding.general)} over {finding.generalCount} posts.
      </p>
    </section>
  )
}
