import { Link } from 'react-router-dom'
import type { HbaProgress } from '../../api/hbaV2'
import type { HomeContentrolMix } from '../../api/home'
import type { ReportDto } from '../../api/reports'
import { LIST_SECTION } from '../../layout/grid.constants'
import { BrandPositionTrendChart } from '../BrandPositionTrendChart'
import { ReportContentrolBars } from './ReportContentrolBars'
import { ReportStoryTriangleRadar } from './ReportRadarChart'
import {
  contentrolFinding,
  pbsTrendFinding,
  reportSummarySentences,
  storyTriangleFinding,
} from '../../utils/reportFindings'

/** §2.1 — diagnose in twee tot drie zinnen, vóór de eerste grafiek. */
function ReportSummaryBlock({ sentences }: { sentences: string[] }) {
  if (sentences.length === 0) return null

  return (
    <>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Samenvatting</h2>
      <p className="m-0 pt-[8px] text-[14px] leading-[21px] text-ce-black">{sentences.join(' ')}</p>
    </>
  )
}

type FindingBlockProps = {
  title: string
  finding: string
  visual: React.ReactNode
  visualWidthClass?: string
  actionLabel?: string
  actionPath?: string
}

/** §2.2 — visual klein aan één kant, de bevinding in gewone taal ernaast. */
function ReportFindingBlock({
  title,
  finding,
  visual,
  visualWidthClass = 'sm:w-[200px]',
  actionLabel,
  actionPath,
}: FindingBlockProps) {
  return (
    <div className={`${LIST_SECTION.itemClass} pt-[20px]`}>
      <div className="flex flex-col gap-[16px] sm:flex-row sm:items-start">
        <div className={`w-full shrink-0 ${visualWidthClass}`}>{visual}</div>
        <div className="min-w-0 flex-1">
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">{title}</p>
          <p className="m-0 pt-[6px] text-[14px] leading-[21px] text-ce-black">{finding}</p>
          {actionLabel && actionPath ? (
            <Link
              to={actionPath}
              className="mt-[10px] inline-block text-[14px] font-semibold leading-[18px] text-ce-blue underline-offset-[3px] hover:underline"
            >
              {actionLabel} →
            </Link>
          ) : null}
        </div>
      </div>
    </div>
  )
}

type ReportDiagnosisSectionProps = {
  hba: HbaProgress | null
  contentrolMix: HomeContentrolMix | null | undefined
  latestReport: ReportDto | null
}

export function ReportDiagnosisSection({
  hba,
  contentrolMix,
  latestReport,
}: ReportDiagnosisSectionProps) {
  const summary = reportSummarySentences(hba, contentrolMix, latestReport)
  const storyMetrics = (hba?.trackerDetails?.storyTriangle ?? [])
    .filter((metric): metric is { label: string; avg: number } => metric.avg != null)
  const displayMode = hba?.scoreDisplayMode ?? 'decimal'
  const storyText = storyTriangleFinding(hba, displayMode)
  const contentrolText = contentrolFinding(contentrolMix)
  const pbsHistory = hba?.pbsHistory ?? []
  const pbsText = pbsTrendFinding(pbsHistory, displayMode, hba)

  const hasFindings = storyText !== null || contentrolText !== null || pbsText !== null
  if (summary.length === 0 && !hasFindings) return null

  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <ReportSummaryBlock sentences={summary} />

      {storyText && storyMetrics.length >= 3 ? (
        <ReportFindingBlock
          title="Hoe je merkverhaal terugkomt"
          finding={storyText}
          visual={<ReportStoryTriangleRadar metrics={storyMetrics} />}
          actionLabel="Waar dit vandaan komt"
          actionPath="/brandarchitecture"
        />
      ) : null}

      {contentrolText ? (
        <ReportFindingBlock
          title="Verdeling van contentrollen"
          finding={contentrolText}
          visual={<ReportContentrolBars mix={contentrolMix} />}
          visualWidthClass="sm:w-[300px]"
          actionLabel="Plan een positioneringsstuk"
          actionPath="/theme"
        />
      ) : null}

      {pbsText ? (
        <ReportFindingBlock
          title="Merkcijfer over tijd"
          finding={pbsText}
          visual={<BrandPositionTrendChart history={pbsHistory} compact />}
        />
      ) : null}
    </section>
  )
}
