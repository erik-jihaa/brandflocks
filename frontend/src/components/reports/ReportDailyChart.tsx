export type DailyBreakdownRow = {
  publish_date?: string
  publishDate?: string
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

function pick(row: DailyBreakdownRow, snake: keyof DailyBreakdownRow, camel: keyof DailyBreakdownRow): number {
  const v = row[snake] ?? row[camel]
  return typeof v === 'number' && Number.isFinite(v) ? v : 0
}

function dayLabel(row: DailyBreakdownRow): string {
  const raw = String(row.publish_date ?? row.publishDate ?? '')
  if (raw.length >= 10) return raw.slice(5, 10)
  return raw || '?'
}

type Props = {
  rows: DailyBreakdownRow[]
  height?: number
}

/** Gestapelde dagelijkse engagement — §5.8bis.1 */
export function ReportDailyChart({ rows, height = 220 }: Props) {
  if (!rows.length) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        Geen dagelijkse uitsplitsing beschikbaar voor deze periode.
      </p>
    )
  }

  const parsed = rows.map((row) => ({
    label: dayLabel(row),
    strategy: [
      pick(row, 'strategy_views', 'strategyViews'),
      pick(row, 'strategy_likes', 'strategyLikes'),
      pick(row, 'strategy_comments', 'strategyComments'),
    ],
    general: [
      pick(row, 'general_views', 'generalViews'),
      pick(row, 'general_likes', 'generalLikes'),
      pick(row, 'general_comments', 'generalComments'),
    ],
  }))

  const maxTotal = Math.max(
    ...parsed.flatMap((d) => [d.strategy.reduce((a, b) => a + b, 0), d.general.reduce((a, b) => a + b, 0)]),
    1,
  )

  const chartW = Math.max(320, parsed.length * 36)
  const pad = { top: 12, right: 12, bottom: 28, left: 36 }
  const innerH = height - pad.top - pad.bottom
  const groupW = (chartW - pad.right) / parsed.length
  const barW = Math.min(12, groupW * 0.32)

  const strategyColors = ['#52ffa8', '#89adaa', '#eae6dc']
  const generalColors = ['#2e2014', '#ada695', '#c17156']

  function stackedBar(
    values: number[],
    x: number,
    colors: string[],
  ) {
    let y = pad.top + innerH
    return values.map((value, i) => {
      const h = (value / maxTotal) * innerH
      y -= h
      return (
        <rect
          key={`${x}-${i}`}
          x={x}
          y={y}
          width={barW}
          height={Math.max(0, h)}
          fill={colors[i]}
          rx={2}
        />
      )
    })
  }

  return (
    <div className="overflow-x-auto">
      <svg width={chartW + pad.right} height={height} role="img" aria-label="Daily engagement chart">
        <text x={pad.right} y={14} className="fill-ce-black text-[12px] font-semibold">
          Engagement op strategie
        </text>
        {parsed.map((day, index) => {
          const gx = pad.right + index * groupW + groupW * 0.2
          return (
            <g key={day.label + index}>
              {stackedBar(day.strategy, gx, strategyColors)}
              {stackedBar(day.general, gx + barW + 4, generalColors)}
              <text
                x={gx + barW}
                y={height - 6}
                textAnchor="middle"
                className="fill-ce-black/50 text-[10px]"
              >
                {day.label}
              </text>
            </g>
          )
        })}
        <text x={pad.right + 4} y={pad.top + 10} className="fill-ce-black/50 text-[10px]">
          Op strategie
        </text>
        <text x={pad.right + barW + 8} y={pad.top + 10} className="fill-ce-black/50 text-[10px]">
          Algemeen
        </text>
      </svg>
      <div className="mt-[6px] flex flex-wrap gap-[12px] text-[11px] text-ce-black">
        <span>Op strategie: views / likes / comments</span>
        <span>Algemeen: views / likes / comments</span>
      </div>
    </div>
  )
}
