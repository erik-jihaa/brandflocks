import { useNavigate } from 'react-router-dom'
import { ArrowIcon } from './ArrowIcon'
import type { HomeStrategyTrend as HomeStrategyTrendData } from '../api/home'

type HomeStrategyTrendProps = {
  data: HomeStrategyTrendData
}

const SPARKLINE_WIDTH = 100
const SPARKLINE_HEIGHT = 36

function sparklinePoints(values: number[]): string {
  if (values.length < 2) return ''
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1
  const stepX = SPARKLINE_WIDTH / (values.length - 1)

  return values
    .map((value, index) => {
      const x = index * stepX
      const y = SPARKLINE_HEIGHT - ((value - min) / range) * SPARKLINE_HEIGHT
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function strategyEmptyMessage(data: HomeStrategyTrendData): string {
  const { publishedPostsCount, weeksWithData, minWeeksRequired, currentPct } = data
  const weeksNeeded = Math.max(0, minWeeksRequired - weeksWithData)

  if (publishedPostsCount === 0) {
    return 'Publiceer posts om te zien hoeveel procent je merk versterkt.'
  }

  if (currentPct !== null && weeksNeeded > 0) {
    const weekLabel = weeksWithData === 1 ? 'week' : 'weken'
    const needLabel = weeksNeeded === 1 ? 'week' : 'weken'
    return `${currentPct}% op-strategie (${weeksWithData} ${weekLabel} met posts) — trend zichtbaar na ${minWeeksRequired} ${needLabel}.`
  }

  return `Nog ${weeksNeeded} ${weeksNeeded === 1 ? 'week' : 'weken'} met gepubliceerde posts nodig voor een trend.`
}

/** Home — 8-weken sparkline "Hoe gaat het" (briefing/cursor-overview-report.md, DEEL 1) */
export function HomeStrategyTrend({ data }: HomeStrategyTrendProps) {
  const navigate = useNavigate()
  const { currentPct, trendDirection, weeklyData, hasEnoughData } = data

  const lineColor = trendDirection === 'down' ? 'var(--color-ce-orange)' : 'var(--color-ce-green-text)'
  const arrowDirection = trendDirection === 'up' ? 'up' : trendDirection === 'down' ? 'down' : 'right'

  function goToReport() {
    const periodEnd = new Date()
    const periodStart = new Date()
    periodStart.setDate(periodStart.getDate() - 8 * 7)
    navigate('/report', {
      state: { periodStart: isoDate(periodStart), periodEnd: isoDate(periodEnd) },
    })
  }

  return (
    <section className="min-w-0">
      <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">Hoe gaat het</p>

      {!hasEnoughData ? (
        <div className="mt-[8px]">
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">{strategyEmptyMessage(data)}</p>
          <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-black">
            {data.publishedPostsCount} post{data.publishedPostsCount === 1 ? '' : 's'} in 8 weken
          </p>
        </div>
      ) : (
        <button
          type="button"
          onClick={goToReport}
          className="mt-[8px] flex w-full cursor-pointer items-end justify-between gap-[12px] border-0 bg-transparent p-0 text-left"
          aria-label="Bekijk strategie-trend in Report"
        >
          <span className="min-w-0">
            <span className="inline-flex items-center gap-[6px]">
              <span className="text-[24px] font-semibold leading-[28px] text-ce-black">
                {currentPct}% op-strategie
              </span>
              <ArrowIcon direction={arrowDirection} />
            </span>
            <p className="m-0 mt-[2px] text-[14px] leading-[18px] text-ce-black">
              8 weken · posts die je merk versterken
            </p>
          </span>
          <svg
            width={SPARKLINE_WIDTH}
            height={SPARKLINE_HEIGHT}
            viewBox={`0 0 ${SPARKLINE_WIDTH} ${SPARKLINE_HEIGHT}`}
            role="img"
            aria-label="Sparkline strategie-trend, 8 weken"
            className="shrink-0"
          >
            <polyline
              points={sparklinePoints(weeklyData)}
              fill="none"
              stroke={lineColor}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </button>
      )}
    </section>
  )
}
