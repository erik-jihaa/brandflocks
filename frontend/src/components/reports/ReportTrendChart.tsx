import type { ReportTrendPoint } from '../../api/reports'

function formatDiff(value: number | null): string {
  if (value == null || Number.isNaN(value)) return '—'
  const sign = value > 0 ? '+' : ''
  return `${sign}${Math.round(value)}%`
}

/** Report — trendlijn engagement_diff_pct over meerdere rapportperiodes (briefing 2a) */
export function ReportTrendChart({ points }: { points: ReportTrendPoint[] }) {
  if (points.length < 2) return null

  const values = points.map((p) => p.engagementDiffPct ?? 0)
  const maxAbs = Math.max(...values.map((v) => Math.abs(v)), 1)
  const barMaxHeight = 60

  return (
    <div className="space-y-[8px]">
      <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
        Verschilpercentage op-strategie vs. generiek — laatste {points.length} rapportperiodes
      </p>
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        Positief betekent: posts die op jullie merkstrategie scoren presteren gemiddeld beter dan
        generieke posts in dezelfde periode.
      </p>
      <div className="flex items-end gap-[16px] pt-[8px]">
        {points.map((point) => {
          const value = point.engagementDiffPct ?? 0
          const height = Math.max(4, (Math.abs(value) / maxAbs) * barMaxHeight)
          const color = value >= 0 ? 'var(--color-ce-green-text)' : 'var(--color-ce-orange)'
          return (
            <div key={point.reportId} className="flex flex-col items-center gap-[6px]">
              <p className="m-0 text-[13px] leading-[16px] font-semibold text-ce-black">
                {formatDiff(point.engagementDiffPct)}
              </p>
              <div
                className="w-[24px] rounded-[6px]"
                style={{ height, backgroundColor: color }}
                aria-hidden
              />
              <p className="m-0 text-[12px] leading-[14px] text-ce-black/50 capitalize">
                {point.monthLabel || '—'}
              </p>
            </div>
          )
        })}
      </div>
    </div>
  )
}
